<?php

use InstaWP\Connect\Helpers;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || die;

class InstaWP_Rest_Api {

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
		add_action( 'init', array( $this, 'perform_actions' ), 0 );
	}

	public function add_api_routes() {
		register_rest_route( $this->namespace . '/' . $this->version, '/config', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'set_config' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version, '/mark-staging', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'mark_staging' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/disconnect', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'disconnect' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/auto-login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'auto_login' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/heartbeat', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_heartbeat' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/config-manager', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'config_manager' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_3, '/site-usage', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'site_usage' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Handle website config set.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_config( $request ) {

		$parameters         = $this->filter_params( $request );
		$connect_id         = instawp_get_connect_id();
		$results            = array(
			'status'     => false,
			'connect_id' => 0,
			'message'    => '',
		);
		$override_from_main = isset( $parameters['override_from_main'] ) ? (bool) $parameters['override_from_main'] : false;

		if ( $override_from_main === true ) {

			$plugin_zip_url = esc_url_raw( 'https://github.com/InstaWP/instawp-connect/archive/refs/heads/main.zip' );
			$this->override_plugin_zip_while_doing_config( $plugin_zip_url );

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

		$config_response = InstaWP_Setting::instawp_generate_api_key( $parameters['api_key'] );
		if ( ! $config_response ) {
			$results['message'] = __( 'Key is not valid', 'instawp-connect' );

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
				Option::update_option( $option_key, $option_value );
			}
		}

		// if any user is passed, then create it
		if ( isset( $parameters['wp'] ) && isset( $parameters['wp']['users'] ) ) {
			InstaWP_Tools::create_user( $parameters['wp']['users'] );
		}

		return $this->send_response( $results );
	}

	/**
	 * Handle events receiver api
	 *
	 * @param WP_REST_Request $req
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function mark_staging( WP_REST_Request $req ) {
		$response = $this->validate_api_request( $req );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$body    = $req->get_body();
		$request = json_decode( $body );

		if ( ! isset( $request->parent_connect_id ) ) {
			return new WP_Error( 400, esc_html__( 'Invalid connect ID', 'instawp-connect' ) );
		}

		delete_option( 'instawp_sync_parent_connect_data' );
		Option::update_option( 'instawp_sync_connect_id', intval( $request->parent_connect_id ) );
		Option::update_option( 'instawp_is_staging', true );
		instawp_get_source_site_detail();

		return $this->send_response( array(
			'status'  => true,
			'message' => __( 'Site has been marked as staging', 'instawp-connect' ),
		) );
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
	 * Auto login url generate
	 * */
	public function auto_login( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$param_user     = $request->get_param( 's' );
		$login_userinfo = instawp_get_user_to_login( base64_decode( $param_user ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( is_wp_error( $login_userinfo ) ) {
			return $this->throw_error( $login_userinfo );
		}

		$username_to_login = Helper::get_args_option( 'username', $login_userinfo );
		$response_message  = Helper::get_args_option( 'message', $login_userinfo );
		$uuid_code         = wp_generate_uuid4();
		$login_code        = str_shuffle( $uuid_code . $uuid_code );
		$args              = array(
			'r' => true,
			'c' => $login_code,
			's' => base64_encode( $username_to_login ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		);
		$auto_login_url    = add_query_arg( $args, site_url() );

		Option::update_option( 'instawp_login_code', array(
			'code'       => $login_code,
			'updated_at' => time(),
		) );

		return $this->send_response(
			array(
				'error'     => false,
				'message'   => $response_message,
				'login_url' => $auto_login_url,
			)
		);
	}

	/**
	 * Handle response for heartbeat endpoint
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function handle_heartbeat( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response = InstaWP_Heartbeat::prepare_data();

		return $this->send_response( $response );
	}

	/**
	 * Handle wp-config.php file's constant modification.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function config_manager( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$wp_config_params = $request->get_param( 'wp-config' );
		$params           = ! is_array( $wp_config_params ) ? array() : $wp_config_params;
		$wp_config        = new Helpers\WPConfig( $params );
		$response         = $wp_config->update();

		return $this->send_response( $response );
	}

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
	 * Checks for a current route being requested, and processes the allowlist
	 *
	 * @param $access
	 *
	 * @return WP_Error|null|boolean
	 */
	public function rest_access( $access ) {
		return $this->is_instawp_route() ? true : $access;
	}

	/**
	 * Check if Current REST route contains instawp or not
	 */
	public function perform_actions() {
		if ( $this->is_instawp_route() ) {
			remove_action( 'init', 'csmm_plugin_init' ); // minimal-coming-soon-maintenance-mode support
		}
	}

	/**
	 * Valid api request and if invalid api key then stop executing.
	 *
	 * @param WP_REST_Request $request
	 * @param string $option
	 *
	 * @return WP_Error|bool
	 */
	public function validate_api_request( WP_REST_Request $request, $option = '' ) {

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
		instawp_get_fs()->mkdir( $dst );

		while ( $file = readdir( $dir ) ) {
			if ( ( $file !== '.' ) && ( $file !== '..' ) ) {
				if ( is_dir( $src . '/' . $file ) ) {
					$this->move_files_folders( $src . '/' . $file, $dst . '/' . $file );
				} else {
					instawp_get_fs()->copy( $src . '/' . $file, $dst . '/' . $file );
					wp_delete_file( $src . '/' . $file );
				}
			}
		}

		closedir( $dir );
		instawp_get_fs()->rmdir( $src );
	}

	public function override_plugin_zip_while_doing_config( $plugin_zip_url ) {

		if ( empty( $plugin_zip_url ) ) {
			return;
		}

		$plugin_zip   = INSTAWP_PLUGIN_SLUG . '.zip';
		$plugins_path = WP_CONTENT_DIR . '/plugins/';

		// Download the file from remote location
		file_put_contents( $plugin_zip, fopen( $plugin_zip_url, 'r' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// Setting permission
		chmod( $plugin_zip, 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

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
			$installed_plugin_slug = isset( $installed_plugin_info[0] ) ? $installed_plugin_info[0] : '';

			if ( ! empty( $installed_plugin_slug ) ) {

				$source      = $plugins_path . $installed_plugin_slug;
				$destination = $plugins_path . INSTAWP_PLUGIN_SLUG;

				$this->move_files_folders( $source, $destination );

				if ( file_exists( $destination ) ) {
					instawp_get_fs()->rmdir( $destination );
				}
			}
		}

		wp_delete_file( $plugin_zip );
	}

	/**
	 * Check if Current REST route contains instawp or not.
	 *
	 * @return bool
	 */
	protected function is_instawp_route() {
		$current_route = $this->get_current_route();

		if ( $current_route && strpos( $current_route, 'instawp-connect' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Current REST route getter.
	 *
	 * @return string
	 */
	protected function get_current_route() {
		$rest_route = get_query_var( 'rest_route', '/' );

		if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$rest_route = $GLOBALS['wp']->query_vars['rest_route'];
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$rest_route = $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		return ( empty( $rest_route ) || '/' === $rest_route ) ? $rest_route : untrailingslashit( $rest_route );
	}

	/**
	 * Returns WP_REST_Response.
	 *
	 * @param array $results
	 *
	 * @return WP_REST_Response|WP_Error|WP_HTTP_Response
	 */
	protected function send_response( array $results ) {
		$response = new WP_REST_Response( $results );
		if ( isset( $results['success'] ) && $results['success'] === false ) {
			$response->set_status( 400 );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Returns error data with WP_REST_Response.
	 *
	 * @param WP_Error $error
	 *
	 * @return WP_REST_Response|WP_Error|WP_HTTP_Response
	 */
	public function throw_error( WP_Error $error ) {
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
	protected function is_enabled( $key ) {
		$default = in_array( $key, array( 'inventory', 'update_core_plugin_theme', 'activate_deactivate' ) ) ? 'on' : 'off';
		$value   = Option::get_option( 'instawp_rm_' . $key, $default );
		$value   = empty( $value ) ? $default : $value;

		return 'on' === $value;
	}

	/**
	 * Filter params.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return array
	 */
	protected function filter_params( WP_REST_Request $request ) {
		$params = $request->get_params();
		$params = ! is_array( $params ) ? array() : $params;
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
	protected function get_management_options( $name = '' ) {
		$options = array(
			'heartbeat'                => __( 'Heartbeat', 'instawp-connect' ),
			'file_manager'             => __( 'File Manager', 'instawp-connect' ),
			'database_manager'         => __( 'Database Manager', 'instawp-connect' ),
			'install_plugin_theme'     => __( 'Install Plugin / Themes', 'instawp-connect' ),
			'update_core_plugin_theme' => __( 'Update Core / Plugin / Themes', 'instawp-connect' ),
			'activate_deactivate'      => __( 'Activate / Deactivate', 'instawp-connect' ),
			'config_management'        => __( 'Config Management', 'instawp-connect' ),
			'inventory'                => __( 'Site Inventory', 'instawp-connect' ),
			'debug_log'                => __( 'Debug Log', 'instawp-connect' ),
		);

		if ( ! empty( $name ) ) {
			return isset( $options[ $name ] ) ? $options[ $name ] : '';
		}

		return $options;
	}
}

new InstaWP_Rest_Api();