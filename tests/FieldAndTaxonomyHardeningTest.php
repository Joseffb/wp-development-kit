<?php
/**
 * Test coverage for the Field And Taxonomy Hardening component.
 *
 * @package WDK\Tests
 */


declare(strict_types=1);

use WDK\Field;
use WDK\Taxonomy;

/**
 * Exercises Field And Taxonomy Hardening behavior.
 */
class FieldAndTaxonomyHardeningTest extends WdkTestCase
{
    protected function setUp(): void
    {
        self::resetWordPressState();
        parent::setUp();

        register_taxonomy('topic', 'post', [
            'capabilities' => [
                'manage_terms' => 'manage_categories',
            ],
        ]);
    }

    public function testCreateFieldEscapesRenderedValues(): void
    {
        $postId = wp_insert_post([
            'post_title' => 'Escaped Post',
            'post_status' => 'publish',
        ]);
        update_post_meta($postId, 'headline', '<script>alert("x")</script>');

        $html = Field::CreateField('headline', 'headline', 'Headline', 'text', [], get_post($postId));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
    }

    public function testCheckboxSavePersistsAllSelectedValues(): void
    {
        Field::AddCustomFieldToPost('post', 'flags', 'Flags', 'checkbox', [
            'values' => [
                'One' => 'one',
                'Two' => 'two',
            ],
        ], [
            'show_on_admin' => false,
        ]);

        $postId = wp_insert_post([
            'post_title' => 'Checkbox Post',
            'post_status' => 'publish',
        ]);

        $_POST['flags_meta_box_nonce'] = $this->nonce('flags_meta_box_nonce');
        $_POST['flags'] = ['one', 'two'];
        do_action('save_post', $postId);

        $this->assertSame(['one', 'two'], get_post_meta($postId, 'flags', false));
    }

    public function testCheckboxSaveRejectsMissingNonce(): void
    {
        Field::AddCustomFieldToPost('post', 'flags', 'Flags', 'checkbox', [
            'values' => [
                'One' => 'one',
            ],
        ], [
            'show_on_admin' => false,
        ]);

        $postId = wp_insert_post([
            'post_title' => 'Nonce Guard Post',
            'post_status' => 'publish',
        ]);

        $_POST['flags'] = ['one'];
        do_action('save_post', $postId);

        $this->assertSame([], get_post_meta($postId, 'flags', false));
    }

    public function testSerializedListSaveSanitizesAndPreservesArrayShape(): void
    {
        Field::AddCustomFieldToPost('post', 'speakers', 'Speakers', 'serialized_list', [], [
            'show_on_admin' => false,
        ]);

        $postId = wp_insert_post([
            'post_title' => 'Serialized List Post',
            'post_status' => 'publish',
        ]);

        $_POST['speakers_meta_box_nonce'] = $this->nonce('speakers_meta_box_nonce');
        $_POST['serialize_data_speakers'] = 'true';
        $_POST['speakers'] = [
            ['name' => 'Alice <script>alert(1)</script>'],
            ['name' => ''],
            ['name' => 'Bob'],
        ];
        do_action('save_post', $postId);

        $this->assertSame(['Alice alert(1)', 'Bob'], get_post_meta($postId, 'speakers', true));
    }

    public function testTaxonomyImageSaveRequiresNonceAndCapability(): void
    {
        $term = wp_insert_term('Topic A', 'topic');
        $termId = (int) $term['term_id'];

        $_POST['taxonomy'] = 'topic';
        $_POST['taxonomy-image-id'] = '55';
        Taxonomy::SaveTermImage($termId);
        $this->assertSame('', get_term_meta($termId, 'taxonomy-image-id', true));

        $_POST['wdk_term_image_nonce'] = $this->nonce('wdk_term_image');
        $state = &wdk_test_state();
        $state['current_user_caps']['manage_categories'] = false;
        Taxonomy::SaveTermImage($termId);

        $this->assertSame('', get_term_meta($termId, 'taxonomy-image-id', true));
    }

    public function testTaxonomyImageUpdateStoresSanitizedImageIdWithNonce(): void
    {
        $term = wp_insert_term('Topic B', 'topic');
        $termId = (int) $term['term_id'];

        $_POST['taxonomy'] = 'topic';
        $_POST['taxonomy-image-id'] = '42';
        $_POST['wdk_term_image_nonce'] = $this->nonce('wdk_term_image');

        Taxonomy::ProcessTermImageUpdate($termId);

        $this->assertSame(42, get_term_meta($termId, 'taxonomy-image-id', true));
    }
}
