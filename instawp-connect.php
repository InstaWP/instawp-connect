<?php
/**
 * @link              https://instawp.com/
 * @since             0.0.1
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       InstaWP Connect
 * Description:       1-click WordPress plugin for Staging, Migrations, Management, Sync and Companion plugin for InstaWP.
 * Version:           0.1.0.78
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

defined( 'INSTAWP_PLUGIN_VERSION' ) || define( 'INSTAWP_PLUGIN_VERSION', '0.1.0.78' );
defined( 'INSTAWP_API_DOMAIN_PROD' ) || define( 'INSTAWP_API_DOMAIN_PROD', 'https://app.instawp.io' );

$wp_plugin_url   = WP_PLUGIN_URL . '/' . plugin_basename( __DIR__ ) . '/';
$parsed_site_url = wp_parse_url( site_url() );

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
defined( 'INSTAWP_CONNECT_PLAN_ID' ) || define( 'INSTAWP_CONNECT_PLAN_ID', 1 );
defined( 'INSTAWP_CONNECT_PLAN_EXPIRE_DAYS' ) || define( 'INSTAWP_CONNECT_PLAN_EXPIRE_DAYS', 0 );

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
		Option::update_option( 'instawp_default_user', get_current_user_id() );
	}

	$instawp_sync_tab_roles = Option::get_option( 'instawp_sync_tab_roles' );
	if ( empty( $instawp_sync_tab_roles ) ) {
		$user  = wp_get_current_user();
		$roles = ( array ) $user->roles;
		Option::update_option( 'instawp_sync_tab_roles', $roles );
	}

	$connect_id = instawp_get_connect_id();
	if ( ! empty( $connect_id ) ) {
		$response = Curl::do_curl( "connects/{$connect_id}/restore", array( 'url' => Helper::wp_site_url() ) );
		if ( empty( $response['success'] ) ) {
			Option::delete_option( 'instawp_api_options' );
		}
	}

	$default_plan_id          = INSTAWP_CONNECT_PLAN_ID;
	$default_plan_expire_days = INSTAWP_CONNECT_PLAN_EXPIRE_DAYS;

	if ( defined( 'CONNECT_WHITELABEL' ) && CONNECT_WHITELABEL && defined( 'CONNECT_WHITELABEL_PLAN_DETAILS' ) && is_array( CONNECT_WHITELABEL_PLAN_DETAILS ) ) {
		$default_plan = array_filter( CONNECT_WHITELABEL_PLAN_DETAILS, function ( $plan ) {
			return $plan['default'] === true;
		} );

		if ( ! empty( $default_plan ) ) {
			$default_plan_id          = $default_plan[0]['plan_id'];
			$default_plan_expire_days = $default_plan[0]['trial'];
		}
	}

	Option::update_option( 'instawp_connect_plan_id', $default_plan_id );
	Option::update_option( 'instawp_connect_plan_expire_days', $default_plan_expire_days );
}

/*Deactivate Hook Handle*/
function instawp_plugin_deactivate() {
	InstaWP_Tools::instawp_reset_permalink();
	Option::delete_option( 'instawp_last_heartbeat_sent' );

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


/**
 * Custom Functions Started
 */
if ( ! function_exists( 'iwp_is_serialized' ) ) {
	function iwp_is_serialized( $data, $strict = true ) {
		// If it isn't a string, it isn't serialized.
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}
		if ( $strict ) {
			$lastc = substr( $data, - 1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return false;
			}
		} else {
			$semicolon = strpos( $data, ';' );
			$brace     = strpos( $data, '}' );
			// Either ; or } must exist.
			if ( false === $semicolon && false === $brace ) {
				return false;
			}
			// But neither must be in the first X characters.
			if ( false !== $semicolon && $semicolon < 3 ) {
				return false;
			}
			if ( false !== $brace && $brace < 4 ) {
				return false;
			}
		}
		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( $strict ) {
					if ( '"' !== substr( $data, - 2, 1 ) ) {
						return false;
					}
				} elseif ( ! str_contains( $data, '"' ) ) {
					return false;
				}
			// Or else fall through.
			case 'a':
			case 'O':
			case 'E':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				$end = $strict ? '$' : '';

				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
		}

		return false;
	}
}

if ( ! function_exists( 'iwp_maybe_serialize' ) ) {
	function iwp_maybe_serialize( $data ) {

		if ( is_array( $data ) || is_object( $data ) ) {
			return serialize( $data );
		}

		if ( iwp_is_serialized( $data, false ) ) {
			return serialize( $data );
		}

		return $data;
	}
}


function iwp_recursive_unserialize_replace( $data, $search_replace ) {

	if ( is_string( $data ) ) {
		return str_replace( array_keys( $search_replace ), array_values( $search_replace ), $data );
	}

	if ( is_array( $data ) ) {
		$data = array_map( function ( $item ) use ( $search_replace ) {
			return iwp_recursive_unserialize_replace( $item, $search_replace );
		}, $data );
	} elseif ( is_object( $data ) ) {
		// Check if the object is __PHP_Incomplete_Class
		if ( $data instanceof __PHP_Incomplete_Class ) {

			$className = get_class( $data );

			// iwp_send_migration_log( 'Incomplete Class Warning', "Encountered incomplete class: $className. Make sure this class is loaded before unserialization.", [ 'class' => $className ] );

			return $data;
		}

		$properties = [];

		try {
			$reflection = new ReflectionObject( $data );
			$properties = $reflection->getProperties();
		} catch ( Exception $e ) {
//			iwp_send_migration_log(
//				'Reflection Error',
//				"Failed to reflect object of class " . get_class( $data ),
//				[ 'error' => $e->getMessage() ]
//			);

			return $data;
		}

		echo "<pre>"; print_r( $properties ); echo "</pre>";

//		foreach ( $properties as $property ) {
//			try {
//				$property->setAccessible( true );
//				$value     = $property->getValue( $data );
//				$new_value = iwp_recursive_unserialize_replace( $value, $search_replace );
//				$property->setValue( $data, $new_value );
//			} catch ( Exception $e ) {
//				// Skip this property if we can't access it
//				continue;
//			}
//		}
	}

	return $data;
}

if ( ! function_exists( 'iwp_maybe_unserialize' ) ) {
	function iwp_maybe_unserialize( $data ) {
		if ( iwp_is_serialized( $data ) ) {
			global $search_replace;

			$data = @unserialize( trim( $data ) );

			if ( is_array( $data ) ) {
				$data = iwp_recursive_unserialize_replace( $data, $search_replace );
			}
		}

		return $data;
	}
}

if ( ! function_exists( 'iwp_array_filter_recursive' ) ) {
	function iwp_array_filter_recursive( array $array, callable $callback = null ) {
		$array = is_callable( $callback ) ? array_filter( $array, $callback ) : array_filter( $array );
		foreach ( $array as &$value ) {
			if ( is_array( $value ) ) {
				$value = call_user_func( __FUNCTION__, $value, $callback );
			}
		}

		return $array;
	}
}
/**
 * Custom Functions End
 */

add_action( 'wp_head', function () {
	if ( isset( $_GET['debug'] ) ) {

		global $mysqli, $search_replace;

		$db_host     = 'localhost';
		$db_username = 'xifafugelu2996_fogipixuwo5351';
		$db_password = '6DLeQM9Aj12sf8VxGvXI';
		$db_name     = 'xifafugelu2996_9NCiSZ5Dcbl8LQk1Ustq';
		$mysqli      = new mysqli( $db_host, $db_username, $db_password, $db_name );
		$mysqli->set_charset( 'utf8' );

		$offset    = isset( $_GET['offset'] ) ? (int) $_GET['offset'] : 839;
		$tableName = isset( $_GET['table'] ) ? sanitize_text_field( $_GET['table'] ) : 'AXF_options';
		$query     = "SELECT * FROM `$tableName` WHERE 1 LIMIT 3 OFFSET $offset";
		$result    = $mysqli->query( $query );

		$source_domain  = 'astonished-sandpiper-aab1df.instawp.xyz';
		$dest_domain    = 'roomier-mallard-5683f5.instawp.xyz';
		$search_replace = [
			'//' . $source_domain   => '//' . $dest_domain,
			'\/\/' . $source_domain => '\/\/' . $dest_domain,
		];

		if ( $mysqli->errno ) {
			echo "<pre>";
			print_r( $mysqli->connect_error );
			echo "</pre>";

			return;
		}

		while ( $dataRow = $result->fetch_assoc() ) {
			$columns = array_map( function ( $value ) {
				global $mysqli;

				if ( empty( $value ) ) {
					return is_array( $value ) ? [] : '';
				}

				return $mysqli->real_escape_string( $value );
			}, array_keys( $dataRow ) );

			$values = array_map( function ( $value ) {
				global $mysqli;

				if ( is_numeric( $value ) ) {
					// If $value has leading zero it will mark as string and bypass returning as numeric
					if ( substr( $value, 0, 1 ) !== '0' ) {
						return $value;
					}
				} else if ( is_null( $value ) ) {
					return "NULL";
				} else if ( is_array( $value ) && empty( $value ) ) {
					$value = [];
				} else if ( is_string( $value ) ) {
					if ( iwp_is_serialized( $value ) ) {
						$value = iwp_maybe_unserialize( $value );
						$value = iwp_maybe_serialize( $value );
					}
					$value = $mysqli->real_escape_string( $value );
				}

				return "'" . $value . "'";
			}, array_values( $dataRow ) );

			$sql_query = "INSERT IGNORE INTO `$tableName` (`" . implode( "`, `", $columns ) . "`) VALUES (" . implode( ", ", $values ) . ");";

			echo "<pre>";
			print_r( $sql_query );
			echo "</pre>";
		}

		die();
	}
}, 0 );

