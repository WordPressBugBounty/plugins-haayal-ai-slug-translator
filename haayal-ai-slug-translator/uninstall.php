<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Deletes all options related to the plugin.
 *
 * This method removes options that were stored in the WordPress options table
 * (e.g., settings and logs).
 *
 */
delete_option( 'haayal_ai_slug_translator_settings' );
delete_option( '_ai_slug_error_log' );
delete_option( '_ai_slug_generated_slugs_counter' );
delete_option( 'haayal_ai_proxy_quota_remaining' );
delete_option( 'haayal_slug_translator_dismissed_notice' );
delete_option( 'haayal_dismissed_review_notice' );
delete_option( 'haayal_ai_api_key_status' );
delete_option( 'haayal_redirects_db_version' );
delete_option( 'haayal_bulk_dismissed_ids' );
delete_option( 'haayal_plugin_version' );
delete_option( 'haayal_show_v1_upgrade_notice' );

 /**
 * Drops custom tables and deletes all post meta fields related to the plugin.
 *
 * @global wpdb $wpdb The WordPress database object.
 */
global $wpdb;

// Drop the redirects table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}haayal_redirects" );
$haayal_meta_keys = [
    '_slug_source',
    '_generated_slug',
    '_generated_slugs_counter',
];
foreach ( $haayal_meta_keys as $haayal_meta_key ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk deletion of postmeta requires a direct database query.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $haayal_meta_key
        )
    );
}

// Delete term meta stored by the plugin.
$haayal_term_meta_keys = [
    '_slug_source',
];
foreach ( $haayal_term_meta_keys as $haayal_meta_key ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk deletion of termmeta requires a direct database query.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s",
            $haayal_meta_key
        )
    );
}