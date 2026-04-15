<?php
/**
 * Test support definitions for the Timber component.
 *
 * @package WDK\Tests
 */


declare(strict_types=1);

/**
 * Provides a lightweight Timber stub for tests.
 */
class Timber
{
    public static array $locations = [];
    public static array $render_calls = [];

    public static function set_locations(array $locations): void
    {
        self::$locations = $locations;
    }

    public static function get_context(): array
    {
        return ['source' => 'legacy'];
    }

    public static function render($templates, array $context): void
    {
        self::$render_calls[] = [$templates, $context];
    }

    public static function get_widgets(): string
    {
        return 'legacy-widgets';
    }
}

/**
 * Provides a lightweight TimberPost stub for tests.
 */
class TimberPost
{
    public $value;

    public function __construct($value = null)
    {
        $this->value = $value;
    }
}

/**
 * Provides a lightweight TimberPostQuery stub for tests.
 */
class TimberPostQuery implements IteratorAggregate
{
    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }
}

require_once __DIR__ . '/../../library/TimberBridge.php';

WDK\TimberBridge::set_locations(['/legacy/views']);

if (!WDK\TimberBridge::is_available()) {
    fwrite(STDERR, "Expected legacy Timber class to be detected.\n");
    exit(1);
}

if (Timber::$locations !== ['/legacy/views']) {
    fwrite(STDERR, "Expected legacy Timber locations to be updated.\n");
    exit(1);
}

$context = WDK\TimberBridge::context();
if (($context['source'] ?? null) !== 'legacy') {
    fwrite(STDERR, "Expected context() to fall back to legacy get_context().\n");
    exit(1);
}

WDK\TimberBridge::render(['index.twig'], ['foo' => 'bar']);
if ((Timber::$render_calls[0][0][0] ?? null) !== 'index.twig') {
    fwrite(STDERR, "Expected render() to proxy to the legacy Timber class.\n");
    exit(1);
}

$post = WDK\TimberBridge::get_post(42);
if (!$post instanceof TimberPost || $post->value !== 42) {
    fwrite(STDERR, "Expected get_post() to fall back to TimberPost.\n");
    exit(1);
}

if (!WDK\TimberBridge::is_post($post)) {
    fwrite(STDERR, "Expected TimberPost instances to be recognized.\n");
    exit(1);
}

if (!WDK\TimberBridge::is_post_query(new TimberPostQuery())) {
    fwrite(STDERR, "Expected TimberPostQuery instances to be recognized.\n");
    exit(1);
}

if (WDK\TimberBridge::get_widgets() !== 'legacy-widgets') {
    fwrite(STDERR, "Expected get_widgets() to proxy to the legacy Timber class.\n");
    exit(1);
}

echo "PASS: timber_bridge_compat_test\n";
