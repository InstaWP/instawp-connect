<?php
/**
 * Class for all hooks
 */

defined( 'ABSPATH' ) || exit;


if ( ! class_exists( 'InstaWP_Hooks' ) ) {
	class InstaWP_Hooks {

		public function __construct() {

			add_action( 'init', array( $this, 'ob_start' ) );
			add_action( 'wp_footer', array( $this, 'ob_end' ) );

			add_action( 'admin_init', array( $this, 'handle_clear_all' ) );
			add_action( 'admin_init', array( $this, 'store_api_options' ) );
		}


		function store_api_options() {
			if (
				( isset( $_REQUEST['access_token'] ) && $_REQUEST['access_token'] != '' ) &&
				( isset( $_REQUEST['success'] ) && $_REQUEST['success'] == true )
			) {
				$access_token = $_REQUEST['access_token'];
				$status       = $_REQUEST['success'];

				$api_key             = '';
				$instawp_api_options = get_option( 'instawp_api_options' );
				if ( ! empty( $instawp_api_options ) ) {
					$api_key = $instawp_api_options['api_key'];
				}

				if ( $api_key != $access_token ) {
					InstaWP_Setting::instawp_generate_api_key( $access_token, $status );
				}
			}
		}

		function handle_clear_all() {

			$admin_page   = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
			$clear_action = isset( $_GET['clear'] ) ? sanitize_text_field( $_GET['clear'] ) : '';

			if ( 'instawp' === $admin_page && 'all' === $clear_action ) {

				instawp_reset_running_migration();

				wp_redirect( admin_url( 'tools.php?page=instawp' ) );
				exit();
			}
		}


		/**
		 * Return Buffered Content
		 *
		 * @param $buffer
		 *
		 * @return mixed
		 */
		function ob_callback( $buffer ) {
			return $buffer;
		}


		/**
		 * Start of Output Buffer
		 */
		function ob_start() {
			ob_start( array( $this, 'ob_callback' ) );
		}


		/**
		 * End of Output Buffer
		 */
		function ob_end() {
			if ( ob_get_length() ) {
				ob_end_flush();
			}
		}
	}
}

new InstaWP_Hooks();
