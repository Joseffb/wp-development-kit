<?php
namespace WDK;

class CiviCRM_Search_Provider extends WP_Search_Provider {

    private ?string  $api_key;
    private ?string  $site_key;
    private ?string $base_url;
    private ?string  $default_entity;
    private ?string  $default_route;
    private ?string  $default_method;

    public function __construct( $api_key = null, $site_key = null, $base_url = null, $default_entity = 'MLA', $default_route = 'get', $default_method = 'POST' ) {
        $this->api_key = $api_key ?? defined('CIVI_API_KEY')?constant('CIVI_API_KEY'):null;
        $this->site_key = $site_key ?? defined('CIVI_SITE_KEY')?constant('CIVI_SITE_KEY'):null;
        $this->base_url = $base_url ?? defined('CIVI_SERVER')&&defined('CIVI_PATH')?constant('CIVI_SERVER').constant('CIVI_PATH'):null;
        $this->default_entity = $default_entity;
        $this->default_route = $default_route;
        $this->default_method = $default_method;
    }

    /**
     * @param string $query
     * @param array|null $args
     * @return \WP_Query|\WP_Error
     */
    public function search(string $query, ?array $args = [] ): \WP_Query|\WP_Error
    {
        $entity = $args['entity'] ?? $this->default_entity;
        $route = $args['route'] ?? $this->default_route;
        $method = $args['method'] ?? $this->default_method;

        $request_url = "{$this->base_url}/civicrm/{$entity}/{$route}";
        try {
            $request_args = array(
                'method' => $method,
                'timeout' => 60,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $this->api_key,
                    'X-Processing-Key' => $this->site_key,
                ),
                'body' => json_encode(array(
                    'name' => $query
                ), JSON_THROW_ON_ERROR),
            );
        } catch(\JsonException $e){
            return new \WP_Error( 'civicrm_api_error', $e->getMessage(), $e );
        }

        $response = wp_remote_request( $request_url, $request_args );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'civicrm_api_error', $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        try {
            $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return new \WP_Error( 'json_decode_error', $e->getMessage(), $e );
        }

        if ( ! isset( $result['is_error'] ) || $result['is_error'] ) {
            return new \WP_Error( 'civicrm_api_error', $result['error_message'] );
        }

        $posts = array_map(static function ($result_item) {
            return (object) [
                'ID' => $result_item['ID'] ?? '',
                'post_title' => $result_item['post_title'] ?? '',
                'post_type' => $result_item['post_type'] ?? '',
                'post_status' => $result_item['post_status'] ?? '',
                'post_date' => $result_item['post_date'] ?? '',
                'post_content' => $result_item['post_content'] ?? '',
                'return_data' => $result_item, // Include all original data
            ];
        }, $result['values']);

        return Search::wp_query_return($posts, count( $posts ), 1);
    }
}

/**
 * Example Usage:
 *
$provider = new Search('CiviCRM_Search_Provider');
$results = $provider->search( 'John Doe', [
'entity' => 'ABC',
'route' => '_custom_route',
'method' => 'POST',
] );
 */