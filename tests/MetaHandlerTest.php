<?php

use PHPUnit\Framework\TestCase;
use WDK\PostInterface;
use WDK\MetaHandler;

class MetaHandlerTest extends TestCase
{
    protected static $post_id;

    public static function setUpBeforeClass(): void
    {
        // Create a test post
        $post_arr = [
            'post_title'   => 'Meta Test Post',
            'post_content' => 'Testing meta handling.',
            'post_status'  => 'publish',
        ];
        self::$post_id = wp_insert_post($post_arr);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up
        wp_delete_post(self::$post_id, true);
    }

    public function testMetaGetSet()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $postInterface->meta->test_meta_key = 'test_value';
        $this->assertEquals('test_value', get_post_meta(self::$post_id, 'test_meta_key', true));
        $this->assertEquals('test_value', $postInterface->meta->test_meta_key);
    }

    public function testMetaUpdate()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $postInterface->meta->update('test_meta_key', 'updated_value');
        $this->assertEquals('updated_value', get_post_meta(self::$post_id, 'test_meta_key', true));
    }

    public function testMetaDelete()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $postInterface->meta->delete('test_meta_key');
        $this->assertEmpty(get_post_meta(self::$post_id, 'test_meta_key', true));
    }
}
