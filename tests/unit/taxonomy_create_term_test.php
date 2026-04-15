<?php
/**
 * Test support definitions for the WP Error Stub component.
 *
 * @package WDK\Tests
 */


declare(strict_types=1);

/**
 * Provides a lightweight WpErrorStub stub for tests.
 */
class WpErrorStub {
    public function get_error_code() { return ''; }
    public function get_error_data($key = null) { return null; }
}

$GLOBALS['wdk_opts'] = [];
$GLOBALS['wdk_terms'] = [];

function get_option($key) { return $GLOBALS['wdk_opts'][$key] ?? false; }
function update_option($key, $value) { $GLOBALS['wdk_opts'][$key] = $value; return true; }
function term_exists($term, $taxonomy) {
    $k = $taxonomy . ':' . $term;
    return $GLOBALS['wdk_terms'][$k] ?? false;
}
function wp_insert_term($term, $taxonomy, $args = []) {
    $id = count($GLOBALS['wdk_terms']) + 1;
    $GLOBALS['wdk_terms'][$taxonomy . ':' . $term] = ['term_id' => $id, 'parent' => $args[0] ?? 0];
    return ['term_id' => $id];
}
function add_action($hook, $cb, $priority = 10) { $cb(); return true; }

require_once __DIR__ . '/../../library/Taxonomy.php';

$ok = WDK\Taxonomy::CreateTerm('My Tax', [
    'Parent' => ['Child A', 'Child B'],
    'Standalone',
]);

if ($ok !== true) {
    fwrite(STDERR, "Expected CreateTerm() to return true for array input.\n");
    exit(1);
}

$expected = ['my_tax:Parent', 'my_tax:Child A', 'my_tax:Child B', 'my_tax:Standalone'];
foreach ($expected as $key) {
    if (!isset($GLOBALS['wdk_terms'][$key])) {
        fwrite(STDERR, "Missing expected term: {$key}\n");
        exit(1);
    }
}

echo "PASS: taxonomy_create_term_test\n";
