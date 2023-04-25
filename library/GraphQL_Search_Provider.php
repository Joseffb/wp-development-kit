<?php

namespace WDK;

use GRAPHQLWP\GRAPHQLWP;

/**
 * A custom search provider using GraphQLWP to search for posts using GraphQL.
 *
 * Example usage for searching posts:
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
 * // Search for posts
 * $search = new Search('GraphQL_Search_Provider');
 * $post_results = $search->search('Your search query here', ['post_type' => 'post'], $fields);
 *
 * // Loop through the search results
 * if ($post_results->have_posts()) {
 *     while ($post_results->have_posts()) {
 *         $post_results->the_post();
 *
 *         // Display post information
 *         the_title();
 *         the_excerpt();
 *         // Display custom meta field value
 *         echo get_post_meta(get_the_ID(), 'custom_meta_field', true);
 *     }
 * }
 */

/**
 * Example usage for searching pages:
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
 * // Search for pages
 * $search = new Search('GraphQL_Search_Provider');
 * $page_results = $search->search('Your search query here', ['post_type' => 'page'], $fields);
 *
 * // Loop through the search results
 * if ($page_results->have_posts()) {
 *     while ($page_results->have_posts()) {
 *         $page_results->the_post();
 *
 *         // Display page information
 *         the_title();
 *         the_excerpt();
 *         // Display custom meta field value
 *         echo get_post_meta(get_the_ID(), 'custom_meta_field', true);
 *     }
 * }
 */

/**
 * Example usage for searching taxonomies:
 *
 * // Set the fields to return within the search results
 * $fields = [
 *     'id',
 *     'name',
 *     'description',
 *     'count',
 *     'meta' => [
 *         'custom_meta_field'
 *     ]
 * ];
 *
 * // Search for taxonomies
 * $search = new Search('GraphQL_Search_Provider');
 * $taxonomy_results = $search->search('Your search query here', ['tax_query' => [
 *         [
 *             'taxonomy' => 'category',
 *             'field' => 'slug',
 *             'terms' => 'my_category_slug'
 *         ]
 *     ]], $fields);
 *
 * // Loop through the search results
 * if ($taxonomy_results) {
 *     foreach ($taxonomy_results as $result) {
 *         // Display taxonomy information
 *         echo $result->name;
 *         echo $result->description;
 *         // Display custom meta field value
 *         echo $result->meta->custom_meta_field;
 *     }
 * }
 */
/**
 * Example usage for searching meta values:
 *
 * // Create a new instance of the GraphQL_Search_Provider class
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
 * // Set the args to search for meta values
 * $args = [
 *     'meta_query' => [
 *         [
 *             'key' => 'custom_meta_field',
 *             'value' => 'custom_meta_value',
 *             'compare' => 'LIKE'
 *         ]
 *     ]
 * ];
 *
 * // Search for meta values
 * $meta_results = $search->search('Your search query here', $args, $fields);
 *
 * // Loop through the meta results
 * if ($meta_results->have_posts()) {
 *     while ($meta_results->have_posts()) {
 *         $meta_results->the_post();
 *
 *         // Display post information
 *         the_title();
 *         the_excerpt();
 *         // Display custom meta field value
 *         echo get_post_meta(get_the_ID(), 'custom_meta_field', true);
 *     }
 * }
 */

/**
 * Example usage for searching users:
 *
 * // Create a new instance of the GraphQL_Search_Provider class
 * $search = new Search('GraphQL_Search_Provider');
 *
 * // Set the fields to return within the search results
 * $fields = [
 *     'id',
 *     'name',
 *     'username',
 *     'email',
 *     'roles'
 * ];
 *
 * // Set the args to search for users
 * $args = [
 *     'user_role' => 'author'
 * ];
 *
 * // Search for users
 * $user_results = $search->search('Your search query here', $args, $fields);
 *
 * // Loop through the user results
 * if ($user_results->have_users()) {
 *     while ($user_results->have_users()) {
 *         $user_results->the_user();
 *
 *         // Display user information
 *         echo get_the_author();
 *         echo get_the_author_meta('description');
 *     }
 * }
 */

/**
 * Example usage for searching options:
 *
 * // Create a new instance of the GraphQL_Search_Provider class
 * $search = new Search('GraphQL_Search_Provider');
 *
 * // Set the fields to return within the search results
 * $fields = [
 *     'option_name',
 *     'option_value'
 * ];
 *
 * // Set the args to search for options
 * $args = [
 *     'option_name' => 'my_option_name'
 * ];
 *
 * // Search for options
 * $option_results = $search->search('Your search query here', $args, $fields);
 *
 * // Loop through the option results
 * if ($option_results->have_options()) {
 *     while ($option_results->have_options()) {
 *         $option_results->the_option();
 *
 *         // Display option information
 *         echo get_option('my_option_name');
 *     }
 * }
 */
class GraphQL_Search_Provider extends WP_Search_Provider
{
    protected GRAPHQLWP $graphql;
    protected array $default_fields = [
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

    public function __construct(array $fields = [])
    {
        if (!class_exists('GRAPHQLWP\GRAPHQLWP')) {
            throw new \BadMethodCallException('GraphQLWP plugin is not installed.');
        }
        $this->graphql = new GRAPHQLWP();
        $this->fields = $this->build_fields($fields);
    }

    /**
     * @param $query
     * @param $args
     * @return \WP_Error|\WP_Query
     */
    public function search($query, $args = [])
    {
        $post_type = isset($args['post_type']) ? $args['post_type'] : null;
        $user_role = isset($args['user_role']) ? $args['user_role'] : null;
        $option_name = isset($args['option_name']) ? $args['option_name'] : null;
        $taxonomies = isset($args['tax_query']) ? $this->build_tax_query($args['tax_query']) : null;
        $meta_query = isset($args['meta_query']) ? $args['meta_query'] : null;

        try {
            $result = $this->graphql->executeQuery($this->build_query($query, $this->fields, $post_type, $user_role, $option_name, $taxonomies, $meta_query), []);
        } catch (\Exception $e) {
            return new \WP_Error('graphql_error', $e->getMessage());
        }

        if ($result->hasErrors()) {
            return new \WP_Error('graphql_error', $result->getErrors());
        }

        $data = $result->getData();

        return search::wp_query_return($data['search']['nodes']);
    }
    /**
     * @param $query
     * @param array $fields
     * @param ?string|null $post_type
     * @param string|null $user_role
     * @param string|null $option_name
     * @param ?array|null $taxonomies
     * @param ?array|null $meta_query
     * @return string
     */
    protected function build_query($query, array $fields = [], ?string $post_type = null, ?string $user_role = null, ?string $option_name = null, ?array $taxonomies = null, ?array $meta_query = null): string
    {
        $fields = $fields ?: $this->default_fields;
        $fields_query = $this->build_fields_query($fields);

        $where = 'query: "' . $query . '"';
        $additional_query = '';
        if ($post_type) {
            $additional_query .= ' postType: "' . $post_type . '"';
        }
        if ($user_role) {
            $additional_query .= ' userRole: "' . $user_role . '"';
        }
        if ($option_name) {
            $additional_query .= ' optionName: "' . $option_name . '"';
        }
        if ($taxonomies) {
            $tax_query = [];
            foreach ($taxonomies as $taxonomy => $terms) {
                $tax_query[] = $taxonomy . ': {terms: ["' . implode('", "', $terms) . '"]}';
            }
            $additional_query .= ' taxQuery: {' . implode(', ', $tax_query) . '}';
        }
        if ($meta_query) {
            $meta_where = [];
            foreach ($meta_query as $meta) {
                if (isset($meta['key'], $meta['value'])) {
                    $meta_where[] = '{key: "' . $meta['key'] . '", value: "' . $meta['value'] . '"}';
                }
            }
            if (!empty($meta_where)) {
                $additional_query .= ' metaQuery: {relation: AND, metaArray: [' . implode(', ', $meta_where) . ']}';
            }
        }

        return <<<GQL
query {
  search($where $additional_query) {
    nodes {
      $fields_query
    }
  }
}
GQL;
    }
    protected function build_fields($fields): array
    {
        if (!$fields) {
            return $this->default_fields;
        }
        // If only one field is provided, convert it to an array
        if (is_string($fields)) {
            $fields = [$fields];
        }
        $fields = array_merge_recursive($this->default_fields, $fields);

        // Remove any keys that don't exist in the default fields
        $default_keys = array_keys($this->default_fields);
        foreach ($fields as $key => $value) {
            if (!in_array($key, $default_keys, true)) {
                unset($fields[$key]);
            }
        }

        return $fields;
    }

    public static function is_available(): bool
    {
        return class_exists('GRAPHQLWP\GRAPHQLWP');
    }

    /**
     * @param $fields
     * @return string
     */
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