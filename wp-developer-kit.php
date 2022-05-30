<?php
/*
Plugin Name: WP-Developer-Kit
Plugin URI: https://github.com/josefb/wp-developer-kit
Description: Installs the WP Developer Kit for use by other applications
Author: Joseff Betancourt
Author URI: https://joseffb.com/
Version: 0.1
Text Domain: wdk
License: LGPL3 or later
License URI: https://www.gnu.org/licenses/lgpl-3.0.html
*/
use WDK\Library\System;

const WDK_VERSION = '0.0.1';
const WDK_PLUGIN = __FILE__;

if (!defined(WDK_TEMPLATE_LOCATIONS_BASE)) {
    if ($config_base = get_option('WDK_TEMPLATE_LOCATIONS_BASE')) {
        define("WDK_TEMPLATE_LOCATIONS_BASE", $config_base);
    } else if(is_dir(get_stylesheet_directory() . '/wdk/Views')) {
        // template override locations
        define("WDK_TEMPLATE_LOCATIONS_BASE",[get_stylesheet_directory() . '/wdk/Views']);
    } else {
        define("WDK_TEMPLATE_LOCATIONS_BASE", []);
    }
}
$locations = WDK_TEMPLATE_LOCATIONS_BASE;

if (!defined(WDK_CONFIG_BASE)) {
    if ($config_base = get_option('WDK_CONFIG_BASE')) {
        define("WDK_CONFIG_BASE", $config_base);
    } else if(directoryExists(get_stylesheet_directory() . '/wdk/Config')) {
        // WP theme based config location
        define("WDK_CONFIG_BASE",get_stylesheet_directory() . '/wdk/Config');
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

add_action('init', static function () use ($locations) {
    /**
     * @throws JsonException
     */
    System::Start($locations);
});