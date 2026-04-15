<?php

namespace WDK;

use RuntimeException;

class Search
{
    protected WP_Search_Provider $search_provider;

    /**
     * @param string|WP_Search_Provider|null $provider
     * @param array $provider_args
     */
    public function __construct(string|WP_Search_Provider|null $provider = null, array $provider_args = [])
    {
        $this->set_search_provider($provider, $provider_args);
    }

    /**
     * @param $query
     * @param $args
     * @param $provider
     * @return \WP_Query
     */
    public static function find($query, $args = [], string|WP_Search_Provider|null $provider = null): \WP_Query
    {
        $providerArgs = [];
        if (isset($args['provider_args']) && is_array($args['provider_args'])) {
            $providerArgs = $args['provider_args'];
            unset($args['provider_args']);
        } elseif (isset($args['provider_constructor_args']) && is_array($args['provider_constructor_args'])) {
            Compatibility::warn(__METHOD__, 'provider_constructor_args is deprecated. Use provider_args instead.');
            $providerArgs = $args['provider_constructor_args'];
            unset($args['provider_constructor_args']);
        }

        return (new self($provider, $providerArgs))->search($query, $args);
    }

    /**
     * @param $search_provider
     * @param $args
     * @return void
     */
    public function set_search_provider(string|WP_Search_Provider|null $search_provider, array $args = []): void
    {
        $this->search_provider = ProviderResolver::resolve(
            $search_provider,
            '\\WDK\\WP_Local_Search_Provider',
            WP_Search_Provider::class,
            $args,
            'search provider'
        );
    }

    /**
     * @param $query
     * @param array $args
     * @return \WP_Query | \WP_Error
     */
    public function search($query, array $args = [])
    {
        unset($args['provider_args'], $args['provider_constructor_args']);
        return $this->search_provider->search($query, $args);
    }

    /**
     * @param $method
     * @param $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (($this->search_provider ?? null) && method_exists($this->search_provider, $method)) {
            return call_user_func_array([$this->search_provider, $method], $arguments);
        }

        throw new RuntimeException("'$method' does not exist in the current search provider", 10403);
    }

    /**
     * @param $posts
     * @param $count
     * @param $pagination
     * @return \WP_Query
     */
    public static function wp_query_return($posts, $count = null, $pagination = 1): \WP_Query
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
