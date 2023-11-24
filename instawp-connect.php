<?php
/**
 * @link              https://instawp.com/
 * @since             0.0.1
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       InstaWP Connect
 * Description:       Create 1-click staging, migration and manage your prod sites.
 * Version:           0.0.9.50
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

define( 'INSTAWP_PLUGIN_VERSION', '0.0.9.50' );
define( 'INSTAWP_RESTORE_INIT', 'init' );
define( 'INSTAWP_API_DOMAIN_PROD', 'https://app.instawp.io' );

define( 'INSTAWP_RESTORE_READY', 'ready' );
define( 'INSTAWP_RESTORE_COMPLETED', 'completed' );
define( 'INSTAWP_RESTORE_RUNNING', 'running' );
define( 'INSTAWP_RESTORE_ERROR', 'error' );
define( 'INSTAWP_RESTORE_WAIT', 'wait' );
define( 'INSTAWP_RESTORE_TIMEOUT', 180 );

defined( 'INSTAWP_PLUGIN_URL' ) || define( 'INSTAWP_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' );
defined( 'INSTAWP_PLUGIN_DIR' ) || define( 'INSTAWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'INSTAWP_DEFAULT_BACKUP_DIR' ) || define( 'INSTAWP_DEFAULT_BACKUP_DIR', 'instawpbackups' );
defined( 'INSTAWP_BACKUP_DIR' ) || define( 'INSTAWP_BACKUP_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR . DIRECTORY_SEPARATOR );

define( 'INSTAWP_CHUNK_SIZE', 1024 * 1024 );

define( 'INSTAWP_PLUGIN_SLUG', 'instawp-connect' );
define( 'INSTAWP_PLUGIN_NAME', plugin_basename( __FILE__ ) );
define( 'INSTAWP_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) . 'admin/' );
define( 'INSTAWP_PLUGIN_IMAGES_URL', INSTAWP_PLUGIN_URL . '/admin/partials/images/' );
//We set a long enough default execution time (10 min) to ensure that the backup process can be completed.
define( 'INSTAWP_MAX_EXECUTION_TIME', 1800 );
define( 'INSTAWP_RESTORE_MAX_EXECUTION_TIME', 1800 );
define( 'INSTAWP_MEMORY_LIMIT', '256M' );
define( 'INSTAWP_RESTORE_MEMORY_LIMIT', '256M' );
define( 'INSTAWP_MIGRATE_SIZE', '2048' );
//If the server uses fastcgi then default execution time should be set to 2 min for more efficient.
define( 'INSTAWP_MAX_EXECUTION_TIME_FCGI', 180 );
//Default number of reserved backups
define( 'INSTAWP_MAX_BACKUP_COUNT', 7 );
define( 'INSTAWP_DEFAULT_BACKUP_COUNT', 3 );
define( 'INSTAWP_DEFAULT_COMPRESS_TYPE', 'zip' );
//Max zip file size.
define( 'INSTAWP_DEFAULT_MAX_FILE_SIZE', 20 );
//Instruct PclZip to use all the time temporary files to create the zip archive or not.The default value is 1.
define( 'INSTAWP_DEFAULT_USE_TEMP', 1 );
//Instruct PclZip to use temporary files for files with size greater than.The default value is 16M.
define( 'INSTAWP_DEFAULT_USE_TEMP_SIZE', 16 );
//Exclude the files which is larger than.The default value is 0 means unlimited.
define( 'INSTAWP_DEFAULT_EXCLUDE_FILE_SIZE', 0 );
//Add a file in an archive without compressing the file.The default value is 200.
define( 'INSTAWP_DEFAULT_NO_COMPRESS', true );
//Backup save folder under WP_CONTENT_DIR

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

define( 'INSTAWP_SUCCESS', 'success' );
define( 'INSTAWP_FAILED', 'failed' );
define( 'INSTAWP_UPLOAD_TO_CLOUD', true );
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
	wp_clear_scheduled_hook( 'instawp_handle_heartbeat' );
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

	instawp_create_db_tables();
	// instawp_alter_db_tables();
}

add_filter( 'got_rewrite', '__return_true' );

run_instawp();


add_action( 'wp_head', function () {
	if ( isset( $_GET['debug'] ) && 'yes' == sanitize_text_field( $_GET['debug'] ) ) {

//		$migrate_key = InstaWP_Tools::get_random_string( 40 );
//		$api_signature      = "4fd7be36694620a8b58760a5651b00f4af627c738dbcc60f4f55118561597311aaacd23faf2a37b32aaddc7d476f2bd2d7b510288f11cccae7cf45cf7c914044";
//		$migrate_settings_str = '{"type":"full","excluded_tables":["wp_actionscheduler_logs","wp_instawp_staging_sites","wp_instawp_events","wp_instawp_sync_history","wp_instawp_event_sites","wp_instawp_event_sync_logs"],"excluded_tables_rows":{"wp_options":["option_name:instawp_api_options","option_name:instawp_connect_id_options","option_name:instawp_sync_parent_connect_data","option_name:instawp_migration_details","option_name:instawp_api_key_config_completed","option_name:instawp_is_event_syncing","option_name:_transient_instawp_staging_sites","option_name:_transient_timeout_instawp_staging_sites"]}}';
//		$migrate_settings     = json_decode( $migrate_settings_str, true );
//		$pre_check_response   = instawp()->tools::get_pull_pre_check_response( $migrate_key, $migrate_settings );


//		echo "<pre>";
//		print_r( InstaWP_Setting::get_option( 'instawp_api_options' ) );
//		echo "</pre>";

//
//		$json_path = '/home/1136091.cloudwaysapps.com/fhpzsvmjkw/public_html/wp-content/instawpbackups/8c53270481b4620d013911a84eebada0f4188d70.json';
//		if ( file_exists( $json_path ) ) {
//			$jsonString = file_get_contents( $json_path );
//			$jsonData   = json_decode( $jsonString, true );
//
//			if ( $jsonData !== null ) {
//				extract( $jsonData );
//			} else {
//				header( 'x-iwp-status: false' );
//				header( 'x-iwp-message: Error: Unable to parse JSON data.' );
//				die();
//			}
//		} else {
//			header( 'x-iwp-status: false' );
//			header( 'x-iwp-message: Error: JSON file not found.' );
//			die();
//		}
//
//		$mysqli = new mysqli( $db_host, $db_username, $db_password, $db_name );
//
////		$instawp_api_options = serialize( $instawp_api_options );
//		$instawp_api_options = 'a:4:{s:7:"api_key";s:40:"pStZUVgJyVUmk1GFm9B92iq2pcLO6j9PpDRBQGib";s:7:"api_url";s:24:"https://stage.instawp.io";s:8:"response";a:3:{s:6:"status";i:1;s:7:"message";s:12:"Key verified";s:4:"data";a:3:{s:6:"status";b:1;s:4:"name";s:14:"Jaed Mosharraf";s:10:"permisions";a:1:{i:0;s:1:"*";}}}s:10:"connect_id";i:8005;}';
//
//		$ret = $mysqli->query( "UPDATE `{$table_prefix}options` SET `option_value` = '{$instawp_api_options}' WHERE `option_name` = 'instawp_api_options_2'" );
//
////		update_option( 'instawp_api_options_2', $instawp_api_options );
//
//		echo "<pre>";
//		print_r( [
//			$instawp_api_options,
//			$ret,
//		] );
//		echo "</pre>";


		die();
	}
}, 0 );