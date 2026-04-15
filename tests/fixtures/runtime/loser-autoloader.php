<?php
/**
 * Fixture autoloader used by the shared runtime arbitration tests.
 *
 * @package WDK\Tests
 */


$GLOBALS['wdk_runtime_autoloaders_loaded'][] = 'loser';

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
