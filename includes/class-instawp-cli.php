<?php
/**
 * InstaWP CLI Commands
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\WPConfig;
use InstaWP\Connect\Helpers\Option;

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
			if ( isset( $args[0] ) && $args[0] === 'local' ) {
				if ( isset( $args[1] ) && $args[1] === 'push' ) {
					$this->cli_local_push();
				}

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'set-waas-mode' ) {
				if ( isset( $args[1] ) ) {
					try {
						$wp_config = new WPConfig( array(
							'INSTAWP_CONNECT_MODE'     => 'WAAS_GO_LIVE',
							'INSTAWP_CONNECT_WAAS_URL' => $args[1],
						) );
						$wp_config->set();
					} catch ( \Exception $e ) {
						WP_CLI::error( $e->getMessage() );

						return false;
					}
				}

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'reset-waas-mode' ) {
				try {
					$wp_config = new WPConfig( array( 'INSTAWP_CONNECT_MODE', 'INSTAWP_CONNECT_WAAS_URL' ) );
					$wp_config->delete();
				} catch ( \Exception $e ) {
					WP_CLI::error( $e->getMessage() );

					return false;
				}

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'config-set' ) {
				if ( isset( $args[1] ) ) {
					if ( $args[1] === 'api-key' ) {
						Helper::instawp_generate_api_key( $args[2] );
					} elseif ( $args[1] === 'api-domain' ) {
						Helper::set_api_domain( $args[2] );
					}
				}

				if ( isset( $args[3] ) ) {
					$payload_decoded = base64_decode( $args[3] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					$payload         = json_decode( $payload_decoded, true );

					if ( isset( $payload['mode'] ) ) {
						if ( isset( $payload['mode']['name'] ) ) {
							try {
								$wp_config = new WPConfig( array( 'INSTAWP_CONNECT_MODE' => $payload['mode']['name'] ) );
								$wp_config->set();
							} catch ( \Exception $e ) {
								WP_CLI::error( $e->getMessage() );

								return false;
							}
						}
					}
				}

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'config-remove' ) {
				$option = new \InstaWP\Connect\Helpers\Option();
				$option->delete( array( 'instawp_api_options', 'instawp_connect_id_options' ) );

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'hard-reset' ) {
				instawp_reset_running_migration( 'hard', false );

				if ( ! empty( $assoc_args['clear-events'] ) ) {
					instawp_delete_sync_entries();
				}

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'clear-events' ) {
				instawp_delete_sync_entries();

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'refresh-staging-list' ) {
				instawp_set_staging_sites_list();

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'staging-set' && ! empty( $args[1] ) ) {
				Option::update_option( 'instawp_sync_connect_id', intval( $args[1] ) );
				Option::update_option( 'instawp_is_staging', true );
				instawp_get_source_site_detail();

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'reset' ) {
				if ( isset( $args[1] ) && $args[1] === 'staging' ) {
					delete_option( 'instawp_sync_connect_id' );
					delete_option( 'instawp_is_staging' );
					instawp_reset_running_migration();
				}
			}

			if ( isset( $args[0] ) && $args[0] === 'scan' ) {
				if ( isset( $args[1] ) && $args[1] === 'summary' ) {
					$wp_scanner  = new \InstaWP\Connect\Helpers\WPScanner();
					$summary_res = $wp_scanner->scan_summary();

					if ( is_wp_error( $summary_res ) ) {
						WP_CLI::error( $summary_res->get_error_message() );

						return false;
					}

					if ( ! is_array( $summary_res ) ) {
						WP_CLI::error( esc_html__( 'Failed: Could not create performance summary.', 'instawp-connect' ) );

						return false;
					}

					WP_CLI::success( esc_html__( 'Performance Summary of this website is given below:', 'instawp-connect' ) );

					$this->render_cli_from_array( $summary_res );
				}

				if ( isset( $args[1] ) && $args[1] === 'slow-item' ) {
					$wp_scanner = new \InstaWP\Connect\Helpers\WPScanner();
					$slow_items = $wp_scanner->scan_slow_items();

					if ( is_wp_error( $slow_items ) ) {
						WP_CLI::error( $slow_items->get_error_message() );

						return false;
					}

					if ( ! is_array( $slow_items ) ) {
						WP_CLI::error( esc_html__( 'Failed: Could not calculate slow items.', 'instawp-connect' ) );

						return false;
					}

					$slow_items_table = array();
					$counter          = 0;
					$fields           = array( 'ID', 'Name', 'Type', 'Time Taken' );

					foreach ( $slow_items as $slow_item ) {
						++ $counter;
						$slow_items_table[] = array(
							'ID'         => $counter,
							'Name'       => $slow_item[2],
							'Type'       => $slow_item[3],
							'Time Taken' => $slow_item[1],
						);
					}

					WP_CLI::success( esc_html__( 'Slow items of this website are given below: (Top is the slowest one)', 'instawp-connect' ) );

					WP_CLI\Utils\format_items( 'table', $slow_items_table, $fields );
				}
			}

			// InstaWP Iterative Pull Push CLI Commands
			if ( ! empty( $args[0] ) && in_array( $args[0], array( 'push', 'pull' ) ) ) {
				require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-ipp-cli.php';
				$instawp_cli = new INSTAWP_IPP_CLI_Commands();
				return $instawp_cli->run_command( $args, $assoc_args );
			}

			return true;
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


