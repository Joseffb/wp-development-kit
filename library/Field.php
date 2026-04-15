<?php

namespace WDK;

/**
 * Class Field - Field input generator and tools
 * @package WDK\Library\Field
 */
class Field
{
    /**
     * @var array used to cache a field config
     */
    public static array $configs = [];

    /**
     * Used to write a custom field to the options table.
     * Todo: In future will also check permissions before write.
     *
     * @return bool|int
     */
    public static function WriteToField($post_id, $field_name, $field_value, bool $unique = true, string $old_value = '', bool $is_update = false)
    {
        if ($retVal = (add_post_meta($post_id, $field_name, $field_value, $unique) === false)) {
            $retVal = update_post_meta($post_id, $field_name, $field_value, $old_value);
        }

        return $retVal;
    }

    /**
     * Grabs the Fields.json config from a directory. Sets a copy in cache for later use.
     */
    public static function GetFieldConfigs($dir = null, $refresh = false)
    {
        if (!$refresh) {
            $dir = new \DirectoryIterator($dir ?: get_template_directory() . '/app/Config/');
            foreach ($dir as $fileinfo) {
                if (!$fileinfo->isDot()) {
                    if ($fileinfo->isDir()) {
                        self::GetFieldConfigs($fileinfo->getPath());
                    } elseif (substr($fileinfo->getFilename(), -5) === '.json' && $fileinfo->getFilename() === 'Fields.json') {
                        $config = json_decode(file_get_contents($fileinfo->getRealPath()), true);
                        if (!empty($config)) {
                            self::$configs = array_merge(self::$configs, $config);
                        }
                    }
                }
            }
        }

        return self::$configs;
    }

    public static function ReadFromField($post_id, $field_name = '', $return_array = false)
    {
        return get_post_meta($post_id, $field_name, !$return_array);
    }

    /**
     * Used to create a custom field metabox to a CPT. Sets up filter column for field as well if option is enabled.
     */
    public static function AddCustomFieldToPost($pt, $id, $label, $type, array $options = [], $fieldObj = [], $context = 'normal', $priority = 'high'): void
    {
        add_action('save_post', function ($post_id) use ($id, $type) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (function_exists('wp_is_post_autosave') && wp_is_post_autosave($post_id)) {
                return;
            }

            if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) {
                return;
            }

            if (empty($_POST["{$id}_meta_box_nonce"]) || !wp_verify_nonce(self::unslash($_POST["{$id}_meta_box_nonce"]), "{$id}_meta_box_nonce")) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            if (!array_key_exists($id, $_POST)) {
                if (in_array($type, ['checkbox', 'list', 'serialized_list'], true)) {
                    delete_post_meta($post_id, $id);
                }
                return;
            }

            $saveValue = self::sanitizeSubmittedValue(
                (string) $type,
                self::unslash($_POST[$id]),
                !empty($_POST["serialize_data_$id"])
            );

            self::persistFieldValue((int) $post_id, (string) $id, (string) $type, $saveValue);
        }, 1000);

        $processed_fields = [];
        $test = $pt . '_' . $id;
        if (!in_array($test, $processed_fields, true)) {
            if ((bool) ($fieldObj['show_on_admin'] ?? true) !== false || Utility::IsTrue($fieldObj['show_on_admin'] ?? true)) {
                if (Utility::IsGutenbergEnabled()) {
                    add_action('add_meta_boxes', function () use ($context, $priority, $pt, $id, $label, $type, $options) {
                        add_meta_box(
                            'meta_box_' . $id,
                            $label,
                            static function ($post) use ($id, $label, $type, $options) {
                                wp_nonce_field("{$id}_meta_box_nonce", "{$id}_meta_box_nonce");
                                echo self::CreateField($id, $id, $label, $type, $options, $post);
                            },
                            $pt,
                            $context,
                            $priority
                        );
                    });
                }
            }
            $processed_fields[] = $test;
        }
    }

    /**
     * Used to create an input field for the meta data based field in the db.
     */
    public static function CreateField($field_id, $field_name, $label, $type, $options, ?\WP_Post $post = null, bool $values = false): string
    {
        $class = self::sanitizeCssClasses($options['classes'] ?? []);
        $labelText = self::escapeText((string) $label);
        $fieldId = self::escapeAttr((string) $field_id);
        $fieldName = self::escapeAttr((string) $field_name);
        $type = trim((string) $type);
        $values = $post !== null
            ? self::ReadFromField($post->ID, $field_id, in_array($type, ['checkbox', 'list'], true))
            : $values;
        $selected = self::normalizeFieldValue($values);
        $selectedValues = array_map('strval', (array) $selected);

        switch ($type) {
            case 'serialized_list':
                $head = "<!-- Editable table -->
                        <div class='mt-3 table-editable'>
                        <span class='table-row-add'><a href='#!' class='float-right text-success add-td-link'>Add Another {$labelText}</a>
                       <table id='table_{$fieldId}' class='table table-bordered table-responsive-md table-striped text-center editable-table'>
                        <thead>
                          <tr>
                            <th class='text-center'>{$labelText}</th>
                            <th class='text-center'>Remove</th>
                          </tr>
                        </thead>
                        <tbody>";
                $rTemplate = "   <tr>
                          <td class='pt-3-half' contenteditable='false'>
                            <input type='search' name='{$fieldName}[][name]' %{name-value}% autocomplete='off' class='form-control autocomplete elem-name'>
                                <button type='button' class='autocomplete-clear'>
                                    <svg fill='#000000' height='24' viewBox='0 0 24 24' width='24' xmlns='https://www.w3.org/2000/svg'>
                                      <path d='M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z' />
                                      <path d='M0 0h24v24H0z' fill='none' />
                                    </svg>
                                </button>
                          </td>
                          <td>
                            <span class='table-elem-remove'><button type='button' class='btn btn-danger btn-rounded btn-sm my-0'>Remove</button></span>
                          </td>
                         </tr>";
                $foot = "<input type='hidden' name='serialize_data_{$fieldName}' value='true'/></tbody>
                                            </table>   </span>  
                                           </div>
                                        
                <!-- Editable table -->";
                $rows = '';
                $rowValues = is_array($selected) ? $selected : [$selected];

                foreach ($rowValues as $rowValue) {
                    $normalizedValue = self::normalizeFieldValue($rowValue);
                    if (is_array($normalizedValue)) {
                        foreach ($normalizedValue as $item) {
                            $rows .= str_replace('%{name-value}%', "value='" . self::escapeAttr((string) $item) . "'", $rTemplate);
                        }
                        continue;
                    }

                    if ((string) $normalizedValue !== '') {
                        $rows .= str_replace('%{name-value}%', "value='" . self::escapeAttr((string) $normalizedValue) . "'", $rTemplate);
                    }
                }

                if ($rows === '') {
                    $rows = str_replace('%{name-value}%', '', $rTemplate);
                }

                return $head . $rows . $foot;

            case 'select':
            case 'list':
                $name = $type === 'select' ? $fieldName : $fieldName . '[]';
                $isList = $type === 'list' ? 'size="5" multiple' : '';
                $html = "<div class='{$class}'><label for='{$fieldId}'>{$labelText}</label><select class='col {$type}' id='{$fieldId}' name='{$name}' {$isList}>";
                if (!empty($options['values']) && is_array($options['values'])) {
                    foreach ($options['values'] as $key => $value) {
                        $optionValue = is_array($value) ? ($value['value'] ?? '') : $value;
                        $selectedAttr = in_array((string) $optionValue, $selectedValues, true) ? 'selected="selected"' : '';
                        $html .= "<option class='{$class} option-{$type}' value='" . self::escapeAttr((string) $optionValue) . "' {$selectedAttr}>" . self::escapeText((string) $key) . '</option>';
                    }
                }
                return $html . '</select></div>';

            case 'radio':
            case 'checkbox':
                $radioInline = $type === 'radio' ? 'radio-inline ml-2' : '';
                $name = $type === 'radio' ? $fieldName : $fieldName . '[]';
                $html = "<label class='col-12'>no values defined</label>";
                if (!empty($options['values']) && is_array($options['values'])) {
                    $html = '';

                    if ($labelText !== '') {
                        $html .= "<label class='col-12' for='{$fieldId}'>{$labelText}</label>";
                    }

                    $html .= "<style>
                                .use-images{
                                  position: absolute;
                                  opacity: 0;
                                  width: 0;
                                  height: 0;
                                }

                                .use-images + img {
                                  cursor: pointer;
                                  transition: background-color 0.2s;
                                }

                                .use-images:hover + img {
                                  background-color: #dddddd;
                                  transition: background-color 0.2s;
                                }

                                .use-images:checked + img {
                                  background-color: #cccccc;
                                  transition: background-color 0.2s;
                                }
                                </style>";

                    $count = 0;
                    foreach ($options['values'] as $key => $value) {
                        $displayLabel = self::escapeText((string) $key);
                        $imageClass = '';
                        if (is_array($value)) {
                            $imageClass = 'use-images';
                            $displayLabel = "<img class='image col' src='" . self::escapeUrl((string) ($value['image'] ?? '')) . "' alt='" . self::escapeAttr((string) $key) . "'>";
                            $value = $value['value'] ?? '';
                        }

                        $checked = in_array((string) $value, $selectedValues, true) ? 'checked' : '';
                        $divClass = self::sanitizeCssClasses(str_replace('div-', '', $class));
                        $labelClass = self::sanitizeCssClasses(str_replace('label-', '', $class));
                        $inputClass = self::sanitizeCssClasses(str_replace('input-', '', $class));
                        $inputId = self::escapeAttr((string) $field_id . '-' . $count);

                        $html .= $type === 'checkbox' ? "<div class='{$divClass} wrapper'>" : '';
                        $html .= "<label class='col {$labelClass} label radio-check-label {$type}-label {$radioInline}' for='{$inputId}'>
                                            <input class='{$inputClass} {$type} {$imageClass}'
                                            type='{$type}'
                                            id='{$inputId}'
                                            name='{$name}'
                                            value='" . self::escapeAttr((string) $value) . "' {$checked}> {$displayLabel}
                                       </label>";
                        $html .= $type === 'checkbox' ? '</div>' : '';
                        $count++;
                    }
                }

                return $html;

            case 'note':
                return "<div class='{$class}'>" . self::escapeText((string) $selected) . '</div>';

            case 'button':
            case 'reset':
            case 'submit':
                return "<button class='{$class} button' type='{$type}'>" . self::escapeText((string) $selected) . '</button>';

            case 'date':
            case 'datetime-local':
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
            case 'password':
            case 'time':
            case 'hidden':
            case 'color':
            case 'file':
            case 'image':
            case 'range':
            case 'search':
            case 'week':
            default:
                $placeholder = self::escapeAttr((string) ($options['placeholder'] ?? ''));
                $labelClass = self::sanitizeCssClasses(str_replace('label-', '', $class));
                $textboxClass = self::sanitizeCssClasses(str_replace('textbox-', '', $class));
                $divClass = self::sanitizeCssClasses(str_replace('div-', '', $class));

                return "<div class='{$divClass}'>
                                        <label class='{$labelClass} label input-label {$type}' for='{$fieldId}'>{$labelText}</label><br/>
                                       <input class='{$textboxClass} input {$type}-label' id='{$fieldId}' type='{$type}' name='{$fieldName}' value='" . self::escapeAttr((string) $selected) . "' placeholder='{$placeholder}'/></div>";
        }
    }

    /**
     * Used to register sortable custom field columns based on a CPT index page. (not filterable)
     */
    public static function AddFieldToPostAdminColumns($field, $post_type, array $location = []): void
    {
        $label = $field['admin_column_header'] === true ? $field['id'] : $field['admin_column_header'];
        add_filter('manage_' . $post_type . '_posts_columns', function (array $columns) use ($field, $label) {
            $bottom = array_slice($columns, count($columns) - 2);
            for ($x = 0; $x <= 2; $x++) {
                $e = array_keys($columns);
                $end = end($e);
                unset($columns[$end]);
            }
            $columns[$field['id']] = _($label);
            foreach ($bottom as $k => $v) {
                $columns[$k] = $v;
            }
            return $columns;
        });

        add_action('manage_' . $post_type . '_posts_custom_column', function ($column, $post_id) use ($field) {
            if ($field['id'] === $column) {
                $value = self::ReadFromField($post_id, $field['id']);
                echo Utility::IsTrue($value) ? "<i class='fa fa-check align-content-center'></i>" : '';
            }
        }, 10, 2);
        add_filter('manage_edit-' . $post_type . '_sortable_columns', function ($columns) use ($field, $label) {
            $columns[$field['id']] = $label;
            return $columns;
        });

        add_action('pre_get_posts', static function ($query) use ($field) {
            if (!is_admin() || !$query->is_main_query()) {
                return;
            }

            if ($field['id'] === $query->get('orderby')) {
                $query->set('orderby', 'meta_value');
                $query->set('meta_key', $field['id']);
                $query->set('meta_type', 'numeric');
            }
        });
    }

    private static function unslash(mixed $value): mixed
    {
        if (function_exists('wp_unslash')) {
            return wp_unslash($value);
        }

        if (is_array($value)) {
            return array_map([self::class, 'unslash'], $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }

    private static function normalizeFieldValue(mixed $value): mixed
    {
        if (is_string($value) && is_serialized($value)) {
            $value = maybe_unserialize($value);
        }

        if (is_array($value)) {
            return array_map([self::class, 'normalizeFieldValue'], $value);
        }

        return $value;
    }

    private static function sanitizeSubmittedValue(string $type, mixed $value, bool $forceSerialized = false): mixed
    {
        if ($forceSerialized || $type === 'serialized_list') {
            $items = [];
            foreach ((array) $value as $row) {
                if (is_array($row) && array_key_exists('name', $row)) {
                    $sanitized = sanitize_text_field((string) $row['name']);
                    if ($sanitized !== '') {
                        $items[] = $sanitized;
                    }
                } else {
                    $sanitized = sanitize_text_field((string) $row);
                    if ($sanitized !== '') {
                        $items[] = $sanitized;
                    }
                }
            }

            return $items;
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(static function ($item) use ($type) {
                return self::sanitizeScalarValue($type, $item);
            }, $value), static function ($item) {
                return $item !== '' && $item !== null;
            }));
        }

        return self::sanitizeScalarValue($type, $value);
    }

    private static function sanitizeScalarValue(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return match ($type) {
            'email' => sanitize_email($value),
            'url', 'image', 'file' => esc_url_raw($value),
            'color' => sanitize_hex_color($value) ?: '',
            'range' => is_numeric($value) ? (string) $value : sanitize_text_field($value),
            default => sanitize_text_field($value),
        };
    }

    private static function persistFieldValue(int $postId, string $fieldId, string $type, mixed $value): void
    {
        delete_post_meta($postId, $fieldId);

        if (is_array($value) && in_array($type, ['checkbox', 'list'], true)) {
            foreach ($value as $item) {
                add_post_meta($postId, $fieldId, $item, false);
            }
            return;
        }

        update_post_meta($postId, $fieldId, $value);
    }

    private static function sanitizeCssClasses(array|string $classes): string
    {
        $classes = is_array($classes) ? $classes : (preg_split('/\s+/', trim((string) $classes)) ?: []);
        $sanitized = array_map(static function ($class) {
            return function_exists('sanitize_html_class')
                ? sanitize_html_class((string) $class)
                : preg_replace('/[^A-Za-z0-9_-]/', '', (string) $class);
        }, $classes);

        return trim(implode(' ', array_filter($sanitized)));
    }

    private static function escapeText(string $value): string
    {
        if (function_exists('esc_html')) {
            return esc_html($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function escapeAttr(string $value): string
    {
        if (function_exists('esc_attr')) {
            return esc_attr($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function escapeUrl(string $value): string
    {
        if (function_exists('esc_url')) {
            return esc_url($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
