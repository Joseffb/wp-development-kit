<?php

use PHPUnit\Framework\TestCase;
use WDK\PostInterface;
use WDK\MetaHandler;

class PostInterfaceTest extends TestCase
{
    protected static $post_id;

    public static function setUpBeforeClass(): void
    {
        // Set up the testing environment
        // Create a test post
        $post_arr = [
            'post_title' => 'Test Post',
            'post_content' => 'This is a test post.',
            'post_status' => 'publish',
        ];
        self::$post_id = wp_insert_post($post_arr);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up after tests
        wp_delete_post(self::$post_id, true);
    }

    public function testPostRetrievalById()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $this->assertInstanceOf(stdClass::class, $postInterface);
        $this->assertEquals('Test Post', $postInterface->post->post_title);
    }

    public function testPostUpdate()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $postInterface->post->post_title = 'Updated Test Post';
        $this->assertEquals('Updated Test Post', get_post(self::$post_id)->post_title);
    }

    public function testPostDeletion()
    {
        $post_id = wp_insert_post([
            'post_title' => 'Temporary Post',
            'post_content' => 'This post will be deleted.',
            'post_status' => 'publish',
        ]);
        $postInterface = PostInterface::post($post_id);
        $result = $postInterface->post->delete();
        $this->assertNotNull($result);
        $this->assertNull(get_post($post_id));
    }
}
