<?php

declare(strict_types=1);

use WDK\Shadow;

class ShadowTest extends WdkTestCase
{
    protected static string $postType = 'shadow_test_post_type';
    protected static string $taxonomy = 'shadow_test_taxonomy';
    protected static string $conditionTaxonomy = 'shadow_condition_taxonomy';

    protected function setUp(): void
    {
        self::resetWordPressState();
        parent::setUp();

        $resetRelationships = Closure::bind(static function (): void {
            Shadow::$relationships = [];
        }, null, Shadow::class);
        $resetRelationships();

        register_post_type(self::$postType, [
            'label' => 'Shadow Test Post Type',
            'public' => true,
            'supports' => ['title', 'editor'],
        ]);

        register_taxonomy(self::$taxonomy, self::$postType, [
            'label' => 'Shadow Test Taxonomy',
            'public' => true,
            'hierarchical' => true,
        ]);

        register_taxonomy(self::$conditionTaxonomy, self::$postType, [
            'label' => 'Shadow Condition Taxonomy',
            'public' => true,
            'hierarchical' => false,
        ]);

        wp_insert_term('Condition Term', self::$conditionTaxonomy);
    }

    private function createShadowPost(string $title, string $content = 'Shadow content'): int
    {
        return wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => self::$postType,
        ]);
    }

    public function testCreateRelationship(): void
    {
        Shadow::create_relationship(self::$postType, self::$taxonomy);

        $postId = $this->createShadowPost('Test Post 1');
        $termId = (int) get_post_meta($postId, 'shadow_term_id', true);
        $term = get_term($termId, self::$taxonomy);

        $this->assertNotSame(0, $termId);
        $this->assertInstanceOf(WP_Term::class, $term);
        $this->assertSame('Test Post 1', $term->name);
    }

    public function testUpdateShadowTerm(): void
    {
        Shadow::create_relationship(self::$postType, self::$taxonomy);

        $postId = $this->createShadowPost('Initial Title');
        $termId = (int) get_post_meta($postId, 'shadow_term_id', true);

        wp_update_post([
            'ID' => $postId,
            'post_title' => 'Updated Title',
        ]);

        $term = get_term($termId, self::$taxonomy);

        $this->assertInstanceOf(WP_Term::class, $term);
        $this->assertSame('Updated Title', $term->name);
    }

    public function testConditionalShadowTermCreation(): void
    {
        Shadow::create_relationship(self::$postType, self::$taxonomy, [
            'operator' => 'AND',
            'conditions' => [[
                'taxonomy' => self::$conditionTaxonomy,
                'values' => 'Condition Term',
            ]],
        ]);

        $postId = $this->createShadowPost('Conditional Post');

        $this->assertSame('', get_post_meta($postId, 'shadow_term_id', true));

        wp_set_object_terms($postId, 'Condition Term', self::$conditionTaxonomy);
        wp_update_post(get_post($postId));

        $termId = (int) get_post_meta($postId, 'shadow_term_id', true);
        $this->assertGreaterThan(0, $termId);
    }

    public function testDeleteShadowTerm(): void
    {
        Shadow::create_relationship(self::$postType, self::$taxonomy);

        $postId = $this->createShadowPost('Delete Me');
        $termId = (int) get_post_meta($postId, 'shadow_term_id', true);

        wp_delete_post($postId, true);

        $this->assertNull(get_term($termId, self::$taxonomy));
    }

    public function testGetAssociatedTerm(): void
    {
        Shadow::create_relationship(self::$postType, self::$taxonomy);

        $postId = $this->createShadowPost('Associated Post');
        $term = Shadow::get_associated_term(get_post($postId), self::$taxonomy);

        $this->assertInstanceOf(WP_Term::class, $term);
        $this->assertSame('Associated Post', $term->name);
    }

    public function testGetAssociatedPosts(): void
    {
        Shadow::create_relationship(self::$postType, self::$taxonomy);

        $postId = $this->createShadowPost('Associated Collection');
        $term = Shadow::get_associated_term(get_post($postId), self::$taxonomy);
        $associatedPosts = Shadow::get_associated_posts($term);

        $this->assertIsArray($associatedPosts);
        $this->assertCount(1, $associatedPosts);
        $this->assertSame($postId, $associatedPosts[0]->ID);
    }

    public function testGetRelatedPosts(): void
    {
        Shadow::create_relationship(self::$postType, self::$taxonomy);

        $postId = $this->createShadowPost('Primary Post');
        $term = Shadow::get_associated_term(get_post($postId), self::$taxonomy);

        $relatedPostId = $this->createShadowPost('Related Post');
        wp_set_object_terms($relatedPostId, [$term->term_id], self::$taxonomy);
        update_post_meta($relatedPostId, 'shadow_term_id', $term->term_id);

        $relatedPosts = Shadow::get_related_posts($relatedPostId, self::$taxonomy, self::$postType);

        $this->assertIsArray($relatedPosts);
        $this->assertCount(2, $relatedPosts);
        $this->assertEqualsCanonicalizing([$postId, $relatedPostId], array_map(static fn (WP_Post $post) => $post->ID, $relatedPosts));
    }
}
