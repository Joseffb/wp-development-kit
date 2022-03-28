<?php

namespace WDK;

use Timber\Timber;

/**
 * Class Install
 */
class System
{
    /**
     * Called from a theme's functions file to start the MLA Kit framework.
     */
    public static function Start($locations = []): void
    {
        //Setup json based configuration
        self::Setup();

        //Setup Twig template system
        //looks for twig files in the following locations.
        Template::Setup(array_merge($locations, [
                get_stylesheet_directory(), //for child templates
                get_stylesheet_directory() . "/wdk", //for child templates
                get_template_directory(),
                get_template_directory() . "/wdk",
                __DIR__ . '/views']
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
     */
    public static function Setup($dir = null)
    {
        $config_files = get_stylesheet_directory() . '/app/Config';
        if (self::is_dir_empty($config_files)) {
            $config_files = get_template_directory();
        }
        $dir = new \DirectoryIterator($dir ?: $config_files);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot() && substr($fileinfo->getFilename(), -5) === '.json') {
                $config_file = json_decode(file_get_contents($fileinfo->getRealPath()), true);

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
     * @return null
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
     * @return null
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
                Shortcode::create_Shortcode($config['tag'], $config['callback']['ns'], $config['callback']['method'], $buttons);
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function processSidebars($config_file)
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                Sidebar::create_Sidebar($config['config']);
                if (!empty($config['defaults'])) {
                    Sidebar::set_Sidebar_Defaults($config['config']['id'], $config['defaults']);
                }
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function process_Menus($config_file)
    {
        foreach ($config_file as $config) {
            if (!empty($config)) {
                Menu::create_Menu($config);
            }
        }
    }

    /**
     * @param $config_file
     */
    public static function processWidgets($config_file)
    {
        //Log::WriteLog('Inside Widget installer');
        foreach ($config_file as $config) {
            if (!empty($config)) {
                Widget::create_Widget($config['callback']);
            }
        }
    }

    /**
     * @param $config_file
     *
     * @return null
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
                    if (empty($field['post_types'])) {
                        $post_ty = 'post';
                    } else {
                        $post_ty = str_replace(" ", "_", $pt);
                    }
                    Field::Add_Custom_Field_To_Post(
                        $post_ty,
                        $field['id'],
                        $field['label'],
                        $field['type'],
                        $field['options'],
                        $field
                    );
                    if (!empty($field['admin_column_header'])) {
                        Field::Add_Field_To_Post_Admin_Columns($field, $pt);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param $config_file
     *
     * @return null
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
                    Post::create_Post($post_type, $post_title, $post_content, $post_meta);
                }, 10000);

            }
        }
    }
}
