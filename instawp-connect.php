<?php
/**
 * @link              https://instawp.com/
 * @since             0.0.1
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       InstaWP Connect
 * Description:       1-click WP Staging with Sync. Manage your Live sites.
 * Version:           0.1.0.19
 * Author:            InstaWP Team
 * Author URI:        https://instawp.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/copyleft/gpl.html
 * Text Domain:       instawp-connect
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

global $wpdb;

define( 'INSTAWP_PLUGIN_VERSION', '0.1.0.19' );
define( 'INSTAWP_API_DOMAIN_PROD', 'https://app.instawp.io' );

$wp_plugin_url   = WP_PLUGIN_URL . '/' . plugin_basename( __DIR__ ) . '/';
$wp_site_url     = get_option( 'siteurl' );
$parsed_site_url = parse_url( $wp_site_url );

if ( isset( $parsed_site_url['scheme'] ) && strtolower( $parsed_site_url['scheme'] ) === 'http' ) {
	$is_protocol_https = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) || ( ! empty( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == 443 );

	if ( ! $is_protocol_https ) {
		$is_protocol_https = ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) === 'https' );
	}

	if ( $is_protocol_https ) {
		$wp_plugin_url = str_replace( 'http://', 'https://', $wp_plugin_url );
	}
}

defined( 'INSTAWP_PLUGIN_URL' ) || define( 'INSTAWP_PLUGIN_URL', $wp_plugin_url );
defined( 'INSTAWP_PLUGIN_DIR' ) || define( 'INSTAWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'INSTAWP_DEFAULT_BACKUP_DIR' ) || define( 'INSTAWP_DEFAULT_BACKUP_DIR', 'instawpbackups' );
defined( 'INSTAWP_BACKUP_DIR' ) || define( 'INSTAWP_BACKUP_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR . DIRECTORY_SEPARATOR );
defined( 'INSTAWP_DOCS_URL_PLUGIN' ) || define( 'INSTAWP_DOCS_URL_PLUGIN', esc_url( 'https://instawp.to/docs/plugin-errors' ) );
defined( 'INSTAWP_PLUGIN_SLUG' ) || define( 'INSTAWP_PLUGIN_SLUG', 'instawp-connect' );
defined( 'INSTAWP_PLUGIN_NAME' ) || define( 'INSTAWP_PLUGIN_NAME', plugin_basename( __FILE__ ) );
defined( 'INSTAWP_DB_TABLE_EVENTS' ) || define( 'INSTAWP_DB_TABLE_EVENTS', $wpdb->prefix . 'instawp_events' );
defined( 'INSTAWP_DB_TABLE_SYNC_HISTORY' ) || define( 'INSTAWP_DB_TABLE_SYNC_HISTORY', $wpdb->prefix . 'instawp_sync_history' );
defined( 'INSTAWP_DB_TABLE_EVENT_SITES' ) || define( 'INSTAWP_DB_TABLE_EVENT_SITES', $wpdb->prefix . 'instawp_event_sites' );
defined( 'INSTAWP_DB_TABLE_EVENT_SYNC_LOGS' ) || define( 'INSTAWP_DB_TABLE_EVENT_SYNC_LOGS', $wpdb->prefix . 'instawp_event_sync_logs' );
defined( 'INSTAWP_DB_TABLE_ACTIVITY_LOGS' ) || define( 'INSTAWP_DB_TABLE_ACTIVITY_LOGS', $wpdb->prefix . 'instawp_activity_logs' );
defined( 'INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED' ) || define( 'INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED', 50 );
defined( 'INSTAWP_EVENTS_SYNC_PER_PAGE' ) || define( 'INSTAWP_EVENTS_SYNC_PER_PAGE', 5 );
defined( 'INSTAWP_API_URL' ) || define( 'INSTAWP_API_URL', '/api/v1' );

/**
 * @global instaWP $instawp_plugin
 */
global $instawp_plugin;

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-instawp.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

function instawp_plugin_activate() {

	InstaWP_Tools::instawp_reset_permalink();
	do_action( 'instawp_prepare_large_files_list' );

	//set default user for sync settings if user empty
	$default_user = InstaWP_Setting::get_option( 'instawp_default_user' );
	if ( empty( $default_user ) ) {
		add_option( 'instawp_default_user', get_current_user_id() );
	}

	$instawp_sync_tab_roles = InstaWP_Setting::get_option( 'instawp_sync_tab_roles' );
	if ( empty( $instawp_sync_tab_roles ) ) {
		$user  = wp_get_current_user();
		$roles = ( array ) $user->roles;
		add_option( 'instawp_sync_tab_roles', $roles );
	}
}

/*Deactivate Hook Handle*/
function instawp_plugin_deactivate() {
	InstaWP_Tools::instawp_reset_permalink();
	delete_option( 'instawp_last_heartbeat_sent' );
}

register_activation_hook( __FILE__, 'instawp_plugin_activate' );
register_deactivation_hook( __FILE__, 'instawp_plugin_deactivate' );


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0
 */
if ( isset( $instawp_plugin ) && is_a( $instawp_plugin, 'instaWP' ) ) {
	return;
}

function run_instawp() {
	$instawp_plugin = new instaWP();

	$GLOBALS['instawp_plugin'] = $instawp_plugin;
	$GLOBALS['instawp']        = $instawp_plugin;
}

add_filter( 'got_rewrite', '__return_true' );

run_instawp();

add_action( 'wp_head', function () {
	if ( isset( $_GET['debug'] ) ) {

//		echo "<pre>";
//		var_dump( InstaWP_Tools::is_wp_root_available() );
//		echo "</pre>";

		global $wp_version;

		$migrate_settings = '{"type":"full","site_name":null,"excluded_tables":["iwpfda4_instawp_events","iwpfda4_instawp_sync_history","iwpfda4_instawp_event_sites","iwpfda4_instawp_event_sync_logs"],"excluded_tables_rows":{"iwpfda4_options":["option_name:instawp_api_options","option_name:instawp_connect_id_options","option_name:instawp_sync_parent_connect_data","option_name:instawp_migration_details","option_name:instawp_api_key_config_completed","option_name:instawp_is_event_syncing","option_name:_transient_instawp_staging_sites","option_name:_transient_timeout_instawp_staging_sites","option_name:instawp_is_staging"]},"excluded_paths":["index.html",".user.ini","wp-content\/cache","wp-content\/.htaccess"]}';
		$migrate_settings = json_decode( $migrate_settings, true );

		unset( $migrate_settings['site_name'] );

		$migrate_key        = InstaWP_Tools::get_random_string( 40 );
		$pre_check_response = InstaWP_Tools::get_pull_pre_check_response( $migrate_key, $migrate_settings );
		$serve_url          = InstaWP_Setting::get_args_option( 'serve_url', $pre_check_response );
		$api_signature      = InstaWP_Setting::get_args_option( 'api_signature', $pre_check_response );

		$migrate_args = array(
			'source_domain'       => site_url(),
			'source_connect_id'   => instawp_get_connect_id(),
			'php_version'         => PHP_VERSION,
			'wp_version'          => $wp_version,
			'plugin_version'      => INSTAWP_PLUGIN_VERSION,
			'file_size'           => InstaWP_Tools::get_total_sizes( 'files', $migrate_settings ),
			'db_size'             => InstaWP_Tools::get_total_sizes( 'db' ),
			'is_website_on_local' => false,
			'settings'            => $migrate_settings,
			'active_plugins'      => InstaWP_Setting::get_option( 'active_plugins', array() ),
			'migrate_key'         => $migrate_key,
			'serve_url'           => $serve_url,
			'api_signature'       => $api_signature,
		);

//		if ( ! empty( $site_name = InstaWP_Setting::get_args_option( 'site_name', $migrate_settings ) ) ) {
//			$site_name = strtolower( $site_name );
//			$site_name = preg_replace( '/[^a-z0-9-_]/', ' ', $site_name );
//			$site_name = preg_replace( '/\s+/', '-', $site_name );
//
//			$migrate_args['site_name'] = $site_name;
//		}

//		$migrate_args['site_name'] = 'php56wp55';

		echo "<pre>";
		print_r( $migrate_args );
		echo "</pre>";

		$migrate_response = InstaWP_Curl::do_curl( 'migrates-v3', $migrate_args );

		echo "<pre>";
		print_r( $migrate_response );
		echo "</pre>";

		die();
	}
}, 0 );