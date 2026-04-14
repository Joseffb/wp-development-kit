<?php

namespace WDK;

use Timber\Timber;

/**
 * Compatibility bridge for Timber 1.x and 2.x APIs.
 */
class TimberBridge
{
    public static function set_locations(array $locations): void
    {
        Timber::$locations = $locations;
    }

    public static function context(): array
    {
        return Timber::context();
    }

    public static function render($templates, array $context): void
    {
        Timber::render($templates, $context);
    }

    public static function get_post()
    {
        return method_exists(Timber::class, 'get_post') ? Timber::get_post() : new \Timber\Post();
    }
}

