<?php

namespace WDK;
class PostType
{

    /**
     * Creates a custom post type
     * @param $post_type_name
     * @param array $args
     * @return true|void
     */
    public static function CreateCustomPostType($post_type_name, array $args)
    {

        $name = $args['label'] ? ucwords($args['label']) : ($args['labels']['name'] ? ucwords($args['label']) : ucwords($post_type_name));
        $post_type_name = strtolower(str_replace(" ", "_", strtolower($post_type_name)));
        $args = array_merge(array(
            'hierarchical' => true,
            'show_in_rest' => true,
            'show_in_graphql' => false,
            'graphql_single_name' => Inflector::singularize($post_type_name),
            'graphql_plural_name' => Inflector::pluralize($post_type_name),
        ), $args);

        $args['labels'] = array(
            'name' => !empty($args['labels']['name']) ?
                ucwords($args['labels']['name']) :
                ucwords($name),
            'singular_name' => !empty($args['labels']['singular_name']) ?
                Inflector::singularize(ucwords($args['labels']['singular_name'])) :
                Inflector::singularize($name),
            'menu_name' => !empty($args['labels']['menu_name']) ?
                ucwords($args['labels']['menu_name']) :
                ucwords($name),
        );
        add_action('init', function () use ($post_type_name, $args, $name) {
            $post_type_name = Inflector::singularize($post_type_name);
            register_post_type($post_type_name, $args);

            $post_type_constant = strtoupper("WDK_PROCESS_TEMPLATE_CPT_{$post_type_name}");

            //Version 0.0.55 - changed from option to constant
            if (!defined($post_type_constant) && !empty($args['use_twig'])) {
                // Define the constant dynamically as true
                define($post_type_constant, true);
            } elseif (defined($post_type_constant) && !$args['use_twig']) {
                // Optional: Log or notify if the constant is already set and use_twig is false
                Utility::Log("Constant {$post_type_constant} already defined. Skipping.");
            }
            //Version 0.0.10 changes related_cpt for shadow_in_cpt which is a more accurate name.
            $shadow_cpt = $args['related_cpt'] ?? $args['shadow_in_cpt'];
            if (!empty($shadow_cpt) && is_array($shadow_cpt)) {
                foreach ($shadow_cpt as $k) {
                    $machine_tax_name = strtolower(substr(str_replace(" ", "_", $k . "_" . $post_type_name), 0, 28)) . "_tax";
                    if (!taxonomy_exists($machine_tax_name)) {
                        Taxonomy::CreateCustomTaxonomy(['human' => $name, "machine" => $machine_tax_name], [$k], [], [
                            "public" => true,
                            'rewrite' => false,
                            'show_tagcloud' => false,
                            'hierarchical' => true,
                            'show_in_rest' => true,
                            'show_in_admin_bar' => true,
                            'show_admin_column' => true,
                            'show_as_admin_filter'=>true
                        ]);
                    }
                    \WDK\Shadow::create_relationship($post_type_name, $machine_tax_name);
                }
            }
        });
    }
}
