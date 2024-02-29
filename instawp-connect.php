<?php
/**
 * @link              https://instawp.com/
 * @since             0.0.1
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       InstaWP Connect
 * Description:       1-click WP Staging with Sync. Manage your Live sites.
 * Version:           0.1.0.18
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

define( 'INSTAWP_PLUGIN_VERSION', '0.1.0.18' );
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

define( 'INSTAWP_CHUNK_SIZE', 1024 * 1024 );

define( 'INSTAWP_PLUGIN_SLUG', 'instawp-connect' );
define( 'INSTAWP_PLUGIN_NAME', plugin_basename( __FILE__ ) );

//Log save folder under WP_CONTENT_DIR
define( 'INSTAWP_DEFAULT_LOG_DIR', 'instawpbackups' . DIRECTORY_SEPARATOR . 'instawp_log' );
define( 'INSTAWP_DEFAULT_ROLLBACK_DIR', 'instawp-old-files' );
//
define( 'INSTAWP_DEFAULT_ADMIN_BAR', true );
define( 'INSTAWP_DEFAULT_TAB_MENU', true );
define( 'INSTAWP_DEFAULT_DOMAIN_INCLUDE', false );
//
define( 'INSTAWP_DEFAULT_ESTIMATE_BACKUP', false );
//Specify the folder and database to be backed up
define( 'INSTAWP_DEFAULT_SUBPACKAGE_PLUGIN_UPLOAD', false );

//define schedule hooks
define( 'INSTAWP_MAIN_SCHEDULE_EVENT', 'instawp_main_schedule_event' );
define( 'INSTAWP_RESUME_SCHEDULE_EVENT', 'instawp_resume_schedule_event' );
define( 'INSTAWP_CLEAN_BACKING_UP_DATA_EVENT', 'instawp_clean_backing_up_data_event' );
define( 'INSTAWP_TASK_MONITOR_EVENT', 'instawp_task_monitor_event' );
define( 'INSTAWP_CLEAN_BACKUP_RECORD_EVENT', 'instawp_clean_backup_record_event' );
//backup resume retry times
define( 'INSTAWP_RESUME_RETRY_TIMES', 6 );
define( 'INSTAWP_RESUME_INTERVAL', 60 );

define( 'INSTAWP_REMOTE_CONNECT_RETRY_TIMES', 3 );
define( 'INSTAWP_REMOTE_CONNECT_RETRY_INTERVAL', '3' );

define( 'INSTAWP_PACK_SIZE', 1 << 20 );

define( 'INSTAWP_DB_TABLE_STAGING_SITES', $wpdb->prefix . 'instawp_staging_sites' );
define( 'INSTAWP_DB_TABLE_EVENTS', $wpdb->prefix . 'instawp_events' );
define( 'INSTAWP_DB_TABLE_SYNC_HISTORY', $wpdb->prefix . 'instawp_sync_history' );
define( 'INSTAWP_DB_TABLE_EVENT_SITES', $wpdb->prefix . 'instawp_event_sites' );
define( 'INSTAWP_DB_TABLE_EVENT_SYNC_LOGS', $wpdb->prefix . 'instawp_event_sync_logs' );

define( 'INSTAWP_API_URL', '/api/v1' );
define( 'INSTAWP_API_2_URL', '/api/v2' );
define( 'INSTAWP_EVENTS_PER_PAGE', 20 );
define( 'INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED', 50 );
define( 'INSTAWP_EVENTS_SYNC_PER_PAGE', 5 );
define( 'INSTAWP_STAGING_SITES_PER_PAGE', 10 );
@ini_set( 'memory_limit', '2048M' );


/**
 * @global instaWP $instawp_plugin
 */
global $instawp_plugin;

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
//when active plugin redirect plugin page.


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

	// Create database tables if they are not created already.
	instawp_create_db_tables();
	instawp_alter_db_tables();
}

add_filter( 'got_rewrite', '__return_true' );

run_instawp();


add_action( 'wp_head', function () {
	if ( isset( $_GET['debug'] ) && 'yes' == sanitize_text_field( $_GET['debug'] ) ) {

		$wp_root    = '/mnt/customers/customers-3g3m02/b2c08e94-0ded-418f-a086-775f047ec96a/wp-content';
		$filePath   = $wp_root . '/wp-config.php';
		$target_url = "https://twnpxqwt.elementor.cloud/wp-content/plugins/instawp-connect/dest.php";

		if ( strpos( $target_url, 'elementor.cloud' ) !== false ) {

			$line_number  = false;
			$config_lines = file( $filePath );

			$new_lines = array(
				'if ( isset( $_SERVER["HTTP_X_FORWARDED_PROTO"] ) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https" ) { $_SERVER["HTTPS"] = "on"; }',
			);

			foreach ( $config_lines as $key => $line ) {
				if ( strpos( $line, "DB_COLLATE" ) !== false ) {
					$line_number = $key;
					break;
				}
			}

			if ( $line_number !== false ) {
				array_splice( $config_lines, $line_number + 1, 0, $new_lines );
			}

			file_put_contents( $filePath, implode( "", $config_lines ) );
		}

		die();

//		function get_wp_root_directory( $find_with_files = 'wp-load.php', $find_with_dir = '' ) {
//
//			$is_find_root_dir = true;
//			$root_path        = '';
//
//			if ( ! empty( $find_with_files ) ) {
//				$level            = 0;
//				$root_path_dir    = __DIR__;
//				$root_path        = __DIR__;
//				$is_find_root_dir = true;
//
//				while ( ! file_exists( $root_path . DIRECTORY_SEPARATOR . $find_with_files ) ) {
//
//					++ $level;
//					$root_path = dirname( $root_path_dir, $level );
//
//					if ( $level > 10 ) {
//						$is_find_root_dir = false;
//						break;
//					}
//				}
//			}
//
//			if ( ! empty( $find_with_dir ) ) {
//				$level            = 0;
//				$root_path_dir    = __DIR__;
//				$root_path        = __DIR__;
//				$is_find_root_dir = true;
//				while ( ! is_dir( $root_path . DIRECTORY_SEPARATOR . $find_with_dir ) ) {
//
//					++ $level;
//					$root_path = dirname( $root_path_dir, $level );
//
//					if ( $level > 10 ) {
//						$is_find_root_dir = false;
//						break;
//					}
//				}
//			}
//
//			return array(
//				'status'    => $is_find_root_dir,
//				'root_path' => $root_path,
//			);
//		}
//
//		$root_dir_data = get_wp_root_directory();
//		$root_dir_find = isset( $root_dir_data['status'] ) ? $root_dir_data['status'] : false;
//		$root_dir_path = isset( $root_dir_data['root_path'] ) ? $root_dir_data['root_path'] : '';
//
//		if ( ! $root_dir_find ) {
//			$root_dir_data = get_wp_root_directory( '', 'flywheel-config' );
//			$root_dir_find = isset( $root_dir_data['status'] ) ? $root_dir_data['status'] : false;
//			$root_dir_path = isset( $root_dir_data['root_path'] ) ? $root_dir_data['root_path'] : '';
//		}
//
//		echo "<pre>"; print_r( $root_dir_path ); echo "</pre>";
//
//		die();

		if ( ! function_exists( 'is_valid_file' ) ) {
			function is_valid_file( $filepath ): bool {
				$filename = basename( $filepath );

				return is_file( $filepath ) && is_readable( $filepath ) && ( preg_match( '/^[a-zA-Z0-9_.@\s-]+$/', $filename ) === 1 );
			}
		}

		$wp_root      = '/mnt/customers/customers-3g3m02/b2c08e94-0ded-418f-a086-775f047ec96a/wp-content';
		$skip_folders = array( 'wp-content', 'wp-admin', 'wp-includes' );
//		$skip_folders     = [];
		$filter_directory = function ( SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator ) use ( $skip_folders ) {

			$relative_path = ! empty( $iterator->getSubPath() ) ? $iterator->getSubPath() . '/' . $file->getBasename() : $file->getBasename();

			if ( in_array( $relative_path, $skip_folders ) ) {
				return false;
			}

			return ! in_array( $iterator->getSubPath(), $skip_folders );
		};
		$directory        = new RecursiveDirectoryIterator( $wp_root, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );
		$iterator         = new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( $directory, $filter_directory ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
		$limitedIterator  = array();

		try {
			$limitedIterator = new LimitIterator( $iterator, 0, 20 );
		} catch ( Exception $e ) {
			echo "<pre>";
			print_r( $e->getMessage() );
			echo "</pre>";
		}

		foreach ( $limitedIterator as $file ) {

			$filepath = $file->getPathname();

			echo "<pre>";
			print_r( [
				'$filepath' => $filepath,
				'is_valid'  => is_valid_file( $filepath ),
			] );
			echo "</pre>";
		}

		die();
	}
}, 0 );


