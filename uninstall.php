<?php
/**
 * Uninstall handler.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$delete_data = apply_filters( 'tsrb_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
    return;
}

global $wpdb;

$tables = array(
    $wpdb->prefix . 'studio_bookings',
    $wpdb->prefix . 'studio_coupons',
    $wpdb->prefix . 'studio_addons',
    $wpdb->prefix . 'studio_studios',
    $wpdb->prefix . 'studio_booking_logs',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

delete_option( 'tsrb_settings' );
