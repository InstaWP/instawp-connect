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

		private $table_name;

		public function __construct() {
			$this->table_name = INSTAWP_DB_TABLE_ACTIVITY_LOGS;

            add_action( 'init', array( $this, 'register_events' ) );
            add_action( 'add_option_instawp_activity_log', array( $this, 'clear_action_create_table' ), 10, 2 );
            add_action( 'update_option_instawp_activity_log', array( $this, 'clear_action_create_table' ), 10, 2 );
            add_action( 'add_option_instawp_activity_log_interval_minutes', array( $this, 'clear_action' ) );
            add_action( 'update_option_instawp_activity_log_interval_minutes', array( $this, 'clear_action' ) );
			add_action( 'instawp_handle_non_critical_logs', array( $this, 'send_log_data' ) );
		}

        public function register_events() {
			if ( ! instawp()->activity_log_enabled ) {
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
        }

		/**
		 * Send queued activity logs to the API in bounded batches.
		 *
		 * Every logged event calls this in "instantly" mode, so it must stay cheap and must never
		 * re-send the whole table: an earlier version POSTed every queued row with 10 inline retries
		 * on each event, and when the API timed out after storing the batch, the same rows were
		 * stored again on every retry (ClickUp 14ypaj0dejd, ~22M duplicate logs/day from one site).
		 *
		 * - At most BATCH_SIZE rows per request and MAX_BATCHES requests per run, oldest first.
		 * - One attempt per batch. A failure backs off exponentially instead of retrying inline.
		 * - Non-critical sends are throttled; rows left behind are picked up by a scheduled follow-up.
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
				$this->schedule_follow_up( max( $state['retry_after'], $state['next_send'] ) );
				return;
			}

			// Claim the slot before the first request so concurrent events skip instead of sending the same rows.
			$state['next_send'] = $now + self::SEND_THROTTLE;
			$this->save_send_state( $state );

			$this->trim_pending_rows();

			global $wpdb;

			$api_domain = Helper::get_api_server_domain();
			$jwt        = Helper::get_jwt();
			$last_id    = 0;
			$has_more   = false;

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
					$this->schedule_follow_up( $state['retry_after'] );
					return;
				}

				$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
				$wpdb->query(
					$wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)", $log_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				);

				$last_id  = end( $log_ids );
				$has_more = count( $results ) === self::BATCH_SIZE;
				if ( ! $has_more ) {
					break;
				}
			}

			$state['failures']    = 0;
			$state['retry_after'] = 0;
			$this->save_send_state( $state );

			if ( $has_more ) {
				$this->schedule_follow_up( $state['next_send'] );
			}
		}

		/**
		 * Schedule one follow-up send, unless one (or the "every X minutes" recurring action) is already pending.
		 *
		 * as_next_scheduled_action() returns an int only for a pending action; it returns true while the action
		 * is running, and a running follow-up that still has rows left must be able to queue the next one.
		 *
		 * @param int $timestamp When to run.
		 *
		 * @return void
		 */
		private function schedule_follow_up( $timestamp ) {
			if ( ! function_exists( 'as_next_scheduled_action' ) || is_int( as_next_scheduled_action( 'instawp_handle_non_critical_logs', array(), 'instawp-connect' ) ) ) {
				return;
			}

			as_schedule_single_action( max( time() + self::SEND_THROTTLE, (int) $timestamp ), 'instawp_handle_non_critical_logs', array(), 'instawp-connect' );
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

			$args['severity'] = $this->get_severity( $args['action'] );
			$args             = $this->setup_userdata( $args );

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

            if ( 'critical' === $args['severity'] ) {
                $this->send_log_data( true );
            }

            if ( 'critical' !== $args['severity'] && 'instantly' === $activity_log_interval ) {
                $this->send_log_data();
            }
		}

		private function setup_userdata( array $args ) {
			$user = function_exists( 'get_user_by' ) ? get_user_by( 'id', get_current_user_id() ) : false;

			if ( $user ) {
				$args['user_caps'] = strtolower( key( $user->caps ) );
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

			if ( empty( $args['user_caps'] ) || 'bbp_participant' === $args['user_caps'] ) {
				$args['user_caps'] = 'administrator';
			}

			return $args;
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