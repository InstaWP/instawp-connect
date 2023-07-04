<?php
/**
 * All helper functions here
 */


if ( ! function_exists( 'instawp_create_db_tables' ) ) {
	/**
	 * @return void
	 */
	function instawp_create_db_tables() {

		if ( ! function_exists( 'maybe_create_table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$sql_create_staging_site_table = "CREATE TABLE " . INSTAWP_DB_TABLE_STAGING_SITES . " (
			id int(50) NOT NULL AUTO_INCREMENT,
			task_id varchar(255) NOT NULL,
			connect_id varchar(255) NOT NULL,
			site_name varchar(255) NOT NULL,
			site_url varchar(255) NOT NULL,
			admin_email varchar(255) NOT NULL,
			username varchar(255) NOT NULL,
			password varchar(255) NOT NULL,
			auto_login_hash varchar(255) NOT NULL,
			datetime  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
    	);";

		maybe_create_table( INSTAWP_DB_TABLE_STAGING_SITES, $sql_create_staging_site_table );


		$sql_create_events_table = "CREATE TABLE " . INSTAWP_DB_TABLE_EVENTS . " (
			id int(20) NOT NULL AUTO_INCREMENT,
			event_name varchar(128) NOT NULL,
			event_slug varchar(128) NOT NULL,
			event_type varchar(128) NOT NULL,
			source_id int(20) NOT NULL,
			title text NOT NULL,
			details longtext NOT NULL,
			user_id int(20) NOT NULL,
			date datetime NOT NULL,
			prod varchar(128) NOT NULL,
			status ENUM ('pending','in_progress','completed','error') DEFAULT 'pending',
			synced_message varchar(128),
			PRIMARY KEY  (id)
        ) ";

		maybe_create_table( INSTAWP_DB_TABLE_EVENTS, $sql_create_events_table );

		$sql_create_sync_history_table = "CREATE TABLE " . INSTAWP_DB_TABLE_EVENT_SITES . " (
            id int(20) NOT NULL AUTO_INCREMENT,
            event_id int(20) NOT NULL,
            connect_id int(20) NOT NULL,
			status ENUM ('pending','in_progress','completed','error') DEFAULT 'pending',
            date datetime NOT NULL,
            PRIMARY KEY  (id)
        )";

		maybe_create_table( INSTAWP_DB_TABLE_EVENT_SITES, $sql_create_sync_history_table );

		$sql_create_event_sites_table = "CREATE TABLE " . INSTAWP_DB_TABLE_SYNC_HISTORY . " (
            id int(20) NOT NULL AUTO_INCREMENT,
            encrypted_contents longtext NOT NULL,
            changes longtext NOT NULL,
            sync_response longtext NOT NULL,
            direction varchar(128) NOT NULL,
            status varchar(128) NOT NULL,
            user_id int(20) NOT NULL,
            changes_sync_id int(20) NOT NULL,
            sync_message varchar(128) NOT NULL,
            source_connect_id int(20) NOT NULL,
            source_url varchar(128),
            date datetime NOT NULL,
            PRIMARY KEY  (id)
            ) ";

		maybe_create_table( INSTAWP_DB_TABLE_SYNC_HISTORY, $sql_create_event_sites_table );
	}
}


if ( ! function_exists( 'instawp_staging_insert_site' ) ) {
	/**
	 * @param $args
	 *
	 * @return bool
	 */
	function instawp_staging_insert_site( $args = array() ) {

		global $wpdb;

		$task_id         = isset( $args['task_id'] ) ? $args['task_id'] : '';
		$connect_id      = isset( $args['connect_id'] ) ? $args['connect_id'] : '';
		$site_name       = isset( $args['site_name'] ) ? $args['site_name'] : '';
		$site_url        = isset( $args['site_url'] ) ? $args['site_url'] : '';
		$admin_email     = isset( $args['admin_email'] ) ? $args['admin_email'] : '';
		$username        = isset( $args['username'] ) ? $args['username'] : '';
		$password        = isset( $args['password'] ) ? $args['password'] : '';
		$auto_login_hash = isset( $args['auto_login_hash'] ) ? $args['auto_login_hash'] : '';
		$is_error        = false;

		// Check if any value is empty
		foreach ( $args as $key => $value ) {
			if ( empty( $value ) ) {
				$is_error = true;

				error_log( sprintf( esc_html__( 'Empty value for %s', 'instawp-connect' ), $key ) );
				break;
			}
		}

		if ( $is_error ) {
			return false;
		}

		$insert_response = $wpdb->insert( INSTAWP_DB_TABLE_STAGING_SITES,
			array(
				'task_id'         => $task_id,
				'connect_id'      => $connect_id,
				'site_name'       => $site_name,
				'site_url'        => $site_url,
				'admin_email'     => $admin_email,
				'username'        => $username,
				'password'        => $password,
				'auto_login_hash' => $auto_login_hash,
			)
		);

		if ( ! $insert_response ) {
			error_log( sprintf( esc_html__( 'Error occurred while inserting new site. Error Message: %s', 'instawp-connect' ), $wpdb->last_error ) );

			return false;
		}

		return true;
	}
}


if ( ! function_exists( 'instawp' ) ) {
	/**
	 * @return instaWP
	 */
	function instawp() {
		global $instawp;

		if ( empty( $instawp ) ) {
			$instawp = new instaWP();
		}

		return $instawp;
	}
}


if ( ! function_exists( 'instawp_get_packages' ) ) {
	function instawp_get_packages( $instawp_task, $data = array() ) {

		if ( ! class_exists( 'InstaWP_ZipClass' ) ) {
			include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-zipclass.php';
		}

		if ( ! $instawp_task instanceof InstaWP_Backup_Task ) {
			return array();
		}

		$instawp_zip = new InstaWP_ZipClass();
		$packages    = $instawp_task->get_packages_info( $data['key'] );

		if ( ! $packages ) {

			if ( isset( $data['plugin_subpackage'] ) ) {
				$ret = $instawp_zip->get_plugin_packages( $data );
			} elseif ( isset( $data['uploads_subpackage'] ) ) {
				$ret = $instawp_zip->get_upload_packages( $data );
			} else {
				if ( $data['key'] == INSTAWP_BACKUP_TYPE_MERGE ) {
					$ret = $instawp_zip->get_packages( $data, true );
				} else {
					$ret = $instawp_zip->get_packages( $data );
				}
			}

			$packages = $instawp_task->set_packages_info( $data['key'], $ret['packages'] );
		}

		return $packages;
	}
}


if ( ! function_exists( 'instawp_build_zip_files' ) ) {
	function instawp_build_zip_files( $instawp_task, $packages = array(), $data = array() ) {

		if ( ! class_exists( 'InstaWP_ZipClass' ) ) {
			include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-zipclass.php';
		}

		if ( ! $instawp_task instanceof InstaWP_Backup_Task ) {
			return array();
		}

		$result      = array();
		$instawp_zip = new InstaWP_ZipClass();

		foreach ( $packages as $package ) {

			instawp()->set_time_limit( $instawp_task->get_id() );

			if ( ! empty( $package['files'] ) && ! $package['backup'] ) {

				if ( isset( $data['uploads_subpackage'] ) ) {
					$files = $instawp_zip->get_upload_files_from_cache( $package['files'] );
				} else {
					$files = $package['files'];
				}

				if ( empty( $files ) ) {
					continue;
				}

				$zip_ret = $instawp_zip->_zip( $package['path'], $files, $data, $package['json'] );

				if ( $zip_ret['result'] == INSTAWP_SUCCESS ) {

					if ( isset( $data['uploads_subpackage'] ) ) {
						if ( file_exists( $package['files'] ) ) {
							@unlink( $package['files'] );
						}
					}

					$result['files'][] = $zip_ret['file_data'];
					$package['backup'] = true;

					$instawp_task->update_packages_info( $data['key'], $package, $zip_ret['file_data'] );
				}
			}
		}

		return $result;
	}
}


if ( ! function_exists( 'instawp_get_overall_migration_progress' ) ) {
	/**
	 * Calculate and return overall progress
	 *
	 * @param $migrate_id
	 *
	 * @return int|mixed|null
	 */
	function instawp_get_overall_migration_progress( $migrate_id = '' ) {

		$overall_progress = 0;

		if ( empty( $migrate_id ) || 0 == $migrate_id ) {
			return $overall_progress;
		}

		$status_response = InstaWP_Curl::do_curl( "migrates/{$migrate_id}/get_parts_status", array(), array(), false );
		$response_data   = InstaWP_Setting::get_args_option( 'data', $status_response, array() );
		$migrate_parts   = InstaWP_Setting::get_args_option( 'migrate_parts', $response_data, array() );
		$migrate_parts   = array_map( function ( $migrate_part ) {
			$restore_progress = InstaWP_Setting::get_args_option( 'restore_progress', $migrate_part );
			if ( ! $restore_progress || $restore_progress == 'null' ) {
				return 0;
			}

			return (int) $restore_progress;
		}, $migrate_parts );

		if ( count( $migrate_parts ) > 0 ) {
			$overall_progress = round( array_sum( $migrate_parts ) / count( $migrate_parts ) );
		}

		return apply_filters( 'INSTAWP_CONNECT/Filters/get_overall_migration_progress', $overall_progress, $migrate_id );
	}
}


if ( ! function_exists( 'instawp_get_migration_site_detail' ) ) {
	/**
	 * Return migration site detail
	 *
	 * @param $migrate_id
	 *
	 * @return array|mixed|null
	 */
	function instawp_get_migration_site_detail( $migrate_id = '' ) {

		if ( empty( $migrate_id ) || 0 == $migrate_id ) {
			return array();
		}

		$api_response    = InstaWP_Curl::do_curl( "migrates/{$migrate_id}", array(), array(), false );
		$response_data   = InstaWP_Setting::get_args_option( 'data', $api_response, array() );
		$site_detail     = InstaWP_Setting::get_args_option( 'site_detail', $response_data, array() );
		$auto_login_hash = InstaWP_Setting::get_args_option( 'auto_login_hash', $site_detail );

		if ( ! empty( $auto_login_hash ) ) {
			$site_detail['auto_login_url'] = sprintf( '%s/wordpress-auto-login?site=%s', InstaWP_Setting::get_api_domain(), $auto_login_hash );
		} else {
			$site_detail['auto_login_url'] = InstaWP_Setting::get_args_option( 'url', $site_detail );
		}

		return apply_filters( 'INSTAWP_CONNECT/Filters/get_migration_site_detail', $site_detail, $migrate_id );
	}
}


if ( ! function_exists( 'instawp_reset_running_migration' ) ) {
	/**
	 * Reset running migration
	 *
	 * @param $reset_type
	 *
	 * @return bool
	 */
	function instawp_reset_running_migration( $reset_type = 'soft', $force_timeout = true ) {

		$reset_type = empty( $reset_type ) ? InstaWP_Setting::get_option( 'instawp_reset_type', 'soft' ) : $reset_type;

		if ( ! in_array( $reset_type, array( 'soft', 'hard', 'task_only' ) ) ) {
			return false;
		}

		InstaWP_taskmanager::delete_all_task();
		$task = new InstaWP_Backup();
		$task->clean_backup();

		if ( 'task_only' == $reset_type ) {
			return true;
		}

		if ( 'hard' == $reset_type ) {
			delete_option( 'instawp_api_key' );
			delete_option( 'instawp_api_options' );
			delete_option( 'instawp_compress_setting' );

			update_option( 'instawp_api_url', esc_url_raw( 'https://app.instawp.io' ) );
		}

		if ( $force_timeout === true || $force_timeout == 1 ) {
			$response = InstaWP_Curl::do_curl( "migrates/force-timeout", array( 'source_domain' => site_url() ) );

			if ( isset( $response['success'] ) && ! $response['success'] ) {
				error_log( json_encode( $response ) );
			}
		}

		return true;
	}
}


if ( ! function_exists( 'instawp_backup_files' ) ) {
	/**
	 * @param InstaWP_Backup_Task $migrate_task_obj
	 *
	 * @return void
	 */
	function instawp_backup_files( InstaWP_Backup_Task $migrate_task_obj, $args = array() ) {

		$migrate_task = InstaWP_taskmanager::get_task( $migrate_task_obj->get_id() );

		foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_obj->get_id() ) as $key => $data ) {

			$backup_status = InstaWP_Setting::get_args_option( 'backup_status', $data );

			if ( 'completed' != $backup_status && 'backup_db' == $key ) {
				$backup_database = new InstaWP_Backup_Database();
				$backup_response = $backup_database->backup_database( $data, $migrate_task_obj->get_id() );

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
			}
		}

		if ( InstaWP_Setting::get_args_option( 'clean_non_zip', $args, false ) === true ) {
			instawp_clean_non_zipped_files_folder( $migrate_task );
		}
	}
}


if ( ! function_exists( 'instawp_update_migration_status' ) ) {
	/**
	 * @param $migrate_id
	 * @param $part_id
	 * @param $args
	 *
	 * @return array
	 */
	function instawp_update_migration_status( $migrate_id = '', $part_id = '', $args = array() ) {

		if ( empty( $migrate_id ) || $migrate_id == 0 || empty( $part_id ) || $part_id == 0 ) {
			return array( 'success' => false, 'message' => esc_html__( 'Invalid migrate or part ID', 'instawp-connect' ) );
		}

		$defaults    = array(
			'type'     => 'restore',
			'progress' => 100,
			'message'  => esc_html__( 'Restore completed for this part', 'instawp-connect' ),
			'status'   => 'completed'
		);
		$status_args = wp_parse_args( $args, $defaults );

		return InstaWP_Curl::do_curl( "migrates/{$migrate_id}/parts/{$part_id}", $status_args, array(), 'patch' );
	}
}


if ( ! function_exists( 'instawp_upload_to_cloud' ) ) {
	/**
	 * Upload file to presigned url
	 *
	 * @param $cloud_url
	 * @param $local_file
	 * @param $args
	 *
	 * @return bool
	 */
	function instawp_upload_to_cloud( $cloud_url = '', $local_file = '', $args = array() ) {

		if ( empty( $cloud_url ) || empty( $local_file ) ) {
			return false;
		}

		$useragent    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$default_args = array(
			'method'     => 'PUT',
			'body'       => file_get_contents( $local_file ),
			'timeout'    => 0,
			'decompress' => false,
			'stream'     => false,
			'filename'   => '',
			'user-agent' => $useragent,
			'headers'    => array(
				'Content-Type' => 'multipart/form-data'
			),
			'upload'     => true
		);
		$upload_args  = wp_parse_args( $args, $default_args );

		for ( $i = 0; $i < INSTAWP_REMOTE_CONNECT_RETRY_TIMES; $i ++ ) {

			$WP_Http_Curl = new WP_Http_Curl();
			$response     = $WP_Http_Curl->request( $cloud_url, $upload_args );

			if ( isset( $response['response']['code'] ) && 200 == $response['response']['code'] ) {
				return true;
			}
		}

		return false;
	}
}


if ( ! function_exists( 'instawp_get_upload_files' ) ) {
	/**
	 * Get files as array that will be uploaded
	 *
	 * @param $data
	 *
	 * @return array
	 */
	function instawp_get_upload_files( $data = array() ) {

		$files_path     = InstaWP_Setting::get_args_option( 'path', $data );
		$zip_files_path = array();

		foreach ( InstaWP_Setting::get_args_option( 'zip_files', $data, array() ) as $zip_file ) {

			$filename    = InstaWP_Setting::get_args_option( 'file_name', $zip_file );
			$part_size   = InstaWP_Setting::get_args_option( 'size', $zip_file );
			$part_number = $_COOKIE['part_number'] ?? 1;

			if ( ! empty( $filename ) && ! empty( $part_size ) ) {
				$zip_files_path[] = array(
					'filename'      => $files_path . $filename,
					'part_size'     => $part_size,
					'content_type'  => 'file',
					'source_status' => 'pending',
					'part_number'   => ++ $part_number,
				);

				setcookie( 'part_number', $part_number );
			}
		}

		return $zip_files_path;
	}
}


if ( ! function_exists( 'instawp_get_response_progresses' ) ) {
	/**
	 * Return response with progresses
	 *
	 * @param $migrate_task_id
	 * @param $migrate_id
	 * @param $response
	 * @param $args
	 *
	 * @return array|mixed
	 */
	function instawp_get_response_progresses( $migrate_task_id = '', $migrate_id = '', $response = array(), $args = array() ) {

		if ( ! empty( $migrate_task_id ) ) {
			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $data ) {

				$backup_progress = (int) InstaWP_Setting::get_args_option( 'backup_progress', $data, '0' );
				$upload_progress = (int) InstaWP_Setting::get_args_option( 'upload_progress', $data, '0' );

				$response['backup']['progress'] = (int) $response['backup']['progress'] + $backup_progress;
				$response['upload']['progress'] = (int) $response['upload']['progress'] + $upload_progress;
			}
		}

		// update backup api
		instawp_update_backup_progress( $migrate_task_id );

		if ( ! empty( $migrate_id ) && $response['backup']['progress'] >= 100 && $response['upload']['progress'] >= 100 ) {

			$overall_migration_progress        = instawp_get_overall_migration_progress( $migrate_id );
			$response['migrate']['progress']   = $overall_migration_progress;
			$response['migrate']['migrate_id'] = $migrate_id;

			if ( $overall_migration_progress >= 100 ) {

				$migration_site_detail          = instawp_get_migration_site_detail( $migrate_id );
				$response['migrate']['message'] = esc_html__( 'Migration completed successfully.', 'instawp-connect' );
				$response['site_detail']        = $migration_site_detail;
				$response['status']             = 'completed';

				instawp_staging_insert_site(
					array(
						'task_id'         => $migrate_task_id,
						'connect_id'      => InstaWP_Setting::get_args_option( 'connect_id', $migration_site_detail ),
						'site_name'       => str_replace( array( 'https://', 'http://' ), '', InstaWP_Setting::get_args_option( 'url', $migration_site_detail ) ),
						'site_url'        => InstaWP_Setting::get_args_option( 'url', $migration_site_detail ),
						'admin_email'     => InstaWP_Setting::get_args_option( 'wp_admin_email', $migration_site_detail ),
						'username'        => InstaWP_Setting::get_args_option( 'wp_username', $migration_site_detail ),
						'password'        => InstaWP_Setting::get_args_option( 'wp_password', $migration_site_detail ),
						'auto_login_hash' => InstaWP_Setting::get_args_option( 'auto_login_hash', $migration_site_detail ),
					)
				);

				if ( false !== InstaWP_Setting::get_args_option( 'delete_task', $args, true ) ) {
					InstaWP_taskmanager::delete_task( $migrate_task_id );
				}
			}
		}


		// Generate parts urls and return with the response
		if (
			true === InstaWP_Setting::get_args_option( 'generate_remote_parts_urls', $args, false ) ||
			true === InstaWP_Setting::get_args_option( 'generate_local_parts_urls', $args, false )
		) {

			$part_urls      = array();
			$part_number    = 1;
			$zip_files_type = '';

			if ( true === InstaWP_Setting::get_args_option( 'generate_local_parts_urls', $args, false ) ) {
				$zip_files_type = 'zip_files';
			} else if ( true === InstaWP_Setting::get_args_option( 'generate_local_parts_urls', $args, false ) ) {
				$zip_files_type = 'zip_files_path';
			}

			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $data ) {

				foreach ( InstaWP_Setting::get_args_option( $zip_files_type, $data, array() ) as $zip_file ) {

					$part_id   = InstaWP_Setting::get_args_option( 'part_id', $zip_file );
					$part_url  = InstaWP_Setting::get_args_option( 'part_url', $zip_file );
					$file_name = InstaWP_Setting::get_args_option( 'file_name', $zip_file );
					$part_size = InstaWP_Setting::get_args_option( 'size', $zip_file );

					if ( $zip_files_type == 'zip_files_path' && ( empty( $part_id ) || $part_id == 0 ) ) {
						continue;
					}

					if ( $zip_files_type == 'zip_files_path' ) {
						$part_url_arr['part_url'] = site_url( 'wp-content/' . INSTAWP_DEFAULT_BACKUP_DIR . '/' . $part_url );
					}

					if ( $zip_files_type == 'zip_files' ) {
						$part_url_arr['url'] = site_url( 'wp-content/' . INSTAWP_DEFAULT_BACKUP_DIR . '/' . $file_name );
					}

					$part_url_arr['part_id']      = $part_id;
					$part_url_arr['part_number']  = $part_number ++;
					$part_url_arr['part_size']    = $part_size;
					$part_url_arr['filename']     = $file_name;
					$part_url_arr['content_type'] = 'file';

					$part_urls[] = $part_url_arr;
				}
			}

			$response['part_urls'] = $part_urls;
		}

		return $response;
	}
}


if ( ! function_exists( 'instawp_update_backup_progress' ) ) {
	/**
	 * update backup progress for migrate id
	 *
	 * @param $migrate_task_id
	 * @param $migrate_id
	 *
	 * @return bool
	 */
	function instawp_update_backup_progress( $migrate_task_id = '', $migrate_id = '' ) {

		$backup_progress = 0;
		$response        = array( 'success' => false );

		foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $key => $data ) {
			$backup_progress += (int) InstaWP_Setting::get_args_option( 'backup_progress', $data, '0' );
		}

		$backup_progress = min( $backup_progress, 100 );
		$migrate_id      = empty( $migrate_id ) ? InstaWP_taskmanager::get_migrate_id( $migrate_task_id ) : $migrate_id;

		if ( empty( $migrate_id ) ) {
			return false;
		}

		if ( $backup_progress < 100 ) {
			$response = InstaWP_Curl::do_curl( "migrates/{$migrate_id}/backup-progress", array( 'backup_progress' => $backup_progress ) );
		}

		return (bool) $response['success'] ?? false;
	}
}


if ( ! function_exists( 'instawp_copy_php_settings' ) ) {
	/**
	 * Update php settings to pass on the destination website
	 *
	 * @return void
	 */
	function instawp_copy_php_settings() {

		$php_settings = array(
			'php_version'         => phpversion(),
			'max_execution_time'  => ini_get( 'max_execution_time' ),
			'max_input_time'      => ini_get( 'max_input_time' ),
			'max_input_vars'      => ini_get( 'max_input_vars' ),
			'memory_limit'        => ini_get( 'memory_limit' ),
			'allow_url_fopen'     => ini_get( 'allow_url_fopen' ),
			'post_max_size'       => ini_get( 'post_max_size' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
		);

		update_option( 'instawp_php_settings', $php_settings );
	}
}


if ( ! function_exists( 'instawp_clean_non_zipped_files_folder' ) ) {
	/**
	 * Cleaning the non-zipped files and folders
	 *
	 * @return void
	 */
	function instawp_clean_non_zipped_files_folder( $migrate_task ) {

		if ( empty( $migrate_task_id = InstaWP_Setting::get_args_option( 'id', $migrate_task ) ) ) {
			return;
		}

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
	}
}

if ( ! function_exists( 'instawp_domain_search' ) ) {
	/**
	 * Domain search using Rapid API
	 *
	 * @param $domain_name
	 *
	 * @return array|mixed
	 */
	function instawp_domain_search( $domain_name = '' ) {

		if ( empty( $domain_name ) ) {
			return [];
		}

		$curl = curl_init();

		curl_setopt_array( $curl, [
			CURLOPT_URL            => "https://domainr.p.rapidapi.com/v2/status?domain={$domain_name}",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => "",
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => "GET",
			CURLOPT_HTTPHEADER     => [
				"X-RapidAPI-Host: domainr.p.rapidapi.com",
				"X-RapidAPI-Key: f78d769ac8msh4df66b894ce80ddp1669a7jsn0fd293b9f64d"
			],
		] );

		$response = curl_exec( $curl );
		$err      = curl_error( $curl );

		curl_close( $curl );

		if ( $err ) {
			return [];
		}

		$response = json_decode( $response, true );

		return $response['status'][0] ?? array();
	}
}

if ( ! function_exists( 'get_connect_id' ) ) {
	/**
	 * get connect id for source site
	 *
	 * @return int
	 */
	function get_connect_id() {
		$connect_options = get_option( 'instawp_connect_id_options' );

		return $connect_options['data']['id'];
	}
}

if ( ! function_exists( 'instawp_uuid' ) ) {
	/**
	 * get random string
	 *
	 * @return string
	 */
	function instawp_uuid( $length = 6 ) {
		return bin2hex( random_bytes( $length ) );
	}
}

if ( ! function_exists( 'instawp_get_post_type_singular_name' ) ) {
	/**
	 * get post type singular name
	 *
	 * @param $post_type
	 *
	 * @return string
	 */
	function instawp_get_post_type_singular_name( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		if ( ! empty( $post_type_object ) ) {
			return $post_type_object->labels->singular_name;
		}

		return '';
	}
}


if ( ! function_exists( 'instawp_get_post_by_name' ) ) {
	/**
	 * get post type singular name
	 *
	 * @param $post_name
	 * @param $post_type
	 *
	 * @return string
	 */
	function instawp_get_post_by_name( $post_name, $post_type ) {
		global $wpdb;
		$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type= %s ", $post_name, $post_type ) );
		if ( $post ) {
			return get_post( $post );
		}

		return null;
	}
}