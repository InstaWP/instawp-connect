<?php

defined( 'ABSPATH' ) || die;

class InstaWP_Backup_Api {

	protected $namespace;
	protected $version;
	protected $version_2;
	protected $version_3;

	public function __construct() {
		$this->version   = 'v1';
		$this->version_2 = 'v2';
		$this->version_3 = 'v3';
		$this->namespace = 'instawp-connect';

		add_action( 'rest_api_init', array( $this, 'add_api_routes' ) );
		add_filter( 'rest_authentication_errors', array( $this, 'rest_access' ), 999 );
	}

	public function add_api_routes() {

		register_rest_route( $this->namespace . '/' . $this->version, '/config', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'config' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/disconnect', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'disconnect' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/auto-login-code', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'auto_login_code' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/auto-login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'auto_login' ),
			'permission_callback' => '__return_true',
		) );

		// Remote Management //
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/clear-cache', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'clear_cache' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/inventory', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_inventory' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/install', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'perform_install' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/update', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'perform_update' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/activate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'perform_activation' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/deactivate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'perform_deactivation' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/auto-update', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'auto_update' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/configuration', array(
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

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/file-manager', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'file_manager' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/database-manager', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'database_manager' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/logs', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_logs' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/settings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_remote_management' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_remote_management' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( $this->namespace . '/' . $this->version_3, '/site-usage', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'site_usage' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_3, '/pull', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_pull_api' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_3, '/push', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_push_api' ),
			'permission_callback' => '__return_true',
		) );

//      register_rest_route( $this->namespace . '/' . $this->version_3, '/get-push-config', array(
//          'methods'             => 'GET',
//          'callback'            => array( $this, 'handle_get_push_config_api' ),
//          'permission_callback' => '__return_true',
//      ) );

		register_rest_route( $this->namespace . '/' . $this->version_3, '/debug', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_debug' ),
			'permission_callback' => '__return_true',
		) );
	}

	function handle_debug( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$drivers   = array();
		$libraries = array(
			'SQLite3'                         => extension_loaded( 'sqlite3' ),
			'Zip'                             => extension_loaded( 'zip' ),
			'PDO'                             => extension_loaded( 'pdo' ),
			"PHP's zlib (for gz compression)" => extension_loaded( 'zlib' ),
			'PharData'                        => extension_loaded( 'phar' ),
			'mysqli_real_connect'             => extension_loaded( 'mysqli' ),
		);

		$pearArchiveTarExists = false;

		@include 'Archive/Tar.php';

		if ( class_exists( 'Archive_Tar' ) ) {
			$pearArchiveTarExists = true;
		}

		$libraries['PEAR Archive_Tar'] = $pearArchiveTarExists;

		if ( $libraries['PDO'] ) {
			$drivers = PDO::getAvailableDrivers();
		}

		return $this->send_response( array(
			'libraries'          => $libraries,
			'drivers'            => $drivers,
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
		) );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function handle_pull_api( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$migrate_key        = sanitize_text_field( $request->get_param( 'migrate_key' ) );
		$migrate_settings   = $request->get_param( 'migrate_settings' );
		$pre_check_response = instawp()->tools::get_pull_pre_check_response( $migrate_key, $migrate_settings );

		if ( is_wp_error( $pre_check_response ) ) {
			return $this->throw_error( $pre_check_response );
		}

		return $this->send_response( $pre_check_response );
	}

	/**
	 * Handle response for push api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function handle_push_api( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		global $wp_version;

		// Create InstaWP backup directory
		instawp()->tools::create_instawpbackups_dir();

		// Clean InstaWP backup directory
		instawp()->tools::clean_instawpbackups_dir();

		$migrate_key      = instawp()->tools::get_random_string( 40 );
		$migrate_settings = instawp()->tools::get_migrate_settings();
		$api_signature    = hash( 'sha512', $migrate_key . current_time( 'U' ) );
		$dest_file_url    = instawp()->tools::generate_destination_file( $migrate_key, $api_signature );

		// Check accessibility of serve file
		if ( ! instawp()->tools::is_migrate_file_accessible( $dest_file_url ) ) {
			return $this->throw_error( new WP_Error( 403, esc_html__( 'Could not create destination file.', 'instawp-connect' ) ) );
		}

		return $this->send_response(
			array(
				'php_version'      => PHP_VERSION,
				'wp_version'       => $wp_version,
				'plugin_version'   => INSTAWP_PLUGIN_VERSION,
				'file_size'        => instawp()->tools::get_total_sizes( 'files', $migrate_settings ),
				'db_size'          => instawp()->tools::get_total_sizes( 'db' ),
				'active_plugins'   => InstaWP_Setting::get_option( 'active_plugins', array() ),
				'migrate_settings' => $migrate_settings,
				'migrate_key'      => $migrate_key,
				'dest_url'         => $dest_file_url,
				'api_signature'    => $api_signature,
			)
		);
	}


	/**
	 * Handle get-push-config api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
//  public function handle_get_push_config_api( WP_REST_Request $request ) {
//
//      $response = $this->validate_api_request( $request );
//      if ( is_wp_error( $response ) ) {
//          return $this->throw_error( $response );
//      }
//
//      global $wp_version;
//
//      $migrate_settings = instawp()->tools::get_migrate_settings();
//
//      return $this->send_response(
//          array(
//              'php_version'    => PHP_VERSION,
//              'wp_version'     => $wp_version,
//              'plugin_version' => INSTAWP_PLUGIN_VERSION,
//              'file_size'      => instawp()->tools::get_total_sizes( 'files', $migrate_settings ),
//              'db_size'        => instawp()->tools::get_total_sizes( 'db' ),
//              'settings'       => $migrate_settings,
//              'active_plugins' => InstaWP_Setting::get_option( 'active_plugins', [] ),
//          )
//      );
//  }


	/**
	 * Handle website total size info
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function site_usage( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$info = instawp()->get_directory_info( ABSPATH );

		return $this->send_response( $info );
	}

	/**
	 * Handle response for disconnect api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function disconnect( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		instawp_reset_running_migration( 'hard' );

		return $this->send_response( array(
			'success' => true,
			'message' => __( 'Plugin reset Successful.', 'instawp-connect' ),
		) );
	}

	/**
	 * Handle response for login code generate
	 * */
	public function auto_login_code( WP_REST_Request $request ) {

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
	public function auto_login( WP_REST_Request $request ) {

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

		$current_login_code   = get_transient( 'instawp_auto_login_code' );
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

		$parameters = $this->filter_params( $request );
		$connect_id = instawp_get_connect_id();
		$results    = array(
			'status'     => false,
			'connect_id' => 0,
			'message'    => '',
		);

		if ( isset( $parameters['override_plugin_zip'] ) && ! empty( $parameters['override_plugin_zip'] ) ) {
			$this->override_plugin_zip_while_doing_config( $parameters['override_plugin_zip'] );

			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			$plugin_slug = INSTAWP_PLUGIN_SLUG . '/' . INSTAWP_PLUGIN_SLUG . '.php';
			if ( ! is_plugin_active( $plugin_slug ) ) {
				activate_plugin( $plugin_slug );
			}
		}

		if ( ! empty( $connect_id ) && ( isset( $parameters['force'] ) && $parameters['force'] !== true ) ) {
			$results['status']     = true;
			$results['message']    = esc_html__( 'Already Configured', 'instawp-connect' );
			$results['connect_id'] = $connect_id;

			return $this->send_response( $results );
		}

		// if api_key is not passed on param
		if ( empty( $parameters['api_key'] ) ) {
			$results['message'] = esc_html__( 'Api key is required', 'instawp-connect' );

			return $this->send_response( $results );
		}

		// if api_key is passed on param
		if ( isset( $parameters['api_domain'] ) ) {
			InstaWP_Setting::set_api_domain( $parameters['api_domain'] );
		}

		$config_response = self::config_check_key( $parameters['api_key'] );

		if ( $config_response['error'] ) {
			$results['message'] = InstaWP_Setting::get_args_option( 'message', $config_response );

			return $this->send_response( $results );
		}

		$connect_id = instawp_get_connect_id();
		if ( ! empty( $connect_id ) ) {
			$results['status']     = true;
			$results['message']    = 'Connected';
			$results['connect_id'] = $connect_id;
		}

		// if any wp_option is passed, then store it
		if ( isset( $parameters['wp'] ) && isset( $parameters['wp']['options'] ) && is_array( $parameters['wp']['options'] ) ) {
			foreach ( $parameters['wp']['options'] as $option_key => $option_value ) {
				update_option( $option_key, $option_value );
			}
		}

		// if any user is passed, then create it
		if ( isset( $parameters['wp'] ) && isset( $parameters['wp']['users'] ) ) {
			InstaWP_Tools::create_user( $parameters['wp']['users'] );
		}

		return $this->send_response( $results );
	}

	/**
	 * Valid api request and if invalid api key then stop executing.
	 *
	 * @param WP_REST_Request $request
	 * @param string $option
	 *
	 * @return WP_Error|bool
	 */
	public function validate_api_request( WP_REST_Request $request, string $option = '' ) {

		// get authorization header value.
		$bearer_token = sanitize_text_field( $request->get_header( 'authorization' ) );
		$bearer_token = str_replace( 'Bearer ', '', $bearer_token );

		// check if the bearer token is empty
		if ( empty( $bearer_token ) ) {
			return new WP_Error( 401, esc_html__( 'Empty bearer token.', 'instawp-connect' ) );
		}

		//in some cases Laravel stores api key with ID attached in front of it.
		//so we need to remove it and then hash the key
		$api_key          = InstaWP_Setting::get_api_key();
		$api_key_exploded = explode( '|', $api_key );

		if ( count( $api_key_exploded ) > 1 ) {
			$api_key_hash = hash( 'sha256', $api_key_exploded[1] );
		} else {
			$api_key_hash = hash( 'sha256', $api_key );
		}

		$bearer_token_hash = trim( $bearer_token );

		if ( empty( $api_key ) || ! hash_equals( $api_key_hash, $bearer_token_hash ) ) {
			return new WP_Error( 403, esc_html__( 'Invalid bearer token.', 'instawp-connect' ) );
		}

		if ( ! empty( $option ) && ! $this->is_enabled( $option ) ) {

			$message = sprintf( 'Setting is disabled! Please enable %s Option from InstaWP Connect <a href="%s" target="_blank">Remote Management settings</a> page.',
				$this->get_management_options( $option ),
				admin_url( 'admin.php?page=instawp&tab=manage' )
			);

			return new WP_Error( 400, $message );
		}

		return true;
	}

	public static function config_check_key( $api_key ): array {

		$res        = array(
			'error'   => true,
			'message' => '',
		);
		$api_domain = InstaWP_Setting::get_api_domain();
		$url        = $api_domain . INSTAWP_API_URL . '/check-key';

		$response = wp_remote_get( $url, array(
			'sslverify' => false,
			'headers'   => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Accept'        => 'application/json',
			),
		) );

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( ! is_wp_error( $response ) && $response_code == 200 ) {
			$body = ( array ) json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $body['status'] == true ) {
				$api_options = InstaWP_Setting::get_option( 'instawp_api_options', array() );

				update_option( 'instawp_api_options', array_merge( $api_options, array(
					'api_key'  => $api_key,
					'response' => $body,
				) ) );

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

	public static function config_connect( $api_key ): array {
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

		$api_domain = InstaWP_Setting::get_api_domain();
		$url        = $api_domain . INSTAWP_API_URL . '/connects';

		$curl_response = $InstaWP_Curl->curl( $url, $body );

		if ( $curl_response['error'] == false ) {
			$response = ( array ) json_decode( $curl_response['curl_res'], true );

			if ( $response['status'] == true ) {
				InstaWP_Setting::set_api_key( $api_key );

				if ( isset( $response['data']['id'] ) && ! empty( $response['data']['id'] ) ) {
					InstaWP_Setting::set_connect_id( $response['data']['id'] );
				}

				$res['message'] = $response['message'];
				$res['error']   = false;
			} else {
				$res['message'] = 'Something Went Wrong. Please try again';
				$res['error']   = true;
			}
		}

		return $res;
	}

	/**
	 * Handle response for clear cache endpoint
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function clear_cache( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$cache_api = new \InstaWP\Connect\Helpers\Cache();
		$response  = $cache_api->clean();

		return $this->send_response( $response );
	}

	/**
	 * Handle response for site inventory.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_inventory( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'inventory' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$inventory = new \InstaWP\Connect\Helpers\Inventory();
		$response  = $inventory->fetch();

		return $this->send_response( $response );
	}

	/**
	 * Handle response for plugin and theme installation and activation.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function perform_install( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'install_plugin_theme' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$params = $this->filter_params( $request );

		$installer = new \InstaWP\Connect\Helpers\Installer( $params );
		$response  = $installer->start();

		return $this->send_response( $response );
	}

	/**
	 * Handle response for core, plugin and theme update.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function perform_update( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'update_core_plugin_theme' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$params = $this->filter_params( $request );

		$installer = new \InstaWP\Connect\Helpers\Updater( $params );
		$response  = $installer->update();

		return $this->send_response( $response );
	}

	/**
	 * Handle response for activate plugins and theme.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function perform_activation( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'activate_deactivate' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response = array();
		$params   = $this->filter_params( $request );

		foreach ( $params as $key => $param ) {
			if ( 'plugin' === $param['type'] ) {
				if ( ! function_exists( 'activate_plugin' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$activate = activate_plugin( $param['asset'] );
				$response[ $key ] = array_merge( [
					'success' => ! is_wp_error( $activate ),
					'message' => is_wp_error( $activate ) ? $activate->get_error_message() : ''
				], $param );
			} elseif ( 'theme' === $param['type'] ) {
				if ( ! function_exists( 'switch_theme' ) ) {
					require_once ABSPATH . 'wp-includes/theme.php';
				}

				switch_theme( $param['asset'] );
				$response[ $key ] = array_merge( [
					'success' => true
				], $param );
			}
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for deactivate plugins.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function perform_deactivation( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'activate_deactivate' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$params = $this->filter_params( $request );

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( $params );

		return $this->send_response( [ 'success' => true ] );
	}

	/**
	 * Handle response for toggle plugin and theme auto update.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function auto_update( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'update_core_plugin_theme' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response = array();
		$params   = $this->filter_params( $request );

		foreach ( $params as $key => $param ) {
			$type  = $param['type'] ?? 'plugin';
			$asset = $param['asset'] ?? '';
			$state = $param['state'] ?? 'disable';

			if ( 'plugin' === $type ) {
				$option = 'auto_update_plugins';

				/** This filter is documented in wp-admin/includes/class-wp-plugins-list-table.php */
				$all_items = apply_filters( 'all_plugins', get_plugins() );
			} elseif ( 'theme' === $type ) {
				$option    = 'auto_update_themes';
				$all_items = wp_get_themes();
			}

			if ( ! isset( $option ) || ! isset( $all_items ) ) {
				$response[ $key ] = array(
					'success' => false,
					'message' => __( 'Invalid data. Unknown type.' )
				);
				continue;
			}

			if ( ! array_key_exists( $asset, $all_items ) ) {
				$response[ $key ] = array(
					'success' => false,
					'message' => __( 'Invalid data. The item does not exist.' )
				);
				continue;
			}

			$auto_updates = (array) get_site_option( $option, array() );

			if ( 'disable' === $state ) {
				$auto_updates = array_diff( $auto_updates, array( $asset ) );
			} else {
				$auto_updates[] = $asset;
				$auto_updates   = array_unique( $auto_updates );
			}

			// Remove items that have been deleted since the site option was last updated.
			$auto_updates = array_intersect( $auto_updates, array_keys( $all_items ) );

			update_site_option( $option, $auto_updates );

			$response[ $key ] = array_merge( array(
				'success' => true
			), $param );
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response to retrieve the defined constant values.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_configuration( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'config_management' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$params    = ( array ) $request->get_param( 'wp-config' ) ?? array();
		$wp_config = new \InstaWP\Connect\Helpers\WPConfig( $params );
		$response  = $wp_config->fetch();

		return $this->send_response( $response );
	}

	/**
	 * Handle wp-config.php file's constant modification.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_configuration( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'config_management' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$params    = ( array ) $request->get_param( 'wp-config' ) ?? array();
		$wp_config = new \InstaWP\Connect\Helpers\WPConfig( $params );
		$response  = $wp_config->update();

		return $this->send_response( $response );
	}

	/**
	 * Handle response to delete the defined constants.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function delete_configuration( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'config_management' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$params    = ( array ) $request->get_param( 'wp-config' ) ?? array();
		$wp_config = new \InstaWP\Connect\Helpers\WPConfig( $params );
		$response  = $wp_config->delete();

		return $this->send_response( $response );
	}

	/**
	 * Handle file manager system.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function file_manager( WP_REST_Request $request ) {
		$response = $this->validate_api_request( $request, 'file_manager' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		InstaWP_Tools::instawp_reset_permalink();

		$file_manager = new \InstaWP\Connect\Helpers\FileManager();
		$response     = $file_manager->get();

		return $this->send_response( $response );
	}

	/**
	 * Handle database manager system.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function database_manager( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'database_manager' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		InstaWP_Tools::instawp_reset_permalink();

		$database_manager = new \InstaWP\Connect\Helpers\DatabaseManager();
		$response         = $database_manager->get();

		return $this->send_response( $response );
	}

	/**
	 * Handle response to retrieve debug logs.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_logs( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'debug_log' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$debug_log = new \InstaWP\Connect\Helpers\DebugLog();
		$response  = $debug_log->fetch();

		return $this->send_response( $response );
	}

	/**
	 * Handle response to retrieve remote management settings.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_remote_management( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$results = array();
		$options = $this->get_management_options();
		foreach ( array_keys( $options ) as $option ) {
			$default = 'heartbeat' === $option ? 'on' : 'off';
			$value   = InstaWP_Setting::get_option( 'instawp_rm_' . $option, $default );
			$value   = empty( $value ) ? $default : $value;

			$results[ $option ] = $value;
		}

		return $this->send_response( $results );
	}

	/**
	 * Handle response to set remote management settings.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_remote_management( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$params  = $this->filter_params( $request );
		$options = $this->get_management_options();
		$results = array();

		foreach ( $params as $key => $value ) {
			$results[ $key ]['status'] = false;

			if ( array_key_exists( $key, $options ) ) {
				$results[ $key ]['message'] = esc_html__( 'Success!', 'instawp-connect' );

				if ( 'off' === $value ) {
					$update                    = update_option( 'instawp_rm_' . $key, $value );
					$results[ $key ]['status'] = $update;
					if ( ! $update ) {
						$results[ $key ]['message'] = esc_html__( 'Setting is already disabled.', 'instawp-connect' );
					}
				} else {
					$results[ $key ]['message'] = esc_html__( 'You can not enable this setting through API.', 'instawp-connect' );
					$default                    = 'heartbeat' === $key ? 'on' : 'off';
					$value                      = InstaWP_Setting::get_option( 'instawp_rm_' . $key, $default );
					$value                      = empty( $value ) ? $default : $value;
				}

				$results[ $key ]['value'] = $value;
			} else {
				$results[ $key ]['message'] = esc_html__( 'Setting does not exist.', 'instawp-connect' );
				$results[ $key ]['value']   = '';
			}
		}

		return $this->send_response( $results );
	}

	/**
	 * Checks for a current route being requested, and processes the allowlist
	 *
	 * @param $access
	 *
	 * @return WP_Error|null|boolean
	 */
	public function rest_access( $access ) {
		$current_route = $this->get_current_route();

		if ( strpos( $current_route, 'instawp-connect' ) !== false ) {
			return null;
		}

		return $access;
	}

	/**
	 * Current REST route getter.
	 *
	 * @return string
	 */
	private function get_current_route() {
		$rest_route = $GLOBALS['wp']->query_vars['rest_route'];

		return ( empty( $rest_route ) || is_null( $rest_route ) || '/' == $rest_route ) ? $rest_route : untrailingslashit( $rest_route );
	}

	/**
	 * Returns WP_REST_Response.
	 *
	 * @param array $results
	 *
	 * @return WP_REST_Response|WP_Error|WP_HTTP_Response
	 */
	protected function send_response( $results ) {
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
		$response = new WP_REST_Response( array(
			'success' => false,
			'message' => $error->get_error_message(),
		) );
		$response->set_status( $error->get_error_code() );

		return rest_ensure_response( $response );
	}

	/**
	 * Verify the remote management feature is enable or not.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	private function is_enabled( string $key ): bool {
		$default = in_array( $key, array( 'inventory', 'update_core_plugin_theme', 'activate_deactivate' ) ) ? 'on' : 'off';
		$value   = InstaWP_Setting::get_option( 'instawp_rm_' . $key, $default );
		$value   = empty( $value ) ? $default : $value;

		return 'on' === $value;
	}

	/**
	 * Filter params.
	 *
	 * @param object $request
	 *
	 * @return array
	 */
	private function filter_params( $request ) {
		$params = $request->get_params() ?? array();
		if ( array_key_exists( 'rest_route', $params ) ) {
			unset( $params['rest_route'] );
		}

		return $params;
	}

	/**
	 * Prepare remote management settings list.
	 *
	 * @param string $name
	 *
	 * @return array|string
	 */
	private function get_management_options( $name = '' ) {
		$options = array(
			'heartbeat'            => __( 'Heartbeat', 'instawp-connect' ),
			'file_manager'         => __( 'File Manager', 'instawp-connect' ),
			'database_manager'     => __( 'Database Manager', 'instawp-connect' ),
			'install_plugin_theme' => __( 'Install Plugin / Themes', 'instawp-connect' ),
			'config_management'    => __( 'Config Management', 'instawp-connect' ),
			'inventory'            => __( 'Site Inventory', 'instawp-connect' ),
			'debug_log'            => __( 'Debug Log', 'instawp-connect' ),
		);
		if ( ! empty( $name ) ) {
			return isset( $options[ $name ] ) ? $options[ $name ] : '';
		}

		return $options;
	}
}

global $InstaWP_Backup_Api;
$InstaWP_Backup_Api = new InstaWP_Backup_Api();