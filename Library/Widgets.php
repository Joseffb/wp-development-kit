<?php

namespace WDK;

use WDK\Library\Utility;
use WDK\Library\Widget;

/**
 * Class Widgets - Extends Wordpress' WP_Widget class so that we can inject config variables
 */
class Widgets extends \WP_Widget
{
    public static $config = null;
    public function __construct() {
        try {
            $class = (new \ReflectionClass(static::class))->getShortName();
            self::$config = null;

            if($config = Widget::get_Config($class)) {
                self::$config = $config;
                parent::__construct(
                    self::$config['id'], // Base ID
                    self::$config['name'], // Name
                    array( 'description' => self::$config['description'] ) // Args
                );
            }
        } catch (\ReflectionException $e) {
            Utility::Log('Widget Reflection Class failed.');
        } catch (\JsonException $e) {
            Utility::Log('json decode failed.');
        }
    }
}
