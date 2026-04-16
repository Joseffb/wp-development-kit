<?php
/**
 * Fixture plugin file used by the shared runtime coexistence suite.
 *
 * @package WDK\Tests
 */

/*
Plugin Name: WDK Fixture Legacy
Version: 1.0.0
*/

require_once WP_PLUGIN_DIR . '/wp-development-kit/tests/fixtures/wp-env/shared-runtime-bootstrap.php';

wdk_fixture_register_bundle(
    'wdk-fixture-legacy',
    'plugin',
    __DIR__,
    '0.2.0',
    __DIR__ . '/wdk/bootstrap.php'
);
