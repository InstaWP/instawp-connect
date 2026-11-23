<?php
/**
 * Who may see and use InstaWP Connect on this site.
 *
 * The list is managed from the InstaWP dashboard, never from wp-admin. client-app pushes it to
 * POST v2/manage/access-control and repeats it on every heartbeat response, so this option is a
 * CACHE of the dashboard's value: nothing in wp-admin writes it, and every sync overwrites it.
 *
 * Until the dashboard has sent a list, the option does not exist and the site keeps the legacy
 * wp-admin blocklist (instawp_hide_plugin_to_users) exactly as before. Once a list has arrived the
 * legacy blocklist is no longer read. A list that arrived once is kept when client-app cannot be
 * reached, so an outage never opens Connect to every admin.
 *
 * Role/capability checks stay with the callers; this class only answers the per-user question.
 *
 * @package InstaWP
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'InstaWP_Access_Control' ) ) {
	class InstaWP_Access_Control {

		/**
		 * The cached dashboard list: array( 'mode' => string, 'users' => array( id => login ), 'synced_at' => int ).
		 */
		const OPTION = 'instawp_access_control';

		/**
		 * The legacy wp-admin blocklist (user IDs). Read only while the dashboard has sent nothing.
		 */
		const LEGACY_OPTION = 'instawp_hide_plugin_to_users';

		/**
		 * Denials already reported, keyed "user_id:action" => expiry timestamp.
		 */
		const REPORTED_OPTION = 'instawp_access_denied_reported';

		const MODE_OFF   = 'off';
		const MODE_BLOCK = 'block';
		const MODE_ALLOW = 'allow';

		const REPORT_HOOK     = 'instawp_report_access_denied';
		const REPORT_INTERVAL = HOUR_IN_SECONDS;

		/**
		 * Headers carrying the acting WordPress user on requests to the InstaWP API.
		 */
		const HEADER_USER_ID    = 'X-InstaWP-WP-User-Id';
		const HEADER_USER_LOGIN = 'X-InstaWP-WP-User-Login';

		public function __construct() {
			add_filter( 'http_request_args', array( $this, 'add_user_headers' ), 10, 2 );
			add_action( self::REPORT_HOOK, array( $this, 'send_denial_report' ) );
		}

		/**
		 * Whether the dashboard has sent a list to this site.
		 *
		 * @return bool
		 */
		public static function is_managed() {
			return is_array( Option::get_option( self::OPTION, false ) );
		}

		/**
		 * The cached dashboard list, normalised. Mode is 'off' when nothing was sent.
		 *
		 * @return array{mode: string, users: array<int, string>, synced_at: int}
		 */
		public static function get_state() {
			$state = Option::get_option( self::OPTION, false );
			$state = is_array( $state ) ? $state : array();

			$mode  = Helper::get_args_option( 'mode', $state, self::MODE_OFF );
			$users = Helper::get_args_option( 'users', $state, array() );

			return array(
				'mode'      => in_array( $mode, self::get_modes(), true ) ? $mode : self::MODE_OFF,
				'users'     => is_array( $users ) ? $users : array(),
				'synced_at' => (int) Helper::get_args_option( 'synced_at', $state, 0 ),
			);
		}

		/**
		 * @return string[]
		 */
		public static function get_modes() {
			return array( self::MODE_OFF, self::MODE_BLOCK, self::MODE_ALLOW );
		}

		/**
		 * Whether a user may see and use InstaWP Connect.
		 *
		 * WP-CLI is always allowed: shell access already means full control of the site.
		 *
		 * @param int $user_id User ID. Defaults to the current user.
		 *
		 * @return bool
		 */
		public static function user_can_access( $user_id = 0 ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return true;
			}

			$user_id = $user_id ? (int) $user_id : get_current_user_id();

			if ( ! self::is_managed() ) {
				$hidden = Option::get_option( self::LEGACY_OPTION );
				$hidden = is_array( $hidden ) ? array_map( 'intval', $hidden ) : array();

				return ! in_array( $user_id, $hidden, true );
			}

			$state  = self::get_state();
			$listed = array_key_exists( $user_id, $state['users'] );

			if ( self::MODE_ALLOW === $state['mode'] ) {
				return $user_id > 0 && $listed;
			}

			if ( self::MODE_BLOCK === $state['mode'] ) {
				return ! $listed;
			}

			return true;
		}

		/**
		 * Read-only description of the dashboard list for the plugin's settings screen.
		 *
		 * @return string HTML.
		 */
		public static function get_summary_html() {
			$state  = self::get_state();
			$logins = array_filter( array_map( 'strval', $state['users'] ) );
			$logins = empty( $logins ) ? esc_html__( 'no users', 'instawp-connect' ) : esc_html( implode( ', ', $logins ) );

			if ( self::MODE_ALLOW === $state['mode'] ) {
				/* translators: %s: comma-separated user logins */
				$summary = sprintf( esc_html__( 'Only these users: %s.', 'instawp-connect' ), $logins );
			} elseif ( self::MODE_BLOCK === $state['mode'] ) {
				/* translators: %s: comma-separated user logins */
				$summary = sprintf( esc_html__( 'Everyone with access except: %s.', 'instawp-connect' ), $logins );
			} else {
				$summary = esc_html__( 'Every user with access.', 'instawp-connect' );
			}

			return $summary . ' ' . sprintf(
				/* translators: %s: link to the InstaWP dashboard */
				esc_html__( 'Managed from your %s.', 'instawp-connect' ),
				'<a href="' . esc_url( Helper::get_api_domain() ) . '" target="_blank" rel="noopener">' . esc_html__( 'InstaWP dashboard', 'instawp-connect' ) . '</a>'
			);
		}

		/**
		 * Save a list sent by the dashboard.
		 *
		 * Expects array( 'mode' => 'off|block|allow', 'users' => array( array( 'id' => int, 'login' => string ), ... ) ).
		 * Rejected as a whole when anything is malformed: half a list is worse than the previous one.
		 *
		 * @param mixed $payload Value received from client-app.
		 *
		 * @return true|WP_Error
		 */
		public static function save_from_dashboard( $payload ) {
			if ( ! is_array( $payload ) ) {
				return new WP_Error( 'invalid_access_control', __( 'Access control must be an object.', 'instawp-connect' ) );
			}

			$mode = isset( $payload['mode'] ) ? sanitize_key( $payload['mode'] ) : '';
			if ( ! in_array( $mode, self::get_modes(), true ) ) {
				return new WP_Error( 'invalid_access_control_mode', __( 'Access control mode must be off, block or allow.', 'instawp-connect' ) );
			}

			$users = isset( $payload['users'] ) ? $payload['users'] : array();
			if ( ! is_array( $users ) ) {
				return new WP_Error( 'invalid_access_control_users', __( 'Access control users must be a list.', 'instawp-connect' ) );
			}

			$sanitized = array();
			foreach ( $users as $user ) {
				$user_id = is_array( $user ) && isset( $user['id'] ) ? absint( $user['id'] ) : 0;
				if ( ! $user_id ) {
					return new WP_Error( 'invalid_access_control_users', __( 'Every access control user needs a numeric id.', 'instawp-connect' ) );
				}

				$sanitized[ $user_id ] = isset( $user['login'] ) ? sanitize_user( (string) $user['login'] ) : '';
			}

			$state = array(
				'mode'      => $mode,
				'users'     => $sanitized,
				'synced_at' => time(),
			);

			// Autoloaded: read on every admin request (menu, admin bar).
			update_option( self::OPTION, $state, true );

			return true;
		}

		/**
		 * Save the list carried by a heartbeat response, when client-app sent one.
		 *
		 * @param array $response Curl::do_curl() result.
		 *
		 * @return void
		 */
		public static function save_from_response( $response ) {
			$data = is_array( $response ) ? Helper::get_args_option( 'data', $response, array() ) : array();

			if ( ! is_array( $data ) || ! array_key_exists( 'access_control', $data ) ) {
				return;
			}

			$result = self::save_from_dashboard( $data['access_control'] );
			if ( is_wp_error( $result ) ) {
				Helper::add_error_log(
					array(
						'message' => 'Access control from heartbeat rejected: ' . $result->get_error_message(),
					)
				);
			}
		}

		/**
		 * What the dashboard needs to show and migrate: the cached list, and the legacy blocklist
		 * so client-app can import it as mode=block the first time it manages this site.
		 *
		 * @return array
		 */
		public static function get_report() {
			$state = self::get_state();

			$users = array();
			foreach ( $state['users'] as $user_id => $login ) {
				$users[] = array(
					'id'    => (int) $user_id,
					'login' => (string) $login,
				);
			}

			$legacy_ids = Option::get_option( self::LEGACY_OPTION );
			$legacy_ids = is_array( $legacy_ids ) ? array_filter( array_map( 'absint', $legacy_ids ) ) : array();
			$legacy     = array();

			if ( ! empty( $legacy_ids ) ) {
				foreach ( get_users( array( 'include' => $legacy_ids, 'fields' => array( 'ID', 'user_login' ) ) ) as $user ) {
					$legacy[] = array(
						'id'    => (int) $user->ID,
						'login' => (string) $user->user_login,
					);
				}
			}

			return array(
				'managed'          => self::is_managed(),
				'mode'             => $state['mode'],
				'users'            => $users,
				'synced_at'        => $state['synced_at'],
				'legacy_blocklist' => $legacy,
			);
		}

		/**
		 * Stop an AJAX request from a user who may not use Connect.
		 *
		 * @param array $error_data Error payload the caller already sends on a refused request.
		 *
		 * @return void
		 */
		public static function verify_ajax_access( $error_data = array() ) {
			if ( self::user_can_access() ) {
				return;
			}

			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::queue_denial_report( $action );

			wp_send_json_error(
				empty( $error_data )
					? array( 'message' => esc_html__( 'You are not allowed to use InstaWP Connect on this site.', 'instawp-connect' ) )
					: $error_data
			);
		}

		/**
		 * Queue a report of a refused action for the dashboard's activity log, at most once per
		 * user and action per REPORT_INTERVAL.
		 *
		 * @param string $action The refused action.
		 *
		 * @return void
		 */
		public static function queue_denial_report( $action ) {
			if ( ! function_exists( 'as_enqueue_async_action' ) || empty( instawp_get_connect_id() ) ) {
				return;
			}

			$user = wp_get_current_user();
			$key  = $user->ID . ':' . $action;
			$now  = time();

			$reported = Option::get_option( self::REPORTED_OPTION, array() );
			$reported = is_array( $reported ) ? $reported : array();
			$reported = array_filter(
				$reported,
				function ( $expire ) use ( $now ) {
					return (int) $expire > $now;
				}
			);

			if ( isset( $reported[ $key ] ) ) {
				return;
			}

			$reported[ $key ] = $now + self::REPORT_INTERVAL;
			Option::update_option( self::REPORTED_OPTION, $reported );

			as_enqueue_async_action(
				self::REPORT_HOOK,
				array(
					array(
						'user_id'     => (int) $user->ID,
						'user_login'  => (string) $user->user_login,
						'action'      => (string) $action,
						'occurred_at' => gmdate( 'c', $now ),
					),
				),
				'instawp-connect'
			);
		}

		/**
		 * Send a queued denial to the dashboard. Runs from Action Scheduler, so it never delays
		 * the refused request.
		 *
		 * @param array $report Denial details.
		 *
		 * @return void
		 */
		public function send_denial_report( $report ) {
			$connect_id = instawp_get_connect_id();
			if ( empty( $connect_id ) || ! is_array( $report ) ) {
				return;
			}

			Curl::do_curl( "connects/{$connect_id}/access-denied", $report );
		}

		/**
		 * Tell the InstaWP API which WordPress user is acting, so it can apply the same list to
		 * requests that reach it. Only requests to the InstaWP API domain, only for a logged-in user.
		 *
		 * @param array  $args HTTP request arguments.
		 * @param string $url  Request URL.
		 *
		 * @return array
		 */
		public function add_user_headers( $args, $url ) {
			if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
				return $args;
			}

			$api_domain = Helper::get_api_domain();
			if ( empty( $api_domain ) || 0 !== strpos( (string) $url, untrailingslashit( $api_domain ) . '/api/' ) ) {
				return $args;
			}

			$user = wp_get_current_user();

			$args['headers']                            = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
			$args['headers'][ self::HEADER_USER_ID ]    = (string) $user->ID;
			$args['headers'][ self::HEADER_USER_LOGIN ] = (string) $user->user_login;

			return $args;
		}
	}
}

new InstaWP_Access_Control();
