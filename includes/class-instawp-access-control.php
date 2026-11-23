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
		const HEADER_CONTEXT    = 'X-InstaWP-Context';

		const CONTEXT_USER   = 'user';
		const CONTEXT_SYSTEM = 'system';
		const CONTEXT_CLI    = 'cli';

		/**
		 * True only while save_from_dashboard() writes the option; every other write is refused.
		 *
		 * @var bool
		 */
		private static $writing = false;

		/**
		 * True while an Action Scheduler action runs. Its async runner is an admin-ajax loopback that
		 * carries the cookies of whoever's request dispatched it, so the "current user" there is an
		 * accident of timing, not the person the job is for.
		 *
		 * @var bool
		 */
		private static $in_background = false;

		/**
		 * True once this request has passed a Connect access check (an allowed user doing a Connect
		 * action). Only then do API calls say they act for a user: housekeeping that merely runs
		 * while someone is logged in (CDN purge on save, plugin activation) acts for the site.
		 *
		 * @var bool
		 */
		private static $acting_for_user = false;

		public function __construct() {
			add_filter( 'http_request_args', array( $this, 'add_user_headers' ), 10, 2 );
			add_filter( 'pre_update_option', array( $this, 'protect_options' ), 10, 3 );
			add_action( self::REPORT_HOOK, array( $this, 'send_denial_report' ) );
			add_action( 'action_scheduler_before_execute', array( $this, 'enter_background' ) );
			add_action( 'action_scheduler_after_execute', array( $this, 'leave_background' ) );
			add_action( 'action_scheduler_failed_execution', array( $this, 'leave_background' ) );
			add_action( 'action_scheduler_execution_ignored', array( $this, 'leave_background' ) );
			add_action( 'action_scheduler_failed_validation', array( $this, 'leave_background' ) );
		}

		/**
		 * Keep the stored list unless save_from_dashboard() is writing it, and keep core's
		 * wp-admin/options.php away from every instawp option: it writes any option an admin posts,
		 * so a refused admin could otherwise switch access off, or change the connect id the list is
		 * bound to. Nothing in this plugin saves through options.php. Names are compared lower-cased
		 * because MySQL matches option_name case-insensitively while the per-option filter does not.
		 * WP-CLI may write: `wp option delete|update` is the documented recovery.
		 *
		 * @param mixed  $value     New value.
		 * @param string $option    Option name, as the caller spelled it.
		 * @param mixed  $old_value Stored value.
		 *
		 * @return mixed
		 */
		public function protect_options( $value, $option, $old_value ) {
			if ( self::$writing || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				return $value;
			}

			$name = strtolower( trim( (string) $option ) );

			if ( self::OPTION === $name ) {
				return $old_value;
			}

			if ( isset( $GLOBALS['pagenow'] ) && 'options.php' === $GLOBALS['pagenow'] && 0 === strpos( $name, 'instawp' ) ) {
				return $old_value;
			}

			return $value;
		}

		public function enter_background() {
			self::$in_background = true;
		}

		public function leave_background() {
			self::$in_background = false;
		}

		/**
		 * Whether the dashboard has sent a list to this site.
		 *
		 * @return bool
		 */
		public static function is_managed() {
			return is_array( self::get_cached() );
		}

		/**
		 * The stored list, or false. A list saved under a DIFFERENT connect is ignored: the site was
		 * reconnected to another account (or is a staging copy of the site that owned it), and that
		 * account's dashboard never set it.
		 *
		 * @return array|false
		 */
		private static function get_cached() {
			$state = Option::get_option( self::OPTION, false );
			if ( ! is_array( $state ) ) {
				return false;
			}

			$bound   = (int) Helper::get_args_option( 'connect_id', $state, 0 );
			$current = (int) instawp_get_connect_id();

			return ( $bound && $current && $bound !== $current ) ? false : $state;
		}

		/**
		 * The cached dashboard list, normalised. Mode is 'off' when nothing was sent.
		 *
		 * @return array{mode: string, users: array<int, string>, synced_at: int}
		 */
		public static function get_state() {
			$state = self::get_cached();
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
			$listed = self::is_listed( $user_id, $state['users'] );

			if ( self::MODE_ALLOW === $state['mode'] ) {
				return $user_id > 0 && $listed;
			}

			if ( self::MODE_BLOCK === $state['mode'] ) {
				return ! $listed;
			}

			return true;
		}

		/**
		 * Whether a user is on the list. The stored login must match too, when there is one: a user
		 * ID can be handed to a new account after the listed one is deleted.
		 *
		 * @param int                $user_id User ID.
		 * @param array<int, string> $users   Stored list, id => login.
		 *
		 * @return bool
		 */
		private static function is_listed( $user_id, $users ) {
			if ( $user_id <= 0 || ! array_key_exists( $user_id, $users ) ) {
				return false;
			}

			$login = (string) $users[ $user_id ];
			if ( '' === $login ) {
				return true;
			}

			$user = get_userdata( $user_id );

			// Lower-cased: WordPress logins are unique regardless of case.
			return $user instanceof WP_User && strtolower( $user->user_login ) === strtolower( $login );
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
				$raw     = is_array( $user ) && isset( $user['id'] ) ? $user['id'] : null;
				$user_id = is_int( $raw ) || ( is_string( $raw ) && ctype_digit( $raw ) ) ? (int) $raw : 0;
				if ( $user_id <= 0 ) {
					return new WP_Error( 'invalid_access_control_users', __( 'Every access control user needs a numeric id.', 'instawp-connect' ) );
				}

				$login                 = isset( $user['login'] ) && is_scalar( $user['login'] ) ? (string) $user['login'] : '';
				$sanitized[ $user_id ] = sanitize_user( $login );
			}

			$state = array(
				'mode'       => $mode,
				'users'      => $sanitized,
				'connect_id' => (int) instawp_get_connect_id(),
				'synced_at'  => time(),
			);

			// Autoloaded: read on every admin request (menu, admin bar).
			self::$writing = true;
			try {
				update_option( self::OPTION, $state, true );
			} finally {
				self::$writing = false;
			}

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
				self::mark_acting_for_user();
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
						'user_caps'   => implode( ',', (array) $user->roles ),
						'user_ip'     => InstaWP_Activity_Log::get_visitor_ip(),
						'action'      => (string) $action,
						'occurred_at' => gmdate( 'Y-m-d H:i:s', $now ),
					),
				),
				'instawp-connect'
			);
		}

		/**
		 * Send a queued denial to the dashboard's Activity Log. Runs from Action Scheduler, so it never
		 * delays the refused request. Goes straight to the activity-log API rather than through the
		 * plugin's activity log, so it is recorded even where that feature is switched off.
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

			$row = array(
				'action'         => 'instawp_access_denied',
				'severity'       => 'high',
				'object_type'    => 'InstaWP',
				'object_subtype' => '',
				'object_name'    => (string) Helper::get_args_option( 'action', $report, '' ),
				'object_id'      => 0,
				'user_id'        => (int) Helper::get_args_option( 'user_id', $report, 0 ),
				'user_name'      => (string) Helper::get_args_option( 'user_login', $report, '' ),
				'user_caps'      => (string) Helper::get_args_option( 'user_caps', $report, '' ),
				'user_ip'        => (string) Helper::get_args_option( 'user_ip', $report, '' ),
				'timestamp'      => (string) Helper::get_args_option( 'occurred_at', $report, gmdate( 'Y-m-d H:i:s' ) ),
			);

			$logs = array(
				array(
					'action'    => $row['action'],
					'data_type' => 'instawp',
					'meta'      => array(),
					'data'      => array( $row ),
				),
			);

			Curl::do_curl( "connects/{$connect_id}/activity-log", array( 'activity_logs' => $logs ), array(), 'POST', null, Helper::get_jwt(), Helper::get_api_server_domain() );
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
			if ( ! self::is_api_url( $url ) ) {
				return $args;
			}

			$context = self::get_request_context();
			$user    = self::CONTEXT_USER === $context ? wp_get_current_user() : null;

			$args['headers']                            = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
			$args['headers'][ self::HEADER_CONTEXT ]    = $context;
			$args['headers'][ self::HEADER_USER_ID ]    = $user ? (string) $user->ID : '0';
			$args['headers'][ self::HEADER_USER_LOGIN ] = $user ? (string) $user->user_login : '';

			return $args;
		}

		/**
		 * Record that this request passed a Connect access check, so its API calls act for the user.
		 *
		 * @return void
		 */
		public static function mark_acting_for_user() {
			self::$acting_for_user = true;
		}

		/**
		 * Who this request is acting for: a user doing a Connect action they were allowed, WP-CLI, or
		 * the site itself (cron, an Action Scheduler action, a REST call from InstaWP, housekeeping
		 * that runs during someone's request, a logged-out visitor).
		 *
		 * @return string
		 */
		public static function get_request_context() {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return self::CONTEXT_CLI;
			}

			if ( ! self::$acting_for_user || self::$in_background || wp_doing_cron() || ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
				return self::CONTEXT_SYSTEM;
			}

			return self::CONTEXT_USER;
		}

		/**
		 * Whether a URL is on the InstaWP API: same scheme and host as the API domain, under /api/.
		 * Compared parsed, because a stored API domain with a trailing slash makes do_curl() build
		 * "//api/".
		 *
		 * @param string $url Request URL.
		 *
		 * @return bool
		 */
		private static function is_api_url( $url ) {
			$api_domain = Helper::get_api_domain();
			if ( empty( $api_domain ) ) {
				return false;
			}

			$api = wp_parse_url( $api_domain );
			$req = wp_parse_url( (string) $url );

			return is_array( $api ) && is_array( $req )
				&& isset( $api['host'], $req['host'], $req['path'] )
				&& strtolower( $api['host'] ) === strtolower( $req['host'] )
				&& Helper::get_args_option( 'scheme', $api, 'https' ) === Helper::get_args_option( 'scheme', $req, '' )
				&& 0 === strpos( '/' . ltrim( $req['path'], '/' ), '/api/' );
		}
	}
}

new InstaWP_Access_Control();
