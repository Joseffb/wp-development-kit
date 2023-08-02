<?php

namespace WDK;

use Timber\Post;
use Timber\Timber;
use Twig\TwigFunction;

class Template
{
    private static $ext = ".twig";
    private static $templates = array(
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
    );

    /**
     * Returns the WP Header template as a variable for inclusion into context.
     * @param null $name
     * @param array $args
     * @return false|string
     */
    public static function GetWPHeader($name = null, array $args = [])
    {
        ob_start();
        get_header($name, $args);
        return ob_get_clean();
    }

    /**
     * Returns the WP Footer template as a variable for inclusion into context.
     * @param null $name
     * @param array $args
     * @return false|string
     */
    public static function GetWPFooter($name = null, array $args = [])
    {
        ob_start();
        get_footer($name, $args);
        return ob_get_clean();
    }

    /**
     * Determine template to load.
     * @param string $engine
     * @return mixed
     */
    public static function get_template()
    {
        global $post;
        if (preg_match('~(\.css|\.js|\.map|\.pdf|\.png|\.jpg/\.gif\.doc\.xls\.ico)~', $_SERVER['REQUEST_URI'])) {
            return false;
        }

        // Loop through each of the template conditionals, and find the appropriate template file base.
        $ext = '.twig';
        $template = false;

        foreach (self::$templates as $tag => $template_getter) {
            if ($tag()) {
                $base = $template_getter;
                switch ($base) {
                    case 'single':
                        Utility::Log('wdk_process_template_cpt_' . $post->post_type);
                        $post_type_check = get_option('wdk_process_template_cpt_' . $post->post_type); //run a twig template for the CPT archive page.
                        Utility::Log($post_type_check);
                        if (!empty($post_type_check)) {
                            if (!Utility::IsTrue($post_type_check)) {
                                $template = $post_type_check;
                            }
                            $template = self::handle_single($post, $template, $base);
                        } else {
                            return false;
                        }
                        break;
                    case 'page':
                        //check to run a Twig template on a specific page. set in functions file via update_post_meta($post_id, "process_template_page_PAGESLUG", true)
                        //Utility::Log($post);
                        $post_type_check = get_option('wdk_process_template_page_' . $post->post_name); //run a twig template for the CPT archive page.
                        //Utility::Log($post_type_check);
                        $page_specific_check = get_post_meta($post->ID, 'wdk_process_template_page_' . $post->post_name, true);
                        $template_check = !empty($page_specific_check) ? $page_specific_check : $post_type_check;
                        Utility::Log($template_check);
                        if (!empty($template_check)) {
                            Utility::Log($template_check);
                            if (!is_bool($template_check)) {
                                $template = $template_check;
                            }
                            Utility::Log($template);
                            $template = self::handle_page($post, $template, $base);
                        } else {
                            return false;
                        }
                        break;
                    case 'taxonomy':
                        $term_name = get_query_var('term');
                        $taxonomy_name = get_query_var('taxonomy');
                        //checks if we should use a Twig template for all terms of taxonomy.
                        $taxonomy_name_check = get_option('wdk_process_template_tax_' . $taxonomy_name);
                        //checks if we should use a specific Twig template for a specific term in a specific taxonomy
                        $taxonomy_id_check = get_option('wdk_process_template_tax_' . $taxonomy_name . "_" . $term_name);
                        if (!Utility::IsTrue($taxonomy_name_check) || !Utility::IsTrue($taxonomy_id_check)) {
                            if ($taxonomy_name_check !== 'true') {
                                $template = $taxonomy_name_check;
                            }
                            if ($taxonomy_id_check !== 'true') {
                                $template = $taxonomy_id_check;
                            }
                            $template = self::handle_taxonomy($template, $base);
                        } else {
                            return false;
                        }
                        break;
                    case 'tag':
                        $tag_name = get_query_var('tag');
                        $tag_check = get_option('wdk_process_template_tag');
                        //checks if we should use a Twig template for all tags.
                        $tag_name_check = get_option('wdk_process_template_tag_term_' . $tag_name);
                        if (!Utility::IsTrue($tag_check) || !Utility::IsTrue($tag_name_check)) {
                            if ($tag_check !== 'true') {
                                $template = $tag_check;
                            }
                            if ($tag_name_check !== 'true') {
                                $template = $tag_name_check;
                            }
                            $template = self::handle_tag($template, $base);
                        } else {
                            return false;
                        }
                        break;
                    case 'category':
                        $cat_name = get_query_var('category_name');
                        //checks if we should use a Twig template for all terms of the default 'category' taxonomy.
                        $category_check = get_option('wdk_process_template_tax_category');
                        //checks if we should use a specific Twig template for a specific term in a specific the default 'category' taxonomy
                        $category_name_check = get_option('wdk_process_template_tax_category_' . $cat_name);
                        if (!Utility::IsTrue($category_check) || !Utility::IsTrue($category_name_check)) {
                            if ($category_check !== 'true') {
                                $template = $category_check;
                            }
                            if ($category_name_check !== 'true') {
                                $template = $category_name_check;
                            }
                            $template = self::handle_category($template, $base);
                        } else {
                            return false;
                        }
                        break;
                    default:
                        $default = get_option('wdk_process_template_' . $base);
                        if (Utility::IsTrue($default)) {
                            $template = ['index' . $ext, 'base' . $ext];
                        } else {
                            $template = strtolower(str_replace([' '], ["_"], $default));
                        }
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
            //add global context values for Timber
            add_filter('timber/context', static function () {
                $show_templates = get_option('wdk_debug_show_templates');
                Utility::Log('inside context hook');
                //$start = Helper::start_timer();
                $context = Timber::context();
                $context['page-template'] = $templates = self::get_template();
                if ($templates && (WP_DEBUG || $show_templates)) {
                    Utility::Log($templates, 'Debug Only Message::Twig Template Hooks');
                }
                if (!empty($templates)) {
                    if(!is_array($templates)) {
                        $templates= [$templates];
                    }
                    $context['post'] = new Post();
                    if (WP_DEBUG || $show_templates) {
                        $context_hooks = [];
                    }
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

                //make menus available in context
                foreach (wp_get_nav_menus() as $menu) {
                    $context['menu'][$menu->slug] = new \Timber\Menu($menu->slug);
                }

                return apply_filters('wdk_context', $context);
            });

            // Conditionally replaces the default WordPress templates.
            // Note: You still need an empty index.php file in theme root due to hardcoded rule in WordPress
            add_filter('template_include', function ($template) {
                $context = Timber::context();
                if ($context['page-template']) {
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

    //Note: these handle_X methods work as LIFO stacks for Twig render

    /**
     * @param $post
     * @param mixed $template
     * @param $base
     * @return array|string[]
     */
    private static function handle_single($post, $template = [], $base = 'single'): array
    {
        if (empty($template) || !is_array($template)) {
            $submit_template = $template;
            $template = ["index.twig", $base . self::$ext];
            if (!is_int((int)$submit_template)) {
                $submit_template = str_replace(self::$ext, "", $submit_template);
                array_unshift($template, $submit_template . self::$ext);
            }
        }
        if (post_password_required($post->ID)) :
            array_splice($template, 0, 0, [
                $base . "-password" . self::$ext,
            ]);
        else:
            $cats = wp_get_post_categories($post->ID, ['orderby' => 'term_order']);
            $parent_cat = !empty($cats[0]) ? get_category_parents($cats[0]) : false;
            Utility::Log($template);
            array_splice($template, 0, 0, [
                $base . self::$ext,
            ]);
            array_splice($template, 0, 0, [
                $base . "-" . $post->post_type . self::$ext,
            ]);
            if (!empty($parent_cat)) {
                $parent_name = strtolower(str_replace([" ", "%20"], ["_", "-"], $parent_cat));
                $full_slug = strtolower(str_replace([" ", "-", "%20"], ["_", "_", "-"], substr($parent_name, 0, -1)));
                $parent_array = explode("/", $full_slug);
                array_splice($template, 0, 0, [
                    $base . "-" . $post->post_type . "-" . $parent_array[0] . self::$ext,
                ]);
                array_splice($template, 0, 0, [
                    $base . "-" . $parent_array[0] . self::$ext,
                ]);
                if (!empty($parent_array[1])) { //check if there are children after the first /
                    array_splice($template, 0, 0, [
                        $base . "-" . str_replace(["/", "%20"], ["--", "-"], $full_slug) . self::$ext,
                    ]);
                }
            } else if (!empty($cats[0])) {
                $page_name = str_replace(['-', "%20"], ["_", "-"], get_category($cats[0])->slug);
                array_splice($template, 0, 0, [
                    $base . "-" . $page_name . self::$ext,
                ]);
            }

        endif;

        return $template;
    }

    /**
     * @param $page
     * @param mixed $template
     * @param $base
     * @return array|string[]
     */
    private static function handle_page($page, $template = [], $base = 'page'): array
    {
        $override_template = false;
        if (empty($template) || !is_array($template)) {
            $override_template = $template;
            $template = ["index.twig", "base.twig"];
        }

        $page_name = get_query_var('page_name') ?: $page->post_name;
        $url = strtok($_SERVER['REQUEST_URI'], '?'); //no url parameters for template selection.
        $slug = str_replace(['-', "/", "%20"], ["_", "--", "-"], substr($url, 1, -1));
        array_splice($template, 0, 0, [
            $base . self::$ext, // page.twig
        ]);
        array_splice($template, 0, 0, [
            $base . "-" . str_replace(['-', "%20"], ["_", "-"], $page_name) . self::$ext,
        ]);
        if ($template_a = get_post_meta($page->ID, 'template', true)) {
            //use template file stored in the custom field
            array_splice($template, 0, 0, [
                $base . "-" . str_replace(['-', "%20"], ["_", "-"], $template_a) . self::$ext,
            ]);
        }
        if (!empty($slug) && !in_array($base . "-" . $slug . self::$ext, $template, true)) {
            array_splice($template, 0, 0, [
                $base . "-" . $slug . self::$ext,
            ]);
        }
        if (!empty($override_template) && !is_int($override_template)) {
            $override_template = str_replace(self::$ext, "", $override_template);
            array_unshift($template, $override_template . self::$ext);
        }


        return $template;
    }

    private static function handle_taxonomy($template, $base)
    {
        $term_id = get_query_var('term');
        $taxonomy_name = get_query_var('taxonomy');
        //put the specific tag and term to top of array.
        array_splice($template, 0, 0, [
            $base . self::$ext,
        ]);
        array_splice($template, 0, 0, [
            $base . "-" . $taxonomy_name . self::$ext,
        ]);
        array_splice($template, 0, 0, [
            $base . "-" . $taxonomy_name . "--" . str_replace(['-', "%20"], ["_", "-"], $term_id) . self::$ext,
        ]);

        //first tax level tax, index and base is already there from the initial check.
        return $template;
    }

    private static function handle_tag($template, $base)
    {
        $tag_name = get_query_var('tag');
        array_splice($template, 0, 0, [
            $base . self::$ext
        ]);
        array_splice($template, 0, 0, [
            $base . "-" . $tag_name . self::$ext
        ]);

        return $template;
    }

    private static function handle_category($template, $base)
    {
        $cat_name = $parent_name = str_replace('-', "_", get_query_var('category_name'));
        array_splice($template, 0, 0, [
            $base . self::$ext
        ]);
        foreach (get_the_category() as $v) {
            if ($v->slug !== $cat_name) {
                array_splice($template, 0, 0, [$base . "-" . $parent_name . "--" . str_replace(['-', "%20"], ["_", "-"], $v->slug) . self::$ext]);
            }
        }
        array_splice($template, 0, 0, [
            $base . "-" . $cat_name . self::$ext
        ]);
        return $template;
    }
}
