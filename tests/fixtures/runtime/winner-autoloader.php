<?php
/**
 * Fixture autoloader used by the shared runtime arbitration tests.
 *
 * @package WDK\Tests
 */


$GLOBALS['wdk_runtime_autoloaders_loaded'][] = 'winner';

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
