<?php

namespace WDK;
/**
 * The `Hive` class is an object-oriented utility class for querying WordPress posts, and provides a convenient way to access
 * post data and related metadata and taxonomies. The class now leverages magic methods to handle different post types dynamically.
 *
 * Usage:
 * - Get a post by ID: `$post = Hive::post(123);` Here, the Hive is initiated for a post type called 'post' with an ID of 123
 * - Get a post by name: `$post = Hive::post('example');` Here, the Hive is initiated for a post type called 'post' with a title of 'example'
 * - Get a post by name: `$post = Hive::contest('example');` Here, the Hive is initiated for a post type called 'contest' with a title of 'example'. This is not the same as the previous.
 * - Get a post by shadow taxonomy: `$post = Hive::post_type(['shadow_term_id' => 1234]);` Here, the Hive is initiated for a specified post type that has a shadow term with the ID 1234.
 * - Get a post with specific meta value: `$post = Hive::post_type(['meta' => ['meta_key' => 'meta_value']]);` Here, the Hive is initiated for a specified post type that has a specific meta value.
 *
 * Once you have a post, you can access its data using the following properties and methods:
 * $post['post']` : This returns a custom WP_Post object for the post
 * $post['post']->update(['post_title' => 'something', 'post_parent' => 23])` : This method updates multiple fields for the post in the WordPress database. In this example, it updates the title and parent of the post.
 * $post['post']->post_title = 'something'` : This property updates the post title in the WordPress database. The custom object implementation allows for directly setting a property on the WP_Post object, such as post_title in this example, and it automatically updates the database.
 * $post['post']->delete()` : This will delete this post.
 * $post['post']->has_post()` : Returns false if post object is empty.
 *
 * $post['comments'] : An object for managing post comments. An array of Comment objects representing the comments associated with the post.
 * Use `$post['comments']->getAll()` to retrieve all comments associated with the post.
 *
 * Each Comment object has the following methods:
 * - `update($args)`: Update the comment with the specified arguments.
 * -  You can also access and modify the comment content using the `$post['comments'][$index]->field_name (or shortcut $post['comments'][$index]->comment for comment_content)` property.
 *
 * - `trash()`: Move the comment to the trash.
 * - `untrash()`: Restore the comment from the trash.
 * - `approve()`: Approve the comment.
 * - `unapprove()`: Unapprove the comment.
 * - `delete()`: Permanently delete the comment.
 *
 * $post['meta']` : an associative array of post meta values, where the key is the meta field's name, and the value is a custom object that manages the value.
 * $post['meta']['meta_field']->update($args[])` : Update the meta field value for the post. The arguments (args[]) are specific to the update operation for that field. This should not be confused with updating the whole array at once.
 * $post['meta']['meta_field']->delete()` : Delete the meta field from the post. This does not take any arguments as it simply removes the meta field.
 *
 * $post['taxonomy']`: an array of all post taxonomy values
 * $post['taxonomy']['taxonomy_name']`: an array of post taxonomy values specific to the taxonomy called 'taxonomy_name'
 * $post['taxonomy']['taxonomy_name']['is_shadow']`: boolean determining if the selected taxonomy is a shadow taxonomy
 * $post['taxonomy']['taxonomy_name']->update($term_value, $optional_args)`: updates the term for this taxonomy for that post. Adds term if missing. The arguments $term_value is the name or ID of the term to be added or updated, and $optional_args is an associative array of arguments to pass to wp_insert_term() if not provided default will be used.
 * $post['taxonomy']['taxonomy_name']->delete()`: deletes the term for this taxonomy from that post.
 * $post['taxonomy']['taxonomy_name']->shadow()`: Gets associated shadow post. null if not applicable.
 *
 * Finally, the following methods are available for media and relationship management:
 * $post['media']->images()` : get all associated images
 * $post['media']->video()` : get all associated video
 * $post['media']->audio()` : get all associated audio
 * $post['media']->application()` : get all associated misc mime types
 * $post['media']->pdf()` : get all associated pdf files
 * $post['media']->zip()` : get all associated zip files
 * $post['media']->html()` : get all associated html files (uploaded to media manager)
 * $post['media']->js()` : get all associated js files (uploaded to media manager)
 * $post['media']->css()` : get all associated css files (uploaded to media manager)
 * $post['relationships']->children()` : get all child posts
 * $post['relationships']->parent()` : get parent post (if applicable)
 *
 * The class uses the magic __callStatic method to allow dynamic calls to different post types. The called post type is internally
 * resolved to form the appropriate query.
 *
 * The functionality has been extended to allow for update and delete operations, as well as for managing media and relationships.
 * Moreover, the class now supports loading shadow taxonomy and post data.
 * Examples:
 *
 * // Get a post by ID
 * $post = Hive::post(42);
 * $title = $post['post']->post_title;
 *
 * // Get a post by title
 * $post = Hive::post('example');
 * !empty($post); //result true
 * $post_title = $post['post']->ID;
 *
 * //call a post type named after PHP reserved word 'abstract'
 * $post = Hive::rw_abstract(42);
 * $title = $post['post']->post_title;
 *
 * // Get a post by title
 * $contest = Hive::contest('example');
 * !empty($contest) //result true
 * $contest_title = $post['post']->ID;
 *
 * $post_title === $contest_title //result is false -- not the same records even with same title.
 *
 * $a = $post['comments']->getAll();
 * $a[0]->comment //get the comment body.
 * $a[0]->comment = 'something' //update the comment body.
 * $a[0]->comment_status = 'spam' //set status to spam.
 * $a[0]->approve() //approve the comment body.
 * $a[0]->trash() //trash the comment body.
 *
 * // Update multiple fields for a post
 * $post['post']->update(['post_title' => 'New Title', 'post_parent' => 23]);
 *
 * // Update a single field for a post
 * $post['post']->post_title = 'New Title';
 * //Note: You cannot update post ID
 *
 * // Delete a post
 * $post['post']->delete();
 *
 * // Update a meta field value for a post
 * $post = Hive::post(42);
 * $post['meta']['custom_field']->update('new_value', $optional_args);
 *
 * // Delete a meta field from a post
 * $post = Hive::post(42);
 * $post['meta']['custom_field']->delete();
 *
 * // Update a taxonomy term for a post
 * $post = Hive::post(42);
 * $post['taxonomy']['category']->update('New Category', ['description' => 'Product category']);
 *
 * // Delete a taxonomy term from a post
 * $post = Hive::post(42);
 * $post['taxonomy']['category']->delete();
 *
 * // Get all associated images for a post
 * $post = Hive::post(42);
 * $images = $post['media']->images();
 *
 * // Get all child posts
 * $post = Hive::post(42);
 * $children = $post['relationships']->children();
 *
 * // Get the parent post
 * $post = Hive::post(42);
 * $parent = $post['relationships']->parent();
 */
class Hive
{
    private array $query_args = [];
    private ?\WP_Post $post = null;

    /**
     * @param $post_type string
     * @param string|array|null $arguments array
     * @return object|null
     */
    public static function __callStatic(string $post_type, $arguments = null)
    {
        //used for PHP reserved words
        $reserved_prefix = $post_type[0].$post_type[1].$post_type[2];
        if($reserved_prefix === 'rw_') {
            $post_type = str_replace($reserved_prefix,"",$post_type);
        }

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
        $slug = get_page_by_path(sanitize_title($post_name), OBJECT, $this->query_args['post_type']);
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
        $taxonomy_data['data'] = $taxonomy_data;
        foreach ($taxonomies as $taxonomy) {
            $is_shadow = str_contains($taxonomy, '_tax') || str_contains($taxonomy, '_shadow-tax');
            $term = get_the_terms($post->ID, $taxonomy)[0];
            $taxonomy_data[$taxonomy] = new class($term) {
                private \WP_Term $taxonomy;
                private ?\WP_Post $post = null;
                private bool $isLoaded = false;
                public bool $is_shadow = false;

                public function __construct($taxonomy)
                {
                    $this->taxonomy = $taxonomy;
                    $this->is_shadow = (str_contains($this->taxonomy->name, '_tax') || str_contains($this->taxonomy->name, '_shadow-tax'));
                }

                public function load()
                {
                    if (!$this->isLoaded && $this->is_shadow) {
                        $this->isLoaded = true;
                        return $this->post = Shadow::GetAssociatedPost($this->taxonomy);

                    }
                    return null;
                }

                public function shadow()
                {
                    return $this->load();
                }

                public function update($value, $args = [])
                {
                    $term_id = term_exists($value, $this->taxonomy);

                    if (!$term_id) {
                        $term = wp_insert_term($value, $this->taxonomy->taxonomy, $args);
                        $term_id = $term['term_id'];
                    }

                    return wp_set_object_terms($this->post->ID, $term_id, $this->taxonomy->taxonomy);
                }

                public function delete()
                {
                    $term_id = term_exists($this->taxonomy->name, $this->taxonomy->taxonomy);

                    if (!$term_id) {
                        return false;
                    }

                    return wp_remove_object_terms($this->post->ID, $term_id, $this->taxonomy->taxonomy);
                }
            };
            $taxonomy_data[$taxonomy]['shadow'] = $is_shadow;

        }


        return (object)[
            'post' => new class($post) {
                private \WP_Post $post;

                public function __construct($post)
                {
                    $this->post = $post;
                }

                public function __get($name)
                {
                    return $this->post->$name;

                }

                public function has_post(): bool
                {
                    return !empty($this->post->post_title);
                }

                public function get_post(): \WP_Post
                {
                    return $this->post;
                }

                public function __set($name, $value)
                {
                    if (isset($this->post->$name) && strtoupper($name) !== 'ID') {
                        // Can't update the record ID, which would break the db.
                        $this->post->$name = $value;
                        $this->update([$name => $value]);
                    }
                }

                public function __isset($name)
                {
                    return isset($this->post->$name);
                }

                public function update($args)
                {
                    $args['ID'] = $this->post->ID;
                    return wp_update_post($args);
                }

                public function delete()
                {
                    return wp_delete_post($this->post->ID, true);
                }
            },
            'comments' => new class($post) {
                private \WP_Post $post;

                public function __construct($post)
                {
                    $this->post = $post;
                }

                public function getAll($status = 'approve'): array
                {
                    $comments = get_comments([
                        'post_id' => $this->post->ID,
                        'status' => $status // Include only approved comments
                    ]);

                    $commentObjects = [];
                    foreach ($comments??[] as $comment) {
                        $commentObjects[] = new class($comment) {
                            private \WP_Comment $comment;

                            public function __construct($comment)
                            {
                                $this->comment = $comment;
                            }

                            public function update($args)
                            {
                                $args['comment_ID'] = $this->comment->comment_ID;
                                return wp_update_comment($args);
                            }

                            public function trash(): bool
                            {
                                return wp_trash_comment($this->comment->comment_ID);
                            }

                            public function untrash(): bool
                            {
                                return wp_untrash_comment($this->comment->comment_ID);
                            }

                            public function approve()
                            {
                                $this->comment->comment_approved = 1;
                                return wp_update_comment((array)$this->comment);
                            }

                            public function unapprove()
                            {
                                $this->comment->comment_approved = 0;
                                return wp_update_comment((array)$this->comment);
                            }

                            public function delete(): bool
                            {
                                return wp_delete_comment($this->comment->comment_ID, true);
                            }

                            public function __get($name)
                            {
                                if ($name === 'comment') {
                                    return $this->comment->comment_content;
                                }
                                return $this->comment->$name;
                            }

                            public function __set($name, $value)
                            {
                                if ($name !== 'comment_ID') {
                                    if ($name === 'comment') {
                                        $this->comment->comment_content = $value;
                                        $this->update(['comment_content' => $value]);
                                    } elseif (isset($this->comment->$name)) {
                                        $this->comment->$name = $value;
                                        $this->update([$name => $value]);
                                    }
                                }
                            }

                            public function __isset($name)
                            {
                                return isset($this->comment->$name);
                            }
                        };
                    }

                    return $commentObjects;
                }

                public function count()
                {
                    return wp_count_comments($this->post->ID)->total_comments;
                }
            },

            'meta' => new class($post) {
                private \WP_Post $post;
                private array $data;

                public function __construct($post)
                {
                    $this->post = $post;
                    $this->data = get_post_meta($post->ID);
                }

                public function __get($field_name)
                {
                    $field_value = $this->data[$field_name] ?? null;
                    return new class($field_name, $this->post, $field_value) {
                        private \WP_Post $post;
                        private $value;
                        private string $field_name;

                        public function __construct($field_name, $post, $value)
                        {
                            $this->post = $post;
                            $this->field_name = $field_name;
                            $this->value = $value;
                        }

                        public function __toString()
                        {
                            return $this->value;
                        }

                        public function update($value, $args = null)
                        {
                            $this->value = $value;
                            return update_post_meta($this->post->ID, $this->field_name, $value, $args);
                        }

                        public function delete(): bool
                        {
                            return delete_post_meta($this->post->ID, $this->field_name);
                        }
                    };
                }

                public function __set($field_name, $value)
                {
                    $this->data[$field_name] = $value;
                    return update_post_meta($this->post->ID, $field_name, $value);
                }

                public function __isset($field_name)
                {
                    return isset($this->data[$field_name]);
                }

                public function getAll()
                {
                    return $this->data;
                }
            },
            'taxonomy' => $taxonomy_data,
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

                /**
                 * Wildcard version of get_by_mime_type. requires the front half of the entire mime type returning all
                 * subtypes in that hierarchy. i.e. 'application' returns all application types.
                 * @param $mime_type
                 * @return array
                 */
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

                /**
                 * Returns specific mime type as defined by type/subtype. i.e. 'image/jpg' will only return all jpegs.
                 * @param $mime_type
                 * @return array
                 */
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
            }
        ];
    }
}