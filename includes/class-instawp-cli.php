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

		function handle_instawp_commands( $args ) {

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

