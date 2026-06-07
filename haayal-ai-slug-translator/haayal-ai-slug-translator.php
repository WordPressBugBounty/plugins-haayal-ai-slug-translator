<?php
// Plugin Name: Ailo - AI Slug Translator
// Description: Automatically generate English slugs for new posts, pages, CPTs, and taxonomy terms based on their non-english titles using AI.
// Version: 1.0.2
// Author: Elchanan Levavi
// Author URI: https://ha-ayal.co.il
// Plugin URI: https://wordpress.org/plugins/haayal-ai-slug-translator/
// Requires PHP: 7.4
// Text Domain: haayal-ai-slug-translator
// Domain Path:       /languages
// License: GPLv2 or later

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

// Extract plugin version from the header comment
$haayal_plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), 'plugin');
define('HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION', $haayal_plugin_data['Version']);

// Plugin constants
define( 'HAAYAL_AI_SLUG_OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions' );
define( 'HAAYAL_AI_SLUG_PROXY_ENDPOINT', 'https://dev.ha-ayal.co.il/slug-translator/wp-json/ai-slug/v1/translate' );
define( 'HAAYAL_AI_SLUG_TRANSLATION_MODELS', [ 'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4.1' ] );
define( 'HAAYAL_AI_SLUG_VALIDATION_MODEL', 'gpt-4.1-mini' );
define( 'HAAYAL_AI_SLUG_API_TIMEOUT', 20 );
define( 'HAAYAL_AI_SLUG_PROXY_TIMEOUT', 10 );
define( 'HAAYAL_AI_SLUG_VALIDATION_TIMEOUT', 10 );
define( 'HAAYAL_AI_SLUG_BULK_TRANSLATE_BATCH', 1 );
define( 'HAAYAL_AI_SLUG_BULK_SAVE_BATCH', 5 );

// Autoloader for loading classes
spl_autoload_register( function( $class_name ) {
    if ( strpos( $class_name, 'Haayal_AI_Slug_' ) === 0 ) {
        $file_name = strtolower( str_replace( ['Haayal_', '_'], ['', '-'], $class_name ) );
        $file = plugin_dir_path( __FILE__ ) . 'includes/class-' . $file_name . '.php';
        
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
});


// Initialize the plugin
add_action( 'plugins_loaded', function() {
    // Initialize notices
    Haayal_AI_Slug_Notices::init();

    // Initialize settings and admin page unconditionally.
    new Haayal_AI_Slug_Settings();
    new Haayal_AI_Slug_Admin_Page();

    // Always initialize redirects (manage existing rules, keep admin tab accessible).
    Haayal_AI_Slug_Redirects::init();

    // Plain/simple permalink structure (?p=123) does not support custom slugs.
    // Skip all translation and badge functionality when it is active.
    if ( '' === get_option( 'permalink_structure' ) ) {
        return;
    }

    new Haayal_AI_Slug_Posts();
    new Haayal_AI_Slug_Terms();
    Haayal_AI_Slug_Bulk::init();
});

// Create redirects table and write default settings on first activation.
register_activation_hook( __FILE__, function() {
    Haayal_AI_Slug_Redirects::install();
    Haayal_AI_Slug_Settings::maybe_set_defaults();
} );

// Hook to add a settings link on the Plugins page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=ai-slug-translator' ) ) . '">' . esc_html__( 'Settings', 'haayal-ai-slug-translator' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );

// Enqueue SweetAlert2 and deactivation warning script on the plugins page.
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( 'plugins.php' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'sweetalert2',
        plugin_dir_url( __FILE__ ) . 'assets/vendor/sweetalert2/sweetalert2.min.css',
        [],
        '11'
    );
    wp_enqueue_script(
        'sweetalert2',
        plugin_dir_url( __FILE__ ) . 'assets/vendor/sweetalert2/sweetalert2.all.min.js',
        [],
        '11',
        true
    );

    // Only enqueue the deactivation warning if active redirects exist.
    global $wpdb;
    $table = $wpdb->prefix . 'haayal_redirects';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name is a safe constant derived from $wpdb->prefix.
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is a safe constant derived from $wpdb->prefix.
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    if ( $count <= 0 ) {
        return;
    }

    wp_enqueue_script(
        'haayal-deactivation',
        plugin_dir_url( __FILE__ ) . 'assets/js/ai-slug-deactivation.js',
        [ 'jquery', 'sweetalert2' ],
        HAAYAL_AI_SLUG_TRANSLATOR_PLUGIN_VERSION,
        true
    );

    wp_localize_script( 'haayal-deactivation', 'haayalDeactivation', [
        'pluginFile'  => plugin_basename( __FILE__ ),
        'title'       => __( 'Are you sure?', 'haayal-ai-slug-translator' ),
        'message'     => sprintf(
            /* translators: %d is the number of active redirect rules */
            __( 'You have %d active 301 redirect rules created by this plugin. Deactivating will disable all redirects, which may cause 404 errors and affect your SEO.', 'haayal-ai-slug-translator' ),
            $count
        ),
        'confirmText' => __( 'Yes, deactivate', 'haayal-ai-slug-translator' ),
        'cancelText'  => __( 'Cancel', 'haayal-ai-slug-translator' ),
    ] );
} );
