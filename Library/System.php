<?php

namespace WDK\Library;

use DirectoryIterator;
use JsonException;

/**
 * Class Install
 */
class System
{
    /**
     * Called from a theme's functions file to start the framework.
     * @throws JsonException
     */
    public static function Start($locations = []): void
    {
        //Setup json based configuration
        self::Setup();

        //Setup Twig template system
        //looks for twig files in the following locations.
        Template::Setup(array_merge($locations, [
                get_stylesheet_directory(), //for child templates
                get_stylesheet_directory() . "/wdk/Views", //for child templates
                get_template_directory(),
                get_template_directory() . "/wdk/Views",
                __DIR__ . '/Views']
        ));
    }

    /**
     * @param $dir
     * @return bool|null
     */
    public static function is_dir_empty($dir): ?bool
    {
        if (!is_readable($dir)) {
            return NULL;
        }
        // if we see . and .. it's an empty directory.
        return (count(scandir($dir)) === 2);
    }

    /**
     * Install command for the customizations in the config files.
     *
     * @param null $dir
     *
     * @return bool|int|null
     * @throws JsonException
     */
    public static function Setup($dir = null): bool|int|null
    {
        //$config_files = get_stylesheet_directory() . '/app/Config';
        $config_files = WDK_CONFIG_BASE;
        if (self::is_dir_empty($config_files)) {
            $config_files = get_template_directory();
        }
        $dir = new DirectoryIterator($dir ?: $config_files);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot() && str_ends_with($fileinfo->getFilename(), '.json')) {
                $config_file = json_decode(file_get_contents($fileinfo->getRealPath()), true, 512, JSON_THROW_ON_ERROR);

                if (!empty($config_file)) {
                    //Log::Write($fileinfo->getFilename());
                    switch ($fileinfo->getFilename()) {
                        case 'PostTypes.json':
                            self::process_Post_Types($config_file);
                            break;
                        case 'Taxonomies.json':
                            self::process_Taxonomies($config_file);
                            break;
                        case 'Shortcodes.json':
                            self::process_Shortcodes($config_file);
                            break;
                        case 'Sidebars.json':
                            self::process_Sidebars($config_file);
                            break;
                        case 'Menus.json':
                            self::process_Menus($config_file);
                            break;
                        case 'Widgets.json':
                            self::process_Widgets($config_file);
                            break;
                        case 'Fields.json':
                            self::process_Fields($config_file);
                            break;
                        case 'Pages.json':
                            self::process_Posts($config_file);
                            break;
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
    public static function process_Post_Types($config_file): void
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
    public static function process_Taxonomies($config_array): void
    {
        foreach ($config_array as $config) {
            if (!empty($config)) {
                $name = $term_name = $config['name'];
                if (!empty($config['rewrite']['slug'])) {
                    //Log::Write($config['rewrite']['slug']);
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
    public static function process_Shortcodes($config_file): void
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
    public static function process_Sidebars($config_file): void
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
    public static function process_Menus($config_file): void
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
    public static function process_Widgets($config_file): void
    {
        //Log::WriteLog('Inside Widget installer');
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
    public static function process_Fields($config_file): void
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
    public static function process_Posts($page_config_file): void
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
