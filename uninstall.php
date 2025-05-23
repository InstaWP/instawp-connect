<?php

// If uninstall not called from WordPress, then exit.
use InstaWP\Connect\Helpers\Helper;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

defined( 'INSTAWP_DEFAULT_BACKUP_DIR' ) || define( 'INSTAWP_DEFAULT_BACKUP_DIR', 'instawpbackups' );

$instawp_backup_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR . DIRECTORY_SEPARATOR;
if ( file_exists( $instawp_backup_dir ) ) {
	$files_to_delete = scandir( $instawp_backup_dir );
	$files_to_delete = ! is_array( $files_to_delete ) ? array() : $files_to_delete;
	$files_to_delete = array_diff( $files_to_delete, array( '.', '..' ) );

	foreach ( $files_to_delete as $file ) {
		if ( is_file( $instawp_backup_dir . $file ) ) {
			wp_delete_file( $instawp_backup_dir . $file );
		}
	}
}

wp_delete_file( ABSPATH . 'fwd.php' );

$api_options = get_option( 'instawp_api_options', array() );
$connect_id  = isset( $api_options['connect_id'] ) ? $api_options['connect_id'] : '';
$api_key     = isset( $api_options['api_key'] ) ? $api_options['api_key'] : '';
$api_url     = isset( $api_options['api_url'] ) ? $api_options['api_url'] : 'https://app.instawp.io';
$site_url    = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'" );
$site_url    = empty( $site_url ) ? site_url() : $site_url;

if ( ! empty( $connect_id ) && ! empty( $api_key ) ) {
	$args = array(
		'headers'         => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			'Referer'       => $site_url,
		),
		'timeout'         => 60,
		'redirection'     => 10,
		'httpversion'     => '1.1',
		'user-agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		'sslverify'       => false,
		'sslverifyhost'   => false,
		'follow_location' => true,
		'max_redirects'   => 10,
	);

	wp_remote_post( "{$api_url}/api/v2/connects/{$connect_id}/disconnect", $args );
}

delete_option( 'instawp_api_options' );
delete_option( 'instawp_large_files_list' );
delete_option( 'instawp_backup_part_size' );
delete_option( 'instawp_max_file_size_allowed' );
delete_option( 'instawp_reset_type' );
delete_option( 'instawp_db_method' );
delete_option( 'instawp_default_user' );
delete_option( 'instawp_api_options' );
delete_option( 'instawp_rm_heartbeat' );
delete_option( 'instawp_api_heartbeat' );
delete_option( 'instawp_rm_file_manager' );
delete_option( 'instawp_migration_details' );
delete_option( 'instawp_rm_database_manager' );
delete_option( 'instawp_rm_install_plugin_theme' );
delete_option( 'instawp_rm_config_management' );
delete_option( 'instawp_rm_inventory' );
delete_option( 'instawp_rm_debug_log' );
delete_option( 'instawp_last_heartbeat_sent' );
delete_option( 'instawp_is_staging' );
delete_option( 'instawp_is_event_syncing' );
delete_option( 'instawp_staging_sites' );

delete_transient( 'instawp_migration_completed' );
delete_transient( 'instawp_generate_large_files' );

// Clear scheduled tasks.
if ( class_exists( 'ActionScheduler_QueueRunner' ) ) {
	ActionScheduler_QueueRunner::instance()->unhook_dispatch_async_request();
}
if ( class_exists( 'ActionScheduler_DBStore' ) ) {
	ActionScheduler_DBStore::instance()->cancel_actions_by_group( 'instawp-connect' );
}