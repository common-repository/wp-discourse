<?php
/**
 * Class SyncDiscourseTopicTest
 *
 * @package WPDiscourse
 */

namespace WPDiscourse\Test;

use WPDiscourse\SyncDiscourseTopic\SyncDiscourseTopic;
use WPDiscourse\Test\UnitTest;

/**
 * SyncDiscourseTopic test case.
 */
class SyncDiscourseTopicTest extends UnitTest {

    /**
     * Instance of SyncDiscourseTopic.
     *
     * @access protected
     * @var \WPDiscourse\SyncDiscourseTopic\SyncDiscourseTopic
     */
    protected $sync_topic;

    /**
     * Request
     *
     * @access protected
     * @var WP_REST_Request
     */
    protected $request;

    /**
     * Signaure
     *
     * @access protected
     * @var string
     */
    protected $signature;

    /**
     * Payload
     *
     * @access protected
     * @var array
     */
    protected $payload;

    /**
     * Setup each test.
     */
    public function setUp(): void {
        parent::setUp();

        self::$plugin_options['webhook-secret']        = '1234567891011';
        self::$plugin_options['use-discourse-webhook'] = 1;

        $this->sync_topic = new SyncDiscourseTopic();
        $this->sync_topic->setup_options( self::$plugin_options );
        $this->sync_topic->setup_logger();

        $this->payload   = $this->response_body_file( 'webhook_post' );
        $this->signature = hash_hmac( 'sha256', $this->payload, self::$plugin_options['webhook-secret'] );

        $this->request = new \WP_REST_Request();
        $this->request->set_header( 'Content-Type', 'application/json' );
        $this->request->set_header( 'X-Discourse-Event-Signature', "sha256={$this->signature}" );
        $this->request->set_header( 'X-Discourse-Event-Type', 'post' );
        $this->request->set_header( 'X-Discourse-Event', 'post_created' );
        $this->request->set_body( $this->payload );
  	}

    /**
     * update_topic_content handles webhook results correctly.
     */
    public function test_update_topic_content() {
        // Setup the posts
        $post_id        = wp_insert_post( self::$post_atts, false, false );
        $discourse_post = json_decode( $this->payload )->post;

        // Setup the post meta
        $discourse_topic_id = $discourse_post->topic_id;
        update_post_meta( $post_id, 'discourse_topic_id', $discourse_topic_id );

        // Perform update
        $response = $this->sync_topic->update_topic_content( $this->request );

        // Ensure the correct response is returned.
        $this->assertEquals( json_decode( $response )->success, true );
        $this->assertEquals( json_decode( $response )->message, 'The posts have been updated.' );

        // Ensure the post meta is updated correctly.
        $this->assertEquals( get_post_meta( $post_id, 'wpdc_sync_post_comments', true ), 1 );
        $this->assertEquals( get_post_meta( $post_id, 'discourse_comments_count', true ), '2' );

        // Cleanup
        wp_delete_post( $post_id );
    }

    /**
     * update_topic_content throws an error when the webhook signature is invalid.
     */
    public function test_update_topic_content_invalid_signature() {
        // Setup invalid signature
        self::$plugin_options['webhook-secret'] = '123456789101112';
        $this->sync_topic->setup_options( self::$plugin_options );

        // Setup the posts
        $post_id        = wp_insert_post( self::$post_atts, false, false );
        $discourse_post = json_decode( $this->payload )->post;

        // Setup the post meta
        $discourse_topic_id = $discourse_post->topic_id;
        update_post_meta( $post_id, 'discourse_topic_id', $discourse_topic_id );

        // Perform update
        $response = $this->sync_topic->update_topic_content( $this->request );

        // Ensure the correct response is returned.
        $this->assertEquals( json_decode( $response )->success, false );
        $this->assertEquals( json_decode( $response )->message, 'The webhook was not verified.' );

        // Ensure the comment metadata is not changed
        $this->assertEquals( get_post_meta( $post_id, 'wpdc_sync_post_comments', true ), null );

        // Cleanup
        wp_delete_post( $post_id );
    }
}
