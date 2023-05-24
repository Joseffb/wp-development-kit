<?php

namespace WDK;
/**
 * The `Hive` class is a static utility class for querying WordPress posts, and provides a convenient way to access
 * post data and related metadata and taxonomies.
 *
 * Usage:
 * - Get a post by ID: `$post = Hive::post_name(123);`
 * - Get a post by name: `$post = Hive::post_name('example-post');`
 * - Get a post by shadow taxonomy: `$post = Hive::post_name(['shadow_term_id' => 1234]);`
 * - Get a post with specific meta value: `$post = Hive::post_name(['meta' => ['meta_key' => 'meta_value']]);`
 *
 * Once you have a post, you can access its data using the following properties:
 * - `$post->post` : the WP_Post object for the post
 * - `$post->meta` : an array of post meta values
 * - `$post->shadow_tax` : the shadow term ID for the post (if applicable)
 * - `$post->taxonomies` : an array of taxonomy term objects for the post
 *
 * Additionally, you can use the following methods to update or delete data:
 * - `$post->update_meta($key, $value)` : update a meta value for the post
 * - `$post->delete_meta($key)` : delete a meta value for the post
 * - `$post->update_taxonomy($taxonomy, $term, $args)` : update a taxonomy term for the post
 * - `$post->delete_taxonomy($taxonomy)` : delete a taxonomy term for the post
 * - `$post->update_post($args)` : update the post itself with new data
 * - `$post->delete_post()` : delete the post from the database
 *
 * Examples:
 *
 * // Get a post by ID
 * $post = Hive::post(42);
 * $title = $post->post->post_title;
 * * //get a specific meta value from the pulled post:
 * $featured = $post->meta['_featured'][0];
 *
 * // Get a post by name
 * $post = Hive::post('example-post');
 * $excerpt = $post->post->post_excerpt;
 *
 * // Get a post by associated shadow's term id
 * $post = Hive::custom_post_type_name(['shadow_term_id' => '1234']);
 *
 * // Get a post with specific meta value
 * $books = Hive::books(['meta' => ['_sku' => 'abc123']]);
 *
 * // Update a meta value for a post
 * $post = Hive::post_type_name(123);
 * $post->update->meta('_price', 19.99);
 *
 * // Update a taxonomy term for a post
 * $post = Hive::post_type_name(123);
 * $post->update->taxonomy('category', 'Widgets', ['description' => 'Product category']);
 *
 * // Update the post itself
 * $post = Hive::post_type_name(123);
 * $post->update->post(['post_title' => 'New Title']);
 *
 * // Delete the post from the database
 * $post = Hive::post_type_name(42);
 * $post->delete->post();
 *
 * // Delete a post taxonomy from the database
 * $post = Hive::post_type_name(42);
 * $post->delete->taxonomy('Category', 'value');
 *
 * // Delete a post meta from the database
 * $post = Hive::post_type_name(42);
 * $post->delete->meta('custom_field_name');
 */
class Hive
{
    private array $query_args = [];

    public static function __callStatic($post_type, $arguments)
    {
        if (!post_type_exists($post_type)) {
            return null;
        }

        $args = $arguments[0] ?? [];
        $hive = new self($post_type);

        if (is_int($args) || is_numeric($args)) {
            $hive->id($args);
        } elseif (is_string($args)) {
            $hive->name($args);
        } elseif (is_array($args)) {
            $hive->query_args = $args;
            if (isset($args['shadow_term_id'])) {
                $hive->shadow($args['shadow_term_id']);
                unset($hive->query_args['shadow_term_id']);
            } else if (isset($args['meta']) && is_array($args['meta'])) {
                $hive->meta($args['meta']);
                unset($hive->query_args['meta']);
            }
        }

        return $hive->get();
    }

    public function __construct($post_type)
    {
        $this->query_args['post_type'] = $post_type;
        $this->query_args['posts_per_page'] = 1;
        $this->query_args['no_found_rows'] = true;
    }

    public function id($post_id): Hive
    {
        $this->query_args['p'] = $post_id;
        return $this;
    }

    public function name($post_name): Hive
    {
        $this->query_args['name'] = $post_name;
        return $this;
    }

    public function shadow(int $term_id): ?Hive
    {
        $post_id = (int)get_term_meta($term_id, 'shadow_post_id', true);
        if (!$post_id) {
            return null;
        }
        $this->query_args['p'] = $post_id;
        return $this;
    }

    public function meta($meta_query): Hive
    {
        $this->query_args['meta_query'] = $meta_query;
        return $this;
    }

    public function get(): ?object
    {
        $provider = $this->query_args['search_provider'] ?? null;
        $search = new Search($provider);
        if (method_exists($search, 'hive_get')) {
            return $search->hive_get($this->query_args);
        }
        $post = $search->search("", $this->query_args)->posts[0];
        //$post = (new WP_Query($this->query_args))->posts[0];

        if (!$post) {
            return null;
        }

        // Get post meta data
        $meta = get_post_meta($post->ID);

        // Get taxonomy data
        $taxonomies = get_object_taxonomies($post->post_type);
        $taxonomy_data = [];
        $shadow_id = Shadow::GetAssociatedTermID($post);
        $shadow_term = $shadow_id ? get_term($shadow_id) : null;
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_data[$taxonomy] = get_the_terms($post->ID, $taxonomy);
        }
        if(!empty($taxonomy_data[$shadow_term->name])) {
            $taxonomy_data['shadow'] = [$taxonomy_data[$shadow_term] ?? null];
            unset($taxonomy_data[$shadow_term->name]);
        }
        return (object)[
            'post' => $post,
            'meta' => $meta,
            'taxonomies' => $taxonomy_data,
            'update' => [
                'post' => function ($args) use ($post) {
                    $args['ID'] = $post->ID;
                    wp_update_post($args);
                },
                'taxonomy' => function ($taxonomy, $term, $args = []) use ($post) {
                    $term_id = term_exists($term, $taxonomy);

                    if (!$term_id) {
                        $term = wp_insert_term($term, $taxonomy, $args);
                        $term_id = $term['term_id'];
                    }

                    return wp_set_object_terms($post->ID, $term_id, $taxonomy);
                },
                'meta' => function ($key, $value) use ($post) {
                    return update_post_meta($post->ID, $key, $value);
                },
            ],
            'delete' => [
                'post' => function () use ($post) {
                    wp_delete_post($post->ID, true);
                },
                'taxonomy' => function ($taxonomy, $term) use ($post): bool {
                    $term_id = term_exists($term, $taxonomy);

                    if (!$term_id) {
                        return false;
                    }

                    return wp_remove_object_terms($post->ID, $term_id, $taxonomy);
                },
                'meta' => function ($key) use ($post) {
                    return delete_post_meta($post->ID, $key);
                },
            ],
        ];
    }
}