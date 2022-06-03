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

        return add_action('init', function () use ($post_type_name, $args, $name) {
            $post_type_name = Inflector::singularize($post_type_name);
            register_post_type($post_type_name, $args);
            if (!empty($args['related_cpt']) && is_array($args['related_cpt'])) {
                foreach ($args['related_cpt'] as $k) {
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

    public static function GetNextNumberedShadowTaxName($taxName): string
    {
        //32 char limit on tax names so we just take 23 characers to allow us to add '_99_tax' at end.
        $array = explode("_", $taxName);
        if (last($array) === "tax") {
            array_pop($array);
        }
        $lastNumber = (int)last($array);
        if ($lastNumber > 0) {
            array_pop($array);
        }
        $machine_tax_name = strtolower(substr(implode("_", $array), 0, 23));
        $machine_tax_name = $lastNumber ? $machine_tax_name . "_" . $lastNumber . "_tax" : $machine_tax_name . "_1_tax";
        if (taxonomy_exists($machine_tax_name)) {

            $array = explode("_", $machine_tax_name);
            if (last($array) === "tax") {
                array_pop($array);
            }
            $lastNumber = (int)last($array);
            array_pop($array);
            $machine_tax_name = self::getNextNumberedShadowTaxName(implode("_", $array) . "_" . ($lastNumber + 1) . "_tax");
        }
        return $machine_tax_name;
    }
}
