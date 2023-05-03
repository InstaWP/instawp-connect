<?php
/**
 * InstaWP Migration Process for hosting platforms
 */


if ( ! class_exists( 'INSTAWP_Migration_hosting' ) ) {
	class INSTAWP_Migration_hosting {

		public static function connect_migrate() {

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
				'status'  => 'running',
			);
			$instawp_plugin      = new instaWP();
			$backup_options      = array(
				'ismerge'        => '',
				'backup_files'   => 'files+db',
				'local'          => '1',
				'type'           => 'Manual',
				'action'         => 'backup',
				'is_migrate'     => true,
				'migration_mode' => 'hosting',
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
					'migration_mode' => 'hosting',
				);
				$migrate_response = InstaWP_Curl::do_curl( 'migrates', $migrate_args );
				$migrate_id       = isset( $migrate_response['data']['migrate_id'] ) ? $migrate_response['data']['migrate_id'] : '';

				$migrate_task['migrate_id'] = $migrate_id;

				InstaWP_taskmanager::update_task( $migrate_task );
			}

			if ( empty( $migrate_id ) ) {

//				InstaWP_taskmanager::delete_task( $migrate_task_id );

				return $response;
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

					$is_delete_files_or_folder = false;

					if ( isset( $data['sql_file_name'] ) && is_file( $data['sql_file_name'] ) && file_exists( $data['sql_file_name'] ) ) {
						@unlink( $data['sql_file_name'] );

						$is_delete_files_or_folder = true;
					}

					if ( is_dir( $temp_folder_path ) ) {
						@rmdir( $temp_folder_path );

						$is_delete_files_or_folder = true;
					}


					if ( $is_delete_files_or_folder ) {
						$migrate_task['options']['backup_options']['backup'][ $key ]['backup_progress'] = $backup_progress + round( 100 / 5 );
					}

					InstaWP_taskmanager::update_task( $migrate_task );
				}
			}


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


			return instawp_get_response_progresses( $migrate_task_id, $migrate_id, $response, array( 'generate_part_urls' => true ) );
		}
	}
}


