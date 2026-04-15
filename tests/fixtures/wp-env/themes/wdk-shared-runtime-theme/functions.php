<?php
/**
 * Fixture theme file used by the shared runtime coexistence suite.
 *
 * @package WDK\Tests
 */


require_once WP_PLUGIN_DIR . '/wp-development-kit/tests/fixtures/wp-env/shared-runtime-bootstrap.php';

wdk_fixture_register_bundle(
    'wdk-shared-runtime-theme',
    'theme',
    __DIR__,
    '0.4.0',
    __DIR__ . '/bundle-bootstrap.php'
);
