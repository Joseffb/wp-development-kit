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

    public function set_payment_provider($payment_provider)
    {
        $this->payment_provider = $payment_provider;
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
