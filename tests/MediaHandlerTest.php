<?php


use PHPUnit\Framework\TestCase;
use WDK\PostInterface;
use WDK\MediaHandler;

class MediaHandlerTest extends TestCase
{
    protected static $post_id;
    protected static $attachment_id;

    public static function setUpBeforeClass(): void
    {
        // Create a test post
        $post_arr = [
            'post_title' => 'Media Test Post',
            'post_content' => 'Testing media handling.',
            'post_status' => 'publish',
        ];
        self::$post_id = wp_insert_post($post_arr);

        // Create a test attachment
        $attachment = [
            'post_mime_type' => 'image/jpeg',
            'post_title' => 'Test Image',
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => self::$post_id,
        ];
        self::$attachment_id = wp_insert_attachment($attachment, __DIR__ . '/assets/test-image.jpg', self::$post_id);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up
        wp_delete_attachment(self::$attachment_id, true);
        wp_delete_post(self::$post_id, true);
    }

    public function testGetAttachments()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $images = $postInterface->media->image();
        $this->assertCount(1, $images);
        $this->assertEquals('Test Image', $images[0]->post_title);
    }

    public function testFeaturedImage()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $postInterface->media->set_featured_image(self::$attachment_id);
        $featured_image = $postInterface->media->get_featured_image();
        $this->assertEquals(self::$attachment_id, $featured_image->ID);
    }

    public function testAttachmentMetadata()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $images = $postInterface->media->image();
        $metadata = $images[0]->metadata;
        $this->assertIsArray($metadata);
    }
}
