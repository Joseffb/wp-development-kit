<?php
/**
 * Contains the Stripe_Rest_Api_Provider class and its legacy alias.
 *
 * @package WDK
 */


namespace WDK;

/**
 * Provides the Stripe REST API Provider integration implementation.
 */
if (!class_exists(__NAMESPACE__ . '\\Stripe_Rest_Api_Provider', false)) {
class Stripe_Rest_Api_Provider extends Payment_Provider
{
    private string $api_secret_key;
    private string $api_url = 'https://api.stripe.com/v1';
    private ?string $api_account;

    public function __construct(string $api_secret_key, ?string $account_id = null)
    {
        $this->api_secret_key = $api_secret_key;
        $this->api_account = $account_id;
    }

    public function create_payment(array $payment_data): array
    {
        if ($this->has_unsafe_card_data($payment_data)) {
            return $this->reject_unsafe_legacy_payload('stripe');
        }

        $normalized = $this->normalize_payment_data($payment_data);
        if (!empty($normalized['error'])) {
            return $this->normalize_response('stripe', 'failed', [], null, null, $normalized['error']);
        }

        $request = [
            'amount' => $normalized['amount'],
            'currency' => $normalized['currency'],
            'description' => $normalized['description'],
            'metadata' => $normalized['metadata'],
            'capture_method' => $normalized['capture_method'],
            'confirm' => $normalized['confirm'] ? 'true' : 'false',
        ];

        if (!empty($normalized['payment_method'])) {
            $request['payment_method'] = $normalized['payment_method'];
        } else {
            $request['automatic_payment_methods'] = ['enabled' => 'true'];
        }

        if (!empty($normalized['customer'])) {
            $request['customer'] = $normalized['customer'];
        }

        if (!empty($normalized['receipt_email'])) {
            $request['receipt_email'] = $normalized['receipt_email'];
        }

        if (!empty($normalized['return_url'])) {
            $request['return_url'] = $normalized['return_url'];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->api_secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        if (!empty($this->api_account)) {
            $headers['Stripe-Account'] = $this->api_account;
        }

        $response = $this->perform_request($this->api_url . '/payment_intents', [
            'method' => 'POST',
            'headers' => $headers,
            'body' => http_build_query($request),
        ]);

        if (!$response['ok']) {
            return $this->normalize_response('stripe', 'failed', [], null, null, $response['error']);
        }

        $data = $this->decode_json_body($response['body']);
        if ($response['status'] >= 400) {
            return $this->normalize_response(
                'stripe',
                'failed',
                $data,
                $data['id'] ?? null,
                null,
                $data['error']['message'] ?? ($data['message'] ?? 'Stripe request failed.')
            );
        }

        $nextAction = null;
        if (!empty($data['next_action']['redirect_to_url']['url'])) {
            $nextAction = [
                'type' => 'redirect',
                'url' => $data['next_action']['redirect_to_url']['url'],
            ];
        } elseif (!empty($data['client_secret'])) {
            $nextAction = [
                'type' => 'client_secret',
                'client_secret' => $data['client_secret'],
            ];
        }

        return $this->normalize_response(
            'stripe',
            $this->map_status($data['status'] ?? ''),
            $data,
            $data['id'] ?? null,
            $nextAction,
            $data['last_payment_error']['message'] ?? null
        );
    }

    private function normalize_payment_data(array $payment_data): array
    {
        $amount = Compatibility::getArrayValue($payment_data, ['amount_cents']);
        if ($amount === null) {
            $legacyAmount = Compatibility::getArrayValue($payment_data, ['amount']);
            $amount = Compatibility::normalizeMoneyAmount($legacyAmount);
            if ($legacyAmount !== null) {
                Compatibility::warn(__METHOD__, 'Passing Stripe amount as a decimal is deprecated. Use amount_cents instead.');
            }
        }

        if ($amount === null || $amount < 1) {
            return ['error' => 'Stripe payments require a positive amount or amount_cents value.'];
        }

        $metadata = $payment_data['metadata'] ?? [];
        foreach (['first_name', 'last_name', 'address', 'city', 'state', 'zip', 'country'] as $legacyKey) {
            if (!empty($payment_data[$legacyKey]) && !isset($metadata[$legacyKey])) {
                Compatibility::warn(__METHOD__, 'Legacy Stripe customer fields are deprecated. Provide metadata explicitly instead.');
                $metadata[$legacyKey] = sanitize_text_field((string) $payment_data[$legacyKey]);
            }
        }

        $currency = strtolower((string) Compatibility::getArrayValue($payment_data, ['currency'], 'usd'));
        $paymentMethod = Compatibility::getArrayValue($payment_data, ['payment_method_id', 'payment_method']);
        $confirm = array_key_exists('confirm', $payment_data)
            ? filter_var($payment_data['confirm'], FILTER_VALIDATE_BOOLEAN)
            : !empty($paymentMethod);

        return [
            'amount' => $amount,
            'currency' => $currency,
            'description' => !empty($payment_data['description']) ? sanitize_text_field((string) $payment_data['description']) : null,
            'metadata' => $metadata,
            'payment_method' => $paymentMethod ? sanitize_text_field((string) $paymentMethod) : null,
            'confirm' => $confirm,
            'capture_method' => !empty($payment_data['capture_method']) ? sanitize_key((string) $payment_data['capture_method']) : 'automatic',
            'return_url' => !empty($payment_data['return_url']) ? esc_url_raw((string) $payment_data['return_url']) : null,
            'customer' => !empty($payment_data['customer']) ? sanitize_text_field((string) $payment_data['customer']) : null,
            'receipt_email' => !empty($payment_data['receipt_email']) ? sanitize_email((string) $payment_data['receipt_email']) : null,
        ];
    }

    private function map_status(string $status): string
    {
        return match ($status) {
            'succeeded' => 'succeeded',
            'processing' => 'pending',
            'requires_action', 'requires_confirmation', 'requires_capture' => 'requires_action',
            'requires_payment_method', 'canceled' => 'failed',
            default => 'pending',
        };
    }
}
}

/**
 * Legacy alias kept for backwards compatibility with historical WDK naming.
 */
if (!class_exists(__NAMESPACE__ . '\\Stripe_Rest_API_Provider', false)) {
class Stripe_Rest_API_Provider extends Stripe_Rest_Api_Provider
{
}
}
