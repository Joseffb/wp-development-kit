<?php

namespace WDK;

class WP_Local_Search_Provider extends WP_Search_Provider
{
    public function search($query, $args = []): \WP_Query
    {
        if(!empty($query)) {
            $args['s']=$query;
        }
        if (isset($args['like']) && $args['like']) {
            global $wpdb;
            $like_query = '%' . $wpdb->esc_like($query) . '%';
            $post_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT ID FROM {$wpdb->posts}
                 WHERE (post_title LIKE %s OR post_content LIKE %s)
                 AND post_type != 'revision' AND post_status = 'publish';",
                $like_query, $like_query
            ));
            unset($args['s']);
            $args['post__in'] = !empty($post_ids) ? $post_ids : array(-1);
        }

        //return new \WP_Query($args);
        return $this->debug_wp_query($args);
    }

    public function debug_wp_query($query_args): \WP_Query
    {
        if (defined("WP_DEBUG") && constant("WP_DEBUG")) {
            // Callback to capture the SQL query
            add_filter('posts_request', function ($sql, $query) {
                $GLOBALS['captured_sql_query'] = $sql;
                return $sql;
            }, 10, 2);

            // Execute the WP_Query
            $query = new \WP_Query($query_args);

            // Remove the callback to prevent affecting other queries
            remove_filter('posts_request', 'capture_sql_query', 10);

            // Output the query arguments and the SQL query
            Utility::Log($query_args,"WP_Query Arguments:");

            Utility::Log($GLOBALS['captured_sql_query'], "Generated SQL Query:");

            // Check for any MySQL errors
            global $wpdb;
            if ($wpdb->last_error) {
                Utility::Log($wpdb->last_error, 'MySQL Error:');
            } else {
                Utility::Log("No MySQL errors");
            }
        } else {
            $query = new \WP_Query($query_args);
        }
        return $query;
    }
}
