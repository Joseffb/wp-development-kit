<?php

namespace WDK;

use GuzzleHttp\Client;

class PayPal_Rest_API_Provider extends Payment_Provider
{
    private $clientId;
    private $clientSecret;
    private $apiUrl;
    private $accessToken;

    public function __construct($clientId, $clientSecret, $sandbox = true)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->apiUrl = $sandbox ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        $this->accessToken = $this->getAccessToken();
    }

    private function getAccessToken()
    {
        $client = new Client();
        $response = $client->post("{$this->apiUrl}/v1/oauth2/token", [
            'auth' => [$this->clientId, $this->clientSecret],
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US'
            ],
            'form_params' => [
                'grant_type' => 'client_credentials'
            ]
        ]);
        try {
            $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $data = $data['access_token'];
        } catch (\JsonException $e) {
            $data = [
                "status" => "failed",
                "message" => $e->getMessage()
            ];
        }
        return $data;
    }

    /**
     * expects:
     $payment_data = [
        'intent' => 'sale',
        'payer' => [
        'payment_method' => 'paypal'
        ],
        'transactions' => [
            [
                'amount' => [
                    'total' => $payment_data['total'],
                    'currency' => $payment_data['currency']
                ],
                'description' => $payment_data['description'],
                'item_list' => [
                    'items' => $payment_data['items']
                ],
                'invoice_number' => uniqid()
            ]
        ],
        'redirect_urls' => [
            'return_url' => $payment_data['return_url'],
            'cancel_url' => $payment_data['cancel_url']
        ]
    ];
     * @param $payment_data
     * @return array|mixed
     */
    public function createPayment($payment_data)
    {
        $client = new Client();
        $response = $client->post("{$this->apiUrl}/v1/payments/payment", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->accessToken}"
            ],
            'json' => $payment_data
        ]);

        try {
            $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $data = [
                "status" => "failed",
                "message" => $e->getMessage()
            ];
        }
        return $data;
    }
}