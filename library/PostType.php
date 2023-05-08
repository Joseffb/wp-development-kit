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

        $name = ucwords($post_type_name);
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
            $p = register_post_type($post_type_name, $args);

            if(!empty($args['use_twig'])) {
                delete_option("wdk_process_template_cpt_$post_type_name"); //removes old data entries.
                update_option("wdk_process_template_cpt_$post_type_name", $args['use_twig']);
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
                    Shadow::CreateRelationship($post_type_name, $machine_tax_name);
                }
            }
        });
    }
}
