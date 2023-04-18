<?php

namespace WDK;

use GuzzleHttp\Client;

class AuthorizeNet_Rest_API_Provider extends Payment_Provider
{
    private $apiLoginId;
    private $transactionKey;
    private $apiUrl;

    public function __construct($apiLoginId, $transactionKey, $sandbox = true)
    {
        $this->apiLoginId = $apiLoginId;
        $this->transactionKey = $transactionKey;
        $this->apiUrl = $sandbox ? 'https://apitest.authorize.net/xml/v1/request.api' : 'https://api.authorize.net/xml/v1/request.api';
    }

    public function createPayment($payment_data)
    {
        $client = new Client();
        $transactionRequest = [
            'createTransactionRequest' => [
                'merchantAuthentication' => [
                    'name' => $this->apiLoginId,
                    'transactionKey' => $this->transactionKey
                ],
                'transactionRequest' => [
                    'transactionType' => 'authCaptureTransaction',
                    'amount' => $payment_data['amount'],
                    'payment' => [
                        'creditCard' => [
                            'cardNumber' => $payment_data['card_number'],
                            'expirationDate' => $payment_data['expiration_date'],
                            'cardCode' => $payment_data['cvv']
                        ]
                    ],
                    'billTo' => [
                        'firstName' => $payment_data['first_name'],
                        'lastName' => $payment_data['last_name'],
                        'address' => $payment_data['address'],
                        'city' => $payment_data['city'],
                        'state' => $payment_data['state'],
                        'zip' => $payment_data['zip'],
                        'country' => $payment_data['country']
                    ]
                ]
            ]
        ];

        $response = $client->post($this->apiUrl, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $transactionRequest
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