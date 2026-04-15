<?php
/**
 * Contains the Payments class.
 *
 * @package WDK
 */


namespace WDK;

use WDK\Payment_Provider;

/**
 * Provides the Payments component.
 */
class Payments
{
    protected Payment_Provider $payment_provider;

    public function __call($method, $arguments)
    {
        // Handle the undefined method call
        if (($this->payment_provider ?? null) && method_exists($this->payment_provider, $method)) {
            return call_user_func_array([$this->payment_provider, $method], $arguments);
        }

        throw new \RuntimeException("'$method' does not exist in the current payment provider", 10403);
    }

    public function __construct(string|Payment_Provider|null $provider = null, array $args = [])
    {
        $this->set_payment_provider($provider, $args);
    }

    public static function create_payment($payment_data, $args = [], $provider = null)
    {
        $providerArgs = is_array($args) ? $args : [];
        if (isset($providerArgs['provider_args']) && is_array($providerArgs['provider_args'])) {
            Compatibility::warn(__METHOD__, 'Passing provider_args inside the constructor arguments array is deprecated. Pass them as the second argument directly.');
            $providerArgs = $providerArgs['provider_args'];
        }

        return (new self($provider, $providerArgs))->payment_provider->create_payment($payment_data);
    }

    /**
     * Set the payment provider.
     *
     * @param string|Payment_Provider $payment_provider The name of the provider class or an instance of a PaymentProvider.
     * @param array $args
     * @return void
     */
    public function set_payment_provider(string|Payment_Provider|null $payment_provider, array $args = []): void
    {
        $this->payment_provider = ProviderResolver::resolve(
            $payment_provider,
            '\\WDK\\PayPal_Rest_API_Provider',
            Payment_Provider::class,
            $args,
            'payment provider'
        );
    }
}

// Usage:

// 1. Creating a payment using the default PayPal provider
//    $payment_data = [
//        'intent' => 'CAPTURE',
//        'purchase_units' => [
//            [
//                'amount' => [
//                    'currency_code' => 'USD',
//                    'value' => '10.00'
//                ]
//            ]
//        ],
//        'return_url' => 'https://example.com/success',
//        'cancel_url' => 'https://example.com/cancel'
//    ];
//    $args = ['client_id', 'client_secret'];
//    $payment = Payments::create_payment($payment_data, $args);

// 2. Creating a payment using the Authorize.Net provider
//    $payment_data = [
//        'amount' => 10.00,
//        'opaque_data' => [
//            'dataDescriptor' => 'COMMON.ACCEPT.INAPP.PAYMENT',
//            'dataValue' => 'opaque-token'
//        ]
//    ];
//    $args = ['api_login_id', 'transaction_key'];
//    $payment = Payments::create_payment($payment_data, $args, 'AuthorizeNet_Rest_API_Provider');

// 3. Creating a payment using the Stripe provider
//    $payment_data = [
//        'amount_cents' => 1000,
//        'currency' => 'usd',
//        'payment_method_id' => 'pm_12345',
//        'confirm' => true,
//        'return_url' => 'https://example.com/stripe/return'
//    ];
//    $args = ['sk_test_123456'];
//    $payment = Payments::create_payment($payment_data, $args, 'Stripe_Rest_Api_Provider');
