<?php

namespace WDK;

/**
 * Compatibility bridge for Timber 1.x and 2.x APIs.
 */
class TimberBridge
{
    private static function timberClass(): ?string
    {
        foreach (['\\Timber\\Timber', '\\Timber'] as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    private static function postClass(): ?string
    {
        foreach (['\\Timber\\Post', '\\TimberPost'] as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    public static function is_available(): bool
    {
        return self::timberClass() !== null;
    }

    public static function set_locations(array $locations): void
    {
        $timberClass = self::timberClass();

        if ($timberClass === null) {
            return;
        }

        if (method_exists($timberClass, 'set_locations')) {
            $timberClass::set_locations($locations);
            return;
        }

        $timberClass::$locations = $locations;
    }

    public static function context(): array
    {
        $timberClass = self::timberClass();

        if ($timberClass === null) {
            return [];
        }

        if (method_exists($timberClass, 'context')) {
            return $timberClass::context();
        }

        if (method_exists($timberClass, 'get_context')) {
            return $timberClass::get_context();
        }

        return [];
    }

    public static function render($templates, array $context): void
    {
        $timberClass = self::timberClass();

        if ($timberClass !== null) {
            $timberClass::render($templates, $context);
        }
    }

    public static function get_post($post = null)
    {
        $timberClass = self::timberClass();

        if ($timberClass !== null && method_exists($timberClass, 'get_post')) {
            return $post === null ? $timberClass::get_post() : $timberClass::get_post($post);
        }

        $postClass = self::postClass();

        if ($postClass === null) {
            return null;
        }

        return $post === null ? new $postClass() : new $postClass($post);
    }

    public static function is_post_query($value): bool
    {
        foreach (['\\Timber\\PostQuery', '\\TimberPostQuery'] as $class) {
            if (class_exists($class) && $value instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public static function is_post($value): bool
    {
        foreach (['\\Timber\\Post', '\\TimberPost'] as $class) {
            if (class_exists($class) && $value instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public static function get_widgets(...$args)
    {
        $timberClass = self::timberClass();

        if ($timberClass !== null && method_exists($timberClass, 'get_widgets')) {
            return $timberClass::get_widgets(...$args);
        }

        return null;
    }
}
