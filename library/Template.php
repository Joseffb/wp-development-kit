<?php

namespace WDK;

use Timber\Post;
use Timber\Timber;
use Twig\TwigFunction;

class Template
{
    private static $ext = ".twig";
    private static $templates = [
        'is_embed' => 'embed',
        'is_attachment' => 'attachment',
        'is_404' => '404',
        'is_search' => 'search',
        'is_home' => 'home',
        'is_front_page' => 'front-page',
        'is_page' => 'page',
        'is_privacy_policy' => 'privacy',
        'is_single' => 'single',
        'is_archive' => 'archive',
        'is_tax' => 'taxonomy',
        'is_category' => 'category',
        'is_tag' => 'tag',
        'is_author' => 'author',
        'is_date' => 'date',
        'is_post_type_archive' => 'archive',
    ];

    /**
     * Returns the WP Header template as a variable for inclusion into context.
     */
    public static function GetWPHeader($name = null, array $args = [])
    {
        ob_start();
        get_header($name, $args);
        return ob_get_clean();
    }

    /**
     * Returns the WP Footer template as a variable for inclusion into context.
     */
    public static function GetWPFooter($name = null, array $args = [])
    {
        ob_start();
        get_footer($name, $args);
        return ob_get_clean();
    }

    /**
     * Retrieve a configuration value with a fallback sequence: constant, post meta, and then option.
     */
    public static function get_config_value(string $key, $post_id = null, string $type = 'all'): mixed
    {
        $globalUseTwig = 'wdk_process_template_global';
        $use_twig_for_all_templates = get_option($globalUseTwig);

        if (!empty($use_twig_for_all_templates) && Utility::IsTrue($use_twig_for_all_templates)) {
            // Option is set to true, so return true to use Twig for all templates
            return true;
        }

        // Convert to uppercase for the constant check
        $globalUseTwig = strtoupper($globalUseTwig);

        // Now check if the constant is defined and set to true
        if (defined($globalUseTwig) && constant($globalUseTwig)) {
            return true;
        }

        $const_name = strtoupper($key);

        if (($type === 'all' || $type === 'constant') && defined($const_name)) {
            return constant($const_name);
        }

        if (($type === 'all' || $type === 'meta') && $post_id) {
            $meta_value = get_post_meta($post_id, $key, true);
            if (!empty($meta_value)) {
                return $meta_value;
            }
        }

        if ($type === 'all' || $type === 'option') {
            $option_value = get_option($key);
            if (!empty($option_value)) {
                return $option_value;
            }
        }

        return false;
    }

    /**
     * Determine the template to load.
     */
    public static function get_template($predefined_template = null): mixed
    {
        global $post;

        if (preg_match('~(\.css|\.js|\.map|\.pdf|\.png|\.jpg|\.gif|.ttf|\.doc|\.xls|\.ico|\.woff2|\.woff|\.svg|admin-ajax.php)~', $_SERVER['REQUEST_URI'])) {
            return false;
        }

        Utility::Log($_SERVER['REQUEST_URI']);
        $ext = '.twig';

        $template = $predefined_template?[$predefined_template, 'index.twig']:['index.twig'];

        foreach (self::$templates as $tag => $template_getter) {
            if ($tag()) {
                $base = $template_getter;
                Utility::Log("Checking template for {$base}");

                $template_check_key = "wdk_process_template_{$base}";
                $template_check = self::get_config_value($template_check_key, $post->ID);

                if (!Utility::IsTrue($template_check)) {
                    if ($template_check !== false) {
                        $template = $template_check;
                    } else {
                        return false;
                    }
                }

                // Determine specific templates based on content type
                switch ($base) {
                    case 'home':
                        $post_type_key = "wdk_process_template_cpt_home";
                        $post_type_template = self::get_config_value($post_type_key, $post->ID);
                        $template = ["index.twig", $template, "{$base}.twig"];
                        if (!empty($post_type_template)) {
                            $template = is_bool($post_type_template) ? self::handle_single($post, $template, $base) : $post_type_template;
                        } else {
                            return false;
                        }
                        break;
                    case 'single':
                        $post_type_key = "wdk_process_template_cpt_{$post->post_type}";
                        $post_type_template = self::get_config_value($post_type_key, $post->ID);
                        if (!empty($post_type_template)) {
                            $template = is_bool($post_type_template) ? self::handle_single($post, $template, $base) : $post_type_template;
                        } else {
                            return false;
                        }
                        break;

                    case 'page':
                        $page_template_key = "wdk_process_template_page_{$post->post_name}";
                        $page_template = self::get_config_value($page_template_key, $post->ID);
                        $template = $page_template ?: $template;
                        $template = self::handle_page($post, $template, $base);
                        break;

                    case 'taxonomy':
                        $taxonomy_key = "wdk_process_template_tax_" . get_query_var('taxonomy');
                        $term_key = $taxonomy_key . "_" . get_query_var('term');
                        $taxonomy_template = self::get_config_value($taxonomy_key);
                        $term_template = self::get_config_value($term_key);

                        $template = $term_template ?: $taxonomy_template ?: $template;
                        if ($template) {
                            $template = self::handle_taxonomy($template, $base);
                        }
                        break;

                    case 'category':
                        $category_key = "wdk_process_template_tax_category_" . get_query_var('category_name');
                        $category_template = self::get_config_value($category_key);

                        $template = $category_template ?: $template;
                        $template = self::handle_category($template, $base);
                        break;

                    default:
                        $default_template = self::get_config_value("wdk_process_template_{$base}");
                        $template = Utility::IsTrue($default_template) ? ["index{$ext}", "base{$ext}"] : $default_template;
                }

                if ($template) {
                    break;
                }
            }
        }

        return $template;
    }

    /**
     * Twig template setup
     */
    public static function Setup($locations = [__DIR__ . '/views'])
    {
        Timber::$locations = $locations;
        if (class_exists(Timber::class)) {
            add_filter('timber/context', static function () {
                $show_templates = self::get_config_value('wdk_debug_show_templates');
                Utility::Log('inside context hook');
                $context = Timber::context();
                $context['page-template'] = $templates = self::get_template();
                if ($templates && (WP_DEBUG || $show_templates)) {
                    Utility::Log($templates, 'Debug Only Message::Twig Template Hooks');
                }
                $context['post'] = new Post();
                if (WP_DEBUG || $show_templates) {
                    $context_hooks = [];
                }

                if (!empty($templates) && is_array($templates)) {
                    $context = apply_filters('wdk_context', $context);
                    foreach (array_reverse($templates) as $name) {
                        $filter = 'wdk_context_' . str_replace(".twig", "", $name);
                        if (WP_DEBUG || $show_templates) {
                            $context_hooks[] = $filter;
                        }
                        $context = apply_filters($filter, $context);
                    }
                    if (WP_DEBUG || $show_templates) {
                        Utility::Log($context_hooks, 'Debug Only Message::Twig Template Context Hooks');
                    }
                }
                return $context;
            });

            add_filter('template_include', function ($template) {
                $context = Timber::context();
                if ($context['page-template']) {
                    Utility::Log($context['page-template']);
                    Timber::render($context['page-template'], $context);
                } else {
                    return $template;
                }
            }, 99);
            //adds the sidebar function to the twig templates
            add_filter('timber/twig', static function ($twig) {
                $ref = new \ReflectionObject($twig);
                $prop = $ref->getProperty('extensionSet');
                $prop->setAccessible(true);
                $extensionSet = $prop->getValue($twig);

// 👉 Only register if Twig isn’t initialized yet
                if (! $extensionSet->isInitialized()) {
                    if (!array_key_exists('get_sidebar', $twig->getFunctions())) $twig->addFunction(new TwigFunction('get_sidebar', ['\Timber\Timber', 'get_widgets']));
                    if (!array_key_exists('paging', $twig->getFunctions())) $twig->addFunction(new TwigFunction('paging', ['\WDK\Query', 'IsPaged']));
                    if (!array_key_exists('log_it', $twig->getFunctions())) $twig->addFunction(new TwigFunction('log_it', ['\WDK\Utility', 'Log']));
                    if (!array_key_exists('get_taxonomy_term_image', $twig->getFunctions())) $twig->addFunction(new TwigFunction('get_taxonomy_term_image', ['\WDK\Taxonomy', 'ProcessTermCustomImages']));
                    if (!array_key_exists('get_wp_header', $twig->getFunctions())) $twig->addFunction(new TwigFunction('get_wp_header', ['\WDK\Template', 'GetWPHeader']));
                    if (!array_key_exists('get_wp_footer', $twig->getFunctions())) $twig->addFunction(new TwigFunction('get_wp_footer', ['\WDK\Template', 'GetWPFooter']));
                }
                return $twig;
            });
        } else {
            add_action('admin_notices', function () {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e('Error! You need to run composer install to use Timber templates.', 'wdk'); ?></p>
                </div>
                <?php
            });
        }
    }

    private static function handle_single($post, mixed $template, $base = 'single'): array
    {
        // Ensure the template array has fallback values
        if (empty($template)) {
            $template = ["index.twig", "{$base}.twig"];
        } else if(!is_array($template)) {
            $template = str_contains($template, '.twig')?$template:$template.".twig";
            $template = ["index.twig", $template, "{$base}.twig"];
        }

        // Add post type-specific template
        array_unshift($template, "{$base}-{$post->post_type}.twig");

        // Check for categories associated with the post
        $categories = wp_get_post_categories($post->ID, ['orderby' => 'term_order']);
        if (!empty($categories)) {
            $parent_cat = get_category($categories[0]);

            // Process parent categories to add templates based on hierarchy
            if ($parent_cat) {
                $parent_slug = strtolower(str_replace([' ', '%20'], ['_', '-'], get_category_parents($parent_cat, false, '/', true)));
                $slug_parts = explode('/', trim($parent_slug, '/'));

                // Add hierarchical templates for parent-child categories
                if (count($slug_parts) > 1) {
                    $hierarchical_slug = implode('--', $slug_parts);
                    array_unshift($template, "{$base}-{$hierarchical_slug}.twig");
                }

                // Add top-level category template
                array_unshift($template, "{$base}-{$slug_parts[0]}.twig");
            }
        }
        // Insert password-protected template if required
        if (post_password_required($post->ID)) {
            array_unshift($template, "{$base}-password.twig");
            array_unshift($template, "{$base}-{$post->post_type}-password.twig");
        }
        return $template;
    }


    private static function handle_page($page, $template = [], $base = 'page'): array
    {
        $override_template = false;
        if (empty($template) || !is_array($template)) {
            $override_template = $template;
            $template = ["index.twig", "base.twig"];
        }

        // Get the page name from query vars or post object
        $page_name = get_query_var('page_name') ?: $page->post_name;

        // Extract slug from the current URL without query parameters
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        $slug = str_replace(['-', "/", "%20"], ["_", "--", "-"], trim($url, '/'));

        // Add base templates
        array_unshift($template, "{$base}.twig");

        // Add template based on page name
        array_unshift($template, "{$base}-" . str_replace(['-', "%20"], ["_", "-"], $page_name) . ".twig");

        // Check for a custom template specified in post meta
        if ($custom_template = get_post_meta($page->ID, 'template', true)) {
            array_unshift($template, "{$base}-" . str_replace(['-', "%20"], ["_", "-"], $custom_template) . ".twig");
        }

        // Add template based on URL slug if not already in the template array
        if (!empty($slug) && !in_array("{$base}-{$slug}.twig", $template, true)) {
            array_unshift($template, "{$base}-{$slug}.twig");
        }

        // Handle override template if provided
        if (!empty($override_template) && !is_int($override_template)) {
            $override_template = str_replace(".twig", "", $override_template);
            array_unshift($template, "{$override_template}.twig");
        }

        return $template;
    }


    private static function handle_taxonomy($template, $base): array
    {
        $term_id = get_query_var('term');
        $taxonomy_name = get_query_var('taxonomy');

        // Ensure $template is an array
        $template = is_array($template) ? $template : [$template];

        // Process term ID by replacing certain characters
        $processed_term_id = str_replace(['-', "%20"], ["_", "-"], $term_id);

        // Add templates to the beginning of the array
        array_unshift($template, "{$base}.twig");
        array_unshift($template, "{$base}-{$taxonomy_name}.twig");
        array_unshift($template, "{$base}-{$taxonomy_name}--{$processed_term_id}.twig");

        return $template;
    }


    private static function handle_category($template, $base): array
    {
        // Replace hyphens with underscores in category name
        $cat_name = $parent_name = str_replace('-', "_", get_query_var('category_name'));

        // Ensure $template is an array
        $template = is_array($template) ? $template : [$template];

        // Add base template
        array_unshift($template, "{$base}.twig");

        // Add templates for each category associated with the post
        $categories = get_the_category();
        if ($categories) {
            foreach ($categories as $category) {
                $category_slug = str_replace(['-', "%20"], ["_", "-"], $category->slug);
                if ($category_slug !== $cat_name) {
                    array_unshift($template, "{$base}-{$parent_name}--{$category_slug}.twig");
                }
            }
        }

        // Add template based on current category name
        array_unshift($template, "{$base}-{$cat_name}.twig");

        return $template;
    }

}
