<?php
/**
 * WordPress admin fixture helpers used by the WDK test suite.
 *
 * @package WDK\Tests
 */


if (!function_exists('is_plugin_active')) {
    function is_plugin_active(string $plugin): bool
    {
        return false;
    }
}
