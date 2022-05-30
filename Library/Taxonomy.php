<?php

namespace WDK\Library;

use Timber\Term;
use Timber\Timber;

/**
 * Class Taxonomy
 */
class Taxonomy
{

    public static $cpt_types = [];

    /**
     * Creates a custom taxonomy and setups column based filters on taxonomy admin index pages
     * @param       $tax_name
     * @param array $post_types
     * @param array $labels
     * @param array $options
     * @return void
     * @since 1.0
     */
    public static function CreateCustomTaxonomy($tax_name, $post_types = array("post"), $labels = array(), $options = array()): void
    {

        // Clean up post types
        $p = [];
        foreach ($post_types as $_posttype) {
            $p[] = strtolower(str_replace(" ", "_", $_posttype));
        }
//        Log::Write($tax_name);
        $post_types = $p;
        if (is_array($tax_name)) {
            $array = $tax_name;
            $tax_name = $array['human'];
        }
        $tax_name = ucwords($tax_name);
        $tax_machine_name = (!empty($array) && is_array($array) && !empty($array['machine'])) ?
            $array['machine'] :
            strtolower(str_replace(" ", "_", $tax_name));

        $tax_slug = (!empty($array) && is_array($array) && !empty($array['slug'])) ?
            $array['slug'] :
            strtolower(str_replace("_", "-", $tax_machine_name));
        $tax_name_singular = Inflector::singularize($tax_name);
        $tax_name_plural = Inflector::pluralize($tax_name);

        $labels = array_merge(array(
            'name' => __($tax_name, 'tax_' . $tax_name),
            'singular_name' => __($tax_name_singular, 'tax_' . $tax_name_singular),
            'search_items' => __('Search ' . $tax_name),
            'all_items' => __('All ' . $tax_name),
            'edit_item' => __('Edit ' . $tax_name_singular),
            'update_item' => __('Update ' . $tax_name_singular),
            'add_new_item' => __('Add New ' . $tax_name_singular),
            'new_item_name' => __("New $tax_name_singular"),
            'parent_item' => __('Parent ' . $tax_name_singular . ":"),
            'parent_item_colon' => __('Parent ' . $tax_name_singular . ":"),
        ), $labels);

        $options = array_merge(array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menu' => false,
            'show_in_admin_bar' => false,
            'show_admin_column' => false,
            'public' => true,
            'show_in_rest' => true, //required for Gutenberg
            'rest_base' => $tax_name_plural,
            'publicly_queryable' => true,
            'has_archive' => true,
            'query_var' => true,
            'show_in_graphql' => true, //needed for wpgraphql plugin\
            'graphql_single_name' => Inflector::singularize($tax_machine_name),
            'graphql_plural_name' => Inflector::pluralize($tax_machine_name),
            'rewrite' => array(
                'slug' => $tax_slug,
                'slug' => $tax_slug,
                'with_front' => false,
            )
        ), $options);

        if (!Utility::IsTrue($options['hierarchical'])) {
            array_shift($options);
        }

        add_action('init', function () use ($tax_name_plural, $tax_machine_name, $post_types, $options) {
            if (!taxonomy_exists($tax_machine_name)) {
                register_taxonomy($tax_machine_name, $post_types, $options);
            } else {
                foreach ($post_types as $pt) {
                    register_taxonomy_for_object_type($tax_machine_name, $pt);
                }
            }

            if (!empty($options['show_as_admin_filter']) && !!$options['show_as_admin_filter']) {
                foreach ($post_types as $type) {
                    if (empty(self::$cpt_types[$tax_name_plural . "-Menu"])) {
                        add_action('restrict_manage_posts', function () use ($type, $tax_name_plural, $tax_machine_name) {
                            self::AddToAdminMenu($type, $tax_name_plural, $tax_machine_name);
                        });
                        add_filter('parse_query', function ($query) use ($type, $tax_machine_name) {
                            self::QueryAdminMenuFilters($query, $type, $tax_machine_name);
                        });
                        self::$cpt_types[$tax_name_plural . "-Menu"] = true;
                    }
                }
            }

            if (!empty($options['meta_box_order'])) {
                $context = !empty($options['meta_box_context']) ? $options['meta_box_context'] : 'side';
                $div_name = !empty($options['hierarchical']) ? $tax_machine_name . 'div' : 'tagsdiv-' . $tax_machine_name;
                $div_name = $div_name === 'post_tagdiv' ? 'tagsdiv-post_tag' : $div_name;
                $desired_order = $options['meta_box_order'];
                foreach ($post_types as $pt) {
                    add_filter("get_user_option_meta-box-order_$pt", function ($order) use ($context, $div_name, $desired_order) {
                        //Log::Write($order);
                        $meta_order = explode(",", $order[$context]);
                        if (($key = array_search($div_name, $meta_order)) !== false) {
                            unset($meta_order[$key]);
                        }
                        array_splice($meta_order, $desired_order - 1, 0, [$div_name]);
                        $order[$context] = implode(",", $meta_order);
                        return $order;
                    });
                }
            }
        }, 9998);


    }

    /**
     *
     * @param $post
     * @param $box
     * @return void
     * @since 1.0
     */
    public
    static function DropDownCategoryMetaBoxCallback($post, $box): void
    {

        $defaults = array('taxonomy' => 'category');
        if (!isset($box['args']) || !is_array($box['args'])) {
            $args = array();
        } else {
            $args = $box['args'];
        }

        extract(wp_parse_args($args, $defaults), EXTR_SKIP);

        /** @var string $taxonomy */
        // from wp_parse_args
        $tax = get_taxonomy($taxonomy);
        ?>

        <div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
            <?php
            $name = ($taxonomy === 'category') ? 'post_category' : 'tax_input[' . $taxonomy . ']';
            echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.

            $term_obj = wp_get_object_terms($post->ID, $taxonomy); //_log($term_obj[0]->term_id)
            //            Log::Write($term_obj);
            if (empty($term_obj)) {
                $term_obj = get_terms(array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                ));
            }

            if (!empty($term_obj)) {
                wp_dropdown_categories(array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'name' => "{$name}[]",
                    'selected' => $term_obj[0]->term_id,
                    'orderby' => 'name',
                    'hierarchical' => 0,
                    'show_option_none' => '&mdash;',
                    'class' => 'widefat'
                ));
            }
            ?>
        </div>
        <?php
    }

    /**
     * Creates a term for a Taxonomy
     * @param string $taxonomy
     * @param array|string $term
     * @param bool|string $parent_term
     * @return bool|true|void
     * @since 1.0
     */
    public
    static function CreateTerm(string $taxonomy, $term, $parent_term = false)
    {
        $taxonomy = strtolower(str_replace(" ", "_", $taxonomy));

        if (is_array($term)) {
            //Log::Write($config, 'C:');
            foreach ($term as $k => $v) {
                //Log::Write($k, 'K:');
                //Log::Write($v, 'V:');
                switch ($v) {
                    case is_array($v):
                        //Log::Write("Mode: Array\nK:  $k \nV: $v\n");
                        if (is_int($k)) {
                            self::CreateTerm($taxonomy, $v, $parent_term);
                        } else {
                            self::CreateTerm($taxonomy, $k, $parent_term);
                            self::CreateTerm($taxonomy, $v, $k);
                        }
                        break;
                    case is_string($v);
                        //Log::Write("Mode: String\nK:  $k \nV: $v\n");
                        self::CreateTerm($taxonomy, $v, $parent_term);
                        break;
                }
            }
            return true;
        }

        $_term = strtolower($taxonomy . "_taxonomy_defaults_installed_" . str_replace(" ", "_", $term));
        $term_check = (bool)get_option($_term);

        if (empty($term_check)) {
            return add_action('init', function () use ($term, $taxonomy, $parent_term, $_term) {
                if (empty(term_exists($term, $taxonomy))) { //double checks if the term already exists.
                    $parent = !empty(term_exists($parent_term, $taxonomy)) ? [term_exists($parent_term, $taxonomy)['term_id']] : [0];
                    $res = wp_insert_term(
                        $term,
                        $taxonomy,
                        $parent
                    );
                }
                update_option($_term, true);

            }, 9999);
        }
        return false;
    }

    /**
     * Adds an taxonomy to admin index column
     * @param $type
     * @param $tax_name
     * @param $tax_machine_name
     * @return void
     * @since 1.0
     */
    public
    static function AddToAdminMenu($type, $tax_name, $tax_machine_name): void
    {
//            Log::Write($tax_machine_name);
        if (isset($_GET['post_type'])) {
            $pt = $_GET['post_type'];
        } else {
            $pt = 'post';
        }

//only add filter to post type you want
        if ($type === $pt) {
            //change this to the list of values you want to show
            //in 'label' => 'value' format
            $terms = get_terms(array(
                'taxonomy' => $tax_machine_name,
                'hide_empty' => true,
            ));
            //error_log(print_r($terms, true));
            $values = [];
            foreach ($terms as $t) {
                $values[$t->name] = $t->slug;
            }
            $hookName = strtoupper($tax_machine_name) . "_FIELD_VALUE";
            ?>
            <select name="<?= $hookName ?>">
                <option value=""
                        selected="selected"><?php _e('All ' . ucwords($tax_name), 'dp_admin'); ?></option>
                <?php
                $current_v = isset($_GET[$hookName]) ? $_GET[$hookName] : '';
                foreach ($values as $label => $value) {
                    printf
                    (
                        '<option value="%s" %s>%s</option>',
                        $value,
                        $value == $current_v ? ' selected="selected"' : '',
                        $label
                    );
                }
                ?>
            </select>
            <?php
        }
    }

    /**
     * Processes taxonomy filter on admin index pages.
     * @param $query
     * @param $type
     * @param $tax_machine_name
     * @since 1.0
     */
    public
    static function QueryAdminMenuFilters($query, $type, $tax_machine_name): void
    {
        global $pagenow;
        if (isset($_GET['post_type'])) {
            $pt = $_GET['post_type'];
        } else {
            $pt = 'post';
        }
        $hookName = strtoupper($tax_machine_name) . "_FIELD_VALUE";
        if ($_GET[$hookName] !== '' && isset($_GET[$hookName]) && $pagenow === 'edit.php' && $type === $pt && is_admin()) {
            $query->query_vars['tax_query'] = array(
                array(
                    'taxonomy' => $tax_machine_name,
                    'field' => 'slug',
                    'terms' => $_GET[$hookName]
                )
            );
        }
    }

    /**
     * Save the form field
     *
     * @param $term_id
     * @returns void
     *
     * @since 1.0.5
     */
    public
    static function SaveTermImage($term_id): void
    {
        global $wpdb;
        $post_field_name = 'taxonomy-image-id';
        $field_value = $wpdb->_escape($_POST[$post_field_name]);

        if (isset($field_value) && is_int((int)$field_value)) {
            add_term_meta($term_id, $post_field_name, absint($field_value), true);
        }
    }

    /**
     * Add a form field in the new category page
     *
     * @since 1.0.0
     */

    public
    static function AddTermImage($tax_name = 'category'): void
    {
        $post_field_name = 'taxonomy-image-id';
        ?>
        <div class="form-field term-group">
            <label for="<?= $post_field_name ?>>"><?php _e('Image', 'wdk'); ?></label>
            <input type="hidden" id="<?= $post_field_name ?>" name="<?= $post_field_name ?>"
                   class="custom_media_url"
                   value="">
            <div id="category-image-wrapper"></div>
            <p>
                <input type="button" class="button button-secondary tax_media_button"
                       id="tax_media_button"
                       name="tax_media_button" value="<?php _e('Add Image', 'wdk'); ?>"/>
                <input type="button" class="button button-secondary tax_media_remove"
                       id="tax_media_remove"
                       name="tax_media_remove" value="<?php _e('Remove Image', 'wdk'); ?>"/>
            </p>
        </div>
        <?php
        Utility::AddMediaManagerInlineScript();
    }


    /**
     * Edit the form field
     *
     * @param $term
     * @returns void
     * @since 1.0.5
     */
    public
    static function UpdateTermImageForm($term): void
    {

        $post_field_name = 'taxonomy-image-id';
        ?>
        <tr class="form-field term-group-wrap">
            <th scope="row">
                <label for="<?= $post_field_name ?>"><?php _e('Image', 'wdk'); ?></label>
            </th>
            <td>
                <?php $image_id = get_term_meta($term->term_id, $post_field_name, true); ?>
                <input type="hidden" id="<?= $post_field_name ?>" name="<?= $post_field_name ?>"
                       value="<?php echo esc_attr($image_id); ?>">
                <div id="category-image-wrapper">
                    <?php if ($image_id) { ?>
                        <?php echo wp_get_attachment_image($image_id, 'thumbnail'); ?>
                    <?php } ?>
                </div>
                <p>
                    <input type="button" class="button button-secondary tax_media_button"
                           id="tax_media_button" name="tax_media_button"
                           value="<?php _e('Add Image', 'wdk'); ?>"/>
                    <input type="button" class="button button-secondary tax_media_remove"
                           id="tax_media_remove" name="tax_media_remove"
                           value="<?php _e('Remove Image', 'wdk'); ?>"/>
                </p>
            </td>
        </tr>
        <?php
        Utility::AddMediaManagerInlineScript();
    }

    /**
     * Update the form field value
     *
     * @param $term_id
     * @returns void
     * @since 1.0.5
     */
    public
    static function ProcessTermImageUpdate($term_id): void
    {
        global $wpdb;
        $term = get_term_by("term_id", $term_id);
        $post_field_name = 'taxonomy-image-id';
        $field_value = $wpdb->_escape($_POST[$post_field_name]);
        if (isset($field_value) && is_int((int)$field_value)) {
            update_term_meta($term_id, $post_field_name, absint($field_value));
        } else {
            update_term_meta($term_id, $post_field_name, '');
        }
    }

    /**
     * @param $terms
     * @return mixed
     */
    public
    static function ProcessTermCustomImages($terms): array
    {
        $children = get_term_children($terms->term_id, $terms->taxonomy);
        $children = $children ?: [$terms->term_id];
        foreach ($children as $id) {
            $term = get_term($id);
            if (!empty($term)) {
                $image_id = get_term_meta($term->term_id, 'taxonomy-image-id', true);
                if (!empty($image_id)) {
                    $d = ['name' => null, 'description' => null, 'link' => null];
                    $link = wp_get_attachment_image_src($image_id)[0];
                    $d['name'] = $term->name;
                    $d['description'] = $term->description;
                    $d['link'] = $link ?: null;
                    return $d;
                }
            }
        }
        return [];
    }

}