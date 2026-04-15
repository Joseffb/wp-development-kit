<?php
/**
 * Contains the AuthorizeNet_Rest_API_Provider class.
 *
 * @package WDK
 */


namespace WDK;

/**
 * Provides the Authorize Net REST API Provider integration implementation.
 */
class AuthorizeNet_Rest_API_Provider extends Payment_Provider
{
    private string $api_login_id;
    private string $transaction_key;
    private string $api_url;

    public function __construct(string $api_login_id, string $transaction_key, bool $sandbox = true)
    {
        $this->api_login_id = $api_login_id;
        $this->transaction_key = $transaction_key;
        $this->api_url = $sandbox ? 'https://apitest.authorize.net/xml/v1/request.api' : 'https://api.authorize.net/xml/v1/request.api';
    }

    public function create_payment(array $payment_data): array
    {
        if ($this->has_unsafe_card_data($payment_data)) {
            return $this->reject_unsafe_legacy_payload('authorizenet');
        }

        $normalized = $this->normalize_payment_data($payment_data);
        if (!empty($normalized['error'])) {
            return $this->normalize_response('authorizenet', 'failed', [], null, null, $normalized['error']);
        }

        $transactionRequest = [
            'createTransactionRequest' => [
                'merchantAuthentication' => [
                    'name' => $this->api_login_id,
                    'transactionKey' => $this->transaction_key,
                ],
                'transactionRequest' => array_filter([
                    'transactionType' => $normalized['transaction_type'],
                    'amount' => $normalized['amount'],
                    'payment' => [
                        'opaqueData' => [
                            'dataDescriptor' => $normalized['descriptor'],
                            'dataValue' => $normalized['value'],
                        ],
                    ],
                    'billTo' => $normalized['bill_to'],
                    'order' => $normalized['order'],
                ]),
            ],
        ];

        $response = $this->perform_request($this->api_url, [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($transactionRequest),
        ]);

        if (!$response['ok']) {
            return $this->normalize_response('authorizenet', 'failed', [], null, null, $response['error']);
        }

        $data = $this->decode_json_body($response['body']);
        $resultCode = strtoupper((string) ($data['messages']['resultCode'] ?? 'ERROR'));

        return $this->normalize_response(
            'authorizenet',
            $resultCode === 'OK' ? 'succeeded' : 'failed',
            $data,
            $data['transactionResponse']['transId'] ?? null,
            null,
            $data['transactionResponse']['errors'][0]['errorText'] ?? ($data['messages']['message'][0]['text'] ?? null)
        );
    }

    private function normalize_payment_data(array $payment_data): array
    {
        $opaqueData = $payment_data['opaque_data'] ?? $payment_data['opaqueData'] ?? null;
        $descriptor = $payment_data['opaque_data_descriptor'] ?? $payment_data['data_descriptor'] ?? ($opaqueData['dataDescriptor'] ?? $opaqueData['descriptor'] ?? null);
        $value = $payment_data['opaque_data_value'] ?? $payment_data['data_value'] ?? ($opaqueData['dataValue'] ?? $opaqueData['value'] ?? null);

        if (empty($descriptor) || empty($value)) {
            return ['error' => 'Authorize.Net payments now require Accept.js opaque token data.'];
        }

        $amount = Compatibility::getArrayValue($payment_data, ['amount']);
        if ($amount === null || $amount === '') {
            return ['error' => 'Authorize.Net payments require an amount.'];
        }

        return [
            'descriptor' => sanitize_text_field((string) $descriptor),
            'value' => sanitize_text_field((string) $value),
            'amount' => (float) $amount,
            'transaction_type' => !empty($payment_data['transaction_type']) ? sanitize_key((string) $payment_data['transaction_type']) : 'authCaptureTransaction',
            'bill_to' => array_filter([
                'firstName' => !empty($payment_data['first_name']) ? sanitize_text_field((string) $payment_data['first_name']) : null,
                'lastName' => !empty($payment_data['last_name']) ? sanitize_text_field((string) $payment_data['last_name']) : null,
                'address' => !empty($payment_data['address']) ? sanitize_text_field((string) $payment_data['address']) : null,
                'city' => !empty($payment_data['city']) ? sanitize_text_field((string) $payment_data['city']) : null,
                'state' => !empty($payment_data['state']) ? sanitize_text_field((string) $payment_data['state']) : null,
                'zip' => !empty($payment_data['zip']) ? sanitize_text_field((string) $payment_data['zip']) : null,
                'country' => !empty($payment_data['country']) ? sanitize_text_field((string) $payment_data['country']) : null,
            ]),
            'order' => array_filter([
                'invoiceNumber' => !empty($payment_data['invoice_number']) ? sanitize_text_field((string) $payment_data['invoice_number']) : null,
                'description' => !empty($payment_data['description']) ? sanitize_text_field((string) $payment_data['description']) : null,
            ]),
        ];
    }
}
