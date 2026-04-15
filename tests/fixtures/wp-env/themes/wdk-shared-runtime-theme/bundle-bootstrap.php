<?php
/**
 * Fixture theme file used by the shared runtime coexistence suite.
 *
 * @package WDK\Tests
 */


update_option('wdk_process_template_page', true);
update_option('wdk_process_template_page_wdk-coexistence', 'coexistence');

add_filter('wdk_context_coexistence', static function (array $context): array {
    $context['runtime_info'] = function_exists('wdk_runtime_info') ? wdk_runtime_info() : [];
    $context['theme_marker'] = 'theme-template-active';

    return $context;
});
