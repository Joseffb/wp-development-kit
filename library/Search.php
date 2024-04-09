<?php

namespace WDK;

use RuntimeException;

class Search
{
    protected WP_Search_Provider $search_provider;

    /**
     * @param $method
     * @param $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        // Handle the undefined method call
        if (($this->search_provider ?? null) && method_exists($this->search_provider, $method)) {
            return call_user_func_array([$this->search_provider, $method], $arguments);
        }

        throw new RuntimeException("'$method' does not exist in the current search provider", 10403);
    }

    /**
     * @return string|null defaults to WDK
     */
    private function get_calling_namespace(): ?string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1];

        if (isset($caller['class'])) {
            try {
                return (new \ReflectionClass($caller['class']))->getNamespaceName();
            } catch (\ReflectionException $e) {
                return 'WDK';
            }
        }

        return null;
    }

    /**
     * @param string|null $provider
     * @param $args
     */
    public function __construct(?string $provider = '\\WDK\\WP_Local_Search_Provider', $args = [])
    {
        Utility::Log($provider);
        // Check if the given class exists
        if ($provider && !class_exists($provider)) {
            // Check if the WDK namespaced class exists
            $wdkNamespacedProvider = "\\WDK\\$provider";
            if (class_exists($wdkNamespacedProvider)) {
                Utility::Log($wdkNamespacedProvider);
                $this->search_provider = $provider = $wdkNamespacedProvider;
            }
            // Check if the called_namespaced class exists
            else {
                $callingNamespace = $this->get_calling_namespace();
                //echo "Calling namespace: " . $callingNamespace . PHP_EOL;
                $calledNamespacedProvider = $callingNamespace . '\\' . $provider;
                Utility::Log($calledNamespacedProvider);
                if (class_exists($calledNamespacedProvider)) {
                    $this->search_provider = $provider = $calledNamespacedProvider;
                } else {
                    throw new RuntimeException('Invalid search provider class provided: ' . $provider);
                }
            }
        }

        if (empty($args)) {
            $this->set_search_provider($provider);
        } else {
            $this->set_search_provider( new $provider(...$args));
        }
    }

    /**
     * @param $query
     * @param $args
     * @param $provider
     * @return \WP_Query
     */
    public static function find($query, $args = [], $provider = 'WP_Local_Search_Provider'): \WP_Query
    {
        return (new self($provider, $args))->search($query,$args);
    }

    /**
     * @param $search_provider
     * @param $args
     * @return void
     */
    public function set_search_provider($search_provider, $args = []): void
    {
        if (is_string($search_provider)) {
            if (!class_exists($search_provider)) {
                throw new RuntimeException('Invalid search provider class provided.');
            }

            if (!is_subclass_of($search_provider, WP_Search_Provider::class)) {
                throw new RuntimeException('Search provider class must extend WP_Search_Provider.');
            }

            if (!empty($args)) {
                $this->search_provider = new $search_provider(...$args);
            } else {
                $this->search_provider = new $search_provider();
            }
        } elseif ($search_provider instanceof WP_Search_Provider) {
            $this->search_provider = $search_provider;
        } else {
            throw new RuntimeException('Invalid search provider type provided. Must be a class name or an instance of WP_Search_Provider.');
        }
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