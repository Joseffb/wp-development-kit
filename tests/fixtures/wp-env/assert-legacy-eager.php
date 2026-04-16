<?php
/**
 * wp-env assertion script for the legacy eager coexistence scenario.
 *
 * @package WDK\Tests
 */


if (!function_exists('wdk_runtime_info')) {
    fwrite(STDERR, "wdk_runtime_info() is unavailable.\n");
    exit(1);
}

$info = wdk_runtime_info();
$messages = array_map(static fn (array $notice): string => (string) ($notice['message'] ?? ''), $info['notices'] ?? []);
$foundLegacyNotice = false;
foreach ($messages as $message) {
    if (str_contains($message, 'Legacy WDK eager bootstrap')) {
        $foundLegacyNotice = true;
        break;
    }
}

if (!$foundLegacyNotice) {
    fwrite(STDERR, "Expected legacy eager bootstrap warning was not found.\n");
    exit(1);
}

if (!get_page_by_path('wdk-coexistence', OBJECT, 'page')) {
    fwrite(STDERR, "Expected coexistence page was not created in legacy eager scenario.\n");
    exit(1);
}
