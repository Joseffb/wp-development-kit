<?php


use PHPUnit\Framework\TestCase;
use WDK\PostInterface;
use WDK\TaxonomyHandler;

class TaxonomyHandlerTest extends TestCase
{
    protected static $post_id;
    protected static $term_id;

    public static function setUpBeforeClass(): void
    {
        // Register a custom taxonomy for testing
        register_taxonomy('test_taxonomy', 'post', [
            'label' => 'Test Taxonomy',
            'public' => true,
            'hierarchical' => true,
        ]);

        // Create a test term
        $term = wp_insert_term('Test Term', 'test_taxonomy');
        self::$term_id = $term['term_id'];

        // Create a test post
        $post_arr = [
            'post_title' => 'Taxonomy Test Post',
            'post_content' => 'Testing taxonomy handling.',
            'post_status' => 'publish',
        ];
        self::$post_id = wp_insert_post($post_arr);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up
        wp_delete_term(self::$term_id, 'test_taxonomy');
        wp_delete_post(self::$post_id, true);
        unregister_taxonomy('test_taxonomy');
    }

    public function testTaxonomyAssignment()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $postInterface->taxonomy->test_taxonomy->update('Test Term');
        $terms = wp_get_object_terms(self::$post_id, 'test_taxonomy', ['fields' => 'names']);
        $this->assertContains('Test Term', $terms);
    }

    public function testTaxonomyRemoval()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $postInterface->taxonomy->test_taxonomy->delete('Test Term');
        $terms = wp_get_object_terms(self::$post_id, 'test_taxonomy', ['fields' => 'names']);
        $this->assertNotContains('Test Term', $terms);
    }

    public function testTermMeta()
    {
        $term_meta = new \WDK\TermMetaHandler(self::$term_id);
        $term_meta->test_meta_key = 'test_value';
        $this->assertEquals('test_value', get_term_meta(self::$term_id, 'test_meta_key', true));

        $term_meta->update('test_meta_key', 'updated_value');
        $this->assertEquals('updated_value', get_term_meta(self::$term_id, 'test_meta_key', true));

        $term_meta->delete('test_meta_key');
        $this->assertEmpty(get_term_meta(self::$term_id, 'test_meta_key', true));
    }
}
