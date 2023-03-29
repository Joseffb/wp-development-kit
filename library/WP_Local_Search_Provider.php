<?php
namespace WDK;
class WP_Local_Search_Provider extends WP_Search_Provider {
    public function search( string $query, ?array $args = [] ): WP_Query
    {
        global $wpdb;

        if ( isset( $args['like'] ) ) {
            $like_query = '%' . $wpdb->esc_like( $query ) . '%';
            $post_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT ID FROM {$wpdb->posts}
                 WHERE (post_title LIKE %s OR post_content LIKE %s)
                 AND post_type = 'any' AND post_status = 'publish';",
                $like_query, $like_query
            ));

            $query_args = [
                'post__in' => $post_ids,
                'post_type' => 'any',
                'post_status' => 'publish',
            ];
        } else {
            $query_args = array(
                's' => $query,
                'post_type' => 'any',
                'post_status' => 'publish',
            );
        }

        return new WP_Query( $query_args );
    }
}

// Usage example:
//
//// Use default WP_Query search
//$search_manager = new Search();
//$results = $search_manager->search( 'Your search query here' );
//
//// Use WP_Query with LIKE search
//$args = ['like' => true];
//$results = $search_manager->search( '', $args );