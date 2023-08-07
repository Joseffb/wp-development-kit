<?php
// Based off of https://github.com/humanmade/shadow-taxonomy/blob/cli_add_meta_query_params/includes/shadow-taxonomy.php

namespace WDK;
use Closure;

/**
 * Class Field - Field input generator and tools
 * @package WDK\Library\Field
 */
class Shadow {

    /**
     * public static function registers a post to taxonomy relationship. Henceforth known as a shadow taxonomy. This function
     * hooks into the WordPress Plugins API and registers multiple hooks. These hooks ensure that any changes
     * made on the post type side or taxonomy side of a given relationship will stay in sync.
     *
     * @param string $post_type Post Type slug.
     * @param string $taxonomy Taxonomy Slug.
     */
    public static function CreateRelationship(string $post_type, string $taxonomy, array $conditionals = []): void
    {
        add_action('wp_insert_post', self::CreateShadowTerm($post_type, $taxonomy, $conditionals));
        add_action('set_object_terms', self::CreateShadowTerm($post_type, $taxonomy, $conditionals));
        add_action('before_delete_post', self::CreateShadowTerm($post_type, $taxonomy, $conditionals));
    }

    /**
     * public static function creates a closure for the wp_insert_post hook, which handles creating an
     * associated taxonomy term.
     * @param string $post_type Post Type Slug
     * @param string $taxonomy Taxonomy Slug
     * @param array $conditionals Conditional taxonomy settings
     * @usage create_relationship('nomination', 'primary', [
     *      'operator'=>"AND",
     *      'conditions'=>[
     *          [
     *              'taxonomy' => 'candidate_type',
     *              'values'=>['primary', 'alternate']
     *          ],
     *          [
     *              'taxonomy' => 'nomination_status',
     *              'values'=>['accepted']
     *          ]
     *      ]
     * ]); will only link a taxonomy if both candidate_type and nomination_status match their corresponding values
     * @return Closure
     */

    public static function CreateShadowTerm(string $post_type, string $taxonomy, array $conditionals = []): Closure
    {
        return static function ($post_id) use ($post_type, $taxonomy, $conditionals) {
            $term = self::GetAssociatedTerm($post_id, $taxonomy);
            $post = get_post($post_id);
            $condition_tests = [];

            if (!empty($conditionals) && is_array($conditionals)) {
                $operator = !empty($conditionals['operator']) ? $conditionals['operator'] : "AND";
                foreach ($conditionals['conditions'] as $condition) {
                    if (!has_term($condition['values'], $condition['taxonomy'], $post)) {
                        $condition_tests[] = false;
                    } else {
                        $condition_tests[] = true;
                    }
                }

                //Any value needs to be false;
                if (($operator === "AND") && in_array(false, $condition_tests, true)) {
                    if ($term = self::GetAssociatedTerm($post_id, $taxonomy)) {
                        wp_delete_term($term->term_id, $taxonomy);
                    }

                    return false;
                }

                //All values need to be false
                if (($operator === "OR") && in_array(true, $condition_tests, true) === false) {
                    if ($term = self::GetAssociatedTerm($post_id, $taxonomy)) {
                        wp_delete_term($term->term_id, $taxonomy);
                    }
                    return false;
                }


            }

            if ($post->post_type !== $post_type) {
                return false;
            }

            if ('auto-draft' === $post->post_status) {
                return false;
            }

            if (!$term) {
                self::CreateShadowTaxonomyTerm($post_id, $post, $taxonomy);
            } else {
                $post = self::GetAssociatedPost($term);

                if (empty($post)) {
                    return false;
                }

                if (self::PostTypeAlreadyInSync($term, $post)) {
                    return false;
                }

                wp_update_term(
                    $term->term_id,
                    $taxonomy,
                    [
                        'name' => $post->post_title,
                        'slug' => $post->post_name,
                    ]
                );
            }
        };

    }

    /**
     * public static function creates a closure for the before_delete_post hook, which handles deleting an
     * associated taxonomy term.
     *
     * @param string $taxonomy Taxonomy Slug.
     *
     * @return Closure
     */
    public static function DeleteShadowTerm(string $taxonomy): Closure
    {
        return function ($post_id) use ($taxonomy) {
            $term_id = self::GetAssociatedTermID(get_post($post_id));

            if (!$term_id) {
                return false;
            }

            return wp_delete_term($term_id, $taxonomy);
        };
    }

    /**
     * public static function responsible for actually creating the shadow term and set the term meta to
     * create the association.
     *
     * @param int $post_id Post ID Number.
     * @param object $post The WP Post Object.
     * @param string $taxonomy Taxonomy Term Name.
     *
     * @return array | bool array Term ID if created or false if an error occurred.
     */
    public static function CreateShadowTaxonomyTerm(int $post_id, object $post, string $taxonomy)
    {
        $new_term = wp_insert_term($post->post_title, $taxonomy, ['slug' => $post->post_name]);

        if (is_wp_error($new_term)) {
            return false;
        }

        update_term_meta($new_term['term_id'], 'shadow_post_id', $post_id);
        update_post_meta($post_id, 'shadow_term_id', $new_term['term_id']);

        return $new_term;
    }

    /**
     * public static function checks to see if the current term and its associated post have the same
     * title and slug. While we generally rely on term and post meta to track association,
     * its important that these two value stay synced.
     *
     * @param object $term The Term Object.
     * @param object $post The $_POST array.
     *
     * @return bool Return true if a match is found, or false if no match is found.
     */
    public static function PostTypeAlreadyInSync(object $term, object $post): bool
    {
        if (isset($term->slug, $post->post_name)) {
            if ($term->name === $post->post_title && $term->slug === $post->post_name) {
                return true;
            }
        } else if ($term->name === $post->post_title) {
            return true;
        }

        return false;
    }

    /**
     * public static function finds the associated shadow post for a given term slug. This public static function is required due
     * to some possible recursion issues if we only check for posts by ID.
     *
     * @param object $term The Term Object.
     * @param string $post_type The Post Type Slug.
     *
     * @return bool|object Returns false if no post is found, or the Post Object if one is found.
     */
    public static function GetRelatedPostBySlug(object $term, string $post_type)
    {
        $post = new \WP_Query([
            'post_type' => $post_type,
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'name' => $term->slug,
            'no_found_rows' => true,
        ]);

        if (empty($post->posts) || is_wp_error($post)) {
            return false;
        }

        return $post->posts[0];
    }

    /**
     * public static function gets the associated shadow post of a given term object.
     *
     * @param object $term WP Term Object.
     *
     * @return bool | int return the post_id or false if no associated post is found.
     */
    public static function GetAssociatedPostID(object $term)
    {
        return get_term_meta($term->term_id, 'shadow_post_id', true);
    }

    /**
     * Find the shadow or associted post to the input taxonomy term.
     *
     * @param object $term WP Term Objct.
     *
     * @return bool|\WP_Post Returns the associated post object or false if no post is found.
     */
    public static function GetAssociatedPost(object $term)
    {
        return self::GetAssociatedSinglePost($term);
    }

    /**
     * Find the shadow or associted post to the input taxonomy term.
     *
     * @param object $term WP Term Objct.
     *
     * @return bool|\WP_Post Returns the associated post object or false if no post is found.
     */
    public static function GetAssociatedSinglePost(object $term)
    {
        if (empty($term)) {
            return false;
        }

        $post_id = self::GetAssociatedPostID($term);

        if (empty($post_id)) {
            return false;
        }

        return get_post($post_id);
    }

    /**
     * @param object $term
     * @return false|int[]|\WP_Post[]
     */
    public static function GetAssociatedMultiplePosts(object $term)
    {

        if (empty($term)) {
            return false;
        }
        $args = [
            'post_type' => 'nomination',
            'tax_query' => [
                [
                    'taxonomy' => 'nomination_candidate_tax',
                    'terms' => $term->term_id,
                ],
            ],
            // Rest of your arguments
        ];
        $posts = get_posts( $args );
        return empty($posts)?false:$posts;
    }

    /**
     * public static function gets the associated shadow term of a given post object
     *
     * @param object | int $post WP Post Object.
     *
     * @return bool | int returns the term_id or false if no associated term was found.
     */
    public static function GetAssociatedTermID($post)
    {
        $post_id = $post->ID;
        if(is_numeric($post)) {
            $post_id = $post;
        }

        return get_post_meta($post_id, 'shadow_term_id', true);
    }

    /**
     * public static function gets the associated Term object for a given input Post Object.
     *
     * @param object|int $post WP Post Object or Post ID.
     * @param string $taxonomy Taxonomy Name.
     *
     * @return bool|object Returns the associated term object or false if no term is found.
     */
    public static function GetAssociatedTerm($post, string $taxonomy)
    {

        if (is_int($post)) {
            $post = get_post($post);
        }

        if (empty($post)) {
            return false;
        }

        $term_id = self::GetAssociatedTermID($post);
        return get_term_by('id', $term_id, $taxonomy);
    }

    /**
     * public static function will get all related posts for a given post ID. The function
     * essentially converts all the attached shadow term relations into the actual associated
     * posts.
     *
     * @param int $post_id The ID of the post.
     * @param string $taxonomy The name of the shadow taxonomy.
     * @param string $cpt The name of the associated post type.
     *
     * @return array|bool Returns false or an are of post Objects if any are found.
     */
    public static function GetThePosts(int $post_id, string $taxonomy, string $cpt)
    {
        $terms = get_the_terms($post_id, $taxonomy);

        if (!empty($terms)) {
            return array_map(static function ($term) use ($cpt) {
                $post = self::GetAssociatedPost($term);
                if (!empty($post)) {
                    return $post;
                }
                return false;
            }, $terms);
        }
        return false;
    }

    /**
     * Used for pagination when a shadow tax is used multiple times in same relationship.
     * @param $taxName
     * @return string
     */

    public static function GetNextNumberedShadowTaxName($taxName): string
    {
        //32 char limit on tax names so we just take 23 characers to allow us to add '_99_tax' at end.
        $array = explode("_", $taxName);
        if (last($array) === "tax") {
            array_pop($array);
        }
        $lastNumber = (int)last($array);
        if ($lastNumber > 0) {
            array_pop($array);
        }
        $machine_tax_name = strtolower(substr(implode("_", $array), 0, 23));
        $machine_tax_name = $lastNumber ? $machine_tax_name . "_" . $lastNumber . "_tax" : $machine_tax_name . "_1_tax";
        if (taxonomy_exists($machine_tax_name)) {

            $array = explode("_", $machine_tax_name);
            if (last($array) === "tax") {
                array_pop($array);
            }
            $lastNumber = (int)last($array);
            array_pop($array);
            $machine_tax_name = self::getNextNumberedShadowTaxName(implode("_", $array) . "_" . ($lastNumber + 1) . "_tax");
        }
        return $machine_tax_name;
    }
}