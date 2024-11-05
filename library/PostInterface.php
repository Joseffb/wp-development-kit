<?php

namespace WDK;

use ReturnTypeWillChange;
use WP_Post;
use WP_Error;
use WP_Query;
use WP_Comment;

/**
 * The `PostInterface` class is an object-oriented utility for querying WordPress posts.
 * It provides a convenient way to access post data, metadata, taxonomies, and related operations.
 * The class leverages magic methods to handle different post types dynamically.
 *
 * **Usage:**
 * - **Get a post by ID:**
 *   ```php
 *   $post = PostInterface::post(123);
 *   ```
 *   Initializes for post type 'post' with ID 123.
 *
 * - **Get a post by name:**
 *   ```php
 *   $post = PostInterface::post('example');
 *   ```
 *   Initializes for post type 'post' with title 'example'.
 *
 * - **Get a post by shadow taxonomy:**
 *   ```php
 *   $post = PostInterface::custom_type(['shadow_term_id' => 1234]);
 *   ```
 *   Initializes for a custom post type that has a shadow term with ID 1234.
 *
 * - **Get a post with specific meta value:**
 *   ```php
 *   $post = PostInterface::custom_type(['meta' => ['meta_key' => 'meta_value']]);
 *   ```
 *   Initializes for a custom post type that has a specific meta value.
 *
 * **Post Operations:**
 * - **Access post data:**
 *   ```php
 *   $post->post; // Returns a custom WP_Post object
 *   ```
 *
 * - **Update post fields:**
 *   ```php
 *   $post->post->update(['post_title' => 'New Title', 'post_parent' => 23]);
 *   $post->post->post_title = 'New Title'; // Automatically updates the database
 *   ```
 *
 * - **Delete a post:**
 *   ```php
 *   $post->post->delete();
 *   ```
 *
 * - **Check if post exists:**
 *   ```php
 *   $post->post->have_post(); // Returns false if post object is empty
 *   ```
 *
 * **Comments Operations:**
 * - **Retrieve comments:**
 *   ```php
 *   $comments = $post->comments->getAll();
 *   ```
 *
 * - **Manage a comment:**
 *   ```php
 *   $comment = $comments[0];
 *   $comment->comment; // Get comment content
 *   $comment->comment = 'Updated content'; // Update comment content
 *   $comment->approve(); // Approve the comment
 *   $comment->trash(); // Trash the comment
 *   ```
 *
 * - **Create a new comment:**
 *   ```php
 *   $new_comment_id = $post->comments->create([
 *       'comment_content' => 'This is a new comment.',
 *       'comment_author'  => 'Author Name',
 *       'comment_author_email' => 'author@example.com',
 *   ]);
 *   ```
 *
 * **Meta Operations:**
 * - **Access meta fields:**
 *   ```php
 *   $meta_value = $post->meta->field_name;
 *   ```
 *
 * - **Update meta fields:**
 *   ```php
 *   $post->meta->update('meta_field', 'new_value');
 *   $post->meta->field_name = 'new_value'; // Automatically updates the database
 *   ```
 *
 * - **Delete meta fields:**
 *   ```php
 *   $post->meta->delete('meta_field');
 *   ```
 *
 * **Taxonomy Operations:**
 * - **Access taxonomies:**
 *   ```php
 *   $taxonomy_terms = $post->taxonomy->category;
 *   ```
 *
 * - **Access term meta:**
 *   ```php
 *   $term_meta_value = $post->taxonomy->category[0]->meta->field_name;
 *   ```
 *
 * - **Update taxonomy terms:**
 *   ```php
 *   $post->taxonomy->category->update('Term Name', ['description' => 'Term Description']);
 *   ```
 *
 * - **Delete taxonomy terms:**
 *   ```php
 *   $post->taxonomy->category->delete('Term Name');
 *   ```
 *
 * **Media and Relationships:**
 * - **Get associated media:**
 *   ```php
 *   $images = $post->media->image();
 *   $videos = $post->media->video();
 *   ```
 *
 * - **Get and set featured image:**
 *   ```php
 *   $featured_image = $post->media->get_featured_image();
 *   $post->media->set_featured_image($attachment_id);
 *   ```
 *
 * - **Get attachment metadata:**
 *   ```php
 *   $attachment_metadata = $post->media->image()[0]->metadata;
 *   ```
 *
 * - **Get child and parent posts:**
 *   ```php
 *   $children = $post->relationships->children();
 *   $parent = $post->relationships->parent();
 *   ```
 *
 * **Examples:**
 *
 * - **Get a post by ID:**
 *   ```php
 *   $post = PostInterface::post(42);
 *   $title = $post->post->post_title;
 *   ```
 *
 * - **Update a taxonomy term:**
 *   ```php
 *   $post->taxonomy->category->update('New Category');
 *   ```
 *
 * - **Get associated images:**
 *   ```php
 *   $images = $post->media->image();
 *   ```
 */
class PostInterface
{
    private ?array $query_args;
    private ?WP_Post $post = null;
    private string $post_type;

    /**
     * Destructor to perform cleanup.
     */
    public function __destruct()
    {
        $this->query_args = null;
        $this->post = null;
    }

    /**
     * Magic static method to handle dynamic post type calls.
     *
     * @param string $post_type Post type slug. Prefix with 'rw_' for reserved PHP words.
     * @param mixed  $arguments Arguments for querying the post.
     *
     * @return object|null
     */
    public static function __callStatic(string $post_type, $arguments = null): ?object
    {
        return self::_load($post_type, $arguments);
    }

    /**
     * Internal method to load the post interface.
     *
     * @param string $post_type Post type slug.
     * @param mixed  $arguments Arguments for querying the post.
     *
     * @return object|null
     */
    public static function _load(string $post_type = 'post', $arguments = null): ?object
    {
        if (empty($arguments)) {
            return null;
        }

        // Handle reserved PHP words with 'rw_' prefix
        if (str_starts_with($post_type, 'rw_')) {
            $post_type = substr($post_type, 3);
        }

        if (!post_type_exists($post_type)) {
            return null;
        }

        $PostInterface = new self($post_type);
        $args = $arguments[0] ?? $arguments;

        if ($args instanceof WP_Post) {
            $PostInterface->post = $args;
            return $PostInterface->get();
        }

        if (is_array($args)) {
            $PostInterface->query_args = array_merge($PostInterface->query_args, $args);

            if (isset($args['shadow_term_id'])) {
                $PostInterface->shadow($args['shadow_term_id']);
                unset($PostInterface->query_args['shadow_term_id']);
            } elseif (!empty($args['meta']) && is_array($args['meta'])) {
                $PostInterface->meta($args['meta']);
            }
        } else {
            if (is_int($args) || is_numeric($args)) {
                $PostInterface->id($args);
            } elseif (is_string($args)) {
                $PostInterface->name($args);
            }
        }

        return $PostInterface->get();
    }

    /**
     * Constructor to initialize the post type and default query arguments.
     *
     * @param string $post_type The post type slug.
     */
    public function __construct(string $post_type)
    {
        $this->post_type = $post_type;
        $this->query_args = [
            'post_type'      => $post_type,
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ];
    }

    /**
     * Creates a new post of the specified type.
     *
     * @param string $postType               The post type.
     * @param array  $postArr                The data for the post as an array.
     * @param bool   $override_on_missing_pt Override to force usage of a non-existent post-type.
     *
     * @return object|WP_Error|null The PostInterface instance for the new post or null on failure.
     */
    public static function createPost(string $postType, array $postArr, bool $override_on_missing_pt = false)
    {
        // Check if the post type exists or if override is allowed
        if (!post_type_exists($postType) && !$override_on_missing_pt) {
            return new WP_Error('invalid_post_type', "Post type '{$postType}' does not exist");
        }

        $postArr['post_type'] = $postType;
        $postId = wp_insert_post($postArr, true);

        // Handle error from wp_insert_post
        if (is_wp_error($postId)) {
            return $postId;  // Propagate the WP_Error
        }

        return self::{$postType}($postId);
    }

    /**
     * Set the post ID for querying.
     *
     * @param int $post_id The post ID.
     *
     * @return PostInterface
     */
    public function id(int $post_id): PostInterface
    {
        $this->query_args['p'] = $post_id;
        return $this;
    }

    /**
     * Set the post name (slug) for querying.
     *
     * @param string $post_name The post name.
     *
     * @return PostInterface
     */
    public function name(string $post_name): PostInterface
    {
        $slug = get_page_by_path(sanitize_title($post_name), OBJECT, $this->query_args['post_type']);
        if ($slug) {
            $this->post = $slug;
        } else {
            $this->query_args['title'] = $post_name;
        }

        return $this;
    }

    /**
     * Set the post based on a shadow term ID.
     *
     * @param int $term_id The term ID.
     *
     * @return PostInterface|null
     */
    public function shadow(int $term_id): ?PostInterface
    {
        // Assuming you have a function to get the associated post ID from a term ID
        $post_id = (int)get_term_meta($term_id, 'shadow_post_id', true);
        if (!$post_id) {
            return null;
        }
        $this->query_args['p'] = $post_id;
        return $this;
    }

    /**
     * Add meta query parameters.
     *
     * @param array $meta_query Associative array of meta queries.
     *
     * @return PostInterface
     */
    public function meta(array $meta_query): PostInterface
    {
        $this->query_args['meta_query'] = [];

        foreach ($meta_query as $key => $value) {
            $this->query_args['meta_query'][] = [
                'key'     => $key,
                'value'   => $value,
                'compare' => '=', // Adjust comparison operator if necessary
            ];
        }
        unset($this->query_args['meta']);
        return $this;
    }

    /**
     * Retrieve the post interface object.
     *
     * @return object|null
     */
    public function get(): ?object
    {
        if (!$this->post) {
            if (class_exists('\WDK\Search') && method_exists('\WDK\Search', 'search')) {
                $provider = $this->query_args['search_provider'] ?? null;
                $search = new \WDK\Search($provider);
                if (method_exists($search, 'PostInterface_get')) {
                    return $search->PostInterface_get($this->query_args);
                }
                $post = $search->search("", $this->query_args)->posts[0];
            } else {
                $query = new WP_Query($this->query_args);
                if (!$query->have_posts()) {
                    return null;
                }
                $post = $query->get_posts()[0];
            }
        } else {
            $post = $this->post;
        }
        $this->post = $post;

        return (object)[
            'post' => new class($post) {
                private WP_Post $post;

                public function __construct($post)
                {
                    $this->post = $post;
                }

                public function __get($name)
                {
                    return $this->post->$name;
                }

                public function __set($name, $value)
                {
                    if (isset($this->post->$name) && strtoupper($name) !== 'ID') {
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

                public function have_post()
                {
                    return (bool)$this->post;
                }

                public function delete()
                {
                    return wp_delete_post($this->post->ID, true);
                }
            },
            'comments' => new CommentsHandler($post),
            'meta'          => new MetaHandler($post),
            'taxonomy'      => new TaxonomyHandler($post),
            'media'         => new MediaHandler($post),
            'relationships' => new RelationshipHandler($post),
        ];
    }
}

/**
 * Class MetaHandler
 *
 * Handles meta-related operations for a post, including custom fields.
 */
class MetaHandler
{
    private int $post_id;
    private array $data;

    public function __construct($post)
    {
        $this->post_id = $post->ID;
        $this->data = get_post_meta($post->ID);
    }

    public function __get($field_name)
    {
        $field_value = $this->data[$field_name][0] ?? null;

        if ($field_value !== null) {
            // Unserialize if necessary
            if (is_serialized($field_value)) {
                $field_value = maybe_unserialize($field_value);
            }
            return $field_value;
        }
        return null;
    }

    public function __set($field_name, $value)
    {
        $this->data[$field_name] = [$value];
        return update_post_meta($this->post_id, $field_name, $value);
    }

    public function __isset($field_name)
    {
        return isset($this->data[$field_name]);
    }

    /**
     * Update a meta field.
     *
     * @param string $key   The meta key.
     * @param mixed  $value The meta value.
     *
     * @return int|bool
     */
    public function update(string $key, $value)
    {
        $this->data[$key] = [$value];
        return update_post_meta($this->post_id, $key, $value);
    }

    /**
     * Delete a meta field.
     *
     * @param string $key The meta key to delete.
     *
     * @return bool
     */
    public function delete(string $key): bool
    {
        unset($this->data[$key]);
        return delete_post_meta($this->post_id, $key);
    }

    /**
     * Get all meta data.
     *
     * @return array
     */
    public function getAll(): array
    {
        $meta = [];
        foreach ($this->data as $key => $values) {
            $meta[$key] = $this->__get($key);
        }
        return $meta;
    }

    /**
     * Register a custom field with sanitization and validation callbacks.
     *
     * @param string   $field_name       The custom field name.
     * @param callable $sanitize_callback Sanitization callback function.
     * @param callable $auth_callback     Authorization callback function.
     */
    public function register_custom_field(string $field_name, callable $sanitize_callback = null, callable $auth_callback = null)
    {
        register_post_meta('', $field_name, [
            'show_in_rest'      => true,
            'single'            => true,
            'sanitize_callback' => $sanitize_callback,
            'auth_callback'     => $auth_callback,
        ]);
    }
}

/**
 * Class TaxonomyHandler
 *
 * Handles taxonomy-related operations for a post.
 */
class TaxonomyHandler
{
    private WP_Post $post;
    private int $post_id;
    private array $taxonomies;

    public function __construct($post)
    {
        $this->post = $post;
        $this->post_id = $post->ID;
        $this->taxonomies = get_object_taxonomies($post, 'names');
    }

    public function __get($taxonomy)
    {
        if (!in_array($taxonomy, $this->taxonomies)) {
            return null;
        }
        $terms = get_the_terms($this->post_id, $taxonomy);
        return new TaxonomyTermHandler($terms, $this->post_id, $taxonomy);
    }

    public function __isset($taxonomy)
    {
        return in_array($taxonomy, $this->taxonomies);
    }

    /**
     * Update a taxonomy term for the post.
     *
     * @param string $taxonomy The taxonomy name.
     * @param string $term     The term name or ID.
     * @param array  $args     Additional arguments for term creation.
     *
     * @return void
     */
    public function update(string $taxonomy, $term, array $args = []): void
    {
        $handler = $this->__get($taxonomy);
        if ($handler) {
            $handler->update($term, $args);
        }
    }

    /**
     * Delete a taxonomy term from the post.
     *
     * @param string $taxonomy The taxonomy name.
     * @param string $term     The term name or ID.
     *
     * @return void
     */
    public function delete(string $taxonomy, $term): void
    {
        $handler = $this->__get($taxonomy);
        if ($handler) {
            $handler->delete($term);
        }
    }

    /**
     * Get all taxonomies associated with the post.
     *
     * @return array
     */
    public function getAll(): array
    {
        $taxonomy_data = [];
        foreach ($this->taxonomies as $taxonomy) {
            $terms = get_the_terms($this->post_id, $taxonomy);
            $taxonomy_data[$taxonomy] = $terms ?: [];
        }
        return $taxonomy_data;
    }
}

/**
 * Class TaxonomyTermHandler
 *
 * Handles individual taxonomy terms for a post.
 */
class TaxonomyTermHandler implements \ArrayAccess
{
    private ?array $terms;
    private int $post_id;
    private string $taxonomy;

    public function __construct($terms, int $post_id, string $taxonomy)
    {
        $this->terms = is_array($terms) ? array_values($terms) : [];
        $this->post_id = $post_id;
        $this->taxonomy = $taxonomy;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->terms[] = $value;
        } else {
            $this->terms[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->terms[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->terms[$offset]);
    }

    #[ReturnTypeWillChange] public function offsetGet(mixed $offset): ?TaxonomyTerm
    {
        if (isset($this->terms[$offset])) {
            return new TaxonomyTerm($this->terms[$offset], $this->taxonomy);
        }
        return null;
    }

    /**
     * Update or add a term to the taxonomy.
     *
     * @param string|int $term_name The term name or ID.
     * @param array      $args      Additional arguments for term creation.
     *
     * @return void
     */
    public function update($term_name, array $args = []): void
    {
        $append = $args['append'] ?? false;
        $no_creation = $args['no_create'] ?? false;
        unset($args['append'], $args['no_create']);

        $term_id = 0;
        $term = term_exists($term_name, $this->taxonomy);
        if (!$term && !$no_creation) {
            $term = wp_insert_term($term_name, $this->taxonomy, $args);
        }

        if (is_numeric($term)) {
            $term_id = (int)$term;
        } elseif (is_array($term) && isset($term['term_id'])) {
            $term_id = (int)$term['term_id'];
        }

        if ($term_id) {
            wp_set_object_terms($this->post_id, [$term_id], $this->taxonomy, $append);
            $this->terms = get_the_terms($this->post_id, $this->taxonomy);
        }
    }

    /**
     * Remove a term from the taxonomy.
     *
     * @param string|int $term_name The term name or ID.
     *
     * @return void
     */
    public function delete($term_name): void
    {
        $term = term_exists($term_name, $this->taxonomy);
        if ($term) {
            wp_remove_object_terms($this->post_id, $term_name, $this->taxonomy);
            $this->terms = get_the_terms($this->post_id, $this->taxonomy);
        }
    }

    /**
     * Get all terms for this taxonomy.
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->terms;
    }
}

/**
 * Class TaxonomyTerm
 *
 * Represents a single taxonomy term and provides access to term meta.
 */
class TaxonomyTerm
{
    public $term;
    private string $taxonomy;

    public function __construct($term, string $taxonomy)
    {
        $this->term = $term;
        $this->taxonomy = $taxonomy;
    }

    public function __get($prop)
    {
        if ($prop === 'meta') {
            return new TermMetaHandler($this->term->term_id);
        }
        return $this->term->$prop ?? null;
    }

    public function __set($name, $value)
    {
        if ($name !== 'term_id') {
            wp_update_term($this->term->term_id, $this->taxonomy, [$name => $value]);
            $this->term = get_term($this->term->term_id, $this->taxonomy);
        }
    }

    public function __isset($name)
    {
        return isset($this->term->$name);
    }

    public function get_shadow()
    {
        if (method_exists('\WDK\Shadow', 'get_associated_post')) {
            return \WDK\Shadow::get_associated_post($this->term);
        }
        return null;
    }
}

/**
 * Class TermMetaHandler
 *
 * Handles term meta operations.
 */
class TermMetaHandler
{
    private int $term_id;

    public function __construct(int $term_id)
    {
        $this->term_id = $term_id;
    }

    public function __get($meta_key)
    {
        return get_term_meta($this->term_id, $meta_key, true);
    }

    public function __set($meta_key, $value)
    {
        update_term_meta($this->term_id, $meta_key, $value);
    }

    public function __isset($meta_key)
    {
        $value = get_term_meta($this->term_id, $meta_key, true);
        return !empty($value);
    }

    public function update(string $key, $value)
    {
        return update_term_meta($this->term_id, $key, $value);
    }

    public function delete(string $key)
    {
        return delete_term_meta($this->term_id, $key);
    }

    public function getAll(): array
    {
        return get_term_meta($this->term_id);
    }
}

/**
 * Class MediaHandler
 *
 * Handles media-related operations for a post, including featured images.
 */
class MediaHandler
{
    private int $post_id;
    private int $post_parent;

    public function __construct($post)
    {
        $this->post_id = $post->ID;
        $this->post_parent = $post->ID;
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

    /**
     * Get the featured image of the post.
     *
     * @return WP_Post|null
     */
    public function get_featured_image()
    {
        $thumbnail_id = get_post_thumbnail_id($this->post_id);
        if ($thumbnail_id) {
            $attachment = get_post($thumbnail_id);
            return $attachment ? new AttachmentHandler($attachment) : null;
        }
        return null;
    }

    /**
     * Set the featured image of the post.
     *
     * @param int $attachment_id The attachment ID to set as featured image.
     *
     * @return bool True on success, false on failure.
     */
    public function set_featured_image(int $attachment_id): bool
    {
        return (bool)set_post_thumbnail($this->post_id, $attachment_id);
    }

    // Add other specific mime type methods as needed

    /**
     * Get attachments by mime type wildcard.
     *
     * @param string $mime_type Mime type prefix (e.g., 'image', 'audio').
     *
     * @return array
     */
    private function get_by_mime_type_wildcard($mime_type): array
    {
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => $mime_type,
            'post_parent'    => $this->post_parent,
            'posts_per_page' => -1,
            'order'          => 'ASC',
        ];
        $query = new WP_Query($args);
        return array_map(function ($post) {
            return new AttachmentHandler($post);
        }, $query->posts);
    }

    /**
     * Get attachments by specific mime type.
     *
     * @param string $mime_type Full mime type (e.g., 'application/pdf').
     *
     * @return array
     */
    private function get_by_mime_type($mime_type): array
    {
        $args = [
            'post_type'      => 'attachment',
            'post_mime_type' => $mime_type,
            'post_parent'    => $this->post_parent,
            'posts_per_page' => -1,
            'order'          => 'ASC',
        ];
        $query = new WP_Query($args);
        return array_map(function ($post) {
            return new AttachmentHandler($post);
        }, $query->posts);
    }
}

/**
 * Class AttachmentHandler
 *
 * Represents an attachment and provides access to metadata.
 */
class AttachmentHandler
{
    private WP_Post $attachment;

    public function __construct($attachment)
    {
        $this->attachment = $attachment;
    }

    public function __get($name)
    {
        if ($name === 'metadata') {
            return wp_get_attachment_metadata($this->attachment->ID);
        }
        return $this->attachment->$name ?? null;
    }

    public function __set($name, $value)
    {
        if ($name !== 'ID') {
            wp_update_post([
                'ID'         => $this->attachment->ID,
                $name        => $value,
            ]);
            $this->attachment = get_post($this->attachment->ID);
        }
    }

    public function __isset($name)
    {
        return isset($this->attachment->$name);
    }
}

/**
 * Class RelationshipHandler
 *
 * Handles parent and child relationships for a post.
 */
class RelationshipHandler
{
    private int $post_id;
    private int $post_parent;

    public function __construct($post)
    {
        $this->post_id = $post->ID;
        $this->post_parent = $post->post_parent;
    }

    public function children(): array
    {
        $args = [
            'post_type'      => 'any',
            'post_parent'    => $this->post_id,
            'posts_per_page' => -1,
            'order'          => 'ASC',
            'post_status'    => 'any',
        ];
        $query = new WP_Query($args);
        return $query->posts;
    }

    public function parent()
    {
        if (!empty($this->post_parent)) {
            return get_post($this->post_parent);
        }
        return null;
    }
}

/**
 * Class CommentsHandler
 *
 * Handles comments-related operations for a post, including full CRUD capabilities.
 */
class CommentsHandler
{
    private WP_Post $post;

    public function __construct($post)
    {
        $this->post = $post;
    }

    /**
     * Retrieve all comments for the post.
     *
     * @param string $status Comment status to filter by.
     *
     * @return array
     */
    public function getAll($status = 'approve'): array
    {
        $comments = get_comments([
            'post_id' => $this->post->ID,
            'status'  => $status,
        ]);

        $commentObjects = [];
        foreach ($comments as $comment) {
            $commentObjects[] = new CommentHandler($comment);
        }

        return $commentObjects;
    }

    /**
     * Count the number of comments for the post.
     *
     * @return int
     */
    public function count(): int
    {
        return wp_count_comments($this->post->ID)->approved;
    }

    /**
     * Create a new comment on the post.
     *
     * @param array $comment_data The comment data.
     *
     * @return int|false The new comment ID on success, false on failure.
     */
    public function create(array $comment_data)
    {
        $comment_data['comment_post_ID'] = $this->post->ID;
        $comment_id = wp_insert_comment($comment_data);
        return $comment_id ?: false;
    }
}

/**
 * Class CommentHandler
 *
 * Represents a comment and provides methods for CRUD operations.
 */
class CommentHandler
{
    private WP_Comment $comment;

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
        return $this->comment->$name ?? null;
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
}
