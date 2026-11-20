<?php global $migrate_id;

/**
 * @link              https://instawp.com/
 * @since             0.0.1
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       InstaWP Connect
 * Description:       1-click WordPress plugin for Staging, Migrations, Management, Sync and Companion plugin for InstaWP.
 * Version:           0.1.4.6
 * Author:            InstaWP Team
 * Author URI:        https://instawp.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/copyleft/gpl.html
 * Text Domain:       instawp-connect
 * Domain Path:       /languages
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

global $wpdb;

defined( 'INSTAWP_PLUGIN_VERSION' ) || define( 'INSTAWP_PLUGIN_VERSION', '0.1.4.6' );
defined( 'INSTAWP_API_DOMAIN_PROD' ) || define( 'INSTAWP_API_DOMAIN_PROD', 'https://app.instawp.io' );

defined( 'INSTAWP_PLUGIN_URL' ) || define( 'INSTAWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
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

// These are read on every request, visitors included, and are stored non-autoloaded. Load them in one
// query instead of one each. Options that do not exist (instawp_is_staging and instawp_parent_is_on_local
// on most sites) are remembered as missing by the same query, so they cost no extra query either.
// WordPress 6.4+; older versions read them one by one as before.
if ( function_exists( 'wp_prime_option_caches' ) ) {
	try {
		wp_prime_option_caches( array( 'instawp_api_options', 'instawp_activity_log', 'instawp_is_staging', 'instawp_parent_is_on_local' ) );
	} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// Only an optimisation: on failure each option is read on its own as before.
	}
}

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-instawp.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';

function instawp_plugin_activate() {
	InstaWP_Tools::instawp_reset_permalink();

	// set default user for sync settings if user empty
	$default_user = Option::get_option( 'instawp_default_user' );
	if ( empty( $default_user ) ) {
		Option::update_option( 'instawp_default_user', get_current_user_id() );
	}

	$instawp_sync_tab_roles = Option::get_option( 'instawp_sync_tab_roles' );
	if ( empty( $instawp_sync_tab_roles ) ) {
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;
		Option::update_option( 'instawp_sync_tab_roles', $roles );
	}

	$connect_id = instawp_get_connect_id();
	if ( ! empty( $connect_id ) ) {
		$response = Curl::do_curl( "connects/{$connect_id}/restore", array( 'url' => Helper::wp_site_url( '', true ) ) );
		if ( empty( $response['success'] ) ) {
			Option::delete_option( 'instawp_api_options' );
		}
	}
}

/*Deactivate Hook Handle*/
function instawp_plugin_deactivate() {
	// Read by the activity log: events logged later in this request (the plugin_deactivated event fires on
	// deactivated_plugin, after this hook) are sent once at the end of the request, since nothing will run
	// a queued send once the plugin is inactive.
	defined( 'INSTAWP_DEACTIVATING' ) || define( 'INSTAWP_DEACTIVATING', true );

	// Nothing in this hook may stop the plugin from deactivating: WordPress marks it inactive only after the
	// hook returns, so an uncaught error here would leave it active.
	try {
		InstaWP_Tools::instawp_reset_permalink();
		Option::delete_option( 'instawp_last_heartbeat_sent' );
		Option::delete_option( 'instawp_migration_details' );
	} catch ( \Throwable $e ) {
		instawp_log_throwable( 'Deactivation reset', $e );
	}

	// Stop the plugin's background jobs: once it is inactive nothing handles them, and Action Scheduler
	// (if another plugin keeps it running) would record each one as failed. Settings and data are kept;
	// activating again re-creates the jobs. Not reached on a plugin update: WordPress deactivates
	// silently during an update, which skips this hook.
	// A failure here must never stop the plugin from deactivating.
	try {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), 'instawp-connect' );
		}
		wp_unschedule_hook( 'instawp_cdn_stock_daily_purge' );
		wp_unschedule_hook( 'instawp_cdn_purge_deferred' );
	} catch ( \Throwable $e ) {
		instawp_log_throwable( 'Deactivation job cleanup', $e );
	}

	// Remove any Database Manager copy now instead of leaving it until the plugin is activated again.
	try {
		do_action( 'instawp_clean_database_manager' );
	} catch ( \Throwable $e ) {
		instawp_log_throwable( 'Deactivation Database Manager cleanup', $e );
	}

	try {
		$connect_id = instawp_get_connect_id();
		if ( ! empty( $connect_id ) ) {
			Curl::do_curl( "connects/{$connect_id}/delete", array(), array(), 'DELETE' );
		}
	} catch ( \Throwable $e ) {
		instawp_log_throwable( 'Deactivation disconnect', $e );
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
