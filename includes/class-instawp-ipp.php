<?php
/**
 * InstaWP Iterative Pull Push CLI Commands
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\WPConfig;
use InstaWP\Connect\Helpers\Option;

if ( ! class_exists( 'INSTAWP_IPP' ) ) {
	class INSTAWP_IPP {

		protected static $_instance = null;

		/**
		 * The command start
		 * @var string
		 */
		private $cron_hook = 'iwp_ipp_cron';

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
		 * @return INSTAWP_IPP
		 */
		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}


		public function __construct() {
			add_action( 'init', array( $this, 'schedule_cron' ) );
			add_action( $this->cron_hook, array( $this, 'run_cron' ) );
			$this->helper = new INSTAWP_IPP_Helper();
		}

		public function schedule_cron() {
			// Get api key
			$encoded_api_key = Helper::get_api_key();
			if ( ! empty( $encoded_api_key ) && ! wp_next_scheduled( $this->cron_hook ) && $this->should_check_checksum() ) {
				wp_schedule_event( time(), 'hourly', $this->cron_hook );
			}
		}

		/**
		 * Runs the daily cron job to process the hitmiss log, update the checksum cache file, and remove expired keys.
		 *
		 * @return void
		 */
		public function run_cron() {
			// Process hit|miss log
			$this->prepare_files_checksum();
		}

		private function prepare_files_checksum() {
			if ( ! $this->should_check_checksum() ) {
				return;
			}
			$this->helper->get_file_settings( array( 
				'exclude_paths' => array(),
				'include_paths' => array(),
				'exclude_core' => false,
				'exclude_uploads' => false,
				'exclude_large_files' => false,
				'files_limit' => 1000, 
			) );
		}

		private function should_check_checksum() {
			$process = get_option( $this->file_checksum_name . '_processed_count', 0 );
			return 'completed' !== $process;
		}



		
	}

	INSTAWP_IPP::instance();
}


