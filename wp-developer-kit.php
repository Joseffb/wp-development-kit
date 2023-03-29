<?php
/*
Plugin Name: WP-Development-Kit
Plugin URI: https://github.com/Joseffb/wp-development-kit
Description: Installs the WP Development Kit. Passive library for WordPress used by other plugins and themes
Author: Joseff Betancourt
Author URI: https://joseffb.com/
Version: 0.1
Text Domain: wdk
License: MIT
*/
/**
 * Note: this library can be installed via composer or as a standalone plugin.
 */
$autoloader = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require($autoloader);

use WDK\System;

const WDK_VERSION = '0.0.8';
const WDK_PLUGIN = __FILE__;

define('WDK_PLUGIN_BASENAME',
    plugin_basename(WDK_PLUGIN)
);

define('WDK_PLUGIN_NAME',
    trim(dirname(WDK_PLUGIN_BASENAME), '/')
);
$locations = WDK_TEMPLATE_LOCATIONS_BASE;
System::Start($locations);