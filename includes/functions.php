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




