<?php
namespace WDK;
class Search {
    protected $search_provider;

    public function __construct( $provider = 'WP_Local_Search_Provider', $args = [] ) {
        if ( !class_exists( $provider ) ) {
            throw new InvalidArgumentException( 'Invalid search provider class provided.' );
        }

        if ( empty( $args ) ) {
            $this->search_provider = new $provider();
        } else {
            $this->search_provider = new $provider( ...$args );
        }
    }

    public function set_search_provider( WP_Search_Provider $search_provider ) {
        $this->search_provider = $search_provider;
    }

    public function search( $query, $args = [] ): WP_Query
    {
        return $this->search_provider->search( $query, $args );
    }
}
/**
// Usage example:

// Use default WP_Query search
$search = new Search();
$results = $search->search( 'Your search query here' );

// Use WP_Query with LIKE search
$like_args = ['like' => true];
$results = $search->search( 'Your search query here', $like_args );

// Use WP_Query with taxonomies and custom post types
$tax_args = [
    'post_type' => 'your_custom_post_type',
    'tax_query' => [
        [
            'taxonomy' => 'your_taxonomy',
            'field' => 'slug',
            'terms' => 'your_taxonomy_term'
        ]
    ]
];
$results = $search->search( 'Your search query here', $tax_args );
 *
// Use a custom search provider
class Custom_Search_Provider extends WP_Search_Provider {
    public function search( $query, $args ) {
    // Implement your custom search logic here
    }
}

$custom_search_provider = new Custom_Search_Provider();
$search = new Search();
$search->set_search_provider( $custom_search_provider );
$results = $search->search( 'Your search query here', $args );
 */