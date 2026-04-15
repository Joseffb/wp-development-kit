<?php

if (!function_exists('is_plugin_active')) {
    function is_plugin_active(string $plugin): bool
    {
        return false;
    }
}
