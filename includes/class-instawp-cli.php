<?php
/**
 * InstaWP CLI Commands
 */

use InstaWP\Connect\Helpers\WPConfig;

if ( ! class_exists( 'INSTAWP_CLI_Commands' ) ) {
	class INSTAWP_CLI_Commands {

		protected static $_instance = null;

		/**
		 * INSTAWP_CLI_Commands Constructor
		 */
		public function __construct() {
			add_action( 'cli_init', array( $this, 'add_wp_cli_commands' ) );
		}

		function cli_local_push() {

			// Files backup
			if ( is_wp_error( $archive_path_file = InstaWP_Tools::cli_archive_wordpress_files() ) ) {
				die( $archive_path_file->get_error_message() );
			}
			WP_CLI::success( 'Files backup created successfully.' );

			// Database backup
			$archive_path_db = InstaWP_Tools::cli_archive_wordpress_db();
			WP_CLI::success( 'Database backup created successfully.' );


//			$archive_path_file = '/Users/jaed/Desktop/wordpress_backup_2024-01-09_05-11-56.zip';
//			$archive_path_db   = '/Users/jaed/Desktop/wordpress_db_backup_2024-01-09_05-12-02.sql';

			// Create Site
			if ( is_wp_error( $create_site_res = InstaWP_Tools::create_insta_site() ) ) {
				die( $create_site_res->get_error_message() );
			}

			$site_id          = InstaWP_Setting::get_args_option( 'id', $create_site_res );
			$site_wp_url      = InstaWP_Setting::get_args_option( 'wp_url', $create_site_res );
			$site_wp_username = InstaWP_Setting::get_args_option( 'wp_username', $create_site_res );
			$site_wp_password = InstaWP_Setting::get_args_option( 'wp_password', $create_site_res );
			$site_s_hash      = InstaWP_Setting::get_args_option( 's_hash', $create_site_res );

			WP_CLI::success( 'Site created successfully. URL: ' . $site_wp_url );

			for ( $index = 10; $index > 0; -- $index ) {
				WP_CLI::line( "Preparing to access the website in $index seconds. Please wait..." );
				sleep( 1 );
			}

			// Upload files and db using SFTP
			if ( is_wp_error( $file_upload_status = InstaWP_Tools::cli_upload_using_sftp( $site_id, $archive_path_file, $archive_path_db ) ) ) {
				die( $file_upload_status->get_error_message() );
			}

			// Call restore API to initiate the restore
			if ( is_wp_error( $file_upload_status = InstaWP_Tools::cli_restore_website( $site_id, $archive_path_file, $archive_path_db ) ) ) {
				die( $file_upload_status->get_error_message() );
			}

			WP_CLI::success( 'Migration successful.' );
		}

		function handle_instawp_commands( $args ) {

			if ( isset( $args[0] ) && $args[0] === 'local' ) {

				if ( isset( $args[1] ) && $args[1] === 'push' ) {
					$this->cli_local_push();
				}

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'set-waas-mode' ) {

				if ( isset( $args[1] ) ) {
					$wp_config = new WPConfig( [ 'INSTAWP_CONNECT_MODE' => 'WAAS_GO_LIVE', 'INSTAWP_CONNECT_WAAS_URL' => $args[1] ] );
					$wp_config->update();
				}

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'reset-waas-mode' ) {
				$wp_config = new WPConfig( [ 'INSTAWP_CONNECT_MODE', 'INSTAWP_CONNECT_WAAS_URL' ] );
				$wp_config->delete();

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'config-set' ) {
				if ( isset( $args[1] ) ) {
					if ( $args[1] === 'api-key' ) {
						InstaWP_Setting::instawp_generate_api_key( $args[2], 'true' );
					} else if ( $args[1] === 'api-domain' ) {
						InstaWP_Setting::set_api_domain( $args[2] );
					}
				}

				if ( isset( $args[3] ) ) {
					$payload_decoded = base64_decode( $args[3] );
					$payload         = json_decode( $payload_decoded, true );

					if ( isset( $payload['mode'] ) ) {
						if ( isset( $payload['mode']['name'] ) ) {
							$wp_config = new WPConfig( [ 'INSTAWP_CONNECT_MODE' => $payload['mode']['name'] ] );
							$wp_config->update();
						}
					}
				}

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'config-remove' ) {
				$option = new \InstaWP\Connect\Helpers\Option();
				$option->delete( [ 'instawp_api_key', 'instawp_api_options', 'instawp_connect_id_options' ] );

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'hard-reset' ) {
				instawp_reset_running_migration( 'hard', false );

				return true;
			}

			if ( isset( $args[0] ) && $args[0] === 'staging-set' && ! empty( $args[1] ) ) {
				update_option( 'instawp_sync_connect_id', intval( $args[1] ) );
				update_option( 'instawp_is_staging', true );
				instawp_get_source_site_detail();

				return true;
			}

			return true;
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


