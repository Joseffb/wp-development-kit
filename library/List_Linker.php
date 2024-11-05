<?php

namespace WDK;
/**
 * Usage:
 * Replaces the top add button link and the in-row edit links on a standard WP admin post-type list page.
 *
 * $cpt_links = [
     * 'post_type_1' => [
     * 'add'  => 'admin.php?page=custom-add-page',    // Custom Add New link for CPT
     * 'edit' => 'admin.php?page=custom-edit-page',   // Custom Edit link for CPT
     * ],
     * 'post_type_2' => [
     * 'add'  => 'admin.php?page=custom-add-form2',    // Custom Add New link for  CPT
     * 'edit' => 'admin.php?page=custom-edit-form2',   // Custom Edit link for  CPT
     * ]
 * ];
 * new \WDK\List_Linker($cpt_links, 'my_text_domain');
 */

class List_Linker {
    private array $cpt_links;
    private mixed $text_domain;

    /**
     * Constructor
     *
     * @param array $cpt_links Configuration array mapping CPTs to custom links.
     */
    public function __construct($cpt_links, $text_domain = null) {
        $this->cpt_links = $cpt_links;
        $this->text_domain = $text_domain;
        // Hook into WordPress admin actions and filters
        add_action('admin_menu', [$this, 'replace_add_new_links'], 999);
        add_filter('post_row_actions', [$this, 'replace_edit_row_actions'], 10, 2);

        // Hook to replace the 'Add New' button at the top of the CPT list page
        add_action('admin_head-edit.php', [$this, 'replace_add_new_button']);
    }

    /**
     * Replace the 'Add New' submenu links for specified CPTs.
     */
    public function replace_add_new_links(): void
    {
        foreach ($this->cpt_links as $cpt => $links) {
            // Remove the default 'Add New' submenu
            remove_submenu_page("edit.php?post_type={$cpt}", "post-new.php?post_type={$cpt}");

            // Add a new 'Add New' submenu with the custom link
            add_submenu_page(
                "edit.php?post_type={$cpt}",        // Parent slug
                __('Add New', $this->text_domain??'text-domain'),        // Page title
                __('Add New', $this->text_domain??'text-domain'),        // Menu title
                'manage_options',                    // Capability
                $links['add'],                       // Menu slug (custom link)
                ''                                    // Callback function (not needed due to redirection)
            );

            // Redirect to the custom add page when 'Add New' is clicked
            add_action("load-edit.php?post_type={$cpt}", static function() use ($links) {
                if (isset($_GET['page']) && $_GET['page'] === $links['add']) {
                    wp_redirect(admin_url($links['add']));
                    exit;
                }
            });
        }
    }

    /**
     * Replace the 'Edit' link in row actions with a custom link.
     *
     * @param array $actions Array of row action links.
     * @param \WP_Post $post The current post object.
     * @return array Modified array of row action links.
     */
    public function replace_edit_row_actions(array $actions, \WP_Post $post): array
    {
        $cpt = get_post_type($post->ID);
        if (array_key_exists($cpt, $this->cpt_links)) {
            // Remove the default 'Edit' action
            if (isset($actions['edit'])) {
                unset($actions['edit']);
            }

            // Add a custom 'Edit' action
            $custom_edit_link = admin_url($this->cpt_links[$cpt]['edit'] . '&post_id=' . $post->ID);
            $actions['edit_custom'] = '<a href="' . esc_url($custom_edit_link) . '">' . __('Edit', 'textdomain') . '</a>';
        }
        return $actions;
    }

    /**
     * Replace the 'Add New' button link at the top of the CPT list page with a custom link.
     */
    public function replace_add_new_button(): void
    {
        global $typenow;

        // Ensure $typenow is set
        if (empty($typenow)) {
            $typenow = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
        }

        // Check if the current CPT is in the configuration array
        if (array_key_exists($typenow, $this->cpt_links)) {
            $custom_add_link = admin_url($this->cpt_links[$typenow]['add']);

            // Output JavaScript to change the 'Add New' button's href
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Find the 'Add New' button by class
                    let addNewButton = $('a.page-title-action');
                    if (addNewButton.length) {
                        addNewButton.attr('href', '<?php echo esc_js($custom_add_link); ?>');
                    }
                });
            </script>
            <?php
        }
    }
}
