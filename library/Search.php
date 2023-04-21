<?php

namespace WDK;

use http\Exception\BadMethodCallException;
use http\Exception\InvalidArgumentException;

class Search
{
    protected $search_provider;

    public function __call($method, $arguments)
    {
        // Handle the undefined method call
        if (($this->search_provider ?? null) && method_exists($this->search_provider, $method)) {
            return call_user_func_array([$this->search_provider, $method], $arguments);
        }

        throw new BadMethodCallException("'$method' does not exist in the current search provider", 10403);
    }

    public function __construct($provider = 'WP_Local_Search_Provider', $args = [])
    {
        if (!class_exists($provider)) {
            throw new InvalidArgumentException('Invalid search provider class provided.');
        }

        if (empty($args)) {
            $this->search_provider = new $provider();
        } else {
            $this->search_provider = new $provider(...$args);
        }
    }

    public static function find($query, $args = [], $provider = 'WP_Local_Search_Provider'): \WP_Query
    {
        return (new self($provider, $args))->search($query);
    }

    public function set_search_provider(WP_Search_Provider $search_provider)
    {
        $this->search_provider = $search_provider;
    }

    /**
     * @param $query
     * @param array $args
     * @return \WP_Query | \WP_Error
     */
    public function search($query, array $args = [])
    {
        return $this->search_provider->search($query, $args);
    }

    public static function wp_query_return($posts, $count = null, $pagination = 1)
    {
        $count = $count??count($posts);
        return new class($posts, $count, $pagination) extends \WP_Query {
            public function __construct($posts, $found_posts, $max_num_pages) {
                $this->posts = $posts;
                $this->post_count = count($posts);
                $this->found_posts = $found_posts;
                $this->max_num_pages = $max_num_pages;
            }
        };
    }
}


/**
 * // Usage example:
 *
 * // Use default WP_Query search
 * $search = new Search();
 * $results = $search->search( 'Your search query here' );
 *
 * // Use WP_Query with LIKE search
 * $like_args = ['like' => true];
 * $results = $search->search( 'Your search query here', $like_args );
 *
 * // Use WP_Query with taxonomies and custom post types
 * $tax_args = [
 * 'post_type' => 'your_custom_post_type',
 * 'tax_query' => [
 * [
 * 'taxonomy' => 'your_taxonomy',
 * 'field' => 'slug',
 * 'terms' => 'your_taxonomy_term'
 * ]
 * ]
 * ];
 * $results = $search->search( 'Your search query here', $tax_args );
 *
 * // Use a custom search provider
 * class Custom_Search_Provider extends WP_Search_Provider {
 * public function search( $query, $args ) {
 * // Implement your custom search logic here
 * }
 * }
 *
 * $custom_search_provider = new Custom_Search_Provider();
 * $search = new Search();
 * $search->set_search_provider( $custom_search_provider );
 * $results = $search->search( 'Your search query here', $args );
 */