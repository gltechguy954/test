<?php
/**
 * BuddyBoss Custom Tabs Extension Class
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BB_Custom_Tabs_Extension extends BP_Group_Extension {
    /**
     * Constructor
     */
    public function __construct() {
        $args = array(
            'slug' => 'custom-tabs',
            'name' => __('Custom Tabs', 'bb-custom-tabs'),
            'nav_item_position' => 81,
            'access' => 'noone', // We'll handle access control in the screen methods
            'show_tab' => 'noone', // Hide the main tab, we'll create individual tabs
        );
        
        parent::init($args);
        
        // Register custom tabs as navigation items
        add_action('bp_actions', array($this, 'register_custom_nav_items'), 20);
    }
    
    /**
     * Display tab content
     */
    public function display($group_id = null) {
        // This is a placeholder for the main custom tabs screen
        // Individual tabs are handled by the custom screen functions
        echo '<div class="bp-custom-tabs-container">';
        echo '<p>' . __('Please select a custom tab from the navigation menu.', 'bb-custom-tabs') . '</p>';
        echo '</div>';
    }
    
    /**
     * Display screen for managing tabs (for group admins)
     */
    public function settings_screen($group_id = null) {
        if (!$this->user_can_manage_tabs($group_id)) {
            return;
        }
        
        $tabs = $this->get_group_tabs($group_id);
        ?>
        <div class="bb-custom-tabs-manager">
            <h3><?php _e('Manage Custom Tabs', 'bb-custom-tabs'); ?></h3>
            
            <div id="bb-custom-tabs-list">
                <?php if (!empty($tabs)) : ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Title', 'bb-custom-tabs'); ?></th>
                                <th><?php _e('Slug', 'bb-custom-tabs'); ?></th>
                                <th><?php _e('Actions', 'bb-custom-tabs'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tabs as $tab) : ?>
                                <tr class="bb-custom-tab-item" data-id="<?php echo esc_attr($tab['id']); ?>">
                                    <td class="tab-title"><?php echo esc_html($tab['title']); ?></td>
                                    <td class="tab-slug"><?php echo esc_html($tab['slug']); ?></td>
                                    <td class="tab-actions">
                                        <a href="#" class="edit-tab" data-id="<?php echo esc_attr($tab['id']); ?>"><?php _e('Edit', 'bb-custom-tabs'); ?></a> | 
                                        <a href="#" class="delete-tab" data-id="<?php echo esc_attr($tab['id']); ?>"><?php _e('Delete', 'bb-custom-tabs'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="no-tabs"><?php _e('No custom tabs created yet.', 'bb-custom-tabs'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="bb-custom-tabs-actions">
                <a href="#" class="button" id="bb-add-new-tab"><?php _e('Add New Tab', 'bb-custom-tabs'); ?></a>
            </div>
            
            <!-- Tab Edit/Add Form (Hidden by default) -->
            <div id="bb-tab-form" style="display: none;">
                <h4 id="tab-form-title"><?php _e('Add New Tab', 'bb-custom-tabs'); ?></h4>
                
                <input type="hidden" id="tab-id" name="tab_id" value="">
                <input type="hidden" id="group-id" name="group_id" value="<?php echo esc_attr($group_id); ?>">
                
                <div class="bp-form-block">
                    <label for="tab-title"><?php _e('Tab Title:', 'bb-custom-tabs'); ?></label>
                    <input type="text" id="tab-title" name="tab_title" value="">
                </div>
                
                <div class="bp-form-block">
                    <label for="tab-slug"><?php _e('Tab Slug:', 'bb-custom-tabs'); ?></label>
                    <input type="text" id="tab-slug" name="tab_slug" value="">
                    <p class="description"><?php _e('Lowercase letters and hyphens only. This will be used in the URL.', 'bb-custom-tabs'); ?></p>
                </div>
                
                <div class="bp-form-block tab-content-field">
                    <label for="tab-content"><?php _e('Tab Content:', 'bb-custom-tabs'); ?></label>
                    <?php
                    // Use WordPress editor
                    wp_editor('', 'tab-content', array(
                        'media_buttons' => true,
                        'textarea_name' => 'tab_content',
                        'textarea_rows' => 10,
                        'editor_class' => 'tab-content-editor',
                        'editor_height' => 200
                    ));
                    ?>
                    <p class="description"><?php _e('You can include text, HTML, and iframe content.', 'bb-custom-tabs'); ?></p>
                </div>
                
                <div class="bp-form-block tab-form-actions">
                    <button type="button" class="button button-primary" id="bb-save-tab"><?php _e('Save Tab', 'bb-custom-tabs'); ?></button>
                    <button type="button" class="button" id="bb-cancel-tab"><?php _e('Cancel', 'bb-custom-tabs'); ?></button>
                </div>
            </div>
            
            <div id="bb-tab-response" class="updated" style="display: none;"></div>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    public function settings_screen_save($group_id = null) {
        // Settings are saved via AJAX
    }
    
    /**
     * Register custom navigation items for the group
     */
    public function register_custom_nav_items() {
        if (!bp_is_group()) {
            return;
        }
        
        $group_id = bp_get_current_group_id();
        $tabs = $this->get_group_tabs($group_id);
        
        if (empty($tabs)) {
            return;
        }
        
        // Register each custom tab as a navigation item
        foreach ($tabs as $tab) {
            bp_core_new_subnav_item(array(
                'name' => esc_html($tab['title']),
                'slug' => sanitize_title($tab['slug']),
                'parent_url' => bp_get_group_permalink(groups_get_current_group()),
                'parent_slug' => bp_get_current_group_slug(),
                'screen_function' => array($this, 'display_tab_content'),
                'position' => 100 + intval($tab['id']),  // Position after standard tabs
                'user_has_access' => true,
                'item_css_id' => 'custom-tab-' . intval($tab['id'])
            ));
            
            // Add a callback to display the content for this tab
            add_action('bp_template_content_' . sanitize_title($tab['slug']), function() use ($tab) {
                echo '<div class="bb-custom-tab-content">';
                echo apply_filters('the_content', $tab['content']);
                echo '</div>';
            });
        }
    }
    
    /**
     * Display custom tab content
     */
    public function display_tab_content() {
        // Load the appropriate template
        add_action('bp_template_content', array($this, 'tab_content'));
        bp_core_load_template(apply_filters('bp_core_template_plugin', 'groups/single/plugins'));
    }
    
    /**
     * Display tab content (this is a placeholder, content is added dynamically)
     */
    public function tab_content() {
        // Content is added via the bp_template_content_{$tab_slug} hook
    }
    
    /**
     * Check if user can manage tabs for a group
     * 
     * @param int $group_id The group ID
     * @return bool True if user can manage tabs, false otherwise
     */
    public function user_can_manage_tabs($group_id) {
        if (!is_user_logged_in() || !$group_id) {
            return false;
        }
        
        if (current_user_can('manage_options')) {
            return true;
        }
        
        return groups_is_user_admin(get_current_user_id(), $group_id) || groups_is_user_mod(get_current_user_id(), $group_id);
    }
    
    /**
     * Get all custom tabs for a group
     * 
     * @param int $group_id The group ID
     * @return array Array of tab data
     */
    public function get_group_tabs($group_id) {
        $tabs = array();
        
        $args = array(
            'post_type' => 'bb_custom_tab',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'group_id',
                    'value' => $group_id,
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $tabs[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'slug' => get_post_meta(get_the_ID(), 'tab_slug', true),
                    'content' => get_the_content()
                );
            }
        }
        
        wp_reset_postdata();
        
        return $tabs;
    }
}
