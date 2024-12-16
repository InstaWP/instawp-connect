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
		private $db_meta_name = 'iwp_ipp_db_meta_repo';

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
			if ( ! empty( $encoded_api_key ) && ! wp_next_scheduled( $this->cron_hook ) ) {
				wp_schedule_event( time(), 'hourly', $this->cron_hook );
			}
		}

		/**
		 * Runs the daily cron job to process the hitmiss log, update the checksum cache file, and remove expired keys.
		 *
		 * @return void
		 */
		public function run_cron() {
			$last_run_time = get_option( 'iwp_ipp_cli_last_run_time', 0 );
			// Check if last run time is more than 2 hours ago
			if ( time() - $last_run_time <  DAY_IN_SECONDS ) {
				return;
			}
			// Prepare database checksum
			$this->prepare_db_meta();
			// Prepare files checksum
			$this->prepare_files_checksum();
		}

		/**
		 * Prepare database checksum
		 *
		 * @return void
		 */
		public function prepare_db_meta() {
			global $wpdb;
			$db_meta = get_option( $this->db_meta_name, array() );
			if ( empty( $db_meta ) || 2 * DAY_IN_SECONDS > ( time() - intval( $db_meta['time'] ) ) ) {
				if ( empty( $db_meta ) ) {
					update_option( $this->db_meta_name . '_last_run_data', array() );
				}
				$db_meta = empty( $db_meta ) ? array() : $db_meta;
				$db_meta['time'] = time();
				$db_meta['tables'] = $this->helper->get_tables();
				$db_meta['table_prefix'] = $wpdb->prefix;
				update_option( $this->db_meta_name, $db_meta );
			} else if ( ! empty( $db_meta['tables'] ) ) {
				if ( empty( $db_meta['meta'] ) ) {
					$db_meta['meta'] = array();
				}
				$meta = $this->helper->get_table_meta( $db_meta['tables'] );
				if ( ! empty( $meta ) && ! empty( $meta['table'] ) && empty( $meta['error']) ) {
					$db_meta['meta'][ $meta['table'] ] = $meta;
					update_option( $this->db_meta_name, $db_meta );
				}
			}
		}

		private function prepare_files_checksum() {
			$process = get_option( $this->file_checksum_name . '_processed_count', 0 );
			if ( 'completed' === $process ) {
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
		
	}

	INSTAWP_IPP::instance();
}


