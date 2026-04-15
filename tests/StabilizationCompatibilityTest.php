<?php
/**
 * Test coverage for the Stabilization Compatibility component.
 *
 * @package WDK\Tests
 */


declare(strict_types=1);

use WDK\AuthorizeNet_Rest_API_Provider;
use WDK\Payments;
use WDK\PayPal_Rest_API_Provider;
use WDK\Query;
use WDK\Search;
use WDK\Stripe_Rest_Api_Provider;
use WDK\System;

/**
 * Exercises Stabilization Compatibility behavior.
 */
class StabilizationCompatibilityTest extends WdkTestCase
{
    protected function setUp(): void
    {
        self::resetWordPressState();
        parent::setUp();
    }

    public function testSearchDefaultProviderDoesNotEmitDeprecations(): void
    {
        wp_insert_post([
            'post_title' => 'Compatibility Search Post',
            'post_content' => 'Search target',
            'post_status' => 'publish',
        ]);

        $query = Search::find('Compatibility');

        $this->assertInstanceOf(WP_Query::class, $query);
        $this->assertCount(1, $query->posts);
        $this->assertSame([], $this->deprecations());
    }

    public function testSearchLegacyShortProviderStillWorksWithDeprecation(): void
    {
        wp_insert_post([
            'post_title' => 'Legacy Search Post',
            'post_content' => 'Legacy provider path',
            'post_status' => 'publish',
        ]);

        $query = Search::find('Legacy', [], 'WP_Local_Search_Provider');

        $this->assertInstanceOf(WP_Query::class, $query);
        $this->assertCount(1, $query->posts);
        $this->assertNotEmpty($this->deprecations());
    }

    public function testPaymentsDefaultProviderUsesPaypalWithoutDeprecation(): void
    {
        $this->queuedHttpResponse([
            'status' => 200,
            'body' => json_encode(['access_token' => 'token-value'], JSON_THROW_ON_ERROR),
        ]);
        $this->queuedHttpResponse([
            'status' => 201,
            'body' => json_encode([
                'id' => 'ORDER-123',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://example.test/approve'],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = Payments::create_payment([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '10.00',
                ],
            ]],
        ], ['client-id', 'client-secret']);

        $this->assertSame('paypal', $response['provider']);
        $this->assertSame('ORDER-123', $response['object_id']);
        $this->assertSame('requires_action', $response['status']);
        $this->assertSame('https://example.test/approve', $response['next_action']['url']);
        $this->assertSame([], $this->deprecations());
    }

    public function testPaymentsLegacyShortProviderStillWorksWithDeprecation(): void
    {
        $this->queuedHttpResponse([
            'status' => 200,
            'body' => json_encode(['access_token' => 'token-value'], JSON_THROW_ON_ERROR),
        ]);
        $this->queuedHttpResponse([
            'status' => 201,
            'body' => json_encode([
                'id' => 'ORDER-124',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://example.test/approve-legacy'],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = Payments::create_payment([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '12.00',
                ],
            ]],
        ], ['client-id', 'client-secret'], 'PayPal_Rest_API_Provider');

        $this->assertSame('paypal', $response['provider']);
        $this->assertSame('ORDER-124', $response['object_id']);
        $this->assertNotEmpty($this->deprecations());
    }

    public function testPayPalLegacyTransactionPayloadIsNormalizedWithDeprecation(): void
    {
        $this->queuedHttpResponse([
            'status' => 200,
            'body' => json_encode(['access_token' => 'token-value'], JSON_THROW_ON_ERROR),
        ]);
        $this->queuedHttpResponse([
            'status' => 201,
            'body' => json_encode([
                'id' => 'ORDER-LEGACY',
                'status' => 'CREATED',
                'links' => [],
            ], JSON_THROW_ON_ERROR),
        ]);

        $provider = new PayPal_Rest_API_Provider('client-id', 'client-secret');
        $response = $provider->create_payment([
            'intent' => 'sale',
            'transactions' => [[
                'amount' => [
                    'total' => '19.50',
                    'currency' => 'USD',
                ],
                'description' => 'Legacy transaction',
                'invoice_number' => 'INV-123',
            ]],
            'redirect_urls' => [
                'return_url' => 'https://example.test/return',
                'cancel_url' => 'https://example.test/cancel',
            ],
        ]);

        $request = $this->lastHttpRequest();
        $payload = json_decode((string) ($request['args']['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('paypal', $response['provider']);
        $this->assertSame('ORDER-LEGACY', $response['object_id']);
        $this->assertSame('CAPTURE', $payload['intent']);
        $this->assertSame('19.50', $payload['purchase_units'][0]['amount']['value']);
        $this->assertSame('https://example.test/return', $payload['application_context']['return_url']);
        $this->assertNotEmpty($this->deprecations());
    }

    public function testStripeRejectsUnsafeRawCardPayload(): void
    {
        $provider = new Stripe_Rest_Api_Provider('sk_test_123');

        $response = $provider->create_payment([
            'amount' => 10.50,
            'card_number' => '4242424242424242',
            'cvv' => '123',
        ]);

        $this->assertSame('failed', $response['status']);
        $this->assertStringContainsString('no longer accepted', (string) $response['message']);
        $this->assertNotEmpty($this->deprecations());
    }

    public function testStripeLegacyUppercaseApiAliasStillAutoloads(): void
    {
        $provider = new \WDK\Stripe_Rest_API_Provider('sk_test_123');

        $this->assertInstanceOf(Stripe_Rest_Api_Provider::class, $provider);
    }

    public function testAuthorizeNetRejectsUnsafeRawCardPayload(): void
    {
        $provider = new AuthorizeNet_Rest_API_Provider('login', 'key');

        $response = $provider->create_payment([
            'amount' => 10.50,
            'card_number' => '4111111111111111',
            'cvv' => '123',
        ]);

        $this->assertSame('failed', $response['status']);
        $this->assertStringContainsString('no longer accepted', (string) $response['message']);
        $this->assertNotEmpty($this->deprecations());
    }

    public function testGetPostAttachmentsReturnsFlatAttachmentListAcrossParents(): void
    {
        $postOne = wp_insert_post([
            'post_title' => 'Parent One',
            'post_status' => 'publish',
        ]);
        $postTwo = wp_insert_post([
            'post_title' => 'Parent Two',
            'post_status' => 'publish',
        ]);

        $attachmentOne = wp_insert_attachment([
            'post_title' => 'Attachment One',
            'post_status' => 'inherit',
            'post_mime_type' => 'image/jpeg',
        ], __FILE__, $postOne);
        $attachmentTwo = wp_insert_attachment([
            'post_title' => 'Attachment Two',
            'post_status' => 'inherit',
            'post_mime_type' => 'image/jpeg',
        ], __FILE__, $postTwo);

        $attachments = Query::GetPostAttachments([$postOne, $postTwo]);

        $this->assertIsArray($attachments);
        $this->assertCount(2, $attachments);
        $this->assertSame([$attachmentOne, $attachmentTwo], array_map(static fn (WP_Post $post) => $post->ID, $attachments));
    }

    public function testProcessTaxonomiesSeedsDefaultsOnlyOnce(): void
    {
        $config = [[
            'name' => 'Event Day',
            'post_types' => ['post'],
            'labels' => [],
            'options' => [],
            'defaults' => ['Day 1'],
        ]];

        System::ProcessTaxonomies($config);
        do_action('init');

        $termsAfterFirstRun = get_terms([
            'taxonomy' => 'event_day',
            'hide_empty' => false,
        ]);

        System::ProcessTaxonomies($config);
        do_action('init');

        $termsAfterSecondRun = get_terms([
            'taxonomy' => 'event_day',
            'hide_empty' => false,
        ]);

        $this->assertCount(1, $termsAfterFirstRun);
        $this->assertCount(1, $termsAfterSecondRun);
        $this->assertTrue((bool) get_option('tax_term_Event Day_installed'));
    }

    public function testProcessPostsCreatesConfiguredPageImmediatelyAfterInit(): void
    {
        do_action('init');

        System::ProcessPosts([[
            'post_type' => 'page',
            'post_title' => 'WDK Coexistence',
            'post_content' => 'Shared runtime coexistence page',
            'post_meta' => [
                'slug' => 'wdk-coexistence',
                'post_status' => 'publish',
            ],
        ]]);

        $page = get_page_by_path('wdk-coexistence', OBJECT, 'page');

        $this->assertInstanceOf(WP_Post::class, $page);
        $this->assertSame('WDK Coexistence', $page->post_title);
        $this->assertSame('publish', $page->post_status);
    }
}
