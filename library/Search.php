<?php
namespace WDK;

class Search {
    protected WP_Local_Search_Provider|WP_Search_Provider $search_provider;

    public function __construct( WP_Search_Provider $search_provider = null ) {
        if ( is_null( $search_provider ) ) {
            $this->search_provider = new WP_Local_Search_Provider();
        } else {
            $this->search_provider = $search_provider;
        }
    }

    public function set_search_provider( WP_Search_Provider $search_provider ): void
    {
        $this->search_provider = $search_provider;
    }

    public function search( string $query, ?array $arg=[] ) {
        return $this->search_provider->search( $query, $arg );
    }
}