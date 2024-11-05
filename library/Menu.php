<?php

namespace WDK;/**
 * Class Menu - Help tools for Menu creation, placement and management.
 */
class Menu
{
    /**
     * Programmatically create a menu from json config.
     * @param array $config
     */
    public static function CreateCustomMenu(array $config): void
    {
        add_action('init', static function () use ($config) {
            if (!get_option('menu_'.$config['menu_name'] . '_installed') && !wp_get_nav_menu_object($config['menu_name'])) {
                $menu_id = wp_create_nav_menu($config['menu_name']);

                foreach ($config['items'] as $item) {
                    wp_update_nav_menu_item($menu_id, 0, array(
                            'menu-item-title' => __($item['title']),
                            'menu-item-classes' => $item['classes'],
                            'menu-item-url' => home_url($item['url']),
                            'menu-item-status' => 'publish')
                    );
                    if (!has_nav_menu($config['location'])) {
                        $locations = get_theme_mod('nav_menu_locations');
                        $locations[$config['location']] = $menu_id;
                        set_theme_mod('nav_menu_locations', $locations);
                    }
                }
                update_option('menu_'.$config['menu_name'] . '_installed', true);

            }
            if(!empty($config['menu_config'])) {
                $m = $config['menu_config'];
                add_filter('wp_nav_menu_args', static function ($args) use ($config, $m) {
                    $menu = str_replace("_","-",strtolower($config['location']));
                    if (!empty($args['menu']) && $menu === $args['menu']->slug) {

                        foreach($m as $k => $v) {
                        	if($k === 'walker') {
		                        $args[$k] = new $v();
	                        } else {
		                        $args[$k] = $v;
	                        }
                        }
                    }
                    return $args;
                });
            }

        });
    }

    /**
     * Get a menu id from it's common name.
     * @param $name
     * @return int
     */
    public static function GetMenuIDFromName($name): int
    {
        return get_term_by('name', $name, 'nav_menu')->term_id;
    }
}
