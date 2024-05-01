<?php
/**
 * @link              https://instawp.com/
 * @since             0.0.1
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       InstaWP Connect
 * Description:       1-click WordPress plugin for Staging, Migrations, Management, Sync and Companion plugin for InstaWP.
 * Version:           0.1.0.31
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

defined( 'INSTAWP_PLUGIN_VERSION' ) || define( 'INSTAWP_PLUGIN_VERSION', '0.1.0.31' );
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


add_action( 'wp_head', function () {
	if ( isset( $_GET['debug'] ) ) {

//		$data = "a:13:{i:0;a:6:{s:2:\"id\";s:6:\"xrjuvz\";s:4:\"name\";s:7:\"section\";s:6:\"parent\";i:0;s:8:\"children\";a:1:{i:0;s:6:\"llxgtk\";}s:8:\"settings\";a:2:{s:3:\"tag\";s:3:\"div\";s:17:\"_cssGlobalClasses\";a:1:{i:0;s:6:\"eircxj\";}}s:5:\"label\";s:10:\"Top Header\";}i:1;a:6:{s:2:\"id\";s:6:\"llxgtk\";s:4:\"name\";s:9:\"container\";s:6:\"parent\";s:6:\"xrjuvz\";s:8:\"children\";a:1:{i:0;s:6:\"eukwpf\";}s:8:\"settings\";a:1:{s:17:\"_cssGlobalClasses\";a:1:{i:0;s:6:\"fxerrl\";}}s:5:\"label\";s:16:\"Top Header Inner\";}i:2;a:6:{s:2:\"id\";s:6:\"eukwpf\";s:4:\"name\";s:3:\"div\";s:6:\"parent\";s:6:\"llxgtk\";s:8:\"children\";a:3:{i:0;s:6:\"pebshg\";i:1;s:6:\"owtjkg\";i:2;s:6:\"iwzngu\";}s:8:\"settings\";a:3:{s:3:\"tag\";s:2:\"ul\";s:17:\"_cssGlobalClasses\";a:2:{i:0;s:6:\"azamgj\";i:1;s:6:\"vxored\";}s:11:\"_attributes\";a:1:{i:0;a:3:{s:2:\"id\";s:6:\"ycvxgo\";s:4:\"name\";s:10:\"aria-label\";s:5:\"value\";s:56:\"Lijst met telefoonnummer en e-mailadres van {site_title}\";}}}s:5:\"label\";s:4:\"List\";}i:3;a:6:{s:2:\"id\";s:6:\"nccqhk\";s:4:\"name\";s:10:\"text-basic\";s:6:\"parent\";s:6:\"pebshg\";s:8:\"children\";a:0:{}s:8:\"settings\";a:2:{s:4:\"text\";s:24:\"Ewa 06 165 000 95\";s:4:\"link\";a:2:{s:4:\"type\";s:8:\"external\";s:3:\"url\";s:16:\"tel:+31616500095\";}}s:5:\"label\";s:5:\"Phone\";}i:4;a:6:{s:2:\"id\";s:6:\"dqbeta\";s:4:\"name\";s:10:\"text-basic\";s:6:\"parent\";s:6:\"iwzngu\";s:8:\"children\";a:0:{}s:8:\"settings\";a:2:{s:4:\"text\";s:43:\"E-mail {echo:display_customer_email}\";s:4:\"link\";a:3:{s:4:\"type\";s:8:\"external\";s:9:\"ariaLabel\";s:55:\"{echo:display_customer_email}, e-mailadres {site_title}\";s:3:\"url\";s:29:\"{echo:display_customer_email}\";}}s:5:\"label\";s:5:\"Email\";}i:5;a:6:{s:2:\"id\";s:6:\"nwuate\";s:4:\"name\";s:7:\"section\";s:6:\"parent\";i:0;s:8:\"children\";a:1:{i:0;s:6:\"mrkqqh\";}s:8:\"settings\";a:1:{s:17:\"_cssGlobalClasses\";a:1:{i:0;s:6:\"pfkjpg\";}}s:5:\"label\";s:6:\"Header\";}i:6;a:6:{s:2:\"id\";s:6:\"mrkqqh\";s:4:\"name\";s:9:\"container\";s:6:\"parent\";s:6:\"nwuate\";s:8:\"children\";a:2:{i:0;s:6:\"ebockw\";i:1;s:6:\"bspzcf\";}s:8:\"settings\";a:1:{s:17:\"_cssGlobalClasses\";a:1:{i:0;s:6:\"avacsi\";}}s:5:\"label\";s:12:\"Header Inner\";}i:7;a:6:{s:2:\"id\";s:6:\"bspzcf\";s:4:\"name\";s:8:\"nav-menu\";s:6:\"parent\";s:6:\"mrkqqh\";s:8:\"children\";a:0:{}s:8:\"settings\";a:4:{s:17:\"_cssGlobalClasses\";a:1:{i:0;s:6:\"nkgrlj\";}s:4:\"menu\";s:1:\"2\";s:10:\"mobileMenu\";s:15:\"tablet_portrait\";s:16:\"mobileMenuFadeIn\";b:1;}s:11:\"themeStyles\";a:0:{}}i:8;a:5:{s:2:\"id\";s:6:\"ebockw\";s:4:\"name\";s:4:\"logo\";s:6:\"parent\";s:6:\"mrkqqh\";s:8:\"children\";a:0:{}s:8:\"settings\";a:3:{s:4:\"logo\";a:5:{s:2:\"id\";i:49;s:8:\"filename\";s:8:\"logo.png\";s:4:\"size\";s:4:\"full\";s:4:\"full\";s:73:\"https://uniclean-facility.instawp.xyz/wp-content/uploads/2024/03/logo.png\";s:3:\"url\";s:73:\"https://uniclean-facility.instawp.xyz/wp-content/uploads/2024/03/logo.png\";}s:8:\"logoText\";s:12:\"{site_title}\";s:17:\"_cssGlobalClasses\";a:1:{i:0;s:6:\"vgryml\";}}}i:9;a:6:{s:2:\"id\";s:6:\"pebshg\";s:4:\"name\";s:3:\"div\";s:6:\"parent\";s:6:\"eukwpf\";s:8:\"children\";a:1:{i:0;s:6:\"nccqhk\";}s:8:\"settings\";a:1:{s:3:\"tag\";s:2:\"li\";}s:5:\"label\";s:9:\"List item\";}i:10;a:6:{s:2:\"id\";s:6:\"iwzngu\";s:4:\"name\";s:3:\"div\";s:6:\"parent\";s:6:\"eukwpf\";s:8:\"children\";a:1:{i:0;s:6:\"dqbeta\";}s:8:\"settings\";a:1:{s:3:\"tag\";s:2:\"li\";}s:5:\"label\";s:9:\"List item\";}i:11;a:6:{s:2:\"id\";s:6:\"owtjkg\";s:4:\"name\";s:3:\"div\";s:6:\"parent\";s:6:\"eukwpf\";s:8:\"children\";a:1:{i:0;s:6:\"beusmo\";}s:8:\"settings\";a:1:{s:3:\"tag\";s:2:\"li\";}s:5:\"label\";s:9:\"List item\";}i:12;a:6:{s:2:\"id\";s:6:\"beusmo\";s:4:\"name\";s:10:\"text-basic\";s:6:\"parent\";s:6:\"owtjkg\";s:8:\"children\";a:0:{}s:8:\"settings\";a:2:{s:4:\"text\";s:30:\"Sebastian 06 260 955 21\";s:4:\"link\";a:2:{s:4:\"type\";s:8:\"external\";s:3:\"url\";s:16:\"tel:+31626095521\";}}s:5:\"label\";s:5:\"Phone\";}}";
//		$data = stripslashes_deep($data);
//
//		echo "<pre>";
//		var_dump( is_serialized( $data ) );
//		echo "</pre>";
//
//		echo "<pre>";
//		print_r( unserialize( $data ) );
//		echo "</pre>";
//
//		die();

		global $mysqli;

		function iwp_is_serialized( $value, &$result = null ) {
			if ( ! is_string( $value ) ) {
				return false;
			}

			// Serialized false, return true. unserialize() returns false on an
			// invalid string or it could return false if the string is serialized
			// false, eliminate that possibility.
			if ( $value === 'b:0;' ) {
				$result = false;

				return true;
			}

			$length = strlen( $value );
			$end    = '';

			if ( isset( $value[0] ) ) {
				switch ( $value[0] ) {
					case 's':
						if ( $value[ $length - 2 ] !== '"' ) {
							return false;
						}
					case 'b':
					case 'i':
					case 'd':
						// This looks odd but it is quicker than isset()ing
						$end .= ';';
					case 'a':
					case 'O':
						$end .= '}';

						if ( $value[1] !== ':' ) {
							return false;
						}

						switch ( $value[2] ) {
							case 0:
							case 1:
							case 2:
							case 3:
							case 4:
							case 5:
							case 6:
							case 7:
							case 8:
							case 9:
								break;

							default:
								return false;
						}
					case 'N':
						$end .= ';';

						if ( $value[ $length - 1 ] !== $end[0] ) {
							return false;
						}
						break;

					default:
						return false;
				}
			}

			if ( ( $result = @unserialize( $value ) ) === false ) {
				$result = null;

				return false;
			}

			return true;
		}

		function iwp_maybe_serialize( $data ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				return serialize( $data );
			}

			return $data;
		}

		function iwp_recursive_search_replace( &$array, $search_replace ) {
			foreach ( $array as $key => &$value ) {
				if ( is_array( $value ) ) {
					iwp_recursive_search_replace( $value, $search_replace );
				} elseif ( is_string( $value ) ) {
					$array[ $key ] = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $value );
				}
			}
		}

		function iwp_maybe_unserialize( $data ) {
			if ( iwp_is_serialized( $data ) ) {

				global $search_replace;

				$data = @unserialize( trim( $data ) );

				if ( is_array( $data ) ) {
					iwp_recursive_search_replace( $data, $search_replace );
				}
			}

			return $data;
		}

		function iwp_array_filter_recursive( array $array, callable $callback = null ) {
			$array = is_callable( $callback ) ? array_filter( $array, $callback ) : array_filter( $array );
			foreach ( $array as &$value ) {
				if ( is_array( $value ) ) {
					$value = call_user_func( __FUNCTION__, $value, $callback );
				}
			}

			return $array;
		}

		function iwp_parse_db_data( $data ) {
			$values = iwp_maybe_unserialize( $data );

			if ( is_array( $values ) && ! empty( $values ) ) {
				$data = iwp_maybe_serialize( iwp_array_filter_recursive( $values ) );
			}

			return $data;
		}

		// a:13:{i:0;a:6:{s:2:"id";s:6:"xrjuvz";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:1:{i:0;s:6:"llxgtk";}s:8:"settings";a:2:{s:3:"tag";s:3:"div";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"eircxj";}}s:5:"label";s:10:"Top Header";}i:1;a:6:{s:2:"id";s:6:"llxgtk";s:4:"name";s:9:"container";s:6:"parent";s:6:"xrjuvz";s:8:"children";a:1:{i:0;s:6:"eukwpf";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"fxerrl";}}s:5:"label";s:16:"Top Header Inner";}i:2;a:6:{s:2:"id";s:6:"eukwpf";s:4:"name";s:3:"div";s:6:"parent";s:6:"llxgtk";s:8:"children";a:3:{i:0;s:6:"pebshg";i:1;s:6:"owtjkg";i:2;s:6:"iwzngu";}s:8:"settings";a:3:{s:3:"tag";s:2:"ul";s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"azamgj";i:1;s:6:"vxored";}s:11:"_attributes";a:1:{i:0;a:3:{s:2:"id";s:6:"ycvxgo";s:4:"name";s:10:"aria-label";s:5:"value";s:56:"Lijst met telefoonnummer en e-mailadres van {site_title}";}}}s:5:"label";s:4:"List";}i:3;a:6:{s:2:"id";s:6:"nccqhk";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"pebshg";s:8:"children";a:0:{}s:8:"settings";a:2:{s:4:"text";s:24:"<b>Ewa</b> 06 165 000 95";s:4:"link";a:2:{s:4:"type";s:8:"external";s:3:"url";s:16:"tel:+31616500095";}}s:5:"label";s:5:"Phone";}i:4;a:6:{s:2:"id";s:6:"dqbeta";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"iwzngu";s:8:"children";a:0:{}s:8:"settings";a:2:{s:4:"text";s:43:"<b>E-mail</b> {echo:display_customer_email}";s:4:"link";a:3:{s:4:"type";s:8:"external";s:9:"ariaLabel";s:55:"{echo:display_customer_email}, e-mailadres {site_title}";s:3:"url";s:29:"{echo:display_customer_email}";}}s:5:"label";s:5:"Email";}i:5;a:6:{s:2:"id";s:6:"nwuate";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:1:{i:0;s:6:"mrkqqh";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"pfkjpg";}}s:5:"label";s:6:"Header";}i:6;a:6:{s:2:"id";s:6:"mrkqqh";s:4:"name";s:9:"container";s:6:"parent";s:6:"nwuate";s:8:"children";a:2:{i:0;s:6:"ebockw";i:1;s:6:"bspzcf";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"avacsi";}}s:5:"label";s:12:"Header Inner";}i:7;a:6:{s:2:"id";s:6:"bspzcf";s:4:"name";s:8:"nav-menu";s:6:"parent";s:6:"mrkqqh";s:8:"children";a:0:{}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"nkgrlj";}s:4:"menu";s:1:"2";s:10:"mobileMenu";s:15:"tablet_portrait";s:16:"mobileMenuFadeIn";b:1;}s:11:"themeStyles";a:0:{}}i:8;a:5:{s:2:"id";s:6:"ebockw";s:4:"name";s:4:"logo";s:6:"parent";s:6:"mrkqqh";s:8:"children";a:0:{}s:8:"settings";a:3:{s:4:"logo";a:5:{s:2:"id";i:49;s:8:"filename";s:8:"logo.png";s:4:"size";s:4:"full";s:4:"full";s:73:"https://uniclean-facility.instawp.xyz/wp-content/uploads/2024/03/logo.png";s:3:"url";s:73:"https://uniclean-facility.instawp.xyz/wp-content/uploads/2024/03/logo.png";}s:8:"logoText";s:12:"{site_title}";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"vgryml";}}}i:9;a:6:{s:2:"id";s:6:"pebshg";s:4:"name";s:3:"div";s:6:"parent";s:6:"eukwpf";s:8:"children";a:1:{i:0;s:6:"nccqhk";}s:8:"settings";a:1:{s:3:"tag";s:2:"li";}s:5:"label";s:9:"List item";}i:10;a:6:{s:2:"id";s:6:"iwzngu";s:4:"name";s:3:"div";s:6:"parent";s:6:"eukwpf";s:8:"children";a:1:{i:0;s:6:"dqbeta";}s:8:"settings";a:1:{s:3:"tag";s:2:"li";}s:5:"label";s:9:"List item";}i:11;a:6:{s:2:"id";s:6:"owtjkg";s:4:"name";s:3:"div";s:6:"parent";s:6:"eukwpf";s:8:"children";a:1:{i:0;s:6:"beusmo";}s:8:"settings";a:1:{s:3:"tag";s:2:"li";}s:5:"label";s:9:"List item";}i:12;a:6:{s:2:"id";s:6:"beusmo";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"owtjkg";s:8:"children";a:0:{}s:8:"settings";a:2:{s:4:"text";s:30:"<b>Sebastian</b> 06 260 955 21";s:4:"link";a:2:{s:4:"type";s:8:"external";s:3:"url";s:16:"tel:+31626095521";}}s:5:"label";s:5:"Phone";}}
		// a:13:{i:0;a:6:{s:2:"id";s:6:"xrjuvz";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:1:{i:0;s:6:"llxgtk";}s:8:"settings";a:2:{s:3:"tag";s:3:"div";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"eircxj";}}s:5:"label";s:10:"Top Header";}i:1;a:6:{s:2:"id";s:6:"llxgtk";s:4:"name";s:9:"container";s:6:"parent";s:6:"xrjuvz";s:8:"children";a:1:{i:0;s:6:"eukwpf";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"fxerrl";}}s:5:"label";s:16:"Top Header Inner";}i:2;a:6:{s:2:"id";s:6:"eukwpf";s:4:"name";s:3:"div";s:6:"parent";s:6:"llxgtk";s:8:"children";a:3:{i:0;s:6:"pebshg";i:1;s:6:"owtjkg";i:2;s:6:"iwzngu";}s:8:"settings";a:3:{s:3:"tag";s:2:"ul";s:17:"_cssGlobalClasses";a:2:{i:0;s:6:"azamgj";i:1;s:6:"vxored";}s:11:"_attributes";a:1:{i:0;a:3:{s:2:"id";s:6:"ycvxgo";s:4:"name";s:10:"aria-label";s:5:"value";s:56:"Lijst met telefoonnummer en e-mailadres van {site_title}";}}}s:5:"label";s:4:"List";}i:3;a:6:{s:2:"id";s:6:"nccqhk";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"pebshg";s:8:"children";a:0:{}s:8:"settings";a:2:{s:4:"text";s:24:"Ewa 06 165 000 95";s:4:"link";a:2:{s:4:"type";s:8:"external";s:3:"url";s:16:"tel:+31616500095";}}s:5:"label";s:5:"Phone";}i:4;a:6:{s:2:"id";s:6:"dqbeta";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"iwzngu";s:8:"children";a:0:{}s:8:"settings";a:2:{s:4:"text";s:43:"E-mail {echo:display_customer_email}";s:4:"link";a:3:{s:4:"type";s:8:"external";s:9:"ariaLabel";s:55:"{echo:display_customer_email}, e-mailadres {site_title}";s:3:"url";s:29:"{echo:display_customer_email}";}}s:5:"label";s:5:"Email";}i:5;a:6:{s:2:"id";s:6:"nwuate";s:4:"name";s:7:"section";s:6:"parent";i:0;s:8:"children";a:1:{i:0;s:6:"mrkqqh";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"pfkjpg";}}s:5:"label";s:6:"Header";}i:6;a:6:{s:2:"id";s:6:"mrkqqh";s:4:"name";s:9:"container";s:6:"parent";s:6:"nwuate";s:8:"children";a:2:{i:0;s:6:"ebockw";i:1;s:6:"bspzcf";}s:8:"settings";a:1:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"avacsi";}}s:5:"label";s:12:"Header Inner";}i:7;a:6:{s:2:"id";s:6:"bspzcf";s:4:"name";s:8:"nav-menu";s:6:"parent";s:6:"mrkqqh";s:8:"children";a:0:{}s:8:"settings";a:4:{s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"nkgrlj";}s:4:"menu";s:1:"2";s:10:"mobileMenu";s:15:"tablet_portrait";s:16:"mobileMenuFadeIn";b:1;}s:11:"themeStyles";a:0:{}}i:8;a:5:{s:2:"id";s:6:"ebockw";s:4:"name";s:4:"logo";s:6:"parent";s:6:"mrkqqh";s:8:"children";a:0:{}s:8:"settings";a:3:{s:4:"logo";a:5:{s:2:"id";i:49;s:8:"filename";s:8:"logo.png";s:4:"size";s:4:"full";s:4:"full";s:73:"https://vasco-test-jaed-2.a.instawpsites.com/wp-content/uploads/2024/03/logo.png";s:3:"url";s:73:"https://vasco-test-jaed-2.a.instawpsites.com/wp-content/uploads/2024/03/logo.png";}s:8:"logoText";s:12:"{site_title}";s:17:"_cssGlobalClasses";a:1:{i:0;s:6:"vgryml";}}}i:9;a:6:{s:2:"id";s:6:"pebshg";s:4:"name";s:3:"div";s:6:"parent";s:6:"eukwpf";s:8:"children";a:1:{i:0;s:6:"nccqhk";}s:8:"settings";a:1:{s:3:"tag";s:2:"li";}s:5:"label";s:9:"List item";}i:10;a:6:{s:2:"id";s:6:"iwzngu";s:4:"name";s:3:"div";s:6:"parent";s:6:"eukwpf";s:8:"children";a:1:{i:0;s:6:"dqbeta";}s:8:"settings";a:1:{s:3:"tag";s:2:"li";}s:5:"label";s:9:"List item";}i:11;a:6:{s:2:"id";s:6:"owtjkg";s:4:"name";s:3:"div";s:6:"parent";s:6:"eukwpf";s:8:"children";a:1:{i:0;s:6:"beusmo";}s:8:"settings";a:1:{s:3:"tag";s:2:"li";}s:5:"label";s:9:"List item";}i:12;a:6:{s:2:"id";s:6:"beusmo";s:4:"name";s:10:"text-basic";s:6:"parent";s:6:"owtjkg";s:8:"children";a:0:{}s:8:"settings";a:2:{s:4:"text";s:30:"Sebastian 06 260 955 21";s:4:"link";a:2:{s:4:"type";s:8:"external";s:3:"url";s:16:"tel:+31626095521";}}s:5:"label";s:5:"Phone";}}

		global $search_replace;

		$db_host         = 'localhost';
		$db_username     = 'cenirihedu9023_fimisizoxe0400';
		$db_password     = 'ZlEbCFmWX5eq1IMQyGL8';
		$db_name         = 'cenirihedu9023_JvmezfoEiU40xB2Zu9hc';
		$mysqli          = new mysqli( $db_host, $db_username, $db_password, $db_name );
		$statements      = '';
		$statements      .= 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"' . ";\n\n";
		$tableName       = 'iwp08c1_postmeta';
		$offset          = 0;
		$limit           = 1;
		$where_clause    = '`meta_id`=36';
		$query           = "SELECT * FROM `$tableName` WHERE {$where_clause} LIMIT {$limit} OFFSET $offset";
		$result          = $mysqli->query( $query );
		$sqlStatements   = [];
		$source_domain_1 = 'vasco-source.a.instawpsites.com';
		$dest_domain     = 'noticeable-alligator-8fd9a5.a.instawpsites.com';
		$search_replace  = [
			$source_domain_1 => $dest_domain,
		];

		if ( $mysqli->errno ) {
			echo 'Database query error - ' . $mysqli->connect_error;
			exit( 1 );
		}

		while ( $dataRow = $result->fetch_assoc() ) {
			$columns = array_map( function ( $value ) {
				global $mysqli;

				if ( empty( $value ) ) {
					return is_array( $value ) ? [] : '';
				}

				return $mysqli->real_escape_string( $value );
			}, array_keys( $dataRow ) );

			$values = array_map( 'iwp_parse_db_data', array_values( $dataRow ) );
			$values = array_map( function ( $value ) {
				global $mysqli;

				if ( is_numeric( $value ) ) {
					return $value;
				} else if ( is_null( $value ) ) {
					return "NULL";
				} else if ( is_array( $value ) && empty( $value ) ) {
					$value = [];
				} else if ( is_string( $value ) ) {
					$value = $mysqli->real_escape_string( $value );
				}

				$value = stripslashes_deep( $value );

				if ( is_serialized( $value ) ) {
					$value = iwp_maybe_unserialize( $value );
					$value = iwp_maybe_serialize( $value );
				}

				return "'" . $value . "'";
			}, $values );

			echo "<pre>";
			print_r( $values );
			echo "</pre>";


			$sql_query       = "INSERT IGNORE INTO `$tableName` (`" . implode( "`, `", $columns ) . "`) VALUES (" . implode( ", ", $values ) . ");";
			$sqlStatements[] = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $sql_query );
		}

		echo "<pre>";
		print_r( $sqlStatements );
		echo "</pre>";

		die();
	}
}, 0 );

