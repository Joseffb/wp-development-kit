<?php
namespace WDK;
abstract class WP_Search_Provider {
    abstract public function search( string $query, ?array $args = [] );
}