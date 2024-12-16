<?php

defined( 'ABSPATH' ) || exit;

/**
 * Sction Migration class.
 */

if ( ! class_exists( 'InstaWP_Updates' ) ) {
	class InstaWP_Updates {

		private static $updates = array(
			'0.1.0.65' => 'updates/update-0.1.0.65.php',
			'0.1.0.70' => 'updates/update-0.1.0.70.php',
		);

		public function __construct() {
			add_action( 'admin_init', array( $this, 'do_updates' ) );
		}

		/**
		 * Check if any update is required.
		 */
		public function do_updates() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$installed_version = get_option( 'instawp_connect_version', '0.1.0.0' );

			if ( ! $installed_version || version_compare( $installed_version, INSTAWP_PLUGIN_VERSION, '<' ) ) {
				$this->perform_updates();
			}
		}

		/**
		 * Perform all updates.
		 */
		public function perform_updates() {
			$installed_version = get_option( 'instawp_connect_version', '0.1.0.0' );

			foreach ( self::$updates as $version => $path ) {
				if ( version_compare( $installed_version, $version, '<' ) ) {
					include_once $path;
					update_option( 'instawp_connect_version', $version, false );
				}
			}

			update_option( 'instawp_connect_version', INSTAWP_PLUGIN_VERSION, false );
		}
	}
}

new InstaWP_Updates();