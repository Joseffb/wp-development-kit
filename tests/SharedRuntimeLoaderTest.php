<?php
/**
 * Test coverage for the Shared Runtime Loader component.
 *
 * @package WDK\Tests
 */


declare(strict_types=1);

require_once __DIR__ . '/WdkTestCase.php';

/**
 * Exercises Shared Runtime Loader behavior.
 */
final class SharedRuntimeLoaderTest extends WdkTestCase
{
    public function testHighestVersionRuntimeWinsAndOnlyWinnerAutoloaderLoads(): void
    {
        unset($GLOBALS['wdk_runtime_autoloaders_loaded']);

        wdk_register_runtime_candidate([
            'id' => 'core-runtime',
            'bundle_id' => 'wdk-core-plugin',
            'version' => '0.4.0',
            'autoloader' => __DIR__ . '/fixtures/runtime/winner-autoloader.php',
            'root' => dirname(__DIR__),
        ]);
        wdk_register_bundle([
            'id' => 'wdk-core-plugin',
            'type' => 'core-plugin',
            'root' => dirname(__DIR__),
            'version' => '0.4.0',
        ]);

        wdk_register_runtime_candidate([
            'id' => 'legacy-runtime',
            'bundle_id' => 'wdk-legacy-plugin',
            'version' => '0.2.0',
            'autoloader' => __DIR__ . '/fixtures/runtime/loser-autoloader.php',
            'root' => dirname(__DIR__),
        ]);
        wdk_register_bundle([
            'id' => 'wdk-legacy-plugin',
            'type' => 'plugin',
            'root' => dirname(__DIR__),
            'version' => '0.2.0',
        ]);

        do_action('after_setup_theme');

        $this->assertSame(['winner'], $GLOBALS['wdk_runtime_autoloaders_loaded'] ?? []);

        $info = wdk_runtime_info();
        $this->assertTrue($info['booted']);
        $this->assertSame('core-runtime', $info['selected']['id']);
        $this->assertSame('0.4.0', $info['selected']['version']);
        $this->assertContains('wdk-core-plugin', $info['bundle_ids']);
        $this->assertContains('wdk-legacy-plugin', $info['bundle_ids']);
        $this->assertGreaterThanOrEqual(1, $info['notice_count']);
    }

    public function testSameVersionTiePrefersFirstRegisteredCandidate(): void
    {
        unset($GLOBALS['wdk_runtime_autoloaders_loaded']);

        wdk_register_runtime_candidate([
            'id' => 'first-runtime',
            'bundle_id' => 'theme-bundle',
            'version' => '0.4.0',
            'autoloader' => __DIR__ . '/fixtures/runtime/winner-autoloader.php',
            'root' => dirname(__DIR__),
        ]);
        wdk_register_bundle([
            'id' => 'theme-bundle',
            'type' => 'theme',
            'root' => dirname(__DIR__),
            'version' => '0.4.0',
        ]);

        wdk_register_runtime_candidate([
            'id' => 'second-runtime',
            'bundle_id' => 'plugin-bundle',
            'version' => '0.4.0',
            'autoloader' => __DIR__ . '/fixtures/runtime/loser-autoloader.php',
            'root' => dirname(__DIR__),
        ]);
        wdk_register_bundle([
            'id' => 'plugin-bundle',
            'type' => 'plugin',
            'root' => dirname(__DIR__),
            'version' => '0.4.0',
        ]);

        do_action('after_setup_theme');

        $info = wdk_runtime_info();
        $this->assertSame('first-runtime', $info['selected']['id']);
        $this->assertSame(['theme-bundle', 'plugin-bundle'], array_slice($info['bundle_ids'], 0, 2));
    }

    public function testLegacySystemStartWarnsWhenRuntimeCandidatesArePending(): void
    {
        wdk_register_runtime_candidate([
            'id' => 'core-runtime',
            'bundle_id' => 'wdk-core-plugin',
            'version' => '0.4.0',
            'autoloader' => __DIR__ . '/fixtures/runtime/winner-autoloader.php',
            'root' => dirname(__DIR__),
        ]);

        $this->assertFalse(\WDK\System::Start());
        $this->assertNotEmpty($this->deprecations());
        $this->assertStringContainsString(
            'shared runtime bootstrap shim',
            $this->deprecations()[0]['message']
        );
        $this->assertGreaterThanOrEqual(1, wdk_runtime_info()['notice_count']);
    }

    public function testLateRegisteredThemeBundleAttachesToBootedRuntimeAndProcessesConfig(): void
    {
        $themeRoot = __DIR__ . '/fixtures/wp-env/themes/wdk-shared-runtime-theme';

        wdk_register_runtime_candidate([
            'id' => 'core-runtime',
            'bundle_id' => 'wdk-core-plugin',
            'version' => '0.4.0',
            'autoloader' => __DIR__ . '/fixtures/runtime/winner-autoloader.php',
            'root' => dirname(__DIR__),
        ]);
        wdk_register_bundle([
            'id' => 'wdk-core-plugin',
            'type' => 'core-plugin',
            'root' => dirname(__DIR__),
            'version' => '0.4.0',
        ]);

        do_action('after_setup_theme');

        $this->assertTrue(wdk_runtime_info()['booted']);
        $this->assertNull(get_page_by_path('wdk-coexistence', OBJECT, 'page'));

        wdk_register_runtime_candidate([
            'id' => 'theme-runtime',
            'bundle_id' => 'wdk-shared-runtime-theme',
            'version' => '0.4.0',
            'autoloader' => __DIR__ . '/fixtures/runtime/loser-autoloader.php',
            'root' => dirname(__DIR__),
        ]);
        wdk_register_bundle([
            'id' => 'wdk-shared-runtime-theme',
            'type' => 'theme',
            'root' => $themeRoot,
            'version' => '0.4.0',
            'config_paths' => [$themeRoot . '/wdk/configs'],
            'template_paths' => [$themeRoot . '/wdk/views'],
            'bootstrap_file' => $themeRoot . '/bundle-bootstrap.php',
        ]);

        do_action('init');

        $info = wdk_runtime_info();
        $this->assertContains('wdk-shared-runtime-theme', $info['bundle_ids']);
        $this->assertSame('coexistence', get_option('wdk_process_template_page_wdk-coexistence'));

        $page = get_page_by_path('wdk-coexistence', OBJECT, 'page');
        $this->assertNotNull($page);
        $this->assertSame('page', $page->post_type);
        $this->assertSame('WDK Coexistence', $page->post_title);
    }
}
