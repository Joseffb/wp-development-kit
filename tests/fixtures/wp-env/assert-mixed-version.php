<?php
/**
 * wp-env assertion script for the mixed version coexistence scenario.
 *
 * @package WDK\Tests
 */


if (!function_exists('wdk_runtime_info')) {
    fwrite(STDERR, "wdk_runtime_info() is unavailable.\n");
    exit(1);
}

$info = wdk_runtime_info();

if (($info['selected']['version'] ?? null) !== '0.4.0') {
    fwrite(STDERR, "Expected runtime version 0.4.0 for mixed-version scenario.\n");
    exit(1);
}

if (!in_array('wdk-fixture-legacy', $info['bundle_ids'] ?? [], true)) {
    fwrite(STDERR, "Legacy bundle was not attached to the shared runtime.\n");
    exit(1);
}

$messages = array_map(static fn (array $notice): string => (string) ($notice['message'] ?? ''), $info['notices'] ?? []);
$foundMixedVersionNotice = false;
foreach ($messages as $message) {
    if (str_contains($message, 'wdk-fixture-legacy') && str_contains($message, 'shared runtime')) {
        $foundMixedVersionNotice = true;
        break;
    }
}

if (!$foundMixedVersionNotice) {
    fwrite(STDERR, "Expected mixed-version downgrade notice was not found.\n");
    exit(1);
}
