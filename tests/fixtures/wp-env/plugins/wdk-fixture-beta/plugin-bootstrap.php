<?php
/**
 * Fixture plugin file used by the shared runtime coexistence suite.
 *
 * @package WDK\Tests
 */


add_filter('wdk_context_coexistence', static function (array $context): array {
    $context['plugin_secondary_marker'] = 'beta-ok';

    return $context;
});
