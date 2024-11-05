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
    public static function get_config_value(string $key, ?int $post_id = null, string $type = 'all'): mixed
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
    public static function get_template(): mixed
    {
        global $post;

        if (preg_match('~(\.css|\.js|\.map|\.pdf|\.png|\.jpg|\.gif|.ttf|\.doc|\.xls|\.ico|\.woff2|\.woff|\.svg|admin-ajax.php)~', $_SERVER['REQUEST_URI'])) {
            return false;
        }

        Utility::Log($_SERVER['REQUEST_URI']);
        $ext = '.twig';
        $template = ['index.twig'];

        foreach (self::$templates as $tag => $template_getter) {
            if (call_user_func($tag)) {
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
    public static function Setup($locations = [__DIR__ . '/views']): void
    {
        Timber::$locations = $locations;
        if (class_exists(Timber::class)) {
            add_filter('timber/context', function () {
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
            add_filter('timber/twig', function ($twig) {
                $twig->addFunction(new TwigFunction('get_sidebar', ['\Timber\Timber', 'get_widgets']));
                $twig->addFunction(new TwigFunction('paging', ['\WDK\Query', 'IsPaged']));
                $twig->addFunction(new TwigFunction('log_it', ['\WDK\Utility', 'Log']));
                $twig->addFunction(new TwigFunction('get_taxonomy_term_image', ['\WDK\Taxonomy', 'ProcessTermCustomImages']));
                $twig->addFunction(new TwigFunction('get_wp_header', ['\WDK\Template', 'GetWPHeader']));
                $twig->addFunction(new TwigFunction('get_wp_footer', ['\WDK\Template', 'GetWPFooter']));
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

    private static function handle_single($post, $template = [], $base = 'single'): array
    {
        $template = is_array($template) ? $template : [$template];
        array_unshift($template, "{$base}-{$post->post_type}.twig", "{$base}.twig");
        return $template;
    }

    private static function handle_page($page, $template = [], $base = 'page'): array
    {
        $template = is_array($template) ? $template : [$template];
        array_unshift($template, "{$base}-{$page->post_name}.twig", "{$base}.twig");
        return $template;
    }

    private static function handle_taxonomy($template, $base)
    {
        $term_id = get_query_var('term');
        $taxonomy_name = get_query_var('taxonomy');
        $template = is_array($template) ? $template : [$template];
        array_unshift($template, "{$base}-{$taxonomy_name}--{$term_id}.twig", "{$base}-{$taxonomy_name}.twig", "{$base}.twig");
        return $template;
    }

    private static function handle_category($template, $base)
    {
        $cat_name = get_query_var('category_name');
        $template = is_array($template) ? $template : [$template];
        array_unshift($template, "{$base}-{$cat_name}.twig", "{$base}.twig");
        return $template;
    }
}
