<?php
/**
 * InstaWP Iterative Pull Push CLI Commands
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\WPConfig;
use InstaWP\Connect\Helpers\Option;

if ( ! class_exists( 'INSTAWP_IPP_CLI_Commands' ) ) {
	class INSTAWP_IPP_CLI_Commands {

		/**
		 * The command start
		 * @var string
		 */
		private $command_start = 'instawp';

		/**
		 * File checksum name
		 */
		private $file_checksum_name = 'iwp_ipp_file_checksums_repo';
		/**
		 * Database checksum name
		 */
		private $db_checksum_name = 'iwp_ipp_db_checksums_repo';

		/**
		 * @var INSTAWP_IPP_Helper
		 */
		private $helper;

		/**
		 * Staging site path
		 */
		private $staging_url = '';

		/**
		 * Staging site path
		 */
		private $username = '';

		/**
		 * API Key
		 */
		private $api_key = '';

		/**
		 * Is staging
		 */
		private $is_staging = false;

		/**
		 * INSTAWP_IPP_CLI_Commands Constructor
		 */
		public function __construct() {
		}

		private function init() {
			$api_key = Helper::get_api_key();
			if ( empty( $api_key ) ) {
				WP_CLI::error( __( 'Missing API key.', 'instawp-connect' ) );
			}
			$api_key = hash( 'sha256', $api_key );
			$connect_id    = instawp_get_connect_id();

			if ( empty( $connect_id ) ) {
				WP_CLI::error( __( 'Missing Connect ID.', 'instawp-connect' ) );
			}

			$staging_sites = get_option( 'instawp_staging_sites' );
			if ( ! empty( $staging_sites ) && ! empty( $staging_sites[0]['url'] ) && filter_var( $staging_sites[0]['url'], FILTER_VALIDATE_URL ) ) {
				$this->staging_url = esc_url( $staging_sites[0]['url'] );
				$this->username = $staging_sites[0]['username'];
			}
			
			if ( empty( $this->staging_url ) || empty( $this->username ) ) {
				WP_CLI::error( __( 'Staging site not found.', 'instawp-connect' ) );
			}

			// Check if its a staging site
			$api_options = get_option( 'instawp_api_options', array() );
			$this->is_staging  = ( ! empty( $api_options ) && ! empty( $api_options['api_url'] ) && false !== stripos( $api_options['api_url'], 'stage' ) ) ? 1 : 0;

			WP_CLI::log( __( 'Connected Site:', 'instawp-connect' ) . ' ' . $this->staging_url );
			// Helpers
			$this->helper = new INSTAWP_IPP_Helper( true );
		}


		/**
		 * RUN Command 
		 * wp instawp push
		 * wp instawp pull
		 * 
		 * @param array $args
		 * 
		 */
		public function run_command( $args, $assoc_args ) {
			if ( empty( $args[0] ) || ! in_array( $args[0], array( 'push', 'pull' ) ) ) {
				WP_CLI::error( 'Missing or invalid type command. Must be push or pull. e.g. ' . $this->command_start . ' push or ' . $this->command_start . ' pull or IWP pull' );
				return false;
			}

			$this->init();
			$ready = $this->call_api( 'is-ready' );
			WP_CLI::success( "Run Successfully " . json_encode( $ready ) );
			return;
			if ( isset( $assoc_args['show-tables'] ) ) {
				$tables = $this->helper->get_tables_checksum();
				WP_CLI::success( "Tables = " . json_encode( $tables ) );
				return;
			}

			// Set last run command
			update_option( 'iwp_ipp_cli_last_run_time', time() );

			$command = $args[0];

			if ( $command === 'push' ) {
				if ( isset( $assoc_args['purge-cache'] ) ) {
					update_option( $this->file_checksum_name, array() );
					update_option( $this->db_checksum_name, array() );
					WP_CLI::success( "Cache purged for {$command} files successfully." );
					return;
				}

				$exclude_tables = empty( $assoc_args['exclude-tables'] ) ? array() : explode( ',', $assoc_args['exclude-tables'] );
				$include_tables = empty( $assoc_args['include-tables'] ) ? array() : explode( ',', $assoc_args['include-tables'] );

				WP_CLI::log( "Detecting files changes..." );

				$start = time();
				$settings = $this->helper->get_file_settings(
					array(
						'exclude_paths' => empty( $assoc_args['exclude-paths'] ) ? array() : explode( ',', $assoc_args['exclude-paths'] ),
						'include_paths' => empty( $assoc_args['include-paths'] ) ? array() : explode( ',', $assoc_args['include-paths'] ),
						'exclude_core' => empty( $assoc_args['exclude-core'] ) ? false : true,
						'exclude_uploads' => empty( $assoc_args['exclude-core'] ) ? false : true,
						'exclude_large_files' => empty( $assoc_args['exclude-large-files'] ) ? false : true,
						'files_limit' => empty( $assoc_args['files-limit'] ) ? 0 : intval( $assoc_args['files-limit'] ),
					)
				);

				if ( ! empty( $settings ) ) {
					WP_CLI::success( "Detecting files changes completed in " . ( time() - $start ) . " seconds" );
				}
			}

			WP_CLI::success( "Run Successfully" );
		}

		private function call_api( $path, $body = array() ) {
			
			$response = wp_remote_post( 
				$this->staging_url . '/wp-json/instawp-connect/v2/ipp/' . $path, 
				array( 
					'body' => $body,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'staging'       => $is_staging,
					),
				) 
			);
			if ( is_wp_error( $response ) ) {
				WP_CLI::error( __( 'API call failed: ', 'instawp-connect' ) . $response->get_error_message() );
			}
	
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
	
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				WP_CLI::error( __( 'Invalid response format.', 'instawp-connect' ) );
			}
	
			return $response;
		}

		/**
		 * Get database settings.
		 *
		 * @param array $args Optional arguments.
		 *
		 * @return array Array of database settings.
		 */
		public function get_db_settings( $args = array() ) {
			
		}

		
	}
}


