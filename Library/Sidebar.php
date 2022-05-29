<?php

namespace WDK\Library;
/**
 * Class Sidebar
 */
class Sidebar
{
    /**
     * Registers custom sidebar via JSON config
     * @param array $config
     */
    public static function CreateCustomSidebar(array $config): void
    {
        add_action('init', function () use ($config) {
            register_sidebar($config);
        });
    }

    /**
     * Sets Custom widgets into side bar based on json config variables.
     * @param $sidebar_name
     * @param array $defaults
     */
    public static function SetCustomSidebarDefaults($sidebar_name, array $defaults): void
    {
        add_action('init', function () use ($sidebar_name, $defaults) {
            //Log::Write($sidebar_name);
            $check_option = get_option("sidebar_".$sidebar_name."_installed_STYLECENTER");
            //Log::Write($check_option);
            if (empty($check_option)) {
                //Log::Write('inside');
                if(!empty($defaults) && is_array($defaults)) {
                    foreach ($defaults as $w) {
                        $widget_name = $w['id'];
                        $active_sidebars = get_option('sidebars_widgets');
                        $widget = get_option("widget_$widget_name");
                        $options = !empty($w['options'])?$w['options']:[];
                        // special case for nav menus
                        if ($widget_name === 'nav_menu') {
                            $options['nav_menu'] = Menu::GetMenuIDFromName($options['nav_menu']);
                        }

                        //save widget index
                        $widget[] = $options;
                        //Log::Write($widget);
                        $widget = array_unique($widget, SORT_REGULAR);
                        //Log::Write($widget);
                        $c_array = array_keys($widget);
                        $already_present = array_search($options, $widget);
                        $count = is_int($already_present)?$already_present:array_pop($c_array)+1;
                        //Log::Write($count);
                        //Log::Write(array_search($options, $widget));
                        $widget['_multiwidget'] = 1;
                        update_option("widget_$widget_name", $widget);

                        //save sidebar configuration
                        $active_sidebars[$sidebar_name][0] = "$widget_name-$count";
                        //Log::Write($active_sidebars);
                        update_option('sidebars_widgets', $active_sidebars);
                    }
                }


                update_option("sidebar_".$sidebar_name."_installed_STYLECENTER", true);
            }
        });
    }

}
