<?php
/**
 * InstaWP CLI Commands
 */

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

			if ( isset( $args[0] ) && $args[0] === 'config-set' ) {
				if ( isset( $args[1] ) ) {
					if ( $args[1] === 'api-key' ) {
						InstaWP_Setting::instawp_generate_api_key( $args[2], 'true' );
					} else if ( $args[1] === 'api-domain' ) {
						InstaWP_Setting::set_api_domain( $args[2] );
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

			$cli_action_index      = array_search( '-action', $args );
			$cli_action            = $args[ ( $cli_action_index + 1 ) ] ?? '';
			$migrate_task_id_index = array_search( '-task', $args );
			$migrate_task_id       = $args[ ( $migrate_task_id_index + 1 ) ] ?? '';

			switch ( $cli_action ) {

				case 'clean':

					instawp_reset_running_migration( 'soft', false );

					WP_CLI::success( esc_html__( 'Cleared previous backup files successfully.', 'instawp-connect' ) );
					break;

				case 'config-set';

					$api_key = $args[ ( $cli_action_index + 2 ) ] ?? '';
					InstaWP_Setting::instawp_generate_api_key( $api_key, 'true' );

					break;

				case 'config-remove';

					$option = new \InstaWP\Connect\Helpers\Option();
					$option->delete( [ 'instawp_api_key', 'instawp_api_options', 'instawp_connect_id_options' ] );

					break;

				default:
					WP_CLI::error( esc_html__( 'Invalid command for `-action`', 'instawp-connect' ) );
					break;
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


