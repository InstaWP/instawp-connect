<?php
/**
 * Class for heartbeat
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'InstaWP_Activity_Log' ) ) {
	class InstaWP_Activity_Log {

		/** Rows per request. */
		const BATCH_SIZE = 500;

		/** Requests per send run; anything left goes to a scheduled follow-up. */
		const MAX_BATCHES = 10;

		/** Unsent rows kept locally; older ones are dropped. */
		const MAX_PENDING_ROWS = 10000;

		/** Minimum seconds between non-critical sends, and the base of the failure backoff. */
		const SEND_THROTTLE = 15;

		/** Longest wait after repeated failures, in seconds. */
		const MAX_BACKOFF = 3600;

		/** Single option holding next_send / retry_after / failures. */
		const SEND_STATE_OPTION = 'instawp_activity_log_send_state';

		/** Option row whose existence means a send is running. Its value is "<unix time>:<uuid>", set when taken and after each batch. */
		const SEND_LOCK_OPTION = 'instawp_activity_log_send_lock';

		/** A lock not refreshed for this long belongs to a send that died. A live send refreshes it after every batch, and one batch request cannot take this long. */
		const SEND_LOCK_TTL = 900;

		/** admin-ajax action of the loopback that runs a queued send. */
		const SEND_AJAX_ACTION = 'instawp_send_activity_log';

		/** When this process last queued a full / critical-only send (0 = not yet). */
		private static $send_queued = array(
			'all'      => 0,
			'critical' => 0,
		);

		/** Value of the send lock while this request holds it, '' otherwise. */
		private $send_lock = '';

		private $table_name;

		public function __construct() {
			$this->table_name = INSTAWP_DB_TABLE_ACTIVITY_LOGS;

            add_action( 'init', array( $this, 'register_events' ) );
            add_action( 'add_option_instawp_activity_log', array( $this, 'clear_action_create_table' ), 10, 2 );
            add_action( 'update_option_instawp_activity_log', array( $this, 'clear_action_create_table' ), 10, 2 );
            add_action( 'add_option_instawp_activity_log_interval_minutes', array( $this, 'clear_action' ) );
            add_action( 'update_option_instawp_activity_log_interval_minutes', array( $this, 'clear_action' ) );
			add_action( 'instawp_handle_non_critical_logs', array( $this, 'send_log_data' ) );
			add_action( 'wp_ajax_' . self::SEND_AJAX_ACTION, array( $this, 'ajax_send_log_data' ) );
			add_action( 'wp_ajax_nopriv_' . self::SEND_AJAX_ACTION, array( $this, 'ajax_send_log_data' ) );
		}

        public function register_events() {
			if ( ! instawp()->activity_log_enabled || ! instawp_should_check_schedules() ) {
				return;
			}

            $activity_log_interval = Option::get_option( 'instawp_activity_log_interval', 'instantly' );
            $activity_log_interval = empty( $activity_log_interval ) ? 'instantly' : $activity_log_interval;

            if ( $activity_log_interval !== 'every_x_minutes' ) {
				return;
			}

            $interval = Option::get_option( 'instawp_activity_log_interval_minutes', 5 );
            $interval = empty( $interval ) ? 5 : (int) $interval;

        	if ( ! as_has_scheduled_action( 'instawp_handle_non_critical_logs', array(), 'instawp-connect' ) ) {
                as_schedule_recurring_action( time(), ( $interval * MINUTE_IN_SECONDS ), 'instawp_handle_non_critical_logs', array(), 'instawp-connect' );
            }
        }

		public function clear_action_create_table( $name, $value ) {
			instawp()->activity_log_enabled = $value === 'on';
			
			if ( $value === 'on' ) {
				$this->create_table();
			}

            $this->clear_action();
        }

        public function clear_action() {
            as_unschedule_all_actions( 'instawp_handle_non_critical_logs', array(), 'instawp-connect' );
            as_unschedule_all_actions( 'instawp_handle_non_critical_logs', array( true ), 'instawp-connect' );
        }

		/**
		 * Send queued activity logs to the API in bounded batches.
		 *
		 * Runs in its own request (the loopback queue_send() starts, or an Action Scheduler action), never
		 * inside the request that logged the event. It must never re-send the whole table: an earlier
		 * version POSTed every queued row with 10 inline retries on each event, and when the API timed out
		 * after storing the batch, the same rows were stored again on every retry (ClickUp 14ypaj0dejd,
		 * ~22M duplicate logs/day from one site).
		 *
		 * - At most BATCH_SIZE rows per request and MAX_BATCHES requests per run, oldest first.
		 * - One attempt per batch. A failure backs off exponentially instead of retrying inline.
		 * - Non-critical sends are throttled; rows left behind are picked up by a scheduled follow-up.
		 * - One send at a time per site (send lock), so two runs never send the same rows.
		 *
		 * @param bool $critical Send only critical rows, right away (skips the throttle, not the failure backoff).
		 *
		 * @return void
		 */
		public function send_log_data( $critical = false ) {
			$connect_id = instawp_get_connect_id();
			if ( ! $connect_id || ! instawp()->activity_log_enabled ) {
				return;
			}

			$state = $this->get_send_state();
			$now   = time();

			if ( $state['retry_after'] > $now || ( ! $critical && $state['next_send'] > $now ) ) {
				$this->schedule_follow_up( max( $state['retry_after'], $critical ? 0 : $state['next_send'] ), $critical );
				return;
			}

			// Another send is running. It may have read the table before this run's rows were added, so
			// come back once it is likely done rather than send the same rows twice.
			if ( ! $this->acquire_send_lock() ) {
				$this->schedule_follow_up( $now + self::SEND_THROTTLE, $critical );
				return;
			}

			try {
				$this->send_locked( $connect_id, $critical );
			} finally {
				$this->release_send_lock();
			}
		}

		/**
		 * The send itself. Only called while holding the send lock.
		 *
		 * @param int  $connect_id Connect ID.
		 * @param bool $critical   Send critical rows only.
		 *
		 * @return void
		 */
		private function send_locked( $connect_id, $critical ) {
			// Re-read under the lock: a send that just finished may have moved the throttle or backoff.
			$state = $this->get_send_state();
			$now   = time();
			if ( $state['retry_after'] > $now || ( ! $critical && $state['next_send'] > $now ) ) {
				$this->schedule_follow_up( max( $state['retry_after'], $critical ? 0 : $state['next_send'] ), $critical );
				return;
			}

			// A full send claims the throttle slot. A critical-only send does not: it is not throttled, the lock
			// already keeps runs apart, and otherwise anyone could hold back every normal send by repeatedly
			// requesting a critical send through the loopback endpoint.
			if ( ! $critical ) {
				$state['next_send'] = $now + self::SEND_THROTTLE;
				$this->save_send_state( $state );
			}

			$this->trim_pending_rows();

			global $wpdb;

			$api_domain = Helper::get_api_server_domain();
			$jwt        = Helper::get_jwt();
			$last_id    = 0;
			$has_more   = false;
			$sent       = false;

			for ( $batch = 0; $batch < self::MAX_BATCHES; $batch++ ) {
				if ( $critical ) {
					$query = $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE severity=%s AND id>%d ORDER BY id ASC LIMIT %d", 'critical', $last_id, self::BATCH_SIZE ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				} else {
					$query = $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id>%d ORDER BY id ASC LIMIT %d", $last_id, self::BATCH_SIZE ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}

				$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( empty( $results ) ) {
					$has_more = false;
					break;
				}

				$logs    = array();
				$log_ids = array();
				foreach ( $results as $result ) {
					$logs[]    = array(
						'action'    => $result->action,
						'data_type' => current( explode( '_', $result->action ) ),
						'meta'      => array(),
						'data'      => array( ( array ) $result ),
					);
					$log_ids[] = (int) $result->id;
				}

				$response = Curl::do_curl( "connects/{$connect_id}/activity-log", array( 'activity_logs' => $logs ), array(), 'POST', null, $jwt, $api_domain );
				$code     = isset( $response['code'] ) ? intval( $response['code'] ) : 0; // No 'code' key when the request itself fails.

				if ( 200 !== $code ) {
					$state['failures']    = $state['failures'] + 1;
					$state['retry_after'] = time() + min( self::MAX_BACKOFF, self::SEND_THROTTLE * pow( 2, min( $state['failures'], 10 ) ) );
					$this->save_send_state( $state );
					$this->schedule_follow_up( $state['retry_after'], $critical );
					return;
				}

				$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
				$wpdb->query(
					$wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)", $log_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				);

				$sent     = true;
				$last_id  = end( $log_ids );
				$has_more = count( $results ) === self::BATCH_SIZE;
				if ( ! $has_more ) {
					break;
				}

				// Lost the lock (taken over as stale): stop without touching the send state. The new holder may
				// send critical rows only, so leave a follow-up for the rest.
				if ( ! $this->refresh_send_lock() ) {
					$this->schedule_follow_up( time() + self::SEND_THROTTLE, $critical );
					return;
				}
			}

			// Only a send the API accepted ends the backoff. A run that found nothing to send must not, or
			// repeated requests could keep the backoff at its first step while the API is down.
			if ( $sent ) {
				$state['failures']    = 0;
				$state['retry_after'] = 0;
				$this->save_send_state( $state );
			}

			if ( $has_more ) {
				$this->schedule_follow_up( $state['next_send'], $critical );
			}
		}

		/**
		 * Take the send lock.
		 *
		 * The lock is a wp_options row inserted with INSERT IGNORE, which is atomic on the unique
		 * option_name. add_option() cannot be used: it writes with ON DUPLICATE KEY UPDATE, so two racing
		 * callers would both "add" it. A lock left by a send that died is taken over once it is older than
		 * SEND_LOCK_TTL, and only by the caller whose UPDATE still matches the value it read.
		 *
		 * @return bool Whether this request now holds the lock.
		 */
		private function acquire_send_lock() {
			global $wpdb;

			$value = time() . ':' . wp_generate_uuid4();
			$taken = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::SEND_LOCK_OPTION, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( 1 !== (int) $taken ) {
				$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::SEND_LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( null === $held || (int) $held > time() - self::SEND_LOCK_TTL ) {
					return false;
				}

				$taken = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, self::SEND_LOCK_OPTION, $held ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( 1 !== (int) $taken ) {
					return false;
				}
			}

			$this->send_lock = $value;

			return true;
		}

		/**
		 * Move the held lock's time forward, so a long send is not mistaken for a dead one.
		 *
		 * @return bool Whether this request still holds the lock.
		 */
		private function refresh_send_lock() {
			global $wpdb;

			if ( '' === $this->send_lock ) {
				return false;
			}

			$value = time() . ':' . wp_generate_uuid4();
			$moved = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, self::SEND_LOCK_OPTION, $this->send_lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( 1 !== (int) $moved ) {
				$this->send_lock = '';

				return false;
			}

			$this->send_lock = $value;

			return true;
		}

		/**
		 * Release the lock, only if this request still holds it.
		 *
		 * @return void
		 */
		private function release_send_lock() {
			global $wpdb;

			if ( '' === $this->send_lock ) {
				return;
			}

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::SEND_LOCK_OPTION, $this->send_lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->send_lock = '';
		}

		/**
		 * Schedule one follow-up send, unless a PENDING action on the hook already runs by then.
		 *
		 * Only pending actions count. as_next_scheduled_action() is not usable here: it returns true whenever
		 * any action on the hook is RUNNING, even if one is also pending, so every event throttled during a
		 * drain would queue another action and the pile could never shrink. Running actions are ignored on
		 * purpose, so a running follow-up with rows left can still queue the next one. A pending action due
		 * later (the "every X minutes" recurring one) does not count, so a drain is not stalled behind it.
		 * The check is not atomic: two callers can both schedule, leaving two pending actions for a while. They
		 * merge on the next run, since neither can schedule while the other is pending.
		 *
		 * A critical-only send is covered by any pending send; a full send only by a pending full send.
		 *
		 * @param int  $timestamp     When to run.
		 * @param bool $critical_only Send critical rows only.
		 *
		 * @return void
		 */
		private function schedule_follow_up( $timestamp, $critical_only = false ) {
			// Before Action Scheduler is ready (an event logged that early), its functions only raise a
			// "called incorrectly" notice. The loopback still sends; a later event queues the backup.
			if ( ! function_exists( 'as_get_scheduled_actions' ) || ! ActionScheduler::is_initialized() ) {
				return;
			}

			$timestamp = max( time() + self::SEND_THROTTLE, (int) $timestamp );
			$args      = $critical_only ? array( true ) : array();
			$query     = array(
				'hook'         => 'instawp_handle_non_critical_logs',
				'group'        => 'instawp-connect',
				'status'       => ActionScheduler_Store::STATUS_PENDING,
				'date'         => $timestamp,
				'date_compare' => '<=',
				'per_page'     => 1,
			);
			if ( ! $critical_only ) {
				$query['args'] = $args;
			}

			$pending = as_get_scheduled_actions( $query, 'ids' );
			if ( ! empty( $pending ) ) {
				return;
			}

			as_schedule_single_action( $timestamp, 'instawp_handle_non_critical_logs', $args, 'instawp-connect' );
		}

		/**
		 * Keep at most MAX_PENDING_ROWS unsent rows, dropping the oldest, so an unreachable API cannot grow the table without bound.
		 *
		 * @return void
		 */
		private function trim_pending_rows() {
			global $wpdb;

			$cutoff = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_name} ORDER BY id DESC LIMIT 1 OFFSET %d", self::MAX_PENDING_ROWS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( empty( $cutoff ) ) {
				return;
			}

			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id<=%d", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			Helper::add_error_log( array(
				'message' => 'Activity log backlog over limit, dropped oldest unsent rows',
				'dropped' => (int) $deleted,
				'limit'   => self::MAX_PENDING_ROWS,
			) );
		}

		/**
		 * @return array{next_send:int, retry_after:int, failures:int}
		 */
		private function get_send_state() {
			$state = Option::get_option( self::SEND_STATE_OPTION, array() );
			$state = is_array( $state ) ? $state : array();

			return array(
				'next_send'   => isset( $state['next_send'] ) ? (int) $state['next_send'] : 0,
				'retry_after' => isset( $state['retry_after'] ) ? (int) $state['retry_after'] : 0,
				'failures'    => isset( $state['failures'] ) ? (int) $state['failures'] : 0,
			);
		}

		/**
		 * @param array $state Send state from get_send_state().
		 *
		 * @return void
		 */
		private function save_send_state( array $state ) {
			update_option( self::SEND_STATE_OPTION, $state, false );
		}

		public function create_table() {
			global $wpdb;

			if ( ! function_exists( 'maybe_create_table' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			$charset_collate = $wpdb->get_charset_collate();
			$sql_query       = "CREATE TABLE " . $this->table_name . " (
				id int(20) NOT NULL AUTO_INCREMENT,
				action varchar(255) NOT NULL,
				severity varchar(20) NOT NULL DEFAULT 'low',
				object_type varchar(255) NOT NULL,
				object_subtype varchar(255) NOT NULL DEFAULT '',
				object_id varchar(50) NOT NULL DEFAULT '0',
				object_name varchar(50) NOT NULL,
				user_id int(50) NOT NULL DEFAULT '0',
				user_name varchar(255) NOT NULL DEFAULT '',
				user_caps varchar(70) NOT NULL DEFAULT 'guest',
				user_ip varchar(55) NOT NULL DEFAULT '127.0.0.1',
				timestamp datetime NOT NULL,
				PRIMARY KEY (id)
	        ) $charset_collate;";

			maybe_create_table( $this->table_name, $sql_query );
		}

		/**
		 * @param array $args
		 * @return void
		 */
		private function insert( array $args ) {
			global $wpdb;

			$args = wp_parse_args( $args, array(
				'action'         => '',
				'object_type'    => '',
				'object_subtype' => '',
				'object_name'    => '',
				'object_id'      => '',
				'user_ip'        => $this->get_ip_address(),
				'timestamp'      => current_time( 'mysql', 1 ),
			) );

			$args['severity']    = $this->get_severity( $args['action'] );
			$args                = $this->setup_userdata( $args );
			$args['object_name'] = $this->prepare_object_name( $args['object_name'] );

			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) === $this->table_name ) {
				$wpdb->insert(
					$this->table_name,
					array(
						'action'         => $args['action'],
						'severity'       => $args['severity'],
						'object_type'    => $args['object_type'],
						'object_subtype' => $args['object_subtype'],
						'object_name'    => $args['object_name'],
						'object_id'      => $args['object_id'],
						'user_id'        => $args['user_id'],
						'user_name'      => $args['user_name'],
						'user_caps'      => $args['user_caps'],
						'user_ip'        => $args['user_ip'],
						'timestamp'      => $args['timestamp'],
					),
					array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
				);
			}

            $activity_log_interval = Option::get_option( 'instawp_activity_log_interval', 'instantly' );
            $activity_log_interval = empty( $activity_log_interval ) ? 'instantly' : $activity_log_interval;

			// "Instantly" sends everything; "every X minutes" leaves non-critical rows to the recurring send.
			if ( 'instantly' === $activity_log_interval ) {
				$this->queue_send( false );
			} elseif ( 'critical' === $args['severity'] ) {
				$this->queue_send( true );
			}
		}

		/**
		 * Get the rows sent soon, without making the current request wait for the API.
		 *
		 * Sending used to happen right here, inside whatever request logged the event: a visitor's failed
		 * login waited on the API, and emptying a trash of 300 posts made 300 requests one after another.
		 * Now the request only queues a send: once per request, a pending Action Scheduler action is the
		 * backup and a non-blocking request to this site runs the send in its own process (the same
		 * fire-and-forget request WordPress uses to spawn WP-Cron). If that request never arrives (loopback
		 * blocked, password-protected site, ...), the backup action runs on the next queue run instead.
		 *
		 * @param bool $critical_only Send critical rows only.
		 *
		 * @return void
		 */
		private function queue_send( $critical_only ) {
			// This request deactivates the plugin, so no loopback or queued action will run afterwards (and a
			// queued action would be left behind). Send now, inside the request, as before.
			if ( defined( 'INSTAWP_DEACTIVATING' ) ) {
				$this->send_log_data( $critical_only );

				return;
			}

			// At most once per SEND_THROTTLE per process, so a bulk action makes one send instead of hundreds
			// while a long-running process (WP-CLI, cron) still queues a send for rows it logs later.
			// A full send also covers critical rows.
			$now    = time();
			$queued = max( self::$send_queued['all'], $critical_only ? self::$send_queued['critical'] : 0 );
			if ( $queued && $now - $queued < self::SEND_THROTTLE ) {
				return;
			}
			self::$send_queued[ $critical_only ? 'critical' : 'all' ] = $now;

			if ( ! instawp_get_connect_id() ) {
				return;
			}

			$state   = $this->get_send_state();
			$send_at = max( $now, $state['retry_after'], $critical_only ? 0 : $state['next_send'] );

			$this->schedule_follow_up( $send_at, $critical_only );

			// Throttled or backing off: the pending action sends once the wait is over.
			if ( $send_at > $now ) {
				return;
			}

			wp_remote_post(
				admin_url( 'admin-ajax.php' ),
				array(
					'timeout'   => 0.01,
					'blocking'  => false,
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
					'body'      => array(
						'action'   => self::SEND_AJAX_ACTION,
						'critical' => $critical_only ? 1 : 0,
					),
				)
			);
		}

		/**
		 * Loopback started by queue_send(). Takes no input beyond "critical rows only", and only ever
		 * runs this site's own throttled, locked send, so it needs no authentication.
		 *
		 * @return void
		 */
		public function ajax_send_log_data() {
			ignore_user_abort( true );

			$this->send_log_data( ! empty( $_POST['critical'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			wp_die( '', '', array( 'response' => 200 ) );
		}

		private function setup_userdata( array $args ) {
			$user = function_exists( 'get_user_by' ) ? get_user_by( 'id', get_current_user_id() ) : false;

			if ( $user ) {
				$args['user_caps'] = $this->get_user_role_label( $user );
				$args['user_name'] = ! empty( $user->user_login ) ? $user->user_login : $user->display_name;
				if ( empty( $args['user_id'] ) ) {
					$args['user_id'] = $user->ID;
				}
			} else {
				$args['user_caps'] = 'guest';
				$args['user_name'] = '';
				if ( empty( $args['user_id'] ) ) {
					$args['user_id'] = 0;
				}
			}

			return $args;
		}

		/**
		 * Label the acting user by their WordPress role.
		 *
		 * The first non-bbPress role wins, so an administrator who also holds a forum role is still
		 * "administrator", and a forum-only user is recorded as their forum role, no longer as an
		 * administrator. A user with no role on this site is "administrator" when is_super_admin()
		 * is true (a multisite super admin, or on a single site a user holding delete_users
		 * directly), otherwise "guest", the label already used for logged-out actors.
		 *
		 * @param WP_User $user Acting user.
		 *
		 * @return string
		 */
		private function get_user_role_label( $user ) {
			$roles = array_values( (array) $user->roles );

			foreach ( $roles as $role ) {
				if ( 0 !== strpos( $role, 'bbp_' ) ) {
					return strtolower( $role );
				}
			}

			if ( ! empty( $roles ) ) {
				return strtolower( $roles[0] );
			}

			return is_super_admin( $user->ID ) ? 'administrator' : 'guest';
		}

		/**
		 * Store the object name as plain text that fits its column.
		 *
		 * object_name is varchar(50) and wpdb rejects the whole row, rather than truncating, when a
		 * value is longer, so an event about a long post title or display name was silently lost.
		 * It is stored unescaped (the dashboard escapes on output). Entities are decoded BEFORE tags
		 * are stripped, so an entity-encoded tag in a title cannot come back as markup in the
		 * alert emails this value is copied into.
		 *
		 * @param mixed $name Raw object name.
		 *
		 * @return string
		 */
		private function prepare_object_name( $name ) {
			$name  = wp_strip_all_tags( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );
			$clean = preg_replace( '/\s+/u', ' ', $name ); // null on invalid UTF-8.
			$name  = trim( null === $clean ? $name : $clean );

			return mb_substr( $name, 0, 50, 'UTF-8' );
		}

		private function get_severity( $action ) {
			$severity_list = $this->event_severity();
			$severity      = 'low';

			foreach ( array_keys( $severity_list ) as $severity_item ) {
				if ( in_array( $action, $severity_list[ $severity_item ], true ) ) {
					$severity = $severity_item;
					break;
				}
			}

			return $severity;
		}

		private function event_severity() {
			return array(
				'low'      => array(
					'post_updated',
					'attachment_uploaded',
					'menu_created',
					'menu_updated',
					'user_logged_in',
					'user_logged_out',
					'term_created',
					'term_updated',
					'theme_installed',
					'theme_updated',
					'widget_updated',
				),
				'medium'   => array(
					'post_restored',
					'attachment_updated',
					'user_updated',
					'theme_updated',
					'widget_deleted',
					'plugin_activated',
					'plugin_deactivated',
				),
				'high'     => array(
					'post_created',
					'post_trashed',
					'attachment_deleted',
					'user_registered',
					'user_failed_login',
					'menu_deleted',
					'term_deleted',
					'theme_file_updated',
					'theme_activated',
					'plugin_installed',
					'plugin_updated',
					'plugin_file_updated',
					'core_updated_minor',
				),
				'critical' => array(
					'post_deleted',
					'user_deleted',
					'theme_deleted',
					'plugin_deleted',
					'core_updated_major',
				),
			);
		}

		private function get_ip_address() {
			$header_key = Option::get_option( 'instawp_log_visitor_ip_source' );

			if ( empty( $header_key ) ) {
				$header_key = 'no-collect-ip';
			}

			if ( 'no-collect-ip' === $header_key ) {
				return '';
			}

			$visitor_ip_address = '';
			if ( ! empty( $_SERVER[ $header_key ] ) ) {
				$visitor_ip_address = $_SERVER[ $header_key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			$remote_address = apply_filters( 'instawp/filters/get_ip_address', $visitor_ip_address );

			if ( ! empty( $remote_address ) && filter_var( $remote_address, FILTER_VALIDATE_IP ) ) {
				return $remote_address;
			}

			return '127.0.0.1';
		}

		public static function insert_log( $args ) {
			$class = new self();

			$class->insert( $args );
		}
	}
}

new InstaWP_Activity_Log();