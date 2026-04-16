<?php
/**
 * Fixture plugin bootstrap used by the shared runtime coexistence suite.
 *
 * @package WDK\Tests
 */


add_filter('wdk_context_coexistence', static function (array $context): array {
    $context['plugin_secondary_marker'] = 'legacy-ok';

    return $context;
});
