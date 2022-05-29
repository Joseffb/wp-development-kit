<?php
namespace WDK\Library;
use JsonException;

/**
 * Class Widget
 */
class Widget {
    /**
     * Grabs widget config from the json directory for a specific widget by Class
     * @param $class
     * @return mixed|null
     * @throws JsonException
     */
    public static function get_Config($class): mixed
    {
        $file = get_template_directory()."/app/Config/Widget.json";
        if(file_exists($file)) {
            $configs = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            foreach($configs as $config) {
                if($class === $config['callback']['class']) {
                    return $config;
                }
            }
        }
        return false;
    }

    /**
     * Installs the widget referred to in config file. You also need to have it exists or this will error
     * @param $config
     */
    public static function CreateCustomWidget($config): void
    {
        //Log::Write($config);
        $ns = $config['ns']."\\".$config['class'];
        if(class_exists($ns)) {
            add_action('widgets_init', function () use ($ns) {
                /* Register the 'primary' sidebar. */
                $ns = new $ns();
                register_widget($ns);
            });
        } else {
            Utility::Log('Error: Namespaced Widget class '.$ns.' not found. Widget not loaded.');
        }
    }

    /**
     * Counts number of times a widget is in use in any sidebar.
     * @param $widget_name
     * @param bool $increment
     * @return int
     */
    public static function count_Instances ($widget_name, bool $increment): int
    {
        $active_sidebars = get_option('sidebars_widgets');
        $matches = array();
        $pattern = "/$widget_name/i";  //contains widget name
        if(is_array($active_sidebars)) {
            foreach ($active_sidebars as $key => $value) {
                //loop through each key under data sub array
                if(is_array($value)) {
                    //Log::Write($value);
                    foreach ($value as $key2 => $value2) {
                        //check for match.
                        if (preg_match($pattern, $value2)) {
                            //add to match array.
                            $matches[] = $value2;
                            //match found, so break from foreach
                            break;
                        }
                    }
                }
            }
        }
        if($increment) {
            return count($matches)+1;
        }

        return count($matches);
    }
}
