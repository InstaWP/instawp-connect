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

		register_rest_route( $this->namespace . '/' . $this->version, 'test-backup', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'test_backup' ),
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

		//autologin code call endpoint
		register_rest_route( $this->namespace . '/' . $this->version_2, '/auto-login-code', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'instawp_handle_auto_login_code' ),
			'permission_callback' => '__return_true',
		) );

		//autologin endpoint
		register_rest_route( $this->namespace . '/' . $this->version_2, '/auto-login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'instawp_handle_auto_login' ),
			'permission_callback' => '__return_true',
		) );


		// clear cache
		register_rest_route( $this->namespace . '/' . $this->version . '/remote-control', '/clear-cache', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'instawp_handle_clear_cache' ),
			'permission_callback' => '__return_true',
		) );
	}


	/**
	 * Handle response for clear cache endpoint
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	function instawp_handle_clear_cache( WP_REST_Request $request ) {

		$this->validate_api_request( $request );

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

		return new WP_REST_Response( array( 'error' => false, 'message' => esc_html( 'Cache clear success' ) ) );
	}


	/**
	 * Handle repsonse for login code generate
	 * */
	public function instawp_handle_auto_login_code( WP_REST_Request $request ) {

		$this->validate_api_request( $request );

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
				'message' => $message
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
		$response->set_status( 200 );

		return $response;
	}

	/**
	 * Auto login url generate
	 * */
	public function instawp_handle_auto_login( WP_REST_Request $request ) {

		$this->validate_api_request( $request );

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
					's' => base64_encode( $site_user )
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
		$response->set_status( 200 );

		return $response;
	}

	public function test_backup( $request ) {
		$parameters = $request->get_params();
		$backups    = InstaWP_Backuplist::get_backup_by_id( '6305fbad810e0' );
		$backupdir  = InstaWP_Setting::get_backupdir();
		$files      = array( $backup['backup']['files'][0]['file_name'] );

		foreach ( $backups['backup']['files'] as $backup ) {
			print_r( $backup );
		}

		$filePath = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backupdir . DIRECTORY_SEPARATOR . $backup['backup']['files'][0]['file_name'];

		$response = new WP_REST_Response( $backups );
		$response->set_status( 200 );

		return $response;
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

				rmdir( $destination );
			}
		}

		unlink( $plugin_zip );
	}


	public function config( $request ) {

		$parameters = $request->get_params();
		$results    = array(
			'status'     => false,
			'connect_id' => 0,
			'message'    => '',
		);

		// Override plugin file, if provided.
		if ( isset( $parameters['override_plugin_zip'] ) && ! empty( $override_plugin_zip = $parameters['override_plugin_zip'] ) ) {
			$this->override_plugin_zip_while_doing_config( $override_plugin_zip );
		}

		// Check if the configuration is already done, then no need to do it again.
		if ( 'yes' == get_option( 'instawp_api_key_config_completed' ) ) {

			$results['message'] = esc_html__( 'Already configured', 'instawp-connect' );

			return new WP_REST_Response( $results );
		}

		$this->instawp_log->CreateLogFile( $this->config_log_file_name, 'no_folder', 'Remote Config' );
		$this->instawp_log->WriteLog( 'Inti Api Config', 'notice' );

		//$this->instawp_log->CloseFile();
		$connect_ids = get_option( 'instawp_connect_id_options', '' );

		if ( ! empty( $connect_ids ) ) {
			if ( isset( $connect_ids['data']['id'] ) && ! empty( $connect_ids['data']['id'] ) ) {
				$id = $connect_ids['data']['id'];
			}
			$this->instawp_log->WriteLog( 'Already Connected : ' . json_encode( $connect_ids ), 'notice' );
			$results['status']     = true;
			$results['message']    = 'Connected';
			$results['connect_id'] = $id;
			$response              = new WP_REST_Response( $results );
			$response->set_status( 200 );

			// update config check token
			update_option( 'instawp_api_key_config_completed', 'yes' );

			return $response;
		}

		if ( ! isset( $parameters['api_key'] ) || empty( $parameters['api_key'] ) ) {
			$this->instawp_log->WriteLog( 'Api key is required', 'error' );
			$results['message'] = 'api key is required';
			$response           = new WP_REST_Response( $results );
			$response->set_status( 200 );

			return $response;
		}

		if ( isset( $parameters['api_domain'] ) ) {
			InstaWP_Setting::set_api_domain( $parameters['api_domain'] );
		}

		$res = $this->_config_check_key( $parameters['api_key'] );

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
			}
		} else {
			$results['status']     = true;
			$results['message']    = $res['message'];
			$results['connect_id'] = 0;
		}

		$response = new WP_REST_Response( $results );
		$response->set_status( 200 );

		return $response;
	}


	/**
	 * Valid api request and if invalid api key then stop executing.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return void
	 */
	function validate_api_request( WP_REST_Request $request ) {

		$bearer_token = sanitize_text_field( $request->get_header( 'authorization' ) );
		$bearer_token = str_replace( 'Bearer ', '', $bearer_token );
		$api_options  = get_option( 'instawp_api_options', array() );

		// check if the bearer token is empty
		if ( empty( $bearer_token ) ) {
			echo json_encode( array( 'error' => true, 'message' => esc_html__( 'Empty bearer token.', 'instawp-connect' ) ) );
			die();
		}

		//in some cases Laravel stores api key with ID attached in front of it.
		//so we need to remove it and then hash the key
		if ( count( $api_key_exploded = explode( "|", $api_options['api_key'] ) ) > 1 ) {
			$api_hash = hash( "sha256", $api_key_exploded[1] );
		} else {
			$api_hash = hash( "sha256", $api_options['api_key'] );
		}

		if ( ! isset( $api_options['api_key'] ) || $bearer_token != $api_hash ) {
			echo json_encode( array( 'error' => true, 'message' => esc_html__( 'Invalid bearer token.', 'instawp-connect' ) ) );
			die();
		}
	}

	public static function restore_bg( $backup_list, $restore_options, $parameters ) {
		// error_log(var_export($backup_list, true));
		// error_log(var_export($this_ref, true));

		global $instawp_plugin;

		$count_backup_list = count( $backup_list );
		$backup_index      = 1;

		$res_result = [];

		foreach ( $backup_list as $backup_list_key => $backup ) {

			do {
				$instawp_plugin->restore_api( $backup_list_key, $restore_options );

				$progress_results = $instawp_plugin->get_restore_progress_api( $backup_list_key );
				$progress_value   = $instawp_plugin->restore_data->get_next_restore_task_progress();


				//consider the foreach loop as well, if there are multiple backup_lists

				$progress_value = $progress_value * ( $backup_index / $count_backup_list );

				//total progress is half of what it is + 50 because the rest of the 50 is taken care by the server.

				$progress_value = ( $progress_value / 2 ) + 50;

				error_log( $progress_value );

				if ( $progress_value < 100 ) {
					$message = 'Restore in progress';
				} else {
					$message = 'Restore completed';
				}

				$progress_response = (array) json_decode( $progress_results );
				$res_result        = array_merge( self::restore_status( $message, $progress_value, $parameters['wp']['options'] )
				);

				// if ( $progress_value > $restore_progress ) {
				//    break;
				// }
			} while ( $progress_response['status'] != 'completed' || $progress_response['status'] == 'error' );

			$backup_index ++;
		}

		if ( $progress_response['status'] == 'completed' ) {
			$res_result['message'] = "Restore completed";
			// $this_ref->instawp_log->WriteLog( 'Restore Status: ' . json_encode( $ret ), 'success' );
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
			$response['status']  = true;
			$response['message'] = 'Restore task completed.';
		}

		if ( $progress_response['status'] == 'error' ) {
			$res_result['message'] = "Error occured";
		}

		// error_log(var_export($res_result, true));

		$instawp_plugin->delete_last_restore_data_api();
	}


	public function restore( WP_REST_Request $request ) {


		global $InstaWP_Curl, $instawp_plugin;

		$this->validate_api_request( $request );

		$parameters      = $request->get_params();
		$restore_options = json_encode( array(
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
		$backup_task     = new InstaWP_Backup_Task();
		$backup_task_ret = $backup_task->new_download_task();

		if ( $backup_task_ret['result'] == 'success' ) {

			$backup_download_ret = $InstaWP_Curl->download( $backup_task_ret['task_id'], $parameters['urls'] );

			if ( $backup_download_ret['result'] != INSTAWP_SUCCESS ) {
				return new WP_REST_Response( array( 'task_id' => $backup_task_ret['task_id'], 'completed' => false, 'progress' => 0, 'message' => 'Download error', 'status' => 'error' ) );
			} else {
				$this->restore_status( 'Backup file downloaded on target site', 51 );
			}
		}

		$instawp_plugin->delete_last_restore_data_api();

		$backup_uploader = new InstaWP_BackupUploader();
		$backup_uploader->_rescan_local_folder_set_backup_api();
		$backup_list = InstaWP_Backuplist::get_backuplist();

		if ( empty( $backup_list ) ) {
			return new WP_REST_Response( array( 'completed' => false, 'progress' => 0, 'message' => 'empty backup list' ) );
		}

		//background processing of restore using woocommerce's scheduler.
		as_enqueue_async_action( 'instawp_restore_bg', [ $backup_list, $restore_options, $parameters ] );

		//imidately run the schedule, don't want for the cron to run.
		do_action( 'action_scheduler_run_queue', 'Async Request' );

		// $count_backup_list = count( $backup_list );
		// $backup_index      = 1;

		// foreach ( $backup_list as $backup_list_key => $backup ) {

		// 	do {
		// 		$instawp_plugin->restore_api( $backup_list_key, $restore_options );

		// 		$progress_results = $instawp_plugin->get_restore_progress_api( $backup_list_key );
		// 		$progress_value   = $instawp_plugin->restore_data->get_next_restore_task_progress();


		// 		//consider the foreach loop as well, if there are multiple backup_lists

		// 		$progress_value = $progress_value * ( $backup_index / $count_backup_list );

		// 		//total progress is half of what it is + 50 because the rest of the 50 is taken care by the server.

		// 		$progress_value = ( $progress_value / 2 ) + 50;

		// 		error_log( $progress_value );

		// 		if ( $progress_value < 100 ) {
		// 			$message = 'in_progress';
		// 		} else {
		// 			$message = 'Restore completed';
		// 		}

		// 		$progress_response = (array) json_decode( $progress_results );
		// 		$res_result        = array_merge( $this->restore_status( $message, $progress_value ),
		// 			array(
		// 				'backup_list_key' => $backup_list_key,
		// 				//					'restore_response' => $restore_response,
		// 				'status'          => ( $progress_response['status'] ?? 'wait' ),
		// 			)
		// 		);

		// 		// if ( $progress_value > $restore_progress ) {
		// 		// 	break;
		// 		// }
		// 	} while ( $progress_response['status'] != 'completed' || $progress_response['status'] == 'error' );

		// 	$backup_index ++;
		// }

		// if ( $progress_response['status'] == 'completed' ) {
		// 	$res_result['message'] = "Restore completed";
		// 	$this->instawp_log->WriteLog( 'Restore Status: ' . json_encode( $ret ), 'success' );
		// 	if ( isset( $parameters['wp'] ) && isset( $parameters['wp']['users'] ) ) {
		// 		$this->create_user( $parameters['wp']['users'] );
		// 	}

		// 	if ( isset( $parameters['wp'] ) && isset( $parameters['wp']['options'] ) ) {
		// 		if ( is_array( $parameters['wp']['options'] ) ) {
		// 			$create_options = $parameters['wp']['options'];

		// 			foreach ( $create_options as $option_key => $option_value ) {
		// 				update_option( $option_key, $option_value );
		// 			}
		// 		}
		// 	}

		// 	$this->write_htaccess_rule();

		// 	InstaWP_AJAX::instawp_folder_remover_handle();
		// 	$response['status']  = true;
		// 	$response['message'] = 'Restore task completed.';
		// }

		// if ( $progress_response['status'] == 'error' ) {
		// 	$res_result['message'] = "Error occured";
		// }

		// // $instawp_plugin->delete_last_restore_data_api();


		// $res_result        = self::restore_status( 'Restore Initiated', 55 , $parameters['wp']['options']);

		// $res_result['completed'] = false;
		// $res_result['status'] = false;

		$res_result = array( 'completed' => false, 'progress' => 55, 'message' => 'Backup downloaded, restore initiated..', 'status' => 'wait' );

		return new WP_REST_Response( $res_result );
	}


	/**
	 * Write htaccess rule to update url for no media type
	 *
	 * @return bool
	 */
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


	public function restore_old( $request ) {
		global $InstaWP_Curl, $instawp_plugin;

		$response   = array();
		$parameters = $request->get_params();

		update_option( 'instawp_restore_urls', $parameters );
		$this->instawp_log->CreateLogFile( $this->download_log_file_name, 'no_folder', 'Download Backup' );
		$this->instawp_log->WriteLog( 'Restore Parameters: ' . json_encode( $parameters ), 'notice' );

		$backup = new InstaWP_Backup_Task();
		$ret    = $backup->new_download_task();

		$task_id = $ret['task_id'];
		update_option( 'instawp_init_restore', $task_id );
		$this->instawp_log->WriteLog( 'New Task Created: ' . $task_id, 'notice' );

		//		$instawp_plugin->end_shutdown_function = false;
		//		 register_shutdown_function(array($instawp_plugin, 'deal_shutdown_error'), $ret['task_id']);
		//		 $instawp_plugin->set_time_limit( $ret['task_id'] );
		//		 @ignore_user_abort(true);


		if ( $ret['result'] == 'success' ) {
			$this->instawp_log->WriteLog( 'Init Download', 'notice' );
			$curl_result = $InstaWP_Curl->download( $ret['task_id'], $parameters['urls'] );
		}

		// if ( $curl_result['result'] != INSTAWP_SUCCESS ) {

		//    $curl_result['task_id'] = $task_id;
		//    $curl_result['completed'] = $task_id;
		//    $REST_Response = new WP_REST_Response($curl_result);
		//    $REST_Response->set_status(200);
		//    return $REST_Response;
		// }

		$res        = $instawp_plugin->delete_last_restore_data_api();
		$backuplist = InstaWP_Backuplist::get_backuplist();

		//		$task = InstaWP_taskmanager::new_download_task_api();

		delete_option( 'instawp_backup_list' );
		$InstaWP_BackupUploader = new InstaWP_BackupUploader();
		$res                    = $InstaWP_BackupUploader->_rescan_local_folder_set_backup_api();

		$backuplist = InstaWP_Backuplist::get_backuplist();
		if ( empty( $backuplist ) ) {
			$this->instawp_log->WriteLog( 'Backup List is empty', 'error' );
		}
		$restore_options      = array(
			'skip_backup_old_site'     => '1',
			'skip_backup_old_database' => '1',
			'is_migrate'               => '1',
			'backup_db',
			'backup_themes',
			'backup_plugin',
			'backup_uploads',
			'backup_content',
			'backup_core',
		);
		$restore_options_json = json_encode( $restore_options );
		// global $instawp_plugin;
		// $this->restore_log = new InstaWP_Log();

		$res_result = [];

		echo "<pre>";
		print_r( $backuplist );
		echo "</pre>";

		foreach ( $backuplist as $key => $backup ) {
			//			do {
			//				if ( get_option( 'instawp_restore_response_sent' . $key ) != 'yes' ) {

			//				$instawp_plugin->restore_api( $key, $restore_options_json );
			//				$results    = $instawp_plugin->get_restore_progress_api( $key );
			//				$progress   = $instawp_plugin->restore_data->get_next_restore_task_progress();
			//				$res_result = $this->restore_status( 'in_progress', $progress );
			//				$ret        = (array) json_decode( $results );

			//				echo "<pre>";
			//				print_r( [
			//					'key'          => $key,
			//					'progress'     => $progress,
			//					'progress_old' => get_option( 'instawp_restore_progress_' . $key ),
			//					'res_result'   => $res_result
			//				] );
			//				echo "</pre>";


			//				if ( $progress > get_option( 'instawp_restore_progress_' . $key, 0 ) ) {
			//
			//					update_option( 'instawp_restore_progress_' . $key, $progress );
			//
			//					exit;
			//				}

			//				update_option( 'instawp_restore_progress_' . $key, $progress );

			//					return new WP_REST_Response( $res_result );
			//				}

			//			} while ( $ret['status'] != 'completed' );
		}


		if ( $ret['status'] == 'completed' ) {
			$this->instawp_log->WriteLog( 'Restore Status: ' . json_encode( $ret ), 'success' );
			if ( isset( $parameters['wp'] ) ) {
				$this->create_user( $parameters['wp']['users'] );
			}
			InstaWP_AJAX::instawp_folder_remover_handle();
			$response['status']  = true;
			$response['message'] = 'Restore task completed.';

			$res_result = $this->restore_status( $response['message'] );
		} else {

			//			$this->instawp_log->WriteLog( 'Restore Status: ' . json_encode( $ret ), 'error' );
			//			$response['status']  = false;
			//			$response['message'] = 'Something Went Wrong';
			//			$res_result = $this->restore_status( $response['message'], 80 );
		}


		//		$this->_disable_maintenance_mode();
		$instawp_plugin->delete_last_restore_data_api();

		$REST_Response = new WP_REST_Response( $res_result );
		$REST_Response->set_status( 200 );

		return $REST_Response;
	}

	public static function restore_status( $message, $progress = 100, $wp_options = [] ) {
		// error_log("Restore Status");

		// $task_id =       get_option('instawp_init_restore', false);
		// if(!$task_id)
		//    return;

		global $InstaWP_Curl;

		$instawp_log = new InstaWP_Log();

		$body = [];

		if ( count( $wp_options ) > 0 ) {

			if ( isset( $wp_options['instawp_sync_connect_id'] ) && isset( $wp_options['instawp_sync_parent_id'] ) ) {

				$parent_id  = $wp_options['instawp_sync_parent_id'];
				$api_doamin = InstaWP_Setting::get_api_domain();
				$url        = $api_doamin . INSTAWP_API_URL . '/connects/' . $parent_id . '/restore_status';


				$domain = str_replace( "https://", "", get_site_url() );
				$domain = str_replace( "http://", "", $domain );

				$body = array(
					// "task_id"         => $task_id,
					// "type"     => 'restore',
					"progress"               => $progress,
					"message"                => $message,
					"connect_id"             => $parent_id,
					"completed"              => ( $progress == 100 ) ? true : false,
					"destination_connect_id" => $wp_options['instawp_sync_connect_id']

				);


				$body_json = json_encode( $body );


				// error_log('Update Restore Status call has made the url is : '. $url);

				$instawp_log->CreateLogFile( 'update_restore_status_call', 'no_folder', 'Update restore status call' );

				$instawp_log->WriteLog( 'Restore Status percentage is : ' . $progress, 'notice' );
				$instawp_log->WriteLog( 'Update Restore Status call has made the body is : ' . $body_json, 'notice' );
				$instawp_log->WriteLog( 'Update Restore Status call has made the url is : ' . $url, 'notice' );

				$curl_response = $InstaWP_Curl->curl( $url, $body_json );

				// error_log("API Error: ==> ".$curl_response['error']);
				// error_log('After Update Restore Status call made the response : '. print_r($curl_response, true));

				if ( $curl_response['error'] == false ) {

					// $this->instawp_log->WriteLog( 'After Update Restore Status call made the response : ' . $curl_response['curl_res'], 'notice' );
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
		// error_log('instawp rest api \n '.print_r(get_option( 'instawp_backup_status_options'),true));
		// update_option( 'instawp_finish_restore', $message );

		return $body;
	}

	public function upload_status( $request ) {
		$parameters = $request->get_params();

		$task_id  = $parameters['task_id'];
		$res      = get_option( 'instawp_upload_data_' . $task_id, '' );
		$response = new WP_REST_Response( $res );
		$response->set_status( 200 );

		return $response;
	}

	public function test( $request ) {

		$InstaWP_S3Compat = new InstaWP_S3Compat();
		$backup           = InstaWP_Backuplist::get_backup_by_id( '62fb88296fe49' );
		$backupdir        = InstaWP_Setting::get_backupdir();
		$files            = array( $backup['backup']['files'][0]['file_name'] );
		$res              = $InstaWP_S3Compat->upload_api( '62fb88296fe49', $backup['backup']['files'][0]['file_name'] );

		$filePath = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backupdir . DIRECTORY_SEPARATOR . $backup['backup']['files'][0]['file_name'];

		//print_r( get_option( 'instawp_upload_data_62fb88296fe49', '' ) );
		// if (isset($backup['backup']['files'][0]['file_name'])) {
		//    $filePath = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backupdir . DIRECTORY_SEPARATOR . $backup['backup']['files'][0]['file_name'];

		// }

		$response = new WP_REST_Response( $res );
		$response->set_status( 200 );

		return $response;
	}

	public function backup( $request ) {
		$instawp_plugin = new instaWP();
		$args           = array(
			"ismerge"      => "1",
			"backup_files" => "files+db",
			"local"        => "1",
		);

		$pre_backup_json = $instawp_plugin->prepare_backup_rest_api( json_encode( $args ) );
		$pre_backup      = (array) json_decode( $pre_backup_json, true );

		//print_r($data);
		if ( $pre_backup['result'] == 'success' ) {

			// Unique connection id / restore_id
			$restore_id = $request->get_param( 'restore_id' );
			$backup_now = $instawp_plugin->backup_now_api( $pre_backup['task_id'], $restore_id );

			$data     = array(
				'task_id' => $pre_backup['task_id'],
				'status'  => true,
				'message' => 'Backup Initiated',
			);
			$response = new WP_REST_Response( $data );
			$response->set_status( 200 );
		} else {

			$data     = array(
				'task_id' => '',
				'status'  => false,
				'message' => 'Failed To Initiated Backup',
			);
			$response = new WP_REST_Response( $data );
			$response->set_status( 403 );
		}

		return $response;

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

			return $response;
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
		$response->set_status( 200 );

		return $response;

	}

	public function _config_check_key( $api_key ) {
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
		$this->instawp_log->WriteLog( 'Init Check Key: ' . json_encode( $log ), 'notice' );


		$api_key = sanitize_text_field( $api_key );
		//102|SouBdaa121zb1U2DDlsWK8tXaoV8L31WsXnqMyOy';
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
		$this->instawp_log->WriteLog( 'Check Key Response : ' . json_encode( $response ), 'notice' );

		if ( ! is_wp_error( $response ) && $response_code == 200 ) {

			$body = (array) json_decode( wp_remote_retrieve_body( $response ), true );
			$this->instawp_log->WriteLog( 'Check Key Response Body: ' . json_encode( $body ), 'notice' );

			$connect_options = array();
			if ( $body['status'] == true ) {
				$connect_options['api_key']  = $api_key;
				$connect_options['response'] = $body;
				update_option( 'instawp_api_options', $connect_options );

				// update config check token
				update_option( 'instawp_api_key_config_completed', 'yes' );

				$this->instawp_log->WriteLog( 'Save instawp_api_options: ' . json_encode( $connect_options ), 'success' );
				$res = $this->_config_connect( $api_key );
			} else {
				$res = array(
					'error'   => true,
					'message' => 'Key Not Valid',

				);
				$this->instawp_log->WriteLog( 'Something Wrong: ' . json_encode( $body ), 'error' );
			}
		}

		return $res;
	}

	public function _config_connect( $api_key ) {
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
				'fields'   => array( 'user_login' )
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
		$this->instawp_log->WriteLog( '_config_connect_init ' . json_encode( $log ), 'notice' );

		$curl_response = $InstaWP_Curl->curl( $url, $body );

		$this->instawp_log->WriteLog( '_config_connect_response ' . json_encode( $curl_response ), 'notice' );

		if ( $curl_response['error'] == false ) {
			$response = (array) json_decode( $curl_response['curl_res'], true );
			update_option( '_config_connect_response', $response );
			$response = (array) json_decode( $curl_response['curl_res'], true );
			if ( $response['status'] == true ) {

				update_option( 'instawp_connect_id_options', $response );
				$this->instawp_log->WriteLog( 'Save instawp_connect_id_options ' . json_encode( $response ), 'success' );
				$res['message'] = $response['message'];
				$res['error']   = false;
			} else {
				$res['message'] = 'Something Went Wrong. Please try again';
				$res['error']   = true;
				$this->instawp_log->WriteLog( 'Something Went Wrong. Please try again ' . json_encode( $curl_response ), 'error' );
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

global $InstaWP_Backup_Api;
$InstaWP_Backup_Api = new InstaWP_Backup_Api();
