<?php
/**
 * Contains the WP_Search_Provider class.
 *
 * @package WDK
 */

namespace WDK;
/**
 * Provides the base implementation for WP Search Provider.
 */
abstract class WP_Search_Provider {
    abstract public function search( string $query, ?array $args = [] );
}