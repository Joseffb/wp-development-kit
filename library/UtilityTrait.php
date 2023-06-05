<?php

namespace WDK;

use JsonException;

/**
 * Utility methods used throughout project.
 */
trait UtilityTrait
{
    /**
     * Checks if the plugin is network activated or not.
     * @param $plugin
     * @return bool
     */
    public function isPluginActiveForNetwork($plugin): bool
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
    public function isTrue($val, bool $return_null = false)
    {
        $bool_val = (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool)$val);
        return ($bool_val === null && !$return_null ? false : $bool_val);
    }

    /**
     * Writes info to log, with optional debug backtrace.
     * @param $log
     * @param string $note
     * @param bool|int $levels
     */
    public function log($log, string $note = "", int $levels = 0): void
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
        error_log($note . "\n" . print_r($log, true) . "\n");
    }

    /**
     * Adds the necessary JavaScript code for the media manager to a taxonomy page.
     *
     * @param bool $off_category_page Optional. Determines whether to add the script even if not on a taxonomy page. Default is false.
     * @return void
     *
     * @since 0.0.42
     *
     * @example
     * // Add media manager script on a taxonomy page
     * $this->AddMediaManagerInlineScript();
     *
     * // Add media manager script regardless of the current page
     * $this->AddMediaManagerInlineScript(true);
     */
    public function addMediaManagerInlineScript($off_category_page = false)
    {
        if (($off_category_page === false) && !isset($_GET['taxonomy'])) {
            // no tax
            return;
        }
        $insertTranslation = __("Insert", "wdk");
        echo /** @lang text */ <<<JS
<script>
    console.log('media script');
    jQuery(document).ready(function ($) {
        _wpMediaViewsL10n.insertIntoPost = '{$insertTranslation}';

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
            let response;
            if ($.inArray('action=add-tag', queryStringArr) !== -1) {
                let xml = xhr.responseXML;
                response = $(xml).find('term_id').text();
                if (response !== "") {
                    // Clear the thumb image
                    $('#category-image-wrapper').html('');
                }
            }
        });
    });
</script>
JS;
    }

    /**
     * Prints the menu array to the log or on screen.
     * @param bool $print_to_display
     */
    public function debugAdminMenus(bool $print_to_display = true): void
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
                    $this->log($menu);
                    $this->log($submenu);
                }
            }
        }
    }

    /**
     * Prints the last query.
     * @param bool|string $msg
     */
    public function lastSQL($msg = ''): void
    {
        if (!defined('SAVEQUERIES')) {
            add_action('init', function () {
                define('SAVEQUERIES', true);
            });
        }

        $this->log($GLOBALS['wp_query']->request, $msg, true, 3);

        global $wpdb;

        // Print last SQL query string
        $this->log($wpdb->last_query, 'Print last SQL query string:', true, 3);

        // Print last SQL query result
        $this->log($wpdb->last_result, 'Print last SQL query result:', true, 3);

        // Print last SQL query Error
        $this->log($wpdb->last_error, 'Print last SQL query Error:', true, 3);
    }

    public function arrayInsert(&$array, $position, $insert): void
    {
        if (is_int($position)) {
            array_splice($array, $position, 0, $insert);
        } else {
            $pos = array_search($position, array_keys($array), true);
            $array = array_merge(
                array_slice($array, 0, $pos),
                $insert,
                array_slice($array, $pos)
            );
        }
    }

    /**
     * Merges two arrays into one. Faster than array_merge.
     * @param $array1
     * @param $array2
     * @return mixed
     */
    public function twoArrayMerge(&$array1, &$array2)
    {
        if (is_array($array2)) {
            foreach ($array2 as $i) {
                $array1[] = $i;
            }
        } else {
            $this->log($array1);
            $this->log($array2, 'Non-Fatal Error: Non-Array Passed to Array Function.', 100);
        }

        return $array1;
    }

    /**
     * @throws JsonException
     */
    public function multiDimensionUnique($array, $key): array
    {
        $array = json_decode(json_encode($array, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $temp_array = [];
        foreach ($array as &$v) {
            if (!isset($temp_array[$v[$key]])) {
                $temp_array[$v[$key]] =& $v;
            }
        }
        return array_values($temp_array);
    }

    /**
     * @param $handle
     * @param $relpath
     * @param string $type
     * @param array $my_deps
     * @param bool $in_footer
     * @return void
     */
    public function enqueuer($handle, $relpath, string $type = 'script', array $my_deps = array(), bool $in_footer = true): void
    {
        $uri = get_theme_file_uri($relpath);
        $vsn = filemtime(get_theme_file_path($relpath));

        if ($type === 'script') {
            wp_enqueue_script($handle, $uri, $my_deps, $vsn, $in_footer);
        } else if ($type === 'style') {
            wp_enqueue_style($handle, $uri, $my_deps, $vsn, $in_footer);
        }
    }
    public function isDirectoryEmpty($dir): ?bool
    {
        if (empty($dir) || !is_readable($dir)) {
            return null;
        }

        // Get canonical absolute pathname
        $path = realpath($dir);

        // If it exists, check if it's a directory
        $isDirExist = ($path !== false && is_dir($path));

        // If the directory exists and is empty, return true
        if ($isDirExist && count(scandir($path)) <= 2) {
            return true;
        }

        return false;
    }

    public function isGutenbergEnabled($check_current_screen = false): bool
    {
        $gutenberg = false;
        $block_editor = false;

        if ($check_current_screen) {
            if (!function_exists('get_current_screen')) {
                return false;
            }

            return get_current_screen()->is_block_editor;
        }

        if (has_filter('replace_editor', 'gutenberg_init')) {
            // Gutenberg is installed and activated.
            $gutenberg = true;
        }

        if (version_compare($GLOBALS['wp_version'], '5.0-beta', '>')) {
            // Block editor.
            $block_editor = true;
        }

        if (!$gutenberg && !$block_editor) {
            return false;
        }

        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        if (!is_plugin_active('classic-editor/classic-editor.php')) {
            return true;
        }

        return get_option('classic-editor-replace') === 'no-replace';
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
    public function weighted_random(array $items, array $drop_rates)
    {
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
