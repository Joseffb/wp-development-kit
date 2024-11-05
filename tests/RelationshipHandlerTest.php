<?php

use PHPUnit\Framework\TestCase;
use WDK\PostInterface;
use WDK\RelationshipHandler;

class RelationshipHandlerTest extends TestCase
{
    protected static $parent_post_id;
    protected static $child_post_id;

    public static function setUpBeforeClass(): void
    {
        // Create a parent post
        $parent_post_arr = [
            'post_title'   => 'Parent Post',
            'post_content' => 'This is the parent post.',
            'post_status'  => 'publish',
        ];
        self::$parent_post_id = wp_insert_post($parent_post_arr);

        // Create a child post
        $child_post_arr = [
            'post_title'   => 'Child Post',
            'post_content' => 'This is the child post.',
            'post_status'  => 'publish',
            'post_parent'  => self::$parent_post_id,
        ];
        self::$child_post_id = wp_insert_post($child_post_arr);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up
        wp_delete_post(self::$child_post_id, true);
        wp_delete_post(self::$parent_post_id, true);
    }

    public function testGetChildren()
    {
        $postInterface = PostInterface::post(self::$parent_post_id);
        $children = $postInterface->relationships->children();
        $this->assertCount(1, $children);
        $this->assertEquals('Child Post', $children[0]->post_title);
    }

    public function testGetParent()
    {
        $postInterface = PostInterface::post(self::$child_post_id);
        $parent = $postInterface->relationships->parent();
        $this->assertNotNull($parent);
        $this->assertEquals('Parent Post', $parent->post_title);
    }
}
