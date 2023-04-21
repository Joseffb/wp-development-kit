<?php
namespace MLA\Search;
use WDK\Search;
use WDK\WP_Search_Provider;

class AWS_Elastic_Search_Provider extends WP_Search_Provider {
    private $es_client;
    private mixed $index;
    private mixed $type;

    public function __construct( $args = array() ) {
        $config = [
            'region' => defined('AWS_REGION') ? constant('AWS_REGION') : 'us-east-1',
            'access_key' => defined('AWS_ACCESS_KEY') ? constant('AWS_ACCESS_KEY') : 'YourAccessKeyHere',
            'secret_key' => defined('AWS_SECRET_KEY') ? constant('AWS_SECRET_KEY') : 'YourSecretKeyHere',
            'endpoint' => defined('AWS_ELASTIC_ENDPOINT') ? constant('AWS_ELASTIC_ENDPOINT') : 'https://search-your-instance-id.us-east-1.es.amazonaws.com',
            'index' => defined('AWS_ELASTIC_INDEX') ? constant('AWS_ELASTIC_INDEX') : 'your_index_name',
            'type' => defined('AWS_ELASTIC_TYPE') ? constant('AWS_ELASTIC_TYPE') : '_doc',
        ];

        $args = wp_parse_args( $args, $config );

        $this->es_client = Elasticsearch\ClientBuilder::create()
            ->setRegion($args['region'])
            ->setHosts([$args['endpoint']])
            ->setConnectionParams([
                'client' => [
                    'timeout' => 30,
                ],
                'http' => [
                    'timeout' => 30,
                ],
            ])
            ->setHttpClient(
                new AwsSigningHttpClient(
                    new CurlHttpClient(),
                    new CredentialProvider(
                        $args['access_key'],
                        $args['secret_key']
                    ),
                    $args['region'],
                    'es',
                    new NullLogger()
                )
            )
            ->build();

        $this->index = $args['index'];
        $this->type = $args['type'];
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