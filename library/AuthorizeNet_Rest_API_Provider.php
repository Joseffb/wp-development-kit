<?php

namespace WDK;

class AuthorizeNet_Rest_API_Provider extends Payment_Provider
{
    private $api_login_id;
    private $transaction_key;
    private $apiUrl;

    /**
     * @param $apiLoginId
     * @param $transactionKey
     * @param bool $sandbox
     */
    public function __construct($api_login_id, $transaction_key, $sandbox = true)
    {
        $this->api_login_id = $api_login_id;
        $this->transaction_key = $transaction_key;
        $this->apiUrl = $sandbox ? 'https://apitest.authorize.net/xml/v1/request.api' : 'https://api.authorize.net/xml/v1/request.api';
    }

    /**
     * Usage:
     * $payment_data = [
     *    'amount' => 10.00,
     *    'card_number' => '4111111111111111',
     *    'expiration_date' => '12/24',
     *    'cvv' => '123',
     *    'first_name' => 'John',
     *    'last_name' => 'Doe',
     *    'address' => '123 Main St',
     *    'city' => 'New York',
     *    'state' => 'NY',
     *    'zip' => '10001',
     *    'country' => 'US',
     * ];
     * createPayment($payment_data)
     * @param array $payment_data
     * @return array
     */
    public function create_payment(array $payment_data): array
    {
        $transactionRequest = [
            'createTransactionRequest' => [
                'merchantAuthentication' => [
                    'name' => $this->api_login_id,
                    'transactionKey' => $this->transaction_key
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

        $response = wp_remote_post($this->apiUrl, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($transactionRequest)
        ]);

        if (is_wp_error($response)) {
            return [
                "status" => "failed",
                "message" => $response->get_error_message()
            ];
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}