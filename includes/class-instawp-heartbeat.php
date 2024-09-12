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
			add_action( 'instawp_handle_heartbeat', array( $this, 'send_heartbeat_data' ) );
			add_action( 'instawp_send_heartbeat', array( $this, 'send_heartbeat_data' ) );
			add_action( 'instawp_handle_heartbeat_status', array( $this, 'handle_heartbeat_status' ) );
		}

		public function register_events() {
			if ( ! as_has_scheduled_action( 'instawp_send_heartbeat', array(), 'instawp-connect' ) ) {
				as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'instawp_send_heartbeat', array(), 'instawp-connect' );
			}

			if ( ! as_has_scheduled_action( 'instawp_handle_heartbeat_status', array(), 'instawp-connect' ) ) {
				as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'instawp_handle_heartbeat_status', array(), 'instawp-connect' );
			}

			$heartbeat = Option::get_option( 'instawp_rm_heartbeat', 'on' );
			$heartbeat = empty( $heartbeat ) ? 'on' : $heartbeat;

			if ( 'on' === $heartbeat ) {
				$interval = Option::get_option( 'instawp_api_heartbeat', 240 );
				$interval = empty( $interval ) ? 240 : (int) $interval;

				if ( ! as_has_scheduled_action( 'instawp_handle_heartbeat', array(), 'instawp-connect' ) ) {
					as_schedule_recurring_action( time(), ( $interval * MINUTE_IN_SECONDS ), 'instawp_handle_heartbeat', array(), 'instawp-connect' );
				}
			}
		}

		public function clear_heartbeat_action() {
			as_unschedule_all_actions( 'instawp_handle_heartbeat', array(), 'instawp-connect' );
		}

		public function send_heartbeat_data() {
			self::send_heartbeat();
		}

		public function handle_heartbeat_status() {
			$disabled = Option::get_option( 'instawp_rm_heartbeat_failed' );
			if ( ! $disabled ) {
				return;
			}

			if ( self::send_heartbeat() ) {
				Option::update_option( 'instawp_rm_heartbeat', 'on' );
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
				$post_data[ $post_type ] = ( array ) wp_count_posts( $post_type );
			}

			$inventory = new Inventory();
			$site_data = $inventory->fetch();

            unset( $sizes_data['total_size'] );
            $total_size    = array_sum( array_filter( wp_list_pluck( $sizes_data, 'raw' ) ) );
            $database_size = ! empty( $sizes_data['database_size']['raw'] ) ? $sizes_data['database_size']['raw'] : 0;

			return array(
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

		public static function send_heartbeat( $connect_id = null ) {
			global $wpdb;

			if ( defined( 'INSTAWP_DEBUG_LOG' ) && true === INSTAWP_DEBUG_LOG ) {
				error_log( "HEARTBEAT RAN AT : " . date( 'd-m-Y, H:i:s, h:i:s' ) );
			}

			if ( empty( $connect_id ) ) {
				$connect_id = instawp_get_connect_id();
			}

			if ( empty( $connect_id ) ) {
				return false;
			}

			$last_sent_data = get_option( 'instawp_heartbeat_sent_data', array() );
			$heartbeat_data = self::prepare_data();

			$setting = Option::get_option( 'instawp_activity_log', 'off' );
			if ( $setting === 'on' ) {
				$log_ids    = $logs = array();
				$table_name = INSTAWP_DB_TABLE_ACTIVITY_LOGS;
				$results    = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$table_name} WHERE severity!=%s", 'critical' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				);

				foreach ( $results as $result ) {
					$logs[ $result->action ] = array(
						'data_type' => current( explode( '_', $result->action ) ),
						'count'     => ! empty( $logs[ $result->action ]['count'] ) ? $logs[ $result->action ]['count'] + 1 : 1,
						'meta'      => array(),
						'data'      => array( ( array ) $result ),
					);
					$log_ids[]               = $result->id;
				}

				$heartbeat_data['activity_logs'] = $logs;
			}

			$heartbeat_body = wp_json_encode( array(
				'site_information' => $heartbeat_data,
				'new_changes'      => instawp_array_recursive_diff( $heartbeat_data, $last_sent_data ),
			) );
			$heartbeat_body = base64_encode( $heartbeat_body ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

			$heartbeat_response = Curl::do_curl( "connects/{$connect_id}/heartbeat", array( $heartbeat_body ), array(), 'POST', 'v1' );
			$response_code      = Helper::get_args_option( 'code', $heartbeat_response );
			$success            = intval( $response_code ) === 200;

			if ( ! $success ) {
				$failed_count = Option::get_option( 'instawp_heartbeat_failed', 0 );
				$failed_count = $failed_count ? $failed_count : 0;

				++$failed_count;

				if ( $failed_count > 10 ) {
					Option::update_option( 'instawp_rm_heartbeat', 'off' );
					Option::update_option( 'instawp_rm_heartbeat_failed', true );

					delete_option( 'instawp_heartbeat_failed' );
					as_unschedule_all_actions( 'instawp_handle_heartbeat', array(), 'instawp-connect' );

					if ( intval( $response_code ) === 404 ) {
						instawp_reset_running_migration( 'hard' );
					}
				} else {
					Option::update_option( 'instawp_heartbeat_failed', $failed_count );
				}
			} else {
				delete_option( 'instawp_heartbeat_failed' );
				delete_option( 'instawp_rm_heartbeat_failed' );

				Option::update_option( 'instawp_heartbeat_sent_data', $heartbeat_data );

				if ( $setting === 'on' ) {
					$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
					$wpdb->query(
						$wpdb->prepare( "DELETE FROM {$table_name} WHERE id IN ($placeholders)", $log_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					);
				}
			}

			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( "Print Heartbeat API Curl Response Start" );
				error_log( wp_json_encode( $heartbeat_response, true ) );
				error_log( "Print Heartbeat API Curl Response End" );
			}

			return $success;
		}
	}
}

new InstaWP_Heartbeat();
