<?php

use InstaWP\Connect\Helpers;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || die;

class InstaWP_Rest_Api_Manage extends InstaWP_Rest_Api {

	public function __construct() {
		parent::__construct();

		add_action( 'rest_api_init', array( $this, 'add_api_routes' ) );
	}

	public function add_api_routes() {
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

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/delete', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'perform_delete' ),
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
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_auto_update' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_auto_update' ),
				'permission_callback' => '__return_true',
			),
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

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/manage', '/user', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_user' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_user' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_user' ),
				'permission_callback' => '__return_true',
			),
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

		$cache_api = new Helpers\Cache();
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

		$inventory = new Helpers\Inventory();
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

		$installer = new Helpers\Installer( $params );
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

		$installer = new Helpers\Updater( $params );
		$response  = $installer->update();

		return $this->send_response( $response );
	}

	/**
	 * Handle response for deletion of plugin and theme update.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function perform_delete( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request, 'install_plugin_theme' );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$params = $this->filter_params( $request );

		$uninstaller = new Helpers\Uninstaller( $params );
		$response    = $uninstaller->uninstall();

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

		$params = $this->filter_params( $request );

		$activator = new Helpers\Activator( $params );
		$response  = $activator->activate();

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

		$deactivator = new Helpers\Deactivator( $params );
		$response    = $deactivator->deactivate();

		return $this->send_response( $response );
	}

	/**
	 * Handle response to retrieve the defined constant values.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_auto_update( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		try {
			$wp_config = new Helpers\WPConfig( array(
				'AUTOMATIC_UPDATER_DISABLED',
				'WP_AUTO_UPDATE_CORE',
			), false, true );
			$response  = $wp_config->get();
		} catch ( \Exception $e ) {
			$response = array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for toggle plugin and theme auto update.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_auto_update( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response     = array();
		$wp_config    = $request->get_param( 'wp-config' );
		$plugin_theme = $request->get_param( 'plugin-theme' );

		if ( ! empty( $wp_config ) ) {
			try {
				$wp_config             = new Helpers\WPConfig( array(
					'AUTOMATIC_UPDATER_DISABLED' => ! empty( $wp_config['updater_disabled'] ) ? $wp_config['updater_disabled'] : false,
					'WP_AUTO_UPDATE_CORE'        => ! empty( $wp_config['auto_update_core'] ) ? $wp_config['auto_update_core'] : false,
				) );
				$response['wp-config'] = $wp_config->set();

				wp_cache_flush();
			} catch ( \Exception $e ) {
				$response['wp-config'] = array(
					'success' => false,
					'message' => $e->getMessage(),
				);
			}
		}

		if ( ! empty( $plugin_theme ) ) {
			foreach ( $plugin_theme as $key => $param ) {
				$type  = isset( $param['type'] ) ? $param['type'] : 'plugin';
				$asset = isset( $param['asset'] ) ? $param['asset'] : '';
				$state = isset( $param['state'] ) ? $param['state'] : false;

				if ( 'plugin' === $type ) {
					if ( ! function_exists( 'get_plugins' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}

					$option = 'auto_update_plugins';

					/** This filter is documented in wp-admin/includes/class-wp-plugins-list-table.php */
					$all_items = apply_filters( 'all_plugins', get_plugins() );
				} elseif ( 'theme' === $type ) {
					if ( ! function_exists( 'wp_get_themes' ) ) {
						require_once ABSPATH . 'wp-includes/theme.php';
					}

					$option    = 'auto_update_themes';
					$all_items = wp_get_themes();
				}

				if ( ! isset( $option ) || ! isset( $all_items ) ) {
					$response['plugin-theme'][ $key ] = array(
						'success' => false,
						'message' => __( 'Invalid data. Unknown type.', 'instawp-connect' ),
					);
					continue;
				}

				if ( ! array_key_exists( $asset, $all_items ) ) {
					$response['plugin-theme'][ $key ] = array(
						'success' => false,
						'message' => __( 'Invalid data. The item does not exist.', 'instawp-connect' ),
					);
					continue;
				}

				$auto_updates = ( array ) get_site_option( $option, array() );

				if ( false === $state ) {
					$auto_updates = array_diff( $auto_updates, array( $asset ) );
				} else {
					$auto_updates[] = $asset;
					$auto_updates   = array_unique( $auto_updates );
				}

				// Remove items that have been deleted since the site option was last updated.
				$auto_updates = array_intersect( $auto_updates, array_keys( $all_items ) );

				update_site_option( $option, $auto_updates );

				$response['plugin-theme'][ $key ] = array_merge( array(
					'success' => true,
				), $param );
			}
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

		try {
			$wp_config_params = $request->get_param( 'wp-config' );
			$params           = ! is_array( $wp_config_params ) ? array() : $wp_config_params;

			$wp_config = new Helpers\WPConfig( $params );
			$response  = $wp_config->get();
		} catch ( \Exception $e ) {
			$response = array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}

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

		$wp_config_params = $request->get_param( 'wp-config' );
		$params           = ! is_array( $wp_config_params ) ? array() : $wp_config_params;

		try {
			$wp_config = new Helpers\WPConfig( $params );
			$response  = $wp_config->set();

			wp_cache_flush();
		} catch ( \Exception $e ) {
			$response = array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}

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

		$wp_config_params = $request->get_param( 'wp-config' );
		$params           = ! is_array( $wp_config_params ) ? array() : $wp_config_params;

		try {
			$wp_config = new Helpers\WPConfig( $params );
			$response  = $wp_config->delete();

			wp_cache_flush();
		} catch ( \Exception $e ) {
			$response = array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}

		return $this->send_response( $response );
	}


	/**
	 * Handle response for create user
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function add_user( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$params = $this->filter_params( $request );

		if ( username_exists( $params['user_login'] ) ) {
			return $this->send_response( array(
				'success' => false,
				'message' => 'Username is already in use!',
			) );
		}

		if ( email_exists( $params['user_email'] ) ) {
			return $this->send_response( array(
				'success' => false,
				'message' => 'Email is already in use!',
			) );
		}

		if ( ! function_exists( 'wp_insert_user' ) ) {
			require_once ABSPATH . 'wp-includes/user.php';
		}

		$user_id = wp_insert_user( wp_parse_args( $params, array(
			'user_pass' => wp_generate_password(),
		) ) );

		if ( is_wp_error( $user_id ) ) {
			return $this->throw_error( $user_id );
		}

		$count_users = count_users();
		$total_users = $count_users['total_users'];

		return $this->send_response( array(
			'success' => true,
			'count'   => $total_users,
		) );
	}

	/**
	 * Handle response for update user
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function update_user( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		if ( ! function_exists( 'wp_update_user' ) ) {
			require_once ABSPATH . 'wp-includes/user.php';
		}

		$params  = $this->filter_params( $request );
		$user_id = wp_update_user( $params );

		if ( is_wp_error( $user_id ) ) {
			return $this->throw_error( $user_id );
		}

		$user = get_user_by( 'id', $user_id );

		return $this->send_response( array(
			'success'  => true,
			'userdata' => $user,
		) );
	}

	/**
	 * Handle response for delete user
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function delete_user( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$params = $this->filter_params( $request );
		$params = wp_parse_args( $params, array(
			'reassign' => null,
		) );
		$status = wp_delete_user( $params['user_id'], $params['reassign'] );

		return $this->send_response( array(
			'success'  => $status,
			'userdata' => $params,
		) );
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

		$database_manager = new Helpers\DatabaseManager();
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

		$debug_log = new Helpers\DebugLog();
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
			$value   = Option::get_option( 'instawp_rm_' . $option, $default );
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
			$results[ $key ]['success'] = false;

			if ( array_key_exists( $key, $options ) ) {
				$results[ $key ]['message'] = esc_html__( 'Success!', 'instawp-connect' );

				if ( 'off' === $value ) {
					$update                     = Option::update_option( 'instawp_rm_' . $key, $value );
					$results[ $key ]['success'] = $update;
					if ( ! $update ) {
						$results[ $key ]['message'] = esc_html__( 'Setting is already disabled.', 'instawp-connect' );
					}
				} else {
					$results[ $key ]['message'] = esc_html__( 'You can not enable this setting through API.', 'instawp-connect' );
					$default                    = 'heartbeat' === $key ? 'on' : 'off';
					$value                      = Option::get_option( 'instawp_rm_' . $key, $default );
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
}

new InstaWP_Rest_Api_Manage();