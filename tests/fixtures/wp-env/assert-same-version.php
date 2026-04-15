<?php
/**
 * wp-env assertion script for the same version coexistence scenario.
 *
 * @package WDK\Tests
 */


if (!function_exists('wdk_runtime_info')) {
    fwrite(STDERR, "wdk_runtime_info() is unavailable.\n");
    exit(1);
}

$info = wdk_runtime_info();

if (empty($info['booted'])) {
    fwrite(STDERR, "Shared runtime did not boot.\n");
    exit(1);
}

if (($info['selected']['version'] ?? null) !== '0.4.0') {
    fwrite(STDERR, "Expected runtime version 0.4.0.\n");
    exit(1);
}

$expectedBundles = [
    'wdk-core-plugin',
    'wdk-shared-runtime-theme',
    'wdk-fixture-alpha',
    'wdk-fixture-beta',
];

foreach ($expectedBundles as $bundleId) {
    if (!in_array($bundleId, $info['bundle_ids'] ?? [], true)) {
        fwrite(STDERR, "Missing bundle: {$bundleId}\n");
        exit(1);
    }
}
