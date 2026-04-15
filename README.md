# WP Development Kit

WP Development Kit, or WDK, is a WordPress development library for building content models, taxonomies, template flows, and admin tooling with a JSON-first setup layer.

## Breaking Change Notice

WDK `0.3.0` is a stabilization release with compatibility shims.

Unavoidable breaking changes:

- PHP `8.1+` is now required.
- Payment providers now expect secure tokenized or hosted-flow payloads.
- Raw card-number / PAN / CVV server-side payloads are rejected.
- The repo now tracks Composer, PHPUnit, CI, and local wp-env tooling as part of the supported developer contract.

Compatibility shims included in `0.3.0`:

- Legacy short provider names such as `PayPal_Rest_API_Provider` and `WP_Local_Search_Provider` still work.
- Deprecated provider-constructor argument patterns are still normalized where they can be adapted safely.
- Legacy PayPal v1-style transaction payloads are normalized into Orders v2 request shapes.
- Legacy Stripe decimal `amount` values are normalized into `amount_cents`.

The shims emit deprecation notices so existing integrations keep moving while you upgrade.

## What WDK Provides

- JSON-based configuration for post types, taxonomies, menus, posts, fields, shortcodes, sidebars, and widgets.
- Shadow taxonomies for linking posts through mirrored taxonomy terms.
- Twig and Timber-compatible template flows for themes, plugins, and admin pages.
- Post, taxonomy, media, meta, relationship, and comment helpers through `PostInterface`.
- Utility helpers for debugging and troubleshooting.

## Supported Runtime Matrix

| Surface | Supported |
| --- | --- |
| PHP | `8.1`, `8.2`, `8.3`, `8.4`, `8.5` |
| WordPress install style | Plugin install or Composer install |
| Twig integration | Timber `^1.24.1` or `^2.0` |
| Local WordPress tooling | Node `18+`, Docker, `@wordpress/env` |
| Automated repo validation | Composer smoke tests, PHPUnit, PHP lint, GitHub Actions |

## Installation

### Plugin install

1. Clone or download this repository into `wp-content/plugins/wp-development-kit` or `wp-content/mu-plugins/wp-development-kit`.
2. Run `composer install`.
3. Activate the plugin.

If Composer dependencies are missing, the plugin now fails closed with an admin notice instead of fatalling at bootstrap.

### Composer install

```bash
composer require joseffb/wp-development-kit
```

Then initialize WDK in your bootstrap file:

```php
require_once __DIR__ . '/vendor/autoload.php';

WDK\System::Start();
```

## Project Layout

By default WDK looks for configuration and view files in:

```text
project-root/
└── wdk/
    ├── configs/
    └── views/
```

You can override those locations with `WDK_CONFIG_BASE` and `WDK_TEMPLATE_LOCATIONS_BASE`.

## Config Files

WDK supports these JSON files:

- `Fields.json`
- `Menus.json`
- `Pages.json` / `Posts.json`
- `Post_types.json`
- `Shortcodes.json`
- `Sidebars.json`
- `Taxonomies.json`
- `Widgets.json`

Config must still be valid JSON. Invalid files stop setup processing.

## Key Behaviors

### Post Types and Taxonomies

- `Post_types.json` mirrors `register_post_type()` with extra WDK options such as `use_twig` and `shadow_in_cpt`.
- `Taxonomies.json` mirrors `register_taxonomy()` and supports admin filters, admin columns, defaults, and GraphQL naming helpers.
- Taxonomy defaults are now seeded once and stay stable across repeated boots.

### Shadow Taxonomies

Shadow taxonomies mirror posts into taxonomy terms and keep term metadata linked back to the source post. This is useful when a taxonomy should behave like a related record picker without building a full custom UI.

### Templates

WDK works with Timber and Twig. When a template is processed through WDK, the corresponding `wdk_context_*` filter can extend the view context.

```php
add_filter('wdk_context_templatename', function ($context) {
    $context['message'] = 'Hello from WDK';
    return $context;
});
```

## Compatibility Shims

`0.3.0` keeps older integrations moving where it is safe to do so.

### Search providers

Recommended:

```php
$search = new WDK\Search(WDK\WP_Local_Search_Provider::class);
```

Still supported with deprecation notice:

```php
$search = new WDK\Search('WP_Local_Search_Provider');
```

### Payment providers

Recommended:

```php
$payments = new WDK\Payments(WDK\PayPal_Rest_API_Provider::class, [
    'client-id',
    'client-secret',
]);
```

Still supported with deprecation notice:

```php
$payments = new WDK\Payments('PayPal_Rest_API_Provider', [
    'client-id',
    'client-secret',
]);
```

## Secure Payment Guidance

WDK still exposes `Payment_Provider::create_payment(array $payment_data)`, but the accepted payloads have changed.

### Normalized response shape

All providers return a normalized array:

```php
[
    'status' => 'succeeded|pending|requires_action|failed',
    'provider' => 'stripe|paypal|authorizenet',
    'object_id' => 'provider-specific-id-or-null',
    'next_action' => [
        'type' => 'redirect|client_secret',
        // provider-specific keys...
    ],
    'message' => 'optional human-readable message',
    'raw' => [ /* provider payload */ ],
]
```

### Stripe

Stripe now uses Payment Intents.

Recommended payload keys:

- `amount_cents`
- `currency`
- `payment_method_id`
- `confirm`
- `capture_method`
- `return_url`
- `customer`
- `receipt_email`
- `metadata`

Deprecated but normalized:

- decimal `amount`

Rejected:

- `card_number`
- `cvv`
- `expiration_date`
- nested raw card `source` payloads

### PayPal

PayPal now uses Orders v2.

Recommended payload keys:

- `intent`
- `purchase_units`
- `application_context`
- `return_url`
- `cancel_url`

Deprecated but normalized:

- v1-style `transactions`
- `redirect_urls`

### Authorize.Net

Authorize.Net now requires Accept.js opaque token data.

Recommended payload keys:

- `opaque_data`
- `opaque_data_descriptor`
- `opaque_data_value`
- `transaction_type`
- `amount`

Rejected:

- raw PAN / card-number payloads

## Upgrade Guide From 0.2.x

1. Upgrade PHP to `8.1+`.
2. Run `composer install` or `composer update`.
3. If you use the tracked local environment, run `npm install`.
4. Move provider references toward fully qualified class names.
5. Replace raw card payloads with secure tokenized or hosted-flow inputs.
6. Update any payment consumers to read the normalized response shape.
7. Re-run the repo validation commands below.

## Developer Tooling

### Composer and PHPUnit

```bash
composer validate --no-check-publish
composer lint
composer test
```

### Local WordPress via wp-env

```bash
npm install
npm run wp-env:start
npm run wp-env:cli
npm run wp-env:test-cli
npm run wp-env:stop
```

`wp-env` requires Docker.

## Examples

### Default search

```php
$results = WDK\Search::find('conference');
```

### Secure Stripe payment intent

```php
$payment = WDK\Payments::create_payment([
    'amount_cents' => 1500,
    'currency' => 'usd',
    'payment_method_id' => 'pm_123',
    'confirm' => true,
    'return_url' => 'https://example.com/payments/return',
], ['sk_test_123'], WDK\Stripe_Rest_Api_Provider::class);
```

### Secure PayPal order

```php
$payment = WDK\Payments::create_payment([
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'amount' => [
            'currency_code' => 'USD',
            'value' => '25.00',
        ],
    ]],
    'return_url' => 'https://example.com/paypal/return',
    'cancel_url' => 'https://example.com/paypal/cancel',
], ['client-id', 'client-secret']);
```

### Secure Authorize.Net opaque token flow

```php
$payment = WDK\Payments::create_payment([
    'amount' => 25.00,
    'opaque_data' => [
        'dataDescriptor' => 'COMMON.ACCEPT.INAPP.PAYMENT',
        'dataValue' => 'opaque-token-value',
    ],
], ['api-login-id', 'transaction-key'], WDK\AuthorizeNet_Rest_API_Provider::class);
```

## Validation Status For 0.3.0

This repository now ships with:

- tracked `composer.lock`
- tracked `package.json` and `.wp-env.json`
- tracked PHPUnit bootstrap and suite
- tracked GitHub Actions CI

The repo validation currently covers:

- PHP linting across `library/` and `tests/`
- standalone smoke tests
- PHPUnit regression coverage
- wp-env CLI/test-cli boot verification
