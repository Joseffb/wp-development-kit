<?php

namespace WDK;

use JsonException;

/**
 * Utility methods used throughout project.
 */
class Utility
{
    /**
     * Checks if the plugin is network activated or not.
     * @param $plugin
     * @return bool
     */
    public static function IsPluginActiveForNetwork($plugin): bool
    {
        if (!is_multisite()) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[$plugin])) {
            return true;
        }

        return false;
    }

    /**
     * Returns True-ish values from mixed inputs
     *
     * @param $val
     * @param bool $return_null
     * @return bool|mixed|null
     * Return Values:
     *
     * is_true(new stdClass);      // true
     * is_true([1,2]);             // true
     * is_true([1]);               // true
     * is_true([0]);               // true
     * is_true(42);                // true
     * is_true(-42);               // true
     * is_true('true');            // true
     * is_true('on')               // true
     * is_true('off')              // false
     * is_true('yes')              // true
     * is_true('no')               // false
     * is_true('ja')               // false
     * is_true('nein')             // false
     * is_true('1');               // true
     * is_true(NULL);              // false
     * is_true(0);                 // false
     * is_true('false');           // false
     * is_true('string');          // false
     * is_true('0.0');             // false
     * is_true('4.2');             // false
     * is_true('0');               // false
     * is_true('');                // false
     * is_true([]);                // false
     */
    public static function IsTrue($val, bool $return_null = false)
    {
        $bool_val = (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool)$val);
        return ($bool_val === null && !$return_null ? false : $bool_val);
    }
    /**
     * writes info to log, with option debug backtrace
     * @param $log
     * @param string $note
     * @param bool | int $levels
     * @param int $deprecated_levels
     */
    public static function Log($log, string $note = "", int $levels = 0): void
    {
        $default_message = empty($note) ? "BACKTRACE>>> " : false;
        if (empty($default_message)) {
            $note = "Note: " . $note . "\n";
        }

        $levels++; // Increase levels to account for the function itself and this call
        $debug = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $levels);

        $note_array = [];
        if (!empty($debug)) {
            foreach ($debug as $k) {
                $note_array[] = $k['file'] . ":" . $k['line'];
            }
        }
        $note = (string)$note . implode("\n", $note_array);
        error_log($note . "\n" . print_r($log, true));    }
    /**
     * Enqueue styles and scripts
     * @param string $tax_name
     * @param false $off_category_page -- in case its not on the admin category pages -- disables the tax page check.
     * @since 1.0.5
     */
    public static function AddMediaManagerInlineScript($off_category_page = false)
    {
        if(($off_category_page === false) && !isset($_GET['taxonomy'])) {
            // no tax
            return;
        }
        ?>
        <script>
            console.log('media script');
            jQuery(document).ready(function ($) {
                _wpMediaViewsL10n.insertIntoPost = '<?php _e("Insert", "wdk"); ?>';

                function ct_media_upload(button_class) {
                    let _custom_media = true, _orig_send_attachment = wp.media.editor.send.attachment;

                    // Add button click
                    $('body').on('click', button_class, function (e) {
                        const button_id = '#' + $(this).attr('id');
                        const button = $(button_id);
                        wp.media.editor.send.attachment = function (props, attachment) {
                            if (_custom_media) {
                                $('#taxonomy-image-id').val(attachment.id);
                                $('#category-image-wrapper').html('<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />');
                                $('#category-image-wrapper .custom_media_image').attr('src', attachment.url).css('display', 'block');
                            } else {
                                return _orig_send_attachment.apply(button_id, [props, attachment]);
                            }
                        }
                        wp.media.editor.open(button);
                        return false;
                    });
                }

                ct_media_upload('.tax_media_button.button');

                // Remove button click
                $('body').on('click', '.tax_media_remove', function () {
                    $('#taxonomy-image-id').val('');
                    $('#category-image-wrapper').html('<img class="custom_media_image" src="" style="margin:0;padding:0;max-height:100px;float:none;" />');
                });
                // Thanks: http://stackoverflow.com/questions/15281995/wordpress-create-category-ajax-response
                $(document).ajaxComplete(function (event, xhr, settings) {
                    const queryStringArr = settings.data.split('&');
                    let $response;
                    if ($.inArray('action=add-tag', queryStringArr) !== -1) {
                        let xml = xhr.responseXML;
                        $response = $(xml).find('term_id').text();
                        if ($response !== "") {
                            // Clear the thumb image
                            $('#category-image-wrapper').html('');
                        }
                    }
                });
            });
        </script>
    <?php }

    /**
     * Prints to Log or on Screen the menu menu array.
     * @param bool $print_to_display
     * @return void
     */
    public static function DebugAdminMenus(bool $print_to_display = true): void
    {
        if (!is_admin()) {
            return;
        }
        global $submenu, $menu, $pagenow;
        if (current_user_can('manage_options')) { // ONLY DO THIS FOR ADMIN
            if ($pagenow === 'index.php') {  // PRINTS ON DASHBOARD
                if ($print_to_display) {
                    echo '<pre>';
                    print_r($menu);
                    echo '</pre>'; // TOP LEVEL MENUS
                    echo '<pre>';
                    print_r($submenu);
                    echo '</pre>'; // SUBMENUS
                } else {
                    self::Log($menu);
                    self::Log($submenu);
                }
            }
        }
    }

    /**
     * Print last query -- note add define( 'SAVEQUERIES', true ) to wp-config
     * @param bool|string $msg
     */
    public static function LastSQL_WP($msg = ''): void
    {
        if (!defined('SAVEQUERIES')) {
            add_action('init', function () {
                define('SAVEQUERIES', true);
            });
        }

        self::Log($GLOBALS['wp_query']->request, $msg,true, 3);

        global $wpdb;

// Print last SQL query string
        self::Log($wpdb->last_query, 'Print last SQL query string:',true, 3);

// Print last SQL query result
        self::Log($wpdb->last_result, 'Print last SQL query result:',true, 3);

// Print last SQL query Error
        self::Log($wpdb->last_error, 'Print last SQL query Error:',true, 3);
    }

    public static function ArrayInsert(&$array, $position, $insert): void
    {
        if (is_int($position)) {
            array_splice($array, $position, 0, $insert);
        } else {
            $pos = array_search($position, array_keys($array));
            $array = array_merge(
                array_slice($array, 0, $pos),
                $insert,
                array_slice($array, $pos)
            );
        }
    }

    /**
     * Merges two arrays into one. Faster then Array Merge.
     * @param $array1
     * @param $array2
     * @return mixed
     */
    public static function TwoArrayMerge(&$array1, &$array2): mixed
    {
        if(is_array($array2)) {
            foreach($array2 as $i) {
                $array1[] = $i;
            }
        } else {
            self::Log($array1);
            self::Log($array2, 'Non-Fatal Error: Non-Array Passed to Array Function.', 100);
        }

        return $array1;
    }

    /**
     * @throws JsonException
     */
    public static function MultiDimensionUnique($array, $key): array
    {
        $array = json_decode(json_encode($array, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $temp_array = [];
        foreach ($array as &$v) {
            if (!isset($temp_array[$v[$key]]))
                $temp_array[$v[$key]] =& $v;
        }
        return array_values($temp_array);
    }

    /**
     * Enqueue scripts and styles intelligently based on their source.
     *
     * @param string $handle    Unique handle for the asset.
     * @param string $relpath   Relative path to the asset file or absolute URL.
     * @param string $type      Type of the asset: 'auto', 'script', 'style'.
     * @param array  $deps      Dependencies for the asset.
     * @param bool   $in_footer Whether to enqueue the script in the footer.
     */
    public static function Enqueuer(
        string $handle,
        string $relpath,
        string $type = 'auto',
        array $deps = [],
        bool $in_footer = true
    ): void {
        // Normalize the relative path by removing leading slashes
        $relpath = ltrim($relpath, '/');

        // Helper to check if URL is absolute
        $is_absolute_url = static function (string $url): bool {
            return (bool)parse_url($url, PHP_URL_SCHEME);
        };

        // Helper to get base paths (URL and filesystem path) for the caller's plugin or theme directory
        $get_base_paths = static function (string $relpath): ?array {
            $file_path = realpath(__FILE__);

            // Get the plugins directory path
            $plugins_dir = realpath(WP_PLUGIN_DIR);

            // Get the themes directory path
            $themes_dir = realpath(get_theme_root());

            if (strpos($file_path, $plugins_dir) === 0) {
                // File is in the plugins directory
                $relative_path = substr($file_path, strlen($plugins_dir) + 1);
                $plugin_folder = explode(DIRECTORY_SEPARATOR, $relative_path)[0];

                $plugin_base_path = $plugins_dir . DIRECTORY_SEPARATOR . $plugin_folder;
                $plugin_base_url = plugins_url($plugin_folder);

                return [
                    'url'  => $plugin_base_url . '/' . ltrim($relpath, '/'),
                    'path' => $plugin_base_path . DIRECTORY_SEPARATOR . ltrim($relpath, '/'),
                ];
            } elseif (strpos($file_path, $themes_dir) === 0) {
                // File is in the themes directory
                $relative_path = substr($file_path, strlen($themes_dir) + 1);
                $theme_folder = explode(DIRECTORY_SEPARATOR, $relative_path)[0];

                $theme_base_path = $themes_dir . DIRECTORY_SEPARATOR . $theme_folder;
                $theme_base_url = get_theme_root_uri() . '/' . $theme_folder;

                return [
                    'url'  => $theme_base_url . '/' . ltrim($relpath, '/'),
                    'path' => $theme_base_path . DIRECTORY_SEPARATOR . ltrim($relpath, '/'),
                ];
            }

            return null; // File is not in plugin or theme directory
        };


        // Determine if the relpath is an absolute URL
        if ($is_absolute_url($relpath)) {
            $uri = $relpath;
            $file_path = null; // No file path needed for absolute URLs
        } else {
            $caller_paths = $get_base_paths($relpath);
            if ($caller_paths && file_exists($caller_paths['path'])) {
                $uri = $caller_paths['url'];
                $file_path = $caller_paths['path'];
            } else {
                self::Log("Enqueuer: File '{$relpath}' not found in plugin or theme directories.");
                return;
            }
        }

        // If file_path is set, get the file modification time for versioning
        $version = $file_path && file_exists($file_path) ? filemtime($file_path) : md5($file_path);
        // Determine file type based on extension if 'auto' is selected
        if ($type === 'auto' || is_null($type)) {
            $extension = pathinfo(parse_url($uri, PHP_URL_PATH), PATHINFO_EXTENSION);
            $type = $extension === 'js' ? 'script' : ($extension === 'css' ? 'style' : '');
        }
        // Enqueue the script or style based on the determined type
        if ($type === 'script') {
            wp_enqueue_script($handle, $uri, $deps, $version, $in_footer);
        } elseif ($type === 'style') {
            wp_enqueue_style($handle, $uri, $deps, $version);
        } else {
            self::Log("Enqueuer: Unknown file type '{$type}' for file '{$relpath}'.");
        }
    }

    /**
     * @param $dir
     * @return bool|null
     */
    public static function IsDirEmpty($dir): ?bool
    {
        //Utility::Log($dir, 'dir to check');
        if (empty($dir) || !is_readable($dir)) {
            return NULL;
        }
        // if we see . and .. it's an empty directory.
        return (count(scandir($dir)) <= 2);
    }
    /**
     * Checks if a folder exist and return canonicalized absolute pathname (sort version)
     * @param string $folder the path being checked.
     * @return mixed returns the canonicalized absolute pathname on success otherwise FALSE is returned
     */
    public static function DoesDirExist($dir)
    {
        // Get canonicalized absolute pathname
        $path = realpath($dir);

        // If it exist, check if it's a directory
        return ($path !== false AND is_dir($path)) ? $path : false;
    }

    public static function IsGutenbergEnabled($check_current_screen = false): bool
    {
        $gutenberg    = false;
        $block_editor = false;

        if($check_current_screen) {
            if( ! function_exists( 'get_current_screen' ) ) {
                return false;
            }

            $screen = get_current_screen();
            return $screen->is_block_editor;
        }

        if ( has_filter( 'replace_editor', 'gutenberg_init' ) ) {
            // Gutenberg is installed and activated.
            $gutenberg = true;
        }

        if ( version_compare( $GLOBALS['wp_version'], '5.0-beta', '>' ) ) {
            // Block editor.
            $block_editor = true;
        }

        if ( ! $gutenberg && ! $block_editor ) {
            return false;
        }

        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        if ( ! is_plugin_active( 'classic-editor/classic-editor.php' ) ) {
            return true;
        }

        return get_option( 'classic-editor-replace' ) === 'no-replace';
    }

    /**
     * Returns a random item from the given array of items, based on the provided drop rates.
     *
     * @param array $items The array of items to choose from.
     * @param array $drop_rates The array of drop rates, corresponding to each item.
     * @return false|string The randomly chosen item.
     *
     * @throws \Exception
     * @example
     * $items = array("Sword", "Bow", "Axe", "Staff");
     * $drop_rates = array(0.4, 0.3, 0.2, 0.1);
     * $weapon = weighted_random($items, $drop_rates);
     * echo "You received a {$weapon}!";
     */
    public static function weighted_random(array $items, array $drop_rates) {
        $weighted_items = array_combine($items, $drop_rates);
        $rand = random_int(0, array_sum($drop_rates) * 100) / 100;
        foreach ($weighted_items as $item => $weight) {
            if ($rand < $weight) {
                return $item;
            }
            $rand -= $weight;
        }
        return false;
    }
}
