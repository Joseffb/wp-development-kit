<?php

namespace WDK\Library;
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
    public static function IsTrue($val, bool $return_null = false): mixed
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
    public static function Log($log, string $note = "", bool|int $levels = 0, int $deprecated_levels = 100): void
    {
        $default_message = empty($note) ? "BACKTRACE>>> " : false;
        if (empty($default_message)) {
            $note = "Note: " . $note . "\n";
        }

        if ($levels === true) {
            //backward compatibility for older debug statements.
            $levels = $deprecated_levels;
        }
        $clean_last = false;
        if (empty($levels)) {
            $levels = 3;
            $clean_last = true;
        } else {
            $levels += 2;
        }

        $debug = debug_backtrace(1, $levels);
        if (!empty($default_message)) {
            $note .= $default_message;
        }
        if($debug[0]['function'] === 'Log') {
            array_shift($debug);
        }

        if($debug[1]['function'] === 'Log') {
            array_shift($debug);
        }

        if($clean_last) {
            array_pop($debug);
        }
        sort($debug);
        $note_array = [];
        if(!empty($debug) && is_array($debug)) {
            foreach ($debug as $k) {
                //error_log('debug: ' . print_r($k, true) . "\n");

                $note_array[] = $note . $k['file'] . ":" . $k['line'];
            }
        }
        $note = implode("\n ",$note_array);
        error_log($note . "\n". print_r($log, true) . "\n");
    }
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
    public static function LastSQL_WP(bool|string $msg = ''): void
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


}
