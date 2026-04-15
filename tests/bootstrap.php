<?php
/**
 * Test bootstrap and WordPress compatibility stubs for the WDK test suite.
 *
 * @package WDK\Tests
 */


declare(strict_types=1);

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/fixtures/wordpress/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('TEMPLATE_ENGINE')) {
    define('TEMPLATE_ENGINE', 'twig');
}

$GLOBALS['wp_version'] = '6.6';

function &wdk_test_state(): array
{
    if (!isset($GLOBALS['wdk_test_state']) || !is_array($GLOBALS['wdk_test_state'])) {
        $GLOBALS['wdk_test_state'] = [];
    }

    return $GLOBALS['wdk_test_state'];
}

function wdk_test_default_caps(): array
{
    return [
        'edit_post' => true,
        'edit_posts' => true,
        'edit_pages' => true,
        'manage_categories' => true,
        'manage_options' => true,
    ];
}

function wdk_test_reset_state(): void
{
    $GLOBALS['wdk_test_state'] = [
        'next_post_id' => 1,
        'posts' => [],
        'post_meta' => [],
        'registered_post_meta' => [],
        'post_types' => [
            'post' => ['label' => 'Posts'],
            'page' => ['label' => 'Pages'],
            'attachment' => ['label' => 'Media'],
        ],
        'next_term_id' => 1,
        'terms' => [],
        'term_meta' => [],
        'taxonomies' => [
            'category' => [
                'object_type' => ['post'],
                'cap' => (object) ['manage_terms' => 'manage_categories'],
            ],
            'post_tag' => [
                'object_type' => ['post'],
                'cap' => (object) ['manage_terms' => 'manage_categories'],
            ],
        ],
        'object_terms' => [],
        'next_comment_id' => 1,
        'comments' => [],
        'featured_images' => [],
        'attachments_meta' => [],
        'options' => [],
        'site_options' => [],
        'hooks' => [],
        'filters' => [],
        'did_actions' => [],
        'deprecated' => [],
        'http_queue' => [],
        'last_http_request' => null,
        'current_user_caps' => wdk_test_default_caps(),
        'nonces' => [],
        'query_vars' => [],
        'is_admin' => true,
        'autosaves' => [],
        'revisions' => [],
    ];

    $_GET = [];
    $_POST = [];
    $_REQUEST = [];
}

function wdk_test_clear_runtime_state(): void
{
    $state = &wdk_test_state();
    $state['deprecated'] = [];
    $state['http_queue'] = [];
    $state['last_http_request'] = null;
    $state['current_user_caps'] = wdk_test_default_caps();
    $state['nonces'] = [];
    $state['query_vars'] = [];
    $state['is_admin'] = true;
    $state['autosaves'] = [];
    $state['revisions'] = [];
    $state['did_actions'] = [];
    $_GET = [];
    $_POST = [];
    $_REQUEST = [];

    if (function_exists('wdk_reset_runtime_state_for_tests')) {
        wdk_reset_runtime_state_for_tests();
    }

    if (class_exists('\WDK\Runtime', false)) {
        \WDK\Runtime::resetForTests();
    }
}

function wdk_test_create_nonce(string $action): string
{
    $token = 'wdk_nonce_' . md5($action);
    $state = &wdk_test_state();
    $state['nonces'][$action] = $token;

    return $token;
}

function wdk_test_push_http_response(array $response): void
{
    $state = &wdk_test_state();
    $state['http_queue'][] = $response;
}

function wdk_test_last_http_request(): ?array
{
    return wdk_test_state()['last_http_request'] ?? null;
}

function wdk_test_deprecations(): array
{
    return wdk_test_state()['deprecated'] ?? [];
}

function wdk_test_callback_key(callable|array|string $callback): string
{
    if (is_string($callback)) {
        return $callback;
    }

    if (is_array($callback)) {
        $target = is_object($callback[0]) ? spl_object_hash($callback[0]) : (string) $callback[0];
        return $target . '::' . $callback[1];
    }

    if ($callback instanceof Closure) {
        return spl_object_hash($callback);
    }

    return md5(serialize($callback));
}

function wdk_test_invoke_callbacks(array $callbacks, array $args, bool $filterMode = false)
{
    ksort($callbacks);
    $value = $args[0] ?? null;

    foreach ($callbacks as $priorityCallbacks) {
        foreach ($priorityCallbacks as $callback) {
            if ($filterMode) {
                $value = $callback($value, ...array_slice($args, 1));
            } else {
                $callback(...$args);
            }
        }
    }

    return $filterMode ? $value : null;
}

function wdk_test_resolve_post_types(string|array|null $postType): array
{
    if ($postType === null || $postType === 'any') {
        return array_keys(wdk_test_state()['post_types']);
    }

    return is_array($postType) ? $postType : [$postType];
}

function wdk_test_matches_meta_query(WP_Post $post, array $metaQuery): bool
{
    $relation = strtoupper((string) ($metaQuery['relation'] ?? 'AND'));
    $clauses = array_values(array_filter($metaQuery, static fn ($key) => $key !== 'relation', ARRAY_FILTER_USE_KEY));
    $matches = [];

    foreach ($clauses as $clause) {
        $values = get_post_meta($post->ID, (string) ($clause['key'] ?? ''), false);
        $actual = $values[0] ?? null;
        $expected = $clause['value'] ?? null;
        $compare = strtoupper((string) ($clause['compare'] ?? '='));

        $matches[] = match ($compare) {
            '!=' => $actual != $expected,
            'IN' => in_array($actual, (array) $expected, true),
            'NOT IN' => !in_array($actual, (array) $expected, true),
            '>=' => $actual >= $expected,
            '<=' => $actual <= $expected,
            default => $actual == $expected,
        };
    }

    if ($matches === []) {
        return true;
    }

    return $relation === 'OR'
        ? in_array(true, $matches, true)
        : !in_array(false, $matches, true);
}

function wdk_test_matches_tax_query(WP_Post $post, array $taxQuery): bool
{
    $relation = strtoupper((string) ($taxQuery['relation'] ?? 'AND'));
    $clauses = array_values(array_filter($taxQuery, static fn ($key) => $key !== 'relation', ARRAY_FILTER_USE_KEY));
    $matches = [];

    foreach ($clauses as $clause) {
        $terms = get_the_terms($post->ID, (string) ($clause['taxonomy'] ?? ''));
        $field = (string) ($clause['field'] ?? 'slug');
        $expected = (array) ($clause['terms'] ?? []);
        $actual = array_map(static function ($term) use ($field) {
            return $term->{$field} ?? null;
        }, $terms ?: []);

        $matches[] = array_intersect($expected, $actual) !== [];
    }

    if ($matches === []) {
        return true;
    }

    return $relation === 'OR'
        ? in_array(true, $matches, true)
        : !in_array(false, $matches, true);
}

function wdk_test_query_posts(array $args): array
{
    $posts = array_values(wdk_test_state()['posts']);
    $postTypes = wdk_test_resolve_post_types($args['post_type'] ?? 'any');
    $statusFilter = $args['post_status'] ?? 'any';
    $statusValues = $statusFilter === 'any' ? [] : (array) $statusFilter;

    $posts = array_values(array_filter($posts, static function (WP_Post $post) use ($args, $postTypes, $statusValues) {
        if (!in_array($post->post_type, $postTypes, true)) {
            return false;
        }

        if ($statusValues !== [] && !in_array($post->post_status, $statusValues, true)) {
            return false;
        }

        if (!empty($args['p']) && $post->ID !== (int) $args['p']) {
            return false;
        }

        if (isset($args['title']) && $post->post_title !== $args['title']) {
            return false;
        }

        if (isset($args['post_parent']) && $post->post_parent !== (int) $args['post_parent']) {
            return false;
        }

        if (!empty($args['post__in']) && !in_array($post->ID, array_map('intval', (array) $args['post__in']), true)) {
            return false;
        }

        if (!empty($args['post__not_in']) && in_array($post->ID, array_map('intval', (array) $args['post__not_in']), true)) {
            return false;
        }

        if (!empty($args['post_mime_type'])) {
            $mime = (string) $args['post_mime_type'];
            if (!str_contains($mime, '/')) {
                if (!str_starts_with($post->post_mime_type, $mime . '/')) {
                    return false;
                }
            } elseif ($post->post_mime_type !== $mime) {
                return false;
            }
        }

        if (!empty($args['s'])) {
            $needle = strtolower((string) $args['s']);
            $haystacks = strtolower($post->post_title . ' ' . $post->post_content);
            if (!str_contains($haystacks, $needle)) {
                return false;
            }
        }

        if (!empty($args['meta_query']) && !wdk_test_matches_meta_query($post, $args['meta_query'])) {
            return false;
        }

        if (!empty($args['tax_query']) && !wdk_test_matches_tax_query($post, $args['tax_query'])) {
            return false;
        }

        return true;
    }));

    $order = strtoupper((string) ($args['order'] ?? 'ASC'));
    usort($posts, static fn (WP_Post $a, WP_Post $b) => $order === 'DESC' ? ($b->ID <=> $a->ID) : ($a->ID <=> $b->ID));

    if (isset($args['posts_per_page']) && (int) $args['posts_per_page'] > -1) {
        $posts = array_slice($posts, 0, (int) $args['posts_per_page']);
    }

    if (($args['fields'] ?? null) === 'ids') {
        return array_map(static fn (WP_Post $post) => $post->ID, $posts);
    }

    return $posts;
}

/**
 * Provides a lightweight WP_Post stub for tests.
 */
class WP_Post
{
    public int $ID = 0;
    public string $post_title = '';
    public string $post_content = '';
    public string $post_status = 'draft';
    public string $post_type = 'post';
    public int $post_parent = 0;
    public string $post_name = '';
    public string $post_mime_type = '';

    public function __construct(int|array $data = 0)
    {
        if (is_int($data)) {
            $this->ID = $data;
            return;
        }

        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

/**
 * Provides a lightweight WP_Term stub for tests.
 */
class WP_Term
{
    public int $term_id = 0;
    public string $taxonomy = '';
    public string $name = '';
    public string $slug = '';
    public int $parent = 0;
    public string $description = '';

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

/**
 * Provides a lightweight WP_Comment stub for tests.
 */
class WP_Comment
{
    public int $comment_ID = 0;
    public int $comment_post_ID = 0;
    public string $comment_author = '';
    public string $comment_author_email = '';
    public string $comment_content = '';
    public int|string $comment_approved = 0;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

/**
 * Provides a lightweight WP_Error stub for tests.
 */
class WP_Error
{
    private string $code;
    private string $message;
    private mixed $data;

    public function __construct(string $code = '', string $message = '', mixed $data = null)
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }

    public function get_error_data(?string $code = null): mixed
    {
        return $this->data;
    }
}

/**
 * Provides a lightweight WP_Query stub for tests.
 */
class WP_Query
{
    public array $query;
    public array $posts = [];
    public int $post_count = 0;
    public int $found_posts = 0;
    public int $max_num_pages = 0;

    public function __construct(array $args = [])
    {
        $this->query = $args;
        $this->posts = wdk_test_query_posts($args);
        $this->post_count = count($this->posts);
        $this->found_posts = $this->post_count;
        $this->max_num_pages = $this->post_count > 0 ? 1 : 0;
    }

    public function have_posts(): bool
    {
        return $this->post_count > 0;
    }

    public function get_posts(): array
    {
        return $this->posts;
    }

    public function get(string $key): mixed
    {
        return $this->query[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->query[$key] = $value;
    }

    public function is_main_query(): bool
    {
        return true;
    }
}

function add_action(string $hook, callable $callback, int $priority = 10): bool
{
    $state = &wdk_test_state();
    $state['hooks'][$hook][$priority][wdk_test_callback_key($callback)] = $callback;

    return true;
}

function remove_action(string $hook, callable $callback, int $priority = 10): bool
{
    $state = &wdk_test_state();
    unset($state['hooks'][$hook][$priority][wdk_test_callback_key($callback)]);

    return true;
}

function do_action(string $hook, ...$args): void
{
    $state = &wdk_test_state();
    $state['did_actions'][$hook] = (int) ($state['did_actions'][$hook] ?? 0) + 1;
    $callbacks = wdk_test_state()['hooks'][$hook] ?? [];
    wdk_test_invoke_callbacks($callbacks, $args, false);
}

function did_action(string $hook): int
{
    return (int) (wdk_test_state()['did_actions'][$hook] ?? 0);
}

function add_filter(string $hook, callable $callback, int $priority = 10): bool
{
    $state = &wdk_test_state();
    $state['filters'][$hook][$priority][wdk_test_callback_key($callback)] = $callback;

    return true;
}

function remove_filter(string $hook, callable|string $callback, int $priority = 10): bool
{
    $state = &wdk_test_state();
    unset($state['filters'][$hook][$priority][wdk_test_callback_key($callback)]);

    return true;
}

function apply_filters(string $hook, mixed $value, ...$args): mixed
{
    $callbacks = wdk_test_state()['filters'][$hook] ?? [];

    return wdk_test_invoke_callbacks($callbacks, array_merge([$value], $args), true);
}

function has_filter(string $hook, callable|string|null $callback = null): bool
{
    $filters = wdk_test_state()['filters'][$hook] ?? [];
    if ($callback === null) {
        return $filters !== [];
    }

    foreach ($filters as $priorityCallbacks) {
        if (isset($priorityCallbacks[wdk_test_callback_key($callback)])) {
            return true;
        }
    }

    return false;
}

function wp_json_encode(mixed $value): string|false
{
    return json_encode($value);
}

function is_wp_error(mixed $value): bool
{
    return $value instanceof WP_Error;
}

function _doing_it_wrong(string $function, string $message, string $version): void
{
    $state = &wdk_test_state();
    $state['deprecated'][] = [
        'function' => $function,
        'message' => $message,
        'version' => $version,
    ];
}

if (!function_exists('__')) {
    function __(string $text, ?string $domain = null): string
    {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e(string $text, ?string $domain = null): void
    {
        echo $text;
    }
}

if (!function_exists('_')) {
    function _(string $text): string
    {
        return $text;
    }
}

function absint(mixed $value): int
{
    return max(0, (int) $value);
}

function sanitize_text_field(string $value): string
{
    $value = strip_tags($value);
    $value = preg_replace('/[\r\n\t]+/', ' ', $value);

    return trim((string) $value);
}

function sanitize_email(string $value): string
{
    return (string) filter_var($value, FILTER_SANITIZE_EMAIL);
}

function sanitize_key(string $value): string
{
    return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $value));
}

function sanitize_title(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);

    return trim((string) $value, '-');
}

function sanitize_hex_color(string $value): string
{
    return preg_match('/^#?[0-9a-fA-F]{6}$/', $value) ? ('#' . ltrim($value, '#')) : '';
}

function sanitize_html_class(string $value): string
{
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?? '';
}

function esc_url_raw(string $value): string
{
    return filter_var($value, FILTER_SANITIZE_URL) ?: '';
}

function esc_attr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_url(string $value): string
{
    return esc_attr($value);
}

function esc_sql(string $value): string
{
    return $value;
}

function wp_unslash(mixed $value): mixed
{
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }

    return is_string($value) ? stripslashes($value) : $value;
}

function maybe_serialize(mixed $value): mixed
{
    return is_array($value) || is_object($value) ? serialize($value) : $value;
}

function is_serialized(mixed $value): bool
{
    if (!is_string($value) || $value === '') {
        return false;
    }

    return @unserialize($value) !== false || $value === 'b:0;';
}

function maybe_unserialize(mixed $value): mixed
{
    return is_serialized($value) ? unserialize($value) : $value;
}

function post_type_exists(string $postType): bool
{
    return isset(wdk_test_state()['post_types'][$postType]);
}

function register_post_type(string $postType, array $args = []): bool
{
    $state = &wdk_test_state();
    $state['post_types'][$postType] = $args;

    return true;
}

function unregister_post_type(string $postType): bool
{
    $state = &wdk_test_state();
    unset($state['post_types'][$postType]);

    return true;
}

function get_post(int|string|WP_Post $post): ?WP_Post
{
    if ($post instanceof WP_Post) {
        return $post;
    }

    return wdk_test_state()['posts'][(int) $post] ?? null;
}

function get_page_by_path(string $slug, string $output = OBJECT, string|array $postType = 'post'): ?WP_Post
{
    foreach (wdk_test_state()['posts'] as $post) {
        if ($post->post_name === $slug && in_array($post->post_type, wdk_test_resolve_post_types($postType), true)) {
            return $post;
        }
    }

    return null;
}

function wp_insert_post(array $postArr, bool $returnError = false): int|WP_Error
{
    $postType = (string) ($postArr['post_type'] ?? 'post');
    if (!post_type_exists($postType)) {
        return $returnError ? new WP_Error('invalid_post_type', 'Invalid post type.') : 0;
    }

    $state = &wdk_test_state();
    $postId = $state['next_post_id']++;
    $postData = array_merge([
        'ID' => $postId,
        'post_title' => '',
        'post_content' => '',
        'post_status' => 'draft',
        'post_type' => $postType,
        'post_parent' => 0,
        'post_name' => '',
        'post_mime_type' => '',
    ], $postArr);

    if ($postData['post_name'] === '' && $postData['post_title'] !== '') {
        $postData['post_name'] = sanitize_title((string) $postData['post_title']);
    }

    $state['posts'][$postId] = new WP_Post($postData);
    do_action('wp_insert_post', $postId);

    return $postId;
}

function wp_update_post(array|WP_Post $postArr): int
{
    $postArr = $postArr instanceof WP_Post ? get_object_vars($postArr) : $postArr;
    $postId = (int) ($postArr['ID'] ?? 0);
    $post = get_post($postId);
    if (!$post instanceof WP_Post) {
        return 0;
    }

    foreach ($postArr as $key => $value) {
        if ($key === 'ID') {
            continue;
        }

        $post->{$key} = $value;
    }

    if (empty($post->post_name) && $post->post_title !== '') {
        $post->post_name = sanitize_title($post->post_title);
    }

    do_action('wp_insert_post', $postId);

    return $postId;
}

function wp_delete_post(int $postId, bool $forceDelete = true): bool
{
    $state = &wdk_test_state();
    if (!isset($state['posts'][$postId])) {
        return false;
    }

    if ($forceDelete) {
        do_action('before_delete_post', $postId);
    } else {
        do_action('wp_trash_post', $postId);
    }

    unset($state['posts'][$postId], $state['post_meta'][$postId], $state['object_terms'][$postId], $state['featured_images'][$postId]);

    return true;
}

function get_posts(array $args = []): array
{
    return wdk_test_query_posts($args);
}

function get_post_meta(int $postId, string $key = '', bool $single = false): mixed
{
    $meta = wdk_test_state()['post_meta'][$postId] ?? [];
    if ($key === '') {
        return $meta;
    }

    $values = $meta[$key] ?? [];
    if ($single) {
        return $values[0] ?? '';
    }

    return $values;
}

function add_post_meta(int $postId, string $key, mixed $value, bool $unique = false): bool
{
    $state = &wdk_test_state();
    $state['post_meta'][$postId] ??= [];
    $state['post_meta'][$postId][$key] ??= [];

    if ($unique && $state['post_meta'][$postId][$key] !== []) {
        return false;
    }

    $state['post_meta'][$postId][$key][] = $value;

    return true;
}

function update_post_meta(int $postId, string $key, mixed $value, mixed $oldValue = null): bool
{
    $state = &wdk_test_state();
    $state['post_meta'][$postId] ??= [];
    $state['post_meta'][$postId][$key] = [$value];

    return true;
}

function delete_post_meta(int $postId, string $key, mixed $value = null): bool
{
    $state = &wdk_test_state();
    if (!isset($state['post_meta'][$postId][$key])) {
        return false;
    }

    if ($value === null) {
        unset($state['post_meta'][$postId][$key]);
        return true;
    }

    $state['post_meta'][$postId][$key] = array_values(array_filter(
        $state['post_meta'][$postId][$key],
        static fn ($existing) => $existing !== $value
    ));

    if ($state['post_meta'][$postId][$key] === []) {
        unset($state['post_meta'][$postId][$key]);
    }

    return true;
}

function register_post_meta(string $objectType, string $metaKey, array $args): bool
{
    $state = &wdk_test_state();
    $state['registered_post_meta'][$metaKey] = $args;

    return true;
}

function taxonomy_exists(string $taxonomy): bool
{
    return isset(wdk_test_state()['taxonomies'][$taxonomy]);
}

function register_taxonomy(string $taxonomy, string|array $objectType, array $options = []): bool
{
    $state = &wdk_test_state();
    $capability = $options['capabilities']['manage_terms'] ?? 'manage_categories';
    $state['taxonomies'][$taxonomy] = [
        'object_type' => is_array($objectType) ? $objectType : [$objectType],
        'cap' => (object) ['manage_terms' => $capability],
        'options' => $options,
    ];

    return true;
}

function unregister_taxonomy(string $taxonomy): bool
{
    $state = &wdk_test_state();
    unset($state['taxonomies'][$taxonomy], $state['terms'][$taxonomy]);

    return true;
}

function register_taxonomy_for_object_type(string $taxonomy, string $objectType): bool
{
    $state = &wdk_test_state();
    $state['taxonomies'][$taxonomy]['object_type'][] = $objectType;
    $state['taxonomies'][$taxonomy]['object_type'] = array_values(array_unique($state['taxonomies'][$taxonomy]['object_type']));

    return true;
}

function get_taxonomy(string $taxonomy): ?object
{
    $data = wdk_test_state()['taxonomies'][$taxonomy] ?? null;
    if ($data === null) {
        return null;
    }

    return (object) [
        'name' => $taxonomy,
        'object_type' => $data['object_type'],
        'cap' => $data['cap'],
    ];
}

function get_taxonomies(): array
{
    return array_keys(wdk_test_state()['taxonomies']);
}

function get_object_taxonomies(WP_Post|string $post, string $output = 'names'): array
{
    $postType = $post instanceof WP_Post ? $post->post_type : $post;
    $taxonomies = [];
    foreach (wdk_test_state()['taxonomies'] as $taxonomy => $config) {
        if (in_array($postType, $config['object_type'], true)) {
            $taxonomies[] = $output === 'names' ? $taxonomy : (object) $config;
        }
    }

    return $taxonomies;
}

function term_exists(int|string $term, ?string $taxonomy = null): array|int|null
{
    foreach (wdk_test_state()['terms'] as $taxonomyName => $terms) {
        if ($taxonomy !== null && $taxonomyName !== $taxonomy) {
            continue;
        }

        foreach ($terms as $termObject) {
            if ((is_numeric($term) && (int) $term === $termObject->term_id)
                || $term === $termObject->name
                || $term === $termObject->slug) {
                return ['term_id' => $termObject->term_id];
            }
        }
    }

    return null;
}

function wp_insert_term(string $name, string $taxonomy, array $args = []): array|WP_Error
{
    if (!taxonomy_exists($taxonomy)) {
        register_taxonomy($taxonomy, ['post']);
    }

    $existing = term_exists($name, $taxonomy);
    if ($existing) {
        return new WP_Error('term_exists', 'Term already exists.', (int) $existing['term_id']);
    }

    $state = &wdk_test_state();
    $termId = $state['next_term_id']++;
    $parent = isset($args['parent']) ? (int) $args['parent'] : (isset($args[0]) ? (int) $args[0] : 0);
    $term = new WP_Term([
        'term_id' => $termId,
        'taxonomy' => $taxonomy,
        'name' => $name,
        'slug' => (string) ($args['slug'] ?? sanitize_title($name)),
        'parent' => $parent,
        'description' => (string) ($args['description'] ?? ''),
    ]);

    $state['terms'][$taxonomy][$termId] = $term;

    return ['term_id' => $termId];
}

function wp_update_term(int $termId, string $taxonomy, array $args = []): array|WP_Error
{
    $state = &wdk_test_state();
    if (!isset($state['terms'][$taxonomy][$termId])) {
        return new WP_Error('missing_term', 'Term not found.');
    }

    foreach ($args as $key => $value) {
        $state['terms'][$taxonomy][$termId]->{$key} = $value;
    }

    return ['term_id' => $termId];
}

function wp_delete_term(int $termId, string $taxonomy): bool
{
    $state = &wdk_test_state();
    unset($state['terms'][$taxonomy][$termId], $state['term_meta'][$termId]);

    foreach ($state['object_terms'] as $postId => $taxAssignments) {
        if (!isset($taxAssignments[$taxonomy])) {
            continue;
        }

        $state['object_terms'][$postId][$taxonomy] = array_values(array_filter(
            $taxAssignments[$taxonomy],
            static fn ($assignedId) => (int) $assignedId !== $termId
        ));
    }

    return true;
}

function get_term(int $termId, ?string $taxonomy = null): ?WP_Term
{
    foreach (wdk_test_state()['terms'] as $taxonomyName => $terms) {
        if ($taxonomy !== null && $taxonomyName !== $taxonomy) {
            continue;
        }

        if (isset($terms[$termId])) {
            return $terms[$termId];
        }
    }

    return null;
}

function get_term_by(string $field, mixed $value, ?string $taxonomy = null): ?WP_Term
{
    foreach (wdk_test_state()['terms'] as $taxonomyName => $terms) {
        if ($taxonomy !== null && $taxonomyName !== $taxonomy) {
            continue;
        }

        foreach ($terms as $term) {
            if (($field === 'id' && (int) $value === $term->term_id)
                || ($field === 'name' && $value === $term->name)
                || ($field === 'slug' && $value === $term->slug)) {
                return $term;
            }
        }
    }

    return null;
}

function get_terms(array $args = []): array
{
    $taxonomy = $args['taxonomy'] ?? null;
    $hideEmpty = !empty($args['hide_empty']);
    $terms = $taxonomy !== null ? array_values(wdk_test_state()['terms'][$taxonomy] ?? []) : [];

    if (!$hideEmpty) {
        return $terms;
    }

    return array_values(array_filter($terms, static function (WP_Term $term) {
        foreach (wdk_test_state()['object_terms'] as $taxAssignments) {
            foreach (($taxAssignments[$term->taxonomy] ?? []) as $assignedTermId) {
                if ((int) $assignedTermId === $term->term_id) {
                    return true;
                }
            }
        }

        return false;
    }));
}

function get_term_children(int $termId, string $taxonomy): array
{
    $children = [];
    foreach (wdk_test_state()['terms'][$taxonomy] ?? [] as $term) {
        if ($term->parent === $termId) {
            $children[] = $term->term_id;
        }
    }

    return $children;
}

function add_term_meta(int $termId, string $key, mixed $value, bool $unique = false): bool
{
    $state = &wdk_test_state();
    $state['term_meta'][$termId] ??= [];
    $state['term_meta'][$termId][$key] ??= [];

    if ($unique && $state['term_meta'][$termId][$key] !== []) {
        return false;
    }

    $state['term_meta'][$termId][$key][] = $value;

    return true;
}

function get_term_meta(int $termId, string $key = '', bool $single = false): mixed
{
    $meta = wdk_test_state()['term_meta'][$termId] ?? [];
    if ($key === '') {
        return $meta;
    }

    $values = $meta[$key] ?? [];
    if ($single) {
        return $values[0] ?? '';
    }

    return $values;
}

function update_term_meta(int $termId, string $key, mixed $value): bool
{
    $state = &wdk_test_state();
    $state['term_meta'][$termId] ??= [];
    $state['term_meta'][$termId][$key] = [$value];

    return true;
}

function delete_term_meta(int $termId, string $key): bool
{
    $state = &wdk_test_state();
    unset($state['term_meta'][$termId][$key]);

    return true;
}

function wdk_test_resolve_term_ids(mixed $terms, string $taxonomy, bool $createIfMissing = true): array
{
    $resolved = [];
    foreach ((array) $terms as $term) {
        if (is_numeric($term)) {
            $resolved[] = (int) $term;
            continue;
        }

        $existing = term_exists((string) $term, $taxonomy);
        if ($existing) {
            $resolved[] = (int) $existing['term_id'];
            continue;
        }

        if ($createIfMissing) {
            $created = wp_insert_term((string) $term, $taxonomy);
            if (is_array($created)) {
                $resolved[] = (int) $created['term_id'];
            }
        }
    }

    return array_values(array_unique($resolved));
}

function wp_set_object_terms(int $postId, mixed $terms, string $taxonomy, bool $append = false): array
{
    $state = &wdk_test_state();
    $termIds = wdk_test_resolve_term_ids($terms, $taxonomy, true);
    $existing = $state['object_terms'][$postId][$taxonomy] ?? [];
    $state['object_terms'][$postId][$taxonomy] = $append
        ? array_values(array_unique(array_merge($existing, $termIds)))
        : $termIds;

    do_action('set_object_terms', $postId);

    return $state['object_terms'][$postId][$taxonomy];
}

function wp_remove_object_terms(int $postId, mixed $terms, string $taxonomy): bool
{
    $state = &wdk_test_state();
    $removeIds = wdk_test_resolve_term_ids($terms, $taxonomy, false);
    $existing = $state['object_terms'][$postId][$taxonomy] ?? [];
    $state['object_terms'][$postId][$taxonomy] = array_values(array_filter(
        $existing,
        static fn ($termId) => !in_array((int) $termId, $removeIds, true)
    ));

    return true;
}

function get_the_terms(int $postId, string $taxonomy): array|false
{
    $termIds = wdk_test_state()['object_terms'][$postId][$taxonomy] ?? [];
    $terms = [];
    foreach ($termIds as $termId) {
        $term = get_term((int) $termId, $taxonomy);
        if ($term instanceof WP_Term) {
            $terms[] = $term;
        }
    }

    return $terms === [] ? false : $terms;
}

function wp_get_object_terms(int $postId, string $taxonomy, array $args = []): array
{
    $terms = get_the_terms($postId, $taxonomy) ?: [];
    $fields = $args['fields'] ?? 'all';

    return match ($fields) {
        'names' => array_map(static fn (WP_Term $term) => $term->name, $terms),
        'ids' => array_map(static fn (WP_Term $term) => $term->term_id, $terms),
        default => $terms,
    };
}

function has_term(mixed $values, string $taxonomy, WP_Post|int $post): bool
{
    $postId = $post instanceof WP_Post ? $post->ID : (int) $post;
    $terms = get_the_terms($postId, $taxonomy) ?: [];
    $values = (array) $values;

    foreach ($terms as $term) {
        if (in_array($term->term_id, $values, true)
            || in_array($term->name, $values, true)
            || in_array($term->slug, $values, true)) {
            return true;
        }
    }

    return false;
}

function wp_insert_comment(array $data): int
{
    $state = &wdk_test_state();
    $commentId = $state['next_comment_id']++;
    $state['comments'][$commentId] = new WP_Comment(array_merge([
        'comment_ID' => $commentId,
        'comment_post_ID' => 0,
        'comment_author' => '',
        'comment_author_email' => '',
        'comment_content' => '',
        'comment_approved' => 0,
    ], $data));

    return $commentId;
}

function get_comment(int $commentId): ?WP_Comment
{
    return wdk_test_state()['comments'][$commentId] ?? null;
}

function get_comments(array $args = []): array
{
    return array_values(array_filter(wdk_test_state()['comments'], static function (WP_Comment $comment) use ($args) {
        if (!empty($args['post_id']) && $comment->comment_post_ID !== (int) $args['post_id']) {
            return false;
        }

        if (($args['status'] ?? 'all') === 'approve' && (string) $comment->comment_approved !== '1') {
            return false;
        }

        if (($args['status'] ?? 'all') === 'trash' && (string) $comment->comment_approved !== 'trash') {
            return false;
        }

        return true;
    }));
}

function wp_count_comments(int $postId): object
{
    $approved = 0;
    foreach (wdk_test_state()['comments'] as $comment) {
        if ($comment->comment_post_ID === $postId && (string) $comment->comment_approved === '1') {
            $approved++;
        }
    }

    return (object) ['approved' => $approved];
}

function wp_update_comment(array $args): int
{
    $commentId = (int) ($args['comment_ID'] ?? 0);
    $comment = get_comment($commentId);
    if (!$comment instanceof WP_Comment) {
        return 0;
    }

    foreach ($args as $key => $value) {
        if ($key === 'comment_ID') {
            continue;
        }

        $comment->{$key} = $value;
    }

    return $commentId;
}

function wp_trash_comment(int $commentId): bool
{
    $comment = get_comment($commentId);
    if (!$comment instanceof WP_Comment) {
        return false;
    }

    $comment->comment_approved = 'trash';

    return true;
}

function wp_untrash_comment(int $commentId): bool
{
    $comment = get_comment($commentId);
    if (!$comment instanceof WP_Comment) {
        return false;
    }

    $comment->comment_approved = 1;

    return true;
}

function wp_delete_comment(int $commentId, bool $forceDelete = true): bool
{
    $state = &wdk_test_state();
    unset($state['comments'][$commentId]);

    return true;
}

function wp_insert_attachment(array $attachment, string $file = '', int $parentPostId = 0): int
{
    $attachment['post_type'] = 'attachment';
    $attachment['post_parent'] = $parentPostId;
    $attachmentId = wp_insert_post($attachment);
    $state = &wdk_test_state();
    $state['attachments_meta'][$attachmentId] = [
        'file' => $file,
        'sizes' => [],
    ];

    return $attachmentId;
}

function wp_delete_attachment(int $attachmentId, bool $forceDelete = true): bool
{
    $state = &wdk_test_state();
    unset($state['attachments_meta'][$attachmentId]);

    return wp_delete_post($attachmentId, $forceDelete);
}

function get_attached_media(string $type = '', int $postId = 0): array
{
    $attachments = [];
    foreach (wdk_test_state()['posts'] as $post) {
        if ($post->post_type !== 'attachment' || $post->post_parent !== $postId) {
            continue;
        }

        if ($type !== '' && !str_starts_with($post->post_mime_type, $type . '/')) {
            continue;
        }

        $attachments[$post->ID] = $post;
    }

    return $attachments;
}

function set_post_thumbnail(int $postId, int $attachmentId): bool
{
    $state = &wdk_test_state();
    $state['featured_images'][$postId] = $attachmentId;

    return true;
}

function get_post_thumbnail_id(int $postId): int
{
    return (int) (wdk_test_state()['featured_images'][$postId] ?? 0);
}

function wp_get_attachment_metadata(int $attachmentId): array
{
    return wdk_test_state()['attachments_meta'][$attachmentId] ?? [];
}

function wp_get_attachment_image(int $attachmentId, string $size = 'thumbnail'): string
{
    $src = wp_get_attachment_image_src($attachmentId, $size);

    return "<img src=\"" . esc_attr((string) $src[0]) . "\" alt=\"attachment-{$attachmentId}\">";
}

function wp_get_attachment_image_src(int $attachmentId, string $size = 'thumbnail'): array
{
    return ["https://example.test/media/{$attachmentId}.jpg", 100, 100, true];
}

function get_option(string $key, mixed $default = false): mixed
{
    return wdk_test_state()['options'][$key] ?? $default;
}

function update_option(string $key, mixed $value): bool
{
    $state = &wdk_test_state();
    $state['options'][$key] = $value;

    return true;
}

function delete_option(string $key): bool
{
    $state = &wdk_test_state();
    unset($state['options'][$key]);

    return true;
}

function get_site_option(string $key, mixed $default = false): mixed
{
    return wdk_test_state()['site_options'][$key] ?? $default;
}

function is_multisite(): bool
{
    return false;
}

function current_user_can(string $capability, mixed ...$args): bool
{
    return (bool) (wdk_test_state()['current_user_caps'][$capability] ?? true);
}

function wp_nonce_field(string $action, string $name): void
{
    $nonce = wdk_test_create_nonce($action);
    echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($nonce) . '">';
}

function wp_verify_nonce(string $nonce, string $action): bool
{
    return $nonce === (wdk_test_state()['nonces'][$action] ?? null);
}

function wp_is_post_autosave(int $postId): bool
{
    return in_array($postId, wdk_test_state()['autosaves'], true);
}

function wp_is_post_revision(int $postId): bool
{
    return in_array($postId, wdk_test_state()['revisions'], true);
}

function get_query_var(string $key): mixed
{
    return wdk_test_state()['query_vars'][$key] ?? null;
}

function wp_reset_query(): void
{
}

function is_admin(): bool
{
    return (bool) wdk_test_state()['is_admin'];
}

function wp_parse_args(array $args, array $defaults): array
{
    return array_merge($defaults, $args);
}

function wp_dropdown_categories(array $args): void
{
    echo '<select name="' . esc_attr((string) ($args['name'] ?? 'taxonomy')) . '"></select>';
}

function get_the_tags(int $postId): array|false
{
    return get_the_terms($postId, 'post_tag');
}

function wp_list_pluck(array $list, string $field): array
{
    return array_map(static function ($item) use ($field) {
        return is_object($item) ? ($item->{$field} ?? null) : ($item[$field] ?? null);
    }, $list);
}

function plugin_dir_path(string $file): string
{
    return rtrim(dirname($file), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}

function get_template_directory(): string
{
    return dirname(__DIR__);
}

function get_current_screen(): object
{
    return (object) ['is_block_editor' => false];
}

function is_plugin_active(string $plugin): bool
{
    return false;
}

function wp_remote_request(string $url, array $args = []): array|WP_Error
{
    $state = &wdk_test_state();
    $state['last_http_request'] = [
        'url' => $url,
        'args' => $args,
    ];

    if ($state['http_queue'] === []) {
        return new WP_Error('http_queue_empty', 'No queued HTTP response available.');
    }

    $response = array_shift($state['http_queue']);
    if (!empty($response['error'])) {
        return new WP_Error('http_error', (string) $response['error']);
    }

    return [
        'response' => ['code' => (int) ($response['status'] ?? 200)],
        'body' => $response['body'] ?? '',
        'headers' => $response['headers'] ?? [],
    ];
}

function wp_remote_retrieve_response_code(array|WP_Error $response): int
{
    return is_array($response) ? (int) ($response['response']['code'] ?? 0) : 0;
}

function wp_remote_retrieve_body(array|WP_Error $response): string
{
    return is_array($response) ? (string) ($response['body'] ?? '') : '';
}

function wp_remote_retrieve_headers(array|WP_Error $response): array
{
    return is_array($response) ? (array) ($response['headers'] ?? []) : [];
}

wdk_test_reset_state();

require_once dirname(__DIR__) . '/wdk-runtime-loader.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/WdkTestCase.php';
