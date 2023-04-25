<?php

namespace WDK;

use Solarium\Client;
use Solarium\QueryType\Select\Query\Query;

class Solr_Search_Provider extends WP_Search_Provider
{

    private string $solr_url = 'localhost';
    private ?string $solr_core = '/solr/mycore';
    private int $solr_port = 8983;
    private SolrClient $solr_client;

    public function __construct(?string $solr_url = null, ?string $solr_core = null, ?int $solr_port = null)
    {
        // Set default values if parameters are empty
        $this->solr_url = $solr_url ?? defined('SOLR_URL') ? constant('SOLR_URL') : $this->solr_url;
        $this->solr_core = $solr_core ?? defined('SOLR_CORE') ? constant('SOLR_CORE') : $this->solr_core;
        $this->solr_port = $solr_port??$this->solr_port;

        // Connect to Solr
        $this->solr_client = new SolrClient([
            'hostname' => $this->solr_url,
            'port' => $this->solr_port,
            'path' => $this->solr_core,
        ]);
    }

    function is_available($host, $port, $path = '/solr/mycore'): bool
    {
        $url = "http://$host:$port$path";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpcode === 200;
    }

    public static function register_solr_indexing($hostname = null, $port = null, $path = null)
    {
        $port = $port ?? defined('SOLR_PORT') ? constant('SOLR_PORT') : 8983;
        $hostname = $hostname ?? defined('SOLR_URL') ? constant('SOLR_URL') : 'localhost';
        $path = $path ?? defined('SOLR_CORE') ? constant('SOLR_CORE') : '/solr/mycore';

        $solr = new self($hostname, $path, $port);
        add_action('wp_insert_post', [$solr, 'add_post_to_solr']);
        add_action('edit_post', [$solr, 'update_post_in_solr']);
        add_action('delete_post', [$solr, 'delete_post_from_solr']);
    }

    /**
     * @param $post_id
     * @return array
     */
    public function add_post_to_solr($post_id): array
    {
        $response = [
            'status' => 'error',
            'message' => '',
            'data' => []
        ];

        if (!class_exists('Solarium\Client')) {
            $response['message'] = 'Solarium client is not installed';
            return $response;
        }

        $post = get_post($post_id);

        // Check if the post is of the desired post type
        $allowed_post_types = get_option('wdk_solr_post_types');
        if (empty($allowed_post_types)) {
            $allowed_post_types = ['post'];
        } else {
            $allowed_post_types = is_array($allowed_post_types) ? $allowed_post_types : explode(',', $allowed_post_types);
        }
        if (!in_array($post->post_type, $allowed_post_types, true)) {
            $response['message'] = 'Post is not of the desired post type';
            return $response;
        }

        // Create a Solr document object for the post
        $doc = $this->create_solr_document($post);

        // Add any additional fields to the document using the hook
        $doc = apply_filters('wdk_solr_doc_fields', $doc, $post);

        // Add the document to Solr
        try {
            $this->solr_client->addDocument($doc);
            $this->solr_client->commit();

            $response['status'] = 'success';
            $response['message'] = 'Post successfully added to Solr';
            $response['data'] = $doc->getFieldValues();
        } catch (\RuntimeException $e) {
            $response['status'] = 'fail';
            $response['message'] = 'Error adding post to Solr: ' . $e->getMessage();
        }

        return $response;
    }

    /**
     * Update a post in Solr index.
     *
     * @param int $post_id ID of the post to be updated.
     * @return array An array with status, message, and data keys.
     */
    public function update_post_in_solr($post_id): array
    {
        $response = [
            'status' => 'error',
            'message' => '',
            'data' => []
        ];

        if (!class_exists('Solarium\Client')) {
            $response['message'] = 'Solarium client is not installed';
            return $response;
        }

        $post = get_post($post_id);

        // Check if the post is of the desired post type
        $allowed_post_types = get_option('wdk_solr_post_types');
        if (empty($allowed_post_types)) {
            $allowed_post_types = ['post'];
        } else {
            $allowed_post_types = is_array($allowed_post_types) ? $allowed_post_types : explode(',', $allowed_post_types);
        }
        if (!in_array($post->post_type, $allowed_post_types, true)) {
            $response['message'] = 'Post is not of the desired post type';
            return $response;
        }

        // Create a Solr document object for the post
        $doc = $this->create_solr_document($post);

        // Update the corresponding record in Solr
        try {
            $this->solr_client->addDocument($doc);
            $this->solr_client->commit();

            $response['status'] = 'success';
            $response['message'] = 'Post successfully updated in Solr';
            $response['data'] = $doc->getFieldValues();
        } catch (\RuntimeException $e) {
            $response['status'] = 'fail';
            $response['message'] = 'Error updating post in Solr: ' . $e->getMessage();
        }

        return $response;
    }

    /**
     * @param $post_id
     * @return array
     */
    public function delete_post_from_solr($post_id): array
    {
        $response = [
            'status' => 'error',
            'message' => '',
            'data' => []
        ];

        if (!class_exists('Solarium\Client')) {
            $response['message'] = 'Solarium client is not installed';
            return $response;
        }

        $post = get_post($post_id);

        // Check if the post is of the desired post type
        $allowed_post_types = get_option('wdk_solr_post_types');
        if (empty($allowed_post_types)) {
            $allowed_post_types = ['post'];
        } else {
            $allowed_post_types = is_array($allowed_post_types) ? $allowed_post_types : explode(',', $allowed_post_types);
        }
        if (!in_array($post->post_type, $allowed_post_types, true)) {
            $response['message'] = 'Post is not of the desired post type';
            return $response;
        }

        // Delete the corresponding record from Solr
        try {
            $query = "id:$post_id";
            $this->solr_client->deleteByQuery($query);
            $this->solr_client->commit();

            $response['status'] = 'success';
            $response['message'] = 'Post successfully deleted from Solr';
            $response['data'] = ['post_id' => $post_id];
        } catch (\RuntimeException $e) {
            $response['status'] = 'fail';
            $response['message'] = 'Error deleting post from Solr: ' . $e->getMessage();
        }

        return $response;
    }
    /**usage:
     *
    add_filter('wdk_solr_doc_fields', 'my_custom_solr_fields', 10, 2);

    function my_custom_solr_fields($doc, $post) {
        $doc->addField('custom_field', 'Custom Value');
        return $doc;
    }
     * @param $post
     * @return SolrInputDocument
     */
    private function create_solr_document($post = null): SolrInputDocument
    {
        if (empty($post)) {
            throw new \InvalidArgumentException('Post parameter cannot be null.');
        }

        $doc = new SolrInputDocument();
        $doc->addField('id', $post->ID);
        $doc->addField('title', $post->post_title);
        $doc->addField('content', $post->post_content);

        // Allow users to add or modify fields using a filter hook
        return apply_filters('wdk_solr_doc_fields', $doc, $post);
    }

    /**
     * @param string $query
     * @param array|null $args
     * @return \WP_Query
     */
    public function search(string $query, ?array $args = []): \WP_Query
    {
        // Set default values if parameters are empty
        $rows = $args['rows'] ?? defined('SOLR_ROWS') ? constant('SOLR_ROWS') : 1;
        $start = $args['start'] ?? defined('SOLR_START') ? constant('SOLR_START') : 0;
        $sort = $args['sort'] ?? defined('SOLR_SORT') ? constant('SOLR_SORT') : 'Asc';

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
 *
 * // Create a new Search manager with the Solr search provider
 * $search_manager = new Search('Solr_Search_Provider');
 *
 * // Search for posts with the query 'WordPress'
 * $results = $search_manager->search('wordpress');
 *
 * // Loop through the results and output the post titles
 * if ($results->have_posts()) {
 * while ($results->have_posts()) {
 * $results->the_post();
 * echo get_the_title() . '<br>';
 * }
 * } else {
 * echo 'No results found.';
 * }
 */