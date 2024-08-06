<?php
/**
 * @link              https://instawp.com/
 * @since             0.0.1
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       InstaWP Connect
 * Description:       1-click WordPress plugin for Staging, Migrations, Management, Sync and Companion plugin for InstaWP.
 * Version:           0.1.0.48
 * Author:            InstaWP Team
 * Author URI:        https://instawp.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/copyleft/gpl.html
 * Text Domain:       instawp-connect
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Option;

if ( ! defined( 'WPINC' ) ) {
	die;
}

global $wpdb;

defined( 'INSTAWP_PLUGIN_VERSION' ) || define( 'INSTAWP_PLUGIN_VERSION', '0.1.0.48' );
defined( 'INSTAWP_API_DOMAIN_PROD' ) || define( 'INSTAWP_API_DOMAIN_PROD', 'https://app.instawp.io' );

$wp_plugin_url   = WP_PLUGIN_URL . '/' . plugin_basename( __DIR__ ) . '/';
$wp_site_url     = get_option( 'siteurl' );
$parsed_site_url = wp_parse_url( $wp_site_url );

if ( isset( $parsed_site_url['scheme'] ) && strtolower( $parsed_site_url['scheme'] ) === 'http' ) {
	$is_protocol_https = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) || ( ! empty( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] === 443 );

	if ( ! $is_protocol_https ) {
		$is_protocol_https = ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) === 'https' );
	}

	if ( $is_protocol_https ) {
		$wp_plugin_url = str_replace( 'http://', 'https://', $wp_plugin_url );
	}
}

defined( 'INSTAWP_PLUGIN_URL' ) || define( 'INSTAWP_PLUGIN_URL', $wp_plugin_url );
defined( 'INSTAWP_PLUGIN_DIR' ) || define( 'INSTAWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'INSTAWP_PLUGIN_FILE' ) || define( 'INSTAWP_PLUGIN_FILE', plugin_basename( __FILE__ ) );
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
	$default_user = Option::get_option( 'instawp_default_user' );
	if ( empty( $default_user ) ) {
		add_option( 'instawp_default_user', get_current_user_id() );
	}

	$instawp_sync_tab_roles = Option::get_option( 'instawp_sync_tab_roles' );
	if ( empty( $instawp_sync_tab_roles ) ) {
		$user  = wp_get_current_user();
		$roles = ( array ) $user->roles;
		add_option( 'instawp_sync_tab_roles', $roles );
	}

	$connect_id = instawp_get_connect_id();
	if ( ! empty( $connect_id ) ) {
		$response = Curl::do_curl( "connects/{$connect_id}/restore", array( 'url' => site_url() ) );
		if ( empty( $response['success'] ) ) {
			delete_option( 'instawp_api_options' );
		}
	}
}

/*Deactivate Hook Handle*/
function instawp_plugin_deactivate() {
	InstaWP_Tools::instawp_reset_permalink();
	delete_option( 'instawp_last_heartbeat_sent' );

	$connect_id = instawp_get_connect_id();
	if ( ! empty( $connect_id ) ) {
		Curl::do_curl( "connects/{$connect_id}/delete", array(), array(), 'DELETE' );
	}
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


function instawp_get_folder_checksum( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	$hash = hash_init( 'md5' );

	foreach ( $files as $file ) {
		if ( $file->isFile() ) {
			$filePath     = $file->getPathname();
			$relativePath = str_replace( $dir, '', $filePath );
			$fileContent  = file_get_contents( $filePath );
			hash_update( $hash, $relativePath . $fileContent );
		}
	}

	return hash_final( $hash );
}

function instawp_delete_directory( string $dirPath ): void {
	if ( ! is_dir( $dirPath ) ) {
		throw new InvalidArgumentException( "$dirPath must be a directory" );
	}
	if ( substr( $dirPath, strlen( $dirPath ) - 1, 1 ) != '/' ) {
		$dirPath .= '/';
	}
	$files = glob( $dirPath . '*', GLOB_MARK );
	foreach ( $files as $file ) {
		if ( is_dir( $file ) ) {
			instawp_delete_directory( $file );
		} else {
			unlink( $file );
		}
	}
	rmdir( $dirPath );
}

function instawp_get_wp_plugin_checksum( $plugin_slug = '' ) {

	$api_url  = "https://api.wordpress.org/plugins/info/1.0/{$plugin_slug}.json";
	$response = wp_remote_get( $api_url );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$plugin_data   = json_decode( wp_remote_retrieve_body( $response ), true );
	$download_link = $plugin_data['download_link'] ?? '';

	if ( empty( $download_link ) ) {
		return false;
	}

	$temp_dir  = sys_get_temp_dir();
	$temp_file = $temp_dir . $plugin_slug . '-' . uniqid() . '.zip';

	$ch = curl_init( $download_link );
	$fp = fopen( $temp_file, 'wb' );
	curl_setopt( $ch, CURLOPT_FILE, $fp );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 50 );

	if ( curl_exec( $ch ) === false ) {
		curl_close( $ch );
		fclose( $fp );
		@unlink( $temp_file );

		return false;
	}

	$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	fclose( $fp );

	if ( $http_code !== 200 ) {
		@unlink( $temp_file );

		return false;
	}

	if ( ! file_exists( $temp_file ) ) {
		return false;
	}

	$zip = new ZipArchive;
	$res = $zip->open( $temp_file );

	if ( $res === false ) {
		return false;
	}

	$zip->extractTo( dirname( $temp_file ) );
	$zip->close();

	@unlink( $temp_file );

	$plugin_folder   = $temp_dir . '/' . $plugin_slug;
	$plugin_checksum = instawp_get_folder_checksum( $plugin_folder );

	instawp_delete_directory( $plugin_folder );

	return $plugin_checksum;
}


add_action( 'wp_head', function () {
	if ( isset( $_GET['debug'] ) ) {

//		if ( ! function_exists( 'get_plugins' ) ) {
//			require_once ABSPATH . 'wp-admin/includes/plugin.php';
//		}
//
//		$all_plugins = get_plugins();
//		$all_plugins = array_keys( $all_plugins );
//		$all_plugins = array_map( function ( $plugin ) {
//			$plugin_arr = explode( '/', $plugin );
//
//			return $plugin_arr[0] ?? '';
//		}, $all_plugins );
//
//		$plugins_cs = [];
//
//		foreach ( $all_plugins as $plugin_slug ) {
//			$plugins_cs[ $plugin_slug ] = array(
//				'wp_repo' => instawp_get_wp_plugin_checksum( $plugin_slug ),
//			);
//		}

		$plugin_slug      = 'classic-editor';
		$plugin_dir_local = WP_PLUGIN_DIR . '/' . $plugin_slug;

		echo "<pre>";
		print_r( [
			instawp_get_wp_plugin_checksum( $plugin_slug ),
			instawp_get_folder_checksum( $plugin_dir_local ),
		] );
		echo "</pre>";

		die();
	}
}, 0 );


