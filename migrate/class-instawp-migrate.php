<?php
/**
 * InstaWP Migration Process
 */


if ( ! class_exists( 'INSTAWP_Migration' ) ) {
	class INSTAWP_Migration {

		protected static $_instance = null;

		/**
		 * INSTAWP_Migration Constructor
		 */
		public function __construct() {

			add_action( 'admin_menu', array( $this, 'add_migrate_menu' ) );

			if ( isset( $_GET['page'] ) && 'instawp' === sanitize_text_field( $_GET['page'] ) ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );

				add_filter( 'admin_footer_text', '__return_false' );
				add_filter( 'update_footer', '__return_false', 99 );
			}

			add_action( 'wp_ajax_instawp_update_settings', array( $this, 'update_settings' ) );
			add_action( 'wp_ajax_instawp_connect_api_url', array( $this, 'connect_api_url' ) );
			add_action( 'wp_ajax_instawp_connect_migrate', array( $this, 'connect_migrate' ) );
		}


		function connect_migrate() {

			if ( ! class_exists( 'InstaWP_ZipClass' ) ) {
				include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-zipclass.php';
			}

			$response            = array(
				'backup'  => array(
					'progress' => 0,
				),
				'upload'  => array(
					'progress' => 0,
				),
				'migrate' => array(
					'progress' => 0,
				),
			);
			$instawp_zip         = new InstaWP_ZipClass();
			$instawp_plugin      = new instaWP();
			$backup_options      = array(
				'ismerge'      => '',
				'backup_files' => 'files+db',
				'local'        => '1',
				'type'         => 'Manual',
				'action'       => 'backup',
			);
			$backup_options      = apply_filters( 'INSTAWP_CONNECT/Filters/migrate_backup_options', $backup_options );
			$incomplete_task_ids = InstaWP_taskmanager::is_there_any_incomplete_task_ids();

			if ( empty( $incomplete_task_ids ) ) {
				$pre_backup_response = $instawp_plugin->pre_backup( $backup_options );
				$migrate_task_id     = InstaWP_Setting::get_args_option( 'task_id', $pre_backup_response );
			} else {
				$migrate_task_id = reset( $incomplete_task_ids );
			}

			$migrate_task_obj = new InstaWP_Backup_Task( $migrate_task_id );
			$migrate_task     = InstaWP_taskmanager::get_task( $migrate_task_id );

			// Getting the migrate_id
			if ( empty( $migrate_id = InstaWP_Setting::get_args_option( 'migrate_id', $migrate_task ) ) ) {

				$migrate_response = InstaWP_Curl::do_curl( 'migrates',
					array(
						'source_domain'  => site_url(),
						'php_version'    => '6.0',
						'plugin_version' => '2.0',
					)
				);
				$migrate_id       = isset( $migrate_response['data']['migrate_id'] ) ? $migrate_response['data']['migrate_id'] : '';

				$migrate_task['migrate_id'] = $migrate_id;

				InstaWP_taskmanager::update_task( $migrate_task );
			}


			// Backing up the files
			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $key => $data ) {

				$backup_status = InstaWP_Setting::get_args_option( 'backup_status', $data );

				if ( 'completed' != $backup_status && 'backup_db' == $key ) {
					$backup_database = new InstaWP_Backup_Database();
					$backup_response = $backup_database->backup_database( $data, $migrate_task_id );

					if ( INSTAWP_SUCCESS == InstaWP_Setting::get_args_option( 'result', $backup_response ) ) {
						$migrate_task['options']['backup_options']['backup'][ $key ]['files'] = $backup_response['files'];
					} else {
						$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status'] = 'in_progress';
					}

					$packages = instawp_get_packages( $migrate_task_obj, $migrate_task['options']['backup_options']['backup'][ $key ] );
					$result   = instawp_build_zip_files( $migrate_task_obj, $packages, $migrate_task['options']['backup_options']['backup'][ $key ] );

					if ( isset( $result['files'] ) && ! empty( $result['files'] ) ) {
						$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files']     = $result['files'];
						$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status'] = 'completed';
					}

					InstaWP_taskmanager::update_task( $migrate_task );
					break;
				}

				if ( 'completed' != $backup_status ) {

					$migrate_task['options']['backup_options']['backup'][ $key ]['files'] = $migrate_task_obj->get_need_backup_files( $migrate_task['options']['backup_options']['backup'][ $key ] );

					$packages = instawp_get_packages( $migrate_task_obj, $migrate_task['options']['backup_options']['backup'][ $key ] );
					$result   = instawp_build_zip_files( $migrate_task_obj, $packages, $migrate_task['options']['backup_options']['backup'][ $key ] );

					if ( isset( $result['files'] ) && ! empty( $result['files'] ) ) {
						$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files']     = $result['files'];
						$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status'] = 'completed';
					}

					InstaWP_taskmanager::update_task( $migrate_task );

					break;
				}
			}


			// Cleaning the non-zipped files and folders
			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $key => $data ) {

				$backup_status    = InstaWP_Setting::get_args_option( 'backup_status', $data );
				$backup_progress  = (int) InstaWP_Setting::get_args_option( 'backup_progress', $data );
				$temp_folder_path = isset( $data['path'] ) && isset( $data['prefix'] ) ? $data['path'] . 'temp-' . $data['prefix'] : '';

				if ( 'completed' == $backup_status ) {

					if ( isset( $data['sql_file_name'] ) && is_file( $data['sql_file_name'] ) && file_exists( $data['sql_file_name'] ) ) {
						@unlink( $data['sql_file_name'] );
					}

					if ( is_dir( $temp_folder_path ) ) {
						@rmdir( $temp_folder_path );
					}

					$migrate_task['options']['backup_options']['backup'][ $key ]['backup_progress'] = $backup_progress + round( 100 / 5 );

					InstaWP_taskmanager::update_task( $migrate_task );
				}
			}


			// Creating  the backup
			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $key => $data ) {

				$upload_status   = InstaWP_Setting::get_args_option( 'upload_status', $data );
				$upload_progress = (int) InstaWP_Setting::get_args_option( 'upload_progress', $data );

				if ( 'completed' != $upload_status ) {

					$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files_path']  = self::get_upload_files( $data );
					$migrate_task['options']['backup_options']['backup'][ $key ]['upload_status']   = 'completed';
					$migrate_task['options']['backup_options']['backup'][ $key ]['upload_progress'] = $upload_progress + round( 100 / 5 );

					InstaWP_taskmanager::update_task( $migrate_task );
					break;
				}
			}


			// Generating progresses
			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $key => $data ) {

				$backup_progress  = (int) InstaWP_Setting::get_args_option( 'backup_progress', $data );
				$upload_progress  = (int) InstaWP_Setting::get_args_option( 'upload_progress', $data );
				$migrate_progress = (int) InstaWP_Setting::get_args_option( 'migrate_progress', $data );

				$response['backup']['progress']  = (int) $response['backup']['progress'] + $backup_progress;
				$response['upload']['progress']  = (int) $response['upload']['progress'] + $upload_progress;
				$response['migrate']['progress'] = (int) $response['migrate']['progress'] + $migrate_progress;
			}

			wp_send_json_success( $response );
		}


		public static function get_upload_files( $data = array() ) {

			$files_path     = InstaWP_Setting::get_args_option( 'path', $data );
			$zip_files_path = array();

			foreach ( InstaWP_Setting::get_args_option( 'zip_files', $data, array() ) as $zip_file ) {

				$filename  = InstaWP_Setting::get_args_option( 'file_name', $zip_file );
				$part_size = InstaWP_Setting::get_args_option( 'size', $zip_file );

				if ( ! empty( $filename ) && ! empty( $part_size ) ) {
					$zip_files_path[] = array(
						'filename'      => $files_path . $filename,
						'part_size'     => $part_size,
						'content_type'  => 'file',
						'source_status' => 'completed',
						'part_number'   => 0,
					);
				}
			}

			return $zip_files_path;
		}


		function connect_api_url() {

			$return_url      = urlencode( admin_url( 'admin.php?page=instawp' ) );
			$connect_api_url = InstaWP_Setting::get_api_domain() . '/authorize?source=InstaWP Connect&return_url=' . $return_url;

			wp_send_json_success( array( 'connect_url' => $connect_api_url ) );
		}


		function update_settings() {

			$_form_data = isset( $_REQUEST['form_data'] ) ? wp_kses_post( $_REQUEST['form_data'] ) : '';
			$_form_data = str_replace( 'amp;', '', $_form_data );

			parse_str( $_form_data, $form_data );

			$settings_nonce = InstaWP_Setting::get_args_option( 'instawp_settings_nonce', $form_data );

			if ( ! wp_verify_nonce( $settings_nonce, 'instawp_settings_nonce_action' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed. Please try again reloading the page.' ) ) );
			}

			foreach ( InstaWP_Setting::get_migrate_settings_fields() as $field_id ) {
				if ( isset( $form_data[ $field_id ] ) ) {
					InstaWP_Setting::update_option( $field_id, InstaWP_Setting::get_args_option( $field_id, $form_data ) );
				}
			}

			wp_send_json_success( array( 'message' => esc_html__( 'Success. Settings updated.' ) ) );

			die();
		}


		/**
		 * @return void
		 */
		function enqueue_styles_scripts() {

			wp_enqueue_style( 'instawp-migrate', instawp()::get_asset_url( 'migrate/assets/css/style.css' ), [], current_time( 'U' ) );

			wp_enqueue_script( 'instawp-tailwind', instawp()::get_asset_url( 'migrate/assets/js/tailwind.js' ) );
			wp_enqueue_script( 'instawp-migrate', instawp()::get_asset_url( 'migrate/assets/js/scripts.js' ), array( 'instawp-tailwind' ), current_time( 'U' ) );
			wp_localize_script( 'instawp-migrate', 'instawp_migrate',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
		}


		/**
		 * @return void
		 */
		function render_migrate_page() {
			include INSTAWP_PLUGIN_DIR . '/migrate/templates/main.php';
		}


		/**
		 * @return void
		 */
		function add_migrate_menu() {
			add_menu_page(
				esc_html__( 'InstaWP', 'instawp-connect' ),
				esc_html__( 'InstaWP', 'instawp-connect' ),
				'administrator', 'instawp',
				array( $this, 'render_migrate_page' ),
				esc_url( INSTAWP_PLUGIN_IMAGES_URL . 'cloud.svg' ),
				2
			);
		}


		/**
		 * @return INSTAWP_Migration
		 */
		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}
}

INSTAWP_Migration::instance();