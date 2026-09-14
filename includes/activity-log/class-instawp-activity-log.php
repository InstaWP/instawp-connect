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
		 * Send attempts, and how quickly a failure has to arrive to be worth retrying.
		 *
		 * Curl::do_curl() waits 60s, 80s, 110s or 290s for a single request depending on the site's
		 * max_execution_time (see connect-helpers Curl.php), so a plain "retry N times" loop blocks
		 * for N times that inside one request -- the old loop of 10 could sit there for 600s and more
		 * on a pool whose request_terminate_timeout is 90.
		 *
		 * A retry is therefore only taken when the loop SO FAR has been fast. A connection refused or
		 * an immediate 5xx is worth another go; an attempt that failed by timing out means the
		 * endpoint is struggling, and the recurring action five minutes from now is the better retry
		 * than a second timeout stacked onto this request.
		 *
		 * Worst case becomes one request timeout plus a few fast attempts, rather than ten timeouts.
		 * The single-request timeout itself lives in connect-helpers and is out of scope here.
		 */
		const MAX_ATTEMPTS      = 3;
		const RETRY_MAX_ELAPSED = 5;

		/**
		 * Retention bounds, so a site whose sync has been broken for weeks can recover on its own.
		 *
		 * These DELETE rows that were never delivered, which is why they are hedged three ways:
		 *
		 * - They apply only once sending has been failing CONTINUOUSLY for RETENTION_GRACE, measured
		 *   from a positive first-failure stamp. The absence of a success marker is deliberately not
		 *   the signal: a site that has only just been upgraded has never recorded a success either,
		 *   and trimming on that would destroy the very backlog this exists to drain.
		 * - The sweep runs AFTER the send attempt, never before, so the stamp it reads is current.
		 * - `critical` rows -- a post, user, theme or plugin DELETED, or a major core update -- are
		 *   held apart under a far larger cap and are never aged out. They are the part of an audit
		 *   trail worth keeping, and must not be evictable by ordinary post_updated volume.
		 *
		 * The resulting rule is one sentence: nothing is deleted except after successful delivery,
		 * unless the sync has been failing without let-up for a day.
		 */
		const RETENTION_ROWS          = 10000;
		const RETENTION_ROWS_CRITICAL = 100000;
		const RETENTION_DAYS          = 30;
		const RETENTION_GRACE         = DAY_IN_SECONDS;

		/**
		 * Caps the retention sweep at one pass per hour.
		 *
		 * Note this does NOT make the surrounding reads hourly -- send_log_data() is called inline
		 * from insert() on every event when the interval is "instantly", so the transient read
		 * happens that often too. Only the sweep itself is rate limited.
		 */
		const RETENTION_TRANSIENT = 'instawp_activity_log_retention';

		/**
		 * Unix time of the first failure of the current streak; absent whenever sending last worked.
		 *
		 * An option rather than a transient: it has to survive an object-cache flush, because losing
		 * it restarts the grace period and delays a stuck site's recovery.
		 */
		const FAILING_SINCE_OPTION = 'instawp_activity_log_failing_since';

		public function send_log_data( $critical = false ) {
			$this->send_pending_logs( $critical );
			$this->enforce_retention();
		}

		/**
		 * Send one bounded batch of pending rows, and delete them if the API took them.
		 *
		 * @param bool $critical Send the critical rows rather than everything else.
		 * @return void
		 */
		private function send_pending_logs( $critical ) {
			$connect_id = instawp_get_connect_id();
			if ( ! $connect_id ) {
				// Not connected is not a passing hiccup: nothing can be delivered until somebody
				// reconnects the site, and the table grows for the whole of that time.
				$this->mark_send_failed();
				return;
			}

			global $wpdb;

			$operator = $critical ? '=' : '!=';
			$query    = $wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE severity {$operator} %s ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'critical',
				$this->batch_size()
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
				// Nothing pending says nothing about whether sending works, so leave the stamp alone.
				return;
			}

			$success    = false;
            $api_domain = Helper::get_api_server_domain();
            $jwt        = Helper::get_jwt();
			$started    = microtime( true );

			for ( $attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt ++ ) {
				if ( $attempt > 0 ) {
					// Only a fast failure earns a retry -- see RETRY_MAX_ELAPSED.
					if ( ( microtime( true ) - $started ) >= self::RETRY_MAX_ELAPSED ) {
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

			if ( ! $success ) {
				$this->mark_send_failed();
				return;
			}

			$this->mark_send_ok();

			$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)", $log_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			);
		}

		/**
		 * @return int Rows per send, never below one.
		 */
		private function batch_size() {
			$size = (int) apply_filters( 'instawp/filters/activity_log_batch_size', self::BATCH_SIZE );

			return $size > 0 ? $size : self::BATCH_SIZE;
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
		 * @return void
		 */
		private function mark_send_ok() {
			if ( get_option( self::FAILING_SINCE_OPTION ) ) {
				delete_option( self::FAILING_SINCE_OPTION );
			}
		}

		/**
		 * @return void
		 */
		private function mark_send_failed() {
			// add_option() does not overwrite, so the stamp keeps recording the FIRST failure of the
			// current streak rather than sliding forward to the most recent one.
			add_option( self::FAILING_SINCE_OPTION, time(), '', 'no' );
		}

		/**
		 * Trim the pending-log table, but only once the sync has been broken long enough to mean it.
		 *
		 * @return void
		 */
		private function enforce_retention() {
			if ( get_transient( self::RETENTION_TRANSIENT ) ) {
				return;
			}

			$failing_since = (int) get_option( self::FAILING_SINCE_OPTION );
			$grace         = (int) apply_filters( 'instawp/filters/activity_log_retention_grace', self::RETENTION_GRACE );

			// Healthy, or failing only briefly: nothing here is allowed to delete an undelivered row.
			if ( $failing_since <= 0 || ( time() - $failing_since ) < $grace ) {
				return;
			}

			set_transient( self::RETENTION_TRANSIENT, 1, HOUR_IN_SECONDS );

			global $wpdb;

			// esc_like: the table prefix makes "_" a LIKE wildcard, and get_var would then return
			// whichever near-miss table sorted first rather than ours.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->table_name ) ) ) !== $this->table_name ) {
				return;
			}

			$this->trim_to_row_cap( false, (int) apply_filters( 'instawp/filters/activity_log_retention_rows', self::RETENTION_ROWS ) );
			$this->trim_to_row_cap( true, (int) apply_filters( 'instawp/filters/activity_log_retention_rows_critical', self::RETENTION_ROWS_CRITICAL ) );

			$max_days = (int) apply_filters( 'instawp/filters/activity_log_retention_days', self::RETENTION_DAYS );

			if ( $max_days > 0 ) {
				// Non-critical only, and in UTC: timestamp is written as current_time( 'mysql', 1 ).
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$this->table_name} WHERE severity != %s AND timestamp < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						'critical',
						gmdate( 'Y-m-d H:i:s', time() - ( $max_days * DAY_IN_SECONDS ) )
					)
				);
			}
		}

		/**
		 * Keep at most $max_rows of one severity class, dropping the oldest.
		 *
		 * The two classes are capped separately and never compete: a burst of post_updated events
		 * must not be able to evict a plugin_deleted that has not been delivered yet.
		 *
		 * @param bool $critical Cap the critical rows rather than everything else.
		 * @param int  $max_rows Zero or less disables this cap entirely.
		 * @return void
		 */
		private function trim_to_row_cap( $critical, $max_rows ) {
			if ( $max_rows <= 0 ) {
				return;
			}

			global $wpdb;

			$operator = $critical ? '=' : '!=';

			// The id of the newest row of this class that is already past the cap; everything at or
			// below it is older still. Walking the primary key keeps the trim off a table scan.
			$cutoff_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$this->table_name} WHERE severity {$operator} %s ORDER BY id DESC LIMIT 1 OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'critical',
					$max_rows
				)
			);

			if ( null === $cutoff_id ) {
				return;
			}

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$this->table_name} WHERE severity {$operator} %s AND id <= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'critical',
					$cutoff_id
				)
			);
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