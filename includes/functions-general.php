<?php

if ( ! defined( 'ABSPATH' ) ) { // Exit if accessed directly
    exit;
}

if ( ! class_exists( 'Fact_Maven_Disable_Blogging_General' ) ):
class Fact_Maven_Disable_Blogging_General {

    //==============================
    // CALL THE FUNCTIONS
    //==============================
    public function __construct() {
        define( 'PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
        $general_settings = get_option( 'factmaven_dsbl_general_settings' );
        
        add_filter( 'custom_menu_order', '__return_true', 10, 1  );
        add_filter( 'menu_order', array( $this, 'menu_order' ), 10, 1 );
        # Disable all posting relate functions
        if ( $general_settings['disable_posts'] == 'disable' ) {
            add_action( 'admin_menu', array( $this, 'posts_menu' ), 10, 1 );
            add_action( 'manage_users_columns', array( $this, 'post_column' ), 10, 1 );
            add_action( 'wp_dashboard_setup', array( $this, 'meta_boxes' ), 10, 1 );
            add_action( 'widgets_init', array( $this, 'widgets' ), 11, 1 );
            add_action( 'load-press-this.php', array( $this, 'press_this' ), 10, 1 );
            add_action( 'admin_init', array( $this, 'posting_options' ), 10, 1 );
            add_action( 'admin_enqueue_scripts', array( $this, 'hide_post_settings' ), 10, 1 );
            add_filter( 'enable_post_by_email_configuration', '__return_false', 10, 1 );
        }
        # Disable all comment relating functions
        if ( $general_settings['disable_comments'] == 'disable' ) {
            add_action( 'admin_menu', array( $this, 'comments_menu' ), 10, 1 );
            add_action( 'init', array( $this, 'comments_column' ), 10, 1 );
            add_action( 'admin_enqueue_scripts', array( $this, 'hide_comment_settings' ), 10, 1 );
            add_action( 'admin_init', array( $this, 'comment_options' ), 10, 1 );
            add_filter( 'comments_template', array( $this, 'comments_template' ), 20, 1 );
        }

        if ( $general_settings['disable_feeds'] == 'disable' ) {
            add_action( 'wp_loaded', array( $this, 'header_feeds' ), 1, 1 );
            add_action( 'template_redirect', array( $this, 'filter_feeds' ), 1, 1 );
            add_action( 'pre_ping', array( $this, 'internal_pingbacks' ), 10, 1 );
            add_filter( 'wp_headers', array( $this, 'x_pingback' ), 10, 1 );
            add_filter( 'bloginfo_url', array( $this, 'pingback_url' ), 1, 2 );
            add_filter( 'bloginfo', array( $this, 'pingback_url' ), 1, 2 );
            add_filter( 'xmlrpc_methods', array( $this, 'xmlrpc' ), 10, 1 );
            add_filter( 'xmlrpc_enabled', '__return_false', 10, 1 );
        }
    }

    //==============================
    // BEGIN THE FUNCTIONS
    //==============================
    public function menu_order() { // move Pages up the top in the sidebar menu
        return array( 'index.php', 'edit.php?post_type=page' );
    }

    public function posts_menu() { // Remove menu/submenu items & redirect to page menu
        $menu_slug = array(
            'edit.php', // Posts
            'separator1',  'separator2', 'separator3' // Separators
            );
        foreach ( $menu_slug as $main ) {
            remove_menu_page( $main );
        }
        $menu_slug = array(
            'tools.php' => 'tools.php', // Tools > Available Tools
            'options-general.php' => 'options-writing.php', // Settings > Writing
        );
        foreach( $menu_slug as $main => $sub ) {
            remove_submenu_page( $main, $sub );
        }
        global $pagenow;
        $page = array(
            'edit.php', // Posts
            'post-new.php', // New Post
            'edit-tags.php', // Tags
            'options-writing.php', // Settings > Writing
            );
        if ( in_array( $pagenow, $page, true ) && ( ! isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) ) {
            wp_redirect( admin_url( 'edit.php?post_type=page' ), 301 );
            exit;
        }
    }

    public function post_column( $column ) { // Remove posts column
        unset( $column['posts'] );
        return $column;
    }

    public function meta_boxes() { // Disable blogging related meta boxes on the Dashboard
        remove_action( 'welcome_panel', 'wp_welcome_panel' ); // Welcome
        $meta_box = array(
            'dashboard_primary' => 'side', // WordPress Blog
            'dashboard_quick_press' => 'side', // Quick Draft
            'dashboard_right_now' => 'normal', // At a Glance
            'dashboard_incoming_links' => 'normal', // Incoming Links
            'dashboard_activity' => 'normal', // Activity
            'wpe_dify_news_feed' => 'normal' // WP Engine
            );
        foreach ( $meta_box as $id => $context ) {
            remove_meta_box( $id, 'dashboard', $context ); 
        }
    }

    public function widgets() { // Remove blog related widgets
        $widgets = array(
            'WP_Widget_Archives', // Archives
            'WP_Widget_Calendar', // Calendar
            'WP_Widget_Categories', // Categories
            'WP_Widget_Links', // Links
            'WP_Widget_Meta', // Meta
            'WP_Widget_Recent_Comments', // Recent Comments
            'WP_Widget_Recent_Posts', // Recent Posts
            'WP_Widget_RSS', // RSS
            'WP_Widget_Tag_Cloud' // Tag Cloud
        );
        foreach( $widgets as $item ) {
            unregister_widget( $item );
        }
    }

    public function press_this() { // Disables "Press This" and redirect to homepage
        wp_safe_redirect( home_url(), 301 );
    }

    public function posting_options() { // Default the reading settings to a static page
        if ( 'posts' == get_option( 'show_on_front' ) ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_for_posts', 0 );
            update_option( 'page_on_front', 1 );
        }
        update_option( 'default_pingback_flag ', 0 );
        update_option( 'default_ping_status ', 0 );
    }

    public function hide_post_settings() {
        global $pagenow;
        // wp_enqueue_style( 'dsbl-wp-admin', plugin_dir_url( __FILE__ ) . 'css/wp-admin.css' );
        if ( $pagenow == 'tools.php' ) {
            wp_enqueue_style( 'dsbl-tools', plugin_dir_url( __FILE__ ) . 'css/tools.css' );
        }
        if ( $pagenow == 'options-reading.php' ) {
            wp_enqueue_style( 'dsbl-options-reading', plugin_dir_url( __FILE__ ) . 'css/options-reading.css' );
        }
    }

    /**
     * Disable Comment related functions
     */
    public function comments_menu() { // Remove menu/submenu items & redirect to page menu
        global $pagenow;
        $menu_slug = array(
            'edit-comments.php', // Comments
            'separator1',  'separator2', 'separator3' // Separators
            );
        foreach ( $menu_slug as $main ) {
            remove_menu_page( $main );
        }
        if ( in_array( $pagenow, $menu_slug, true ) && ( ! isset( $_GET['post_type'] ) || isset( $_GET['post_type'] ) && $_GET['post_type'] == 'post' ) ) {
            wp_redirect( admin_url( 'edit.php?post_type=page' ), 301 );
            exit;
        }
    }

    public function comments_column() { // Remove comments column from posts & pages
        $menu_slug = array(
            'post' => 'comments', // Posts
            'page' => 'comments', // Pages
            'attachment' => 'comments' // Media
            );
        foreach ( $menu_slug as $item => $column ) {
            remove_post_type_support( $item, $column );
        }
    }

    public function hide_comment_settings() {
        global $pagenow;
        wp_enqueue_style( 'dsbl-wp-admin', plugin_dir_url( __FILE__ ) . 'css/wp-admin.css' );
        if ( $pagenow == 'tools.php' ) {
            wp_enqueue_style( 'dsbl-tools', plugin_dir_url( __FILE__ ) . 'css/tools.css' );
        }
        if ( $pagenow == 'options-reading.php' ) {
            wp_enqueue_style( 'dsbl-options-reading', plugin_dir_url( __FILE__ ) . 'css/options-reading.css' );
        }
    }

    public function comment_options() {
        # Allow people to post comments on new articles (unchecked)
        update_option( 'default_comment_status', 0 );
        # Comment must be manually approved (checked)
        update_option( 'comment_moderation', 1 );
    }

    public function comments_template() { // Replaces theme's comments template with empty page
        return PLUGIN_PATH . '/index.php';
    }

    public function header_feeds() { // Remove feed links from the header
        $feed = array(
            'feed_links' => 2, // General feeds
            'feed_links_extra' => 3, // Extra feeds
            'rsd_link' => 10, // Really Simply Discovery & EditURI
            'wlwmanifest_link' => 10, // Windows Live Writer manifest
            'index_rel_link' => 10, // Index link
            'parent_post_rel_link' => 10, // Prev link
            'start_post_rel_link' => 10, // Start link
            'adjacent_posts_rel_link' => 10, // Relational links
            'wp_generator' => 10 // WordPress version
            );
        foreach ( $feed as $function => $priority ) {
            remove_action( 'wp_head', $function, $priority );
        }
    }

    public function filter_feeds() { // Prevent redirect loop
        if ( !is_feed() || is_404() ) {
            return;
        }
        $this -> redirect_feeds();
    }

    private function redirect_feeds() { // Redirect all feeds to homepage
        global $wp_rewrite, $wp_query;

        if ( isset( $_GET['feed'] ) ) {
            wp_safe_redirect( esc_url_raw( remove_query_arg( 'feed' ) ), 301 );
            exit;
        }

        if ( get_query_var( 'feed' ) !== 'old' ) {
            set_query_var( 'feed', '' );
        }
        redirect_canonical();

        $url_struct = ( !is_singular() && is_comment_feed() ) ? $wp_rewrite -> get_comment_feed_permastruct() : $wp_rewrite -> get_feed_permastruct();
        $url_struct = preg_quote( $url_struct, '#' );
        $url_struct = str_replace( '%feed%', '(\w+)?', $url_struct );
        $url_struct = preg_replace( '#/+#', '/', $url_struct );
        $url_current = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $url_new = preg_replace( '#' . $url_struct . '/?$#', '', $url_current );

        if ( $url_new != $url_current ) {
            wp_safe_redirect( $url_new, 301 );
            exit;
        }
    }

    public function internal_pingbacks( &$links ) { // Disable internal pingbacks
        foreach ( $links as $l => $link ) {
            if ( 0 === strpos( $link, get_option( 'home' ) ) ) {
                unset( $links[$l] );
            }
        }
    }

    public function x_pingback( $headers ) { // Disable x-pingback
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    public function pingback_url( $output, $show ) { // Remove pingback URLs
        if ( $show == 'pingback_url' ) $output = '';
        return $output;
    }

    public function xmlrpc( $methods ) { // Disable XML-RPC methods
        unset( $methods['pingback.ping'] );
        return $methods;
    }
}
endif;

new Fact_Maven_Disable_Blogging_General();