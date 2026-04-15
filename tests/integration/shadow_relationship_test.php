<?php
/**
 * Test support definitions for the WP Post component.
 *
 * @package WDK\Tests
 */


declare(strict_types=1);

/**
 * Provides a lightweight WP_Post stub for tests.
 */
class WP_Post {
    public int $ID;
    public string $post_type = 'book';
    public string $post_status = 'publish';
    public string $post_title = 'Book A';
    public string $post_name = 'book-a';
    public function __construct(int $id) { $this->ID = $id; }
}

/**
 * Provides a lightweight WP_Term stub for tests.
 */
class WP_Term {
    public int $term_id;
    public string $name = 'Book A';
    public string $slug = 'book-a';
    public function __construct(int $id) { $this->term_id = $id; }
}

/**
 * Provides a lightweight WP_Error stub for tests.
 */
class WP_Error {
    public function get_error_code() { return ''; }
    public function get_error_data($key = null) { return null; }
}

$GLOBALS['hooks'] = [];
$GLOBALS['post_meta'] = [];
$GLOBALS['term_meta'] = [];
$GLOBALS['posts'] = [101 => new WP_Post(101)];
$GLOBALS['next_term_id'] = 200;

function wp_json_encode($value) { return json_encode($value); }
function add_action($hook, $cb) { $GLOBALS['hooks'][$hook][] = $cb; }
function get_post($post_id) { return $GLOBALS['posts'][$post_id] ?? null; }
function get_post_meta($post_id, $key, $single = true) { return $GLOBALS['post_meta'][$post_id][$key] ?? ''; }
function update_post_meta($post_id, $key, $value) { $GLOBALS['post_meta'][$post_id][$key] = $value; return true; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['post_meta'][$post_id][$key]); return true; }
function get_term_meta($term_id, $key, $single = true) { return $GLOBALS['term_meta'][$term_id][$key] ?? ''; }
function update_term_meta($term_id, $key, $value) { $GLOBALS['term_meta'][$term_id][$key] = $value; return true; }
function delete_term_meta($term_id, $key) { unset($GLOBALS['term_meta'][$term_id][$key]); return true; }
function has_term($values, $taxonomy, $post) { return true; }
function get_term_by($field, $value, $taxonomy) { return new WP_Term((int)$value); }
function get_posts($args) { return []; }
function wp_delete_term($term_id, $taxonomy) { return true; }
function wp_update_term($term_id, $taxonomy, $args) { return ['term_id' => $term_id]; }
function is_wp_error($thing) { return $thing instanceof WP_Error; }
function wp_insert_term($name, $taxonomy, $args = []) {
    $id = ++$GLOBALS['next_term_id'];
    return ['term_id' => $id];
}
function get_term($term_id) { return new WP_Term((int)$term_id); }
function get_the_terms($post_id, $taxonomy) { return []; }

require_once __DIR__ . '/../../library/Shadow.php';

WDK\Shadow::create_relationship('book', 'book_tax');
WDK\Shadow::create_relationship('book', 'book_tax'); // duplicate should be ignored

if (count($GLOBALS['hooks']['wp_insert_post'] ?? []) !== 1) {
    fwrite(STDERR, "Expected duplicate relationship registration protection.\n");
    exit(1);
}

$post = $GLOBALS['posts'][101];
$res = WDK\Shadow::create_shadow_taxonomy_term($post, 'book_tax');
$termId = (int)$res['term_id'];

if (($GLOBALS['post_meta'][101]['shadow_term_id'] ?? null) !== $termId) {
    fwrite(STDERR, "Expected post meta shadow_term_id to be linked.\n");
    exit(1);
}

if (($GLOBALS['term_meta'][$termId]['shadow_post_id'] ?? null) !== 101) {
    fwrite(STDERR, "Expected term meta shadow_post_id to be linked.\n");
    exit(1);
}

echo "PASS: shadow_relationship_test\n";
