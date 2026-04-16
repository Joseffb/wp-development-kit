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
if (($info['selected']['version'] ?? null) !== '0.5.0') {
    fwrite(STDERR, "Expected runtime version 0.5.0 for the System::Start() coexistence scenario.\n");
    exit(1);
}

if (!in_array('wdk-fixture-eager', $info['bundle_ids'] ?? [], true)) {
    fwrite(STDERR, "System::Start() plugin fixture did not attach to the shared runtime.\n");
    exit(1);
}

$messages = array_map(static fn (array $notice): string => (string) ($notice['message'] ?? ''), $info['notices'] ?? []);
foreach ($messages as $message) {
    if (str_contains($message, 'Legacy WDK eager bootstrap')) {
        fwrite(STDERR, "Unexpected legacy eager bootstrap warning was emitted.\n");
        exit(1);
    }
}

if (get_option('wdk_process_template_page_wdk-coexistence') !== 'coexistence') {
    fwrite(STDERR, "Theme bootstrap options were not applied for the System::Start() coexistence scenario.\n");
    exit(1);
}
