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

		// $sql_create_staging_site_table = "CREATE TABLE " . INSTAWP_DB_TABLE_STAGING_SITES . " (
		// 	id int(50) NOT NULL AUTO_INCREMENT,
		// 	task_id varchar(255) NOT NULL,
		// 	connect_id varchar(255) NOT NULL,
		// 	site_name varchar(255) NOT NULL,
		// 	site_url varchar(255) NOT NULL,
		// 	admin_email varchar(255) NOT NULL,
		// 	username varchar(255) NOT NULL,
		// 	password varchar(255) NOT NULL,
		// 	auto_login_hash varchar(255) NOT NULL,
		// 	datetime  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		// 	PRIMARY KEY (id)
		// );";

		// maybe_create_table( INSTAWP_DB_TABLE_STAGING_SITES, $sql_create_staging_site_table );


		$sql_create_events_table = "CREATE TABLE " . INSTAWP_DB_TABLE_EVENTS . " (
			id int(20) NOT NULL AUTO_INCREMENT,
			event_hash varchar(50) NOT NULL,
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
			synced_message text NULL,
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

		$sql_create_event_sync_log_table = "CREATE TABLE " . INSTAWP_DB_TABLE_EVENT_SYNC_LOGS . " (
			id int(20) NOT NULL AUTO_INCREMENT,
			event_id int(20) NOT NULL,
			event_hash varchar(50) NOT NULL,
			source_url varchar(128) NOT NULL,
			data longtext NOT NULL,
			logs text NOT NULL,
			date datetime NOT NULL,
			PRIMARY KEY (id)
        ) ";

		maybe_create_table( INSTAWP_DB_TABLE_EVENT_SYNC_LOGS, $sql_create_event_sync_log_table );
	}
}


if ( ! function_exists( 'instawp_alter_db_tables' ) ) {
	function instawp_alter_db_tables() {
		global $wpdb;

		$row = $wpdb->get_row( "SELECT * FROM " . INSTAWP_DB_TABLE_EVENTS, ARRAY_A );
		$row = $row ?? [];

		if ( ( ! array_key_exists( 'event_hash', $row ) ) ) {
			$wpdb->query( "ALTER TABLE " . INSTAWP_DB_TABLE_EVENTS . " ADD `event_hash` varchar(50) NOT NULL AFTER `id`" );
		}
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


if ( ! function_exists( 'instawp_update_migration_stages' ) ) {
	/**
	 * Update migration stages
	 *
	 * @param $stages
	 *
	 * @return bool
	 */
	function instawp_update_migration_stages( $stages = [] ) {

		if (
			empty( $stages ) ||
			empty( $migrate_id = InstaWP_Setting::get_option( 'migrate_id' ) ) ||
			empty( $migrate_key = InstaWP_Setting::get_option( 'migrate_key' ) )
		) {
			return false;
		}

		$stage_args     = array(
			'migrate_key' => $migrate_key,
			'stage'       => json_encode( $stages ),
		);
		$stage_response = InstaWP_Curl::do_curl( 'migrates-v3/' . $migrate_id . '/update-status', $stage_args );

		return (bool) InstaWP_Setting::get_args_option( 'status', $stage_response, true );
	}
}


if ( ! function_exists( 'instawp_reset_running_migration' ) ) {
	/**
	 * Reset running migration
	 *
	 * @param string $reset_type
	 * @param bool $abort_forcefully
	 *
	 * @return bool
	 */
	function instawp_reset_running_migration( $reset_type = 'soft', $abort_forcefully = false ) {

		$migration_details = InstaWP_Setting::get_option( 'instawp_migration_details', [] );
		$migrate_id        = InstaWP_Setting::get_args_option( 'migrate_id', $migration_details );
		$migrate_key       = InstaWP_Setting::get_args_option( 'migrate_key', $migration_details );

		// Delete migration details
		delete_option( 'instawp_migration_details' );

		$reset_type = empty( $reset_type ) ? InstaWP_Setting::get_option( 'instawp_reset_type', 'soft' ) : $reset_type;

		if ( ! in_array( $reset_type, array( 'soft', 'hard' ) ) ) {
			return false;
		}

		if ( 'hard' == $reset_type ) {
			delete_option( 'instawp_api_key' );
			delete_option( 'instawp_backup_part_size' );
			delete_option( 'instawp_max_file_size_allowed' );
			delete_option( 'instawp_reset_type' );
			delete_option( 'instawp_db_method' );
			delete_option( 'instawp_default_user' );
			delete_option( 'instawp_api_options' );

			delete_option( 'instawp_rm_heartbeat' );
			delete_option( 'instawp_api_heartbeat' );
			delete_option( 'instawp_rm_file_manager' );
			delete_option( 'instawp_rm_database_manager' );
			delete_option( 'instawp_rm_install_plugin_theme' );
			delete_option( 'instawp_rm_config_management' );
			delete_option( 'instawp_rm_inventory' );
			delete_option( 'instawp_rm_debug_log' );

			delete_transient( 'instawp_staging_sites' );
			delete_transient( 'instawp_migration_completed' );

			wp_clear_scheduled_hook( 'instawp_handle_heartbeat' );

			$file_db_manager = InstaWP_Setting::get_option( 'instawp_file_db_manager', [] );
			$file_name       = InstaWP_Setting::get_args_option( 'file_name', $file_db_manager );
			if ( $file_name ) {
				wp_clear_scheduled_hook( 'instawp_clean_file_manager', [ $file_name ] );
				do_action( 'instawp_clean_file_manager', $file_name );
			}

			$file_db_manager = InstaWP_Setting::get_option( 'instawp_file_db_manager', [] );
			$file_name       = InstaWP_Setting::get_args_option( 'db_name', $file_db_manager );
			if ( $file_name ) {
				wp_clear_scheduled_hook( 'instawp_clean_database_manager', [ $file_name ] );
				do_action( 'instawp_clean_database_manager', $file_name );
			}
		}

		if ( $abort_forcefully === true && ! empty( $migrate_id ) && ! empty( $migrate_key ) ) {

			$response = InstaWP_Curl::do_curl( "migrates-v3/{$migrate_id}/update-status",
				array(
					'migrate_key'    => $migrate_key,
					'stage'          => array( 'aborted' => true ),
					'failed_message' => esc_html__( 'Migration aborted forcefully', 'instawp-connect' ),
				)
			);

			if ( isset( $response['success'] ) && ! $response['success'] ) {
				error_log( json_encode( $response ) );
			}
		}

		return true;
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

		if ( empty( $cloud_url ) || empty( $local_file ) || ! file_exists( $local_file ) || ! is_file( $local_file ) ) {
			return false;
		}

		$useragent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$file      = file_get_contents( $local_file );
		if ( false === $file ) {
			return false;
		}

		$default_args = array(
			'method'     => 'PUT',
			'body'       => $file,
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

			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( 'Upload to cloud - $cloud_url - ' . $cloud_url );
				error_log( 'Upload to cloud - $local_file - ' . $local_file );
				error_log( 'Upload to cloud - $response - ' . json_encode( $response ) );
			}

			if ( ! is_wp_error( $response ) && isset( $response['response']['code'] ) && 200 == $response['response']['code'] ) {
				return true;
			}
		}

		return false;
	}
}


if ( ! function_exists( 'instawp_is_website_on_local' ) ) {
	/**
	 * Check if the current website is on local server or live
	 *
	 * @return bool
	 */
	function instawp_is_website_on_local() {

		$http_host       = $_SERVER['HTTP_HOST'] ?? '';
		$remote_address  = $_SERVER['REMOTE_ADDR'] ?? '';
		$local_addresses = array(
			'127.0.0.1',
			'::1'
		);
		$local_addresses = apply_filters( 'INSTAWP_CONNECT/Filters/local_addresses', $local_addresses );

		return ( $http_host == 'localhost' || in_array( $remote_address, $local_addresses ) );
	}
}


if ( ! function_exists( 'instawp_get_connect_id' ) ) {
	/**
	 * get connect id for source site
	 *
	 * @return int
	 */
	function instawp_get_connect_id() {
		return InstaWP_Setting::get_connect_id();
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
	function instawp_get_post_type_singular_name( $post_type ): string {
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


if ( ! function_exists( 'instawp_get_staging_sites_list' ) ) {
	/**
	 * Get staging sites list from API.
	 *
	 * @return array
	 */
	function instawp_get_staging_sites_list( $insta_only = false, $force_update = false ) {
		$staging_sites = get_transient( 'instawp_staging_sites' );

		if ( $force_update || ! $staging_sites || ! is_array( $staging_sites ) ) {
			$api_response  = InstaWP_Curl::do_curl( 'connects/' . instawp_get_connect_id() . '/staging-sites', [], [], false );
			$staging_sites = [];

			if ( $api_response['success'] && ! empty( $api_response['data'] ) ) {
				set_transient( 'instawp_staging_sites', $api_response['data'], ( 3 * HOUR_IN_SECONDS ) );
				$staging_sites = $api_response['data'];
			}
		}

		if ( $insta_only ) {
			$staging_sites = array_filter( $staging_sites, function ( $value ) {
				return ( ! isset( $value['is_insta_site'] ) || ( isset( $value['is_insta_site'] ) && $value['is_insta_site'] ) );
			} );
		}

		return is_array( $staging_sites ) ? $staging_sites : [];
	}
}


if ( ! function_exists( 'instawp_get_database_details' ) ) {
	/**
	 * Get directory content.
	 */
	function instawp_get_database_details( $sort_by = false ) {
		global $wpdb;

		$tables = [];
		$rows   = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		if ( $wpdb->num_rows > 0 ) {
			foreach ( $rows as $row ) {
				$size = $row['Data_length'] + $row['Index_length'];

				$tables[] = [
					'name' => $row['Name'],
					'size' => $size,
					'rows' => $row['Rows'],
				];
			}

			if ( $sort_by === 'descending' ) {
				usort( $tables, function ( $item1, $item2 ) {
					return $item2['size'] <=> $item1['size'];
				} );
			} else if ( $sort_by === 'ascending' ) {
				usort( $tables, function ( $item1, $item2 ) {
					return $item1['size'] <=> $item2['size'];
				} );
			}
		}

		return $tables;
	}
}


if ( ! function_exists( 'instawp_get_dir_contents' ) ) {
	/**
	 * Get directory content.
	 */
	function instawp_get_dir_contents( $dir = '/', $sort_by = false ) {
		return instawp()->get_directory_contents( ABSPATH . $dir, $sort_by );
	}
}


if ( ! function_exists( 'instawp_set_whitelist_ip' ) ) {
	function instawp_set_whitelist_ip( $ip_addresses = '' ) {
		// WordFence
		instawp_set_wordfence_whitelist_ip( $ip_addresses );

		// SolidWP
		instawp_set_solid_wp_whitelist_ip( $ip_addresses );
	}
}


if ( ! function_exists( 'instawp_prepare_whitelist_ip' ) ) {
	function instawp_prepare_whitelist_ip( $ip_addresses = '' ) {
		$server_ip_addresses = [ '167.71.233.239', '159.65.64.73' ];

		if ( is_string( $ip_addresses ) ) {
			$ip_addresses = trim( $ip_addresses );
			$ip_addresses = array_map( 'trim', explode( ',', $ip_addresses ) );
		}

		return array_merge( $server_ip_addresses, $ip_addresses );
	}
}


if ( ! function_exists( 'instawp_is_wordfence_whitelisted' ) ) {
	function instawp_is_wordfence_whitelisted() {
		$whitelisted = false;
		if ( class_exists( '\wfConfig' ) && method_exists( '\wfConfig', 'get' ) ) {
			$whites = \wfConfig::get( 'whitelisted', [] );
			$arr    = explode( ',', $whites );
			if ( in_array( '167.71.233.239', $arr ) && in_array( '159.65.64.73', $arr ) ) {
				$whitelisted = true;
			}
		}

		return $whitelisted;
	}
}


if ( ! function_exists( 'instawp_is_solid_wp_whitelisted' ) ) {
	function instawp_is_solid_wp_whitelisted() {
		$whitelisted = false;
		if ( class_exists( '\ITSEC_Modules' ) && method_exists( '\ITSEC_Modules', 'get_settings' ) ) {
			$whites = \ITSEC_Modules::get_setting( 'global', 'lockout_white_list', [] );
			if ( in_array( '167.71.233.239', $whites ) && in_array( '159.65.64.73', $whites ) ) {
				$whitelisted = true;
			}
		}

		return $whitelisted;
	}
}


if ( ! function_exists( 'instawp_set_wordfence_whitelist_ip' ) ) {
	function instawp_set_wordfence_whitelist_ip( $ip_addresses = '' ) {
		if ( class_exists( '\wordfence' ) && method_exists( '\wordfence', 'whitelistIP' ) ) {
			foreach ( instawp_prepare_whitelist_ip( $ip_addresses ) as $ip_address ) {
				\wordfence::whitelistIP( $ip_address );
			}
		}
	}
}


if ( ! function_exists( 'instawp_set_solid_wp_whitelist_ip' ) ) {
	function instawp_set_solid_wp_whitelist_ip( $ip_addresses = '' ) {
		if ( class_exists( '\ITSEC_Modules' ) && method_exists( '\ITSEC_Modules', 'get_settings' ) && method_exists( '\ITSEC_Modules', 'set_settings' ) ) {
			$settings = \ITSEC_Modules::get_settings( 'global' );

			$settings['lockout_white_list'] = array_unique( array_merge( $settings['lockout_white_list'], instawp_prepare_whitelist_ip( $ip_addresses ) ) );

			\ITSEC_Modules::set_settings( 'global', $settings );
		}
	}
}


if ( ! function_exists( 'instawp_whitelist_ip' ) ) {
	function instawp_whitelist_ip() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$providers = [
			'wordfence/wordfence.php'                   => [
				'name'     => 'WordFence',
				'function' => 'instawp_is_wordfence_whitelisted'
			],
			'better-wp-security/better-wp-security.php' => [
				'name'     => 'Solid Security',
				'function' => 'instawp_is_solid_wp_whitelisted',
			],
		];

		$output = [
			'can_whitelist' => false,
			'plugins'       => ''
		];

		$names = [];
		foreach ( $providers as $plugin => $details ) {
			if ( is_plugin_active( $plugin ) && ! call_user_func( $details['function'] ) ) {
				$output['can_whitelist'] = true;
				$names[]                 = $details['name'];
			}
		}

		if ( ! empty( $names ) ) {
			$output['plugins'] = join( ', ', $names );
		}

		return $output;
	}
}


if ( ! function_exists( 'instawp_get_source_site_detail' ) ) {
	function instawp_get_source_site_detail(): void {
		$parent_data = InstaWP_Setting::get_option( 'instawp_sync_parent_connect_data' );
		if ( ! empty( $parent_data ) || ! instawp()->is_staging ) {
			return;
		}

		$connect_id  = instawp_get_connect_id();
		$parent_data = get_connect_detail_by_connect_id( $connect_id );

		update_option( 'instawp_sync_parent_connect_data', $parent_data );
	}
}


if ( ! function_exists( 'get_connect_detail_by_connect_id' ) ) {
	/**
	 * Connect detail
	 *
	 * @param $connect_id
	 *
	 * @return array
	 */
	function get_connect_detail_by_connect_id( $connect_id ): array {
		// connects/<connect_id>
		$response        = [];
		$site_connect_id = instawp_get_connect_id();
		$api_response    = InstaWP_Curl::do_curl( 'connects/' . $site_connect_id . '/connected-sites', [], [], false );

		if ( $api_response['success'] ) {
			$api_response = InstaWP_Setting::get_args_option( 'data', $api_response, [] );

			if ( isset( $api_response['is_parent'] ) ) {
				$response = $api_response['is_parent'] ? $api_response['parent'] : $api_response['children'];

				if ( ! $api_response['is_parent'] ) {
					$response = array_filter( $response, function( $value ) use( $connect_id ) {
						return $value['id'] === intval( $connect_id );
					} );
					$response = reset( $response );
				}
			}
		}

		return $response;
	}
}


if ( ! function_exists( 'instawp_send_connect_log' ) ) {
	/**
	 * Send connect log to app
	 *
	 * @param $action
	 * @param $log_message
	 *
	 * @return bool
	 */
	function instawp_send_connect_log( $action = '', $log_message = '' ) {

		if ( empty( $action ) || empty( $log_message ) ) {
			return false;
		}

		$connect_id = instawp()->connect_id;
		$log_args   = array(
			'action' => $action,
			'logs'   => $log_message,
		);

		// connects/<connect_id>/logs
		$log_response = InstaWP_Curl::do_curl( "connects/{$connect_id}/logs", $log_args );

		if ( isset( $log_response['success'] ) && $log_response['success'] ) {
			return true;
		}

		return false;
	}
}