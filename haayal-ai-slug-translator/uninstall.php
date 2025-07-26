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
delete_option( 'ai_slug_translator_settings' );
delete_option( '_ai_slug_error_log' );


 /**
 * Deletes all post meta fields related to the plugin.
 *
 * This method removes custom meta fields that were added by the plugin,
 * such as `_slug_source` and `_generated_slugs_counter`.
 *
 * @global wpdb $wpdb The WordPress database object.
 */
global $wpdb;
$meta_keys = [
    '_slug_source',
    '_generated_slug',
    '_generated_slugs_counter',
];
foreach ( $meta_keys as $meta_key ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk deletion of postmeta requires a direct database query.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $meta_key
        )
    );
}