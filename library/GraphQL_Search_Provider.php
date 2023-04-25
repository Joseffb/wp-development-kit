<?php

namespace WDK;

class GraphQL_Search_Provider extends WP_Search_Provider {
    protected \GraphQLWP\GraphQLWP $graphql;

    public function __construct() {
        if (!class_exists('GraphQLWP\GraphQLWP')) {
            throw new \RuntimeException('The GraphQLWP plugin is required to use the GraphQL search provider.');
        }

        $this->graphql = new \GraphQLWP\GraphQLWP();
    }

    public function search($query, $args = []) {
        $result = $this->graphql->executeQuery($this->build_query($query), []);

        if ($result->hasErrors()) {
            // Handle errors
            return new \WP_Error('graphql_error', $result->getErrors());
        }

        // Get the data from the result
        $data = $result->getData();

        // Process the data and return the WP_Query object
        return Search::wp_query_return($data['posts']);
    }

    protected function build_query($query): string
    {
        // Build the GraphQL query
        $gql_query = <<<GQL
query {
  posts(where: {search: "$query"}) {
    nodes {
      id
      title
      excerpt
      uri
      date
      featuredImage {
        node {
          sourceUrl
        }
      }
    }
  }
}
GQL;

        return $gql_query;
    }
}