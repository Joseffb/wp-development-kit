<?php

namespace WDK;

use DirectoryIterator;
use JsonException;

/**
 * Class Install
 */
class System
{
    /**
     * Called from a theme or plugin's functions file to start the framework.
     *
     */
    public static function Start(array $locations = []): bool
    {
        $trace = debug_backtrace();
        $call_path_dir = dirname($trace[0]['file']);
        if (!defined('WDK_CONFIG_BASE')) {
            if (is_dir($call_path_dir . "/wdk/configs")) {
                define("WDK_CONFIG_BASE", $call_path_dir . "/wdk/configs");
            } else if ($config_base = get_option('WDK_CONFIG_BASE')) {
                define("WDK_CONFIG_BASE", $config_base);
            } else if (is_dir(get_stylesheet_directory() . '/wdk/config')) {
                // WP theme based config location
                define("WDK_CONFIG_BASE", get_stylesheet_directory() . '/wdk/config');
            } else {
                define("WDK_CONFIG_BASE", dirname(__DIR__) . "/configs");
            }
        }

        if (!defined('WDK_TEMPLATE_LOCATIONS_BASE')) {
            if (is_dir($call_path_dir . "/wdk/views")) {
                define("WDK_TEMPLATE_LOCATIONS_BASE", [$call_path_dir . "/wdk/views"]);
            } else if ($config_base = get_option('WDK_TEMPLATE_LOCATIONS_BASE')) {
                define("WDK_TEMPLATE_LOCATIONS_BASE", $config_base);
            } else if (is_dir(plugin_dir_path(__FILE__) . '/wdk/views')) {
                // template override locations
                define("WDK_TEMPLATE_LOCATIONS_BASE", [plugin_dir_path() . '/wdk/views']);
            } else if (is_dir(get_stylesheet_directory() . '/wdk/views')) {
                // template override locations
                define("WDK_TEMPLATE_LOCATIONS_BASE", [get_stylesheet_directory() . '/wdk/views']);
            } else if (is_dir(__DIR__ . '/views')) {
                // template override locations
                define("WDK_TEMPLATE_LOCATIONS_BASE", [__DIR__ . '/views']);
            } else {
                define("WDK_TEMPLATE_LOCATIONS_BASE", []);
            }
        }


        //Setup json based configuration
        try {
            self::Setup();
            //Setup Twig template system
            //looks for twig files in the following locations.
            Template::Setup(array_merge(WDK_TEMPLATE_LOCATIONS_BASE, [$call_path_dir . "/wdk/views", //for child templates
                    get_stylesheet_directory() . "/wdk/views", //for child templates
                    get_stylesheet_directory(), //for child templates
                    get_stylesheet_directory() . "/wdk/views", //for child templates
                    get_template_directory(),
                    get_template_directory() . "/wdk/views",
                    dirname(__DIR__) . '/views']
            ));
            return true;
        } catch
        (\Exception $e) {
            Utility::Log('JSON ERROR, Please validate config files.');
            Utility::Log($e);
            return false;
        }
    }

    /**
     * Install command for the customizations in the config files.
     *
     * @param null $dir
     *
     * @return bool|int|null
     * @throws JsonException
     */
    public static function Setup($dir = null)
    {
        $config_files = WDK_CONFIG_BASE;
        if (Utility::IsDirEmpty($config_files)) {
            $config_files = get_template_directory();
        }
        $dir = $dir ?: $config_files;
        if (Utility::DoesDirExist($dir)) {
            $dir = new DirectoryIterator($dir);
            foreach ($dir as $fileinfo) {
                if (!$fileinfo->isDot() && (bool)strpos($fileinfo->getFilename(), '.json')) {
                    $config_file = json_decode(file_get_contents($fileinfo->getRealPath()), true, 512, JSON_THROW_ON_ERROR);
                    if (!empty($config_file)) {
                        switch (strtolower($fileinfo->getFilename())) {
                            case 'posttypes.json':
                            case 'post_types.json':
                                self::ProcessPostTypes($config_file);
                                break;
                            case 'taxonomies.json':
                            case 'taxonomy.json':
                                self::ProcessTaxonomies($config_file);
                                break;
                            case 'shortcodes.json':
                            case 'shortcode.json':
                                self::ProcessShortcodes($config_file);
                                break;
                            case 'sidebars.json':
                            case 'sidebar.json':
                                self::ProcessSidebars($config_file);
                                break;
                            case 'menus.json':
                            case 'menu.json':
                                self::ProcessMenus($config_file);
                                break;
                            case 'widgets.json':
                            case 'widget.json':
                                self::ProcessWidgets($config_file);
                                break;
                            case 'fields.json':
                            case 'field.json':
                                self::ProcessFields($config_file);
                                break;
                            case 'pages.json':
                            case 'page.json':
                                self::ProcessPosts($config_file);
                                break;
                        }
                    }
                }
            }
        }
        return null; //makes IDE happy
    }

    /**
     * @param $config_file
     *
     * @return void
     */
    public static function ProcessPostTypes($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                PostType::CreateCustomPostType($config['name'], $config['args']);
            }
        }
    }

    /**
     * @param $config_array
     *
     * @return void
     */
    public static function ProcessTaxonomies($config_array): void
    {
        foreach ($config_array as $config) {
            if (!empty($config)) {
                $name = $term_name = $config['name'];
                if (!empty($config['rewrite']['slug'])) {
                    $name = ['human' => $name, 'slug' => $config['rewrite']['slug']];
                }
                $post_types = !empty($config['post_types']) ? $config['post_types'] : array("post");
                $labels = !empty($config['labels']) ? $config['labels'] : array();
                $options = !empty($config['options']) ? $config['options'] : array();
                // Taxonomy crate must happen on every load
                Taxonomy::CreateCustomTaxonomy($name, $post_types, $labels, $options);
                update_option('tax_' . $name . '_installed', true);
                delete_option('tax_term_' . $name . '_installed');
                if (!empty($config['defaults']) && (bool)get_option('tax_term_' . $name . '_installed') === false) {
                    // Default values need to only be seeded once, or you won't be able to delete or rename them later.
                    Taxonomy::CreateTerm($term_name, $config['defaults']);
                    update_option('tax_term_' . $name . '_installed', true);
                }
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function ProcessShortcodes($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                $buttons = !empty($config['tinyMCE_buttons']) ? $config['tinyMCE_buttons'] : false;
                Shortcode::CreateCustomShortcode($config['tag'], $config['callback']['ns'], $config['callback']['method'], $buttons);
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function ProcessSidebars($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                Sidebar::CreateCustomSidebar($config['config']);
                if (!empty($config['defaults'])) {
                    Sidebar::SetCustomSidebarDefaults($config['config']['id'], $config['defaults']);
                }
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function ProcessMenus($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                Menu::CreateCustomMenu($config);
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function ProcessWidgets($config_file): void
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                Widget::CreateCustomWidget($config['callback']);
            }
        }
    }

    /**
     * @param $config_file
     *
     * @return void
     */
    public static function ProcessFields($config_file): void
    {
        foreach ($config_file as $k => $field) {
            $field = !empty($field['field']) ? $field['field'] : $field;
            if ($k === "global") {
                break;
            }
            if (!empty($field['post_types'])) {
                foreach ($field['post_types'] as $pt) {
                    $post_ty = str_replace(" ", "_", $pt);

                    Field::AddCustomFieldToPost(
                        $post_ty,
                        $field['id'],
                        $field['label'],
                        $field['type'],
                        $field['options'],
                        $field
                    );
                    if (!empty($field['admin_column_header'])) {
                        Field::AddFieldToPostAdminColumns($field, $pt);
                    }
                }
            }
        }

    }

    /**
     * @param $page_config_file
     * @return void
     */
    public static function ProcessPosts($page_config_file): void
    {
        foreach ($page_config_file as $config) {
            if (!empty($config)) {
                $post_type = !empty($config['post_type']) ? $config['post_type'] : [];
                $post_title = !empty($config['post_title']) ? $config['post_title'] : [];
                $post_content = !empty($config['post_content']) ? $config['post_content'] : [];
                $post_meta = !empty($config['post_meta']) ? $config['post_meta'] : [];
                add_action('init', function () use ($post_type, $post_title, $post_content, $post_meta) {
                    Post::CreatePost($post_type, $post_title, $post_content, $post_meta);
                }, 10000);
            }
        }
    }
}
