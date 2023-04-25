<?php

namespace WDK;

use WDK\Payment_Provider;
use http\Exception\BadMethodCallException;
use http\Exception\InvalidArgumentException;

class Payments
{
    protected Payment_Provider $payment_provider;

    public function __call($method, $arguments)
    {
        // Handle the undefined method call
        if (($this->payment_provider ?? null) && method_exists($this->payment_provider, $method)) {
            return call_user_func_array([$this->payment_provider, $method], $arguments);
        }

        throw new BadMethodCallException("'$method' does not exist in the current payment provider", 10403);
    }

    public function __construct($provider = 'PayPal_Rest_API_Provider', $args = [])
    {
        if (!class_exists($provider)) {
            throw new InvalidArgumentException('Invalid payment provider class provided.');
        }

        if (empty($args)) {
            $this->set_payment_provider($provider);
        } else {
            $this->set_payment_provider( new $provider(...$args));
        }
    }

    public static function create_payment($payment_data, $args = [], $provider = 'PayPal_Rest_API_Provider')
    {
        return (new self($provider, $args))->payment_provider->create_payment($payment_data);    }

    /**
     * Set the payment provider.
     *
     * @param string|Payment_Provider $payment_provider The name of the provider class or an instance of a PaymentProvider.
     * @param array $args
     * @return void
     */
    public function set_payment_provider($payment_provider, array $args = []): void
    {
        if (is_string($payment_provider)) {
            if (!class_exists($payment_provider)) {
                throw new InvalidArgumentException('Invalid payment provider class provided.');
            }

            if (!is_subclass_of($payment_provider, Payment_Provider::class)) {
                throw new InvalidArgumentException('Payment provider class must extend Payment_Provider.');
            }

            if(!empty($args)) {
                $this->payment_provider = new $payment_provider(...$args);
            } else {
                $this->payment_provider = new $payment_provider();
            }
        } elseif ($payment_provider instanceof Payment_Provider) {
            $this->payment_provider = $payment_provider;
        } else {
            throw new InvalidArgumentException('Invalid payment provider type provided. Must be a class name or an instance of Payment_Provider.');
        }
    }
}

// Usage:

// 1. Creating a payment using the default PayPal provider
//    $payment_data = [
//        // Your payment data here
//        'intent' => 'sale',
//        'payer' => [
//            'payment_method' => 'paypal'
//        ],
//        'transactions' => [
//            [
//                'amount' => [
//                    'total' => '10.00',
//                    'currency' => 'USD'
//                ],
//                'description' => 'Payment description',
//                'invoice_number' => uniqid()
//            ]
//        ],
//        'redirect_urls' => [
//            'return_url' => 'https://example.com/success',
//            'cancel_url' => 'https://example.com/cancel'
//        ]
//    ];
//    $args = [client_id, client_secret];
//    $payment = Payments::create_payment($payment_data,$args);

// 2. Creating a payment using the Authorize.Net provider
//    $payment_data = [
//        // Your payment data here
//        'amount' => 10.00,
//        'card_number' => '4111111111111111',
//        'expiration_date' => '12/24',
//        'cvv' => '123',
//        'first_name' => 'John',
//        'last_name' => 'Doe',
//        'address' => '123 Main St',
//        'city' => 'New York',
//        'state' => 'NY',
//        'zip' => '10001',
//        'country' => 'US',
//    ];
//    $args = ['api_login_id', 'transaction_key'];
//    $payment = Payments::create_payment($payment_data, $args, 'AuthorizeNet_Rest_API_Provider');

// 3. Creating a payment using the Stripe provider
//    $payment_data = [
//        // Your payment data here
//        'amount' => 10.00,
//        'card_number' => '4242424242424242',
//        'expiration_date' => '12/24',
//        'cvv' => '123',
//        'first_name' => 'John',
//        'last_name' => 'Doe',
//        'address' => '123 Main St',
//        'city' => 'New York',
//        'state' => 'NY',
//        'zip' => '10001',
//        'country' => 'US',
//    ];
//    $args = ['sk_test_123456', 'acct_12345'];
//    $payment = Payments::create_payment($payment_data, $args, 'Stripe_Rest_Api_Provider');