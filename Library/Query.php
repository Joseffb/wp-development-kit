<?php

namespace WDK\Library;

use Timber\PostQuery;
use WP_Query;

/**
 * Class Query
 */
class Query
{
    /**
     * @return int
     */
    public static function IsPaged(): int
    {
        if (get_query_var('paged')) {
            $paged = get_query_var('paged');
        } elseif (get_query_var('page')) {
            $paged = get_query_var('page');
        } else {
            $paged = 1;
        }
        return $paged;
    }

    /**
     * Easy querying because you shouldn't need to refer to WP reference for this
     *
     * @param array|null $taxonomies
     * @param array|null $fields
     * @param array $args
     * @param bool $debug
     * @return bool|WP_Query
     */
    public static function GetPost(array $taxonomies = null, array $fields = null, array $args = [], bool $debug = false): WP_Query|bool
    {
        wp_reset_query();
        $paged = self::IsPaged();
        if ($debug) {
            Utility::Log('tracing debug...', "debug", true, 100);
            Utility::Log($args);
        }
        wp_reset_query();
        $args['post_type'] = !empty($args['post_type']) ? $args['post_type'] : 'any';
        $args['post_status'] = !empty($args['post_status']) ? $args['post_status'] : ['publish'];
        $args['posts_per_page'] = !empty($args['posts_per_page']) ? $args['posts_per_page'] : -1;
        $args['orderby'] = !empty($args['orderby']) ? $args['orderby'] : "menu_order";
        $args['order'] = !empty($args['order']) ? $args['order'] : "DESC";
        $args['paged'] = $paged;
        $args['page'] = $paged;

        if ($debug) {
            Utility::Log($taxonomies, "Taxonomy Array:");
        }
        if (!empty($taxonomies)) {
            $args['tax_query'] = self::GetTaxQueriesForQuery($taxonomies);
        }

        /**
         * Fields Example
         * array(
         *      array(
         *          'key'   => 'ys_product_status',
         *          'value' => 'ok'
         *      ),
         *      array (
         *          'key'   => 'ys_product_start',
         *          'value' => date('Ymd'),
         *          'compare' => '>='
         *      ),
         *      array (
         *          'key'   => 'ys_product_end',
         *          'value' => date('Ymd'),
         *          'compare' => '<='
         *      )
         * )
         */
        if ($debug) {
            Utility::Log($fields, "Fields Array:");
        }
        if (!empty($fields) && !empty($fields[0])) {
            //Log::Write($fields);
            $args['meta_query'] = self::GetMetaQueriesForQuery($fields);
        }

        if ($debug) {
            Utility::Log($args, "Args Array:");
        }

        if (!empty($args['related']) && is_array($args['related']['post_type'])) {
            add_filter('posts_results', ['wdk\Library\Query', "shadow_taxonomy_posts"], 10, 2);
        }
        return self::query_WP($args, $debug);
    }

    private static function query_WP($query, $debug = false): WP_Query
    {
        if (!empty($query['like_query'])) {
            add_filter('posts_where', ['wdk\Library\Query', "title_filter"], 10, 2);
        }

        $results = new WP_Query($query);

        if ($debug) {
            Utility::LastSQL_WP();
        }

        if (!empty($query['like_query'])) {
            remove_filter('posts_where', ['wdk\Query', "title_filter"], 10);
        }
        return $results;
    }

    /**
     * @param $where
     * @param $wp_query
     * @return mixed|string
     * @expects    'like_query' => [
     * 'relation' => 'OR',
     * 'cols' => [
     * 'post_title' => [
     * 'relation' => 'OR',
     * 'values' => [$_GET['first_name'], $_GET['last_name']]
     *              ],
     * 'post_content' => [
     * 'relation' => 'OR',
     * 'values' => [$_GET['institution']]
     * ],
     * ]
     * ],
     * ]
     */
    public static function title_filter($where, $wp_query): mixed
    {
        global $wpdb;
        $search_terms = $wp_query->get('like_query');

        if (is_array($search_terms)) {
            $operator = !empty($search_terms['relation']) ? $search_terms['relation'] : 'OR';
            $wild = '%';
            $beginning = true;
            foreach ($search_terms['cols'] as $k => $v) {
                reset($search_terms['cols']);
                if (!empty($v) && $k === key($search_terms['cols'])) {
                    $where .= " $operator (";
                } else {
                    $beginning = false;
                }
                $relation = !empty($v['relation']) ? $v['relation'] : '';
                if (is_array($v)) {
                    $values = $v['values'];
                    foreach ($values as $key => $value) {
                        if (is_null($value) || $value === '') {
                            unset($values[$key]);
                        }
                    }
                    foreach ($values as $key => $value) {
                        reset($values);
                        if (!$beginning && $key === key($values) && !empty($value)) {
                            $where .= "$relation (";
                        } else if ($key === key($values) && !empty($value)) {
                            $where .= " (";
                        }
                        $where .= " $wpdb->posts.$k LIKE '$wild" . esc_sql($wpdb->esc_like($value)) . $wild . "'";
                        end($values);
                        if ($key !== key($values)) {
                            $where .= " $relation ";
                        }
                        if ($key === key($values)) {
                            $where .= ") ";
                        }
                    }
                }
                end($search_terms['cols']);
                if ($k != key($search_terms['cols']) && !empty($search_terms['cols']['values'])) {
                    $where .= " $operator ";
                }
                if ($k === key($search_terms['cols'])) {
                    $where .= " ) ";
                }
            }
            //$where .= " AND $where";
        }

        return $where;
    }

    public static function shadow_taxonomy_posts($posts, WP_Query $query)
    {
        $args = $query->query;
        global $template_engine;
        foreach ($posts as $post) {
            $post->related_posts = [];
            foreach ($args['related']['post_type'] as $tax) {
                $related_posts = get_the_posts($post->ID, $post->post_type . "_" . $tax . "_tax", $post->post_type);
                if (TEMPLATE_ENGINE === 'twig') {
                    // PostQuery adds in the twig specific links and
                    // this is the only central place to add it to all related post records.
                    $related_posts = new PostQuery($related_posts);
                }
                $post->related_posts[] = $related_posts;
            }
        }
        return $posts;
    }


    /**
     * Retrieve the ID of a taxonomy from its name.
     *
     * @param string $cat_name Category name.
     *
     * @return int 0, if failure and ID of category on success.
     */
    public static function get_tax_ID(string $cat_name): int
    {
        $cat = get_term_by('name', $cat_name);
        if ($cat) {
            return $cat->term_id;
        }

        return 0;
    }

    /**
     * Retrieve the ID of a taxonomy from its name.
     *
     * @param string $term_name Category name.
     *
     * @return array|bool, if failure and ID of category on success.
     */
    public static function get_tax_from_term_name(string $term_name): bool|array
    {
        return self::get_taxonomy_from_a_term('name', $term_name, 'name');
    }

    /**
     * @param $term_id
     *
     * @return array|bool
     */
    public static function get_tax_from_term_id($term_id): bool|array
    {
        return self::get_taxonomy_from_a_term('id', $term_id, 'name');
    }

    /**
     * @param string $term_field
     * @param $term_value
     * @param string $return_field
     *
     * @return array|bool
     */
    public static function get_taxonomy_from_a_term(string $term_field = "slug", $term_value = null, string $return_field = 'all'): bool|array
    {
        if (!$term_value) {
            Utility::Log('SOFT ERROR: query::get_taxonomy_from_a_term has no term_value provided');
            return false;
        }
        $taxonomies = get_taxonomies();
        $tax = [];
        foreach ($taxonomies as $tax_type_key => $taxonomy) {
            if ($term_object = get_term_by($term_field, $term_value, $taxonomy)) {
                if ($return_field === 'all') {
                    $tax[] = $term_object;
                } else {
                    $tax[] = $term_object->$return_field;
                }
            }
        }
        if (!empty($tax)) {
            return $tax;
        }

        return false;
    }

    /**
     * Create taxonomy query format from an array of taxonomies.
     *
     * @param array $tax
     *
     * @return mixed
     */
    public static function GetTaxQueriesForQuery(array $tax): mixed
    {
        /**
         * Tax Examples
         * array(
         * 'taxonomy'    => 'organize',
         * 'field'       => 'slug',
         * 'terms'       => array( 'aba-therapist' ),
         * 'operator' => "NOT IN"
         * ),
         * array(
         * 'taxonomy'    => 'location',
         * 'field'       => 'slug',
         * 'terms'       => array( $term->slug )
         * )
         */

        $args = [];
        $cnt = 0;
        foreach ($tax as $k => $v) {
            if (is_array($k)) {
                return self::GetTaxQueriesForQuery($k);
            }

            if (!empty($v['terms']) && $v['taxonomy']) {
                if ($cnt > 0) {
                    switch (true) {
                        case count($tax) >= 2 && empty($tax['relation']):
                            $args['relation'] = 'OR';
                            break;
                        case !empty($tax['relation']):
                            $args['relation'] = $tax['relation'];
                            break;
                        default:
                            $args['relation'] = 'AND';
                            break;
                    }
                }
                $args[$cnt]['taxonomy'] = $v['taxonomy'];
                $args[$cnt]['field'] = !empty($v['field']) ? $v['field'] : 'slug';
                $args[$cnt]['operator'] = !empty($v['operator']) ? $v['operator'] : 'IN';
                $args[$cnt]['terms'] = $v['terms'];
            }
            $cnt++;
        }
        return $args;
    }

    /**
     * Create meta query format from an array of meta-fields.
     *
     * @param array $fields
     *
     * @return array
     */
    public static function GetMetaQueriesForQuery(array $fields): array
    {
        //https://wordpress.stackexchange.com/questions/70864/meta-query-compare-operator-explanation
        /**
         * Fields Example
         * array(
         * array(
         * 'key'   => 'ys_product_status',
         * 'value' => 'ok'
         * ),
         * array (
         * 'key'   => 'ys_product_start',
         * 'value' => date('Ymd'),
         * 'compare' => '>='
         * ),
         * array (
         * 'key'   => 'ys_product_end',
         * 'value' => date('Ymd'),
         * 'compare' => '<='
         * )
         * )
         */
        $args = [];
        $cnt = 0;
        foreach ($fields as $k => $v) {
            if (is_array($k)) {
                return self::GetMetaQueriesForQuery($k);
            }

            $args['relation'] = 'AND';
            if (count($fields) > 1) {
                //more then one field set
                if (!empty($fields['relation']) &&
                    $fields['relation'] !== 'AND') {
                    $args['relation'] = 'OR'; // OR
                }
            }

            $args[$cnt]['compare'] = !empty($v['compare']) ? $v['compare'] : '=';
            if (!empty($v['key']) && !empty($v['value'])) {
                $args[$cnt]['key'] = $v['key'];
                $args[$cnt]['value'] = $v['value'];
            } else if (!empty($v['key'])) {
                $args[$cnt]['key'] = (string)$v['key'];
            } else if (!empty($v['value'])) {
                $args[$cnt]['value'] = (string)$v['value'];
            }
            $cnt++;
        }

        return $args;
    }

    /**
     * Returns current post type of page
     * @return string|null
     */
    public static function GetCurrentPostType(): ?string
    {
        // https://gist.github.com/bradvin/1980309
        global $post, $typenow, $current_screen;

        if ($post && $post->post_type) {
            return $post->post_type;
        }

        if ($typenow) {
            return $typenow;
        }

        if ($current_screen && $current_screen->post_type) {
            return $current_screen->post_type;
        }

        if (isset($_REQUEST['post_type'])) {
            return sanitize_key($_REQUEST['post_type']);
        }

        if (isset($_REQUEST['post'])) {
            $p = get_post($_REQUEST['post']);
            if ($p) {
                return $p->post_type;
            }
        }

        // Unknown
        return null;
    }

    /**
     * @param array $post_ids
     *
     * @return array|bool
     */
    public static function GetPostAttachments(array $post_ids): bool|array
    {
        global $wpdb;
        $post_ids_for_sql = implode(',', $post_ids);
        if (empty($post_ids_for_sql)) {
            //no post id's so return false.
            return false;
        }
        $sql = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent IN(%d) and post_type = 'attachment'",$post_ids_for_sql);
        $wpdb->query($sql);
        if (!$wpdb->num_rows) {
            //no matching post with attachments so return false.
            return false;
        }
        $post_in = $wpdb->get_col($sql);
        $att = Query::GetPost([], [], [
            'posts_per_page' => -1,
            'post_mime_type' => 'image',
            'post_status' => 'all',
            'post_type' => 'attachment',
            'post__in' => $post_in,
            'ignore_sticky_posts' => 1
        ]);

        $retVal = [];
        if (!empty($att->posts)) {
            $c = 0;
            foreach ($att->posts as $post) {
                $m = get_attached_media('', $post);
                if (!empty($m)) {
                    $retVal[$c] = $m;
                }
                $c++;
            }

            return $retVal;
        }

        return false;
    }

    public static function get_post_id_by_slug($slug, $post_type = "any", $status = 'publish'): bool|string|null
    {
        global $wpdb;

        if ($post_type === "any") {
            $sql = 'SELECT ID FROM ' . $wpdb->posts . '
            WHERE post_name = %s AND post_status = %s';
            $query = $wpdb->prepare($sql, $slug, $status);
        } else {
            $sql = 'SELECT ID FROM ' . $wpdb->posts . '
            WHERE post_name = %s AND post_type = %s AND post_status = %s';
            $query = $wpdb->prepare($sql, $slug, $post_type, $status);
        }

        $wpdb->query($query);
        if ($wpdb->num_rows) {
            return $wpdb->get_var($query);
        }
        return false;
    }

    public static function get_post_id_by_title($title, $post_type = "any", $status = 'publish'): bool|string|null
    {
        global $wpdb;

        if ($post_type === "any") {
            $sql = 'SELECT ID FROM ' . $wpdb->posts . '
            WHERE post_title = %s AND post_status = %s';
            $query = $wpdb->prepare($sql, $title, $status);
        } else {
            $sql = 'SELECT ID FROM ' . $wpdb->posts . '
            WHERE post_title = %s AND post_type = %s AND post_status = %s';
            $query = $wpdb->prepare($sql, $title, $post_type, $status);
        }

        $wpdb->query($query);
        if ($wpdb->num_rows) {
            return $wpdb->get_var($query);
        }
        return false;
    }

    /**
     * Modified from https://www.cssigniter.com/programmatically-get-related-wordpress-posts-easily/
     *
     * @param null $post_ids
     * @param int $related_count
     *
     * @param array $args
     * @return bool | WP_Query
     */
    public
    static function GetRelatedPost($post_ids = null, int $related_count = 15, array $args = []): WP_Query|bool
    {
        $post_ids = is_array($post_ids) ? $post_ids : [$post_ids];

        //Attempt to load the categories and tags
        if (!empty($post_ids) && is_array($post_ids)) {
            $array1 = [
                'post_type' => "any",
                'posts_per_page' => $related_count,
                'post_status' => 'publish',
                'post__not_in' => $post_ids,
                'ignore_sticky_posts' => 1,
                'not_paged' => 1
            ];
            $related_args = Utility::TwoArrayMerge($array1, $args);
            //has a bunch of post ids.
            $categories = [];
            $tags = [];
            $cat = false;
            $tag = false;
            $taxonomy = [];
            $mode = null;
            foreach ($post_ids as $post_id) {
                if ($c = wp_list_pluck(get_the_terms($post_id, "category"), 'slug')) {
                    $categories[] = $c[0];
                }
                if ($t = wp_list_pluck(get_the_tags($post_id), 'slug')) {
                    $tags[] = $t;
                }
            }
            if (($key = array_search('uncategorized', $categories, true)) !== false) {
                unset($categories[$key]);
            }
            if (empty($categories) && empty($tags)) {
                return false;
            }
            if (!empty($categories)) {
                $cat2 = [];
                foreach ($categories as $c) {
                    if (!in_array($c, $cat2, true)) {
                        $cat2[] = $c;
                    }
                }
                $cat = [
                    'taxonomy' => 'categories',
                    'field' => 'slug',
                    'terms' => $cat2,
                    'operator' => 'IN'
                ];
                $mode = "cats";
            }

            if (!empty($tags)) {
                $tags2 = [];
                foreach ($tags as $t) {
                    foreach ($t as $tt) {
                        if (!in_array($tt, $tags2, true)) {
                            $tags2[] = $tt;
                        }
                    }
                }
                $tag = [
                    'taxonomy' => 'post_tag',
                    'field' => 'slug',
                    'terms' => $tags2,
                    'operator' => 'IN'
                ];
                $mode .= "tags";
            }
            switch ($mode) {
                case null:
                    return false; // no terms to query
                case "cats":
                    $taxonomy = $cat;
                    break;
                case "tags":
                    $taxonomy = $tag;
                    break;
                case "catstags":
                    $taxonomy = ['relation' => 'OR', $cat, $tag];
                    break;
            }

            return self::GetPost($taxonomy, null, $related_args);
        }

        return false;

    }

    public static function MergeResults(array $arrays): WP_Query
    {
        $result = new WP_Query();
        $result->posts = array_merge(...$arrays);
        $result->post_count = count($result->posts);
        return $result;
    }
}
