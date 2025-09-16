<?php

use InstaWP\Connect\Helpers\Cache;
use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\DatabaseManager;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;
use InstaWP\Connect\Helpers\Updater;

defined( 'ABSPATH' ) || exit;

class InstaWP_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_instawp_save_management_settings', array( $this, 'save_management_settings' ) );
		add_action( 'wp_ajax_instawp_disconnect_plugin', array( $this, 'disconnect_api' ) );
		add_action( 'wp_ajax_instawp_refresh_staging_sites', array( $this, 'refresh_staging_sites' ) );
		add_action( 'wp_ajax_instawp_get_dir_contents', array( $this, 'get_dir_contents' ) );
		add_action( 'wp_ajax_instawp_get_database_tables', array( $this, 'get_database_tables' ) );
		add_action( 'wp_ajax_instawp_get_large_files', array( $this, 'get_large_files' ) );
		add_action( 'wp_ajax_instawp_process_ajax', array( $this, 'process_ajax' ) );
		add_action( 'wp_ajax_instawp_check_usages_limit', array( $this, 'check_usages_limit' ) );
		add_action( 'wp_ajax_instawp_migrate_init', array( $this, 'migrate_init' ) );
		add_action( 'wp_ajax_instawp_migrate_progress', array( $this, 'migrate_progress' ) );
		add_action( 'wp_ajax_instawp_skip_item', array( $this, 'skip_item' ) );
		add_action( 'wp_ajax_instawp_change_plan', array( $this, 'change_plan' ) );
		add_action( 'wp_ajax_instawp_update_plugin', array( $this, 'update_plugin' ) );
		add_action( 'wp_ajax_instawp_get_site_plans', array( $this, 'get_site_plans' ) );
	}

	public function update_plugin() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'updated'    => false,
					'permission' => false,
				)
			);
		}

		$plugin_updater = new Updater(
			array(
				array(
					'type' => 'plugin',
					'slug' => INSTAWP_PLUGIN_FILE,
				),
			)
		);
		$response       = $plugin_updater->update();

		if ( isset( $response[ INSTAWP_PLUGIN_FILE ] ) && isset( $response[ INSTAWP_PLUGIN_FILE ]['success'] ) && (bool) $response[ INSTAWP_PLUGIN_FILE ]['success'] === true ) {
			wp_send_json_success(
				array(
					'updated'  => true,
					'response' => $response,
				)
			);
		}

		wp_send_json_error(
			array(
				'updated'  => false,
				'response' => $response,
			)
		);
	}

	public function process_ajax() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'file';

		InstaWP_Tools::instawp_reset_permalink();

		if ( $type === 'database' && class_exists( 'InstaWP\Connect\Helpers\DatabaseManager' ) ) {
			$manager = new DatabaseManager();
			wp_send_json_success( $manager->get() );
		} elseif ( $type === 'cache' ) {
			$cache_api = new Cache();
			$response  = $cache_api->clean();

			set_transient( 'instawp_cache_purged', $response, 300 );
			wp_send_json_success( $response );
		} elseif ( $type === 'debug_log' ) {
			wp_send_json_success( array( 'login_url' => site_url( 'wp-content/debug.log' ) ) );
		} elseif ( $type === 'error_log' ) {
			wp_send_json_success( array( 'error_log' => Helper::get_error_log() ) );
		}

		wp_send_json_error();
	}

	public function migrate_progress() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$visibility        = isset( $_POST['visible'] ) && filter_var( wp_unslash( $_POST['visible'] ), FILTER_VALIDATE_BOOLEAN );
		$progress          = array(
			'progress_files'   => 0,
			'progress_db'      => 0,
			'progress_restore' => 0,
		);
		$migration_details = Option::get_option( 'instawp_migration_details' );
		$migrate_id        = Helper::get_args_option( 'migrate_id', $migration_details );
		$migrate_key       = Helper::get_args_option( 'migrate_key', $migration_details );
		$started_at        = Helper::get_args_option( 'started_at', $migration_details );

		if ( empty( $migrate_id ) || empty( $migrate_key ) ) {
			wp_send_json_success( $progress );
		}

		$response = Curl::do_curl( "migrates-v3/{$migrate_id}", array( 'migrate_key' => $migrate_key ) );

		if ( isset( $response['success'] ) && $response['success'] !== true ) {
			error_log( wp_json_encode( $response ) );
			wp_send_json_error( array( 'message' => Helper::get_args_option( 'message', $response ) ) );
		}

		$response_data                     = Helper::get_args_option( 'data', $response, array() );
		$auto_login_hash                   = isset( $response_data['dest_wp']['auto_login_hash'] ) ? $response_data['dest_wp']['auto_login_hash'] : '';
		$response_data['migrate_id']       = $migrate_id;
		$response_data['started_at']       = $started_at;
		$response_data['timestamp']        = wp_date( 'Y-m-d H:i:s' );
		$response_data['processed_files']  = array();
		$response_data['processed_db']     = array();
		$response_data['progress_files']   = Helper::get_args_option( 'progress_files', $response_data, 0 );
		$response_data['progress_db']      = Helper::get_args_option( 'progress_db', $response_data, 0 );
		$response_data['progress_restore'] = Helper::get_args_option( 'progress_restore', $response_data, 0 );
		$response_data['server_logs']      = Helper::get_args_option( 'server_logs', $response_data );
		$response_data['failed_message']   = Helper::get_args_option( 'failed_message', $response_data, esc_html( 'Something went wrong' ) );

		if ( ! empty( $response_data['stage'] ) ) {
			foreach ( $response_data['stage'] as $stage => $status ) {
				if ( ! $status ) {
					continue;
				}
				$migration_details['status'] = 'completed';
				Option::update_option( 'instawp_last_migration_details', $migration_details );
			}
		}

		if ( isset( $response_data['stage']['failed'] ) && $response_data['stage']['failed'] === true ) {
			instawp_reset_running_migration();

			$response_data['message'] = esc_html__( 'Migration failed.', 'instawp-connect' );

			wp_send_json_error( $response_data );
		}

		$tracking_db = InstaWP_Tools::get_tracking_database( $migrate_key );
		$statuses    = array(
			1 => 'sent',
			2 => 'in-progress',
			3 => 'failed',
			4 => 'skipped',
			5 => 'invalid',
		);

		if ( $visibility && $tracking_db instanceof IWPDB ) {
			$sendingFiles = array();
			$file_offset  = Option::get_option( 'instawp_files_offset', 0 );
			$file_offset  = empty( $file_offset ) ? 0 : $file_offset;

			$files_query_res = $tracking_db->query( "SELECT id, filepath, size, sent FROM iwp_files_sent WHERE sent != 0 LIMIT {$file_offset}, 18446744073709551615" );
			if ( $files_query_res instanceof mysqli_result ) {
				$tracking_db->fetch_rows( $files_query_res, $sendingFiles );
			}

			if ( ! empty( $sendingFiles ) ) {
				$sendingFiles = array_map(
					function ( $value ) use ( $statuses ) {
						$path_to_replace = wp_normalize_path( instawp_get_root_path() . DIRECTORY_SEPARATOR );

						$value['filepath'] = str_replace( $path_to_replace, '', wp_normalize_path( $value['filepath'] ) );
						$value['size']     = instawp()->get_file_size_with_unit( $value['size'] );
						$value['status']   = $statuses[ intval( $value['sent'] ) ];

						return $value;
					},
					$sendingFiles
				);

				$sendingFilesOffset = array_filter(
					$sendingFiles,
					function ( $value ) {
						return $value['status'] !== 'in-progress';
					}
				);

				Option::update_option( 'instawp_files_offset', count( $sendingFilesOffset ) + $file_offset );
			}

			$response_data['processed_files'] = $sendingFiles;

			$sendingDB = array();
			$db_offset = Option::get_option( 'instawp_db_offset', 0 );
			$db_offset = empty( $db_offset ) ? 0 : $db_offset;

			$db_query_res = $tracking_db->query( "SELECT id, table_name, offset, rows_total, completed FROM iwp_db_sent WHERE completed != 0 LIMIT {$db_offset}, 18446744073709551615" );
			if ( $db_query_res instanceof mysqli_result ) {
				$tracking_db->fetch_rows( $db_query_res, $sendingDB );
			}

			if ( ! empty( $sendingDB ) ) {
				$sendingDB = array_map(
					function ( $value ) use ( $statuses ) {
						$value['status'] = $statuses[ intval( $value['completed'] ) ];

						return $value;
					},
					$sendingDB
				);

				$sendingDBOffset = array_filter(
					$sendingDB,
					function ( $value ) {
						return $value['status'] !== 'in-progress';
					}
				);

				Option::update_option( 'instawp_db_offset', count( $sendingDBOffset ) + $db_offset );
			}

			$response_data['processed_db'] = $sendingDB;
		}

		if ( isset( $response_data['stage']['migration-finished'] ) && $response_data['stage']['migration-finished'] === true ) {
			delete_option( 'instawp_files_offset' );
			delete_option( 'instawp_db_offset' );

			$migration_details['status'] = 'completed';
			Option::update_option( 'instawp_last_migration_details', $migration_details );

			if ( $tracking_db instanceof IWPDB ) {
				$migrate_settings = $tracking_db->get_option( 'migrate_settings' );
				$migrate_options  = Helper::get_args_option( 'options', $migrate_settings, array() );

				if ( is_array( $migrate_options ) && in_array( 'enable_event_syncing', $migrate_options ) ) {
					Option::update_option( 'instawp_is_event_syncing', 1 );
				}
			}

			// update staging websites list
			instawp_set_staging_sites_list();

			instawp_reset_running_migration();
		}

		if ( ! empty( $auto_login_hash ) ) {
			$response_data['dest_wp']['auto_login_url'] = sprintf( '%s/wordpress-auto-login?site=%s', Helper::get_api_domain(), $auto_login_hash );
		} else {
			$response_data['dest_wp']['auto_login_url'] = isset( $response_data['dest_wp']['url'] ) ? $response_data['dest_wp']['url'] : '';
		}

		if ( isset( $response_data['dest_wp']['url'] ) && ! empty( $dest_url = $response_data['dest_wp']['url'] ) ) {
			$url_parts = wp_parse_url( $dest_url );
			$url_raw   = $url_parts['host'] . ( isset( $url_parts['path'] ) ? $url_parts['path'] : '' ) . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' ) . ( isset( $url_parts['fragment'] ) ? '#' . $url_parts['fragment'] : '' );
			$url_ip    = gethostbyname( $url_raw );

			instawp_set_whitelist_ip( $url_ip );
		}

		wp_send_json_success( $response_data );
	}

	public function skip_item() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$item              = isset( $_POST['item'] ) ? sanitize_text_field( wp_unslash( $_POST['item'] ) ) : '';
		$item_type         = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'file';
		$migration_details = Option::get_option( 'instawp_migration_details', array() );
		$migrate_key       = Helper::get_args_option( 'migrate_key', $migration_details );

		if ( empty( $migrate_key ) || empty( $item ) ) {
			wp_send_json_error( array( 'message' => __( 'Migrate key or item is missing!', 'instawp-connect' ) ) );
		}

		$tracking_db = InstaWP_Tools::get_tracking_database( $migrate_key );
		if ( $tracking_db instanceof IWPDB ) {
			if ( $item_type === 'file' ) {
				$tracking_db->update( 'iwp_files_sent', array( 'sent' => '4' ), array( 'id' => $item ) );
			} elseif ( $item_type === 'db' ) {
				$tracking_db->update( 'iwp_db_sent', array( 'completed' => '4' ), array( 'table_name_hash' => hash( 'sha256', $item ) ) );
			}

			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Can not initiate database.', 'instawp-connect' ) ) );
		}
	}


	public function migrate_init() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$settings_str = isset( $_POST['settings'] ) ? $_POST['settings'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		parse_str( $settings_str, $settings_arr );

		global $wp_version, $wpdb;

		$site_url            = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'" );
		$source_domain       = empty( $site_url ) ? site_url() : $site_url;
		$is_website_on_local = instawp_is_website_on_local();
		$instawp_migrate     = Helper::get_args_option( 'instawp_migrate', $settings_arr, array() );

		if ( isset( $instawp_migrate['whitelist_ip'] ) && $instawp_migrate['whitelist_ip'] === 'yes' ) {
			instawp_set_whitelist_ip();
		}

		$migrate_settings   = InstaWP_Tools::get_migrate_settings( $_POST );
		$migrate_key        = Helper::get_random_string( 40 );
		$pre_check_response = InstaWP_Tools::get_pull_pre_check_response( $migrate_key, $migrate_settings );

		if ( is_wp_error( $pre_check_response ) ) {

			// send log to app before starting migrate pull
			$log_array = array(
				'migrate_settings' => $migrate_settings,
				'message'          => $pre_check_response->get_error_message(),
			);
			instawp_send_connect_log( 'pull-precheck', wp_json_encode( $log_array ) );

			wp_send_json_error( array( 'message' => $pre_check_response->get_error_message() ) );
		}

		if ( empty( $serve_url = Helper::get_args_option( 'serve_url', $pre_check_response ) ) ) {

			// send log to app before starting migrate pull
			$message   = esc_html__( 'Error: Empty serve url found in pre-check response.', 'instawp-connect' );
			$log_array = array(
				'migrate_settings' => $migrate_settings,
				'message'          => $message,
			);
			instawp_send_connect_log( 'pull-precheck', wp_json_encode( $log_array ) );

			wp_send_json_error( array( 'message' => $message ) );
		}

		if ( empty( $api_signature = Helper::get_args_option( 'api_signature', $pre_check_response ) ) ) {

			// send log to app before starting migrate pull
			$message   = esc_html__( 'Error: Empty api signature found in pre-check response.', 'instawp-connect' );
			$log_array = array(
				'migrate_settings' => $migrate_settings,
				'message'          => $message,
			);
			instawp_send_connect_log( 'pull-precheck', wp_json_encode( $log_array ) );

			wp_send_json_error( array( 'message' => $message ) );
		}

		$migrate_args = array(
			'source_domain'       => $source_domain,
			'source_connect_id'   => instawp_get_connect_id(),
			'php_version'         => PHP_VERSION,
			'wp_version'          => $wp_version,
			'plugin_version'      => INSTAWP_PLUGIN_VERSION,
			'file_size'           => InstaWP_Tools::get_total_sizes( 'files', $migrate_settings ),
			'db_size'             => InstaWP_Tools::get_total_sizes( 'db' ),
			'is_website_on_local' => $is_website_on_local,
			'settings'            => $migrate_settings,
			'active_plugins'      => Option::get_option( 'active_plugins', array() ),
			'migrate_key'         => $migrate_key,
			'serve_url'           => $serve_url,
			'api_signature'       => $api_signature,
			'plan_id'             => Helper::get_args_option( 'plan_id', $migrate_settings, null ),
		);

		if ( ! empty( $site_name = Helper::get_args_option( 'site_name', $migrate_settings ) ) ) {
			$site_name = strtolower( $site_name );
			$site_name = preg_replace( '/[^a-z0-9-_]/', ' ', $site_name );
			$site_name = preg_replace( '/\s+/', '-', $site_name );

			$migrate_args['site_name'] = $site_name;
		}

		$migrate_response         = Curl::do_curl( 'migrates-v3', $migrate_args );
		$migrate_response_status  = (bool) Helper::get_args_option( 'success', $migrate_response, true );
		$migrate_response_message = Helper::get_args_option( 'message', $migrate_response );

		if ( $migrate_response_status === false ) {

			// send log to app when pull failed
			$log_array = array(
				'migrate_settings' => $migrate_settings,
				'message'          => $migrate_response,
			);
			instawp_send_connect_log( 'pull-failed', wp_json_encode( $log_array ) );

			error_log( wp_json_encode( $migrate_response ) );

			$migrate_response_message = empty( $migrate_response_message ) ? esc_html__( 'Could not create migrate id.', 'instawp-connect' ) : $migrate_response_message;

			wp_send_json_error( array( 'message' => $migrate_response_message ) );
		}

		$migrate_response_data = Helper::get_args_option( 'data', $migrate_response, array() );
		$migrate_id            = Helper::get_args_option( 'migrate_id', $migrate_response_data );
		$migrate_key           = Helper::get_args_option( 'migrate_key', $migrate_response_data );
		$tracking_url          = Helper::get_args_option( 'tracking_url', $migrate_response_data );
		$destination_site_url  = Helper::get_args_option( 'destination_site_url', $migrate_response_data );
		$serve_with_wp         = (bool) Helper::get_args_option( 'serve_with_wp', $pre_check_response );
		$migration_details     = array(
			'migrate_id'    => $migrate_id,
			'migrate_key'   => $migrate_key,
			'tracking_url'  => $tracking_url,
			'dest_url'      => $destination_site_url,
			'started_at'    => current_time( 'mysql', 1 ),
			'status'        => 'initiated',
			'mode'          => 'pull',
			'serve_with_wp' => $serve_with_wp,
		);
		$tracking_db           = InstaWP_Tools::get_tracking_database( $migrate_key );

		if ( $tracking_db ) {
			$tracking_db->update_option( 'dest_url', $destination_site_url );
		}

		Option::update_option( 'instawp_migration_details', $migration_details );

		wp_send_json_success( $migration_details );
	}

	public function check_usages_limit() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$migrate_settings     = InstaWP_Tools::get_migrate_settings( $_POST );
		$check_usage_response = instawp()->instawp_check_usage_on_cloud( $migrate_settings );

		$can_proceed       = (bool) Helper::get_args_option( 'can_proceed', $check_usage_response, false );
		$api_response      = Helper::get_args_option( 'api_response', $check_usage_response, array() );
		$api_response_code = Helper::get_args_option( 'code', $api_response );
		$message           = Helper::get_args_option( 'message', $api_response );

		if ( $can_proceed ) {
			wp_send_json_success( $check_usage_response );
		}

		$error = array(
			'error'     => true,
			'title'     => esc_html__( 'Communication Error', 'instawp-connect' ),
			'message'   => empty( $message ) ? esc_html__( 'Something went wrong.', 'instawp-connect' ) : esc_html( $message ),
			'http_code' => empty( $api_response_code ) ? 400 : intval( $api_response_code ),
		);

		if ( intval( $api_response_code ) === 404 ) {
			$return_url      = urlencode( admin_url( 'tools.php?page=instawp&instawp-nonce=' . wp_create_nonce( 'instawp_connect_nonce' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
			$api_domain      = Helper::get_api_domain();
			$connect_api_url = $api_domain . '/authorize?source=connect_manage&return_url=' . $return_url;

			instawp_reset_running_migration( 'hard' );

			Helper::set_api_domain( $api_domain );

			wp_send_json_error(
				array_merge(
					$error,
					array(
						'button_text' => esc_html__( 'Connect Again', 'instawp-connect' ),
						'button_url'  => $connect_api_url,
					)
				)
			);
		}

		// Disk space data not found
		if ( ! isset( $check_usage_response['remaining_disk_space'] ) && ! empty( $api_response ) && isset( $api_response['success'] ) && false === $api_response['success'] ) {
			wp_send_json_error(
				array_merge(
					$error,
					array(
						'button_text' => esc_html__( 'Contact Support', 'instawp-connect' ),
						'button_url'  => 'https://instawp.com/support',
					)
				)
			);
		}

		$check_usage_response['button_text'] = esc_html__( 'Increase Limit', 'instawp-connect' );
		$check_usage_response['button_url']  = InstaWP_Setting::get_pro_subscription_url( 'subscriptions?source=connect_limit_warning' );

		wp_send_json_error( $check_usage_response );
	}

	public function get_dir_contents() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		// phpcs:disable
		$path                = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '/';
		$active_plugins_only = isset( $_POST['active_plugins'] ) && filter_var( $_POST['active_plugins'], FILTER_VALIDATE_BOOLEAN );
		$active_themes_only  = isset( $_POST['active_themes'] ) && filter_var( $_POST['active_themes'], FILTER_VALIDATE_BOOLEAN );
		$skip_media_folder   = isset( $_POST['skip_media_folder'] ) && filter_var( $_POST['skip_media_folder'], FILTER_VALIDATE_BOOLEAN );
		$is_item_checked     = isset( $_POST['is_checked'] ) && filter_var( $_POST['is_checked'], FILTER_VALIDATE_BOOLEAN );
		$is_select_all       = isset( $_POST['select_all'] ) && filter_var( $_POST['select_all'], FILTER_VALIDATE_BOOLEAN );
		$sort_by             = isset( $_POST['sort_by'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_by'] ) ) : false;
		// phpcs:enable

		if ( ! $path ) {
			wp_send_json_error();
		}
		$dir_data = instawp_get_dir_contents( $path, $sort_by );
		if ( empty( $dir_data ) ) {
			wp_send_json_success( array( 'content' => __( 'Empty folder!', 'instawp-connect' ) ) );
		}

		$list_data      = get_option( 'instawp_large_files_list', array() );
		$paths          = wp_list_pluck( $list_data, 'realpath' );
		$upload_dir     = wp_upload_dir();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$current_theme  = wp_get_theme();
		$themes_dir     = wp_normalize_path( $current_theme->get_theme_root() );
		$theme_path     = wp_normalize_path( $current_theme->get_stylesheet_directory() );
		$template_path  = wp_normalize_path( $current_theme->get_template_directory() );
		$total_size     = 0;
		$total_files    = 0;

		ob_start();
		foreach ( $dir_data as $key => $data ) {
			if ( $data['name'] === '.' || $data['name'] === '..' || strpos( $data['full_path'], 'instawp-connect' ) !== false ) {
				continue;
			}
			$total_size  += $data['size'];
			$total_files += $data['count'];
			$skip_media   = ( $skip_media_folder && strpos( $data['full_path'], wp_normalize_path( $upload_dir['basedir'] ) ) !== false );

			$theme_item_checked      = false;
			$can_perform_theme_check = ( $active_themes_only && strpos( $data['full_path'], $themes_dir ) !== false );
			if ( $can_perform_theme_check ) {
				$theme_item_checked = true;

				if ( in_array( $data['full_path'], array( $theme_path, $template_path, $themes_dir, $themes_dir . '/index.php' ) )
					|| strpos( $data['full_path'], $theme_path ) !== false
					|| strpos( $data['full_path'], $template_path ) !== false ) {

					$theme_item_checked = false;
				}
			}

			$plugin_item_checked      = false;
			$can_perform_plugin_check = ( $active_plugins_only && strpos( $data['full_path'], wp_normalize_path( WP_PLUGIN_DIR ) ) !== false );
			if ( $can_perform_plugin_check ) {
				$plugin_item_checked = true;

				if ( in_array( $data['full_path'], array( wp_normalize_path( WP_PLUGIN_DIR ), wp_normalize_path( WP_PLUGIN_DIR ) . '/index.php' ) )
					|| in_array( basename( $data['relative_path'] ), array_map( 'dirname', $active_plugins ) ) ) {

					$plugin_item_checked = false;
				}
			}

			$is_checked  = ( in_array( $data['full_path'], $paths ) || $skip_media || $theme_item_checked || $plugin_item_checked );
			$is_disabled = ( $is_checked || $can_perform_theme_check || $can_perform_plugin_check );
			$element_id  = wp_generate_uuid4(); ?>

			<div class="flex flex-col gap-5 item">
				<div class="flex justify-between items-center">
					<div class="flex items-center cursor-pointer" style="transform: translate(0em);">
						<?php if ( $data['type'] === 'folder' ) : ?>
							<div class="p-2 pl-0 expand-folder" data-expand-folder="<?php echo esc_attr( $data['relative_path'] ); ?>">
								<svg width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="rotate-icon">
									<path d="M4.75504 4.09984L5.74004 3.11484L7.34504 1.50984C7.68004 1.16984 7.44004 0.589844 6.96004 0.589844L3.84504 0.589844L1.04004 0.589843C0.560037 0.589843 0.320036 1.16984 0.660037 1.50984L3.25004 4.09984C3.66004 4.51484 4.34004 4.51484 4.75504 4.09984Z" fill="#4F4F4F"/>
								</svg>
							</div>
						<?php endif; ?> 
						<input name="migrate_settings[excluded_paths][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $data['relative_path'] ); ?>" type="checkbox" class="instawp-checkbox exclude-file-item !mt-0 !mr-3 rounded border-gray-300 text-primary-900 focus:ring-primary-900 <?php echo esc_html( $data['name'] ); ?> <?php echo esc_attr( str_replace( '/', '-', $data['relative_path'] ) ); ?>" <?php checked( $is_checked || $is_item_checked || $is_select_all, true ); ?> <?php disabled( $is_disabled || $is_item_checked, true ); ?> data-size="<?php echo esc_html( $data['size'] ); ?>" data-count="<?php echo esc_html( $data['count'] ); ?>">
						<label for="<?php echo esc_attr( $element_id ); ?>" class="text-sm font-medium text-grayCust-800 truncate"<?php echo ( $data['type'] === 'file' ) ? ' style="width: calc(400px - 1em);"' : ''; ?>><?php echo esc_html( $data['name'] ); ?></label>
					</div>
					<div class="flex items-center" style="width: 105px;">
						<svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M2.33333 6.49984H11.6667M2.33333 6.49984C1.59695 6.49984 1 5.90288 1 5.1665V2.49984C1 1.76346 1.59695 1.1665 2.33333 1.1665H11.6667C12.403 1.1665 13 1.76346 13 2.49984V5.1665C13 5.90288 12.403 6.49984 11.6667 6.49984M2.33333 6.49984C1.59695 6.49984 1 7.09679 1 7.83317V10.4998C1 11.2362 1.59695 11.8332 2.33333 11.8332H11.6667C12.403 11.8332 13 11.2362 13 10.4998V7.83317C13 7.09679 12.403 6.49984 11.6667 6.49984M10.3333 3.83317H10.34M10.3333 9.1665H10.34" stroke="#111827" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<div class="text-sm font-medium text-grayCust-800 ml-2"><?php echo esc_html( instawp()->get_file_size_with_unit( $data['size'] ) ); ?></div>
					</div>
				</div>
			</div>
			<?php
		}

		$content = ob_get_clean();
		$data    = array(
			'content' => '<div class="flex flex-col gap-5">' . $content . '</div>',
			'size'    => instawp()->get_file_size_with_unit( $total_size ),
			'count'   => $total_files,
		);
		wp_send_json_success( $data );
	}

	public function get_database_tables() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$sort_by    = isset( $_POST['sort_by'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_by'] ) ) : false;
		$tables     = instawp_get_database_details( $sort_by );
		$table_size = array_sum( wp_list_pluck( $tables, 'size' ) );

		ob_start();
		if ( ! empty( $tables ) ) {
			?>
			<div class="flex flex-col gap-5">
				<?php
				foreach ( $tables as $table ) {
					$element_id = wp_generate_uuid4();
					?>
					<div class="flex flex-col gap-5 item">
						<div class="flex justify-between items-center">
							<div class="flex items-center cursor-pointer" style="transform: translate(0em);">
								<input name="instawp_migrate[excluded_tables][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $table['name'] ); ?>" type="checkbox" class="instawp-checkbox exclude-database-item !mt-0 !mr-3 rounded border-gray-300 text-primary-900 focus:ring-primary-900" data-size="<?php echo esc_html( $table['size'] ); ?>">
								<label for="<?php echo esc_attr( $element_id ); ?>" class="text-sm font-medium text-grayCust-800 truncate" style="width: calc(400px - 1em);"><?php echo esc_html( $table['name'] ); ?> (<?php printf( esc_html__( '%s rows', 'instawp-connect' ), esc_html( $table['rows'] ) ); ?>)</label>
							</div>
							<div class="flex items-center" style="width: 105px;">
								<svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M2.33333 6.49984H11.6667M2.33333 6.49984C1.59695 6.49984 1 5.90288 1 5.1665V2.49984C1 1.76346 1.59695 1.1665 2.33333 1.1665H11.6667C12.403 1.1665 13 1.76346 13 2.49984V5.1665C13 5.90288 12.403 6.49984 11.6667 6.49984M2.33333 6.49984C1.59695 6.49984 1 7.09679 1 7.83317V10.4998C1 11.2362 1.59695 11.8332 2.33333 11.8332H11.6667C12.403 11.8332 13 11.2362 13 10.4998V7.83317C13 7.09679 12.403 6.49984 11.6667 6.49984M10.3333 3.83317H10.34M10.3333 9.1665H10.34" stroke="#111827" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								<div class="text-sm font-medium text-grayCust-800 ml-2"><?php echo esc_html( instawp()->get_file_size_with_unit( $table['size'] ) ); ?></div>
							</div>
						</div>
					</div>
				<?php } ?>
			</div>
			<?php
		}

		$content = ob_get_clean();
		$data    = array(
			'content' => $content,
			'size'    => instawp()->get_file_size_with_unit( $table_size ),
			'count'   => count( $tables ),
		);

		wp_send_json_success( $data );
	}

	public function get_large_files() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$skip     = isset( $_POST['skip'] ) && filter_var( $_POST['skip'], FILTER_VALIDATE_BOOLEAN ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$generate = isset( $_POST['generate'] ) && filter_var( $_POST['generate'], FILTER_VALIDATE_BOOLEAN ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		if ( $generate ) {
			delete_option( 'instawp_large_files_list' );
			delete_transient( 'instawp_generate_large_files' );
			do_action( 'instawp_prepare_large_files_list' );
		}

		$list      = get_option( 'instawp_large_files_list' );
		$list_data = ( ! empty( $list ) && is_array( $list ) ) ? $list : array();

		ob_start();
		if ( ! empty( $list_data ) ) {
			?>
			<div class="bg-yellow-50 border border-2 border-r-0 border-y-0 border-l-orange-400 rounded-lg text-sm text-orange-700 p-4 flex flex-col items-start gap-3">
				<div class="flex items-center gap-3">
					<div class="text-sm font-medium"><?php esc_html_e( 'We have identified following large files in your installation:', 'instawp-connect' ); ?></div>
				</div>
				<div class="flex flex-col items-start gap-3 max-h-48 w-full overflow-auto">
					<?php
					foreach ( $list_data as $data ) {
						$element_id = wp_generate_uuid4();
						?>
						<div class="flex justify-between items-center text-xs">
							<input type="checkbox" name="instawp_migrate[migrate_settings][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $data['relative_path'] ); ?>" class="instawp-checkbox exclude-file-item large-file !mt-0 !mr-3 rounded border-gray-300 text-primary-900 focus:ring-primary-900" data-size="<?php echo esc_html( $data['size'] ); ?>" data-count="1" <?php checked( $skip, true ); ?>>
							<label for="<?php echo esc_attr( $element_id ); ?>"><?php echo esc_html( $data['relative_path'] ); ?> (<?php echo esc_html( instawp()->get_file_size_with_unit( $data['size'] ) ); ?>)</label>
						</div>
					<?php } ?>
				</div>
			</div>
			<?php
		}

		$content = ob_get_clean();
		wp_send_json_success(
			array(
				'content'  => $content,
				'has_data' => boolval( get_transient( 'instawp_generate_large_files' ) ),
			)
		);
	}

	public function save_management_settings() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$option_name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$option_value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		if ( ! $option_name || ! $option_value ) {
			wp_send_json_error();
		}

		if ( strpos( $option_name, 'instawp' ) === false ) {
			wp_send_json_error();
		}

		$result = Option::update_option( $option_name, $option_value );
		if ( ! $result ) {
			wp_send_json_error();
		}

		wp_cache_flush();
		wp_send_json_success();
	}

	public function disconnect_api() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( instawp_is_connected_origin_valid() ) {
			$check_api = isset( $_POST['api'] ) && filter_var( wp_unslash( $_POST['api'] ), FILTER_VALIDATE_BOOLEAN ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash)

			if ( $check_api ) {
				$disconnect_res = instawp_destroy_connect();

				if ( ! $disconnect_res['success'] ) {
					wp_send_json_error(
						array(
							'message' => $disconnect_res['message'],
						)
					);
				}
			} else {
				instawp_destroy_connect( 'delete' ); // force disconnect quietly
			}
		}

		instawp_reset_running_migration( 'hard' );

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Plugin reset successfully.', 'instawp-connect' ),
			)
		);
	}

	public function refresh_staging_sites() {
		check_ajax_referer( 'instawp-connect', 'security' );

		instawp_set_staging_sites_list();

		wp_send_json_success();
	}

	public function change_plan() {
		check_ajax_referer( 'instawp-connect', 'security' );

		$plan_id = isset( $_POST['plan_id'] ) ? intval( $_POST['plan_id'] ) : 0;
		if ( ! $plan_id ) {
			wp_send_json_error();
		}

		$connect_id   = instawp_get_connect_id();
		$disconnected = Option::get_option( 'instawp_connect_plan_disconnected' );

		if ( ! empty( $disconnected ) ) {
			$response = Curl::do_curl( "connects/{$connect_id}/restore", array( 'url' => site_url() ) );
			if ( empty( $response['success'] ) ) {
				$api_key = Helper::get_api_key();
				$jwt     = Helper::get_jwt();

				// Create new connect if not exists
				Helper::generate_api_key( $api_key, $jwt );
				$connect_id = instawp_get_connect_id();
			}
			Option::delete_option( 'instawp_connect_plan_disconnected' );
		}

		$response = instawp_connect_activate_plan( $plan_id );

		if ( empty( $response['success'] ) ) {
			wp_send_json_error(
				array(
					'message' => $response['message'],
				)
			);
		}

		ob_start();
		include INSTAWP_PLUGIN_DIR . '/migrate/templates/ajax/part-plans.php';
		$data = ob_get_clean();

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Plan changed successfully.', 'instawp-connect' ),
				'plans'   => $data,
			)
		);
	}

	public function get_site_plans() {
		check_ajax_referer( 'instawp-connect', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$migrate_settings = InstaWP_Tools::get_migrate_settings( $_POST );
		$total_files_size = InstaWP_Tools::get_total_sizes( 'files', $migrate_settings );
		$total_db_size    = InstaWP_Tools::get_total_sizes( 'db' );
		$total_size       = $total_files_size + $total_db_size;

		$base = 1000;
		$gb   = $total_size / ( $base ** 3 );
		$mb   = $total_size / ( $base ** 2 );

		if ( $mb > $base ) {
			$total_size_formatted = number_format( $gb, 2 ) . ' GB';
		} elseif ( $gb >= 0.01 ) {
			$total_size_formatted = number_format( $mb, 2 ) . ' MB (' . number_format( $gb, 2 ) . ' GB)';
		} else {
			$total_size_formatted = number_format( $mb, 2 ) . ' MB';
		}

		$plan_data  = instawp()->is_connected ? instawp_get_plans() : array();
		$site_plans = ! empty( $plan_data['plans']['sites'] ) ? (array) $plan_data['plans']['sites'] : array();

		if ( isset( $plan_data['plans'] ) ) {
			unset( $plan_data['plans'] );
		}

		$site_data = array_merge(
			$plan_data,
			array(
				'total_size'           => $total_size,
				'total_size_formatted' => $total_size_formatted,
			)
		);

		if ( empty( $site_plans ) ) {
			$site_data['message'] = esc_html__( 'No plans found.', 'instawp-connect' );

			wp_send_json_error( $site_data );
		}

		ob_start();
		include INSTAWP_PLUGIN_DIR . '/migrate/templates/ajax/part-site-plans.php';
		$content = ob_get_clean();

		$site_data['content'] = $content;

		wp_send_json_success( $site_data );
	}
}

new InstaWP_Ajax();
