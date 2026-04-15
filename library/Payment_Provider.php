<?php
/**
 * Contains the Payment_Provider class.
 *
 * @package WDK
 */


namespace WDK;

/**
 * Provides the base implementation for Payment Provider.
 */
abstract class Payment_Provider
{
    /**
     * Create a payment with the given data.
     *
     * @param array $payment_data An associative array containing the payment information.
     * @return mixed The result of the payment creation, specific to the provider implementation.
     */
    abstract public function create_payment(array $payment_data);

    protected function has_unsafe_card_data(array $payment_data): bool
    {
        $unsafeKeys = ['card_number', 'cvv', 'expiration_date', 'expiration_month', 'expiration_year'];

        foreach ($unsafeKeys as $key) {
            if (!empty($payment_data[$key])) {
                return true;
            }
        }

        return isset($payment_data['source']) && is_array($payment_data['source']);
    }

    protected function reject_unsafe_legacy_payload(string $provider): array
    {
        Compatibility::warn(__METHOD__, sprintf(
            'Legacy raw card payment payloads for %s are no longer processed. Use secure tokenized or hosted-flow inputs instead.',
            $provider
        ));

        return [
            'status' => 'failed',
            'provider' => $provider,
            'object_id' => null,
            'next_action' => null,
            'message' => 'Legacy raw card details are no longer accepted. Use secure tokenized or hosted-flow inputs instead.',
            'raw' => null,
        ];
    }

    protected function normalize_response(
        string $provider,
        string $status,
        array $raw,
        ?string $objectId = null,
        ?array $nextAction = null,
        ?string $message = null
    ): array {
        return [
            'status' => $status,
            'provider' => $provider,
            'object_id' => $objectId,
            'next_action' => $nextAction,
            'message' => $message,
            'raw' => $raw,
        ];
    }

    protected function perform_request(string $url, array $args): array
    {
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'error' => $response->get_error_message(),
                'headers' => [],
            ];
        }

        return [
            'ok' => true,
            'status' => (int) wp_remote_retrieve_response_code($response),
            'body' => wp_remote_retrieve_body($response),
            'headers' => wp_remote_retrieve_headers($response),
            'error' => null,
        ];
    }

    protected function decode_json_body(?string $body): array
    {
        if ($body === null || $body === '') {
            return [];
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ['message' => $e->getMessage(), 'raw_body' => $body];
        }
    }
}
