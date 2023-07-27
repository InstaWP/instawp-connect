<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

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

		register_rest_route( $this->namespace . '/' . $this->version, 'config', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'config' ),
			'permission_callback' => '__return_true',
		) );

//		register_rest_route( $this->namespace . '/' . $this->version, 'task_status/(?P<task_id>\w+)', array(
//			'methods'             => 'GET',
//			'callback'            => array( $this, 'task_status' ),
//			'permission_callback' => '__return_true',
//		) );

//		register_rest_route( $this->namespace . '/' . $this->version, 'upload_status/(?P<task_id>\w+)', array(
//			'methods'             => 'GET',
//			'callback'            => array( $this, 'upload_status' ),
//			'permission_callback' => '__return_true',
//		) );

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

		register_rest_route( $this->namespace . '/' . $this->version_2, '/configuration', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_configuration' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_configuration' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_configuration' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/file-manager', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'file_manager' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/database-manager', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'database_manager' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/logs', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_logs' ),
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
	 * @param bool $check_management
	 *
	 * @return WP_Error|bool
	 */
	public function validate_api_request( WP_REST_Request $request, $check_management = false ) {

		if ( $check_management && ( ! defined( 'INSTAWP_ALLOW_MANAGE' ) || ( defined( 'INSTAWP_ALLOW_MANAGE' ) && true !== INSTAWP_ALLOW_MANAGE ) ) ) {
			return new WP_Error( 400, esc_html__( 'INSTAWP_ALLOW_MANAGE should be defined and set to true in wp-config.php file.', 'instawp-connect' ) );
		}

		// get authorization header value.
		$bearer_token = sanitize_text_field( $request->get_header( 'authorization' ) );
		$bearer_token = str_replace( 'Bearer ', '', $bearer_token );

		// check if the bearer token is empty
		if ( empty( $bearer_token ) ) {
			return new WP_Error( 401, esc_html__( 'Empty bearer token.', 'instawp-connect' ) );
		}

		$api_options = get_option( 'instawp_api_options', array() );

		//in some cases Laravel stores api key with ID attached in front of it.
		//so we need to remove it and then hash the key
		$api_key          = isset( $api_options['api_key'] ) ? trim( $api_options['api_key'] ) : '';
		$api_key_exploded = explode( '|', $api_key );

		if ( count( $api_key_exploded ) > 1 ) {
			$api_key_hash = hash( 'sha256', $api_key_exploded[1] );
		} else {
			$api_key_hash = hash( 'sha256', $api_key );
		}

//		echo "<pre>";
//		print_r( [ $api_key_hash ] );
//		echo "</pre>";

		$bearer_token_hash = trim( $bearer_token );

		if ( empty( $api_key ) || ! hash_equals( $api_key_hash, $bearer_token_hash ) ) {
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


	public static function backup_bg( $migrate_task_id, $parameters = array() ) {

		$migrate_task_obj = new InstaWP_Backup_Task( $migrate_task_id );
		$migrate_id       = InstaWP_Setting::get_args_option( 'migrate_id', $parameters );

		// Create backup zip
		instawp_backup_files( $migrate_task_obj, array( 'clean_non_zip' => true ) );

		// Update back progress
		instawp_update_backup_progress( $migrate_task_id, $migrate_id );

		// Update total parts number
		instawp_update_total_parts_number( $migrate_task_id, $migrate_id );

		// Upload backup parts to S3 cloud
		instawp_upload_backup_parts_to_cloud( $migrate_task_id, $migrate_id );
	}


	public function backup( WP_REST_Request $request ) {

		if ( is_wp_error( $response = $this->validate_api_request( $request ) ) ) {
			return $this->throw_error( $response );
		}

		$parameters       = $request->get_params();
		$is_background    = $parameters['instawp_is_background'] ?? true;
		$migrate_id       = InstaWP_Setting::get_args_option( 'migrate_id', $parameters );
		$migrate_settings = InstaWP_Setting::get_args_option( 'migrate_settings', $parameters );
		$migrate_task_id  = instawp_get_migrate_backup_task_id( array( 'migrate_settings' => $migrate_settings ) );

		InstaWP_taskmanager::store_migrate_id_to_migrate_task( $migrate_task_id, $migrate_id );

		if ( $is_background === false ) {
			return $this->throw_response( array(
				'task_id' => $migrate_task_id,
				'message' => esc_html__( 'Backup will run through CLI.', 'instawp-connect' ),
			) );
		}

		// Doing in background processing
		as_enqueue_async_action( 'instawp_backup_bg', [ $migrate_task_id, $parameters ] );

		do_action( 'action_scheduler_run_queue', 'Async Request' );

		return $this->throw_response( array( 'message' => esc_html__( 'Backup is running on background processing', 'instawp-connect' ) ) );
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

		$response = $this->validate_api_request( $request, true );
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

		$results = [
			'theme'     => $themes,
			'plugin'    => $plugins,
			'mu_plugin' => $mu_plugins,
			'core'      => [
				[ 'version' => get_bloginfo( 'version' ) ],
			],
		];

		return $this->send_response( $results );
	}


	/**
	 * Handle response for plugin and theme installation and activation.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function perform_install( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, true );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$param_target   = $request->get_param( 'target' ) ?? '';
		$param_source   = $request->get_param( 'source' ) ?? 'wp.org';
		$param_type     = $request->get_param( 'type' ) ?? 'plugin';
		$param_activate = $request->get_param( 'activate' ) ?? false;
		$target_url     = ( 'url' === $param_source ) ? $param_target : '';

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
					require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
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

		return $this->send_response( $results );
	}

	/**
	 * Handle response to retrieve the defined constant values.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_configuration( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, true );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$allowed_constants = [
			'WP_ENVIRONMENT_TYPE',
			'WP_DEVELOPMENT_MODE',
			'WP_DISABLE_FATAL_ERROR_HANDLER',
			'WP_DISABLE_ADMIN_EMAIL_VERIFY_SCREEN',
			'AUTOSAVE_INTERVAL',
			'WP_POST_REVISIONS',
			'MEDIA_TRASH',
			'EMPTY_TRASH_DAYS',
			'WP_MAIL_INTERVAL',
			'WP_MEMORY_LIMIT',
			'WP_MAX_MEMORY_LIMIT',
			'AUTOMATIC_UPDATER_DISABLED',
			'WP_AUTO_UPDATE_CORE',
			'CORE_UPGRADE_SKIP_NEW_BUNDLE',
			'WP_CACHE',
			'WP_DEBUG',
			'WP_DEBUG_LOG',
			'WP_DEBUG_DISPLAY',
			'WP_CONTENT_DIR',
			'WP_CONTENT_URL',
			'WP_PLUGIN_DIR',
			'WP_PLUGIN_URL',
			'UPLOADS',
			'AUTOSAVE_INTERVAL',
			'CONCATENATE_SCRIPTS',
		];

		$params = ( array ) $request->get_param( 'wp-config' ) ?? [];
		$params = array_filter( $params );
		if ( ! empty( $params ) ) {
			$allowed_constants = array_merge( $allowed_constants, $params );
		}
		$constants = array_diff( $allowed_constants, instawp()->get_blacklisted_constants() );

		$file = InstaWP_Tools::get_config_file();

		try {
			if ( ! class_exists( 'InstaWP_WP_Config' ) ) {
				require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-wp-config.php';
			}

			$config  = new InstaWP_WP_Config( $file );
			$results = [
				'wp-config'           => [],
				'wp-config-undefined' => [],
			];

			foreach ( $constants as $constant ) {
				if ( preg_match( '/[a-z]/', $constant ) ) {
					continue;
				}

				if ( $config->exists( 'constant', $constant ) ) {
					$value = trim( $config->get_value( 'constant', $constant ), "'" );
					if ( filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) !== null ) {
						$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
					} elseif ( filter_var( $value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) !== null ) {
						$value = intval( $value );
					}

					$results['wp-config'][ $constant ] = $value;
				} else {
					$results['wp-config-undefined'][ $constant ] = defined( $constant ) ? constant( $constant ) : '';
				}
			}
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		return $this->send_response( $results );
	}

	/**
	 * Handle wp-config.php file's constant modification.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_configuration( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, true );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$file    = InstaWP_Tools::get_config_file();
		$args    = [
			'normalize' => true,
			'add'       => true,
		];
		$content = file_get_contents( $file );
		if ( false === strpos( $content, "/* That's all, stop editing!" ) ) {
			preg_match( '@\$table_prefix = (.*);@', $content, $matches );
			$args['anchor']    = $matches[0] ?? '';
			$args['placement'] = 'after';
		}
		$params = ( array ) $request->get_param( 'wp-config' ) ?? [];

		try {
			if ( ! class_exists( 'InstaWP_WP_Config' ) ) {
				require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-wp-config.php';
			}

			$config  = new InstaWP_WP_Config( $file );
			$results = [ 'success' => true ];

			foreach ( $params as $key => $value ) {
				if ( empty( $key ) || preg_match( '/[a-z]/', $key ) || in_array( $key, instawp()->get_blacklisted_constants(), true ) ) {
					continue;
				}

				if ( is_array( $value ) ) {
					if ( ! array_key_exists( 'value', $value ) ) {
						continue;
					}

					$params = [ 'separator', 'add' ];
					foreach ( $params as $param ) {
						if ( array_key_exists( $param, $value ) ) {
							$args[ $param ] = $value[ $param ];
						}
					}
					$args['raw'] = array_key_exists( 'raw', $value ) ? $value['raw'] : true;
					$value       = $value['value'];
				} elseif ( is_bool( $value ) ) {
					$value       = $value ? 'true' : 'false';
					$args['raw'] = true;
				} elseif ( is_integer( $value ) ) {
					$value       = strval( $value );
					$args['raw'] = true;
				} else {
					$value       = sanitize_text_field( wp_unslash( $value ) );
					$args['raw'] = false;
				}

				$config->update( 'constant', $key, $value, $args );
			}
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		return $this->send_response( $results );
	}

	/**
	 * Handle response to delete the defined constants.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function delete_configuration( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, true );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$constants = [];
		$params    = ( array ) $request->get_param( 'wp-config' ) ?? [];
		$params    = array_filter( $params );

		if ( empty( $params ) ) {
			return $this->send_response( [
				'success' => false,
				'message' => esc_html__( 'No constants provided!', 'instawp-connect' ),
			] );
		}

		$constants = $params;
		$file      = InstaWP_Tools::get_config_file();

		try {
			if ( ! class_exists( 'InstaWP_WP_Config' ) ) {
				require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-wp-config.php';
			}

			$config  = new InstaWP_WP_Config( $file );
			$results = [ 'success' => true ];

			foreach ( $constants as $constant ) {
				$config->remove( 'constant', $constant );
			}
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		return $this->send_response( $results );
	}

	/**
	 * Handle file manager system.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function file_manager( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, true );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$file_name = InstaWP_Setting::get_option( 'instawp_file_manager_name', '' );

		if ( ! empty( $file_name ) ) {
			as_unschedule_all_actions( 'instawp_clean_file_manager', [ $file_name ], 'instawp-connect' );

			$file_path = InstaWP_File_Management::get_file_path( $file_name );
			if ( file_exists( $file_path ) ) {
				@unlink( $file_path );
			}
		}
		
		$url       = 'https://instawp.com/filemanager';
		$username  = InstaWP_Tools::get_random_string( 15 );
		$password  = InstaWP_Tools::get_random_string( 20 );
		$file_name = InstaWP_Tools::get_random_string( 20 );
		$token     = md5( $username . '|' . $password. '|' . $file_name );

		$search  = [ 
			'Tiny File Manager', 
			'CCP Programmers', 
			'tinyfilemanager.github.io', 
			'FM_SELF_URL', 
			'FM_SESSION_ID', 
			"'translation.json'",
			'</style>', 
		];
		$replace = [ 
			'InstaWP File Manager', 
			'InstaWP', 
			'instawp.com', 
			'INSTAWP_FILE_MANAGER_SELF_URL', 
			'INSTAWP_FILE_MANAGER_SESSION_ID', 
			"__DIR__ . '/translation.json'",
			'<?php if ( file_exists( __DIR__ . "/custom.css" ) ) { echo file_get_contents( __DIR__ . "/custom.css" ); } ?></style>', 
		];

		$file = file_get_contents( $url );
		$file = str_replace( $search, $replace, $file );
		$file = preg_replace( '!/\*.*?\*/!s', '', $file );

		$file_path        = InstaWP_File_Management::get_file_path( $file_name );
		$file_manager_url = InstaWP_File_Management::get_file_manager_url( $file_name );
		
		$results = [
			'login_url' => add_query_arg( [
				'action' => 'instawp-file-manager-auto-login',
				'token'  => hash( 'sha256', $token ),
			], admin_url( 'admin-post.php' ) ),
		];

		$config_file = InstaWP_Tools::get_config_file();

		try {
			$result = file_put_contents( $file_path, $file, LOCK_EX );
			if ( false === $result ) {
				throw new Exception( esc_html__( 'Failed to create the file manager file.', 'instawp-connect' ) );
			}

			$file       = file( $file_path );
			$new_line   = "if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) { die; }";
			$first_line = array_shift( $file );
			array_unshift( $file, $new_line );
			array_unshift( $file, $first_line );

			$fp = fopen( $file_path, 'w' );
			fwrite( $fp, implode( '', $file ) );
			fclose( $fp );

			if ( ! class_exists( 'InstaWP_WP_Config' ) ) {
				require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-wp-config.php';
			}

			$args = [
				'normalize' => true,
				'add'       => true,
				'raw'       => false,
			];

			$config = new InstaWP_WP_Config( $config_file );
			$config->update( 'constant', 'INSTAWP_FILE_MANAGER_USERNAME', $username, $args );
			$config->update( 'constant', 'INSTAWP_FILE_MANAGER_PASSWORD', $password, $args );
			$config->update( 'constant', 'INSTAWP_FILE_MANAGER_SELF_URL', $file_manager_url, $args );
			$config->update( 'constant', 'INSTAWP_FILE_MANAGER_SESSION_ID', 'instawp_file_manager', $args );

			set_transient( 'instawp_file_manager_login_token', $token, ( 15 * MINUTE_IN_SECONDS ) );
			InstaWP_Setting::update_option( 'instawp_file_manager_name', $file_name );
			
			flush_rewrite_rules();
			as_schedule_single_action( time() + DAY_IN_SECONDS, 'instawp_clean_file_manager', [ $file_name ], 'instawp-connect', false, 5 );
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		return $this->send_response( $results );
	}

	/**
	 * Handle database manager system.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function database_manager( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, true );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$file_name = InstaWP_Setting::get_option( 'instawp_database_manager_name', '' );

		if ( ! empty( $file_name ) ) {
			as_unschedule_all_actions( 'instawp_clean_database_manager', [ $file_name ], 'instawp-connect' );

			$file_path = InstaWP_Database_Management::get_file_path( $file_name );

			if ( file_exists( $file_path ) ) {
				@unlink( $file_path );
			}
		}
		
		$url       = 'https://instawp.com/dbeditor';
		$file_name = InstaWP_Tools::get_random_string( 20 );
		$token     = md5( $file_name );

		$search  = [ 
			'/\bjs_escape\b/', 
			'/\bget_temp_dir\b/', 
		];
		$replace = [ 
			'instawp_js_escape',
			'instawp_get_temp_dir',
		];

		$file = file_get_contents( $url );
		$file = preg_replace( $search, $replace, $file );

		$file_path            = InstaWP_Database_Management::get_file_path( $file_name );
		$database_manager_url = InstaWP_Database_Management::get_database_manager_url( $file_name );
		
		$results = [
			'login_url' => add_query_arg( [
				'action' => 'instawp-database-manager-auto-login',
				'token'  => hash( 'sha256', $token ),
			], admin_url( 'admin-post.php' ) ),
		];

		$config_file = InstaWP_Tools::get_config_file();

		try {
			$result = file_put_contents( $file_path, $file, LOCK_EX );
			if ( false === $result ) {
				throw new Exception( esc_html__( 'Failed to create the database manager file.', 'instawp-connect' )  );
			}

			$file = file( $file_path );
			$new_line = "if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) { die; }";
			$first_line = array_shift( $file );
			array_unshift( $file, $new_line );
			array_unshift( $file, $first_line );

			$fp = fopen( $file_path, 'w' );
			fwrite( $fp, implode( '', $file ) );
			fclose( $fp );

			set_transient( 'instawp_database_manager_login_token', $token, ( 15 * MINUTE_IN_SECONDS ) );
			InstaWP_Setting::update_option( 'instawp_database_manager_name', $file_name );
			
			flush_rewrite_rules();
			as_schedule_single_action( time() + DAY_IN_SECONDS, 'instawp_clean_database_manager', [ $file_name ], 'instawp-connect', false, 5 );
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		return $this->send_response( $results );
	}

	/**
	 * Handle response to retrieve debug logs.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_logs( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, true );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		try {
			$debug_enabled  = false;
			$debug_log_file = WP_CONTENT_DIR . '/debug.log';

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				$debug_enabled = true;

				if ( is_string( WP_DEBUG_LOG ) && file_exists( WP_DEBUG_LOG ) ) {
					$debug_log_file = WP_DEBUG_LOG;
				}
			}

			if ( ! $debug_enabled ) {
				return $this->send_response( [
					'success' => false,
					'message' => esc_html__( 'WP Debug is not enabled!', 'instawp-connect' ),
				] );
			}

			$file = $debug_log_file;
			if ( ! file_exists( $file ) ) {
				return $this->send_response( [
					'success' => false,
					'message' => esc_html__( 'Debug file not found!', 'instawp-connect' ),
				] );
			}

			$fh = fopen( $file, 'r' );
			if ( ! $fh ) {
				return $this->send_response( [
					'success' => false,
					'message' => esc_html__( 'Debug file can\'t be opened!', 'instawp-connect' ),
				] );
			}

			$logs  = [];
			$store = false;
			$index = 0;
			while ( $line = @fgets( $fh ) ) {
				$parts = InstaWP_Tools::get_parts( $line );
				if ( count( $parts ) >= 4 ) {
					$info = trim( preg_replace( '/\s+/', ' ', stripslashes( $parts[3] ) ) );
					$time = strtotime( $parts[1] );

					$logs[ $index ] = [
						'timestamp' => date( 'Y-m-d', strtotime( $parts[0] ) ) . ' ' . date( 'H:i:s', $time ),
						'timezone'  => $parts[2],
						'message'   => $info,
					];
					$index++;
				} else {
					$last_index = $index - 1;
					$logs[ $last_index ]['message'] .= trim( preg_replace( '/\s+/', ' ', $line ) );
				}
			}
			@fclose( $fh );

			$results = $logs;
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		return $this->send_response( $results );
	}

	/**
	 * Returns WP_REST_Response.
	 *
	 * @param array $results
	 *
	 * @return WP_REST_Response|WP_Error|WP_HTTP_Response
	 */
	private function send_response( $results ) {
		$response = new WP_REST_Response( $results );

		return rest_ensure_response( $response );
	}

	/**
	 * Returns error data with WP_REST_Response.
	 *
	 * @param WP_Error $error
	 *
	 * @return WP_REST_Response|WP_Error|WP_HTTP_Response
	 */
	public function throw_error( $error ) {
		$response = new WP_REST_Response( [
			'success' => false,
			'message' => $error->get_error_message(),
		] );
		$response->set_status( $error->get_error_code() );

		return rest_ensure_response( $response );
	}

	/**
	 * Return REST response
	 *
	 * @param $response
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	private function throw_response( $response = array() ) {

		$response['success'] = true;
		$rest_response       = new WP_REST_Response( $response );

		return rest_ensure_response( $rest_response );
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
			$valid    = 200 === wp_remote_retrieve_response_code( $response );
		}

		return $valid;
	}
}

global $InstaWP_Backup_Api;
$InstaWP_Backup_Api = new InstaWP_Backup_Api();