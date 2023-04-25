<?php
namespace MLA\Search;
use WDK\Search;
use WDK\WP_Search_Provider;

class AWS_Elastic_Search_Provider extends WP_Search_Provider {


    private $es_client;
    private $index;
    private $doc_type;

    public function __construct( $args = array() ) {
        $config = [
            'region' => defined('AWS_REGION') ? constant('AWS_REGION') : 'us-east-1',
            'access_key' => defined('AWS_ACCESS_KEY') ? constant('AWS_ACCESS_KEY') : 'YourAccessKeyHere',
            'secret_key' => defined('AWS_SECRET_KEY') ? constant('AWS_SECRET_KEY') : 'YourSecretKeyHere',
            'endpoint' => defined('AWS_ELASTIC_ENDPOINT') ? constant('AWS_ELASTIC_ENDPOINT') : 'https://search-your-instance-id.us-east-1.es.amazonaws.com',
            'index' => defined('AWS_ELASTIC_INDEX') ? constant('AWS_ELASTIC_INDEX') : 'your_index_name',
            'doc_type' => defined('AWS_ELASTIC_DOC_TYPE') ? constant('AWS_ELASTIC_DOC_TYPE') : '_doc',
        ];

        $args = wp_parse_args( $args, $config );

        $this->es_client = ClientBuilder::create()
            ->setHosts([$args['endpoint']])
            ->setHttpClient(new AwsHttpHandler([
                'region' => $args['region'],
                'version' => 'latest',
                'credentials' => new Credentials(
                    $args['access_key'],
                    $args['secret_key']
                ),
            ]))
            ->build();

        $this->index = $args['index'];
        $this->doc_type = $args['doc_type'];
    }
    /**
     * @param $endpoint
     * @param $index
     * @param $region
     * @param $access_key
     * @param $secret_key
     * @return void
     */
    public static function register_elastic_indexing($endpoint = null, $index = null, $region = null, $access_key = null, $secret_key = null)
    {
        $endpoint = $endpoint ?? defined('AWS_ELASTIC_ENDPOINT') ? constant('AWS_ELASTIC_ENDPOINT') : 'https://search-your-instance-id.us-east-1.es.amazonaws.com';
        $index = $index ?? defined('AWS_ELASTIC_INDEX') ? constant('AWS_ELASTIC_INDEX') : 'your_index_name';
        $access_key = $access_key ?? defined('AWS_ACCESS_KEY') ? constant('AWS_ACCESS_KEY') : 'YourAccessKeyHere';
        $secret_key = $secret_key ?? defined('AWS_SECRET_KEY') ? constant('AWS_SECRET_KEY') : 'YourSecretKeyHere';

        $aws_args = [
            'region' => $region,
            'access_key' => $access_key,
            'secret_key' => $secret_key,
            'endpoint' => $endpoint,
            'index' => $index,
        ];

        $provider = new self($aws_args);

        add_action('wp_insert_post', [$provider, 'add_post_to_elastic_search']);
        add_action('edit_post', [$provider, 'update_post_in_elastic_search']);
        add_action('delete_post', [$provider, 'delete_post_from_elastic_search']);
    }

    /**
     * Adds a post to the ElasticSearch index.
     *
     * @param int $post_id The ID of the post to add.
     * @return array An array with the status, message, and data keys.
     */
    public function add_post_to_elastic_search($post_id): array
    {
        $response = [
            'status' => 'error',
            'message' => '',
            'data' => []
        ];

        if (!class_exists('Elasticsearch\Client')) {
            $response['message'] = 'Elasticsearch client is not installed';
            return $response;
        }

        $post = get_post($post_id);

        // Check if the post is of the desired post type
        $allowed_post_types = get_option('wdk_elastic_post_types');
        if (empty($allowed_post_types)) {
            $allowed_post_types = ['post'];
        } else {
            $allowed_post_types = is_array($allowed_post_types) ? $allowed_post_types : explode(',', $allowed_post_types);
        }
        if (!in_array($post->post_type, $allowed_post_types, true)) {
            $response['message'] = 'Post is not of the desired post type';
            return $response;
        }

        // Create an ElasticSearch document object for the post
        $doc = $this->create_elastic_document($post);

        // Add any additional fields to the document using the hook
        $doc = apply_filters('wdk_elastic_doc_fields', $doc, $post);

        // Index the document in ElasticSearch
        try {
            $params = [
                'index' => $this->index,
                'type' => $this->type,
                'id' => $post_id,
                'body' => $doc->getParams()['body'],
            ];
            $this->es_client->index($params);

            $response['status'] = 'success';
            $response['message'] = 'Post successfully added to ElasticSearch';
            $response['data'] = $doc->getParams()['body'];
        } catch (\Exception $e) {
            $response['status'] = 'fail';
            $response['message'] = 'Error adding post to ElasticSearch: ' . $e->getMessage();
        }

        return $response;
    }

    /**
     * @param $post_id
     * @return array
     */
    public function update_post_in_elastic_search($post_id): array
    {
        $response = [
            'status' => 'error',
            'message' => '',
            'data' => []
        ];

        if (!class_exists('Elasticsearch\ClientBuilder')) {
            $response['message'] = 'Elasticsearch client is not installed';
            return $response;
        }

        $post = get_post($post_id);

        // Check if the post is of the desired post type
        $allowed_post_types = get_option('wdk_elastic_post_types');
        if (empty($allowed_post_types)) {
            $allowed_post_types = ['post'];
        } else {
            $allowed_post_types = is_array($allowed_post_types) ? $allowed_post_types : explode(',', $allowed_post_types);
        }
        if (!in_array($post->post_type, $allowed_post_types, true)) {
            $response['message'] = 'Post is not of the desired post type';
            return $response;
        }

        // Create an ElasticSearch document object for the post
        $doc = $this->create_elastic_search_document($post);

        // Update the corresponding record in ElasticSearch
        try {
            $params = [
                'index' => $this->index,
                'type' => $this->type,
                'id' => $post_id,
                'body' => [
                    'doc' => $doc->getParams()
                ]
            ];
            $this->es_client->update($params);

            $response['status'] = 'success';
            $response['message'] = 'Post successfully updated in ElasticSearch';
            $response['data'] = $doc->getParams();
        } catch (\Exception $e) {
            $response['status'] = 'fail';
            $response['message'] = 'Error updating post in ElasticSearch: ' . $e->getMessage();
        }

        return $response;
    }

    /**
     * Delete a post from Elasticsearch index.
     *
     * @param int $post_id ID of the post to be deleted.
     * @return array An array with status, message, and data keys.
     */
    public function delete_post_from_elastic_search($post_id): array
    {
        $response = [
            'status' => 'error',
            'message' => '',
            'data' => []
        ];

        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'id' => $post_id
        ];

        try {
            $this->es_client->delete($params);
            $response['status'] = 'success';
            $response['message'] = 'Post successfully deleted from Elasticsearch index';
            $response['data'] = ['post_id' => $post_id];
        } catch (\Exception $e) {
            $response['message'] = 'Error deleting post from Elasticsearch index: ' . $e->getMessage();
        }

        return $response;
    }

    public function search( string $query, ?array $args = array() ) {
        $args = wp_parse_args( $args, array(
            'size' => 10,
            'from' => 0,
            'sort_field' => 'date',
            'sort_order' => 'desc'
        ) );

        $params = [
            'index' => $this->index,
            'type' => $this->type,
            'body' => [
                'from' => $args['from'],
                'size' => $args['size'],
                'sort' => [
                    $args['sort_field'] => [
                        'order' => $args['sort_order'],
                    ],
                ],
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'match' => [
                                    'post_title' => $query,
                                ],
                            ],
                            [
                                'match' => [
                                    'post_content' => $query,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $results = $this->es_client->search( $params );

        $post_ids = array();
        if ( isset( $results['hits'] ) && isset( $results['hits']['hits'] ) ) {
            foreach ( $results['hits']['hits'] as $hit ) {
                $post_ids[] = $hit['_id'];
            }
        }

        $wp_query_args = array(
            'post_type' => array( 'post', 'page' ),
            'post__in' => $post_ids,
            'orderby' => 'post__in',
            'posts_per_page' => $args['size'],
            'offset' => $args['from']
        );
        return new \WP_Query( $wp_query_args );
    }
}

/**
 * $search_manager = new Search('Elasticsearch_Search_Provider');

$query = 'example search query';
$args = [
'posts_per_page' => 10,
'paged' => get_query_var('paged') ?: 1,
'meta_query' => [
[
'key' => 'example_meta_key',
'value' => 'example_meta_value',
'compare' => '='
]
]
];

$results = $search_manager->search($query, $args);

// Output the results
if ($results->have_posts()) {
while ($results->have_posts()) {
$results->the_post();
// Display the post
}
} else {
// No results found
}
 */