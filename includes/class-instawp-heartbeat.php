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
			
			// Add admin test hooks for testing
			if ( is_admin() && ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
				add_action( 'admin_init', array( $this, 'handle_admin_test_requests' ) );
			}
		}

		/**
		 * Handle admin test requests for heartbeat retry testing
		 */
		public function handle_admin_test_requests() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Handle test_retry_delay request
			if ( isset( $_GET['test_retry_delay'] ) && isset( $_GET['count'] ) ) {
				$count = intval( $_GET['count'] );
				$result = self::test_retry_delay( $count, true );
				
				wp_die(
					'<h1>Heartbeat Retry Delay Test</h1>' .
					'<div style="padding: 20px; background: #f0f0f0; margin: 20px 0;">' .
					'<h2>Test Results for ' . $count . ' Failure(s)</h2>' .
					'<p><strong>Failed Count:</strong> ' . esc_html( $result['failed_count'] ) . '</p>' .
					'<p><strong>Retry Delay:</strong> ' . esc_html( $result['formatted_delay'] ) . ' (' . esc_html( $result['retry_delay'] ) . ' seconds)</p>' .
					'<p><strong>Next Attempt:</strong> ' . esc_html( $result['next_attempt'] ) . '</p>' .
					'<p><strong>Action Scheduled:</strong> ' . ( $result['action_scheduled'] ? 'Yes' : 'No' ) . '</p>' .
					'<hr>' .
					'<p><strong>✅ Action has been scheduled!</strong> Check <a href="' . admin_url( 'tools.php?page=action-scheduler&status=pending&hook=instawp_handle_heartbeat&group=instawp-connect' ) . '">Tools → Scheduled Actions</a> to verify.</p>' .
					'<p><a href="' . admin_url() . '">← Back to Admin</a></p>' .
					'</div>',
					'Heartbeat Test',
					array( 'back_link' => true )
				);
			}

			// Handle reset_heartbeat_test request
			if ( isset( $_GET['reset_heartbeat_test'] ) ) {
				self::reset_test_state();
				wp_die(
					'<h1>Heartbeat Test Reset</h1>' .
					'<div style="padding: 20px; background: #d4edda; margin: 20px 0;">' .
					'<p><strong>✅ Success!</strong> Heartbeat test state has been reset.</p>' .
					'<p>All failed counts, last attempt timestamps, and scheduled actions have been cleared.</p>' .
					'<p><a href="' . admin_url() . '">← Back to Admin</a></p>' .
					'</div>',
					'Heartbeat Test Reset',
					array( 'back_link' => true )
				);
			}

			// Handle check_heartbeat_state request
			if ( isset( $_GET['check_heartbeat_state'] ) ) {
				$state = self::get_heartbeat_state();
				wp_die(
					'<h1>Current Heartbeat State</h1>' .
					'<div style="padding: 20px; background: #f0f0f0; margin: 20px 0;">' .
					'<pre style="background: white; padding: 15px; overflow: auto;">' . esc_html( print_r( $state, true ) ) . '</pre>' .
					'<p><a href="' . admin_url() . '">← Back to Admin</a></p>' .
					'</div>',
					'Heartbeat State',
					array( 'back_link' => true )
				);
			}
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
		// Check if we should skip this heartbeat due to recent failure (minimum 60 minutes)
		$last_attempt = Option::get_option( 'instawp_heartbeat_last_attempt', 0 );
		$min_retry_delay = HOUR_IN_SECONDS; // 60 minutes minimum
		
		if ( $last_attempt > 0 && ( time() - $last_attempt ) < $min_retry_delay ) {
			// Too soon to retry, reschedule for later
			$failed_count = Option::get_option( 'instawp_heartbeat_failed', 0 );
			$retry_delay = $this->calculate_retry_delay( $failed_count );
			$next_attempt = $last_attempt + $retry_delay;
			
			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( sprintf( 
					'[HEARTBEAT RETRY] Too soon to retry. Last attempt: %s, Failed count: %d, Retry delay: %d seconds (%s), Next attempt: %s', 
					date( 'Y-m-d H:i:s', $last_attempt ),
					$failed_count,
					$retry_delay,
					$this->format_delay( $retry_delay ),
					date( 'Y-m-d H:i:s', $next_attempt )
				) );
			}
			
			// Only reschedule if we're not already past the next attempt time
			if ( $next_attempt > time() ) {
				as_unschedule_all_actions( 'instawp_handle_heartbeat', array(), 'instawp-connect' );
				as_schedule_single_action( $next_attempt, 'instawp_handle_heartbeat', array(), 'instawp-connect' );
			}
			return;
		}
		
		// Update last attempt time
		Option::update_option( 'instawp_heartbeat_last_attempt', time() );
		
		$heartbeat_response = self::send_heartbeat();

		if ( $heartbeat_response['success'] ) {
			// Success: reset everything and restore normal schedule
			$failed_count = Option::get_option( 'instawp_heartbeat_failed', 0 );
			
			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( sprintf( 
					'[HEARTBEAT SUCCESS] Heartbeat succeeded. Resetting failed count (was: %d) and restoring normal schedule.', 
					$failed_count
				) );
			}
			
			Option::delete_option( 'instawp_heartbeat_failed' );
			Option::delete_option( 'instawp_heartbeat_last_attempt' );
			
			// Reschedule recurring action with normal interval
			as_unschedule_all_actions( 'instawp_handle_heartbeat', array(), 'instawp-connect' );
			$interval = Option::get_option( 'instawp_api_heartbeat', 240 );
			$interval = empty( $interval ) ? 240 : (int) $interval;
			as_schedule_recurring_action( time(), ( $interval * MINUTE_IN_SECONDS ), 'instawp_handle_heartbeat', array(), 'instawp-connect' );
		} else {
			// Failure: implement exponential backoff
			$failed_count = Option::get_option( 'instawp_heartbeat_failed', 0 );
			$failed_count = $failed_count ? $failed_count : 0;
			++$failed_count;
			
			Option::update_option( 'instawp_heartbeat_failed', $failed_count );
			
			// Calculate retry delay with exponential backoff
			$retry_delay = $this->calculate_retry_delay( $failed_count );
			$next_attempt = time() + $retry_delay;
			
			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( sprintf( 
					'[HEARTBEAT FAILURE] Attempt failed. Failed count: %d, Retry delay: %d seconds (%s), Next attempt scheduled: %s', 
					$failed_count,
					$retry_delay,
					$this->format_delay( $retry_delay ),
					date( 'Y-m-d H:i:s', $next_attempt )
				) );
			}
			
			// Unschedule recurring action and schedule single retry
			as_unschedule_all_actions( 'instawp_handle_heartbeat', array(), 'instawp-connect' );
			as_schedule_single_action( $next_attempt, 'instawp_handle_heartbeat', array(), 'instawp-connect' );
			
			if ( $failed_count > 10 ) {
				Option::update_option( 'instawp_rm_heartbeat', 'off' );

				if ( intval( $heartbeat_response['response_code'] ) === 404 ) {
					instawp_reset_running_migration( 'hard' );
				}
			}
		}
	}

	/**
	 * Calculate retry delay based on failed count with exponential backoff
	 * Pattern: 1hr → 2hrs → 3hrs → 4hrs (capped at 4hrs)
	 *
	 * @param int $failed_count Number of consecutive failures
	 * @return int Delay in seconds
	 */
	private function calculate_retry_delay( $failed_count ) {
		// Minimum delay: 60 minutes (1 hour)
		$min_delay = HOUR_IN_SECONDS;
		
		// Exponential backoff pattern:
		// Failed 1: 1 hour (60 min)
		// Failed 2: 2 hours (120 min)
		// Failed 3: 3 hours (180 min)
		// Failed 4+: 4 hours (240 min) - capped
		$delay_hours = min( $failed_count, 4 );
		$delay_seconds = $delay_hours * HOUR_IN_SECONDS;
		
		// Ensure minimum delay of 60 minutes
		return max( $delay_seconds, $min_delay );
	}

	/**
	 * Format delay in seconds to human-readable string
	 *
	 * @param int $seconds Delay in seconds
	 * @return string Formatted delay string
	 */
	private function format_delay( $seconds ) {
		$hours = floor( $seconds / HOUR_IN_SECONDS );
		$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		
		if ( $hours > 0 && $minutes > 0 ) {
			return sprintf( '%d hour(s) %d minute(s)', $hours, $minutes );
		} elseif ( $hours > 0 ) {
			return sprintf( '%d hour(s)', $hours );
		} else {
			return sprintf( '%d minute(s)', $minutes );
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
			$response_data      = Helper::get_args_option( 'data', $heartbeat_response, array() );
			$success            = intval( $response_code ) === 200;

			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( 'Print Heartbeat API Curl Response Start' );
				error_log( wp_json_encode( $heartbeat_response, true ) );
				error_log( 'Print Heartbeat API Curl Response End' );
			}

			if ( $success ) {
				if ( ! empty( $heartbeat_body ) ) {
					self::set_last_heartbeat_data( $heartbeat_body, $connect_id );
				}
			} elseif ( ! empty( $response_data ) && isset( $response_data['remove_connect'] ) && true === $response_data['remove_connect'] ) {
				// Remove connect.
				instawp_reset_running_migration( 'hard' );
			}

			return array(
				'success'       => $success,
				'response'      => $heartbeat_response,
				'response_code' => $response_code,
			);
		}

		/**
		 * Test helper: Simulate heartbeat failure to test retry delay
		 * 
		 * Usage: Call this method to manually trigger a failure and test retry delays
		 * Example: InstaWP_Heartbeat::test_retry_delay( 1 ); // Simulate 1 failure
		 *
		 * @param int $failed_count Number of failures to simulate (1-4 recommended for testing)
		 * @param bool $schedule_action Whether to actually schedule the action (default: true)
		 * @return array Test results with delay information
		 */
		public static function test_retry_delay( $failed_count = 1, $schedule_action = true ) {
			$instance = new self();
			
			// Set the failed count
			Option::update_option( 'instawp_heartbeat_failed', $failed_count );
			
			// Set last attempt time to now (simulating a failure just happened)
			Option::update_option( 'instawp_heartbeat_last_attempt', time() );
			
			// Calculate the retry delay
			$retry_delay = $instance->calculate_retry_delay( $failed_count );
			
			// Calculate next attempt time
			$next_attempt = time() + $retry_delay;
			
			// Actually schedule the action if requested
			if ( $schedule_action ) {
				// Unschedule any existing actions
				as_unschedule_all_actions( 'instawp_handle_heartbeat', array(), 'instawp-connect' );
				// Schedule the retry
				as_schedule_single_action( $next_attempt, 'instawp_handle_heartbeat', array(), 'instawp-connect' );
			}
			
			$result = array(
				'failed_count'     => $failed_count,
				'retry_delay'      => $retry_delay,
				'retry_delay_hours' => round( $retry_delay / HOUR_IN_SECONDS, 2 ),
				'formatted_delay'  => $instance->format_delay( $retry_delay ),
				'last_attempt'     => date( 'Y-m-d H:i:s', time() ),
				'next_attempt'     => date( 'Y-m-d H:i:s', $next_attempt ),
				'next_attempt_timestamp' => $next_attempt,
				'seconds_until_retry' => $retry_delay,
				'action_scheduled' => $schedule_action,
			);
			
			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( '[HEARTBEAT TEST] Retry delay test results: ' . wp_json_encode( $result, JSON_PRETTY_PRINT ) );
			}
			
			return $result;
		}

		/**
		 * Test helper: Reset heartbeat test state
		 * 
		 * Usage: Call this to reset all test state before/after testing
		 * Example: InstaWP_Heartbeat::reset_test_state();
		 *
		 * @return bool Success status
		 */
		public static function reset_test_state() {
			Option::delete_option( 'instawp_heartbeat_failed' );
			Option::delete_option( 'instawp_heartbeat_last_attempt' );
			
			// Clear scheduled actions
			as_unschedule_all_actions( 'instawp_handle_heartbeat', array(), 'instawp-connect' );
			
			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( '[HEARTBEAT TEST] Test state reset successfully' );
			}
			
			return true;
		}

		/**
		 * Test helper: Get current heartbeat state for debugging
		 * 
		 * Usage: Call this to see current heartbeat state
		 * Example: InstaWP_Heartbeat::get_heartbeat_state();
		 *
		 * @return array Current state information
		 */
		public static function get_heartbeat_state() {
			$instance = new self();
			$failed_count = Option::get_option( 'instawp_heartbeat_failed', 0 );
			$last_attempt = Option::get_option( 'instawp_heartbeat_last_attempt', 0 );
			
			// Get scheduled actions
			$scheduled_actions = as_get_scheduled_actions( 
				array( 
					'hook' => 'instawp_handle_heartbeat',
					'group' => 'instawp-connect',
					'status' => 'pending',
					'per_page' => 1,
				),
				'ids'
			);
			
			$next_scheduled = null;
			if ( ! empty( $scheduled_actions ) && class_exists( 'ActionScheduler' ) ) {
				$action_id = reset( $scheduled_actions );
				$store = ActionScheduler::store();
				if ( $store && method_exists( $store, 'get_date' ) ) {
					try {
						$next_date = $store->get_date( $action_id );
						if ( $next_date ) {
							if ( is_a( $next_date, 'DateTime' ) ) {
								$next_scheduled = $next_date->getTimestamp();
							} elseif ( is_object( $next_date ) && method_exists( $next_date, 'getTimestamp' ) ) {
								$next_scheduled = $next_date->getTimestamp();
							}
						}
					} catch ( Exception $e ) {
						// Silently fail if we can't get the schedule
					}
				}
			}
			
			$retry_delay = $failed_count > 0 ? $instance->calculate_retry_delay( $failed_count ) : 0;
			
			$state = array(
				'failed_count'        => $failed_count,
				'last_attempt'        => $last_attempt > 0 ? date( 'Y-m-d H:i:s', $last_attempt ) : 'Never',
				'last_attempt_timestamp' => $last_attempt,
				'retry_delay'         => $retry_delay,
				'formatted_delay'     => $retry_delay > 0 ? $instance->format_delay( $retry_delay ) : 'N/A',
				'next_scheduled'      => $next_scheduled ? date( 'Y-m-d H:i:s', $next_scheduled ) : 'Not scheduled',
				'next_scheduled_timestamp' => $next_scheduled,
				'seconds_until_next' => $next_scheduled ? max( 0, $next_scheduled - time() ) : 0,
				'heartbeat_enabled'   => Option::get_option( 'instawp_rm_heartbeat', 'on' ),
			);
			
			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( '[HEARTBEAT STATE] Current state: ' . wp_json_encode( $state, JSON_PRETTY_PRINT ) );
			}
			
			return $state;
		}
	}
}

new InstaWP_Heartbeat();
