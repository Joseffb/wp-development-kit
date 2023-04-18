<?php

namespace WDK;
use http\Exception\BadMethodCallException;
use http\Exception\InvalidArgumentException;

class Payments
{
    protected $payment_provider;

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
            $this->payment_provider = new $provider();
        } else {
            $this->payment_provider = new $provider(...$args);
        }
    }

    public static function createPayment($payment_data, $args = [], $provider = 'PayPal_Rest_API_Provider')
    {
        return (new self($provider, $args))::createPayment($payment_data);
    }

    /**
     * Set the payment provider.
     *
     * @param string|Payment_Provider $payment_provider The name of the provider class or an instance of a PaymentProvider.
     * @throws InvalidArgumentException If an invalid payment provider type or class is provided.
     * @return void
     */
    public function set_payment_provider($payment_provider)
    {
        if (is_string($payment_provider)) {
            if (!class_exists($payment_provider)) {
                throw new InvalidArgumentException('Invalid payment provider class provided.');
            }

            if (!is_subclass_of($payment_provider, Payment_Provider::class)) {
                throw new InvalidArgumentException('Payment provider class must extend Payment_Provider.');
            }

            $this->payment_provider = new $payment_provider();
        } elseif ($payment_provider instanceof Payment_Provider) {
            $this->payment_provider = $payment_provider;
        } else {
            throw new InvalidArgumentException('Invalid payment provider type provided. Must be a class name or an instance of Payment_Provider.');
        }
    }
}

//Usage:
//// Create a payment using PayPal provider
//$payment_data = [
//    // Your payment data here
//];
//$payment = Payments::createPayment($payment_data);
//
//// Create a payment using Authorize.Net provider
//$payment_data = [
//    // Your payment data here
//];
//$payment = Payments::createPayment($payment_data, [], 'AuthorizeNet_Rest_API_Provider');
