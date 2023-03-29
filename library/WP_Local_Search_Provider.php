<?php

namespace WDK;

class WP_Local_Search_Provider extends WP_Search_Provider
{
    public function search($query, $args = []): WP_Query
    {
        $query_args = array(
            's' => $query,
            'p' => $args['p'] ?? '',
            'name' => $args['name'] ?? '',
            'title' => $args['title'] ?? '',
            'page_id' => $args['page_id'] ?? '',
            'pagename' => $args['pagename'] ?? '',
            'post_parent' => $args['post_parent'] ?? '',
            'post_parent__in' => $args['post_parent__in'] ?? array(),
            'post_parent__not_in' => $args['post_parent__not_in'] ?? array(),
            'post__in' => $args['post__in'] ?? array(),
            'post__not_in' => $args['post__not_in'] ?? array(),
            'post_name__in' => $args['post_name__in'] ?? array(),
            'post_type' => $args['post_type'] ?? 'any',
            'post_status' => $args['post_status'] ?? 'publish',
            'posts_per_page' => $args['posts_per_page'] ?? 10,
            'nopaging' => $args['nopaging'] ?? false,
            'offset' => $args['offset'] ?? '',
            'paged' => $args['paged'] ?? '',
            'ignore_sticky_posts' => $args['ignore_sticky_posts'] ?? false,
            'orderby' => $args['orderby'] ?? 'date',
            'order' => $args['order'] ?? 'DESC',
            'meta_key' => $args['meta_key'] ?? '',
            'meta_value' => $args['meta_value'] ?? '',
            'meta_value_num' => $args['meta_value_num'] ?? '',
            'meta_compare' => $args['meta_compare'] ?? '',
            'meta_query' => $args['meta_query'] ?? '',
            'tax_query' => $args['tax_query'] ?? '',
            'cat' => $args['cat'] ?? '',
            'category_name' => $args['category_name'] ?? '',
            'category__and' => $args['category__and'] ?? array(),
            'category__in' => $args['category__in'] ?? array(),
            'category__not_in' => $args['category__not_in'] ?? array(),
            'tag' => $args['tag'] ?? '',
            'tag_id' => $args['tag_id'] ?? '',
            'tag__and' => $args['tag__and'] ?? array(),
            'tag__in' => $args['tag__in'] ?? array(),
            'tag__not_in' => $args['tag__not_in'] ?? array(),
            'tag_slug__and' => $args['tag_slug__and'] ?? array(),
            'tag_slug__in' => $args['tag_slug__in'] ?? array(),
            'author' => $args['author'] ?? '',
            'author_name' => $args['author_name'] ?? '',
            'author__in' => $args['author__in'] ?? array(),
            'author__not_in' => $args['author__not_in'] ?? array(),
            'perm' => $args['perm'] ?? '',
            'cache_results' => $args['cache_results'] ?? '',
            'update_post_meta_cache' => $args['update_post_meta_cache'] ?? '',
            'update_post_term_cache' => $args['update_post_term_cache'] ?? '',
            'w' => $args['w'] ?? '',
            'year' => $args['year'] ?? '',
            'monthnum' => $args['monthnum'] ?? '',
            'day' => $args['day'] ?? '',
            'hour' => $args['hour'] ?? '',
            'minute' => $args['minute'] ?? '',
            'second' => $args['second'] ?? '',
            'm' => $args['m'] ?? '',
            'comment_count' => $args['comment_count'] ?? '',
            'no_found_rows' => $args['no_found_rows'] ?? false,
            'fields' => $args['fields'] ?? 'all',
            'menu_order' => $args['menu_order'] ?? '',
            'post_mime_type' => $args['post_mime_type'] ?? '',
            'subtype' => $args['subtype'] ?? '',
            'date_query' => $args['date_query'] ?? array(),
        );

        if ( isset( $args['like'] ) && $args['like'] ) {
            global $wpdb;
            $like_query = '%' . $wpdb->esc_like( $query ) . '%';
            $post_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT ID FROM {$wpdb->posts}
                 WHERE (post_title LIKE %s OR post_content LIKE %s)
                 AND post_type != 'revision' AND post_status = 'publish';",
                $like_query, $like_query
            ));

            $query_args['post__in'] = !empty( $post_ids ) ? $post_ids : array(-1);
        }

        return new WP_Query($query_args);
    }
}

/**
 * Example uses:
 *
 * Post Search
$search = search(array(
's' => 'example', // Search keyword
'post_type' => 'post', // Post type
));
 *
 * Taxonomy Search
$search = search(array(
'tax_query' => array(
array(
'taxonomy' => 'category', // Taxonomy name
'field' => 'slug',
'terms' => 'technology', // Term slug
),
),
));
 *
 * Meta Search
$search = search(array(
'meta_query' => array(
array(
'key' => 'custom_meta_key', // Meta key
'value' => 'custom_value', // Meta value
'compare' => '=', // Comparison operator
),
),
));
 *
 * Mixed Search
$search = search(array(
's' => 'example', // Search keyword
'post_type' => 'post', // Post type
'tax_query' => array(
array(
'taxonomy' => 'category', // Taxonomy name
'field' => 'slug',
'terms' => 'technology', // Term slug
),
),
'meta_query' => array(
array(
'key' => 'custom_meta_key', // Meta key
'value' => 'custom_value', // Meta value
'compare' => '=', // Comparison operator
),
),
));
 */