<?php
// Plugin Name: Ailo - AI Slug Translator
// Description: Automatically generate English slugs for new posts, pages, CPTs, and taxonomy terms based on their non-english titles using OpenAI API.
// Version: 0.7.3
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
$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), 'plugin');
define('Haayal_AI_SLUG_TRANSLATOR_PLUGIN_VERSION', $plugin_data['Version']);

// Load translation files
function haayal_load_textdomain() {
    load_plugin_textdomain( 'haayal-ai-slug-translator', false, dirname(plugin_basename(__FILE__)) . '/languages/' );
}
add_action( 'init', 'haayal_load_textdomain' );

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
    // Initialize settings, posts, and terms functionality
    new Haayal_AI_Slug_Settings();
    new Haayal_AI_Slug_Posts();
    new Haayal_AI_Slug_Terms();
});

// Hook to add a settings link on the Plugins page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $settings_link = '<a href="options-general.php?page=ai-slug-translator">' . __( 'Settings', 'haayal-ai-slug-translator' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );
