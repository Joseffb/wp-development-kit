<?php

namespace WDK;

class Stripe_Rest_Api_Provider extends Payment_Provider
{
    private string $api_secret_key;
    private string $api_url = 'https://api.stripe.com/v1';
    private ?string $api_account;

    /**
     * @param string $api_secret_key  - the API-Key determines if you are in test or production environment
     * @param string|null $account_id - Used to specify the ID of a connected Stripe account on behalf of which the payment is being made. This is useful when a platform or a marketplace is facilitating payments between multiple parties. The platform can create charges on behalf of the connected accounts, while Stripe takes care of handling the payouts to the connected accounts.
     */
    public function __construct(string $api_secret_key, string $account_id = null)
    {
        $this->api_secret_key = $api_secret_key;
        $this->api_account = $account_id;
    }

    /**
     * Usage:
     * $payment_data = [
     *    'amount' => 10.00,
     *    'card_number' => '4242424242424242',
     *    'expiration_month' => '12',
     *    'expiration_year' => '2024',
     *    'cvv' => '123',
     *    'first_name' => 'John',
     *    'last_name' => 'Doe',
     *    'address' => '123 Main St',
     *    'city' => 'New York',
     *    'state' => 'NY',
     *    'zip' => '10001',
     *    'country' => 'US',
     * ];
     * create_payment($payment_data)
     * @param array $payment_data
     * @return array
     */
    public function create_payment(array $payment_data): array
    {
        $charge_data = [
            'amount' => $payment_data['amount'],
            'currency' => 'usd',
            'source' => [
                'object' => 'card',
                'number' => $payment_data['card_number'],
                'exp_month' => substr($payment_data['expiration_date'], 0, 2),
                'exp_year' => substr($payment_data['expiration_date'], -2),
                'cvc' => $payment_data['cvv'],
                'name' => $payment_data['first_name'] . ' ' . $payment_data['last_name'],
                'address_line1' => $payment_data['address'],
                'address_city' => $payment_data['city'],
                'address_state' => $payment_data['state'],
                'address_zip' => $payment_data['zip'],
                'address_country' => $payment_data['country']
            ],
            'description' => $payment_data['description'],
            'metadata' => [
                'first_name' => $payment_data['first_name'],
                'last_name' => $payment_data['last_name'],
                'address' => $payment_data['address'],
                'city' => $payment_data['city'],
                'state' => $payment_data['state'],
                'zip' => $payment_data['zip'],
                'country' => $payment_data['country']
            ]
        ];

        $headers = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => http_build_query($charge_data)
        ];
        if(!empty($this->api_account)) {
            $headers['headers']['Stripe-Account'] = $this->api_account;
        }
        $response = wp_remote_post($this->api_url . '/v1/charges', $headers);

        if (is_wp_error($response)) {
            return [
                "status" => "failed",
                "message" => $response->get_error_message()
            ];
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}