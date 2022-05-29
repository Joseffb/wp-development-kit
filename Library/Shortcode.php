<?php

namespace WDK\Library;
/**
 * Class Shortcode
 */
class Shortcode {
	/**
	 * Creates a custom short tag from config variables.
	 *
	 * @param $tag
	 * @param $namespace
	 * @param $method
	 * @param bool|array $buttons
	 */
	public static function CreateCustomShortcode($tag, $namespace, $method, bool|array $buttons = [] ): void
    {
		add_action( 'init', function () use ( $tag, $namespace, $method, $buttons ) {

			add_shortcode( $tag, array( $namespace, $method ) );

			// TinyMCE Settings
			if ( ! empty( $buttons ) ) {
				if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
					return;
				}

				if ( get_user_option( 'rich_editing' ) !== 'true' ) {
					return;
				}

				if(isset($buttons['tiny_mce_settings']) && method_exists($namespace, $buttons['tiny_mce_settings'])) {
                    add_filter( 'tiny_mce_before_init', [$namespace, $buttons['tiny_mce_settings']] );
                }

                if(isset($buttons['tiny_mce_init']) && method_exists($namespace, 'tiny_mce_init')) {
                    add_filter( 'wp_tiny_mce_init', [$namespace, $buttons['tiny_mce_init']] );
                }

                if(
                    isset($buttons['add_buttons_callback'], $buttons['register_buttons_callback']) && method_exists($namespace, $buttons['add_buttons_callback']) && method_exists($namespace, $buttons['register_buttons_callback']) && isset($buttons['extra_vars_callback']) && method_exists($namespace, $buttons['extra_vars_callback'])
                ) {
				add_filter( 'mce_external_plugins', array( $buttons['ns'], $buttons['add_buttons_callback'] ) );
				add_filter( 'mce_buttons', array( $buttons['ns'], $buttons['register_buttons_callback'] ) );
				add_action( 'after_wp_tiny_mce', array( $buttons['ns'], $buttons['extra_vars_callback'] ) );
                }
			}
		} );
	}
}
