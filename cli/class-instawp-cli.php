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

			global $instawp_plugin;

			$cli_action_index = array_search( '-action', $args );
			$cli_action       = $args[ ( $cli_action_index + 1 ) ] ?? '';

			switch ( $cli_action ) {

				case 'clean':

					instawp_reset_running_migration( 'soft', false );
					WP_CLI::success( esc_html__( 'Cleared previous backup files successfully.', 'instawp-connect' ) );
					break;

				case 'backup':

					$backup_options      = array(
						'ismerge'      => '',
						'backup_files' => 'files+db',
						'local'        => '1',
						'type'         => 'Manual',
						'insta_type'   => 'stage_to_production',
						'action'       => 'backup',
						'is_migrate'   => false,
					);
					$backup_options      = apply_filters( 'INSTAWP_CONNECT/Filters/migrate_backup_options', $backup_options );
					$pre_backup_response = $instawp_plugin->pre_backup( $backup_options );
					$migrate_task_id     = InstaWP_Setting::get_args_option( 'task_id', $pre_backup_response );
					$migrate_task_obj    = new InstaWP_Backup_Task( $migrate_task_id );

					instawp_backup_files( $migrate_task_obj, array( 'clean_non_zip' => true ) );

					WP_CLI::success( esc_html__( 'Build backup files successfully.', 'instawp-connect' ) );

					break;

				default:
					WP_CLI::error( esc_html__( 'Invalid command for `-action`', 'instawp-connect' ) );
					break;
			}
		}


		public static function get_pending_task_id() {

			$incomplete_task_ids = InstaWP_taskmanager::is_there_any_incomplete_task_ids();
			$migrate_task_id     = false;

			if ( ! empty( $incomplete_task_ids ) ) {
				$migrate_task_id = reset( $incomplete_task_ids );
			}

			return $migrate_task_id;
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


