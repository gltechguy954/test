<?php
/**
 * Plugin Name: UC Townsquare Custom Group Tabs
 * Plugin URI: https://yourwebsite.com
 * Description: Allows BuddyBoss group organizers to create custom tabs with rich text content
 * Version: 1.2.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: bb-custom-tabs
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BB_Custom_Tabs {

    public function __construct() {
        add_action( 'init',                          array( $this, 'load_textdomain' ) );
        register_activation_hook( __FILE__,           array( $this, 'activate' ) );
        add_action( 'init',                          array( $this, 'register_post_type' ) );
        add_action( 'bp_groups_admin_meta_boxes',    array( $this, 'add_meta_box' ) );
        add_action( 'bp_loaded',                     array( $this, 'register_group_extension' ) );
        add_action( 'wp_ajax_bb_custom_tabs_add',    array( $this, 'ajax_add_tab' ) );
        add_action( 'wp_ajax_bb_custom_tabs_edit',   array( $this, 'ajax_edit_tab' ) );
        add_action( 'wp_ajax_bb_custom_tabs_delete', array( $this, 'ajax_delete_tab' ) );
        add_action( 'wp_ajax_bb_custom_tabs_get',    array( $this, 'ajax_get_tab' ) );
        add_action( 'wp_enqueue_scripts',            array( $this, 'enqueue_scripts' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'bb-custom-tabs',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages/'
        );
    }

    public function activate() {
        $this->register_post_type();
        flush_rewrite_rules();
    }

    public function register_post_type() {
        register_post_type( 'bb_custom_tab', array(
            'labels'             => array(
                'name'               => _x( 'Custom Tabs', 'post type general name', 'bb-custom-tabs' ),
                'singular_name'      => _x( 'Custom Tab', 'post type singular name', 'bb-custom-tabs' ),
                'add_new_item'       => __( 'Add New Custom Tab', 'bb-custom-tabs' ),
                'edit_item'          => __( 'Edit Custom Tab', 'bb-custom-tabs' ),
                'all_items'          => __( 'All Custom Tabs', 'bb-custom-tabs' ),
                'not_found'          => __( 'No custom tabs found.', 'bb-custom-tabs' ),
                'not_found_in_trash' => __( 'No custom tabs in Trash.', 'bb-custom-tabs' ),
            ),
            'public'             => false,
            'show_ui'            => false,
            'has_archive'        => false,
            'supports'           => array( 'title', 'editor' ),
        ) );
    }

    public function add_meta_box() {
        $screen = get_current_screen();
        if ( $screen && 'buddyboss_page_bp-groups' === $screen->id ) {
            add_meta_box(
                'bb-custom-tabs',
                __( 'Custom Tabs', 'bb-custom-tabs' ),
                array( $this, 'render_meta_box' ),
                $screen->id,
                'side'
            );
        }
    }

    public function render_meta_box() {
        $group_id = bp_get_current_group_id();
        $tabs     = $this->get_group_tabs( $group_id );

        echo '<div class="bb-custom-tabs-admin">';
        echo '<p>' . esc_html__( 'Create custom tabs for this group.', 'bb-custom-tabs' ) . '</p>';
        echo '<div id="bb-custom-tabs-list">';
        if ( ! empty( $tabs ) ) {
            foreach ( $tabs as $tab ) {
                printf(
                    '<div class="bb-custom-tab-item" data-id="%1$d">
                        <strong class="tab-title">%2$s</strong>
                        <span class="tab-slug">(%3$s)</span>
                        <div class="tab-actions">
                            <a href="#" class="edit-tab"   data-id="%1$d">%4$s</a> |
                            <a href="#" class="delete-tab" data-id="%1$d">%5$s</a>
                        </div>
                    </div>',
                    esc_attr( $tab['id'] ),
                    esc_html( $tab['title'] ),
                    esc_html( $tab['slug'] ),
                    esc_html__( 'Edit',   'bb-custom-tabs' ),
                    esc_html__( 'Delete', 'bb-custom-tabs' )
                );
            }
        } else {
            echo '<p class="no-tabs">' . esc_html__( 'No custom tabs created yet.', 'bb-custom-tabs' ) . '</p>';
        }
        echo '</div>';
        echo '<p><a href="#" class="button" id="bb-add-new-tab">' . esc_html__( 'Add New Tab', 'bb-custom-tabs' ) . '</a></p>';
        echo '<div id="bb-tab-form" style="display:none;">';
        echo   '<input type="hidden" id="tab-id"   name="tab_id"   value="">';
        echo   '<input type="hidden" id="group-id" name="group_id" value="' . esc_attr( $group_id ) . '">';
        echo   '<p><label for="tab-title">'   . esc_html__( 'Tab Title:',   'bb-custom-tabs' ) . '</label> <input type="text" id="tab-title" name="tab_title"></p>';
        echo   '<p><label for="tab-slug">'    . esc_html__( 'Tab Slug:',    'bb-custom-tabs' ) . '</label> <input type="text" id="tab-slug"  name="tab_slug"></p>';
        echo   '<p><em>' . esc_html__( 'Lowercase letters and hyphens only', 'bb-custom-tabs' ) . '</em></p>';
        echo   '<div><label for="tab-content">' . esc_html__( 'Tab Content:', 'bb-custom-tabs' ) . '</label><textarea id="tab-content" name="tab_content"></textarea></div>';
        echo   '<p><button type="button" class="button button-primary" id="bb-save-tab">' . esc_html__( 'Save Tab', 'bb-custom-tabs' ) . '</button> <button type="button" class="button" id="bb-cancel-tab">' . esc_html__( 'Cancel', 'bb-custom-tabs' ) . '</button></p>';
        echo '</div>';
        echo '</div>';
    }

    public function register_group_extension() {
        if ( class_exists( 'BP_Group_Extension' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'class-bb-custom-tabs-extension.php';
            bp_register_group_extension( 'BB_Custom_Tabs_Extension' );
        }
    }

    public function ajax_add_tab() {
        check_ajax_referer( 'bb-custom-tabs-nonce', 'nonce' );
        $group_id = intval( $_POST['group_id'] ?? 0 );
        if ( ! $this->user_can_manage_tabs( $group_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'bb-custom-tabs' ) ) );
        }
        $title   = sanitize_text_field( $_POST['tab_title']   ?? '' );
        $slug    = sanitize_title(      $_POST['tab_slug']    ?? '' );
        $content = wp_kses_post(        $_POST['tab_content'] ?? '' );
        if ( '' === $title || '' === $slug ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Title and slug are required.', 'bb-custom-tabs' ) ) );
        }
        $tab_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'bb_custom_tab',
        ) );
        if ( is_wp_error( $tab_id ) ) {
            wp_send_json_error( array( 'message' => $tab_id->get_error_message() ) );
        }
        update_post_meta( $tab_id, 'group_id', $group_id );
        update_post_meta( $tab_id, 'tab_slug',  $slug );
        wp_send_json_success( array(
            'message' => esc_html__( 'Tab added successfully!', 'bb-custom-tabs' ),
            'tab'     => compact( 'tab_id', 'title', 'slug' ),
        ) );
    }

    public function ajax_edit_tab() {
        check_ajax_referer( 'bb-custom-tabs-nonce', 'nonce' );
        $tab_id = intval( $_POST['tab_id'] ?? 0 );
        $post   = get_post( $tab_id );
        if ( ! $post || 'bb_custom_tab' !== $post->post_type ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid tab.', 'bb-custom-tabs' ) ) );
        }
        $group_id = intval( get_post_meta( $tab_id, 'group_id', true ) );
        if ( ! $this->user_can_manage_tabs( $group_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'bb-custom-tabs' ) ) );
        }
        $title   = sanitize_text_field( $_POST['tab_title']   ?? '' );
        $slug    = sanitize_title(      $_POST['tab_slug']    ?? '' );
        $content = wp_kses_post(        $_POST['tab_content'] ?? '' );
        if ( '' === $title || '' === $slug ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Title and slug are required.', 'bb-custom-tabs' ) ) );
        }
        wp_update_post( array(
            'ID'           => $tab_id,
            'post_title'   => $title,
            'post_content' => $content,
        ) );
        update_post_meta( $tab_id, 'tab_slug', $slug );
        wp_send_json_success( array(
            'message' => esc_html__( 'Tab updated successfully!', 'bb-custom-tabs' ),
            'tab'     => compact( 'tab_id', 'title', 'slug' ),
        ) );
    }

    public function ajax_delete_tab() {
        check_ajax_referer( 'bb-custom-tabs-nonce', 'nonce' );
        $tab_id = intval( $_POST['tab_id'] ?? 0 );
        $post   = get_post( $tab_id );
        if ( ! $post || 'bb_custom_tab' !== $post->post_type ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid tab.', 'bb-custom-tabs' ) ) );
        }
        $group_id = intval( get_post_meta( $tab_id, 'group_id', true ) );
        if ( ! $this->user_can_manage_tabs( $group_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'bb-custom-tabs' ) ) );
        }
        wp_delete_post( $tab_id, true );
        wp_send_json_success( array( 'message' => esc_html__( 'Tab deleted successfully!', 'bb-custom-tabs' ) ) );
    }

    public function ajax_get_tab() {
        check_ajax_referer( 'bb-custom-tabs-nonce', 'nonce' );
        $tab_id = intval( $_POST['tab_id'] ?? 0 );
        $post   = get_post( $tab_id );
        if ( ! $post || 'bb_custom_tab' !== $post->post_type ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid tab.', 'bb-custom-tabs' ) ) );
        }
        $group_id = intval( get_post_meta( $tab_id, 'group_id', true ) );
        if ( ! $this->user_can_manage_tabs( $group_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'bb-custom-tabs' ) ) );
        }
        wp_send_json_success( array( 'tab' => array(
            'id'      => $tab_id,
            'title'   => get_the_title( $tab_id ),
            'slug'    => get_post_meta( $tab_id, 'tab_slug', true ),
            'content' => $post->post_content,
        ) ) );
    }

    public function enqueue_scripts() {
        if ( function_exists( 'bp_is_group' ) && bp_is_group() && ( bp_group_is_admin() || bp_group_is_mod() ) ) {
            wp_enqueue_style(
                'bb-custom-tabs-admin-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/bb-custom-tabs-admin.css',
                array(),
                '1.2.0'
            );
            wp_enqueue_script(
                'bb-custom-tabs-admin-js',
                plugin_dir_url( __FILE__ ) . 'assets/js/bb-custom-tabs-admin.js',
                array( 'jquery', 'wp-editor' ),
                '1.2.0',
                true
            );
            wp_localize_script(
                'bb-custom-tabs-admin-js',
                'bbCustomTabs',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'bb-custom-tabs-nonce' ),
                )
            );
            wp_enqueue_editor();
        }
    }

    private function user_can_manage_tabs( $group_id ) {
        if ( ! is_user_logged_in() || ! $group_id ) {
            return false;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        if ( ! function_exists( 'groups_is_user_admin' ) || ! function_exists( 'groups_is_user_mod' ) ) {
            return false;
        }
        return groups_is_user_admin( get_current_user_id(), $group_id )
            || groups_is_user_mod( get_current_user_id(), $group_id );
    }

    private function get_group_tabs( $group_id ) {
        $tabs  = array();
        $query = new WP_Query( array(
            'post_type'      => 'bb_custom_tab',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'group_id',
                    'value'   => $group_id,
                    'compare' => '=',
                ),
            ),
        ) );
        while ( $query->have_posts() ) {
            $query->the_post();
            $tabs[] = array(
                'id'      => get_the_ID(),
                'title'   => get_the_title(),
                'slug'    => get_post_meta( get_the_ID(), 'tab_slug', true ),
                'content' => get_the_content(),
            );
        }
        wp_reset_postdata();
        return $tabs;
    }
}

new BB_Custom_Tabs();
