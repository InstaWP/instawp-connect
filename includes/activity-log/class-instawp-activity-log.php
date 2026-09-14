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
		 * How many pending rows one send may carry.
		 *
		 * The payload is built in memory from every row the SELECT returns, so an unbounded query is
		 * what makes a backlog fatal: the bigger the table, the bigger the payload, the sooner the run
		 * dies of memory exhaustion -- and a run that dies deletes nothing, so the table is larger
		 * still by the next attempt. Bounding the batch bounds the memory whatever the table size.
		 */
		const BATCH_SIZE = 500;

		/**
		 * Send attempts, and the wall-clock budget they share.
		 *
		 * Curl::do_curl() waits up to 60s (80s where max_execution_time allows) for one request, so a
		 * plain "retry N times" loop blocks for N * 60s inside a single request -- past the FPM
		 * request_terminate_timeout that then kills it. The budget is checked BEFORE each retry, so a
		 * slow failure (a timeout) buys no second attempt while a fast one (connection refused, an
		 * immediate 5xx) still does. Whatever is left unsent stays in the table for the next run.
		 */
		const MAX_ATTEMPTS = 3;
		const RETRY_BUDGET = 30;

		/**
		 * Retention bounds, applied whether or not sending works.
		 *
		 * Rows are deleted only after the API accepts them, which is correct while the API is
		 * reachable and unbounded when it is not: a site that has been failing for weeks accumulates
		 * rows it can never send and has no way back on its own. These caps give it one.
		 *
		 * The row cap discards rows that were never delivered, so it is deliberately NOT applied
		 * while sending is working -- a busy site whose backlog is merely draining slowly must not
		 * lose logs it is about to send. Only the age cap applies unconditionally: a row nobody has
		 * managed to send in RETENTION_DAYS is dead weight either way.
		 */
		const RETENTION_ROWS = 10000;
		const RETENTION_DAYS = 30;

		/**
		 * Guards the retention sweep so it costs one option read per hour, not one per event.
		 */
		const RETENTION_TRANSIENT = 'instawp_activity_log_retention';

		/**
		 * Set for this long after every accepted send; its absence is what "sending is broken" means.
		 */
		const SEND_OK_TRANSIENT = 'instawp_activity_log_send_ok';
		const SEND_OK_TTL       = 2 * HOUR_IN_SECONDS;

		public function send_log_data( $critical = false ) {
			$this->enforce_retention();

			$connect_id = instawp_get_connect_id();
			if ( ! $connect_id ) {
				return;
			}

			global $wpdb;

			$operator = $critical ? '=' : '!=';
			$query    = $wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE severity {$operator} %s ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'critical',
				self::BATCH_SIZE
			);

			$log_ids = $logs = array();
			$results = $wpdb->get_results( $query );

			foreach ( $results as $result ) {
				$logs[] = array(
                    'action'    => $result->action,
					'data_type' => current( explode( '_', $result->action ) ),
					'meta'      => array(),
					'data'      => array( ( array ) $result ),
				);
				$log_ids[] = $result->id;
			}

			unset( $results );

            if ( empty( $log_ids ) ) {
                return;
            }

			$success    = false;
            $api_domain = Helper::get_api_server_domain();
            $jwt        = Helper::get_jwt();
			$deadline   = microtime( true ) + self::RETRY_BUDGET;

			for ( $attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt ++ ) {
				if ( $attempt > 0 ) {
					// No budget left to absorb another request of unknown length.
					if ( microtime( true ) >= $deadline ) {
						break;
					}
					sleep( $attempt );
				}

				$response = Curl::do_curl( "connects/{$connect_id}/activity-log", array( 'activity_logs' => $logs ), array(), 'POST', null, $jwt, $api_domain );

				// do_curl() omits 'code' entirely when it never reached the server -- a WP_Error, or
				// its own empty api-key / api-domain guards -- so this is not an HTTP status of 0.
				$code = isset( $response['code'] ) ? intval( $response['code'] ) : 0;

                if ( 200 === $code ) {
					$success = true;
					break;
				}

				if ( ! $this->is_retryable_code( $code ) ) {
					break;
				}
			}

			if ( $success ) {
				set_transient( self::SEND_OK_TRANSIENT, 1, self::SEND_OK_TTL );

				$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
				$wpdb->query(
					$wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)", $log_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				);
			}
		}

		/**
		 * Is another attempt at this response worth the wall clock?
		 *
		 * A 4xx is the server answering and refusing, and it will refuse the same payload again --
		 * the exception being 408/429, which are explicitly "come back". Anything else (a 5xx, or a
		 * request that never arrived) may well work on the next try.
		 *
		 * @param int $code
		 * @return bool
		 */
		private function is_retryable_code( $code ) {
			if ( 408 === $code || 429 === $code ) {
				return true;
			}

			return ! ( $code >= 400 && $code < 500 );
		}

		/**
		 * Keep the pending-log table bounded, regardless of whether sending is working.
		 *
		 * Runs at most hourly: send_log_data() is called inline from insert() on every event when the
		 * interval is "instantly", and this must not add queries to that path.
		 *
		 * @return void
		 */
		private function enforce_retention() {
			if ( get_transient( self::RETENTION_TRANSIENT ) ) {
				return;
			}

			set_transient( self::RETENTION_TRANSIENT, 1, HOUR_IN_SECONDS );

			global $wpdb;

			// esc_like: the table prefix makes "_" a LIKE wildcard, and get_var would then return
			// whichever near-miss table sorted first rather than ours.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table_name ) ) ) !== $this->table_name ) {
				return;
			}

			$max_rows = (int) apply_filters( 'instawp/filters/activity_log_retention_rows', self::RETENTION_ROWS );
			$max_days = (int) apply_filters( 'instawp/filters/activity_log_retention_days', self::RETENTION_DAYS );

			// Only trim undelivered rows once sending has actually stopped working; see the constant.
			if ( $max_rows > 0 && ! get_transient( self::SEND_OK_TRANSIENT ) ) {
				// The id of the newest row that is already past the cap; everything at or below it is
				// older still. Walking the primary key like this keeps the trim off a table scan.
				$cutoff_id = $wpdb->get_var(
					$wpdb->prepare( "SELECT id FROM {$this->table_name} ORDER BY id DESC LIMIT 1 OFFSET %d", $max_rows ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				);

				if ( ! empty( $cutoff_id ) ) {
					$wpdb->query(
						$wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id <= %d", $cutoff_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					);
				}
			}

			if ( $max_days > 0 ) {
				// timestamp is written as current_time( 'mysql', 1 ), i.e. UTC -- so compare in UTC.
				$wpdb->query(
					$wpdb->prepare( "DELETE FROM {$this->table_name} WHERE timestamp < %s", gmdate( 'Y-m-d H:i:s', time() - ( $max_days * DAY_IN_SECONDS ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				);
			}
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