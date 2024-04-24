<?php
/**
 * @link              https://instawp.com/
 * @since             0.0.1
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       InstaWP Connect
 * Description:       1-click WordPress plugin for Staging, Migrations, Management, Sync and Companion plugin for InstaWP.
 * Version:           0.1.0.30
 * Author:            InstaWP Team
 * Author URI:        https://instawp.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/copyleft/gpl.html
 * Text Domain:       instawp-connect
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
use InstaWP\Connect\Helpers\Option;

if ( ! defined( 'WPINC' ) ) {
	die;
}

global $wpdb;

defined( 'INSTAWP_PLUGIN_VERSION' ) || define( 'INSTAWP_PLUGIN_VERSION', '0.1.0.30' );
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

add_action( 'wp_head', function() {
	//update_option( 'instawp_test_data', 'a:31:{i:0;a:6:{s:2:"id";s:6:"zftitb";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:2:{i:0;s:6:"lhumne";i:1;s:6:"xpuaab";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"dydcfy";}}s:5:"label";s:4:"Hero";}i:1;a:6:{s:2:"id";s:6:"lfjhee";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:1:{i:0;s:6:"mzsxyi";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"bjmnuz";}}s:5:"label";s:8:"Services";}i:2;a:6:{s:2:"id";s:6:"szhdxp";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:2:{i:0;s:6:"mvdmsq";i:1;s:6:"lvuepi";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"dydcfy";}}s:5:"label";s:3:"CTA";}i:3;a:6:{s:2:"id";s:6:"mzsxyi";s:4:"name";s:9:"container";s:6:"parent";s:6:"lfjhee";s:8:"children";a:3:{i:0;s:6:"ctsqfd";i:1;s:6:"irauck";i:2;s:6:"pjtuds";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"qwimjr";}}s:5:"label";s:14:"Services Inner";}i:4;a:6:{s:2:"id";s:6:"lhumne";s:4:"name";s:9:"container";s:6:"parent";s:6:"zftitb";s:8:"children";a:2:{i:0;s:6:"zorsjt";i:1;s:6:"wqsvae";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"utwteb";}}s:5:"label";s:8:"Hero CTA";}i:5;a:6:{s:2:"id";s:6:"xpuaab";s:4:"name";s:9:"container";s:6:"parent";s:6:"zftitb";s:8:"children";a:1:{i:0;s:6:"hvdlhv";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"qxdtvo";}}s:5:"label";s:10:"Hero Media";}i:6;a:6:{s:2:"id";s:6:"zorsjt";s:4:"name";s:7:"heading";s:6:"parent";s:6:"lhumne";s:8:"children";a:0:{}s:8:"settings";a:3:{s:3:"tag";s:2:"h1";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"tgncjl";}s:4:"text";s:12:"{post_title}";}s:5:"label";s:0:"";}i:7;a:6:{s:2:"id";s:6:"wqsvae";s:4:"name";s:6:"button";s:6:"parent";s:6:"lhumne";s:8:"children";a:0:{}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xfazrf";}s:4:"link";a:3:{s:6:"postId";s:2:"29";s:4:"type";s:4:"meta";s:14:"useDynamicData";s:22:"{acf_hero_button_link}";}s:4:"text";s:23:"{acf_hero_button_label}";s:4:"icon";a:4:{s:7:"library";s:3:"svg";s:3:"svg";a:3:{s:2:"id";i:282;s:8:"filename";s:15:"arrow-right.svg";s:3:"url";s:68:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/arrow-right.svg";}s:6:"height";s:3:"1em";s:5:"width";s:3:"1em";}}s:5:"label";s:0:"";}i:8;a:6:{s:2:"id";s:6:"hvdlhv";s:4:"name";s:5:"image";s:6:"parent";s:6:"xpuaab";s:8:"children";a:0:{}s:8:"settings";a:5:{s:3:"tag";s:6:"figure";s:7:"loading";s:5:"eager";s:7:"caption";s:4:"none";s:5:"image";a:5:{s:14:"useDynamicData";s:16:"{featured_image}";s:4:"size";s:5:"large";s:8:"filename";s:0:"";s:2:"id";i:165;s:3:"url";s:78:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/after-cleaning_-highl.jpg";}s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"jvmenz";}}s:11:"themeStyles";a:0:{}}i:9;a:6:{s:2:"id";s:6:"ctsqfd";s:4:"name";s:7:"heading";s:6:"parent";s:6:"mzsxyi";s:8:"children";a:0:{}s:8:"settings";a:3:{s:4:"text";s:13:"Onze diensten";s:3:"tag";s:2:"h2";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"mfdjzt";}}s:5:"label";s:14:"Services Title";}i:10;a:6:{s:2:"id";s:6:"mvdmsq";s:4:"name";s:9:"container";s:6:"parent";s:6:"szhdxp";s:8:"children";a:2:{i:0;s:6:"nwcwnz";i:1;s:6:"rpeciy";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"utwteb";i:1;s:6:"ftjrya";}}s:5:"label";s:8:"Hero CTA";}i:11;a:6:{s:2:"id";s:6:"lvuepi";s:4:"name";s:9:"container";s:6:"parent";s:6:"szhdxp";s:8:"children";a:1:{i:0;s:6:"enhewe";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"qxdtvo";i:1;s:6:"sjeyxn";}}s:5:"label";s:10:"Hero Media";}i:12;a:6:{s:2:"id";s:6:"rpeciy";s:4:"name";s:6:"button";s:6:"parent";s:6:"mvdmsq";s:8:"children";a:0:{}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xfazrf";}s:4:"link";a:3:{s:6:"postId";s:2:"29";s:4:"type";s:4:"meta";s:14:"useDynamicData";s:32:"{acf_call_to_action_button_link}";}s:4:"text";s:33:"{acf_call_to_action_button_label}";s:4:"icon";a:4:{s:7:"library";s:3:"svg";s:3:"svg";a:3:{s:2:"id";i:282;s:8:"filename";s:15:"arrow-right.svg";s:3:"url";s:68:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/arrow-right.svg";}s:6:"height";s:3:"1em";s:5:"width";s:3:"1em";}}s:5:"label";s:0:"";}i:13;a:6:{s:2:"id";s:6:"enhewe";s:4:"name";s:5:"image";s:6:"parent";s:6:"lvuepi";s:8:"children";a:0:{}s:8:"settings";a:5:{s:3:"tag";s:6:"figure";s:7:"loading";s:5:"eager";s:7:"caption";s:4:"none";s:5:"image";a:5:{s:14:"useDynamicData";s:35:"{acf_call_to_action_featured_image}";s:4:"size";s:4:"full";s:8:"filename";s:0:"";s:2:"id";i:82;s:3:"url";s:74:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/large-industrial-.jpg";}s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"jvmenz";}}s:11:"themeStyles";a:0:{}}i:14;a:6:{s:2:"id";s:6:"fznonb";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:1:{i:0;s:6:"xcynjl";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xhliom";}}s:5:"label";s:12:"Testimonials";}i:15;a:6:{s:2:"id";s:6:"xcynjl";s:4:"name";s:9:"container";s:6:"parent";s:6:"fznonb";s:8:"children";a:2:{i:0;s:6:"ptdoru";i:1;s:6:"sslscs";}s:8:"settings";a:2:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xlpcbo";}s:7:"_rowGap";s:16:"var(--space-2xl)";}s:5:"label";s:18:"Testimonials Inner";}i:16;a:6:{s:2:"id";s:6:"ptdoru";s:4:"name";s:7:"heading";s:6:"parent";s:6:"xcynjl";s:8:"children";a:0:{}s:8:"settings";a:3:{s:4:"text";s:32:"Waarom bedrijven voor ons kiezen";s:3:"tag";s:2:"h2";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"mfdjzt";}}s:5:"label";s:18:"Testimonials Title";}i:17;a:6:{s:2:"id";s:6:"sslscs";s:4:"name";s:5:"block";s:6:"parent";s:6:"xcynjl";s:8:"children";a:1:{i:0;s:6:"cfjyjh";}s:8:"settings";a:2:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"nzlulc";i:1;s:6:"azamgj";}s:3:"tag";s:2:"ul";}s:5:"label";s:17:"Testimonials List";}i:18;a:6:{s:2:"id";s:6:"braoau";s:4:"name";s:3:"div";s:6:"parent";s:6:"cfjyjh";s:8:"children";a:1:{i:0;s:6:"xhdphw";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"nxipnr";}}s:5:"label";s:20:"Testimonials Balloon";}i:19;a:6:{s:2:"id";s:6:"xhdphw";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"braoau";s:8:"children";a:0:{}s:8:"settings";a:2:{s:3:"tag";s:1:"p";s:4:"text";s:17:"{acf_review-text}";}s:5:"label";s:7:"Content";}i:20;a:6:{s:2:"id";s:6:"cfjyjh";s:4:"name";s:5:"block";s:6:"parent";s:6:"sslscs";s:8:"children";a:2:{i:0;s:6:"braoau";i:1;s:6:"adzrnh";}s:8:"settings";a:4:{s:3:"tag";s:2:"li";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"kwxjbb";}s:7:"hasLoop";b:1;s:5:"query";a:1:{s:9:"post_type";a:1:{i:0;s:6:"review";}}}s:5:"label";s:22:"Testimonials List Item";}i:21;a:6:{s:2:"id";s:6:"adzrnh";s:4:"name";s:3:"div";s:6:"parent";s:6:"cfjyjh";s:8:"children";a:2:{i:0;s:6:"iqbjyb";i:1;s:6:"cumbah";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"ijaboo";}}s:5:"label";s:20:"Testimonials Profile";}i:22;a:6:{s:2:"id";s:6:"iqbjyb";s:4:"name";s:5:"image";s:6:"parent";s:6:"adzrnh";s:8:"children";a:0:{}s:8:"settings";a:3:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"tdjzte";}s:7:"caption";s:4:"none";s:5:"image";a:2:{s:14:"useDynamicData";s:17:"{acf_review-foto}";s:4:"size";s:5:"large";}}s:5:"label";s:13:"Profile Image";}i:23;a:6:{s:2:"id";s:6:"cumbah";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"adzrnh";s:8:"children";a:0:{}s:8:"settings";a:2:{s:3:"tag";s:4:"span";s:4:"text";s:12:"{post_title}";}s:5:"label";s:12:"Profile Name";}i:24;a:6:{s:2:"id";s:6:"irauck";s:4:"name";s:5:"block";s:6:"parent";s:6:"mzsxyi";s:8:"children";a:1:{i:0;s:6:"iqdhsq";}s:8:"settings";a:2:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"vvvhdl";i:1;s:6:"azamgj";}s:3:"tag";s:2:"ul";}s:5:"label";s:13:"Services List";}i:25;a:6:{s:2:"id";s:6:"iqdhsq";s:4:"name";s:5:"block";s:6:"parent";s:6:"irauck";s:8:"children";a:3:{i:0;s:6:"uwsdmq";i:1;s:6:"wctacm";i:2;s:6:"fagpyg";}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"yidgei";i:1;s:6:"ocrgfr";}s:3:"tag";s:2:"li";s:7:"hasLoop";b:1;s:5:"query";a:5:{s:9:"post_type";a:1:{i:0;s:4:"page";}s:11:"post_parent";s:2:"31";s:7:"orderby";s:5:"title";s:5:"order";s:3:"asc";s:14:"posts_per_page";s:1:"3";}}s:5:"label";s:18:"Services List Item";}i:26;a:7:{s:2:"id";s:6:"uwsdmq";s:4:"name";s:5:"image";s:6:"parent";s:6:"iqdhsq";s:8:"children";a:0:{}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"dmlxsx";}s:3:"tag";s:6:"figure";s:7:"caption";s:4:"none";s:5:"image";a:2:{s:14:"useDynamicData";s:16:"{featured_image}";s:4:"size";s:5:"large";}}s:11:"themeStyles";a:0:{}s:5:"label";s:5:"Media";}i:27;a:6:{s:2:"id";s:6:"fagpyg";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"iqdhsq";s:8:"children";a:0:{}s:8:"settings";a:3:{s:4:"text";s:17:"{post_excerpt:20}";s:3:"tag";s:1:"p";s:9:"_flexGrow";s:1:"1";}s:5:"label";s:7:"Content";}i:28;a:6:{s:2:"id";s:6:"wctacm";s:4:"name";s:7:"heading";s:6:"parent";s:6:"iqdhsq";s:8:"children";a:0:{}s:8:"settings";a:3:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"wvtcce";}s:4:"text";s:12:"{post_title}";s:4:"link";a:2:{s:4:"type";s:4:"meta";s:14:"useDynamicData";s:10:"{post_url}";}}s:5:"label";s:5:"Title";}i:29;a:5:{s:2:"id";s:6:"nwcwnz";s:4:"name";s:12:"post-content";s:6:"parent";s:6:"mvdmsq";s:8:"children";a:0:{}s:8:"settings";a:0:{}}i:30;a:6:{s:2:"id";s:6:"pjtuds";s:4:"name";s:6:"button";s:6:"parent";s:6:"mzsxyi";s:8:"children";a:0:{}s:8:"settings";a:4:{s:4:"text";s:13:"Meer diensten";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xfazrf";}s:4:"link";a:2:{s:4:"type";s:8:"internal";s:6:"postId";s:2:"31";}s:4:"icon";a:4:{s:7:"library";s:3:"svg";s:3:"svg";a:3:{s:2:"id";i:282;s:8:"filename";s:15:"arrow-right.svg";s:3:"url";s:68:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/arrow-right.svg";}s:6:"height";s:3:"1em";s:5:"width";s:3:"1em";}}s:5:"label";s:20:"More Services button";}}');
	//$data = get_option( 'instawp_test_data');

	//update_post_meta( 400, '_bricks_page_content_2', 'a:31:{i:0;a:6:{s:2:"id";s:6:"zftitb";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:2:{i:0;s:6:"lhumne";i:1;s:6:"xpuaab";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"dydcfy";}}s:5:"label";s:4:"Hero";}i:1;a:6:{s:2:"id";s:6:"lfjhee";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:1:{i:0;s:6:"mzsxyi";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"bjmnuz";}}s:5:"label";s:8:"Services";}i:2;a:6:{s:2:"id";s:6:"szhdxp";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:2:{i:0;s:6:"mvdmsq";i:1;s:6:"lvuepi";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"dydcfy";}}s:5:"label";s:3:"CTA";}i:3;a:6:{s:2:"id";s:6:"mzsxyi";s:4:"name";s:9:"container";s:6:"parent";s:6:"lfjhee";s:8:"children";a:3:{i:0;s:6:"ctsqfd";i:1;s:6:"irauck";i:2;s:6:"pjtuds";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"qwimjr";}}s:5:"label";s:14:"Services Inner";}i:4;a:6:{s:2:"id";s:6:"lhumne";s:4:"name";s:9:"container";s:6:"parent";s:6:"zftitb";s:8:"children";a:2:{i:0;s:6:"zorsjt";i:1;s:6:"wqsvae";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"utwteb";}}s:5:"label";s:8:"Hero CTA";}i:5;a:6:{s:2:"id";s:6:"xpuaab";s:4:"name";s:9:"container";s:6:"parent";s:6:"zftitb";s:8:"children";a:1:{i:0;s:6:"hvdlhv";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"qxdtvo";}}s:5:"label";s:10:"Hero Media";}i:6;a:6:{s:2:"id";s:6:"zorsjt";s:4:"name";s:7:"heading";s:6:"parent";s:6:"lhumne";s:8:"children";a:0:{}s:8:"settings";a:3:{s:3:"tag";s:2:"h1";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"tgncjl";}s:4:"text";s:12:"{post_title}";}s:5:"label";s:0:"";}i:7;a:6:{s:2:"id";s:6:"wqsvae";s:4:"name";s:6:"button";s:6:"parent";s:6:"lhumne";s:8:"children";a:0:{}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xfazrf";}s:4:"link";a:3:{s:6:"postId";s:2:"29";s:4:"type";s:4:"meta";s:14:"useDynamicData";s:22:"{acf_hero_button_link}";}s:4:"text";s:23:"{acf_hero_button_label}";s:4:"icon";a:4:{s:7:"library";s:3:"svg";s:3:"svg";a:3:{s:2:"id";i:282;s:8:"filename";s:15:"arrow-right.svg";s:3:"url";s:68:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/arrow-right.svg";}s:6:"height";s:3:"1em";s:5:"width";s:3:"1em";}}s:5:"label";s:0:"";}i:8;a:6:{s:2:"id";s:6:"hvdlhv";s:4:"name";s:5:"image";s:6:"parent";s:6:"xpuaab";s:8:"children";a:0:{}s:8:"settings";a:5:{s:3:"tag";s:6:"figure";s:7:"loading";s:5:"eager";s:7:"caption";s:4:"none";s:5:"image";a:5:{s:14:"useDynamicData";s:16:"{featured_image}";s:4:"size";s:5:"large";s:8:"filename";s:0:"";s:2:"id";i:165;s:3:"url";s:78:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/after-cleaning_-highl.jpg";}s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"jvmenz";}}s:11:"themeStyles";a:0:{}}i:9;a:6:{s:2:"id";s:6:"ctsqfd";s:4:"name";s:7:"heading";s:6:"parent";s:6:"mzsxyi";s:8:"children";a:0:{}s:8:"settings";a:3:{s:4:"text";s:13:"Onze diensten";s:3:"tag";s:2:"h2";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"mfdjzt";}}s:5:"label";s:14:"Services Title";}i:10;a:6:{s:2:"id";s:6:"mvdmsq";s:4:"name";s:9:"container";s:6:"parent";s:6:"szhdxp";s:8:"children";a:2:{i:0;s:6:"nwcwnz";i:1;s:6:"rpeciy";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"utwteb";i:1;s:6:"ftjrya";}}s:5:"label";s:8:"Hero CTA";}i:11;a:6:{s:2:"id";s:6:"lvuepi";s:4:"name";s:9:"container";s:6:"parent";s:6:"szhdxp";s:8:"children";a:1:{i:0;s:6:"enhewe";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"qxdtvo";i:1;s:6:"sjeyxn";}}s:5:"label";s:10:"Hero Media";}i:12;a:6:{s:2:"id";s:6:"rpeciy";s:4:"name";s:6:"button";s:6:"parent";s:6:"mvdmsq";s:8:"children";a:0:{}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xfazrf";}s:4:"link";a:3:{s:6:"postId";s:2:"29";s:4:"type";s:4:"meta";s:14:"useDynamicData";s:32:"{acf_call_to_action_button_link}";}s:4:"text";s:33:"{acf_call_to_action_button_label}";s:4:"icon";a:4:{s:7:"library";s:3:"svg";s:3:"svg";a:3:{s:2:"id";i:282;s:8:"filename";s:15:"arrow-right.svg";s:3:"url";s:68:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/arrow-right.svg";}s:6:"height";s:3:"1em";s:5:"width";s:3:"1em";}}s:5:"label";s:0:"";}i:13;a:6:{s:2:"id";s:6:"enhewe";s:4:"name";s:5:"image";s:6:"parent";s:6:"lvuepi";s:8:"children";a:0:{}s:8:"settings";a:5:{s:3:"tag";s:6:"figure";s:7:"loading";s:5:"eager";s:7:"caption";s:4:"none";s:5:"image";a:5:{s:14:"useDynamicData";s:35:"{acf_call_to_action_featured_image}";s:4:"size";s:4:"full";s:8:"filename";s:0:"";s:2:"id";i:82;s:3:"url";s:74:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/large-industrial-.jpg";}s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"jvmenz";}}s:11:"themeStyles";a:0:{}}i:14;a:6:{s:2:"id";s:6:"fznonb";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:1:{i:0;s:6:"xcynjl";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xhliom";}}s:5:"label";s:12:"Testimonials";}i:15;a:6:{s:2:"id";s:6:"xcynjl";s:4:"name";s:9:"container";s:6:"parent";s:6:"fznonb";s:8:"children";a:2:{i:0;s:6:"ptdoru";i:1;s:6:"sslscs";}s:8:"settings";a:2:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xlpcbo";}s:7:"_rowGap";s:16:"var(--space-2xl)";}s:5:"label";s:18:"Testimonials Inner";}i:16;a:6:{s:2:"id";s:6:"ptdoru";s:4:"name";s:7:"heading";s:6:"parent";s:6:"xcynjl";s:8:"children";a:0:{}s:8:"settings";a:3:{s:4:"text";s:32:"Waarom bedrijven voor ons kiezen";s:3:"tag";s:2:"h2";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"mfdjzt";}}s:5:"label";s:18:"Testimonials Title";}i:17;a:6:{s:2:"id";s:6:"sslscs";s:4:"name";s:5:"block";s:6:"parent";s:6:"xcynjl";s:8:"children";a:1:{i:0;s:6:"cfjyjh";}s:8:"settings";a:2:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"nzlulc";i:1;s:6:"azamgj";}s:3:"tag";s:2:"ul";}s:5:"label";s:17:"Testimonials List";}i:18;a:6:{s:2:"id";s:6:"braoau";s:4:"name";s:3:"div";s:6:"parent";s:6:"cfjyjh";s:8:"children";a:1:{i:0;s:6:"xhdphw";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"nxipnr";}}s:5:"label";s:20:"Testimonials Balloon";}i:19;a:6:{s:2:"id";s:6:"xhdphw";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"braoau";s:8:"children";a:0:{}s:8:"settings";a:2:{s:3:"tag";s:1:"p";s:4:"text";s:17:"{acf_review-text}";}s:5:"label";s:7:"Content";}i:20;a:6:{s:2:"id";s:6:"cfjyjh";s:4:"name";s:5:"block";s:6:"parent";s:6:"sslscs";s:8:"children";a:2:{i:0;s:6:"braoau";i:1;s:6:"adzrnh";}s:8:"settings";a:4:{s:3:"tag";s:2:"li";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"kwxjbb";}s:7:"hasLoop";b:1;s:5:"query";a:1:{s:9:"post_type";a:1:{i:0;s:6:"review";}}}s:5:"label";s:22:"Testimonials List Item";}i:21;a:6:{s:2:"id";s:6:"adzrnh";s:4:"name";s:3:"div";s:6:"parent";s:6:"cfjyjh";s:8:"children";a:2:{i:0;s:6:"iqbjyb";i:1;s:6:"cumbah";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"ijaboo";}}s:5:"label";s:20:"Testimonials Profile";}i:22;a:6:{s:2:"id";s:6:"iqbjyb";s:4:"name";s:5:"image";s:6:"parent";s:6:"adzrnh";s:8:"children";a:0:{}s:8:"settings";a:3:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"tdjzte";}s:7:"caption";s:4:"none";s:5:"image";a:2:{s:14:"useDynamicData";s:17:"{acf_review-foto}";s:4:"size";s:5:"large";}}s:5:"label";s:13:"Profile Image";}i:23;a:6:{s:2:"id";s:6:"cumbah";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"adzrnh";s:8:"children";a:0:{}s:8:"settings";a:2:{s:3:"tag";s:4:"span";s:4:"text";s:12:"{post_title}";}s:5:"label";s:12:"Profile Name";}i:24;a:6:{s:2:"id";s:6:"irauck";s:4:"name";s:5:"block";s:6:"parent";s:6:"mzsxyi";s:8:"children";a:1:{i:0;s:6:"iqdhsq";}s:8:"settings";a:2:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"vvvhdl";i:1;s:6:"azamgj";}s:3:"tag";s:2:"ul";}s:5:"label";s:13:"Services List";}i:25;a:6:{s:2:"id";s:6:"iqdhsq";s:4:"name";s:5:"block";s:6:"parent";s:6:"irauck";s:8:"children";a:3:{i:0;s:6:"uwsdmq";i:1;s:6:"wctacm";i:2;s:6:"fagpyg";}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"yidgei";i:1;s:6:"ocrgfr";}s:3:"tag";s:2:"li";s:7:"hasLoop";b:1;s:5:"query";a:5:{s:9:"post_type";a:1:{i:0;s:4:"page";}s:11:"post_parent";s:2:"31";s:7:"orderby";s:5:"title";s:5:"order";s:3:"asc";s:14:"posts_per_page";s:1:"3";}}s:5:"label";s:18:"Services List Item";}i:26;a:7:{s:2:"id";s:6:"uwsdmq";s:4:"name";s:5:"image";s:6:"parent";s:6:"iqdhsq";s:8:"children";a:0:{}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"dmlxsx";}s:3:"tag";s:6:"figure";s:7:"caption";s:4:"none";s:5:"image";a:2:{s:14:"useDynamicData";s:16:"{featured_image}";s:4:"size";s:5:"large";}}s:11:"themeStyles";a:0:{}s:5:"label";s:5:"Media";}i:27;a:6:{s:2:"id";s:6:"fagpyg";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"iqdhsq";s:8:"children";a:0:{}s:8:"settings";a:3:{s:4:"text";s:17:"{post_excerpt:20}";s:3:"tag";s:1:"p";s:9:"_flexGrow";s:1:"1";}s:5:"label";s:7:"Content";}i:28;a:6:{s:2:"id";s:6:"wctacm";s:4:"name";s:7:"heading";s:6:"parent";s:6:"iqdhsq";s:8:"children";a:0:{}s:8:"settings";a:3:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"wvtcce";}s:4:"text";s:12:"{post_title}";s:4:"link";a:2:{s:4:"type";s:4:"meta";s:14:"useDynamicData";s:10:"{post_url}";}}s:5:"label";s:5:"Title";}i:29;a:5:{s:2:"id";s:6:"nwcwnz";s:4:"name";s:12:"post-content";s:6:"parent";s:6:"mvdmsq";s:8:"children";a:0:{}s:8:"settings";a:0:{}}i:30;a:6:{s:2:"id";s:6:"pjtuds";s:4:"name";s:6:"button";s:6:"parent";s:6:"mzsxyi";s:8:"children";a:0:{}s:8:"settings";a:4:{s:4:"text";s:13:"Meer diensten";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"xfazrf";}s:4:"link";a:2:{s:4:"type";s:8:"internal";s:6:"postId";s:2:"31";}s:4:"icon";a:4:{s:7:"library";s:3:"svg";s:3:"svg";a:3:{s:2:"id";i:282;s:8:"filename";s:15:"arrow-right.svg";s:3:"url";s:68:"https://www.unicleanbv.nl/wp-content/uploads/2024/03/arrow-right.svg";}s:6:"height";s:3:"1em";s:5:"width";s:3:"1em";}}s:5:"label";s:20:"More Services button";}}');
	$data = get_post_meta( 400, '_bricks_page_content_2', true );
	$data = maybe_unserialize( $data );
	echo "<pre>"; print_r( $data ); echo "</pre>";
	die;
}, 0);