<?php

namespace WDK;
/**
 * The `Hive` class is a static utility class for querying WordPress posts, and provides a convenient way to access
 * post data and related metadata and taxonomies.
 *
 * Usage:
 * - Get a post by ID: `$post = Hive::post_type_name(123);`
 * - Get a post by name: `$post = Hive::post_type_name('example-post');`
 * - Get a post by shadow taxonomy: `$post = Hive::post_type_name(['shadow_term_id' => 1234]);`
 * - Get a post with specific meta value: `$post = Hive::post_type_name(['meta' => ['meta_key' => 'meta_value']]);`
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
    private ?\WP_Post $post = null;

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
        $slug = get_page_by_path($post_name, OBJECT);
        if ($slug) {
            $this->post = $slug;
        } else {
            $this->query_args['title'] = $post_name;
        }
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
        $post = null;
        if (!$this->post) {
            $post = $search->search("", $this->query_args)->posts[0];
            //$post = (new WP_Query($this->query_args))->posts[0];
        }

        if (!$post) {
            return null;
        }

        // Get taxonomy data
        $taxonomies = get_object_taxonomies($post->post_type);
        $taxonomy_data = [];
        $shadow_id = Shadow::GetAssociatedTermID($post);
        $shadow_term = $shadow_id ? get_term($shadow_id) : null;
        foreach ($taxonomies as $taxonomy) {
            $taxonomy_data[$taxonomy] = get_the_terms($post->ID, $taxonomy);
        }
        if (!empty($taxonomy_data[$shadow_term->name])) {
            $taxonomy_data['shadow'] = [$taxonomy_data[$shadow_term] ?? null];
            unset($taxonomy_data[$shadow_term->name]);
        }
        return (object)[
            'post' => $post,
            'meta' => get_post_meta($post->ID),
            'taxonomies' => $taxonomy_data,
            'media' => new class ($post) {
                private \WP_Post $post;
                private \wpdb $wpdb;
                public function __construct($post)
                {
                    global $wpdb;
                    $this->post = $post;
                    $this->wpdb = $wpdb;
                }

                public function image(): array
                {
                    return $this->get_by_mime_type_wildcard('image');
                }

                public function audio(): array
                {
                    return $this->get_by_mime_type_wildcard('audio');
                }

                public function video(): array
                {
                    return $this->get_by_mime_type_wildcard('video');
                }

                public function application(): array
                {
                    return $this->get_by_mime_type_wildcard('application');
                }

                public function pdf(): array
                {
                    return $this->get_by_mime_type('application/pdf');
                }

                public function html(): array
                {
                    return $this->get_by_mime_type('text/html');
                }

                public function xml(): array
                {
                    return $this->get_by_mime_type('application/xml');
                }

                public function css(): array
                {
                    return $this->get_by_mime_type('text/css');
                }

                public function js(): array
                {
                    return $this->get_by_mime_type('application/javascript');
                }

                public function zip(): array
                {
                    return $this->get_by_mime_type('application/zip');
                }

                public function tar(): array
                {
                    return $this->get_by_mime_type('application/tar');
                }

                public function rar(): array
                {
                    return $this->get_by_mime_type('application/rar');
                }

                private function get_by_mime_type_wildcard($mime_type): array
                {
                    $sql = $this->wpdb->prepare(
                        "SELECT * FROM {$this->wpdb->posts} 
             WHERE post_parent = %d 
             AND post_type = 'attachment' 
             AND post_mime_type LIKE %s 
             ORDER BY post_date ASC",
                        $this->post->ID,
                        $mime_type . '%'
                    );

                    return $this->wpdb->get_results($sql);
                }

                private function get_by_mime_type($mime_type): array
                {
                    $args = array(
                        'posts_per_page' => -1,
                        'order' => 'ASC',
                        'post_parent' => $this->post->ID,
                        'post_type' => 'attachment',
                        'post_mime_type' => $mime_type
                    );
                    return get_children($args);
                }
            },
            'relationships' => new class($post) {
                private \WP_Post $post;

                public function __construct($post)
                {
                    $this->post = $post;
                }

                public function children(): array
                {
                    $args = array(
                        'posts_per_page' => -1,
                        'order' => 'ASC',
                        'post_parent' => $this->post->ID,
                        'post_type' => 'any',
                        'post_status' => 'any',
                        'post__not_in' => get_posts(array(
                            'post_type' => 'attachment',
                            'post_parent' => $this->post->ID,
                            'posts_per_page' => -1,
                            'fields' => 'ids'
                        )),
                    );
                    return (new \WP_Query($args))->get_posts();
                }

                public function parent()
                {
                    if ($this->post->post_parent) {
                        return get_post($this->post->post_parent);
                    }
                    return null;
                }
            },
            'update' => new class($post) {
                private \WP_Post $post;

                public function __construct($post)
                {
                    $this->post = $post;
                }

                public function post($args)
                {
                    $args['ID'] = $this->post->ID;
                    wp_update_post($args);
                }

                public function taxonomy($taxonomy, $term, $args = [])
                {
                    $term_id = term_exists($term, $taxonomy);

                    if (!$term_id) {
                        $term = wp_insert_term($term, $taxonomy, $args);
                        $term_id = $term['term_id'];
                    }

                    return wp_set_object_terms($this->post->ID, $term_id, $taxonomy);
                }

                public function meta($key, $value)
                {
                    return update_post_meta($this->post->ID, $key, $value);
                }
            },
            'delete' => new class($post) {
                private $post;

                public function __construct($post)
                {
                    $this->post = $post;
                }

                public function post()
                {
                    wp_delete_post($this->post->ID, true);
                }

                public function taxonomy($taxonomy, $term): bool
                {
                    $term_id = term_exists($term, $taxonomy);

                    if (!$term_id) {
                        return false;
                    }

                    return wp_remove_object_terms($this->post->ID, $term_id, $taxonomy);
                }

                public function meta($key)
                {
                    return delete_post_meta($this->post->ID, $key);
                }
            }
        ];
    }
}