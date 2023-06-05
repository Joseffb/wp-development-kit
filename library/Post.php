<?php

namespace WDK;
/**
 * Class Shortcode
 */
class Post {
	/**
	 * Create a custom page or post
	 *
	 * @param $type - page, post or cpt
	 * @param $title
	 * @param $content - content
	 * @param $meta - template, categories, tags, taxonomies
	 */
	public static function CreatePost( $type, $title, $content, $meta ): void
    {
		if ( empty( get_page_by_path( sanitize_title($title), 'OBJECT', $type )) ) {
			$parent = 0;
			if ( ! empty( $meta['post_parent'] ) ) {
				$parent_obj = get_post($meta['post_parent']);
				$parent     = $parent_obj->ID ?: $parent;
			}
			$args = array(
				'comment_status' => ! empty( $meta['comment_status'] ) ? $meta['comment_status'] : 'close',
				'ping_status'    => ! empty( $meta['ping_status'] ) ? $meta['ping_status'] : 'close',
				'post_author'    => ! empty( $meta['post_author'] ) ? $meta['post_author'] : 1,
				'post_title'     => ucwords( $title ),
				'post_name'      => ! empty( $meta['slug'] ) ? sanitize_title( $meta['slug'] ) : sanitize_title( $title ),
				'post_status'    => ! empty( $meta['post_status'] ) ? $meta['status'] : 'publish',
				'post_content'   => $content,
				'post_type'      => $type,
				'post_parent'    => $parent,
				'menu_order'     => ! empty( $meta['menu_order'] ) ? $meta['menu_order'] : 0,
			);
			$page_id = wp_insert_post( $args );
			if ( ! empty( $meta['template'] ) ) {
				update_post_meta( $page_id, 'template', $meta['template'] );
			}
			if ( ! empty( $meta['categories'] ) ) {
				wp_set_post_categories( $page_id, $meta['categories'] );
			}
			if ( ! empty( $meta['tags'] ) ) {
				wp_set_post_terms( $page_id, $meta['tags'] );
			}
			if ( ! empty( $meta['terms'] ) ) {
				foreach ( $meta['terms'] as $tax => $terms ) {
					wp_set_post_terms( $page_id, $terms, $tax );
				}
			}
		}
	}

    /**
     * Function to get the next or previous page ID
     * @param int $id post id to scale from
     * @param string $target 'next' or 'prev'
     * @return int|string
     */
    public static function GetNextPrevPageID(int $id, string $target="next")
    {
        // Get all pages under this section
        $post = get_post($id);
        //We only need children on the next
        $children = get_pages('child_of='.$id. '&post_status=publish&sort_column=menu_order&sort_order=asc');
        if(!empty($children[0])) {
            return $children[0]->ID;
        }
        $post_parent = $post->post_parent;
        $get_pages_query = 'child_of=' . $post_parent . '&parent=' . $post_parent . '&post_status=publish&sort_column=menu_order&sort_order=asc';
        $get_pages = get_pages($get_pages_query);
        $page_id = false;

        // Count results
        $page_count = count($get_pages);
        $key = $get_pages[0];
        for ($p = 0; $p < $page_count; $p++) {
            // Get the array key for our entry
            if ($id === $get_pages[$p]->ID) {
                if($target === 'next') {
                    $key = $p+1 > $page_count?$get_pages[$page_count]:$get_pages[$p+1];
                } else {
                    $key = $p === 0?$get_pages[0]:$get_pages[$p-1];
                }

                break;
            }
        }

        // If there isn't a value assigned for the previous key, go all the way to the end
        if (isset($get_pages[$key])) {
            $page_id = $get_pages[$key]->ID;
        }

        return $page_id;
    }
}
