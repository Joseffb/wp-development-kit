<?php

namespace WDK;

use GRAPHQLWP\GRAPHQLWP;

/**
 * A custom search provider using GraphQLWP to search for posts using GraphQL.
 *
 * Example usage:
 *
 * // Create a new instance of the Search class with the GraphQL_Search_Provider
 * $search = new Search('GraphQL_Search_Provider');
 *
 * // Set the fields to return within the search results
 * $fields = [
 *     'id',
 *     'title',
 *     'excerpt',
 *     'uri',
 *     'date',
 *     'featuredImage' => [
 *         'node' => [
 *             'sourceUrl'
 *         ]
 *     ],
 *     'meta' => [
 *         'custom_meta_field'
 *     ]
 * ];
 *
 * // Set the args to search by post type and taxonomy term
 * $args = [
 *     'post_type' => 'my_custom_post_type',
 *     'tax_query' => [
 *         [
 *             'taxonomy' => 'my_custom_taxonomy',
 *             'field' => 'slug',
 *             'terms' => 'my_custom_taxonomy_term'
 *         ]
 *     ]
 * ];
 *
 * // Set the meta query to search by custom meta field
 * $meta_query = [
 *     [
 *         'key' => 'custom_meta_field',
 *         'value' => 'custom_meta_value',
 *         'compare' => 'LIKE'
 *     ]
 * ];
 *
 * // Add the meta query to the search args
 * $args['meta_query'] = $meta_query;
 *
 * // Search for posts
 * $results = $search->search('Your search query here', $args, $fields);
 *
 * // Loop through the search results
 * if ($results->have_posts()) {
 *     while ($results->have_posts()) {
 *         $results->the_post();
 *
 *         // Display post information
 *         the_title();
 *         the_excerpt();
 *         // Display custom meta field value
 *         echo get_post_meta(get_the_ID(), 'custom_meta_field', true);
 *     }
 * }
 */
class GraphQL_Search_Provider extends WP_Search_Provider
{

    protected GRAPHQLWP $graphql;
    protected array $fields;

    public function __construct(array $fields = []) {
        if (!class_exists('GRAPHQLWP\GRAPHQLWP')) {
            throw new \BadMethodCallException('GraphQLWP plugin is not installed.');
        }
        $this->graphql = new GRAPHQLWP();
        $this->fields = $this->build_fields($fields);
    }

    public function search($query, $args = []) {
        try {
            $result = $this->graphql->executeQuery($this->build_query($query), []);
        } catch (\Exception $e) {
            return new \WP_Error('graphql_error', $e->getMessage());
        }

        if ($result->hasErrors()) {
            return new \WP_Error('graphql_error', $result->getErrors());
        }

        $data = $result->getData();

        return search::wp_query_return($data['posts']);
    }

    /**
     * @param $query
     * @param array|null $fields
     * @param string|null $post_type
     * @param array|null $taxonomies
     * @param array|null $meta_query
     * @return string
     */
    protected function build_query($query, ?array $fields = null, ?string $post_type = null, ?array $taxonomies = null, ?array $meta_query = null): string
    {
        $fields = $fields ?? $this->fields;
        $fields_query = $this->build_fields_query($fields);

        $where = 'search: "' . $query . '"';
        if ($post_type) {
            $where .= ', postType: "' . $post_type . '"';
        }
        if ($taxonomies) {
            foreach ($taxonomies as $taxonomy => $terms) {
                $where .= ', ' . $taxonomy . ': {terms: ["' . implode('", "', $terms) . '"]}';
            }
        }
        if ($meta_query) {
            $meta_where = [];
            foreach ($meta_query as $meta) {
                if (isset($meta['key'], $meta['value'])) {
                    $meta_where[] = '{key: "' . $meta['key'] . '", value: "' . $meta['value'] . '"}';
                }
            }
            if (!empty($meta_where)) {
                $where .= ', metaQuery: {relation: AND, metaArray: [' . implode(', ', $meta_where) . ']}';
            }
        }

        $gql_query = <<<GQL
query {
  posts(where: {$where}) {
    nodes {
      $fields_query
    }
  }
}
GQL;

        return $gql_query;
    }

    protected function build_fields($fields): array {
        $default_fields = [
            'id',
            'title',
            'excerpt',
            'uri',
            'date',
            'featuredImage' => [
                'node' => [
                    'sourceUrl'
                ]
            ]
        ];
        return array_merge_recursive($default_fields, $fields);
    }

    protected function build_fields_query($fields): string
    {
        $fields_query = [];

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $fields_query[] = $key . ' {' . $this->build_fields_query($value) . '}';
            } else {
                $fields_query[] = $value;
            }
        }

        return implode("\n", $fields_query);
    }
}