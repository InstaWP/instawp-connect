<?php
/**
 * InstaWP CLI Commands
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\WPConfig;
use InstaWP\Connect\Helpers\Option;
use InstaWP\Connect\Helpers\WPScanner;

if ( ! class_exists( 'INSTAWP_CLI_Commands' ) ) {
	class INSTAWP_CLI_Commands {

		protected static $_instance = null;

		/**
		 * INSTAWP_CLI_Commands Constructor
		 */
		public function __construct() {
			add_action( 'cli_init', array( $this, 'add_wp_cli_commands' ) );
		}

		public function cli_local_push() {

			global $wp_version;

			// Files backup
			if ( is_wp_error( $archive_path_file = InstaWP_Tools::cli_archive_wordpress_files() ) ) {
				die( esc_html( $archive_path_file->get_error_message() ) );
			}
			WP_CLI::success( 'Files backup created successfully.' );

			Option::update_option( 'instawp_parent_is_on_local', true );

			// Database backup
			$archive_path_db = InstaWP_Tools::cli_archive_wordpress_db();
			WP_CLI::success( 'Database backup created successfully.' );

			delete_option( 'instawp_parent_is_on_local' );

			// Create Site
			if ( is_wp_error( $create_site_res = InstaWP_Tools::create_insta_site() ) ) {
				die( esc_html( $create_site_res->get_error_message() ) );
			}

			$site_id          = Helper::get_args_option( 'id', $create_site_res );
			$site_wp_url      = Helper::get_args_option( 'wp_url', $create_site_res );
			$site_wp_username = Helper::get_args_option( 'wp_username', $create_site_res );
			$site_wp_password = Helper::get_args_option( 'wp_password', $create_site_res );
			$site_s_hash      = Helper::get_args_option( 's_hash', $create_site_res );

			WP_CLI::success( 'Site created successfully. URL: ' . $site_wp_url );

			// Add migration entry
			$migrate_key         = Helper::get_random_string( 40 );
			$migrate_settings    = InstaWP_Tools::get_migrate_settings( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$migrate_args        = array(
				'site_id'           => $site_id,
				'mode'              => 'local-push',
				'source_connect_id' => instawp()->connect_id,
				'settings'          => $migrate_settings,
				'php_version'       => PHP_VERSION,
				'wp_version'        => $wp_version,
				'plugin_version'    => INSTAWP_PLUGIN_VERSION,
				'migrate_key'       => $migrate_key,
			);
			$migrate_res         = Curl::do_curl( 'migrates-v3/local-push', $migrate_args );
			$migrate_res_status  = (bool) Helper::get_args_option( 'success', $migrate_res, true );
			$migrate_res_message = Helper::get_args_option( 'message', $migrate_res );
			$migrate_res_data    = Helper::get_args_option( 'data', $migrate_res, array() );

			if ( ! $migrate_res_status ) {
				die( esc_html( $migrate_res_message ) );
			}

			$migrate_id   = Helper::get_args_option( 'migrate_id', $migrate_res_data );
			$tracking_url = Helper::get_args_option( 'tracking_url', $migrate_res_data );

			WP_CLI::success( "Migration initiated with migrate_id: {$migrate_id}. Tracking URL: {$tracking_url}" );

			// Wait 10 seconds
			sleep( 10 );

			// Upload files and db using SFTP
			if ( is_wp_error( $file_upload_status = InstaWP_Tools::cli_upload_using_sftp( $site_id, $archive_path_file, $archive_path_db ) ) ) {

				// Mark the migration failed
				instawp_update_migration_stages( array( 'failed' => true ), $migrate_id, $migrate_key );

				die( esc_html( $file_upload_status->get_error_message() ) );
			}

			// Call restore API to initiate the restore
			if ( is_wp_error( $file_upload_status = InstaWP_Tools::cli_restore_website( $site_id, $archive_path_file, $archive_path_db ) ) ) {

				// Mark the migration failed
				instawp_update_migration_stages( array( 'failed' => true ), $migrate_id, $migrate_key );

				die( esc_html( $file_upload_status->get_error_message() ) );
			}

			// Mark the migration failed
			instawp_update_migration_stages( array( 'migration-finished' => true ), $migrate_id, $migrate_key );

			// Finish configuration of the staging website
			$finish_mig_args    = array(
				'site_id'           => $site_id,
				'parent_connect_id' => instawp()->connect_id,
			);
			$finish_mig_res     = Curl::do_curl( 'migrates-v3/finish-local-staging', $finish_mig_args );
			$finish_mig_status  = (bool) Helper::get_args_option( 'success', $finish_mig_res, true );
			$finish_mig_message = Helper::get_args_option( 'message', $finish_mig_res );

			if ( ! $finish_mig_status ) {
				WP_CLI::success( 'Error in configuring the staging website. Error message: ' . $finish_mig_message );
			}

			WP_CLI::success( 'Migration successful.' );
		}

		/**
		 * @throws \WP_CLI\ExitException
		 */
		public function handle_instawp_commands( $args, $assoc_args ) {
			if ( empty( $args[0] ) ) {
				return false;
			}
		
			$command    = $args[0];
			$subcommand = isset( $args[1] ) ? $args[1] : '';

			// Command handler mapping
			$commands = array(
				'local'                => array(
					'push' => function() { // wp instawp local push
						$this->cli_local_push();
					},
				),
				'set-waas-mode'        => function( $args ) { // wp instawp set-waas-mode https://instawp.com
					if ( empty( $args[0] ) ) {
						WP_CLI::error( 'WaaS URL is required' );
						return false;
					}

					try {
						$wp_config = new WPConfig( array(
							'INSTAWP_CONNECT_MODE'     => 'WAAS_GO_LIVE',
							'INSTAWP_CONNECT_WAAS_URL' => $args[0],
						) );
						$wp_config->set();
					} catch ( \Exception $e ) {
						WP_CLI::error( $e->getMessage() );
						return false;
					}
				},
				'reset-waas-mode'      => function() { // wp instawp reset-waas-mode
					try {
						$wp_config = new WPConfig( array( 'INSTAWP_CONNECT_MODE', 'INSTAWP_CONNECT_WAAS_URL' ) );
						$wp_config->delete();
					} catch ( \Exception $e ) {
						WP_CLI::error( $e->getMessage() );
						return false;
					}
				},
				'config-set'           => array(
					'api-key'    => function( $args, $assoc_args ) { // wp instawp config-set api-key 1234567890
						if ( empty( $args[0] ) ) {
							WP_CLI::error( 'API key value is required' );
							return false;
						}

						$jwt     = ! empty( $assoc_args['jwt'] ) ? $assoc_args['jwt'] : '';
						$managed = ! isset( $assoc_args['unmanaged'] );
						$plan_id = ! empty( $assoc_args['plan-id'] ) ? $assoc_args['plan-id'] : 0;
						$config  = array(
							'managed' => $managed,
							'plan_id' => $plan_id,
						);
						
						Helper::generate_api_key( $args[0], $jwt, $config );

						if ( ! empty( $args[1] ) ) {
							$this->set_connect_mode( $args[1] );
						}
					},
					'api-domain' => function( $args ) { // wp instawp config-set api-domain https://instawp.com
						if ( empty( $args[0] ) ) {
							WP_CLI::error( 'API domain value is required' );
							return false;
						}
						Helper::set_api_domain( $args[0] );

						if ( ! empty( $args[1] ) ) {
							$this->set_connect_mode( $args[1] );
						}
					},
				),
				'config-remove'        => function() { // wp instawp config-remove
					$option = new Option();
					$option->delete( array( 'instawp_api_options', 'instawp_connect_id_options' ) );
				},
				'hard-reset'           => function( $args, $assoc_args ) { // wp instawp hard-reset --clear-events --disconnect --force
					if ( isset( $assoc_args['clear-events'] ) ) {
						instawp_delete_sync_entries();
					}

					if ( isset( $assoc_args['disconnect'] ) && instawp_is_connected_origin_valid() ) {
						$disconnect_res = $this->handle_disconnect();
						
						if ( ! $disconnect_res ) {
							if ( isset( $assoc_args['force'] ) ) {
								instawp_destroy_connect( 'delete' ); // force disconnect quietly
							} else {
								return false;
							}
						}
					}

					instawp_reset_running_migration( 'hard', false );
				},
				'disconnect'           => function( $args, $assoc_args ) { // wp instawp disconnect --force
					if ( instawp_is_connected_origin_valid() ) {
						$disconnect_res = $this->handle_disconnect();

						if ( ! $disconnect_res ) {
							if ( isset( $assoc_args['force'] ) ) {
								instawp_destroy_connect( 'delete' ); // force disconnect quietly
								return true;
							}
							return false;
						}
					}
					return false;
				},
				'clear-events'         => function() { // wp instawp clear-events
					instawp_delete_sync_entries();
				},
				'activate-plan'        => function( $args ) { // wp instawp activate-plan 123
					$plan_id = isset( $args[0] ) ? intval( $args[0] ) : 0;
					if ( empty( $plan_id ) ) {
						WP_CLI::error( __( 'Plan ID is required', 'instawp-connect' ) );
						return false;
					}

					$response = instawp_connect_activate_plan( $plan_id );
					if ( ! $response['success'] ) {
						WP_CLI::error( $response['message'] );
						return false;
					}
				},
				'refresh-staging-list' => function() { // wp instawp refresh-staging-list
					instawp_set_staging_sites_list();
				},
				'staging-set'          => function( $args ) { // wp instawp staging-set 123
					if ( empty( $args[0] ) ) {
						WP_CLI::error( __( 'Staging ID is required', 'instawp-connect' ) );
						return false;
					}
					Option::update_option( 'instawp_sync_connect_id', intval( $args[0] ) );
					Option::update_option( 'instawp_is_staging', true );

					instawp_get_source_site_detail();
				},
				'reset'                => array(
					'staging' => function() { // wp instawp reset staging
						delete_option( 'instawp_sync_connect_id' );
						delete_option( 'instawp_is_staging' );

						instawp_reset_running_migration();
					},
				),
				'scan'                 => array(
					'summary'   => function() { // wp instawp scan summary
						$this->handle_scan_summary();
					},
					'slow-item' => function() { // wp instawp scan slow-item
						$this->handle_scan_slow_items();
					},
				),
			);
		
			// Execute command if it exists
			if ( isset( $commands[ $command ] ) ) {
				unset( $args[0] );
				$args = array_values( $args );
				
				if ( is_array( $commands[ $command ] ) && isset( $commands[ $command ][ $subcommand ] ) && is_callable( $commands[ $command ][ $subcommand ] ) ) {
					unset( $args[0] );
					$args = array_values( $args );

					return $commands[ $command ][ $subcommand ]( $args, $assoc_args ) !== false;
				} elseif ( is_callable( $commands[ $command ] ) ) {
					return $commands[ $command ]( $args, $assoc_args ) !== false;
				}
			}
		
			return false;
		}

		// Set connect mode
		private function set_connect_mode( $data ) {
			try {
				$payload = json_decode( base64_decode( $data ), true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					$input_data = is_array( $data ) ? json_encode( $data ) : $data;
					throw new \Exception( 'Invalid payload format. Json error: ' . json_last_error_msg() . ' Payload: ' . $input_data );
				}
	
				if ( ! empty( $payload['mode']['name'] ) ) {
					$wp_config = new WPConfig( array( 'INSTAWP_CONNECT_MODE' => $payload['mode']['name'] ) );
					$wp_config->set();
					
					WP_CLI::success( 'Mode configuration updated successfully' );
				}
			} catch ( \Exception $e ) {
				WP_CLI::error( 'Failed to configure mode: ' . $e->getMessage() );
				return false;
			}
		}

		/**
		 * Handle disconnect operation
		 * 
		 * @return bool
		 */
		private function handle_disconnect() {
			$disconnect_res = instawp_destroy_connect();
			
			if ( ! $disconnect_res['success'] ) {
				WP_CLI::error( $disconnect_res['message'] );
				return false;
			}

			WP_CLI::success( $disconnect_res['message'] );
			return true;
		}
		
		// Helper methods for scan functionality
		private function handle_scan_summary() {
			$wp_scanner = new WPScanner();
			$summary_res = $wp_scanner->scan_summary();
		
			if ( is_wp_error($summary_res) ) {
				WP_CLI::error($summary_res->get_error_message());
				return false;
			}
		
			if ( ! is_array($summary_res) ) {
				WP_CLI::error(__('Failed: Could not create performance summary.', 'instawp-connect'));
				return false;
			}
		
			WP_CLI::success(__('Performance Summary of this website is given below:', 'instawp-connect'));
			$this->render_cli_from_array($summary_res);
		}
		
		private function handle_scan_slow_items() {
			$wp_scanner = new WPScanner();
			$slow_items = $wp_scanner->scan_slow_items();
		
			if ( is_wp_error( $slow_items ) || ! is_array( $slow_items ) ) {
				WP_CLI::error( __( 'Failed: Could not calculate slow items.', 'instawp-connect' ) );
				return false;
			}
		
			$slow_items_table = array_map( function( $item, $index ) {
				return array(
					'ID'         => $index + 1,
					'Name'       => $item[2],
					'Type'       => $item[3],
					'Time Taken' => $item[1],
				);
			}, $slow_items, array_keys( $slow_items ) );
		
			WP_CLI::success( __( 'Slow items of this website are given below: (Top is the slowest one)', 'instawp-connect' ) );
			WP_CLI\Utils\format_items( 'table', $slow_items_table, array( 'ID', 'Name', 'Type', 'Time Taken' ) );
		}

		public function render_cli_from_array( $args ) {
			if ( is_array( $args ) ) {
				foreach ( $args as $key => $value ) {
					if ( is_array( $value ) ) {
						$this->render_cli_from_array( $value );
					} else {
						WP_CLI::line( strtoupper( $key ) . ': ' . $value );
					}
				}
			}
		}

		/**
		 * Add CLI Commands
		 *
		 * @return void
		 * @throws Exception
		 */
		public function add_wp_cli_commands() {

			WP_CLI::add_command( 'instawp', array( $this, 'handle_instawp_commands' ) );
		}

		/**
		 * @return INSTAWP_CLI_Commands
		 */
		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}
}

INSTAWP_CLI_Commands::instance();


