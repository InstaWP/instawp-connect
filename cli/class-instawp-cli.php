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

			$cli_action_index      = array_search( '-action', $args );
			$cli_action            = $args[ ( $cli_action_index + 1 ) ] ?? '';
			$migrate_task_id_index = array_search( '-task', $args );
			$migrate_task_id       = $args[ ( $migrate_task_id_index + 1 ) ] ?? '';

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

				case 'upload';

					break;

				case 'download';

					if ( empty( $migrate_task = InstaWP_taskmanager::get_task( $migrate_task_id ) ) ) {
						WP_CLI::error( esc_html__( 'Invalid task ID : ' . $migrate_task_id, 'instawp-connect' ) );
					}

					$migrate_task_data = InstaWP_Setting::get_args_option( 'data', $migrate_task );
					$parameters        = InstaWP_Setting::get_args_option( 'parameters', $migrate_task_data );

					if ( ! empty( $parameters ) && is_array( $parameters ) ) {

						as_enqueue_async_action( 'instawp_download_bg', [ $migrate_task_id, $parameters ] );

						do_action( 'action_scheduler_run_queue', 'Async Request' );
					}

					break;


				case 'restore':

					$parameters      = array();
					$restore_options = json_encode( array(
						'skip_backup_old_site'     => '1',
						'skip_backup_old_database' => '1',
						'is_migrate'               => '1',
						'backup_db',
						'backup_themes',
						'backup_plugin',
						'backup_uploads',
						'backup_content',
						'backup_core',
					) );
					$backup_uploader = new InstaWP_BackupUploader();
					$backup_uploader->_rescan_local_folder_set_backup_api( $parameters );
					$backup_list = InstaWP_Backuplist::get_backuplist();

					if ( empty( $backup_list ) ) {
						return new WP_REST_Response( array( 'completed' => false, 'progress' => 0, 'message' => 'empty backup list' ) );
					}

					// Background processing of restore using woocommerce's scheduler.
					as_enqueue_async_action( 'instawp_restore_bg', [ $backup_list, $restore_options, $parameters ] );

					// Immediately run the schedule, don't want for the cron to run.
					do_action( 'action_scheduler_run_queue', 'Async Request' );

					break;

				default:
					WP_CLI::error( esc_html__( 'Invalid command for `-action`', 'instawp-connect' ) );
					break;
			}

			return true;
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


