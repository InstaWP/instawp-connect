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
		 * INSTAWP_IPP_CLI_Commands Constructor
		 */
		public function __construct() {
			$this->helper = new INSTAWP_IPP_Helper( true );
		}


		/**
		 * @throws \WP_CLI\ExitException
		 */
		public function run_command( $args, $assoc_args ) {
			if ( empty( $args[0] ) || ! in_array( $args[0], array( 'push', 'pull' ) ) ) {
				WP_CLI::error( 'Missing or invalid type command. Must be push or pull. e.g. ' . $this->command_start . ' push or ' . $this->command_start . ' pull or IWP pull' );
				return false;
			}

			if ( isset( $assoc_args['show-tables'] ) ) {
				$tables = $this->helper->get_tables_checksum();
				WP_CLI::success( "Tables = " . json_encode( $tables ) );
				return;
			}

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


