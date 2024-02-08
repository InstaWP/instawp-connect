<?php
/**
 * Class for heartbeat
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'InstaWP_Heartbeat' ) ) {
	class InstaWP_Heartbeat {

		public function __construct() {
			add_action( 'init', array( $this, 'register_events' ), 11 );
			add_action( 'add_option_instawp_api_heartbeat', array( $this, 'clear_heartbeat_action' ) );
			add_action( 'update_option_instawp_api_heartbeat', array( $this, 'clear_heartbeat_action' ) );
			add_action( 'add_option_instawp_rm_heartbeat', array( $this, 'clear_heartbeat_action' ) );
			add_action( 'update_option_instawp_rm_heartbeat', array( $this, 'clear_heartbeat_action' ) );
			add_action( 'instawp_handle_heartbeat', array( $this, 'handle_heartbeat' ) );
			add_action( 'instawp_send_heartbeat', array( $this, 'send_heartbeat_data' ) );
			add_action( 'instawp_handle_heartbeat_status', array( $this, 'handle_heartbeat_status' ) );
		}

		public function register_events() {
			$heartbeat = InstaWP_Setting::get_option( 'instawp_rm_heartbeat', 'on' );
			$heartbeat = empty( $heartbeat ) ? 'on' : $heartbeat;

			$interval = InstaWP_Setting::get_option( 'instawp_api_heartbeat', 15 );
			$interval = empty( $interval ) ? 15 : (int) $interval;

			if ( ! empty( InstaWP_Setting::get_api_key() ) && $heartbeat === 'on' && ! wp_next_scheduled( 'instawp_handle_heartbeat' ) ) {
				wp_schedule_single_event( time() + ( $interval * MINUTE_IN_SECONDS ), 'instawp_handle_heartbeat' );
			}

			if ( ! wp_next_scheduled( 'instawp_send_heartbeat' ) ) {
				wp_schedule_event( time(), 'daily', 'instawp_send_heartbeat' );
			}

			if ( ! wp_next_scheduled( 'instawp_handle_heartbeat_status' ) ) {
				wp_schedule_event( time(), 'daily', 'instawp_handle_heartbeat_status' );
			}
		}

		public function clear_heartbeat_action() {
			wp_unschedule_hook( 'instawp_handle_heartbeat' );
		}

		public function handle_heartbeat() {
			self::send_heartbeat();
		}

		public function send_heartbeat_data() {
			self::send_heartbeat( null, true );
		}

		public function handle_heartbeat_status() {
			$disabled = get_option( 'instawp_rm_heartbeat_failed' );
			if ( ! $disabled ) {
				return;
			}

			if ( self::send_heartbeat() ) {
				update_option( 'instawp_rm_heartbeat', 'on' );
			}
		}

		public static function prepare_data(): array {
			if ( ! class_exists( 'WP_Debug_Data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
			}

//			$heartbeat_data = get_transient( 'instawp_heartbeat_data' );
//			if ( ! empty( $heartbeat_data ) ) {
//				return $heartbeat_data;
//			}

			$sizes_data     = WP_Debug_Data::get_sizes();
			$wp_version     = get_bloginfo( 'version' );
			$php_version    = phpversion();
			$total_size     = $sizes_data['total_size']['size'];
			$active_theme   = wp_get_theme()->get( 'Name' );
			$count_posts    = wp_count_posts();
			$posts          = $count_posts->publish;
			$count_pages    = wp_count_posts( 'page' );
			$pages          = $count_pages->publish;
			$count_users    = count_users();
			$users          = $count_users['total_users'];

			$post_data = array();
			foreach ( get_post_types() as $post_type ) {
				$post_data[ $post_type ] = ( array ) wp_count_posts( $post_type );
			}

			$inventory = new \InstaWP\Connect\Helpers\Inventory();
			$site_data = $inventory->fetch();

			$heartbeat_data = array(
				'wp_version'        => $wp_version,
				'php_version'       => $php_version,
				'plugin_version'    => INSTAWP_PLUGIN_VERSION,
				'total_size'        => $total_size,
				// old items
				'theme'             => $active_theme,
				'posts'             => $posts,
				'pages'             => $pages,
				'users'             => $users,
				// new items
				'core'              => $site_data['core'],
				'themes'            => $site_data['theme'],
				'plugins'           => $site_data['plugin'],
				'mu_plugins'        => $site_data['mu_plugin'],
				'consolidated_data' => array(
					'users' => $count_users['avail_roles'],
					'posts' => $post_data,
				),
			);

			//set_transient( 'instawp_heartbeat_data', $heartbeat_data, 300 );

			return $heartbeat_data;
		}

		public static function send_heartbeat( $connect_id = null, $unfiltered = false ): bool {
			if ( defined( 'INSTAWP_DEBUG_LOG' ) && true === INSTAWP_DEBUG_LOG ) {
				error_log( "HEARTBEAT RAN AT : " . date( 'd-m-Y, H:i:s, h:i:s' ) );
			}

			if ( empty( $connect_id ) ) {
				$connect_id = instawp()->connect_id;
			}

			$last_sent_data = get_option( 'instawp_heartbeat_sent_data', array() );
			$heartbeat_data = self::prepare_data();
			$filtered_data  = $unfiltered ? $heartbeat_data : instawp_array_recursive_diff( $last_sent_data, $heartbeat_data );
			$heartbeat_body = base64_encode( wp_json_encode( $filtered_data ) );

			$success = false;
			for ( $i = 0; $i < 10; $i ++ ) {
				$heartbeat_response = InstaWP_Curl::do_curl( "connects/{$connect_id}/heartbeat", $heartbeat_body, array(), true, 'v1' );
				if ( $heartbeat_response['code'] == 200 ) {
					$success = true;
					break;
				}
			}

			if ( ! $success ) {
				update_option( 'instawp_rm_heartbeat', 'off' );
				update_option( 'instawp_rm_heartbeat_failed', true );
				wp_unschedule_hook( 'instawp_handle_heartbeat' );
			} else {
				delete_option( 'instawp_rm_heartbeat_failed' );
				update_option( 'instawp_heartbeat_sent_data', $heartbeat_data );
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
