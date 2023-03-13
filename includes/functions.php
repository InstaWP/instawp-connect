<?php
/**
 * All helper functions here
 */


if ( ! function_exists( 'instawp_staging_create_db_table' ) ) {
	/**
	 * @return void
	 */
	function instawp_staging_create_db_table() {

		if ( ! function_exists( 'maybe_create_table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$sql_create_table = "CREATE TABLE " . INSTAWP_DB_TABLE_STAGING_SITES . " (
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

		maybe_create_table( INSTAWP_DB_TABLE_STAGING_SITES, $sql_create_table );
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