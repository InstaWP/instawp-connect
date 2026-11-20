<?php
/**
 * All helper functions here
 */


use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

if ( ! function_exists( 'instawp_create_db_tables' ) ) {
	/**
	 * @return void
	 */
	function instawp_create_db_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		if ( ! function_exists( 'maybe_create_table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$sql_create_events_table = 'CREATE TABLE ' . INSTAWP_DB_TABLE_EVENTS . " (
			id int(20) NOT NULL AUTO_INCREMENT,
			event_hash varchar(50) NOT NULL,
			event_name varchar(128) NOT NULL,
			event_slug varchar(128) NOT NULL,
			event_type varchar(128) NOT NULL,
			source_id varchar(128) NOT NULL,
			title text NOT NULL,
			details longtext NOT NULL,
			user_id int(20) NOT NULL,
			date datetime NOT NULL,
			prod varchar(128) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			synced_message varchar(128),
			PRIMARY KEY (id)
        ) $charset_collate;";

		maybe_create_table( INSTAWP_DB_TABLE_EVENTS, $sql_create_events_table );

		$sql_create_sync_history_table = 'CREATE TABLE ' . INSTAWP_DB_TABLE_EVENT_SITES . " (
            id int(20) NOT NULL AUTO_INCREMENT,
            event_id int(20) NOT NULL,
            event_hash varchar(50) NOT NULL,
            connect_id int(20) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			synced_message text NULL,
            date datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

		maybe_create_table( INSTAWP_DB_TABLE_EVENT_SITES, $sql_create_sync_history_table );

		$sql_create_event_sites_table = 'CREATE TABLE ' . INSTAWP_DB_TABLE_SYNC_HISTORY . " (
            id int(20) NOT NULL AUTO_INCREMENT,
            encrypted_contents longtext NOT NULL,
            changes longtext NOT NULL,
            sync_response longtext NOT NULL,
            direction varchar(128) NOT NULL,
            status varchar(128) NOT NULL DEFAULT 'pending',
            user_id int(20) NOT NULL,
            changes_sync_id int(20) NOT NULL,
            sync_message varchar(128) NOT NULL,
            source_connect_id int(20) NOT NULL,
            source_url varchar(128),
            date datetime NOT NULL,
            PRIMARY KEY (id)
            ) $charset_collate;";

		maybe_create_table( INSTAWP_DB_TABLE_SYNC_HISTORY, $sql_create_event_sites_table );

		$sql_create_event_sync_log_table = 'CREATE TABLE ' . INSTAWP_DB_TABLE_EVENT_SYNC_LOGS . " (
			id int(20) NOT NULL AUTO_INCREMENT,
			event_id int(20) NOT NULL,
			event_hash varchar(50) NOT NULL,
			source_url varchar(128) NOT NULL,
			data longtext NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			logs text NOT NULL,
			date datetime NOT NULL,
			PRIMARY KEY (id)
        ) $charset_collate;";

		maybe_create_table( INSTAWP_DB_TABLE_EVENT_SYNC_LOGS, $sql_create_event_sync_log_table );

		instawp_alter_db_tables();
	}
}

if ( ! function_exists( 'get_set_sync_config_data' ) ) {
	function get_set_sync_config_data( $key, $config_data = null ) {
		$config = get_option( 'iwp_sync_config_data' );
		$config = ( empty( $config ) || ! is_array( $config ) ) ? array() : $config;

		if ( empty( $config_data ) ) {
			return isset( $config[ $key ] ) && is_array( $config[ $key ] ) ? $config[ $key ] : array();
		}

		$config[ $key ] = Helper::sanitize_data( $config_data );
		update_option( 'iwp_sync_config_data', $config );
	}
}

if ( ! function_exists( 'instawp_delete_sync_entries' ) ) {
	function instawp_delete_sync_entries() {
		global $wpdb;

		$tables = array(
			INSTAWP_DB_TABLE_EVENTS,
			INSTAWP_DB_TABLE_SYNC_HISTORY,
			INSTAWP_DB_TABLE_EVENT_SITES,
			INSTAWP_DB_TABLE_EVENT_SYNC_LOGS,
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}
	}
}

if ( ! function_exists( 'instawp_alter_db_tables' ) ) {
	function instawp_alter_db_tables() {
		global $wpdb;

		foreach ( array( INSTAWP_DB_TABLE_EVENTS, INSTAWP_DB_TABLE_EVENTS, INSTAWP_DB_TABLE_EVENT_SYNC_LOGS ) as $table_name ) {
			$has_col = $wpdb->get_results(
				$wpdb->prepare( 'SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `table_name`=%s AND `TABLE_SCHEMA`=%s AND `COLUMN_NAME`=%s', $table_name, $wpdb->dbname, 'event_hash' )
			);

			if ( empty( $has_col ) ) {
				$wpdb->query( 'ALTER TABLE ' . $table_name . ' ADD `event_hash` varchar(50) NOT NULL AFTER `id`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		$table_name = INSTAWP_DB_TABLE_EVENT_SYNC_LOGS;
		$has_col    = $wpdb->get_results(
			$wpdb->prepare( 'SELECT `COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`COLUMNS` WHERE `table_name`=%s AND `TABLE_SCHEMA`=%s AND `COLUMN_NAME`=%s', $table_name, $wpdb->dbname, 'status' )
		);

		if ( empty( $has_col ) ) {
			$wpdb->query( 'ALTER TABLE ' . $table_name . " ADD `status` varchar(50) NOT NULL DEFAULT 'pending' AFTER `data`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
	 * @param array  $stages
	 * @param string $migrate_id
	 * @param string $migrate_key
	 *
	 * @return bool
	 */
	function instawp_update_migration_stages( $stages = array(), $migrate_id = '', $migrate_key = '' ) {

		$migrate_id  = empty( $migrate_id ) ? Option::get_option( 'migrate_id' ) : $migrate_id;
		$migrate_key = empty( $migrate_key ) ? Option::get_option( 'migrate_key' ) : $migrate_key;

		if ( empty( $stages ) || ! is_array( $stages ) || empty( $migrate_id ) || empty( $migrate_key ) ) {
			return false;
		}

		$stage_args     = array(
			'migrate_key' => $migrate_key,
			'stage'       => $stages,
		);
		$stage_response = Curl::do_curl( 'migrates-v3/' . $migrate_id . '/update-status', $stage_args );

		return (bool) Helper::get_args_option( 'success', $stage_response, true );
	}
}

if ( ! function_exists( 'instawp_create_api_connect' ) ) {
	/**
	 * Create api connect.
	 *
	 * @param string $api_key
	 * @param string $jwt
	 * @param array  $config
	 * @return bool
	 */
	function instawp_create_api_connect( $api_key, $jwt = '', $config = array() ) {
		if ( empty( $config ) || ( is_array( $config ) && empty( $config['group_uuid'] ) ) ) {
			require_once INSTAWP_PLUGIN_DIR . 'includes/class-instawp-heartbeat.php';
			$config['site_information'] = InstaWP_Heartbeat::prepare_data();
		}
		return Helper::generate_api_key( $api_key, $jwt, $config );
	}
}

if ( ! function_exists( 'instawp_get_admin_capability' ) ) {
	/**
	 * Capability required for InstaWP's admin tools (staging, migration, database manager,
	 * settings). On multisite these act on the whole network (one database, one wp-config),
	 * so they need a super admin rather than a site administrator.
	 *
	 * @return string
	 */
	function instawp_get_admin_capability() {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}
}

if ( ! function_exists( 'instawp_is_allowed_api_domain' ) ) {
	/**
	 * Whether a URL is one of InstaWP's own app domains, which are the only ones the plugin may
	 * send its API key to. The INSTAWP_API_DOMAIN constant is handled by the callers.
	 *
	 * @param string $api_domain Domain URL, e.g. https://app.instawp.io.
	 *
	 * @return bool
	 */
	function instawp_is_allowed_api_domain( $api_domain ) {
		$allowed_domains = array(
			'https://stage.instawp.io',
			'https://dev.instawp.io',
			'https://app.instawp.io',
		);

		// Development environments.
		for ( $dev_env = 1; $dev_env <= 9; $dev_env++ ) {
			$allowed_domains[] = 'https://client.dev' . $dev_env . '.instawp.me';
		}

		return in_array( rtrim( (string) $api_domain, '/' ), $allowed_domains, true );
	}
}

if ( ! function_exists( 'instawp_is_admin' ) ) {
	/**
	 * Check if user is admin
	 *
	 * @param string $check_permission
	 * @return bool
	 */
	function instawp_is_admin( $check_permission = 'manage_options', $check_admin = true ) {
		// Check if we're in admin area first (lightweight check)
		if ( $check_admin && ( ! function_exists( 'is_admin' ) || ! is_admin() ) ) {
			return false;
		}

		if ( ! function_exists( 'wp_get_current_user' ) && file_exists( ABSPATH . 'wp-includes/pluggable.php' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}

		if ( ! function_exists( 'current_user_can' ) && file_exists( ABSPATH . 'wp-includes/capabilities.php' ) ) {
			require_once ABSPATH . 'wp-includes/capabilities.php';
		}

		if ( ! function_exists( 'wp_get_current_user' ) || ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		if ( $check_admin && ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) ) {
			return false;
		}

		return current_user_can( $check_permission );
	}
}


if ( ! function_exists( 'instawp_is_options_file_protected' ) ) {
	/**
	 * Check if a migration options file should be protected from deletion.
	 * Only protects options-{key}.txt that matches the current active migration.
	 *
	 * @param string $file_path Full path to the file.
	 *
	 * @return bool True if file should NOT be deleted.
	 */
	function instawp_is_options_file_protected( $file_path ) {
		$filename = basename( $file_path );

		// Only protect options-{key}.txt files
		if ( 0 !== strpos( $filename, 'options-' ) || '.txt' !== substr( $filename, -4 ) ) {
			return false;
		}

		// Allow deletion if file is older than 24 hours (stale/abandoned migration)
		if ( file_exists( $file_path ) && ( time() - filemtime( $file_path ) ) > 86400 ) {
			return false;
		}

		// Protect only if active migration option has matching key
		if ( function_exists( 'get_option' ) ) {
			$migration_details = get_option( 'instawp_migration_details', array() );
			$active_key        = isset( $migration_details['migrate_key'] ) ? $migration_details['migrate_key'] : '';

			if ( ! empty( $active_key ) && $filename === 'options-' . $active_key . '.txt' ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'instawp_delete_migration_options_file' ) ) {
	/**
	 * Delete the encrypted migration options file(s) from the backups directory.
	 *
	 * The filename (`options-{migrate_key}.txt`) doubles as the key material used to
	 * decrypt its own contents — the site's database credentials and the migration
	 * api_signature — so it must not outlive the migration that produced it.
	 *
	 * Unlike the loops in instawp_reset_running_migration() this ignores the
	 * instawp_is_options_file_protected() guard: callers use it at points where the
	 * migration is already finished, so there is no active migration left to protect.
	 *
	 * @param string $migrate_key Delete only this migration's file. Empty string deletes
	 *                            every options file left in the directory.
	 *
	 * @return int Number of files deleted.
	 */
	function instawp_delete_migration_options_file( $migrate_key = '' ) {

		// Runs at migration terminal states and on reset; a failure must never fatal those
		// flows, so the whole body is wrapped.
		try {
			if ( ! defined( 'INSTAWP_DEFAULT_BACKUP_DIR' ) || ! defined( 'WP_CONTENT_DIR' ) ) {
				return 0;
			}

			$backup_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR . DIRECTORY_SEPARATOR;

			if ( ! is_dir( $backup_dir ) ) {
				return 0;
			}

			// A specific key targets one file; an empty key sweeps whatever is left behind by
			// migrations that crashed before reaching their own cleanup. basename() keeps a
			// malformed key from ever resolving outside the backups directory.
			if ( ! empty( $migrate_key ) ) {
				$options_files = array( $backup_dir . 'options-' . basename( $migrate_key ) . '.txt' );
			} else {
				$options_files = (array) glob( $backup_dir . 'options-*.txt' );
			}

			$deleted_count = 0;

			foreach ( $options_files as $options_file ) {
				if ( ! empty( $options_file ) && is_file( $options_file ) ) {
					wp_delete_file( $options_file );
					$deleted_count ++;
				}
			}

			return $deleted_count;
		} catch ( \Throwable $th ) {
			Helper::add_error_log( array( 'title' => 'instawp: instawp_delete_migration_options_file failed' ), $th );

			return 0;
		}
	}
}

if ( ! function_exists( 'instawp_reset_running_migration' ) ) {
	/**
	 * Reset running migration
	 *
	 * @param string $reset_type
	 * @param bool   $abort_forcefully
	 *
	 * @return bool
	 */
	function instawp_reset_running_migration( $reset_type = 'soft', $abort_forcefully = false, $clear_events = false, $disconnect_connect = false ) {
		global $wpdb;

		$migration_details = Option::get_option( 'instawp_migration_details' );
		$migrate_id        = Helper::get_args_option( 'migrate_id', $migration_details );
		$migrate_key       = Helper::get_args_option( 'migrate_key', $migration_details );

		// Delete migration details
		delete_option( 'instawp_migration_details' );

		/*
		 * The V4 run record -- only once its run has ended and its retention has passed.
		 *
		 * This function is reached by the daily V3 housekeeping job, so an unconditional delete here
		 * wiped the record of a V4 migration that was still running. The record now goes only when
		 * InstaWP_Staging_V4 says it has expired: terminal, and DETAILS_RETENTION past the migration's
		 * completion. The delete fires the record's delete hook, which removes instamigrate.
		 */
		if ( class_exists( 'InstaWP_Staging_V4' ) && InstaWP_Staging_V4::details_expired( get_option( 'instawp_staging_v4_details' ) ) ) {
			delete_option( 'instawp_staging_v4_details' );
		}

		// Explicitly delete the options file for this migration. The option was already
		// deleted above, so instawp_is_options_file_protected() will no longer guard it.
		// This ensures cleanup even if the general file loop below is guarded.
		if ( ! empty( $migrate_key ) ) {
			$options_file = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR . DIRECTORY_SEPARATOR . 'options-' . $migrate_key . '.txt';
			if ( file_exists( $options_file ) ) {
				wp_delete_file( $options_file );
			}
		}

		$reset_type = empty( $reset_type ) ? Option::get_option( 'instawp_reset_type', 'soft' ) : $reset_type;

		if ( ! in_array( $reset_type, array( 'soft', 'hard' ) ) ) {
			return false;
		}

		// Clean remaining backup files. The guard prevents deleting options-{key}.txt
		// belonging to a different active migration (e.g., if reset is called by cron
		// while another migration is running on the same site).
		$instawp_backup_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR . DIRECTORY_SEPARATOR;
		if ( file_exists( $instawp_backup_dir ) ) {
			$files_to_delete = scandir( $instawp_backup_dir );
			$files_to_delete = ! is_array( $files_to_delete ) ? array() : $files_to_delete;
			$files_to_delete = array_diff( $files_to_delete, array( '.', '..' ) );

			foreach ( $files_to_delete as $file ) {
				// Leave the index.php listing guard in place — the directory survives this
				// cleanup, so removing its guard would re-expose it to directory listing.
				if ( class_exists( 'InstaWP_Tools' ) && InstaWP_Tools::is_dir_guard_file( $file ) ) {
					continue;
				}

				if ( is_file( $instawp_backup_dir . $file ) && ! instawp_is_options_file_protected( $instawp_backup_dir . $file ) ) {
					wp_delete_file( $instawp_backup_dir . $file );
				}
			}
		}

		// Clean proxy files regarding pull and push migration
		InstaWP_Tools::clean_instawpbackups_dir( ABSPATH . 'iwp-serve', true );
		InstaWP_Tools::clean_instawpbackups_dir( ABSPATH . 'iwp-dest', true );

		// $wpdb->query( "DROP TABLE IF EXISTS `iwp_db_sent`;" );
		// $wpdb->query( "DROP TABLE IF EXISTS `iwp_files_sent`;" );
		// $wpdb->query( "DROP TABLE IF EXISTS `iwp_options`;" );

		if ( 'hard' === $reset_type ) {
			if ( $disconnect_connect && instawp_is_connected_origin_valid() ) {
				instawp_destroy_connect();
			}

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
			delete_option( 'instawp_last_heartbeat_sent' );
			delete_option( 'instawp_is_staging' );
			delete_option( 'instawp_staging_sites' );
			delete_option( 'instawp_is_event_syncing' );

			delete_transient( 'instawp_migration_completed' );

			wp_clear_scheduled_hook( 'instawp_clean_file_manager' );
			wp_clear_scheduled_hook( 'instawp_clean_database_manager' );

			do_action( 'instawp_clean_file_manager' );
			do_action( 'instawp_clean_database_manager' );

			if ( $clear_events ) {
				instawp_delete_sync_entries();
			}
		}

		if ( $abort_forcefully === true && ! empty( $migrate_id ) && ! empty( $migrate_key ) ) {
			$response = Curl::do_curl(
				"migrates-v3/{$migrate_id}/update-status",
				array(
					'migrate_key'    => $migrate_key,
					'stage'          => array( 'aborted' => true ),
					'failed_message' => esc_html__( 'Migration aborted forcefully', 'instawp-connect' ),
				)
			);

			if ( isset( $response['success'] ) && ! $response['success'] ) {
				Helper::add_error_log( array(
					'message' => 'Migration abort request failed',
					'error'   => Helper::get_args_option( 'message', $response ),
				) );
			}
		}

		return true;
	}
}


if ( ! function_exists( 'instawp_is_website_on_local' ) ) {
	/**
	 * Check if the current website is on local server or live
	 *
	 * @return bool
	 */
	function instawp_is_website_on_local() {

		if ( defined( 'INSTAWP_LOCAL_DEV' ) && INSTAWP_LOCAL_DEV === true ) {
			return false;
		}

		$http_host       = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$remote_address  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$local_addresses = array(
			'127.0.0.1',
			'::1',
		);
		$local_addresses = apply_filters( 'instawp/filters/local_addresses', $local_addresses );

		return ( $http_host === 'localhost' || in_array( $remote_address, $local_addresses ) );
	}
}


if ( ! function_exists( 'instawp_log_throwable' ) ) {
	/**
	 * Record an error caught by a guard that keeps background work (scheduling, API calls, cleanup)
	 * from breaking the request it runs in. Never throws itself.
	 *
	 * @param string     $where What was running.
	 * @param \Throwable $e     The caught error.
	 *
	 * @return void
	 */
	function instawp_log_throwable( $where, $e ) {
		static $logged = array();

		try {
			// The error log keeps only its last few entries, and a failure that repeats on every request would
			// push everything else out. Log each distinct error at most once an hour (one option holding
			// array( key => expiry ), expired keys pruned), and once per process.
			$key = md5( $where . '|' . $e->getMessage() );
			if ( isset( $logged[ $key ] ) ) {
				return;
			}
			$logged[ $key ] = true;

			$now  = time();
			$seen = Option::get_option( 'instawp_throwable_log_seen', array() );
			$seen = is_array( $seen ) ? $seen : array();
			if ( isset( $seen[ $key ] ) && (int) $seen[ $key ] > $now ) {
				return;
			}
			Helper::add_error_log( array(
				'message' => $where . ' failed',
				'error'   => $e->getMessage(),
				'where'   => basename( $e->getFile() ) . ':' . $e->getLine(),
			) );

			// Recorded after the entry is written, so a failure here can never hide the error itself.
			foreach ( $seen as $seen_key => $expiry ) {
				if ( (int) $expiry <= $now ) {
					unset( $seen[ $seen_key ] );
				}
			}
			$seen[ $key ] = $now + HOUR_IN_SECONDS;
			if ( count( $seen ) > 100 ) {
				// Messages that differ on every request would otherwise keep adding keys for an hour.
				asort( $seen );
				$seen = array_slice( $seen, -100, null, true );
			}
			Option::update_option( 'instawp_throwable_log_seen', $seen );
		} catch ( \Throwable $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Logging must not break the request either.
		}
	}
}

if ( ! function_exists( 'instawp_should_check_schedules' ) ) {
	/**
	 * Whether this request should make sure the plugin's recurring jobs are scheduled.
	 *
	 * The check is a database query per job, so it is skipped for visitors' page views, AJAX and
	 * Heartbeat. wp-admin page loads and cron runs are enough to re-create a missing job: WP-Cron runs
	 * as its own request, so even a site nobody logs into gets its jobs back within minutes.
	 *
	 * @return bool
	 */
	function instawp_should_check_schedules() {
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}

		return is_admin() && ! wp_doing_ajax();
	}
}

if ( ! function_exists( 'instawp_connect_return_url' ) ) {
	/**
	 * Where the InstaWP dashboard sends the browser back after the user authorizes this site.
	 *
	 * `iwp_code=1` asks the dashboard for a one-time code instead of putting the API key and a JWT in this
	 * URL, where browser history and access logs would keep them. The code is traded server-to-server in
	 * instawp_redeem_connect_code(). Dashboards that predate the marker ignore it and send the old
	 * `access_token`, which InstaWP_Hooks::generate_api_key() still accepts.
	 *
	 * @return string
	 */
	function instawp_connect_return_url() {
		return add_query_arg(
			array(
				'page'          => 'instawp',
				'instawp-nonce' => wp_create_nonce( 'instawp_connect_nonce' ),
				'iwp_code'      => '1',
			),
			admin_url( 'tools.php' )
		);
	}
}

if ( ! function_exists( 'instawp_redeem_connect_code' ) ) {
	/**
	 * Trade the one-time code from the authorize redirect for the API key and JWT.
	 *
	 * Called directly rather than through Curl::do_curl(), which refuses to run without an API key; this
	 * is the call that obtains one.
	 *
	 * @param string $code The code from the redirect.
	 *
	 * @return array{api_key: string, jwt: string}|array Empty on any failure.
	 */
	function instawp_redeem_connect_code( $code ) {
		try {
			if ( ! is_string( $code ) || ! preg_match( '/^[A-Za-z0-9]{48}$/', $code ) ) {
				return array();
			}

			$api_domain = rtrim( Helper::get_api_domain(), '/' );
			if ( empty( $api_domain ) || ( ! defined( 'INSTAWP_API_DOMAIN' ) && ! instawp_is_allowed_api_domain( $api_domain ) ) ) {
				Helper::add_error_log( array( 'message' => 'Connect code exchange skipped: API domain not allowed' ) );

				return array();
			}

			$response = wp_remote_post(
				$api_domain . '/api/v2/connect/token',
				array(
					'timeout' => 30,
					'headers' => array( 'Accept' => 'application/json' ),
					'body'    => array( 'code' => $code ),
				)
			);

			$status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$body   = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
			$data   = is_array( $body ) && isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();

			if ( 200 !== $status || empty( $data['api_key'] ) || empty( $data['jwt'] ) || ! is_string( $data['api_key'] ) || ! is_string( $data['jwt'] ) ) {
				Helper::add_error_log( array(
					'message' => 'Connect code exchange failed',
					'status'  => $status,
					'error'   => is_wp_error( $response ) ? $response->get_error_message() : '',
				) );

				return array();
			}

			return array(
				'api_key' => $data['api_key'],
				'jwt'     => $data['jwt'],
			);
		} catch ( \Throwable $e ) {
			instawp_log_throwable( 'Connect code exchange', $e );

			return array();
		}
	}
}

if ( ! function_exists( 'instawp_private_debug_log_file' ) ) {
	/**
	 * The private file WP_DEBUG_LOG points to when debug logging is switched on from the plugin.
	 *
	 * WP_DEBUG_LOG = true writes wp-content/debug.log, a URL that is the same on every site. The private
	 * file lives in wp-content/instawp-logs/ with an unguessable name, and the folder gets index.php and
	 * .htaccess deny files as a second layer. The name is stored so it stays the same across toggles.
	 *
	 * @return string|false Absolute path, or false when the folder cannot be created.
	 */
	function instawp_private_debug_log_file() {
		try {
			$dir  = wp_normalize_path( WP_CONTENT_DIR . '/instawp-logs' );
			$file = Option::get_option( 'instawp_debug_log_file' );

			// Only a name this function made, directly in this site's folder: a stored value from another host
			// (a migrated database) or anything else is replaced.
			if ( ! is_string( $file ) || wp_normalize_path( dirname( $file ) ) !== $dir || ! instawp_is_private_debug_log_path( $file ) ) {
				$file = $dir . '/debug-' . wp_generate_password( 32, false ) . '.log';
				Option::update_option( 'instawp_debug_log_file', $file );
			}

			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}

			if ( ! file_exists( $dir . '/index.php' ) ) {
				@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
			if ( ! file_exists( $dir . '/.htaccess' ) ) {
				@file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}

			return $file;
		} catch ( \Throwable $e ) {
			instawp_log_throwable( 'Private debug log setup', $e );

			return false;
		}
	}
}

if ( ! function_exists( 'instawp_is_private_debug_log_path' ) ) {
	/**
	 * Whether a path has the shape of a private debug log file (instawp-logs/debug-<32 letters/digits>.log).
	 *
	 * @param string $path File path.
	 *
	 * @return bool
	 */
	function instawp_is_private_debug_log_path( $path ) {
		$path = wp_normalize_path( (string) $path );

		return 'instawp-logs' === basename( dirname( $path ) ) && 1 === preg_match( '/^debug-[A-Za-z0-9]{32}\.log$/', basename( $path ) );
	}
}

if ( ! function_exists( 'instawp_move_default_debug_log' ) ) {
	/**
	 * Bring the existing wp-content/debug.log into the private file, so the InstaWP log viewer keeps the
	 * history and the public copy is gone.
	 *
	 * A rename is instant at any size. If it is not possible (permissions, or the private file already
	 * exists because entries were written to debug.log again after an earlier move), the last 5 MB is
	 * appended to the private file and debug.log is then removed (emptied if it cannot be removed), so the
	 * same entries are not copied again on the next switch.
	 *
	 * @param string $target The private log file.
	 *
	 * @return bool False when debug.log is still there with its entries (it could not be moved or removed).
	 */
	function instawp_move_default_debug_log( $target ) {
		try {
			$default = wp_normalize_path( WP_CONTENT_DIR . '/debug.log' );

			if ( ! is_file( $default ) || is_link( $default ) ) {
				return true;
			}

			// A rename keeps debug.log's own owner and mode, so it is used only when the file is writable;
			// otherwise the copy below creates a fresh private file this process can write.
			if ( ! file_exists( $target ) && wp_is_writable( $default ) && @rename( $default, $target ) ) {
				return true;
			}

			$in  = @fopen( $default, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$out = $in ? @fopen( $target, 'ab' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$copied = false;
			if ( $in && $out ) {
				$max  = 5 * 1024 * 1024;
				$stat = fstat( $in );
				if ( is_array( $stat ) && $stat['size'] > $max && 0 === fseek( $in, -( $max + 1 ), SEEK_END ) ) {
					fgets( $in );
				}
				$copied = false !== stream_copy_to_stream( $in, $out );
			}
			if ( $in ) {
				fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
			if ( $out ) {
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}

			if ( ! $copied ) {
				return false;
			}
			if ( @unlink( $default ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				return true;
			}
			$empty = @fopen( $default, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( ! $empty ) {
				return false;
			}
			fclose( $empty ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			return true;
		} catch ( \Throwable $e ) {
			instawp_log_throwable( 'Debug log move', $e );

			return false;
		}
	}
}

if ( ! function_exists( 'instawp_can_use_private_debug_log' ) ) {
	/**
	 * Whether switching WP_DEBUG_LOG to the private file can work, checked before anything is changed.
	 *
	 * The file is created and must be readable and writable, or PHP would drop every entry. An existing
	 * wp-content/debug.log must be readable and removable (wp-content writable, or the file itself writable
	 * so it can be emptied), or its history would stay in a file the viewer no longer reads.
	 *
	 * @param string $file The private log file.
	 *
	 * @return bool
	 */
	function instawp_can_use_private_debug_log( $file ) {
		try {
			$existed = file_exists( $file );
			$fh      = @fopen( $file, 'ab' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( ! $fh ) {
				return false;
			}
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			// Opening it for appending proved it is a writable file; the viewer also has to read it.
			clearstatcache();
			$usable = is_readable( $file );

			$default = wp_normalize_path( WP_CONTENT_DIR . '/debug.log' );
			if ( $usable && is_file( $default ) && ! is_link( $default ) ) {
				$usable = is_readable( $default ) && ( wp_is_writable( WP_CONTENT_DIR ) || wp_is_writable( $default ) );
			}

			// Remove the probe file this check created: the move must find no file there, so debug.log is
			// renamed (all of it kept, same owner) rather than copied (only its last 5 MB).
			if ( ! $existed ) {
				@unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}

			return $usable;
		} catch ( \Throwable $e ) {
			instawp_log_throwable( 'Private debug log check', $e );

			return false;
		}
	}
}

if ( ! function_exists( 'instawp_current_debug_log_tail' ) ) {
	/**
	 * The last part of the log file WP_DEBUG_LOG currently writes to, as plain text.
	 *
	 * @param int $max_bytes Most bytes returned.
	 *
	 * @return string|false False when there is no log file.
	 */
	function instawp_current_debug_log_tail( $max_bytes = 2097152 ) {
		try {
			$file   = WP_CONTENT_DIR . '/debug.log';
			$stored = Option::get_option( 'instawp_debug_log_file' );
			if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && is_file( WP_DEBUG_LOG ) ) {
				$file = WP_DEBUG_LOG;
			} elseif ( ! is_file( $file ) && is_string( $stored ) && instawp_is_private_debug_log_path( $stored ) && is_file( $stored )
				&& wp_normalize_path( dirname( $stored ) ) === wp_normalize_path( WP_CONTENT_DIR . '/instawp-logs' ) ) {
				// Debug logging is off again: the history is still in this site's private file. A stored name from
				// another site (a cloned database) is not read.
				$file = $stored;
			}

			$fh = is_file( $file ) ? @fopen( $file, 'rb' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( ! $fh ) {
				return false;
			}

			$stat = fstat( $fh );
			if ( is_array( $stat ) && $stat['size'] > $max_bytes && 0 === fseek( $fh, -( $max_bytes + 1 ), SEEK_END ) ) {
				fgets( $fh );
			}
			$text = stream_get_contents( $fh, $max_bytes );
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			// Nothing, or one unterminated line longer than the limit, reads as no log rather than a blank page.
			return ( false === $text || '' === $text ) ? false : $text;
		} catch ( \Throwable $e ) {
			instawp_log_throwable( 'Debug log read', $e );

			return false;
		}
	}
}

if ( ! function_exists( 'instawp_get_connect_id' ) ) {
	/**
	 * get connect id for source site
	 *
	 * @return int
	 */
	function instawp_get_connect_id() {
		return Helper::get_connect_id();
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


if ( ! function_exists( 'instawp_get_staging_sites_list' ) ) {
	function instawp_get_staging_sites_list( $insta_only = false ) {
		$staging_sites = get_option( 'instawp_staging_sites' );

		if ( ! $staging_sites || ! is_array( $staging_sites ) ) {
			return array();
		}

		if ( $insta_only ) {
			$staging_sites = array_filter(
				$staging_sites,
				function ( $value ) {
					return ! empty( $value['is_insta_site'] );
				}
			);
		}

		usort(
			$staging_sites,
			function ( $a, $b ) {
				return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
			}
		);

		return array_map(
			function ( $site ) {
				$site['is_parent'] = false;

				return $site;
			},
			$staging_sites
		);
	}
}


if ( ! function_exists( 'instawp_set_staging_sites_list' ) ) {
	function instawp_set_staging_sites_list( $force_update = true ) {
		if ( ! $force_update ) {
			$staging_sites = instawp_get_staging_sites_list();
			if ( ! empty( $staging_sites ) ) {
				return true;
			}
		}

		$connect_id = instawp_get_connect_id();
		if ( empty( $connect_id ) ) {
			return false;
		}

		$api_response = Curl::do_curl( 'connects/' . $connect_id . '/staging-sites', array(), array(), 'GET' );

		if ( $api_response['success'] ) {
			$staging_sites = Helper::get_args_option( 'data', $api_response, array() );

			foreach ( $staging_sites as $key => $staging_site ) {
				$staging_site_data = instawp_get_connect_detail_by_connect_id( $staging_site['connect_id'] );
				if ( ! $staging_site_data ) {
					continue;
				}

				unset( $staging_site_data['site_information'], $staging_site_data['site_information_raw'] );
				$staging_sites[ $key ]['data'] = $staging_site_data;
			}

			Option::update_option( 'instawp_staging_sites', $staging_sites );

			return true;
		}

		return false;
	}
}

/**
 * Get Migration headers
 *
 * @param string $hash
 *
 * @return array
 */
if ( ! function_exists( 'instawp_get_migration_headers' ) ) {
	function instawp_get_migration_headers( $hash ) {
		return array(
			'Authorization' => 'Bearer ' . $hash,
			'X-IWP-AUTH'    => $hash,
			'User-Agent'    => 'InstaWP Migration Service',
			'Content-Type'  => 'application/json',
			'Cache-Control' => 'no-cache',
			'Cookie'        => 'instawp_skip_splash=true',
			'Referer'       => Helper::wp_site_url( '', true ),
		);
	}
}

if ( ! function_exists( 'instawp_get_connected_sites_list' ) ) {
	function instawp_get_connected_sites_list( $insta_only = false ) {
		$staging_sites = instawp_get_staging_sites_list( $insta_only );
		$staging_sites = empty( $staging_sites ) || ! is_array( $staging_sites ) ? array() : $staging_sites;

		if ( instawp()->is_staging ) {
			$parent_connect_data = Option::get_option( 'instawp_sync_parent_connect_data' );

			if ( ! empty( $parent_connect_data ) ) {
				if ( ! array_key_exists( 'url', $parent_connect_data ) ) {
					$parent_connect_data['url'] = Helper::get_args_option( 'domain', $parent_connect_data, '' );
				}
				if ( ! array_key_exists( 'connect_id', $parent_connect_data ) ) {
					$parent_connect_data['connect_id'] = Helper::get_args_option( 'id', $parent_connect_data, '' );
				}
				$parent_connect_data['is_parent'] = true;

				$staging_sites[] = $parent_connect_data;
			}
		}

		return $staging_sites;
	}
}


if ( ! function_exists( 'instawp_get_database_details' ) ) {
	/**
	 * Get directory content.
	 */
	function instawp_get_database_details( $sort_by = false ) {
		global $wpdb;

		$tables = array();
		$rows   = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		if ( $wpdb->num_rows > 0 ) {
			foreach ( $rows as $row ) {
				$size = $row['Data_length'] + $row['Index_length'];

				$tables[] = array(
					'name' => $row['Name'],
					'size' => $size,
					'rows' => $row['Rows'],
				);
			}

			if ( $sort_by === 'descending' ) {
				usort(
					$tables,
					function ( $item1, $item2 ) {
						if ( $item1['size'] === $item2['size'] ) {
							return 0;
						}

						return ( $item1['size'] > $item2['size'] ) ? - 1 : 1;
					}
				);
			} elseif ( $sort_by === 'ascending' ) {
				usort(
					$tables,
					function ( $item1, $item2 ) {
						if ( $item1['size'] === $item2['size'] ) {
							return 0;
						}

						return ( $item1['size'] < $item2['size'] ) ? - 1 : 1;
					}
				);
			}
		}

		return $tables;
	}
}


if ( ! function_exists( 'instawp_get_dir_contents' ) ) {
	/**
	 * Get directory content.
	 */
	function instawp_get_dir_contents( $dir = '/', $sort_by = false, $add_root_path = true ) {
		$dir = $add_root_path ? instawp_get_root_path() . $dir : $dir;

		return instawp()->get_directory_contents( $dir, $sort_by );
	}
}


if ( ! function_exists( 'instawp_get_root_path' ) ) {
	function instawp_get_root_path() {
		$server = ! empty( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( $_SERVER['SERVER_SOFTWARE'] ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( false === strpos( $server, 'flywheel' ) && file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH;
		}

		$root_path = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : ABSPATH; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$root_path = rtrim( $root_path, '\\/' );

		return str_replace( '\\', '/', $root_path );
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
		$server_ip_addresses = array( '167.71.233.239', '159.65.64.73' );

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
			$whites = \wfConfig::get( 'whitelisted', array() );
			$arr    = is_array( $whites ) ? $whites : explode( ',', $whites );

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
			$whites = \ITSEC_Modules::get_setting( 'global', 'lockout_white_list', array() );

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

		$providers = array(
			'wordfence/wordfence.php'                   => array(
				'name'     => 'WordFence',
				'function' => 'instawp_is_wordfence_whitelisted',
			),
			'better-wp-security/better-wp-security.php' => array(
				'name'     => 'Solid Security',
				'function' => 'instawp_is_solid_wp_whitelisted',
			),
		);

		$output = array(
			'can_whitelist' => false,
			'plugins'       => '',
		);

		$names = array();
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


if ( ! function_exists( 'instawp_is_admin_page_load' ) ) {
	/**
	 * Whether this is a normal wp-admin page load by an administrator.
	 *
	 * admin_init also fires for admin-ajax.php (Heartbeat every 15-60 seconds per open tab, other
	 * plugins' AJAX, and logged-out visitors' AJAX) and for admin-post.php, so work hooked on it that
	 * calls the InstaWP API must check this first.
	 *
	 * @return bool
	 */
	function instawp_is_admin_page_load() {
		global $pagenow;

		if ( wp_doing_ajax() || 'admin-post.php' === $pagenow ) {
			return false;
		}

		return current_user_can( instawp_get_admin_capability() );
	}
}

if ( ! function_exists( 'instawp_retry_is_waiting' ) ) {
	/**
	 * Whether a retry wait set by instawp_retry_later() is still running.
	 *
	 * All waits live in one option, array( $key => expiry timestamp ).
	 *
	 * @param string $key Retry key.
	 *
	 * @return bool
	 */
	function instawp_retry_is_waiting( $key ) {
		$waits = Option::get_option( 'instawp_retry_after', array() );

		return is_array( $waits ) && isset( $waits[ $key ] ) && (int) $waits[ $key ] > time();
	}
}

if ( ! function_exists( 'instawp_retry_later' ) ) {
	/**
	 * Wait before trying $key again, or clear the wait with a $seconds of 0. Expired waits are pruned.
	 *
	 * @param string $key     Retry key.
	 * @param int    $seconds Seconds to wait; 0 clears the wait.
	 *
	 * @return void
	 */
	function instawp_retry_later( $key, $seconds ) {
		$waits = Option::get_option( 'instawp_retry_after', array() );
		$waits = is_array( $waits ) ? $waits : array();
		$now   = time();

		foreach ( $waits as $wait_key => $expiry ) {
			if ( (int) $expiry <= $now ) {
				unset( $waits[ $wait_key ] );
			}
		}

		if ( $seconds > 0 ) {
			$waits[ $key ] = $now + (int) $seconds;
		} else {
			unset( $waits[ $key ] );
		}

		if ( empty( $waits ) ) {
			Option::delete_option( 'instawp_retry_after' );
		} else {
			Option::update_option( 'instawp_retry_after', $waits );
		}
	}
}

if ( ! function_exists( 'instawp_get_source_site_detail' ) ) {
	/**
	 * On a staging site, look up and store its parent site's details.
	 *
	 * Hooked on admin_init, it runs only on wp-admin page loads by an administrator, and after a lookup
	 * that failed or found nothing it waits an hour: before, a staging site whose parent could not be
	 * found called the API on every admin_init, including each Heartbeat tick and logged-out AJAX.
	 *
	 * @param bool $force Look up now, ignoring the request type and the wait. Used by WP-CLI right after the
	 *                    site is marked as staging, when instawp()->is_staging still holds the value read
	 *                    before the flag was set.
	 *
	 * @return void
	 */
	function instawp_get_source_site_detail( $force = false ) {
		if ( true === $force ) {
			$is_staging = (bool) Option::get_option( 'instawp_is_staging', false );
		} else {
			$is_staging = instawp()->is_staging && instawp_is_admin_page_load();
		}

		if ( ! $is_staging ) {
			return;
		}

		if ( ! empty( Option::get_option( 'instawp_sync_parent_connect_data' ) ) ) {
			return;
		}

		if ( true !== $force && instawp_retry_is_waiting( 'source_site_detail' ) ) {
			return;
		}

		// Runs on admin page loads: an error here must not break the page.
		try {
			$connect_id  = instawp_get_connect_id();
			$parent_data = instawp_get_connect_detail_by_connect_id( $connect_id );

			Option::update_option( 'instawp_sync_parent_connect_data', $parent_data );
			instawp_retry_later( 'source_site_detail', empty( $parent_data ) ? HOUR_IN_SECONDS : 0 );
		} catch ( \Throwable $e ) {
			instawp_log_throwable( 'Staging parent lookup', $e );

			try {
				instawp_retry_later( 'source_site_detail', HOUR_IN_SECONDS );
			} catch ( \Throwable $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// The wait is only an optimisation.
			}

			// A forced lookup comes from WP-CLI (staging-set): say it failed rather than exit silently.
			if ( true === $force && defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
				WP_CLI::warning( 'Staging parent lookup failed: ' . $e->getMessage() );
			}
		}
	}
}


if ( ! function_exists( 'instawp_get_connect_detail_by_connect_id' ) ) {
	/**
	 * Connect detail
	 *
	 * @param $connect_id
	 *
	 * @return array
	 */
	function instawp_get_connect_detail_by_connect_id( $connect_id ) {
		// connects/<connect_id>
		// The endpoint returns every connected site at once, so instawp_set_staging_sites_list() used to
		// download the same response once per staging site. Reuse a successful one for this request.
		static $connected_sites = array();

		$response        = array();
		$site_connect_id = instawp_get_connect_id();

		if ( isset( $connected_sites[ $site_connect_id ] ) ) {
			$api_response = $connected_sites[ $site_connect_id ];
		} else {
			$api_response = Curl::do_curl( 'connects/' . $site_connect_id . '/connected-sites', array(), array(), 'GET' );
			if ( ! empty( $api_response['success'] ) ) {
				$connected_sites[ $site_connect_id ] = $api_response;
			}
		}

		if ( $api_response['success'] ) {
			$api_response = Helper::get_args_option( 'data', $api_response, array() );

			if ( isset( $api_response['is_parent'] ) ) {
				$response = $api_response['is_parent'] ? $api_response['parent'] : $api_response['children'];

				if ( ! $api_response['is_parent'] ) {
					$response = array_filter(
						$response,
						function ( $value ) use ( $connect_id ) {
							return $value['id'] === intval( $connect_id );
						}
					);
					$response = count( $response ) > 0 ? reset( $response ) : array();
				}
			}
		}

		return (array) $response;
	}
}


if ( ! function_exists( 'instawp_get_site_detail_by_connect_id' ) ) {
	/**
	 * Site detail
	 *
	 * @param $connect_id
	 *
	 * @return mixed
	 */
	function instawp_get_site_detail_by_connect_id( $connect_id, $field = '', $force_update = false ) {

		if ( $force_update === true ) {
			instawp_set_staging_sites_list();
		}

		$staging_sites = instawp_get_staging_sites_list();
		$site_data     = array();

		foreach ( $staging_sites as $staging_site ) {
			if ( $staging_site['connect_id'] === $connect_id ) {
				if ( ! empty( $staging_site ) && ( empty( $field ) || ! empty( $staging_site[ $field ] ) ) ) {
					$site_data = $staging_site;
				} else {
					$site_data = instawp_get_site_detail_by_connect_id( $staging_site['connect_id'], '', true );
				}
				break;
			}
		}

		if ( ! empty( $field ) ) {
			return isset( $site_data[ $field ] ) ? $site_data[ $field ] : '';
		}

		return $site_data;
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
		$log_response = Curl::do_curl( "connects/{$connect_id}/logs", $log_args );

		if ( isset( $log_response['success'] ) && $log_response['success'] ) {
			return true;
		}

		return false;
	}
}


if ( ! function_exists( 'instawp_purge_cdn_cache' ) ) {
	/**
	 * Purge CDN cache for the connected site via InstaWP API.
	 * Only works for sites hosted with InstaWP.
	 *
	 * @return array|WP_Error Response from API or error
	 */
	function instawp_purge_cdn_cache() {
		$connect_id = instawp()->connect_id;

		if ( empty( $connect_id ) ) {
			return new WP_Error( 'no_connect_id', __( 'Site is not connected to InstaWP.', 'instawp-connect' ) );
		}

		$sent_at  = microtime( true );
		$response = Curl::do_curl(
			"connects/{$connect_id}/purge-cache",
			array(),
			array(),
			'POST'
		);

		// Keeps the CDN flag in step and clears what this purge covered from the
		// auto-purge retry list, whoever triggered it.
		if ( class_exists( 'InstaWP_CDN_Cache_Purge' ) ) {
			InstaWP_CDN_Cache_Purge::after_purge( true, array(), $sent_at, $response );
		}

		return $response;
	}
}


if ( ! function_exists( 'instawp_send_heartbeat' ) ) {
	/**
	 * Send heartbeat to InstaWP
	 *
	 * @return bool
	 */
	function instawp_send_heartbeat( $connect_id = '' ) {
		return InstaWP_Heartbeat::send_heartbeat( $connect_id );
	}
}


if ( ! function_exists( 'instawp_get_user_to_login' ) ) {
	/**
	 * Validate and Return the user to login
	 *
	 * @param string $username
	 *
	 * @return WP_Error|array
	 */
	function instawp_get_user_to_login( $username = '' ) {

		if ( username_exists( $username ) ) {
			$user_to_login = get_user_by( 'login', $username );
			$message       = esc_html__( 'Login information for the given username', 'instawp-connect' );
		} elseif ( is_email( $username ) ) {
			$user_to_login = get_user_by( 'email', $username );
			$message       = esc_html__( 'Login information could not found with username, but found with the given email address.', 'instawp-connect' );
		} elseif ( ! empty( $default_username = Option::get_option( 'instawp_default_username' ) ) && ! empty( $default_username ) ) {
			$user_to_login = get_user_by( 'login', $default_username );
			$message       = esc_html__( 'Login information for the given username/email didn\'t found, You are going to login with default login username.', 'instawp-connect' );
		} else {
			$admin_users   = get_users( array( 'role' => 'administrator' ) );
			$user_to_login = is_array( $admin_users ) && isset( $admin_users[0] ) ? $admin_users[0] : false;
			$message       = esc_html__( 'No login found with given username/email and default username, You are going to login with first admin user.', 'instawp-connect' );
		}

		if ( ! $user_to_login instanceof WP_User ) {
			return new WP_Error( 'login_user_not_found', esc_html__( 'No login information found.', 'instawp-connect' ) );
		}

		return array(
			'username' => $user_to_login->user_login,
			'message'  => $message,
		);
	}
}

if ( ! function_exists( 'instawp_get_user_by_token' ) ) {
	/**
	 * @param $token
	 *
	 * @return \WP_User|null
	 */
	function instawp_get_user_by_token( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}

		// Tokens are stored as their sha256. A link created before that change still has its
		// 120-character token stored as-is, so a token of that length is also matched as-is until
		// it expires. A stored hash (64 characters) is never accepted as a token itself.
		$candidates = array( hash( 'sha256', $token ) );
		if ( 120 === strlen( $token ) ) {
			$candidates[] = $token;
		}

		$users = get_users(
			array(
				'meta_key'     => '_instawp_temporary_login_token',
				'meta_value'   => $candidates,
				'meta_compare' => 'IN',
			)
		);

		if ( empty( $users ) ) {
			return null;
		}

		return $users[0];
	}
}

if ( ! function_exists( 'instawp_is_user_login_expired' ) ) {
	function instawp_is_user_login_expired( $user_id ) {
		$expiration = get_user_meta( $user_id, '_instawp_temporary_login_expiration', true );

		if ( empty( $expiration ) ) {
			return true;
		}

		return time() > $expiration;
	}
}

if ( ! function_exists( 'instawp_is_user_attempt_expired' ) ) {
	function instawp_is_user_attempt_expired( $user_id ) {
		$attempt = get_user_meta( $user_id, '_instawp_temporary_login_attempt', true );

		if ( empty( $attempt ) ) {
			return true;
		}

		return $attempt <= 0;
	}
}

if ( ! function_exists( 'instawp_reduce_login_attempt' ) ) {
	function instawp_reduce_login_attempt( $user_id ) {
		global $wpdb;

		$meta_key = '_instawp_temporary_login_attempt';
		$current  = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1", $user_id, $meta_key )
		);

		if ( null === $current || ! is_numeric( $current ) || (int) $current <= 0 ) {
			return false;
		}

		// Compare-and-swap: the row only changes if it still holds the value just read, so two
		// simultaneous requests cannot both use the last attempt. (No CAST in the WHERE clause:
		// strict SQL mode errors on it for non-numeric values.)
		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE user_id = %d AND meta_key = %s AND meta_value = %s",
				(string) ( (int) $current - 1 ),
				$user_id,
				$meta_key,
				$current
			)
		);

		wp_cache_delete( $user_id, 'user_meta' );

		return 1 === (int) $updated;
	}
}

if ( ! function_exists( 'instawp_is_bot_request' ) ) {
	function instawp_is_bot_request() {
		$user_agent      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$referer         = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$bot_user_agents = array(
			'googlebot',
			'bingbot',
			'slurp',
			'duckduckbot',
			'baiduspider',
			'yandexbot',
			'sogou',
			'exabot',
			'facebot',
			'ia_archiver',
			'mediapartners-google',
			'adsbot-google',
			'feedfetcher-google',
			'google-read-aloud',
			'google-structured-data-testing-tool',
			'googlebot-image',
			'googlebot-news',
			'googlebot-video',
			'bingpreview',
			'msnbot',
			'aolbuild',
			'ask jeeves',
			'fast-webcrawler',
			'gigablast',
			'infoseek',
			'inktomi',
			'looksmart',
			'lycos',
			'bingbot',
			'msnbot',
			'bingpreview',
			'facebookexternalhit',
			'linkedinbot',
			'twitterbot',
			'applebot',
			'petalbot',
			'mj12bot',
			'ahrefsbot',
			'semrushbot',
			'dotbot',
			'zoominfobot',
			'yandeximages',
			'yandexvideo',
			'yandeximages',
			'yandexmedia',
			'yandexblogs',
			'yandexfavicons',
			'yandexdirect',
			'yandexsearchshop',
			'yandexmetrika',
			'yandexnews',
			'yandexbrowser',
			'yandexmobile',
			'yandexreplay',
			'baidu',
			'baiduspider',
			'baiduimagespider',
			'baidunews',
			'baiduvideo',
			'baidutranscoder',
			'baidufed',
			'baidulocal',
			'baidumap',
			'baidutop',
			'baidumobile',
			'baiduapimonitor',
			'baidu-wapspider',
			'baidumoniitor',
			'sogou spider',
			'sogou inst spider',
			'sogou spider2',
			'sogou mobile spider',
			'sogou news spider',
			'sogou video spider',
			'exabot',
			'seznam',
			'seznambot',
			'seznam zbozi',
			'seznam-ppc-cz',
			'seznam-social',
			'seznam-tout',
			'seznam-dispute',
			'seznam-kr',
			'seznam-nachricht',
			'mojeekbot',
			'mojeek',
			'teoma',
			'gigabot',
			'gigabotfast',
			'gigabotvideo',
			'gigabotimage',
			'ia_archiver',
			'dotbot',
			'MJ12bot',
			'ahrefsbot',
			'semrushbot',
			'zoominfobot',
			'spinn3r',
			'aboundexbot',
			'curious george',
			'sitelock spider',
			'blexbot',
			'wotbox',
			'rogerbot',
			'embedly',
			'linkfluence',
			'backlink-crawler',
			'twitterbot',
			'redditbot',
			'pinterestbot',
			'slackbot',
			'whatsapp',
			'flipboard',
			'tumblr',
			'vkshare',
			'okhttp',
			'discordbot',
			'skypeuripreview',
			'telegrambot',
			'applebot',
			'scrapy',
			'phpscraper',
			'python-urllib',
			'lxml',
			'pycurl',
			'httpclient',
			'okhttp',
			'httpful',
			'guzzle',
			'httpclient',
			'postman',
			'curl',
			'wget',
			'python-requests',
			'python-urllib',
			'java',
			'go-http-client',
			'okhttp',
			'perl',
			'libwww-perl',
			'mechanize',
			'node-fetch',
			'axios',
			'httpie',
			'requests',
			'botify',
			'deepcrawl',
			'screaming frog',
			'onpage.org',
			'contentkingapp',
			'ubermetrics',
			'sitebulb',
			'serpstatbot',
			'audisto',
			'seobility',
			'siteanalyzer',
			'seochatbot',
			'linkdex',
			'scrapybot',
			'serpwoo',
			'pythons',
			'awario',
			'monitorbacklinks',
			'digg',
			'bingpreview',
			'aboundexbot',
			'top100bot',
			'topsy',
			'tineye',
			'proximic',
			'gigablast',
			'yacybot',
			'everyone-webcrawler',
			'nutch',
			'commoncrawl',
			'semanticbot',
			'gocrawler',
			'scrapy',
			'scrapybot',
			'curl',
			'wget',
			'python',
			'php',
			'perl',
			'java',
			'go-http-client',
			'rust',
			'dotbot',
			'httpclient',
			'axios',
			'pycurl',
			'okhttp',
			'requests',
			'guzzle',
			'httpie',
			'node-fetch',
			'dart',
			'ahrefsbot',
			'semrushbot',
			'dotbot',
			'mj12bot',
			'petalbot',
			'zoominfobot',
			'spinn3r',
			'curious george',
			'wotbox',
			'blexbot',
			'archive.org',
			'ia_archiver',
			'archive',
			'crawler',
		);
		$known_domains   = array(
			'facebook.com',
			'twitter.com',
			'linkedin.com',
			'whatsapp.com',
			'slack.com',
			'telegram.org',
			'discord.com',
			'pinterest.com',
		);

		foreach ( $bot_user_agents as $bot ) {
			if ( stripos( $user_agent, $bot ) !== false ) {
				return true;
			}
		}

		foreach ( $known_domains as $domain ) {
			if ( stripos( $referer, $domain ) !== false ) {
				return true;
			}
		}

		return false;
	}
}


if ( ! function_exists( 'instawp_add_file_to_phar' ) ) {
	/**
	 * Add files to phar
	 *
	 * @param $phar
	 * @param $sourceDir
	 * @param $file
	 * @param $localName
	 *
	 * @return void
	 */
	function instawp_add_file_to_phar( $phar, $sourceDir, $file, $localName ) {
		// Check if the file is a symbolic link
		if ( is_link( $file ) ) {
			// Resolve the path that the link points to
			$realFilePath = readlink( $file );
			// Add the resolved path file to the Phar archive
			$phar->addFile( $realFilePath, $localName );
		} else {
			// Add the file to the Phar archive
			$phar->addFile( $file, $localName );
		}
	}
}


if ( ! function_exists( 'instawp_add_dir_to_phar' ) ) {

	/**
	 * Add directories to phar
	 *
	 * @param $phar
	 * @param $sourceDir
	 * @param $skipDirs
	 * @param $dir
	 *
	 * @return void
	 */
	function instawp_add_dir_to_phar( $phar, $sourceDir, $skipDirs, $dir = '' ) {
		$fullDir = realpath( $sourceDir . '/' . $dir );

		if ( in_array( $fullDir, $skipDirs ) ) {
			return;
		}

		$handle = opendir( $fullDir );

		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( $entry !== '.' && $entry !== '..' ) {
				$fullPath  = $fullDir . '/' . $entry;
				$localName = $dir . '/' . $entry;

				if ( is_dir( $fullPath ) ) {
					instawp_add_dir_to_phar( $phar, $sourceDir, $skipDirs, $localName );
				} else {
					instawp_add_file_to_phar( $phar, $sourceDir, $fullPath, $localName );
				}
			}
		}

		closedir( $handle );
	}
}


if ( ! function_exists( 'instawp_zip_folder_with_phar' ) ) {
	/**
	 * Make archive with phar
	 *
	 * @param $source
	 * @param $destination
	 * @param array       $skipDirs
	 *
	 * @return void
	 * @throws Exception
	 */
	function instawp_zip_folder_with_phar( $source, $destination, array $skipDirs = array() ) {
		global $wp_filesystem;

		// Make sure that the above variable is properly setup.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		$source = rtrim( $source, '/' );

		if ( ! file_exists( $source ) ) {
			throw new Exception( 'Source directory does not exist: ' . esc_html( $source ) );
		}

		// Prepare full paths for directories to skip
		foreach ( $skipDirs as &$dir ) {
			$dir = realpath( $source . '/' . $dir );
		}

		// Initialize PharData with .tar
		$phar = new PharData( $destination . '.tar' );

		// Manually add files to Phar, taking care of symbolic links and skipping directories
		instawp_add_dir_to_phar( $phar, $source, $skipDirs );

		// Compress the .tar into .tar.gz
		$phar->compress( Phar::GZ );

		// Clean up the .tar file
		wp_delete_file( $destination . '.tar' );

		// Rename .tar.gz to .zip
		rename( $destination . '.tar.gz', $destination ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
	}
}


if ( ! function_exists( 'instawp_array_recursive_diff' ) ) {

	/**
	 * Filer to get difference of recursive array data
	 *
	 * @param $array1
	 * @param $array2
	 *
	 * @return array
	 */
	function instawp_array_recursive_diff( $array1, $array2 ) {
		$diff = array();
		if ( ! is_array( $array1 ) || ! is_array( $array2 ) ) {
			return $diff;
		}

		foreach ( $array1 as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( ! isset( $array2[ $key ] ) || ! is_array( $array2[ $key ] ) ) {
					$diff[ $key ] = $value;
				} else {
					$recursive_diff = instawp_array_recursive_diff( $value, $array2[ $key ] );
					if ( ! empty( $recursive_diff ) ) {
						$diff[ $key ] = $recursive_diff;
					}
				}
			} elseif ( ! array_key_exists( $key, $array2 ) || $array2[ $key ] !== $value ) {
				$diff[ $key ] = $value;
			}
		}

		return $diff;
	}
}

if ( ! function_exists( 'instawp_mig_excluded_plugins' ) ) {
	/**
	 * Get excluded plugins list for migration
	 *
	 * @param bool $connect_plugin
	 *
	 * @return array
	 */
	function instawp_mig_excluded_plugins( $connect_plugin = false ) {
		$plugins = array(
			'iwp-migration-helper',
			'iwp-migration-helper-main',
			'iwp-migration-helper-settings',
			'iwp-demo-helper',
			'iwp-demo-helper-main',
		);

		if ( $connect_plugin ) {
			$plugins = array_merge(
				$plugins,
				array(
					'instawp-connect',
					'instawp-connect-main',
				)
			);
		}

		return apply_filters( 'instawp_migration_excluded_plugins', $plugins );
	}
}

if ( ! function_exists( 'instawp_connect_activate_plan' ) ) {
	function instawp_connect_activate_plan( $plan_id ) {
		$connect_id = instawp_get_connect_id();
		if ( empty( $connect_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Connect ID not found', 'instawp-connect' ),
			);
		}

		$response = Curl::do_curl(
			"connects/{$connect_id}/subscribe",
			array(
				'plan_id' => $plan_id,
			)
		);

		if ( empty( $response['success'] ) ) {
			return array(
				'success' => false,
				'message' => $response['message'],
			);
		}

		Helper::set_connect_plan_id( $plan_id );

		return array(
			'success' => true,
			'message' => __( 'Plan activated successfully', 'instawp-connect' ),
		);
	}
}

if ( ! function_exists( 'instawp_destroy_connect' ) ) {
	function instawp_destroy_connect( $mode = 'disconnect' ) {
		$connect_id = instawp_get_connect_id();
		if ( empty( $connect_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Connect ID not found', 'instawp-connect' ),
			);
		}

		if ( $mode === 'delete' ) {
			$api_response = Curl::do_curl( "connects/{$connect_id}/delete", array(), array(), 'DELETE' );
		} else {
			$api_response = Curl::do_curl( "connects/{$connect_id}/disconnect" );
		}

		if ( empty( $api_response['success'] ) ) {
			return array(
				'success' => false,
				'message' => $api_response['message'],
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connect disconnected successfully', 'instawp-connect' ),
		);
	}
}

if ( ! function_exists( 'instawp_is_connected_origin_valid' ) ) {
	function instawp_is_connected_origin_valid() {
		$connect_origin = Helper::get_connect_origin();
		$current_url    = Helper::wp_site_url( '', true );

		if ( ! empty( $connect_origin ) ) {
			return hash_equals( $connect_origin, md5( $current_url ) );
		}

		return true;
	}
}

if ( ! function_exists( 'instawp_is_connect_whitelabelled' ) ) {
	function instawp_is_connect_whitelabelled() {
		if ( ! defined( 'CONNECT_WHITELABEL' ) || CONNECT_WHITELABEL !== true ) {
			return false;
		}

		$plans = defined( 'CONNECT_WHITELABEL_PLAN_DETAILS' ) && is_array( CONNECT_WHITELABEL_PLAN_DETAILS ) ? CONNECT_WHITELABEL_PLAN_DETAILS : array();
		if ( empty( $plans ) ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'instawp_is_account_info_hidden' ) ) {
	/**
	 * Check if account information should be hidden.
	 *
	 * Returns true when the CONNECT_HIDE_ACCOUNT_INFO constant is defined and
	 * set to true. Define this constant in wp-config.php to suppress account
	 * details (e.g. API key, plan info) from the plugin UI — useful for
	 * white-label or managed hosting deployments.
	 *
	 * @return bool True if account info should be hidden, false otherwise.
	 */
	function instawp_is_account_info_hidden() {
		return defined( 'CONNECT_HIDE_ACCOUNT_INFO' ) && CONNECT_HIDE_ACCOUNT_INFO === true;
	}
}

if ( ! function_exists( 'instawp_get_plans' ) ) {
	/**
	 * Get plans
	 *
	 * @return array
	 */
	function instawp_get_plans() {
		$response     = array();
		$api_response = Curl::do_curl( 'connects/plans', array(), array(), 'GET' );

		if ( $api_response['success'] ) {
			$response = Helper::get_args_option( 'data', $api_response, array() );
		}

		return $response;
	}
}
