<?php

namespace WDK;

class PayPal_Rest_API_Provider extends Payment_Provider
{
    private string $client_id;
    private string $client_secret;
    private string $api_url;
    private ?string $access_token = null;

    public function __construct(string $client_id, string $client_secret, bool $sandbox = true)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->api_url = $sandbox ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        $this->access_token = $this->get_access_token();
    }

    public function create_payment(array $payment_data): array
    {
        if ($this->access_token === null) {
            return $this->normalize_response('paypal', 'failed', [], null, null, 'Unable to authenticate with PayPal.');
        }

        $normalized = $this->normalize_payment_data($payment_data);
        if (!empty($normalized['error'])) {
            return $this->normalize_response('paypal', 'failed', [], null, null, $normalized['error']);
        }

        $response = $this->perform_request($this->api_url . '/v2/checkout/orders', [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($normalized['request']),
        ]);

        if (!$response['ok']) {
            return $this->normalize_response('paypal', 'failed', [], null, null, $response['error']);
        }

        $data = $this->decode_json_body($response['body']);
        if ($response['status'] >= 400) {
            return $this->normalize_response(
                'paypal',
                'failed',
                $data,
                $data['id'] ?? null,
                null,
                $data['message'] ?? 'PayPal order creation failed.'
            );
        }

        $approvalUrl = null;
        foreach (($data['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approvalUrl = $link['href'] ?? null;
                break;
            }
        }

        return $this->normalize_response(
            'paypal',
            $this->map_status($data['status'] ?? ''),
            $data,
            $data['id'] ?? null,
            $approvalUrl ? ['type' => 'redirect', 'url' => $approvalUrl] : null,
            null
        );
    }

    private function get_access_token(): ?string
    {
        $response = $this->perform_request($this->api_url . '/v1/oauth2/token', [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
        ]);

        if (!$response['ok']) {
            return null;
        }

        $data = $this->decode_json_body($response['body']);
        return $data['access_token'] ?? null;
    }

    private function normalize_payment_data(array $payment_data): array
    {
        if (!empty($payment_data['transactions']) && is_array($payment_data['transactions'])) {
            Compatibility::warn(__METHOD__, 'Legacy PayPal v1 transaction payloads are deprecated. Use purchase_units and application_context instead.');
            $purchaseUnits = [];
            foreach ($payment_data['transactions'] as $transaction) {
                $amount = $transaction['amount'] ?? [];
                $purchaseUnits[] = array_filter([
                    'amount' => [
                        'currency_code' => strtoupper((string) ($amount['currency'] ?? 'USD')),
                        'value' => (string) ($amount['total'] ?? '0.00'),
                    ],
                    'description' => $transaction['description'] ?? null,
                    'invoice_id' => $transaction['invoice_number'] ?? null,
                    'items' => $transaction['item_list']['items'] ?? null,
                ]);
            }

            $redirectUrls = $payment_data['redirect_urls'] ?? [];
            $request = [
                'intent' => strtoupper((string) (($payment_data['intent'] ?? 'CAPTURE') === 'sale' ? 'CAPTURE' : ($payment_data['intent'] ?? 'CAPTURE'))),
                'purchase_units' => $purchaseUnits,
                'application_context' => array_filter([
                    'return_url' => !empty($redirectUrls['return_url']) ? esc_url_raw((string) $redirectUrls['return_url']) : null,
                    'cancel_url' => !empty($redirectUrls['cancel_url']) ? esc_url_raw((string) $redirectUrls['cancel_url']) : null,
                ]),
            ];

            return ['request' => $request];
        }

        $purchaseUnits = $payment_data['purchase_units'] ?? [];
        if (empty($purchaseUnits)) {
            return ['error' => 'PayPal payments require purchase_units or a legacy transactions payload.'];
        }

        $applicationContext = $payment_data['application_context'] ?? [];
        if (!empty($payment_data['return_url']) || !empty($payment_data['cancel_url'])) {
            $applicationContext['return_url'] = !empty($payment_data['return_url']) ? esc_url_raw((string) $payment_data['return_url']) : ($applicationContext['return_url'] ?? null);
            $applicationContext['cancel_url'] = !empty($payment_data['cancel_url']) ? esc_url_raw((string) $payment_data['cancel_url']) : ($applicationContext['cancel_url'] ?? null);
        }

        return [
            'request' => [
                'intent' => strtoupper((string) ($payment_data['intent'] ?? 'CAPTURE')),
                'purchase_units' => $purchaseUnits,
                'application_context' => $applicationContext,
            ],
        ];
    }

    private function map_status(string $status): string
    {
        return match ($status) {
            'COMPLETED' => 'succeeded',
            'CREATED', 'PAYER_ACTION_REQUIRED' => 'requires_action',
            'APPROVED' => 'pending',
            default => 'pending',
        };
    }
}
