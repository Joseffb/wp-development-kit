<?php
/**
 * Test support definitions for the WDK Test Case component.
 *
 * @package WDK\Tests
 */


declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Provides shared helpers and state resets for WDK test cases.
 */
abstract class WdkTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        wdk_test_clear_runtime_state();
    }

    protected static function resetWordPressState(): void
    {
        wdk_test_reset_state();
    }

    protected function nonce(string $action): string
    {
        return wdk_test_create_nonce($action);
    }

    protected function queuedHttpResponse(array $response): void
    {
        wdk_test_push_http_response($response);
    }

    protected function deprecations(): array
    {
        return wdk_test_deprecations();
    }

    protected function lastHttpRequest(): ?array
    {
        return wdk_test_last_http_request();
    }
}
