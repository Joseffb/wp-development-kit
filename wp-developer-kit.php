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
$autoloader = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require($autoloader);

use WDK\System;

const WDK_VERSION = '0.0.1';
const WDK_PLUGIN = __FILE__;

if (!defined('WDK_TEMPLATE_LOCATIONS_BASE')) {
    if ($config_base = get_option('WDK_TEMPLATE_LOCATIONS_BASE')) {
        define("WDK_TEMPLATE_LOCATIONS_BASE", $config_base);
    } else if(is_dir(get_stylesheet_directory() . '/wdk/views')) {
        // template override locations
        define("WDK_TEMPLATE_LOCATIONS_BASE",[get_stylesheet_directory() . '/wdk/views']);
    } else {
        define("WDK_TEMPLATE_LOCATIONS_BASE", []);
    }
}
$locations = WDK_TEMPLATE_LOCATIONS_BASE;

if (!defined('WDK_CONFIG_BASE')) {
    if ($config_base = get_option('WDK_CONFIG_BASE')) {
        define("WDK_CONFIG_BASE", $config_base);
    } else if(is_dir(get_stylesheet_directory() . '/wdk/config')) {
        // WP theme based config location
        define("WDK_CONFIG_BASE", get_stylesheet_directory() . '/wdk/config');
    } else {
        define("WDK_CONFIG_BASE", __DIR__ . "/configs");
    }
}

define('WDK_PLUGIN_BASENAME',
    plugin_basename(WDK_PLUGIN)
);

define('WDK_PLUGIN_NAME',
    trim(dirname(WDK_PLUGIN_BASENAME), '/')
);

System::Start($locations);