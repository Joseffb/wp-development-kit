<?php
/**
 * Shared runtime wp-env fixture support script.
 *
 * @package WDK\Tests
 */


declare(strict_types=1);

if (!function_exists('wdk_fixture_runtime_root')) {
    function wdk_fixture_runtime_root(): string
    {
        return WP_PLUGIN_DIR . '/wp-development-kit';
    }
}

if (!function_exists('wdk_fixture_register_bundle')) {
    function wdk_fixture_register_bundle(string $bundleId, string $type, string $root, string $version = '0.4.0', ?string $bootstrapFile = null): void
    {
        require_once wdk_fixture_runtime_root() . '/wdk-runtime-loader.php';

        wdk_register_runtime_bundle([
            'id' => $bundleId . '-runtime',
            'bundle_id' => $bundleId,
            'version' => $version,
            'autoloader' => wdk_fixture_runtime_root() . '/vendor/autoload.php',
            'root' => wdk_fixture_runtime_root(),
        ], [
            'id' => $bundleId,
            'type' => $type,
            'root' => $root,
            'bootstrap_file' => $bootstrapFile,
            'version' => $version,
        ]);
    }
}
