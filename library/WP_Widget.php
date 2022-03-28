<?php

namespace WDK;

use WDK\includes\Log;
use WDK\includes\Widget;

/**
 * Class Widgets - Extends Wordpress' WP_Widget class so that we can inject config variables
 */
class WP_Widget extends \WP_Widget
{
    public static $config = null;
    public function __construct() {
        try {
            $class = (new \ReflectionClass(static::class))->getShortName();
            self::$config = null;

            if($config = Widget::getWidgetConfig($class)) {
                self::$config = $config;
                parent::__construct(
                    self::$config['id'], // Base ID
                    self::$config['name'], // Name
                    array( 'description' => self::$config['description'] ) // Args
                );
            }
        } catch (\ReflectionException $e) {
            Log::Write('Widget Reflection Class failed.');
        }
    }
}
