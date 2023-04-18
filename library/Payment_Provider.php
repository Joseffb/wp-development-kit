<?php

namespace WDK;

abstract class Payment_Provider
{
    /**
     * Create a payment with the given data.
     *
     * @param array $payment_data An associative array containing the payment information.
     * @return mixed The result of the payment creation, specific to the provider implementation.
     */
    abstract public function createPayment(array $payment_data);
}