<?php

namespace WDK;

use RuntimeException;

class ProviderResolver
{
    /**
     * @param object|string|null $provider
     * @param string $defaultClass
     * @param string $expectedParent
     * @param array $constructorArgs
     * @param string $label
     * @return object
     */
    public static function resolve(object|string|null $provider, string $defaultClass, string $expectedParent, array $constructorArgs = [], string $label = 'provider'): object
    {
        if ($provider === null || $provider === '') {
            $provider = $defaultClass;
        }

        if (is_object($provider)) {
            if (!$provider instanceof $expectedParent) {
                throw new RuntimeException('Invalid ' . $label . ' instance provided. Must extend ' . $expectedParent . '.');
            }

            return $provider;
        }

        $providerClass = self::normalizeClassName($provider, $defaultClass);

        if ($providerClass !== ltrim($provider, '\\') && strpos($provider, '\\') === false) {
            Compatibility::warn(__METHOD__, sprintf(
                'Passing short %s names such as "%s" is deprecated. Pass "%s" instead.',
                $label,
                $provider,
                $providerClass
            ));
        }

        if (!class_exists($providerClass)) {
            throw new RuntimeException('Invalid ' . $label . ' class provided: ' . $providerClass);
        }

        if (!is_subclass_of($providerClass, $expectedParent)) {
            throw new RuntimeException(ucfirst($label) . ' class must extend ' . $expectedParent . '.');
        }

        return new $providerClass(...$constructorArgs);
    }

    private static function normalizeClassName(string $provider, string $defaultClass): string
    {
        $provider = trim($provider);

        if ($provider === '') {
            return ltrim($defaultClass, '\\');
        }

        if (str_contains($provider, '\\')) {
            return ltrim($provider, '\\');
        }

        $aliases = [
            'Stripe_Rest_API_Provider' => 'WDK\\Stripe_Rest_Api_Provider',
            'Stripe_Rest_Api_Provider' => 'WDK\\Stripe_Rest_Api_Provider',
            'PayPal_Rest_API_Provider' => 'WDK\\PayPal_Rest_API_Provider',
            'AuthorizeNet_Rest_API_Provider' => 'WDK\\AuthorizeNet_Rest_API_Provider',
            'WP_Local_Search_Provider' => 'WDK\\WP_Local_Search_Provider',
            'WP_Search_Provider' => 'WDK\\WP_Search_Provider',
        ];

        if (isset($aliases[$provider])) {
            return $aliases[$provider];
        }

        return 'WDK\\' . ltrim($provider, '\\');
    }
}
