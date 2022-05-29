<?php

namespace WDK\Library;
/**
 * Class Field - Field input generator and tools
 * @package WDK\Library\Field
 */
class Field
{
    /**
     * @var array used to cache a field config
     */
    static $configs = [];

    /**
     * Used to write a custom field to the options table.
     * Todo: In future will also check permissions before write.
     * @param $post_id
     * @param $field_name
     * @param $field_value
     * @param bool $unique
     * @param string $old_value
     * @param bool $is_update
     * @return bool|int
     */
    public static function WriteToField($post_id, $field_name, $field_value, $unique = true, $old_value = '', $is_update = false)
    {
        //todo add permission check
        if ($retVal = add_post_meta($post_id, $field_name, $field_value, $unique) === false) {
            $retVal = update_post_meta($post_id, $field_name, $field_value, $old_value);
        }

        return $retVal;
    }

    /**
     * Grabs the Fields.json config from a directory. Sets a copy in cache for later use.
     * @param null $dir
     * @param bool $refresh
     * @return array
     */
    public static function GetFieldConfigs($dir = null, $refresh = false)
    {
        if (!$refresh) {
            $dir = new \DirectoryIterator($dir ?: get_template_directory() . '/app/Config/');
            foreach ($dir as $fileinfo) {
                if (!$fileinfo->isDot()) {
                    if ($fileinfo->isDir()) {
                        self::GetFieldConfigs($fileinfo->getPath());
                    } else if (substr($fileinfo->getFilename(), -5) === '.json') {
                        if ($fileinfo->getFilename() === 'Fields.json') {
                            $config = json_decode(file_get_contents($fileinfo->getRealPath()), true);
                            if (!empty($config)) {
                                self::$configs = array_merge(self::$configs, $config);
                            }
                        }

                    }
                }
            }
        }
        return self::$configs;
    }

    /**
     * @param $post_id
     * @param $field_name
     * @param bool $return_array
     *
     * @return mixed
     */
    public static function ReadFromField($post_id, $field_name = '', $return_array = false)
    {
        //todo add permission check
        return get_post_meta($post_id, $field_name, !$return_array);
    }

    /**
     * Used to create a custom field metabox to a CPT. Sets up filter column for field as well if option is enabled.
     * @param $pt
     * @param $id
     * @param $label
     * @param $type
     * @param array $options
     * @param array $fieldObj
     * @param string $context
     * @param string $priority
     */
    public static function AddCustomFieldToPost($pt, $id, $label, $type, array $options = array(), $fieldObj = array(), $context = 'normal', $priority = 'high')
    {
        // todo: check permissions to see if user has write permission.
        add_action('save_post', function ($post_id) use ($id) {
            // Bail if we're doing an auto save
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
            // if our nonce isn't there, or we can't verify it, bail
            if (empty($_POST['meta_box_nonce']) || !wp_verify_nonce($_POST['meta_box_nonce'], 'my_meta_box_nonce')) return;
            // if our current user can't edit this post, bail
            if (!current_user_can('edit_post')) return;
            //no data field, bail
            if (empty($_POST[$id])) {
                return;
            }

            // Probably a good idea to make sure your data is set
            $save_value = $_POST[$id];
            if (!empty($_POST["serialize_data_$id"])) {
                if (is_array($_POST[$id])) {
                    $ar = [];
                    if (!is_serialized($_POST[$id][0])) {
                        foreach ($_POST[$id] as $value) {
                            $ar[] = $value['name'];
                        }
                    }
                    $save_value = $ar;
                } else {
                    if (!is_serialized($_POST[$id]['name'])) {
                        $save_value = serialize($_POST[$id]['name']);
                    } else {
                        $save_value = $_POST[$id]['name'];
                    }

                }
                delete_post_meta($post_id, $id);
                update_post_meta($post_id, $id, $save_value);
                return;
            }

            if (is_array($save_value)) {
                delete_post_meta($post_id, $id);
                foreach ($_POST[$id] as $v) {
                    Log::Write($_POST[$id] );
                    update_post_meta($post_id, $id, $v);
                }
                return;
            } else {
                update_post_meta($post_id, $id, $save_value);
                return;
            }

        }, 1000);

        // todo: check permissions to see if user has read permission.
        if (empty($fieldObj['show_on_admin']) || Check::IsTrue($fieldObj['show_on_admin'])) {
            add_action('add_meta_boxes', function () use ($context, $priority, $pt, $id, $label, $type, $options, $fieldObj) {
                add_meta_box(
                    'meta_box_' . $id,
                    $label,
                    function ($post) use ($id, $label, $type, $options) {
                        wp_nonce_field('my_meta_box_nonce', 'meta_box_nonce');
                        echo self::CreateField($id, $id, $label, $type, $options, $post);
                    },
                    $pt,
                    $context,
                    $priority);
            });
        }


    }

    /**
     * Used to create an input field for the meta data based field in the db.
     * Todo: make a function for each case type.
     * @param $field_id
     * @param $field_name
     * @param $label
     * @param $type
     * @param $options
     * @param \WP_Post|null $post
     * @param bool $values
     * @return string
     */
    public static function CreateField($field_id, $field_name, $label, $type, $options, \WP_Post $post = null, $values = false)
    {
        $class = '';
        if (!empty($options['classes'])) {
            $class = implode(" ", (array)$options['classes']);
        }

        // Manage existing values here.
        $values = !empty($post) ?self::ReadFromField(get_the_ID(), $field_id): $values;
        $selected = !empty($values) ? $values : '';
        //Log::Write($selected);

        switch (trim($type)) {
            case 'serialized_list':
                $head = "<!-- Editable table -->
                        <div class='mt-3 table-editable'>
                        <span class='table-row-add'><a href='#!' class='float-right text-success add-td-link'>Add Another $label</a>
                       <table id='table_$field_id' class='table table-bordered table-responsive-md table-striped text-center editable-table'>
                        <thead>
                          <tr>
                            <th class='text-center'>$label</th>
                            <th class='text-center'>Remove</th>
                          </tr>
                        </thead>
                        <tbody>";
                $rTemplate = "   <tr>
                          <td class='pt-3-half' contenteditable='false'>
                            <input type='search' name='" . $field_name . "[][name]' %{name-value}% autocomplete='off' class='form-control autocomplete elem-name'>
                                <button class='autocomplete-clear'>
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
                $foot = "<input type='hidden' name='serialize_data_$field_name' value='true'/></tbody>
                                            </table>   </span>  
                                           </div>
                                        
                <!-- Editable table -->";
                $rows = '';
                //error_log(print_r($selected, true));

                if (!empty($selected) && is_array($selected)) {
                    $count = 0;
                    foreach ($selected as $k) {
                        //error_log(print_r($k, true));
                        if (is_serialized($k)) {
                            $k = unserialize($k);
                            //error_log(is_array($k));
                            if (is_array($k)) {
                                foreach ($k as $kk) {
                                    $rows .= str_replace(['%{count}%', '%{name-value}%'], [$count, "value='$kk'"], $rTemplate);
                                    $count++;
                                }
                            } else {
                                $rows .= str_replace(['%{count}%', '%{name-value}%'], [0, "value='$k'"], $rTemplate);
                            }
                        } else {
                            $rows .= str_replace(['%{count}%', '%{name-value}%'], [$count, "value='$k'"], $rTemplate);
                            $count++;
                        }
                    }
                } else {
                    //error_log('string');
                    $rows .= str_replace(['%{count}%', '%{name-value}%'], [0, "value='$selected'"], $rTemplate);
                }
                $html = $head . $rows . $foot;
                break;
            case 'select':
            case 'list':
                if($type === 'select') {
                    $name = $field_name;
                } else {
                    $name = $field_name.'[]';
                }
                $is_list = $type === 'list' ? 'size="5" multiple' : '';
                $html = "<div class='$class'><label for='$field_id'>$label</label><select class='col $type' id='$field_id' name='".$name."' $is_list>";
                if (!empty($options['values']) && is_array($options['values'])) {
                    //Log::Write($options['values']);
                    foreach ($options['values'] as $k => $v) {
                        //Log::Write($k);
                        //Log::Write($v);
                        $s = in_array($v, (array) $selected) ? 'selected="selected"':'';
                        $html .= "<option class='$class option-$type' value='$v' $s>$k</option>.";
                    }
                }
                $html .= '</select></div>';
                break;
            case 'radio':
            case 'checkbox':
                if($type === 'radio') {
                    $radio_inline = 'radio-inline ml-2';
                    $name = $field_name;
                } else {
                    $radio_inline = '';
                    $name = $field_name.'[]';
                }
                $html = "";
                if (!empty($options['values']) && is_array($options['values'])) {

                    if (!empty($label)) {
                        $html .= "<label class='col-12' for='$field_id'>$label</label>";
                    }

                    // style for special radio buttons that use images instead of buttons.
                    $html .= "<style>
                                /* HIDE RADIO */
                                 .use-images{ 
                                  position: absolute;
                                  opacity: 0;
                                  width: 0;
                                  height: 0;
                                }
                                
                                /* IMAGE STYLES */
                                 .use-images + img {
                                  cursor: pointer;
                                  transition: background-color 0.2s;
                                }
                                
                                /* HOVER STYLES */
                                 .use-images:hover + img {
                                  background-color: #dddddd;
                                  transition: background-color 0.2s;
                                }
                                
                                /* CHECKED STYLES */
                                 .use-images:checked + img {
                                  background-color: #cccccc;
                                  transition: background-color 0.2s;
                                }
                                
                                </style>";
                    $cnt = 0;
                    foreach ($options['values'] as $k => $v) {
                        $lbl = $k;
                        $image_class = '';
                        //Log::Write($v, "Notice:");
                        if(is_array($v)) {
                            // these are the special radio buttons that use images instead of buttons.
                            $image_class = "use-images";
                            $lbl ="<img class='image col' src='".$v['image']."'>";
                            $v = $v['value'];
                        }

                        $s = in_array($v, (array) $selected) ? 'checked':'';
                        $div_class = str_replace('div-','', $class );
                        $html .= $type === 'checkbox'?"<div class='$div_class wrapper'>":'';
                        $label_class = str_replace('label-','', $class );
                        $input_class = str_replace('input-', '', $class);
                        $html .= "
                                        <label class='col $label_class label radio-check-label $type-label $radio_inline' for='$field_id-$cnt'>
                                            <input class='$input_class $type $image_class' 
                                            type='$type'  
                                            id='$field_id-$cnt' 
                                            name='$name' 
                                            value='$v' $s> $lbl
                                       </label>
                                 ";
                        $html .= $type === 'checkbox'?"</div>":'';
                        $cnt++;
                    }
                }
                break;
            case 'note':
                $html = "<div class='$class'>$selected</div>";
                break;

            case 'button':
            case 'reset':
            case 'submit':
                $html = "<button class='$class button'  type='$type'>$selected</button>";
                break;

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
            $placeholder = !empty($options['placeholder'])?$options['placeholder']:'';
            $label_class = str_replace('label-', '', $class);
            $textbox_class = str_replace('textbox-', '', $class);
            $div_class = str_replace('div-', '', $class);
                $html = "<div class='$div_class'>
                                        <label class='$label_class label input-label $type' for='$field_id'>$label</label><br/>
                                       <input class='$textbox_class input $type-label'  id='$field_id' type='$type' name='$field_name' value='$selected' placeholder='$placeholder'/></div>";
                break;
        }
        return $html;
    }

    /**
     * Used to register sortable custom field columns based on a CPT index page. (not filterable)
     * @param $field
     * @param $post_type
     * @param bool $location
     */
    public static function AddFieldToPostAdminColumns($field, $post_type, array $location = []): void
    {

        $label = $field['admin_column_header'] === true ? $field['id'] : $field['admin_column_header'];
        add_filter('manage_' . $post_type . '_posts_columns', function (array $columns) use ($field, $label, $location) {
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
}
