<?php

namespace WDK;

class PayPal_Rest_API_Provider extends Payment_Provider
{
    private $client_id;
    private $client_secret;
    private $api_url;
    private $access_token;

    /**
     * @param $client_id
     * @param $client_secret
     * @param bool $sandbox
     */
    public function __construct($client_id, $client_secret, bool $sandbox = true)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->api_url = $sandbox ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        $this->access_token = $this->get_access_token();
    }

    /**
     * @return string
     */
    private function get_api_url()
    {
        return $this->api_url;
    }

    /**
     * @return mixed|null
     */
    private function get_access_token()
    {
        $url = $this->get_api_url() . '/v1/oauth2/token';
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
        ]);

        if (is_wp_error($response)) {
            // Handle the error
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['access_token'] ?? null;
    }

    /**
     * Usage:
     * $payment_data = [
     *      'intent' => 'sale',
     *      'payer' => [
     *          'payment_method' => 'paypal'
     *      ],
     *      'transactions' => [
     *          [
     *              'amount' => [
     *                  'total' => $payment_data['total'],
     *                  'currency' => $payment_data['currency']
     *              ],
     *              'description' => $payment_data['description'],
     *              'item_list' => [
     *              'items' => $payment_data['items']
     *          ],
     *          'invoice_number' => uniqid()
     *      ]
     * ],
     * 'redirect_urls' => [
     * 'return_url' => $payment_data['return_url'],
     * 'cancel_url' => $payment_data['cancel_url']
     * ]
     * ];
     * createPayment($payment_data)
     * @param array $payment_data
     * @return array|null
     */
    public function create_payment(array $payment_data): array
    {
        $url = $this->get_api_url() . '/v1/payments/payment';

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payment_data),
        ]);

        if (is_wp_error($response)) {
            // Handle the error
            return [
                "status" => "failed",
                "message" => $response->get_error_message()
            ];
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}