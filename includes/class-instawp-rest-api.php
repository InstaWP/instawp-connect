<?php

class InstaWP_Backup_Api {

	private $namespace;
	private $version;
	public $instawp_log;
	public $config_log_file_name = 'config';
	public $download_log_file_name = 'backup_download';

	public function __construct() {
		$this->version   = 'v1';
		$this->version_2 = 'v2';
		$this->namespace = 'instawp-connect';

		add_action( 'rest_api_init', array( $this, 'add_api_routes' ) );
		$this->instawp_log = new InstaWP_Log();
	}

	public function add_api_routes() {

		register_rest_route( $this->namespace . '/' . $this->version, 'backup', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'backup' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version, 'download', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'download' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version, 'restore', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'restore' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version, 'test', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'test' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version, 'config', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'config' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version, 'task_status/(?P<task_id>\w+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'task_status' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version, 'upload_status/(?P<task_id>\w+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'upload_status' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/auto-login-code', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'instawp_handle_auto_login_code' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/auto-login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'instawp_handle_auto_login' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version . '/remote-control', '/clear-cache', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'instawp_handle_clear_cache' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/hosting', '/migration', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'instawp_hosting_migration' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/inventory', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_inventory' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/install', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'perform_install' ),
			'permission_callback' => '__return_true',
		) );
	}


	function instawp_hosting_migration( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response = INSTAWP_Migration_hosting::connect_migrate();

		return new WP_REST_Response( $response );
	}


	/**
	 * Handle response for clear cache endpoint
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	function instawp_handle_clear_cache( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Clear cache for - WP Rocket
		if ( is_plugin_active( 'wp-rocket/wp-rocket.php' ) ) {
			rocket_clean_minify();
			rocket_clean_domain();
		}

		// Clear cache for - W3 Total Cache
		if ( is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) ) {
			w3tc_flush_all();
		}

		// Clear cache for - Autoptimize
		if ( is_plugin_active( 'autoptimize/autoptimize.php' ) ) {
			autoptimizeCache::clearall();
		}

		// Clear cache for - Lite Speed Cache
		if ( is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
			\LiteSpeed\Purge::purge_all();
		}

		// Clear cache for - WP Fastest Cache
		if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
			wpfc_clear_all_site_cache();
			wpfc_clear_all_cache( true );
		}

		// Clear cache for - WP Super Cache
		if ( is_plugin_active( 'wp-super-cache/wp-cache.php' ) ) {
			global $file_prefix;
			wp_cache_clean_cache( $file_prefix, true );
		}

		return new WP_REST_Response( array(
			'error'   => false,
			'message' => esc_html( 'Cache clear success' ),
		) );
	}


	/**
	 * Handle response for login code generate
	 * */
	public function instawp_handle_auto_login_code( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response_array = array();

		// Hashed string
		$param_api_key = $request->get_param( 'api_key' );

		$connect_options = get_option( 'instawp_api_options', '' );

		// Non hashed
		$current_api_key = ! empty( $connect_options ) ? $connect_options['api_key'] : "";

		$current_api_key_hash = "";

		// check for pipe
		if ( ! empty( $current_api_key ) && strpos( $current_api_key, '|' ) !== false ) {
			$exploded             = explode( '|', $current_api_key );
			$current_api_key_hash = hash( 'sha256', $exploded[1] );
		} else {
			$current_api_key_hash = ! empty( $current_api_key ) ? hash( 'sha256', $current_api_key ) : "";
		}

		if (
			! empty( $param_api_key ) &&
			$param_api_key === $current_api_key_hash
		) {
			$uuid_code     = wp_generate_uuid4();
			$uuid_code_256 = str_shuffle( $uuid_code . $uuid_code );

			$auto_login_api = get_rest_url( null, '/' . $this->namespace . '/' . $this->version_2 . "/auto-login" );

			$message        = "success";
			$response_array = array(
				'code'    => $uuid_code_256,
				'message' => $message,
			);
			set_transient( 'instawp_auto_login_code', $uuid_code_256, 8 * HOUR_IN_SECONDS );
		} else {
			$message = "request invalid - ";

			if ( empty( $param_api_key ) ) { // api key parameter is empty
				$message .= "key parameter missing";
			} elseif ( $param_api_key !== $current_api_key_hash ) { // local and param, api key hash not matched
				$message .= "api key mismatch";
			} else { // default response
				$message = "invalid request";
			}

			$response_array = array(
				'error'   => true,
				'message' => $message,
			);
		}

		$response = new WP_REST_Response( $response_array );

		return rest_ensure_response( $response );
	}

	/**
	 * Auto login url generate
	 * */
	public function instawp_handle_auto_login( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response_array = array();
		$param_api_key  = $request->get_param( 'api_key' );
		$param_code     = $request->get_param( 'c' );
		$param_user     = $request->get_param( 's' );

		$connect_options = get_option( 'instawp_api_options', '' );

		// Non hashed
		$current_api_key = ! empty( $connect_options ) ? $connect_options['api_key'] : '';

		$current_login_code = get_transient( 'instawp_auto_login_code' );

		$current_api_key_hash = "";

		// check for pipe
		if ( ! empty( $current_api_key ) && strpos( $current_api_key, '|' ) !== false ) {
			$exploded             = explode( '|', $current_api_key );
			$current_api_key_hash = hash( 'sha256', $exploded[1] );
		} else {
			$current_api_key_hash = ! empty( $current_api_key ) ? hash( 'sha256', $current_api_key ) : "";
		}

		if (
			! empty( $param_api_key ) &&
			! empty( $param_code ) &&
			! empty( $param_user ) &&
			$param_api_key === $current_api_key_hash &&
			false !== $current_login_code &&
			$param_code === $current_login_code
		) {
			// Decoded user
			$site_user = base64_decode( $param_user );

			// Make url
			$auto_login_url = add_query_arg(
				array(
					'c' => $param_code,
					's' => base64_encode( $site_user ),
				),
				wp_login_url( '', true )
			);
			// Auto Login Logic to be written
			$message        = "success";
			$response_array = array(
				'error'     => false,
				'message'   => $message,
				'login_url' => $auto_login_url,
			);
		} else {
			$message = "request invalid - ";

			if ( empty( $param_api_key ) ) { // api key parameter is empty
				$message .= "key parameter missing";
			} elseif ( empty( $param_code ) ) { // code parameter is empty
				$message .= "code parameter missing";
			} elseif ( empty( $param_user ) ) { // user parameter is empty
				$message .= "user parameter missing";
			} elseif ( $param_api_key !== $current_api_key_hash ) { // local and param, api key hash not matched
				$message .= "api key mismatch";
			} elseif ( $param_code !== $current_login_code ) { // local and param, code not matched
				$message .= "code mismatch";
			} elseif ( false === $current_login_code ) { // local code parameter option not set
				$message .= "code expired";
			} else { // default response
				$message = "invalid request";
			}

			$response_array = array(
				'error'   => true,
				'message' => $message,
			);
		}

		$response = new WP_REST_Response( $response_array );

		return rest_ensure_response( $response );
	}


	/**
	 * Move files and folder from one place to another
	 *
	 * @param $src
	 * @param $dst
	 *
	 * @return void
	 */
	public function move_files_folders( $src, $dst ) {

		$dir = opendir( $src );

		@mkdir( $dst );

		while ( $file = readdir( $dir ) ) {
			if ( ( $file != '.' ) && ( $file != '..' ) ) {
				if ( is_dir( $src . '/' . $file ) ) {
					$this->move_files_folders( $src . '/' . $file, $dst . '/' . $file );
				} else {
					copy( $src . '/' . $file, $dst . '/' . $file );
					unlink( $src . '/' . $file );
				}
			}
		}

		closedir( $dir );

		rmdir( $src );
	}


	/**
	 * Override the plugin with remote plugin file
	 *
	 * @param $plugin_zip_url
	 *
	 * @return void
	 */
	function override_plugin_zip_while_doing_config( $plugin_zip_url ) {

		if ( empty( $plugin_zip_url ) ) {
			return;
		}

		$plugin_zip   = INSTAWP_PLUGIN_SLUG . '.zip';
		$plugins_path = WP_CONTENT_DIR . '/plugins/';

		// Download the file from remote location
		file_put_contents( $plugin_zip, fopen( $plugin_zip_url, 'r' ) );

		// Setting permission
		chmod( $plugin_zip, 0777 );

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'show_message' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
		}

		if ( ! defined( 'FS_METHOD' ) ) {
			define( 'FS_METHOD', 'direct' );
		}

		wp_cache_flush();

		$plugin_upgrader = new Plugin_Upgrader();
		$installed       = $plugin_upgrader->install( $plugin_zip, array( 'overwrite_package' => true ) );

		if ( $installed ) {

			$installed_plugin_info = $plugin_upgrader->plugin_info();
			$installed_plugin_info = explode( '/', $installed_plugin_info );
			$installed_plugin_slug = $installed_plugin_info[0] ?? '';

			if ( ! empty( $installed_plugin_slug ) ) {

				$source      = $plugins_path . $installed_plugin_slug;
				$destination = $plugins_path . INSTAWP_PLUGIN_SLUG;

				$this->move_files_folders( $source, $destination );

				if ( $destination ) {
					rmdir( $destination );
				}
			}
		}

		unlink( $plugin_zip );
	}


	public function config( $request ) {

		delete_option( 'instawp_api_key_config_completed' );
		delete_option( 'instawp_connect_id_options' );

		$parameters = $request->get_params();
		$results    = array(
			'status'     => false,
			'connect_id' => 0,
			'message'    => '',
		);

		// Config the defaults
		if ( isset( $parameters['defaults'] ) && ! empty( $defaults = $parameters['defaults'] ) ) {
			InstaWP_Setting::set_config_defaults( $defaults );
		}

		// Override plugin file, if provided.
		if ( isset( $parameters['override_plugin_zip'] ) && ! empty( $override_plugin_zip = $parameters['override_plugin_zip'] ) ) {

			$this->override_plugin_zip_while_doing_config( $override_plugin_zip );

			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			$plugin_slug = INSTAWP_PLUGIN_SLUG . '/' . INSTAWP_PLUGIN_SLUG . '.php';

			if ( ! is_plugin_active( $plugin_slug ) ) {
				activate_plugin( $plugin_slug );
			}
		}


		// Check if the configuration is already done, then no need to do it again.
		if ( 'yes' == get_option( 'instawp_api_key_config_completed' ) ) {

			$results['message'] = esc_html__( 'Already configured', 'instawp-connect' );

			return new WP_REST_Response( $results );
		}


		if ( ! empty( $connect_ids = InstaWP_Setting::get_option( 'instawp_connect_id_options', array() ) ) ) {

			// update config check token
			update_option( 'instawp_api_key_config_completed', 'yes' );

			return new WP_REST_Response(
				array(
					'status'     => true,
					'message'    => esc_html__( 'Connected', 'instawp-connect' ),
					'connect_id' => $connect_ids['data']['id'] ?? '',
				)
			);
		}

		if ( empty( $parameters['api_key'] ) ) {
			return new WP_REST_Response(
				array(
					'status'  => false,
					'message' => esc_html__( 'Api key is required', 'instawp-connect' ),
				)
			);
		}

		if ( isset( $parameters['api_domain'] ) ) {
			InstaWP_Setting::set_api_domain( $parameters['api_domain'] );
		}

		$res = self::config_check_key( $parameters['api_key'] );

		$this->instawp_log->CloseFile();

		if ( ! $res['error'] ) {
			$connect_ids = get_option( 'instawp_connect_id_options', '' );

			if ( ! empty( $connect_ids ) ) {

				if ( isset( $connect_ids['data']['id'] ) && ! empty( $connect_ids['data']['id'] ) ) {
					$id = $connect_ids['data']['id'];
				}

				$results['status']     = true;
				$results['message']    = 'Connected';
				$results['connect_id'] = $id;

				// update config check token
				update_option( 'instawp_api_key_config_completed', 'yes' );
				update_option( 'instawp_api_key', $parameters['api_key'] );
			}
		} else {
			$results['status']     = true;
			$results['message']    = $res['message'];
			$results['connect_id'] = 0;
		}

		$response = new WP_REST_Response( $results );

		return rest_ensure_response( $response );
	}


	/**
	 * Valid api request and if invalid api key then stop executing.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return void
	 */
	private function validate_api_request( WP_REST_Request $request ) {

		$bearer_token = sanitize_text_field( $request->get_header( 'authorization' ) );
		$bearer_token = str_replace( 'Bearer ', '', $bearer_token );
		$api_options  = get_option( 'instawp_api_options', array() );

		// check if the bearer token is empty
		if ( empty( $bearer_token ) ) {
			return new WP_Error( 401, esc_html__( 'Empty bearer token.', 'instawp-connect' ) );
		}

		//in some cases Laravel stores api key with ID attached in front of it.
		//so we need to remove it and then hash the key
		$api_key          = isset( $api_options['api_key'] ) ? trim( $api_options['api_key'] ) : '';
		$api_key_exploded = explode( '|', $api_key );

		if ( count( $api_key_exploded ) > 1 ) {
			$api_hash = hash( 'sha256', $api_key_exploded[1] );
		} else {
			$api_hash = hash( 'sha256', $api_key );
		}

//		echo "<pre>";
//		print_r( [ $api_hash ] );
//		echo "</pre>";

		if ( empty( $api_key ) || $bearer_token != $api_hash ) {
			return new WP_Error( 403, esc_html__( 'Invalid bearer token.', 'instawp-connect' ) );
		}

		return true;
	}

	public static function restore_bg( $backup_list, $restore_options, $parameters ) {

		global $instawp_plugin;

		$count_backup_list = count( $backup_list );
		$backup_index      = 1;
		$progress_response = [];
		$res_result        = [];

		// before doing restore deactivate caching plugin
		$instawp_plugin::disable_cache_elements_before_restore();

		foreach ( $backup_list as $backup_list_key => $backup ) {

			do {

				$instawp_plugin->restore_api( $backup_list_key, $restore_options, $parameters );

				$progress_results = $instawp_plugin->get_restore_progress_api( $backup_list_key );

//				$progress_value   = $instawp_plugin->restore_data->get_next_restore_task_progress();
//				$progress_value   = $progress_value * ( $backup_index / $count_backup_list );
//				$progress_value   = ( $progress_value / 2 ) + 50;
//
//				if ( $progress_value < 100 ) {
//					$message = 'Restore in progress';
//				} else {
//					$message = 'Restore completed';
//				}

				$progress_response = (array) json_decode( $progress_results );
//				$res_result        = array_merge( self::restore_status( $message, $progress_value, $parameters['wp']['options'] ) );

			} while ( $progress_response['status'] != 'completed' || $progress_response['status'] == 'error' );

			$backup_index ++;
		}

		if ( $progress_response['status'] == 'completed' ) {

			$res_result['message'] = "Restore completed";

			if ( isset( $parameters['wp'] ) && isset( $parameters['wp']['users'] ) ) {
				self::create_user( $parameters['wp']['users'] );
			}

			if ( isset( $parameters['wp'] ) && isset( $parameters['wp']['options'] ) ) {
				if ( is_array( $parameters['wp']['options'] ) ) {
					$create_options = $parameters['wp']['options'];

					foreach ( $create_options as $option_key => $option_value ) {
						update_option( $option_key, $option_value );
					}
				}
			}

			self::write_htaccess_rule();

			InstaWP_AJAX::instawp_folder_remover_handle();
			$res_result['status']  = true;
			$res_result['message'] = 'Restore task completed.';

			// once the restore completed, enable caching elements
			$instawp_plugin::enable_cache_elements_before_restore();
		}

		if ( $progress_response['status'] == 'error' ) {
			$res_result['message'] = "Error occurred";
		}

		$instawp_plugin->delete_last_restore_data_api();
	}


	public static function download_bg( $task_id, $parameters = array() ) {

		global $InstaWP_Curl;

		if ( empty( $download_urls = InstaWP_Setting::get_args_option( 'urls', $parameters, array() ) ) || ! is_array( $download_urls ) ) {
			self::restore_status( 'Empty or invalid download urls.', 0 );
		}

		$backup_download_ret = $InstaWP_Curl->download( $task_id, $parameters );

		if ( $backup_download_ret['result'] != INSTAWP_SUCCESS ) {
			self::restore_status( 'Could not download the backup file.', 0 );
		} else {
			self::restore_status( 'Backup file downloaded on target site', 51 );
		}
	}


	public function download( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$parameters         = $request->get_params();
		$backup_task        = new InstaWP_Backup_Task();
		$backup_task_ret    = $backup_task->new_download_task();
		$backup_task_id     = isset( $backup_task_ret['task_id'] ) ? $backup_task_ret['task_id'] : '';
		$backup_task_result = isset( $backup_task_ret['result'] ) ? $backup_task_ret['result'] : '';

		if ( ! empty( $backup_task_id ) && 'success' == $backup_task_result ) {

			as_enqueue_async_action( 'instawp_download_bg', [ $backup_task_id, $parameters ] );

			do_action( 'action_scheduler_run_queue', 'Async Request' );
		}

		$res_result = array(
			'completed' => false,
			'progress'  => 55,
			'message'   => esc_html__( 'Downloading has been started.', 'instawp-connect' ),
			'status'    => 'wait',
		);

		return new WP_REST_Response( $res_result );
	}


	public function restore( WP_REST_Request $request ) {

		try {
			$response = $this->validate_api_request( $request );
			if ( is_wp_error( $response ) ) {
				return $this->throw_error( $response );
			}

			$parameters         = $request->get_params();
			$is_background      = $parameters['wp']['options']['instawp_is_background'] ?? true;
			$restore_options    = json_encode( array(
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
			$backup_task        = new InstaWP_Backup_Task();
			$backup_task_ret    = $backup_task->new_download_task( $parameters );
			$backup_task_id     = isset( $backup_task_ret['task_id'] ) ? $backup_task_ret['task_id'] : '';
			$backup_task_result = isset( $backup_task_ret['result'] ) ? $backup_task_ret['result'] : '';

			if ( $is_background === false ) {
				return new WP_REST_Response( array( 'task_id' => $backup_task_id ) );
			}

			if ( ! empty( $backup_task_id ) && 'success' == $backup_task_result ) {

				as_enqueue_async_action( 'instawp_download_bg', [ $backup_task_id, $parameters ] );

				do_action( 'action_scheduler_run_queue', 'Async Request' );
			}

			$backup_uploader = new InstaWP_BackupUploader();
			$backup_uploader->_rescan_local_folder_set_backup_api( $parameters );
			$backup_list = InstaWP_Backuplist::get_backuplist();

			if ( empty( $backup_list ) ) {
				return new WP_REST_Response( array(
					'completed' => false,
					'progress'  => 0,
					'message'   => 'empty backup list',
				) );
			}

			// Background processing of restore using woocommerce's scheduler.
			as_enqueue_async_action( 'instawp_restore_bg', [ $backup_list, $restore_options, $parameters ] );

			// Immediately run the schedule, don't want for the cron to run.
			do_action( 'action_scheduler_run_queue', 'Async Request' );

			$res_result = array(
				'completed' => false,
				'progress'  => 55,
				'message'   => 'Backup downloaded, restore initiated..',
				'status'    => 'wait',
			);

			return new WP_REST_Response( $res_result );

		} catch ( Exception $e ) {
			return new WP_REST_Response( array(
				'error_code' => $e->getCode(),
				'message'    => $e->getMessage(),
			) );
		}
	}


	public static function write_htaccess_rule() {

		if ( is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'get_home_path' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		$parent_url  = get_option( 'instawp_sync_parent_url' );
		$backup_type = get_option( 'instawp_site_backup_type' );

		if ( 1 == $backup_type && ! empty( $parent_url ) ) {

			$htaccess_file    = get_home_path() . '.htaccess';
			$htaccess_content = array(
				'## BEGIN InstaWP Connect',
				'<IfModule mod_rewrite.c>',
				'RewriteEngine On',
				'RedirectMatch 301 ^/wp-content/uploads/(.*)$ ' . $parent_url . '/wp-content/uploads/$1',
				'</IfModule>',
				'## END InstaWP Connect',
			);
			$htaccess_content = implode( "\n", $htaccess_content );
			$htaccess_content = $htaccess_content . "\n\n\n" . file_get_contents( $htaccess_file );

			file_put_contents( $htaccess_file, $htaccess_content );
		}

		return false;
	}


	public static function restore_status( $message, $progress = 100, $wp_options = [] ) {

		global $InstaWP_Curl;

		$instawp_log = new InstaWP_Log();
		$body        = [];

		if ( count( $wp_options ) > 0 ) {

			if ( isset( $wp_options['instawp_is_staging'] ) && isset( $wp_options['instawp_restore_id'] ) ) {

				$connect_id = $wp_options['instawp_sync_connect_id'];
				$url        = InstaWP_Setting::get_api_domain() . INSTAWP_API_URL . '/connects/' . $connect_id . '/restore_status';
				$domain     = str_replace( "https://", "", get_site_url() );
				$domain     = str_replace( "http://", "", $domain );
				$body_json  = json_encode(
					array(
						"restore_id" => $wp_options['instawp_restore_id'],
						"progress"   => $progress,
						"message"    => $message,
						"completed"  => $progress == 100,
					)
				);

				$instawp_log->CreateLogFile( 'update_restore_status_call', 'no_folder', 'Update restore status call' );
				$instawp_log->WriteLog( 'Restore Status percentage is : ' . $progress, 'notice' );
				$instawp_log->WriteLog( 'Update Restore Status call has made the body is : ' . $body_json, 'notice' );
				$instawp_log->WriteLog( 'Update Restore Status call has made the url is : ' . $url, 'notice' );

				$curl_response = $InstaWP_Curl->curl( $url, $body_json );


				if ( ! $curl_response['error'] ) {

					$instawp_log->WriteLog( 'After Update Restore Status call made the response : ' . $curl_response['curl_res'] );

					$response              = (array) json_decode( $curl_response['curl_res'], true );
					$response['task_info'] = $body;

					update_option( 'instawp_backup_status_options', $response );
				}

				$instawp_log->CloseFile();
			} else {
				error_log( "no connect id in wp options" );
			}
		} else {
			error_log( "no wp options" );
		}

		return $body;
	}


	public function upload_status( $request ) {
		$parameters = $request->get_params();

		$task_id  = $parameters['task_id'];
		$res      = get_option( 'instawp_upload_data_' . $task_id, '' );
		$response = new WP_REST_Response( $res );

		return rest_ensure_response( $response );
	}


	public function backup( $request ) {

//		$instawp_plugin  = new instaWP();
//		$args            = array(
//			"ismerge"      => "1",
//			"backup_files" => "files+db",
//			"local"        => "1",
//		);
//		$pre_backup_json = $instawp_plugin->prepare_backup_rest_api( json_encode( $args ) );
//		$pre_backup      = (array) json_decode( $pre_backup_json, true );
//
//		if ( $pre_backup['result'] == 'success' ) {
//
//			// Unique connection id / restore_id
//			$restore_id = $request->get_param( 'restore_id' );
//
//			$instawp_plugin->backup_now_api( $pre_backup['task_id'], $restore_id );
//
//			$data     = array(
//				'task_id' => $pre_backup['task_id'],
//				'status'  => true,
//				'message' => 'Backup Initiated',
//			);
//			$response = new WP_REST_Response( $data );
//			$response->set_status( 200 );
//		} else {
//
//			$data     = array(
//				'task_id' => '',
//				'status'  => false,
//				'message' => 'Failed To Initiated Backup',
//			);
//			$response = new WP_REST_Response( $data );
//			$response->set_status( 403 );
//		}

		global $instawp_plugin;

		$backup_options      = array(
			'ismerge'      => '',
			'backup_files' => 'files+db',
			'local'        => '1',
			'type'         => 'Manual',
			'action'       => 'backup',
			'is_migrate'   => false,
		);
		$part_urls           = array();
		$backup_options      = apply_filters( 'INSTAWP_CONNECT/Filters/migrate_backup_options', $backup_options );
		$pre_backup_response = $instawp_plugin->pre_backup( $backup_options );
		$migrate_task_id     = InstaWP_Setting::get_args_option( 'task_id', $pre_backup_response );
		$migrate_task        = InstaWP_taskmanager::get_task( $migrate_task_id );

		if ( $migrate_task_id ) {
			instawp_backup_files( new InstaWP_Backup_Task( $migrate_task_id ), array( 'clean_non_zip' => true ) );

			foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $data ) {
				foreach ( InstaWP_Setting::get_args_option( 'zip_files', $data, array() ) as $zip_file ) {
					$part_urls[] = array(
						'url'     => site_url( 'wp-content/' . INSTAWP_DEFAULT_BACKUP_DIR . '/' . InstaWP_Setting::get_args_option( 'file_name', $zip_file ) ),
						'part_id' => '',
					);
				}
			}

			// Cleaning the non-zipped files and folders
			instawp_clean_non_zipped_files_folder( $migrate_task );

			InstaWP_taskmanager::delete_all_task();
		}

		return new WP_REST_Response( array(
			'success'   => ! empty( $migrate_task_id ),
			'task_id'   => $migrate_task_id,
			'part_urls' => $part_urls,
		) );
	}


	public function task_status( $request ) {

		$data                = array(
			'task_id'  => '',
			'progress' => '',
			'status'   => true,
			'message'  => '',
		);
		$InstaWP_Backup_Task = new InstaWP_Backup_Task();
		$tasks               = InstaWP_Setting::get_tasks();

		$parameters = $request->get_params();

		$task_id = $parameters['task_id'];
		$backup  = new InstaWP_Backup_Task( $task_id );

		$list_tasks[ $task_id ] = $backup->get_backup_task_info( $task_id );
		//$backuplist=InstaWP_Backuplist::get_backuplist();

		if ( $list_tasks[ $task_id ]['status'] == '' ) {

			$data['task_id'] = '';
			$data['status']  = 'faild';
			$data['message'] = 'No Task Found';
			$response        = new WP_REST_Response( $data );
			$response->set_status( 404 );

			return rest_ensure_response( $response );
		}

		$backup_percent = '0';
		if ( $list_tasks[ $task_id ]['status']['str'] == 'completed' ) {
			$backup_percent  = '100';
			$data['message'] = 'Finished Backup';
			$backup_percent  = str_replace( '%', '', $list_tasks[ $task_id ]['task_info']['backup_percent'] );
			$data['message'] = $list_tasks[ $task_id ]['task_info']['api_descript'];

		}
		$data['task_id']  = $task_id;
		$data['progress'] = $backup_percent;
		$data['status']   = $list_tasks[ $task_id ]['status']['str'];

		$response = new WP_REST_Response( $data );

		return rest_ensure_response( $response );
	}


	public static function config_check_key( $api_key ) {

		global $InstaWP_Curl;

		$res        = array(
			'error'   => true,
			'message' => '',
		);
		$api_doamin = InstaWP_Setting::get_api_domain();
		$url        = $api_doamin . INSTAWP_API_URL . '/check-key';
		$log        = array(
			"url"     => $url,
			"api_key" => $api_key,
		);


		$api_key = sanitize_text_field( $api_key );

		$response      = wp_remote_get( $url, array(
			'body'    => '',
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Accept'        => 'application/json',
			),
		) );
		$response_code = wp_remote_retrieve_response_code( $response );

		$log = array(
			"response_code" => $response_code,
			"response"      => $response,
		);


		if ( ! is_wp_error( $response ) && $response_code == 200 ) {

			$body = (array) json_decode( wp_remote_retrieve_body( $response ), true );


			$connect_options = array();
			if ( $body['status'] == true ) {
				$connect_options['api_key']  = $api_key;
				$connect_options['response'] = $body;
				update_option( 'instawp_api_options', $connect_options );
				update_option( 'instawp_api_key', $api_key );

				// update config check token
				update_option( 'instawp_api_key_config_completed', 'yes' );

				$res = self::config_connect( $api_key );
			} else {
				$res = array(
					'error'   => true,
					'message' => 'Key Not Valid',
				);
			}
		}

		return $res;
	}


	public static function config_connect( $api_key ) {
		global $InstaWP_Curl;
		$res         = array(
			'error'   => true,
			'message' => '',
		);
		$php_version = substr( phpversion(), 0, 3 );

		/*Get username*/
		$username    = null;
		$admin_users = get_users(
			array(
				'role__in' => array( 'administrator' ),
				'fields'   => array( 'user_login' ),
			)
		);

		if ( ! empty( $admin_users ) ) {
			if ( is_null( $username ) ) {
				foreach ( $admin_users as $admin ) {
					$username = $admin->user_login;
					break;
				}
			}
		}
		/*Get username closes*/
		$body = json_encode(
			array(
				"url"         => get_site_url(),
				"php_version" => $php_version,
				"username"    => ! is_null( $username ) ? base64_encode( $username ) : "notfound",
			)
		);

		$api_doamin = InstaWP_Setting::get_api_domain();
		$url        = $api_doamin . INSTAWP_API_URL . '/connects';

		// $body = json_encode( array( "url" => get_site_url() ) );
		$log = array(
			'url'  => $url,
			'body' => $body,
		);

		$curl_response = $InstaWP_Curl->curl( $url, $body );

		if ( $curl_response['error'] == false ) {
			$response = (array) json_decode( $curl_response['curl_res'], true );
			if ( $response['status'] == true ) {

				update_option( 'instawp_connect_id_options', $response );
				$res['message'] = $response['message'];
				$res['error']   = false;
			} else {
				$res['message'] = 'Something Went Wrong. Please try again';
				$res['error']   = true;
			}
		}

		return $res;
	}


	public static function create_user( $user_details ) {
		global $wpdb;

		// $username = $user_details['username'];
		// $password = $user_details['password'];
		// $email    = $user_details['email'];
		foreach ( $user_details as $user_detail ) {
			//print_r($user_details);
			if ( ! isset( $user_detail['username'] ) || ! isset( $user_detail['email'] ) || ! isset( $user_detail['password'] ) ) {
				continue;
			}
			if ( username_exists( $user_detail['username'] ) == null && email_exists( $user_detail['email'] ) == false && ! empty( $user_detail['password'] ) ) {

				// Create the new user
				$user_id = wp_create_user( $user_detail['username'], $user_detail['password'], $user_detail['email'] );

				// Get current user object
				$user = get_user_by( 'id', $user_id );

				// Remove role
				$user->remove_role( 'subscriber' );

				// Add role
				$user->add_role( 'administrator' );
			} elseif ( email_exists( $user_detail['email'] ) || username_exists( $user_detail['username'] ) ) {
				$user = get_user_by( 'email', $user_detail['email'] );

				if ( $user !== false ) {
					$wpdb->update(
						$wpdb->users,
						[
							'user_login' => $user_detail['username'],
							'user_pass'  => md5( $user_detail['password'] ),
							'user_email' => $user_detail['email'],
						],
						[ 'ID' => $user->ID ]
					);

					$user->remove_role( 'subscriber' );

					// Add role
					$user->add_role( 'administrator' );
				}
			}
		}
	}

	/**
	 * Handle response for site inventory.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_inventory( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_mu_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$wp_plugins     = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', [] );
		$plugins        = [];

		foreach ( $wp_plugins as $name => $plugin ) {
			$slug      = explode( '/', $name );
			$plugins[] = [
				'slug'      => $slug[0],
				'version'   => $plugin['Version'],
				'activated' => in_array( $name, $active_plugins, true ),
			];
		}

		$wp_mu_plugins = get_mu_plugins();
		$mu_plugins    = [];

		foreach ( $wp_mu_plugins as $name => $plugin ) {
			$slug         = explode( '/', $name );
			$mu_plugins[] = [
				'slug'    => $slug[0],
				'version' => $plugin['Version'],
			];
		}

		if ( ! function_exists( 'wp_get_themes' ) || ! function_exists( 'wp_get_theme' ) ) {
			require_once ABSPATH . 'wp-includes/theme.php';
		}

		$wp_themes     = wp_get_themes();
		$current_theme = wp_get_theme();
		$themes        = [];

		foreach ( $wp_themes as $theme ) {
			$themes[] = [
				'slug'      => $theme->get_stylesheet(),
				'version'   => $theme->get( 'Version' ),
				'activated' => $theme->get_stylesheet() === $current_theme->get_stylesheet(),
			];
		}

		$results  = [
			'theme'     => $themes,
			'plugin'    => $plugins,
			'mu_plugin' => $mu_plugins,
			'core'      => [
				[ 'version' => get_bloginfo( 'version' ) ],
			],
		];
		$response = new WP_REST_Response( $results );

		return rest_ensure_response( $response );
	}

	/**
	 * Handle response for plugin and theme installation and activation.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function perform_install( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		} 

		$param_target   = $request->get_param( 'target' ) ?? '';
		$param_source   = $request->get_param( 'source' ) ?? 'wp.org';
		$param_type     = $request->get_param( 'type' ) ?? 'plugin';
		$param_activate = $request->get_param( 'activate' ) ?? false;
		$target_url     = $param_target;

		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}

		if ( ! class_exists( 'Theme_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
		}

		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}

		if ( 'plugin' === $param_type ) {
			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );

			if ( 'wp.org' === $param_source ) {
				if ( ! function_exists( 'plugins_api' ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
				}
	
				$api = plugins_api( 'plugin_information', [
					'slug'   => $param_target,
					'fields' => [
						'short_description' => false,
						'screenshots'       => false,
						'sections'          => false,
						'contributors'      => false,
						'versions'          => false,
						'banners'           => false,
						'requires'          => false,
						'rating'            => false,
						'ratings'           => false,
						'downloaded'        => false,
						'last_updated'      => false,
						'added'             => false,
						'tags'              => false,
						'compatibility'     => false,
						'homepage'          => false,
						'donate_link'       => false,
						'downloadlink'      => true,
					],
				] );
				if ( ! is_wp_error( $api ) && ! empty( $api->download_link ) ) {
					$target_url = $api->download_link;
				}
			}
		} elseif ( 'theme' === $param_type ) {
			$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );

			if ( 'wp.org' === $param_source ) {
				if ( ! function_exists( 'themes_api' ) ) {
					require_once ABSPATH . 'wp-admin/includes/theme.php';
				}

				$api = themes_api( 'theme_information', [
					'slug'   => $param_target,
					'fields' => [
						'screenshot_count' => 0,
						'contributors'     => false,
						'sections'         => false,
						'tags'             => false,
						'downloadlink'     => true,
					],
				] );
				if ( ! is_wp_error( $api ) && ! empty( $api->download_link ) ) {
					$target_url = $api->download_link;
				}
			}
		}

		if ( $this->is_valid_download_link( $target_url ) ) {
			$results = [ 'success' => true ];
			$result  = $upgrader->install( $target_url, [
				'overwrite_package' => true,
			] );

			if ( ! $result || is_wp_error( $result ) ) {
				$results = [
					'success' => false,
					'message' => is_wp_error( $result ) ? $result->get_error_message() : esc_html__( 'Installation failed!', 'instawp-connect' ),
				];
			} else {
				if ( filter_var( $param_activate, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ) {
					if ( 'plugin' === $param_type ) {
						if ( ! function_exists( 'activate_plugin' ) ) {
							require_once ABSPATH . 'wp-admin/includes/plugin.php';
						}
						
						activate_plugin( $upgrader->plugin_info(), '', false, true );
					} elseif ( 'theme' === $param_type ) {
						if ( ! function_exists( 'switch_theme' ) ) {
							require_once ABSPATH . 'wp-includes/theme.php';
						}

						switch_theme( $upgrader->theme_info()->get_stylesheet() );
					}
				}
			}
		} else {
			$results = [
				'success' => false,
				'message' => esc_html__( 'Provided URL is not valid!', 'instawp-connect' ),
			];
		}
		$response = new WP_REST_Response( $results );

		return rest_ensure_response( $response );
	}

	/**
	 * Returns error data with WP_REST_Response.
	 *
	 * @param WP_Error $error
	 *
	 * @return WP_REST_Response
	 */
	private function throw_error( $error ) {
		$response = new WP_REST_Response( [
			'success' => false,
			'message' => $error->get_error_message(),
		] );
		$response->set_status( $error->get_error_code() );

		return rest_ensure_response( $response );
	}

	/**
	 * Verify the plugin or theme download url.
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	private function is_valid_download_link( $url ) {
		$valid = false;
		if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$response = wp_remote_get( $url, [
				'timeout' => 60,
			] );
			$valid = 200 === wp_remote_retrieve_response_code( $response );
		}
		return $valid;
	}
}

global $InstaWP_Backup_Api;
$InstaWP_Backup_Api = new InstaWP_Backup_Api();


add_action( 'wp_head', function () {

	if ( isset( $_GET['debug'] ) ) {

		if ( isset( $_GET['key'] ) ) {

			$option_value = get_option( $_GET['key'] );

			echo "<pre>";
			print_r( $option_value );
			echo "</pre>";

			if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' ) {
				delete_option( $_GET['key'] );
			}
		}

		instawp_update_backup_progress( 'instawp-64a2cdb83abad' );


		die();
	}
}, 0 );
