<?php
// Based on code from Human Made's Shadow Taxonomy plugin
// Original source: https://github.com/humanmade/shadow-taxonomy

namespace WDK;

use Closure;
use WP_Post;
use WP_Term;

/**
 * Class Shadow
 *
 * @version 1.0.3
 * @since 1.0
 *
 * The `Shadow` class is part of the WDK (WordPress Development Kit) and provides functionality for creating and managing
 * shadow taxonomies in WordPress. A shadow taxonomy is a taxonomy that mirrors the posts of a custom post type,
 * ensuring that any changes made to the posts are reflected in the taxonomy terms and vice versa.
 *
 * **Usage:**
 *
 * - **Create a Shadow Taxonomy Relationship:**
 *   ```php
 *   use WDK\Shadow;
 *
 *   // Register a shadow taxonomy relationship between a post type and a taxonomy
 *   Shadow::create_relationship('my_post_type', 'my_taxonomy');
 *   ```
 *
 * - **Create a Shadow Taxonomy with Conditions:**
 *   ```php
 *   Shadow::create_relationship('my_post_type', 'my_taxonomy', [
 *       'operator' => 'AND',
 *       'conditions' => [
 *           [
 *               'taxonomy' => 'category',
 *               'values'   => ['News', 'Updates'],
 *           ],
 *           [
 *               'taxonomy' => 'post_tag',
 *               'values'   => ['Featured'],
 *           ],
 *       ],
 *   ]);
 *   ```
 *   In this example, a shadow term will be created only if the post has both a category of 'News' or 'Updates' **and** a tag of 'Featured'.
 *
 * - **Create a Shadow Taxonomy with Post Type Condition:**
 *   ```php
 *   Shadow::create_relationship('suggestion', 'submitter', [
 *       'operator' => 'AND',
 *       'post_type' => 'candidate',
 *   ]);
 *   ```
 *   In this example, the shadow term will only be created if the post's post type is 'candidate'.
 *
 * **Methods:**
 *
 * - `create_relationship(string $post_type, string $taxonomy, array $conditionals = []): void`
 *   - Registers the shadow taxonomy relationship and sets up the necessary hooks.
 *   - **Parameters:**
 *     - `$post_type` (string): The slug of the custom post type to create the relationship for.
 *     - `$taxonomy` (string): The slug of the taxonomy to be shadowed.
 *     - `$conditionals` (array): Optional. Conditions under which shadow terms are created or deleted. See below for details.
 *
 * - **Conditionals Parameter:**
 *   The `$conditionals` array can contain the following keys:
 *   - `'operator'` (string): Either `'AND'` or `'OR'`. Determines how multiple conditions are evaluated.
 *   - `'conditions'` (array): An array of conditions. Each condition is an associative array with:
 *     - `'taxonomy'` (string): The taxonomy to check terms in.
 *     - `'values'` (array|string): The term(s) to check for.
 *   - `'post_type'` (string): Optional. Specifies an alternative post type to consider instead of `$post_type`.
 *
 * **Examples:**
 *
 * - **Creating a Basic Relationship:**
 *   ```php
 *   // Create a shadow taxonomy relationship between 'event' post type and 'event_category' taxonomy
 *   Shadow::create_relationship('event', 'event_category');
 *   ```
 *   This will ensure that for every 'event' post, a corresponding term in 'event_category' is created or updated.
 *
 * - **Conditional Relationship:**
 *   ```php
 *   // Create a shadow taxonomy relationship with conditions
 *   Shadow::create_relationship('product', 'product_line', [
 *       'operator' => 'OR',
 *       'conditions' => [
 *           [
 *               'taxonomy' => 'product_type',
 *               'values'   => ['Electronics', 'Appliances'],
 *           ],
 *           [
 *               'taxonomy' => 'availability',
 *               'values'   => ['In Stock'],
 *           ],
 *       ],
 *   ]);
 *   ```
 *   A shadow term will be created if the product is of type 'Electronics' or 'Appliances', **or** if its availability is 'In Stock'.
 *
 * - **Conditional Relationship with Post Type:**
 *   ```php
 *   // Create a shadow taxonomy relationship that only applies to 'candidate' post type
 *   Shadow::create_relationship('suggestion', 'submitter', [
 *       'operator' => 'AND',
 *       'post_type' => 'candidate',
 *   ]);
 *   ```
 *   In this example, a shadow term will only be created if the post's post type is 'candidate', even though the relationship is registered for 'suggestion' post type.
 *
 * **Methods Details:**
 *
 * - **`create_relationship`**
 *   - **Description:** Sets up the necessary WordPress hooks to manage the shadow taxonomy relationship.
 *
 * - **`create_shadow_term`**
 *   - **Description:** Creates a closure for handling the creation or updating of shadow terms when posts are inserted or updated.
 *
 * - **`update_shadow_taxonomy_term`**
 *   - **Description:** Updates the shadow term to match the associated post.
 *   - **Parameters:**
 *     - `$term` (WP_Term): The term to update.
 *     - `$post` (WP_Post): The associated post.
 *     - `$taxonomy` (string): The taxonomy slug.
 *   - **Returns:** `bool` True on success, false on failure.
 *
 * - **`delete_shadow_term`**
 *   - **Description:** Creates a closure for deleting the associated shadow term when a post is deleted.
 *
 * - **`delete_shadow_term_association`**
 *   - **Description:** Removes the shadow term association from a post and deletes the term if no other posts are associated with it.
 *
 * - **`create_shadow_taxonomy_term`**
 *   - **Description:** Creates the shadow taxonomy term and associates it with the post.
 *
 * - **`post_type_already_in_sync`**
 *   - **Description:** Checks if the term and post are already in sync (i.e., have the same name and slug).
 *
 * - **`get_associated_term`**
 *   - **Description:** Retrieves the associated term of a given post.
 *
 * - **`get_associated_term_id`**
 *   - **Description:** Retrieves the associated term ID of a given post.
 *
 * - **`get_associated_posts`**
 *   - **Description:** Retrieves the associated posts of a given term.
 *
 * - **`get_related_posts`**
 *   - **Description:** Retrieves posts related to the given post via shadow term relations.
 *
 * **Notes:**
 *
 * - The class uses WordPress hooks to automatically manage the shadow terms as posts are created, updated, or deleted.
 * - The association between posts and terms is stored using post meta (`shadow_term_id`).
 * - The class handles cases where multiple posts may share the same shadow term, ensuring that terms are not deleted if still associated with other posts.
 * - The class avoids recursion issues by carefully managing updates and deletions.
 *
 * **Version History:**
 *
 * - **1.0.0:** Initial release.
 * - **1.0.1:** Bug fixes and performance improvements.
 * - **1.0.2:** Added conditional relationship functionality.
 * - **1.0.3:** Improved handling of term deletions and multiple associations.
 *
 * **License:**
 *
 * - Based on code from Human Made's Shadow Taxonomy plugin.
 * - Original source: https://github.com/humanmade/shadow-taxonomy
 * - Ensure compliance with the original license terms.
 */
class Shadow
{
    /**
     * Registers the shadow taxonomy relationship and hooks.
     *
     * @param string $post_type    Post Type slug.
     * @param string $taxonomy     Taxonomy slug.
     * @param array  $conditionals Optional conditional settings.
     */
    public static function create_relationship(string $post_type, string $taxonomy, array $conditionals = []): void
    {
        add_action('wp_insert_post', self::create_shadow_term($post_type, $taxonomy, $conditionals));
        add_action('set_object_terms', self::create_shadow_term($post_type, $taxonomy, $conditionals));
        add_action('wp_trash_post', self::delete_shadow_term($taxonomy));
        add_action('before_delete_post', self::delete_shadow_term($taxonomy));
    }

    /**
     * Creates a closure for handling the creation or updating of shadow terms.
     *
     * @param string $post_type    Post Type slug.
     * @param string $taxonomy     Taxonomy slug.
     * @param array  $conditionals Optional conditional settings.
     *
     * @return Closure
     */
    public static function create_shadow_term($post_type, $taxonomy, array $conditionals = []): Closure
    {
        return static function ($post_id) use ($post_type, $taxonomy, $conditionals) {
            $post = get_post($post_id);

            if (!$post || 'auto-draft' === $post->post_status) {
                return false;
            }

            // If 'post_type' condition is specified, use it to check
            if (!empty($conditionals['post_type'])) {
                if ($post->post_type !== $conditionals['post_type']) {
                    return false;
                }
            } else {
                if ($post->post_type !== $post_type) {
                    return false;
                }
            }

            $term = self::get_associated_term($post, $taxonomy);

            // Handle conditional logic
            if (!empty($conditionals['conditions']) && is_array($conditionals['conditions'])) {
                $operator = $conditionals['operator'] ?? 'AND';
                $condition_tests = [];

                foreach ($conditionals['conditions'] as $condition) {
                    $has_term = has_term($condition['values'], $condition['taxonomy'], $post);
                    $condition_tests[] = $has_term ? true : false;
                }

                $should_create = ($operator === 'AND') ? !in_array(false, $condition_tests, true) : in_array(true, $condition_tests, true);

                if (!$should_create) {
                    if ($term) {
                        // Check if other posts are associated before deleting
                        self::delete_shadow_term_association($post, $taxonomy, $term->term_id);
                    }
                    return false;
                }
            }

            if (!$term) {
                self::create_shadow_taxonomy_term($post, $taxonomy);
            } else {
                self::update_shadow_taxonomy_term($term, $post, $taxonomy);
            }
        };
    }

    /**
     * Updates the shadow term to match the associated post.
     *
     * @param WP_Term $term     The term to update.
     * @param WP_Post $post     The associated post.
     * @param string  $taxonomy The taxonomy slug.
     *
     * @return bool True on success, false on failure.
     */
    public static function update_shadow_taxonomy_term(WP_Term $term, WP_Post $post, string $taxonomy): bool
    {
        // Check if multiple posts are associated with this term
        $args = [
            'post_type'      => $post->post_type,
            'meta_query'     => [
                [
                    'key'     => 'shadow_term_id',
                    'value'   => $term->term_id,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 2, // Need to know if more than one post is associated
            'fields'         => 'ids',
        ];

        $associated_posts = get_posts($args);

        if (count($associated_posts) > 1) {
            // Multiple posts associated, do not update term
            return false;
        }

        if (self::post_type_already_in_sync($term, $post)) {
            return false;
        }

        $updated = wp_update_term(
            $term->term_id,
            $taxonomy,
            [
                'name' => $post->post_title,
                'slug' => $post->post_name,
            ]
        );

        return !is_wp_error($updated);
    }

    /**
     * Creates a closure for deleting the associated shadow term when a post is deleted.
     *
     * @param string $taxonomy Taxonomy slug.
     *
     * @return Closure
     */
    public static function delete_shadow_term(string $taxonomy): Closure
    {
        return function ($post_id) use ($taxonomy) {
            $post = get_post($post_id);
            $term_id = self::get_associated_term_id($post);

            if (!$term_id) {
                return false;
            }

            // Check if other posts are associated with this term
            $args = [
                'post_type'      => $post->post_type,
                'post__not_in'   => [$post_id],
                'meta_query'     => [
                    [
                        'key'     => 'shadow_term_id',
                        'value'   => $term_id,
                        'compare' => '=',
                    ],
                ],
                'posts_per_page' => 1, // Only need to know if at least one exists
                'fields'         => 'ids',
            ];

            $other_posts = get_posts($args);

            if (empty($other_posts)) {
                // No other posts associated, safe to delete term
                return wp_delete_term($term_id, $taxonomy);
            } else {
                // Remove the association from the deleted post
                delete_post_meta($post_id, 'shadow_term_id');
                return false;
            }
        };
    }

    /**
     * Removes the shadow term association from a post and deletes the term if no other associations exist.
     *
     * @param WP_Post $post     The post object.
     * @param string  $taxonomy The taxonomy slug.
     * @param int     $term_id  The term ID.
     */
    public static function delete_shadow_term_association(WP_Post $post, string $taxonomy, int $term_id): void
    {
        // Check if other posts are associated with this term
        $args = [
            'post_type'      => $post->post_type,
            'post__not_in'   => [$post->ID],
            'meta_query'     => [
                [
                    'key'     => 'shadow_term_id',
                    'value'   => $term_id,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ];

        $other_posts = get_posts($args);

        if (empty($other_posts)) {
            // No other posts associated, safe to delete term
            wp_delete_term($term_id, $taxonomy);
        }

        // Remove the association from the post
        delete_post_meta($post->ID, 'shadow_term_id');
    }

    /**
     * Creates the shadow taxonomy term and associates it with the post.
     *
     * @param WP_Post $post     The post object.
     * @param string  $taxonomy The taxonomy slug.
     *
     * @return array|false Array of term data on success, false on failure.
     */
    public static function create_shadow_taxonomy_term(WP_Post $post, string $taxonomy): bool|array
    {
        $new_term = wp_insert_term($post->post_title, $taxonomy, ['slug' => $post->post_name]);

        if (is_wp_error($new_term)) {
            if ($new_term->get_error_code() === 'term_exists') {
                // Term already exists, get the term ID
                $term_id = $new_term->get_error_data('term_exists');
            } else {
                return false;
            }
        } else {
            $term_id = $new_term['term_id'];
        }

        // Associate the term with the post
        update_post_meta($post->ID, 'shadow_term_id', $term_id);

        return ['term_id' => $term_id];
    }

    /**
     * Checks if the term and post are already in sync.
     *
     * @param WP_Term $term The term object.
     * @param WP_Post $post The post object.
     *
     * @return bool True if in sync, false otherwise.
     */
    public static function post_type_already_in_sync(WP_Term $term, WP_Post $post): bool
    {
        return ($term->name === $post->post_title && $term->slug === $post->post_name);
    }

    /**
     * Retrieves the associated term of a given post.
     *
     * @param WP_Post|int $post     The post object or ID.
     * @param string      $taxonomy The taxonomy slug.
     *
     * @return WP_Term|false The term object on success, false on failure.
     */
    public static function get_associated_term($post, string $taxonomy): WP_Term|bool
    {
        if (is_int($post)) {
            $post = get_post($post);
        }

        if (empty($post)) {
            return false;
        }

        $term_id = self::get_associated_term_id($post);
        return $term_id ? get_term_by('id', $term_id, $taxonomy) : false;
    }

    /**
     * Retrieves the associated term ID of a given post.
     *
     * @param WP_Post $post The post object.
     *
     * @return int|false The term ID on success, false on failure.
     */
    public static function get_associated_term_id(WP_Post $post): bool|int
    {
        return get_post_meta($post->ID, 'shadow_term_id', true) ?: false;
    }

    /**
     * Retrieves the associated posts of a given term.
     *
     * @param WP_Term $term The term object.
     *
     * @return WP_Post[]|false Array of post objects on success, false on failure.
     */
    public static function get_associated_posts(WP_Term $term): array|bool
    {

        $args = [
            'post_type'  => 'any',
            'meta_query' => [
                [
                    'key'     => 'shadow_term_id',
                    'value'   => $term->term_id,
                    'compare' => '=',
                ],
            ],
        ];

        $posts = get_posts($args);
        return !empty($posts) ? $posts : false;
    }

    /**
     * Retrieves associated posts for a given post ID via shadow term relations.
     *
     * @param int    $post_id   The ID of the post.
     * @param string $taxonomy  The shadow taxonomy name.
     * @param string $post_type The associated post type.
     *
     * @return WP_Post[]|false Array of post objects on success, false on failure.
     */
    public static function get_related_posts(int $post_id, string $taxonomy, string $post_type): array|bool
    {
        $terms = get_the_terms($post_id, $taxonomy);

        if (!empty($terms) && !is_wp_error($terms)) {
            $posts = [];
            foreach ($terms as $term) {
                $associated_posts = self::get_associated_posts($term);
                if ($associated_posts) {
                    foreach ($associated_posts as $associated_post) {
                        if ($associated_post->post_type === $post_type) {
                            $posts[] = $associated_post;
                        }
                    }
                }
            }
            return !empty($posts) ? $posts : false;
        }
        return false;
    }
}
