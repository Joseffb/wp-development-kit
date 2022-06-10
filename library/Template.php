<?php

namespace WDK;

use Timber\Helper;
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
        $template = ['index' . $ext, 'base'.$ext];
        foreach (self::$templates as $tag => $template_getter) {
            if ($tag()) {
                $base = $template_getter;
                array_unshift($template,$base.$ext);
            }
        }

        foreach($template as $base) {
            $base = str_replace('.twig', "", $base);
            switch ($base) {
                case 'single':
                    $template = self::handle_single($post,$template,$base);
                    break;
                case 'page':
                    $template = self::handle_page($post,$template,$base);
                    break;
                case 'taxonomy':
                    $template = self::handle_taxonomy($template,$base);
                    break;
                case 'tag':
                    $template = self::handle_tag($template,$base);
                    break;
                case 'category':
                    $template = self::handle_category($template,$base);
                    break;
            }
        }
        return array_unique($template);
    }

    /**
     * Twig template setup
     */
    public static function Setup($locations=[__DIR__ . '/views']): void
    {
        Timber::$locations = $locations;
        if (class_exists(Timber::class)) {
            //add global context values for Timber
            add_filter('timber/context', static function () {
                //$start = Helper::start_timer();
                $context = Timber::context();
                $templates = self::get_template();
                $context['page-template'] = $templates;
                $context['post'] = new Post();
                if(!empty($templates) && is_array($templates)) {
                    foreach (array_reverse($templates) as $name) {
                        $filter = 'context_' . str_replace(".twig", "", $name);
                        if(WP_DEBUG) {
                            Utility::Log($filter);
                        }
                        $context = apply_filters($filter, $context);
                    }
                }

                //make menus available in context
                foreach (wp_get_nav_menus() as $menu) {
                    $context['menu'][$menu->slug] = new \Timber\Menu($menu->slug);
                }

                return apply_filters('wdk_context', $context);
            });

            // Replaces the default wordpress templates. We still need an empty index.php file in theme root.
            // Todo: Add conditional for plugin mode. Currently replaces all Wordpress template routes (theme mode).
            // Todo: Add some routing options here to use WP template as default and Twig for specific pages/post
            add_filter('template_include', function () {
                $context = Timber::context();
                Timber::render($context['page-template'], $context);
            }, 99);
            //adds the sidebar function to the twig templates
            add_filter('timber/twig', function ($twig) {
                $twig->addFunction(new TwigFunction('sidebar', ['\Timber\Timber', 'get_widgets']));
                $twig->addFunction(new TwigFunction('is_paged', ['\WDK\Query', 'IsPaged']));
                $twig->addFunction(new TwigFunction('log_it', ['\WDK\Utility', 'Log']));
                $twig->addFunction(new TwigFunction('get_term_image', ['\WDK\Taxonomy', 'ProcessTermCustomImages']));
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
    private static function handle_single($post, $template, $base) {
        $base = str_replace('.twig',"",$base);
        if ( post_password_required( $post->ID ) ) :
            array_splice($template, 0, 0, [
                $base . "-password" . self::$ext,
            ]);
        else:
            $cats = wp_get_post_categories($post->ID, ['orderby' => 'term_order']);
            $parent_cat = !empty($cats[0])?get_category_parents($cats[0]):false;
            array_splice($template, 0, 0, [
                $base . "-" . $post->post_type. self::$ext,
            ]);
            if (!empty($parent_cat)) {
                $parent_name = strtolower(str_replace([" ","%20"], ["_", "-"], $parent_cat));
                $full_slug = strtolower(str_replace([" ", "-","%20"], ["_", "_", "-"], substr($parent_name, 0, -1)));
                $parent_array = explode("/", $full_slug);
                array_splice($template, 0, 0, [
                    $base . "-" . $post->post_type."-".$parent_array[0]. self::$ext,
                ]);
                array_splice($template, 0, 0, [
                    $base . "-" . $parent_array[0] . self::$ext,
                ]);
                if (!empty($parent_array[1])) { //check if there are children after the first /
                    array_splice($template, 0, 0, [
                        $base . "-" . str_replace(["/","%20"], ["--","-"], $full_slug) . self::$ext,
                    ]);
                }
            } else if (!empty($cats[0])) {
                $page_name = str_replace(['-',"%20"], ["_","-"], get_category($cats[0])->slug);
                array_splice($template, 0, 0, [
                    $base . "-" . $page_name . self::$ext,
                ]);
            }

        endif;
            return $template;
    }
    private static function handle_page($page, $template, $base)
    {
        $page_name = get_query_var('page_name')?:$page->post_name;
        $url = strtok($_SERVER['REQUEST_URI'], '?'); //no url parameters for template selection.
        $slug = str_replace(['-',"/", "%20"], ["_","--", "-"], substr($url, 1, -1));
        array_splice($template, 0, 0, [
            $base . "-" . str_replace(['-',"%20"], ["_","-"], $page_name) . self::$ext,
        ]);
        if ($template_a = get_post_meta($page->ID, 'template', true)) {
            //use template file stored in the custom field
            array_splice($template, 0, 0, [
                $base . "-" . str_replace(['-',"%20"], ["_","-"], $template_a) . self::$ext,
            ]);
        }
        if(!empty($slug) && !in_array($base . "-" . $slug .self::$ext,$template, true )) {
            array_splice($template, 0, 0, [
                $base . "-" . $slug . self::$ext,
            ]);
        }
        return $template;
    }
    private static function handle_taxonomy($template, $base)
    {
        $term_id = get_query_var('term');
        $taxonomy_name = get_query_var('taxonomy');
        //put the specific tag and term to top of array.
        array_splice($template, 0, 0, [
            $base . "-" . $taxonomy_name . "--" . str_replace(['-', "%20"], ["_","-"], $term_id) . self::$ext,
        ]);
        //put secondary tax level after the third level
        array_splice($template, 1, 0, [
            $base . "-" . $taxonomy_name . self::$ext
        ]);

        //first tax level tax, index and base is already there from the initial check.
        return $template;
    }
    private static function handle_tag($template, $base)
    {
        $tag_name = get_query_var('tag');
        //put the specific tag to top of array.
        array_splice($template, 0, 0, [
            $base . "-" . $tag_name . self::$ext
        ]);
        return $template;
    }
    private static function handle_category($template, $base)
    {
        $cat_name = $parent_name = str_replace('-', "_", get_query_var('category_name'));
        //put the specific tag to top of array.
        array_splice($template, 0, 0, [
            $base . "-" . $cat_name . self::$ext
        ]);
        foreach (get_the_category() as $v) {
            if ($v->slug !== $cat_name) {
                array_splice($template, 0, 0, [$base . "-" . $parent_name . "--" . str_replace(['-', "%20"], ["_", "-"], $v->slug) . self::$ext]);
            }
        }
        return $template;
    }
}
