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
			add_action( 'wp_ajax_instawp_reset_plugin', array( $this, 'reset_plugin' ) );
			add_action( 'wp_ajax_instawp_abort_migration', array( $this, 'abort_migration' ) );
		}


		function abort_migration() {
			instawp_reset_running_migration();

			wp_send_json_success( array( 'message' => esc_html__( 'Migration is aborted successfully.' ) ) );
		}


		function reset_plugin() {

			$reset_type = isset( $_POST['reset_type'] ) ? sanitize_text_field( $_POST['reset_type'] ) : '';
			$reset_type = empty( $reset_type ) ? InstaWP_Setting::get_option( 'instawp_reset_type', 'soft' ) : $reset_type;

			if ( ! in_array( $reset_type, array( 'soft', 'hard' ) ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid reset type.' ) ) );
			}

			if ( ! instawp_reset_running_migration( $reset_type ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Plugin reset unsuccessful.' ) ) );
			}

			wp_send_json_success( array( 'message' => esc_html__( 'Plugin reset successfully.' ) ) );
		}


		function connect_migrate() {

			if ( ! class_exists( 'InstaWP_ZipClass' ) ) {
				include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-zipclass.php';
			}

			$_settings = isset( $_POST['settings'] ) ? $_POST['settings'] : '';
			parse_str( $_settings, $settings );
			$instawp_migrate  = InstaWP_Setting::get_args_option( 'instawp_migrate', $settings, [] );
			$migrate_options  = InstaWP_Setting::get_args_option( 'options', $instawp_migrate, [] );
			$migrate_settings = [];

			foreach ( $migrate_options as $migrate_option ) {
				$migrate_settings[ $migrate_option ] = true;
			}

			$response = array(
				'backup'  => array(
					'progress' => 0,
				),
				'upload'  => array(
					'progress' => 0,
				),
				'migrate' => array(
					'progress' => 0,
				),
				'status'  => 'running',
			);

			$instawp_zip         = new InstaWP_ZipClass();
			$instawp_plugin      = new instaWP();
			$backup_options      = array(
				'ismerge'      => '',
				'backup_files' => 'files+db',
				'local'        => '1',
				'type'         => 'Manual',
				'action'       => 'backup',
				'is_migrate'   => true,
				'settings'     => $migrate_settings,
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

				$migrate_args     = array(
					'source_domain'  => site_url(),
					'php_version'    => '6.0',
					'plugin_version' => '2.0',
				);
				$migrate_response = InstaWP_Curl::do_curl( 'migrates', $migrate_args );
				$migrate_id       = isset( $migrate_response['data']['migrate_id'] ) ? $migrate_response['data']['migrate_id'] : '';

				$migrate_task['migrate_id'] = $migrate_id;

				InstaWP_taskmanager::update_task( $migrate_task );
			}

			if ( empty( $migrate_id ) ) {
				wp_send_json_success( $response );
			}

			// copy current php settings
			instawp_copy_php_settings();


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
						$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files']       = $result['files'];
						$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status']   = 'completed';
						$migrate_task['options']['backup_options']['backup'][ $key ]['backup_progress'] = 20;
					}

					InstaWP_taskmanager::update_task( $migrate_task );
					break;
				}

				if ( 'completed' != $backup_status ) {

					$migrate_task['options']['backup_options']['backup'][ $key ]['files'] = $migrate_task_obj->get_need_backup_files( $migrate_task['options']['backup_options']['backup'][ $key ] );

					$packages = instawp_get_packages( $migrate_task_obj, $migrate_task['options']['backup_options']['backup'][ $key ] );
					$result   = instawp_build_zip_files( $migrate_task_obj, $packages, $migrate_task['options']['backup_options']['backup'][ $key ] );

					if ( isset( $result['files'] ) && ! empty( $result['files'] ) ) {
						$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files']       = $result['files'];
						$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status']   = 'completed';
						$migrate_task['options']['backup_options']['backup'][ $key ]['backup_progress'] = 20;
					}

					InstaWP_taskmanager::update_task( $migrate_task );

					break;
				}
			}

			// Cleaning the non-zipped files and folders
			instawp_clean_non_zipped_files_folder( $migrate_task );


			$part_number_index = (int) InstaWP_Setting::get_args_option( 'part_number_index', $migrate_task, '0' );

			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $key => $data ) {

				foreach ( InstaWP_Setting::get_args_option( 'zip_files', $data, array() ) as $index => $zip_file ) {

					$part_number = (int) InstaWP_Setting::get_args_option( 'part_number', $zip_file );

					if ( empty( $part_number ) || $part_number == 0 ) {

						$part_number_index ++;

						$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files'][ $index ]['part_number'] = $part_number_index;

						$migrate_task['part_number_index'] = $part_number_index;
					}
				}
			}

			$pending_backups = array_map( function ( $data ) {

				if ( isset( $data['backup_status'] ) && $data['backup_status'] == 'completed' ) {
					return '';
				}

				return $data['key'] ?? '';
			}, InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) );
			$pending_backups = array_filter( array_values( $pending_backups ) );


			if ( empty( $pending_backups ) ) {

				// Hit the total part number api
				$part_number_index  = (int) InstaWP_Setting::get_args_option( 'part_number_index', $migrate_task, '0' );
				$part_number_update = InstaWP_Setting::get_args_option( 'part_number_update', $migrate_task );

				if ( $part_number_update != 'completed' ) {

					$total_parts_args     = array(
						'total_parts' => $part_number_index,
					);
					$total_parts_response = InstaWP_Curl::do_curl( 'migrates/' . $migrate_id . '/total-parts', $total_parts_args );

					if ( isset( $total_parts_response['data']['status'] ) && $total_parts_response['data']['status'] ) {
						$migrate_task['part_number_update'] = 'completed';
					}
				}
			}


			// Uploading files
			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $key => $data ) {

				$upload_progress = (int) InstaWP_Setting::get_args_option( 'upload_progress', $data );

				if ( empty( InstaWP_Setting::get_args_option( 'zip_files_path', $data, array() ) ) ) {

					$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files_path'] = instawp_get_upload_files( $data );

					InstaWP_taskmanager::update_task( $migrate_task );
					break;
				}

				if ( 'completed' != InstaWP_Setting::get_args_option( 'upload_status', $data ) ) {

					foreach ( InstaWP_taskmanager::get_task_backup_upload_data( $migrate_task_id, $key ) as $file_path_index => $file_path_args ) {

						if ( 'completed' != InstaWP_Setting::get_args_option( 'source_status', $file_path_args ) ) {

							$migrate_part_response = InstaWP_Curl::do_curl( 'migrates/' . $migrate_id . '/parts', $file_path_args );

							if ( $migrate_part_response && isset( $migrate_part_response['success'] ) && $migrate_part_response['success'] ) {

								$migrate_part_id  = isset( $migrate_part_response['data']['part_id'] ) ? $migrate_part_response['data']['part_id'] : '';
								$migrate_part_url = isset( $migrate_part_response['data']['part_url'] ) ? $migrate_part_response['data']['part_url'] : '';
								$upload_status    = instawp_upload_to_cloud( $migrate_part_url, $file_path_args['filename'] );

								if ( $upload_status ) {
									$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files_path'][ $file_path_index ]['part_id']       = $migrate_part_id;
									$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files_path'][ $file_path_index ]['part_url']      = $migrate_part_url;
									$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files_path'][ $file_path_index ]['source_status'] = 'completed';

									// update progress
									InstaWP_Curl::do_curl( "migrates/{$migrate_id}/parts/{$migrate_part_id}", array(
										'type'     => 'upload',
										'progress' => 100,
										'message'  => 'Successfully uploaded part - ' . $migrate_part_id,
										'status'   => 'completed',
									), array(), 'patch' );

									InstaWP_taskmanager::update_task( $migrate_task );
									break;
								}
							}
						}
					}

					$pending_zip_files = array_map( function ( $args ) {

						if ( 'pending' == InstaWP_Setting::get_args_option( 'source_status', $args ) ) {
							return $args;
						}

						return array();
					}, $migrate_task['options']['backup_options']['backup'][ $key ]['zip_files_path'] );
					$pending_zip_files = array_filter( $pending_zip_files );

					if ( empty( $pending_zip_files ) ) {
						$migrate_task['options']['backup_options']['backup'][ $key ]['upload_status']   = 'completed';
						$migrate_task['options']['backup_options']['backup'][ $key ]['upload_progress'] = $upload_progress + round( 100 / 5 );
					}

					InstaWP_taskmanager::update_task( $migrate_task );
					break;
				}
			}

			wp_send_json_success( instawp_get_response_progresses( $migrate_task_id, $migrate_id, $response ) );
		}


		function connect_api_url() {

			$return_url      = urlencode( admin_url( 'tools.php?page=instawp' ) );
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

				if ( ! isset( $form_data[ $field_id ] ) ) {
					continue;
				}

				$field_value = InstaWP_Setting::get_args_option( $field_id, $form_data );

				if ( 'instawp_api_key' == $field_id && ! empty( $field_value ) && $field_value != InstaWP_Setting::get_option( 'instawp_api_key' ) ) {

					$api_key_check_response = InstaWP_Backup_Api::config_check_key( $field_value );

					if ( isset( $api_key_check_response['error'] ) && $api_key_check_response['error'] == 1 ) {
						wp_send_json_error( array( 'message' => InstaWP_Setting::get_args_option( 'message', $api_key_check_response, esc_html__( 'Error. Invalid API Key', 'instawp-connect' ) ) ) );
					}

					continue;
				}

				InstaWP_Setting::update_option( $field_id, $field_value );
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
			add_management_page(
				esc_html__( 'InstaWP', 'instawp-connect' ),
				esc_html__( 'InstaWP', 'instawp-connect' ),
				'administrator', 'instawp',
				array( $this, 'render_migrate_page' ),
				1
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


