<?php
/**
 * Bootstrap entrypoint for the standalone WDK WordPress plugin.
 *
 * @package WDK
 */

/*
Plugin Name: WP-Development-Kit
Plugin URI: https://github.com/Joseffb/wp-development-kit
Description: Installs the WP Development Kit. Passive library for WordPress used by other plugins and themes
Author: Joseff Betancourt
Author URI: https://joseffb.com/
Version: 0.4.0
Text Domain: wdk
License: MIT
*/
/**
 * Note: this library can be installed via composer or as a standalone plugin.
 */
$runtimeLoader = plugin_dir_path(__FILE__) . 'wdk-runtime-loader.php';
require_once $runtimeLoader;

$autoloader = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (!file_exists($autoloader)) {
    add_action('admin_notices', static function () {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('WP Development Kit requires Composer dependencies. Run composer install inside the plugin directory before activating it.', 'wdk'); ?></p>
        </div>
        <?php
    });
    return;
}

require $autoloader;

const WDK_VERSION = '0.4.0';
const WDK_PLUGIN = __FILE__;

define('WDK_PLUGIN_BASENAME',
    plugin_basename(WDK_PLUGIN)
);

define('WDK_PLUGIN_NAME',
    trim(dirname(WDK_PLUGIN_BASENAME), '/')
);

wdk_register_runtime_bundle([
    'id' => 'wdk-core-plugin-runtime',
    'bundle_id' => 'wdk-core-plugin',
    'version' => WDK_VERSION,
    'autoloader' => $autoloader,
    'root' => plugin_dir_path(__FILE__),
], [
    'id' => 'wdk-core-plugin',
    'type' => 'core-plugin',
    'root' => plugin_dir_path(__FILE__),
    'template_paths' => [plugin_dir_path(__FILE__) . 'views'],
    'config_paths' => [
        plugin_dir_path(__FILE__) . 'configs',
        plugin_dir_path(__FILE__) . 'wdk/configs',
    ],
    'version' => WDK_VERSION,
]);
