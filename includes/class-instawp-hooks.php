<?php
/**
 * Class for all hooks
 */

defined( 'INSTAWP_PLUGIN_DIR' ) || exit;


if ( ! class_exists( 'InstaWP_Hooks' ) ) {
	class InstaWP_Hooks {

		public function __construct() {

			add_action( 'init', array( $this, 'ob_start' ) );
			add_action( 'wp_footer', array( $this, 'ob_end' ) );
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

new InstaWP_AJAX();
