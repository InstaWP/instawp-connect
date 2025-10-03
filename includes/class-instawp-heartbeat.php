<?php
/**
 * Class for heartbeat
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Inventory;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'InstaWP_Heartbeat' ) ) {
	class InstaWP_Heartbeat {

		public function __construct() {
			add_action( 'init', array( $this, 'register_events' ) );
			add_action( 'add_option_instawp_api_heartbeat', array( $this, 'clear_heartbeat_action' ) );
			add_action( 'update_option_instawp_api_heartbeat', array( $this, 'clear_heartbeat_action' ) );
			add_action( 'add_option_instawp_rm_heartbeat', array( $this, 'clear_heartbeat_action' ) );
			add_action( 'update_option_instawp_rm_heartbeat', array( $this, 'clear_heartbeat_action' ) );
			add_action( 'instawp_send_heartbeat', array( $this, 'send_heartbeat_data' ) ); // Every 24 hours
			add_action( 'instawp_handle_heartbeat', array( $this, 'handle_heartbeat_data' ) ); // User defined interval
		}

		public function register_events() {
			if ( ! as_has_scheduled_action( 'instawp_send_heartbeat', array(), 'instawp-connect' ) ) {
				as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'instawp_send_heartbeat', array(), 'instawp-connect' );
			}

			$heartbeat = Option::get_option( 'instawp_rm_heartbeat', 'on' );
			$heartbeat = empty( $heartbeat ) ? 'on' : $heartbeat;

			if ( 'on' === $heartbeat ) {
				if ( ! as_has_scheduled_action( 'instawp_handle_heartbeat', array(), 'instawp-connect' ) ) {
					$interval = Option::get_option( 'instawp_api_heartbeat', 240 );
					$interval = empty( $interval ) ? 240 : (int) $interval;

					as_schedule_recurring_action( time(), ( $interval * MINUTE_IN_SECONDS ), 'instawp_handle_heartbeat', array(), 'instawp-connect' );
				}
			}
		}

		public function clear_heartbeat_action() {
			as_unschedule_all_actions( 'instawp_handle_heartbeat', array(), 'instawp-connect' );
		}

		public function send_heartbeat_data() {
			$heartbeat_response = self::send_heartbeat();
			if ( ! $heartbeat_response['success'] ) {
				return;
			}

			$failed_count = Option::get_option( 'instawp_heartbeat_failed', 0 );
			$failed_count = $failed_count ? $failed_count : 0;

			if ( $failed_count > 10 ) {
				Option::delete_option( 'instawp_heartbeat_failed' );
				Option::update_option( 'instawp_rm_heartbeat', 'on' );
			}
		}

		public function handle_heartbeat_data() {
			$heartbeat_response = self::send_heartbeat();

			if ( $heartbeat_response['success'] ) {
				Option::delete_option( 'instawp_heartbeat_failed' );
			} else {
				$failed_count = Option::get_option( 'instawp_heartbeat_failed', 0 );
				$failed_count = $failed_count ? $failed_count : 0;

				++$failed_count;

				Option::update_option( 'instawp_heartbeat_failed', $failed_count );

				if ( $failed_count > 10 ) {
					Option::update_option( 'instawp_rm_heartbeat', 'off' );

					if ( intval( $heartbeat_response['response_code'] ) === 404 ) {
						instawp_reset_running_migration( 'hard' );
					}
				}
			}
		}

		public static function prepare_data() {
			if ( ! class_exists( 'WP_Debug_Data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
			}

			$sizes_data   = WP_Debug_Data::get_sizes();
			$wp_version   = get_bloginfo( 'version' );
			$php_version  = phpversion();
			$active_theme = wp_get_theme()->get( 'Name' );
			$count_posts  = wp_count_posts();
			$posts        = $count_posts->publish;
			$count_pages  = wp_count_posts( 'page' );
			$pages        = $count_pages->publish;
			$count_users  = count_users();
			$users        = $count_users['total_users'];

			$post_data = array();
			foreach ( get_post_types() as $post_type ) {
				$post_data[ $post_type ] = (array) wp_count_posts( $post_type );
			}

			$inventory = new Inventory();
			$site_data = $inventory->fetch();

			unset( $sizes_data['total_size'] );
			$total_size    = array_sum( array_filter( wp_list_pluck( $sizes_data, 'raw' ) ) );
			$database_size = ! empty( $sizes_data['database_size']['raw'] ) ? $sizes_data['database_size']['raw'] : 0;

			return array(
				'title'             => get_bloginfo( 'name' ),
				'icon'              => get_site_icon_url(),
				'wp_version'        => $wp_version,
				'php_version'       => $php_version,
				'plugin_version'    => INSTAWP_PLUGIN_VERSION,
				'total_size'        => $total_size,
				'file_size'         => $total_size - $database_size,
				'db_size'           => $database_size,
				'theme'             => $active_theme,
				'posts'             => $posts,
				'pages'             => $pages,
				'users'             => $users,
				'core'              => $site_data['core'],
				'themes'            => $site_data['theme'],
				'plugins'           => $site_data['plugin'],
				'mu_plugins'        => $site_data['mu_plugin'],
				'consolidated_data' => array(
					'users' => $count_users['avail_roles'],
					'posts' => $post_data,
				),
			);
		}

		/**
		 * Set last sent heartbeat data
		 *
		 * @param array  $data Heartbeat data
		 * @param string $connect_id Connection ID
		 *
		 * @return bool
		 */
		public static function set_last_heartbeat_data( $data, $connect_id ) {
			if ( empty( $data ) || empty( $connect_id ) ) {
				return false;
			}
			// Get last heartbeat.
			$hearbeats = self::get_last_heartbeat_data();
			// If last heartbeat is not empty.
			if ( ! empty( $hearbeats[ $connect_id ] ) && $data === $hearbeats[ $connect_id ] ) {
				return false;
			}
			// Set last heartbeat.
			$hearbeats[ $connect_id ] = $data;
			Option::update_option( 'instawp_last_heartbeat_data', $hearbeats );
			return true;
		}

		/**
		 * Get last sent heartbeat data.
		 *
		 * @param string $connect_id Connection ID.
		 *
		 * @return array
		 */
		public static function get_last_heartbeat_data( $connect_id = null ) {
			$hearbeats = Option::get_option( 'instawp_last_heartbeat_data', array() );
			$hearbeats = is_array( $hearbeats ) ? $hearbeats : array();
			if ( empty( $connect_id ) ) {
				return $hearbeats;
			}
			return ( empty( $hearbeats ) || empty( $hearbeats[ $connect_id ] ) ) ? '' : $hearbeats[ $connect_id ];
		}

		/**
		 * Check if new heartbeat data.
		 *
		 * @param array  $data Heartbeat data.
		 * @param string $connect_id Connection ID.
		 *
		 * @return bool
		 */
		public static function has_new_heartbeat_data( $data, $connect_id ) {
			return self::get_last_heartbeat_data( $connect_id ) !== $data;
		}

		/**
		 * Send heartbeat data.
		 *
		 * @param string $connect_id Connection ID.
		 *
		 * @return array
		 */
		public static function send_heartbeat( $connect_id = null ) {
			if ( defined( 'INSTAWP_DEBUG_LOG' ) && true === INSTAWP_DEBUG_LOG ) {
				error_log( 'HEARTBEAT RAN AT : ' . date( 'd-m-Y, H:i:s, h:i:s' ) );
			}

			if ( empty( $connect_id ) ) {
				$connect_id = instawp_get_connect_id();
			}

			if ( empty( $connect_id ) ) {
				return array(
					'success'       => false,
					'response'      => array(),
					'response_code' => 404,
				);
			}

			$heartbeat_data = self::prepare_data();
			$heartbeat_body = wp_json_encode(
				array(
					'site_information' => $heartbeat_data,
				)
			);
			$heartbeat_body = base64_encode( $heartbeat_body ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			// If no new heartbeat data.
			$heartbeat_body = self::has_new_heartbeat_data( $heartbeat_body, $connect_id ) ? $heartbeat_body : null;

			$heartbeat_response = Curl::do_curl(
				"connects/{$connect_id}/heartbeat",
				empty( $heartbeat_body ) ? array() : array( $heartbeat_body ),
				array(),
				'POST',
				'v1'
			);
			$response_code      = Helper::get_args_option( 'code', $heartbeat_response );
			$success            = intval( $response_code ) === 200;

			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( 'Print Heartbeat API Curl Response Start' );
				error_log( wp_json_encode( $heartbeat_response, true ) );
				error_log( 'Print Heartbeat API Curl Response End' );
			}

			if ( $success && ! empty( $heartbeat_body ) ) {
				self::set_last_heartbeat_data( $heartbeat_body, $connect_id );
			}

			return array(
				'success'       => $success,
				'response'      => $heartbeat_response,
				'response_code' => $response_code,
			);
		}
	}
}

new InstaWP_Heartbeat();
