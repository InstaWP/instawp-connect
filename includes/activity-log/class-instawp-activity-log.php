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

		public function send_log_data( $critical = false ) {
			$connect_id = instawp_get_connect_id();
			if ( ! $connect_id ) {
				return;
			}

			global $wpdb;

            if ( $critical ) {
                $query = $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE severity=%s", 'critical' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            } else {
                $query = $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE severity!=%s", 'critical' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }

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

            if ( empty( $log_ids ) ) {
                return;
            }

			$success    = false;
            $api_domain = Helper::get_api_server_domain();
            $jwt        = Helper::get_jwt();

			for ( $i = 0; $i < 10; $i ++ ) {
				$response = Curl::do_curl( "connects/{$connect_id}/activity-log", array( 'activity_logs' => $logs ), array(), 'POST', null, $jwt, $api_domain );
                if ( intval( $response['code'] ) === 200 ) {
					$success = true;
					break;
				}
			}

			if ( $success ) {
				$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
				$wpdb->query(
					$wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)", $log_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
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