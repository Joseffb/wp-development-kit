<?php

use PHPUnit\Framework\TestCase;
use WDK\Shadow;
use WP_Post;
use WP_Term;

/**
 * Class ShadowTest
 *
 * Tests for the Shadow class functionality.
 */
class ShadowTest extends TestCase
{
    protected static $post_type = 'shadow_test_post_type';
    protected static $taxonomy = 'shadow_test_taxonomy';
    protected static $condition_taxonomy = 'shadow_condition_taxonomy';
    protected static $posts = [];
    protected static $terms = [];
    protected static $factory;

    public static function setUpBeforeClass(): void
    {
        // Register custom post type
        register_post_type(self::$post_type, [
            'label'        => 'Shadow Test Post Type',
            'public'       => true,
            'supports'     => ['title', 'editor'],
            'has_archive'  => true,
            'show_in_rest' => true,
        ]);

        // Register custom taxonomy
        register_taxonomy(self::$taxonomy, self::$post_type, [
            'label'        => 'Shadow Test Taxonomy',
            'public'       => true,
            'hierarchical' => true,
            'show_in_rest' => true,
        ]);

        // Register condition taxonomy
        register_taxonomy(self::$condition_taxonomy, self::$post_type, [
            'label'        => 'Shadow Condition Taxonomy',
            'public'       => true,
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);

        // Create terms in condition taxonomy
        self::$terms['condition_term'] = wp_insert_term('Condition Term', self::$condition_taxonomy);

        // Hook into WordPress factories if available (for integration with WP tests)
        global $wp_tests_options;
        if (isset($wp_tests_options['active_plugins'])) {
            self::$factory = new WP_UnitTest_Factory();
        }

        // Create initial posts
        self::createTestPosts();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up posts
        foreach (self::$posts as $post_id) {
            wp_delete_post($post_id, true);
        }

        // Clean up terms
        foreach (self::$terms as $term) {
            if (isset($term['term_id'])) {
                wp_delete_term($term['term_id'], self::$condition_taxonomy);
            }
        }

        // Unregister post type and taxonomies
        unregister_post_type(self::$post_type);
        unregister_taxonomy(self::$taxonomy);
        unregister_taxonomy(self::$condition_taxonomy);
    }

    protected static function createTestPosts()
    {
        // Create posts for testing
        $post_data = [
            'post_title'   => 'Test Post 1',
            'post_content' => 'Content for test post 1.',
            'post_status'  => 'publish',
            'post_type'    => self::$post_type,
        ];

        $post_id = wp_insert_post($post_data);
        self::$posts[] = $post_id;
    }

    public function testCreateRelationship()
    {
        // Create shadow relationship
        Shadow::create_relationship(self::$post_type, self::$taxonomy);

        // Trigger post save to create shadow term
        $post_id = self::$posts[0];
        $post = get_post($post_id);
        wp_update_post($post);

        // Check if shadow term was created
        $term_id = get_post_meta($post_id, 'shadow_term_id', true);
        $this->assertNotEmpty($term_id, 'Shadow term ID should not be empty');

        $term = get_term($term_id, self::$taxonomy);
        $this->assertInstanceOf(WP_Term::class, $term, 'Shadow term should be a valid WP_Term object');
        $this->assertEquals($post->post_title, $term->name, 'Term name should match post title');
    }

    public function testUpdateShadowTerm()
    {
        $post_id = self::$posts[0];
        $post = get_post($post_id);

        // Update post title
        $new_title = 'Updated Test Post Title';
        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $new_title,
        ]);

        // Retrieve updated term
        $term_id = get_post_meta($post_id, 'shadow_term_id', true);
        $term = get_term($term_id, self::$taxonomy);

        // Check if term name was updated
        $this->assertEquals($new_title, $term->name, 'Term name should be updated to match new post title');
    }

    public function testConditionalShadowTermCreation()
    {
        // Create shadow relationship with conditionals
        Shadow::create_relationship(self::$post_type, self::$taxonomy, [
            'operator'   => 'AND',
            'conditions' => [
                [
                    'taxonomy' => self::$condition_taxonomy,
                    'values'   => 'Condition Term',
                ],
            ],
        ]);

        // Create a new post without the condition term
        $post_id = wp_insert_post([
            'post_title'   => 'Conditional Test Post',
            'post_content' => 'This post should not have a shadow term.',
            'post_status'  => 'publish',
            'post_type'    => self::$post_type,
        ]);
        self::$posts[] = $post_id;

        // Trigger post save
        $post = get_post($post_id);
        wp_update_post($post);

        // Check that no shadow term was created
        $term_id = get_post_meta($post_id, 'shadow_term_id', true);
        $this->assertEmpty($term_id, 'Shadow term ID should be empty because conditions are not met');

        // Assign the condition term to the post
        wp_set_object_terms($post_id, 'Condition Term', self::$condition_taxonomy);

        // Trigger post save again
        wp_update_post($post);

        // Check that shadow term is now created
        $term_id = get_post_meta($post_id, 'shadow_term_id', true);
        $this->assertNotEmpty($term_id, 'Shadow term ID should not be empty after meeting conditions');

        // Clean up
        wp_delete_post($post_id, true);
    }

    public function testDeleteShadowTerm()
    {
        $post_id = self::$posts[0];
        $term_id = get_post_meta($post_id, 'shadow_term_id', true);

        // Delete the post
        wp_delete_post($post_id, true);

        // Check if shadow term was deleted
        $term = get_term($term_id, self::$taxonomy);
        $this->assertNull($term, 'Shadow term should be deleted when post is deleted');

        // Remove from posts array
        array_shift(self::$posts);
    }

    public function testGetAssociatedTerm()
    {
        // Create a new post and shadow term
        $post_id = wp_insert_post([
            'post_title'   => 'Associated Term Test Post',
            'post_content' => 'Testing get_associated_term method.',
            'post_status'  => 'publish',
            'post_type'    => self::$post_type,
        ]);
        self::$posts[] = $post_id;

        // Trigger shadow term creation
        $post = get_post($post_id);
        wp_update_post($post);

        // Retrieve associated term
        $term = Shadow::get_associated_term($post, self::$taxonomy);
        $this->assertInstanceOf(WP_Term::class, $term, 'Associated term should be a valid WP_Term object');
        $this->assertEquals($post->post_title, $term->name, 'Term name should match post title');
    }

    public function testGetAssociatedPosts()
    {
        // Get the term associated with the last created post
        $post_id = end(self::$posts);
        $post = get_post($post_id);
        $term = Shadow::get_associated_term($post, self::$taxonomy);

        // Retrieve associated posts
        $associated_posts = Shadow::get_associated_posts($term);
        $this->assertIsArray($associated_posts, 'Associated posts should be an array');
        $this->assertCount(1, $associated_posts, 'There should be one associated post');
        $this->assertEquals($post_id, $associated_posts[0]->ID, 'Associated post ID should match the original post ID');
    }

    public function testGetRelatedPosts()
    {
        // Create another post and assign the same shadow term
        $post_id = wp_insert_post([
            'post_title'   => 'Related Post',
            'post_content' => 'Testing get_related_posts method.',
            'post_status'  => 'publish',
            'post_type'    => self::$post_type,
        ]);
        self::$posts[] = $post_id;

        // Assign the shadow term from the previous post
        $term = Shadow::get_associated_term(get_post(end(self::$posts)), self::$taxonomy);
        wp_set_object_terms($post_id, $term->term_id, self::$taxonomy);

        // Retrieve related posts
        $related_posts = Shadow::get_related_posts($post_id, self::$taxonomy, self::$post_type);
        $this->assertIsArray($related_posts, 'Related posts should be an array');
        $this->assertCount(2, $related_posts, 'There should be two related posts');
    }
}
