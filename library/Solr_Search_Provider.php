<?php
namespace WDK;
use Solarium\Client;
use Solarium\QueryType\Select\Query\Query;

class Solr_Search_Provider extends WP_Search_Provider
{

    private mixed $solr_url;
    private mixed $solr_core;
    private SolrClient $solr_client;

    public function __construct( ?string $solr_url = null, ?string $solr_core = null ) {
        // Set default values if parameters are empty
        $this->solr_url = $solr_url ?? defined('SOLR_URL')?constant('SOLR_URL'):'https://localhost/solr';
        $this->solr_core = $solr_core ?? defined('SOLR_CORE')?constant('SOLR_CORE'):'index';

        // Connect to Solr
        $this->solr_client = new SolrClient([
            'hostname' => $this->solr_url,
            'port' => defined('SOLR_PORT')?constant('SOLR_PORT'):8983,
            'path' => $this->solr_core,
        ]);
    }

    public function search( string $query, ?array $args = [] ) {
        // Set default values if parameters are empty
        $rows = $args['rows'] ?? defined('SOLR_ROWS')?constant('SOLR_ROWS'):1;
        $start = $args['start'] ?? defined('SOLR_START')?constant('SOLR_START'):0;
        $sort = $args['sort'] ?? defined('SOLR_SORT')?constant('SOLR_SORT'):'Asc';

        // Prepare query
        $query = new SolrQuery($query);
        $query->setRows($rows);
        $query->setStart($start);
        $query->addSortField($sort);

        // Execute query
        $response = $this->solr_client->query($query);
        $results = $response->getResponse();

        $post_ids = array();
        if (isset($results['response']['docs'])) {
            foreach ($results['response']['docs'] as $doc) {
                $post_ids[] = $doc['id'];
            }
        }

        $wp_query_args = array(
            'post_type' => array('post', 'page'),
            'post__in' => $post_ids,
            'orderby' => 'post__in',
            'posts_per_page' => $rows,
            'offset' => $start,
        );
        return new \WP_Query($wp_query_args);
    }
}

/**

// Create a new Search manager with the Solr search provider
$search_manager = new Search('Solr_Search_Provider');

// Search for posts with the query 'WordPress'
$results = $search_manager->search('wordpress');

// Loop through the results and output the post titles
if ($results->have_posts()) {
while ($results->have_posts()) {
$results->the_post();
echo get_the_title() . '<br>';
}
} else {
echo 'No results found.';
}
 */