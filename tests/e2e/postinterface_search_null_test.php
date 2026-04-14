<?php

declare(strict_types=1);

namespace {
    class WP_Post {
        public int $ID;
        public function __construct(int $id = 1) { $this->ID = $id; }
    }
    class WP_Error {}
    class WP_Query {
        public function __construct($args = []) {}
        public function have_posts() { return false; }
        public function get_posts() { return []; }
    }
    class WP_Comment {}

    function post_type_exists($postType) { return true; }
    function get_page_by_path($slug, $output, $post_type) { return null; }
    function sanitize_title($title) { return strtolower(str_replace(' ', '-', $title)); }
    function get_term_meta($term_id, $key, $single = true) { return 0; }
    function wp_insert_post($arr, $return_error = false) { return 1; }
    function wp_update_post($args) { return 1; }
    function wp_delete_post($id, $force = true) { return true; }
    function get_post_meta($id, $key = '', $single = false) { return []; }
    function update_post_meta($id, $key, $value) { return true; }
    function delete_post_meta($id, $key) { return true; }
    function is_serialized($v) { return false; }
    function maybe_unserialize($v) { return $v; }
    function register_post_meta($a, $b, $c) { return true; }
    function get_object_taxonomies($post, $output = 'names') { return []; }
    function get_the_terms($post_id, $taxonomy) { return []; }
    function get_term($term_id, $taxonomy = null) { return null; }
    function term_exists($term_name, $taxonomy = null) { return false; }
    function wp_insert_term($term_name, $taxonomy, $args = []) { return ['term_id' => 1]; }
    function wp_set_object_terms($post_id, $terms, $taxonomy, $append = false) { return true; }
    function wp_remove_object_terms($post_id, $terms, $taxonomy) { return true; }
    function wp_update_term($term_id, $taxonomy, $args = []) { return true; }
    function get_post_thumbnail_id($post_id) { return 0; }
    function get_post($id) { return new WP_Post((int)$id); }
    function set_post_thumbnail($post_id, $attachment_id) { return true; }
    function wp_get_attachment_metadata($id) { return []; }
    function get_comments($args = []) { return []; }
    function wp_count_comments($post_id) { return (object)['approved' => 0]; }
    function wp_insert_comment($data) { return 1; }
    function wp_update_comment($args) { return 1; }
    function wp_trash_comment($id) { return true; }
    function wp_untrash_comment($id) { return true; }
    function wp_delete_comment($id, $force = true) { return true; }
    function update_term_meta($id, $k, $v){ return true; }
    function delete_term_meta($id, $k){ return true; }
    function is_wp_error($v){ return false; }
}

namespace WDK {
    class Search {
        public function __construct($provider = null) {}
        public function search($term, $args) {
            return (object)['posts' => []];
        }
    }
}

namespace {
    require_once __DIR__ . '/../../library/PostInterface.php';

    $post = \WDK\PostInterface::post(['search_provider' => 'fake']);
    if ($post !== null) {
        fwrite(STDERR, "Expected null when search provider returns no posts.\n");
        exit(1);
    }

    echo "PASS: postinterface_search_null_test\n";
}
