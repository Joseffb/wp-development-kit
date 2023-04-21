<?php
namespace WDK;

/**
 * Example Provider for generic remote API
 */
class Remote_API_Search_Provider extends WP_Search_Provider {
    protected $endpoint;
    protected $method;
    protected $headers;
    protected $params;

    public function __construct( $endpoint = '', $method = 'GET', $headers = [], $params = [] ) {
        $this->endpoint = $endpoint;
        $this->method = $method;
        $this->headers = $headers;
        $this->params = $params;
    }

    public function search( $query, $args = [] ): \WP_Query
    {
        $args = wp_parse_args( $args, [
            'response_type' => 'json',
            'search_field' => '',
        ]);

        $headers = array_merge( [
            'Content-Type' => 'application/' . $args['response_type'],
        ], $this->headers );

        $this->params[ $args['search_field'] ] = $query;

        $response = wp_remote_request( $this->endpoint, [
            'method' => $this->method,
            'headers' => $headers,
            'body' => $this->params,
        ]);

        if ( is_wp_error( $response ) ) {
            return [
                'posts' => [],
                'found_posts' => 0,
                'max_num_pages' => 0,
            ];
        }

        $data = wp_remote_retrieve_body( $response );

        // Parse data based on response type
        switch ( $args['response_type'] ) {
            case 'json':
                try {
                    $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $data = [
                        "status" => "failed",
                        "message" => $e->getMessage()
                    ];
                }
                break;
            case 'xml':
                try {
                    $data = simplexml_load_string($data);
                } catch (\JsonException $e) {
                    $data = [
                        "status" => "failed",
                        "message" => $e->getMessage()
                    ];
                }
                break;
            case 'graphql':
                try {
                    $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                    if (isset($data['errors']) && !empty($data['errors'])) {
                        throw new \RuntimeException('GraphQL error: ' . json_encode($data['errors'], JSON_THROW_ON_ERROR));
                    }
                    $data = $data['data'] ?? [
                        "status" => "failed",
                        "message" => 'Invalid GraphQL response format.'
                    ];
                } catch (\JsonException | \RuntimeException $e) {
                    $data = [
                        "status" => "failed",
                        "message" => $e->getMessage()
                    ];
                }
                break;
            default:
                // Handle unsupported response types
                $data = [
                    "status" => "failed",
                    "message" => 'Unsupported api response type. Response must be XML or JSON.'
                ];
                break;
        }

        // Extract search results
        $results = $data['results'] ?? [];

        $posts = array_map( static function($result ) {
            return [
                'ID' => $result['ID'] ?? '',
                'post_title' => $result['post_title'] ?? '',
                'post_type' => $result['post_type'] ?? '',
                'post_status' => $result['post_status'] ?? '',
                'post_date' => $result['post_date'] ?? '',
                'post_content' => $result['post_content'] ?? '',
                'return_data' => $result, // Include all original data
            ];
        }, $results );
        return Search::wp_query_return($posts, count( $results ), 1);
    }
}