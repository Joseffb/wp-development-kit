<?php
/**
 * Contains the Compatibility class.
 *
 * @package WDK
 */


namespace WDK;

/**
 * Provides the Compatibility component.
 */
class Compatibility
{
    public static function warn(string $hook, string $message, string $version = '0.4.0'): void
    {
        if (function_exists('_doing_it_wrong')) {
            _doing_it_wrong($hook, $message, $version);
            return;
        }

        trigger_error($hook . ': ' . $message . ' (since ' . $version . ')', E_USER_DEPRECATED);
    }

    public static function getArrayValue(array $payload, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $payload[$key];
            }
        }

        return $default;
    }

    public static function normalizeMoneyAmount(int|float|string|null $amount, int $precision = 2): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (is_int($amount)) {
            return $amount;
        }

        if (is_numeric($amount)) {
            return (int) round(((float) $amount) * (10 ** $precision));
        }

        return null;
    }
}
