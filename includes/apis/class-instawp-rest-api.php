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
		add_filter( 'bb_exclude_endpoints_from_restriction', array( $this, 'endpoints_from_restriction_callback' ), 99, 2 );
		add_action( 'init', array( $this, 'perform_actions' ), 0 );
	}

	public function add_api_routes() {
		register_rest_route( $this->namespace . '/' . $this->version, '/config', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'set_config' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version, '/mark-parent', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'mark_parent' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version, '/mark-staging', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'mark_staging' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/refresh-staging-sites-list', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'refresh_staging_sites_list' ),
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

		register_rest_route( $this->namespace . '/' . $this->version_2, '/activity-logs', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'activity_logs' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/temporary-login', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'temporary_login' ),
				'args'                => array(
					'i' => array(
						'required'          => true,
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param );
						},
					),
					'e' => array(
						'required'          => true,
						'validate_callback' => function ( $param, $request, $key ) {
							return strtotime( $param ) !== false;
						},
					),
					'r' => array(
						'default'           => 1,
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param );
						},
					),
				),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_temporary_login' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2, '/heartbeat', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_heartbeat' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_3, '/site-usage', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'site_usage' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_3, '/create-update-task', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_update_task' ),
			'args'                => array(
				'items' => array(
					'required'          => true,
					'validate_callback' => function ( $param, $request, $key ) {
						return is_array( $param );
					},
				),
			),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Checks a plaintext application password against a hashed password.
	 *
	 * @since WordPress 6.8.0
	 *
	 * @param string $password Plaintext password.
	 * @param string $hash     Hash of the password to check against.
	 * @param int    $user_id User ID.
	 * @return bool Whether the password matches the hashed password.
	 */
	public function check_password( $password, $hash, $user_id ) {
		if ( ! str_starts_with( $hash, '$generic$' ) ) {
			/*
			 * If the hash doesn't start with `$generic$`, it is a hash created with `wp_hash_password()`.
             * This is the case for application passwords created before WordPress 6.8.0.
			 */
			return wp_check_password( $password, $hash, $user_id );
		} elseif ( function_exists( 'wp_verify_fast_hash' ) ) {
			return wp_verify_fast_hash( $password, $hash );
		}

		return false;
	}

	/**
	 * Handle website config set.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_config( WP_REST_Request $request ) {

		try {
			$parameters           = $this->filter_params( $request );
			$wp_username          = isset( $parameters['wp_username'] ) ? sanitize_text_field( $parameters['wp_username'] ) : '';
			$application_password = isset( $parameters['application_password'] ) ? sanitize_text_field( $parameters['application_password'] ) : '';
			$jwt                  = isset( $parameters['token'] ) ? sanitize_text_field( $parameters['token'] ) : '';
			$api_key              = isset( $parameters['api_key'] ) ? sanitize_text_field( $parameters['api_key'] ) : '';
			$api_domain           = isset( $parameters['api_domain'] ) ? sanitize_text_field( $parameters['api_domain'] ) : '';
			$plan_id              = isset( $parameters['advance_connect_plan_id'] ) ? intval( $parameters['advance_connect_plan_id'] ) : 0; // before ppu
			$plan_id              = isset( $parameters['plan_id'] ) ? intval( $parameters['plan_id'] ) : $plan_id;
			$managed              = isset( $parameters['managed'] ) ? boolval( $parameters['managed'] ) : true;

			if ( empty( $wp_username ) || empty( $application_password ) ) {
				return $this->send_response( array(
					'status'  => false,
					'success' => false,
					'message' => esc_html__( 'This request is not authorized.', 'instawp-connect' ),
				) );
			}

			if ( empty( $api_key ) ) {
				return $this->send_response( array(
					'status'  => false,
					'success' => false,
					'message' => esc_html__( 'API key and JWT token is required.', 'instawp-connect' ),
				) );
			}

			$application_password = base64_decode( $application_password ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$application_password = str_replace( ' ', '', $application_password );
			$wp_username          = base64_decode( $wp_username ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$wp_user              = get_user_by( 'login', $wp_username );
			$is_validated         = false;

			// Check if WP_User is valid or not
			if ( ! $wp_user instanceof WP_User ) {
				return $this->send_response( array(
					'status'  => false,
					'success' => false,
					'message' => esc_html__( 'No user found with the provided username.', 'instawp-connect' ),
				) );
			}

			// Check if the user is an administrator or not
			if ( ! user_can( $wp_user, 'manage_options' ) ) {
				return $this->send_response( array(
					'status'  => false,
					'success' => false,
					'message' => esc_html__( 'This user does not have capability to config the website.', 'instawp-connect' ),
				) );
			}

			$application_passwords = get_user_meta( $wp_user->ID, '_application_passwords', true );
			$application_passwords = ! is_array( $application_passwords ) ? array() : $application_passwords;

			foreach ( $application_passwords as $password_data ) {
				$password = isset( $password_data['password'] ) ? $password_data['password'] : '';

				if ( $this->check_password( $application_password, $password, $wp_user->ID ) ) {
					$is_validated = true;
					break;
				}
			}

			if ( ! $is_validated ) {
				return $this->send_response( array(
					'status'  => false,
					'success' => false,
					'message' => esc_html__( 'Application password does not match.', 'instawp-connect' ),
				) );
			}

			if ( isset( $parameters['override_from_main'] ) && $parameters['override_from_main'] ) {
				$plugin_zip_url = esc_url_raw( 'https://github.com/InstaWP/instawp-connect/archive/refs/heads/develop.zip' );
				$this->override_plugin_zip_while_doing_config( $plugin_zip_url );

				if ( ! function_exists( 'is_plugin_active' ) ) {
					require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				}

				$plugin_slug = INSTAWP_PLUGIN_SLUG . '/' . INSTAWP_PLUGIN_SLUG . '.php';
				if ( ! is_plugin_active( $plugin_slug ) ) {
					activate_plugin( $plugin_slug );
				}
			}

			$config = array(
				'managed'             => $managed,
				'plan_id'             => $plan_id,
				'e2e_mig_wo_connects' => ! empty( $parameters['e2e_mig_wo_connects'] ),
				'group_uuid'          => ( empty( $parameters['group_uuid'] ) || ! is_string( $parameters['group_uuid'] ) ) ? '' : sanitize_key( $parameters['group_uuid'] ),
			);

			// Is migration only config
			$migration_only_config = ( $config['e2e_mig_wo_connects'] && ! empty( $config['group_uuid'] ) );

			// If already connected then ignore
			if ( ! $migration_only_config && ! empty( instawp_get_connect_id() ) ) {
				return $this->send_response( array(
					'status'  => true,
					'success' => true,
					'message' => esc_html__( 'This website is already connected!', 'instawp-connect' ),
				) );
			}

			// If api_domain is passed then, set it
			if ( ! empty( $api_domain ) ) {
				$api_domain      = rtrim( $api_domain, '/' );
				$allowed_domains = array(
					'https://stage.instawp.io',
					'https://dev.instawp.io',
					'https://app.instawp.io',
				);

				$domain_to_set = defined( 'INSTAWP_API_DOMAIN' )
					? INSTAWP_API_DOMAIN
					: ( in_array( $api_domain, $allowed_domains ) ? $api_domain : '' );

				if ( empty( $domain_to_set ) ) {
					return $this->send_response( array(
						'status'  => false,
						'success' => false,
						'message' => esc_html__( 'Invalid API domain parameter passed.', 'instawp-connect' ),
					) );
				}

				Helper::set_api_domain( $domain_to_set );
			}

			// Generate API Key
			$response = Helper::generate_api_key( $api_key, $jwt, $config );
			if ( false === $response ) {
				return $this->send_response( array(
					'status'  => false,
					'success' => false,
					'message' => esc_html__( 'API Key is not valid.', 'instawp-connect' ),
				) );
			}

			if ( $migration_only_config ) {
				if ( Helper::has_mig_gid( $config['group_uuid'] ) ) {
					return $this->send_response( array(
						'status'  => true,
						'success' => true,
						'data'    => $response,
						'message' => esc_html__( 'Connected.', 'instawp-connect' ),
					) );
				} else {
					return $this->send_response( array(
						'status'     => false,
						'success'    => false,
						'group_uuid' => $config['group_uuid'],
						'message'    => esc_html__( 'Something went wrong during connecting to InstaWP.', 'instawp-connect' ),
					) );
				}
			}

			$connect_id = instawp_get_connect_id();
			if ( empty( $connect_id ) ) {
				return $this->send_response( array(
					'status'  => false,
					'success' => false,
					'message' => esc_html__( 'Something went wrong during connecting to InstaWP.', 'instawp-connect' ),
				) );
			}

			return $this->send_response( array(
				'status'     => true,
				'success'    => true,
				'connect_id' => $connect_id,
				'message'    => esc_html__( 'Connected.', 'instawp-connect' ),
			) );
		} catch ( \Throwable $th ) {
			return $this->send_response( array(
				'status'  => false,
				'success' => false,
				'message' => $th->getMessage(),
                'line'    => $th->getLine(),
                'file'    => $th->getFile(),
				'params'  => isset( $parameters ) ? $parameters : null,
			) );
		}
	}

	/**
	 * Mark website as parent.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function mark_parent( WP_REST_Request $request ) {
		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		if ( ! instawp()->is_staging ) {
			return $this->send_response( array(
				'status'  => false,
				'message' => __( 'This site is not currently marked as staging', 'instawp-connect' ),
			) );
		}

		$parent_connect_id = (int) $request->get_param( 'parent_connect_id' );
		if ( empty( $parent_connect_id ) ) {
			return $this->send_response( array(
				'status'  => false,
				'message' => esc_html__( 'Invalid parent connect ID', 'instawp-connect' ),
			) );
		}

		$sync_connect_id = (int) get_option( 'instawp_sync_connect_id', 0 );
		if ( $sync_connect_id !== $parent_connect_id ) {
			return $this->send_response( array(
				'status'  => false,
				'message' => esc_html__( 'Parent connect ID does not match', 'instawp-connect' ),
			) );
		}

		delete_option( 'instawp_is_staging' );
		delete_option( 'instawp_sync_connect_id' );
		delete_option( 'instawp_sync_parent_connect_data' );

		instawp_set_staging_sites_list( true );

		return $this->send_response( array(
			'status'  => true,
			'message' => __( 'Site has been marked as parent', 'instawp-connect' ),
		) );
	}

	/**
	 * Mark website as staging.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function mark_staging( WP_REST_Request $request ) {
		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$parent_connect_id = (int) $request->get_param( 'parent_connect_id' );
		if ( empty( $parent_connect_id ) ) {
			return $this->send_response( array(
				'status'  => false,
				'message' => esc_html__( 'Invalid connect ID', 'instawp-connect' ),
			) );
		}

		delete_option( 'instawp_sync_parent_connect_data' );
		Option::update_option( 'instawp_sync_connect_id', $parent_connect_id );
		Option::update_option( 'instawp_is_staging', true );
		instawp_get_source_site_detail();

		return $this->send_response( array(
			'status'  => true,
			'message' => __( 'Site has been marked as staging', 'instawp-connect' ),
		) );
	}

	/**
	 * Refresh staging site list.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function refresh_staging_sites_list( WP_REST_Request $request ) {
		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		instawp_set_staging_sites_list();

		return $this->send_response( array(
			'status'  => true,
			'message' => __( 'Staging Site List Refreshed.', 'instawp-connect' ),
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

		$param_user = $request->get_param( 's' );
		$redirect   = $request->get_param( 'redir' );

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
		if ( ! empty( $redirect ) ) {
			$args['redir'] = rawurlencode( $redirect );
		}
		$auto_login_url = add_query_arg( $args, Helper::wp_site_url( '', true ) );

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
	 * Toggle activity log
	 * */
	public function activity_logs( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$setting = (bool) $request->get_param( 'activity_logs' );

		if ( $setting ) {
			Option::update_option( 'instawp_activity_log', 'on' );
		} else {
			Option::update_option( 'instawp_activity_log', 'off' );
		}

		return $this->send_response( array(
			'success' => true,
			'message' => $setting ? __( 'Activity log is enabled.', 'instawp-connect' ) : __( 'Activity log is disabled.', 'instawp-connect' ),
		) );
	}

	/**
	 * Temporary Auto login url generate
	 * */
	public function temporary_login( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$param_user_id = $request->get_param( 'i' );
		$param_expiry  = $request->get_param( 'e' );
		$param_reuse   = $request->get_param( 'r' );

		$user_to_login = get_userdata( $param_user_id );
		if ( ! $user_to_login instanceof \WP_User ) {
			return $this->send_response( array(
				'success' => false,
				'message' => esc_html__( 'No login information found.', 'instawp-connect' ),
			) );
		}

		$token       = Helper::get_random_string( 120 );
		$expiry_time = get_date_from_gmt( $param_expiry, 'U' );

		$user_metas = array(
			'_instawp_temporary_login'            => 'yes',
			'_instawp_temporary_login_token'      => $token,
			'_instawp_temporary_login_expiration' => $expiry_time,
			'_instawp_temporary_login_attempt'    => $param_reuse,
		);

		foreach ( $user_metas as $meta_key => $meta_value ) {
			update_user_meta( $user_to_login->ID, $meta_key, $meta_value );
		}

		$login_url = add_query_arg( array(
			'iwp-temp-login' => $token,
		), Helper::wp_site_url( '', true ) );

		return $this->send_response( array(
			'success'   => true,
			'login_url' => $login_url,
		) );
	}

	/**
	 * Temporary Auto login url delete all
	 * */
	public function delete_temporary_login( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$param_user_id = $request->get_param( 'i' );
		if ( ! empty( $param_user_id ) ) {
			$user_ids = array( $param_user_id );
		} else {
			$user_ids = get_users( array(
				'meta_key'   => '_instawp_temporary_login',
				'meta_value' => 'yes',
				'fields'     => 'ID',
			) );
		}

		if ( ! empty( $user_ids ) ) {
			foreach ( $user_ids as $user_id ) {
				delete_user_meta( $user_id, '_instawp_temporary_login' );
				delete_user_meta( $user_id, '_instawp_temporary_login_token' );
				delete_user_meta( $user_id, '_instawp_temporary_login_expiration' );
				delete_user_meta( $user_id, '_instawp_temporary_login_attempt' );
			}
		}

		return $this->send_response( array(
			'success' => true,
			'message' => __( 'All Temporary logins are removed.', 'instawp-connect' ),
		) );
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
	 * Handle create update task api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function create_update_task( WP_REST_Request $request ) {
		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$parameters = $this->filter_params( $request );
		$items      = ! empty( $parameters['items'] ) ? array_map( 'sanitize_text_field', $parameters['items'] ) : array();

		if ( empty( $items ) ) {
			return $this->send_response( array(
				'success' => false,
				'message' => __( 'No items found', 'instawp-connect' ),
			) );
		}

		as_unschedule_all_actions( 'instawp_create_update_task', array( $items ), 'instawp-connect' );
		as_enqueue_async_action( 'instawp_create_update_task', array( $items ), 'instawp-connect' );

		return $this->send_response( array(
			'success' => true,
			'message' => __( 'Update task create successfully', 'instawp-connect' ),
		) );
	}

	/**
	 * Checks for a current route being requested, and processes the allowlist
	 *
	 * @param $access
	 *
	 * @return WP_Error|null|boolean
	 */
	public function rest_access( $access ) {
		$instawp_route = $this->is_instawp_route();

		if ( is_wp_error( $instawp_route ) ) {
			return $instawp_route;
		}

		return $instawp_route ? true : $access;
	}

	/**
	 * Bypass BuddyBoss endpoints blocking
	 */
	public function endpoints_from_restriction_callback( $default_exclude_endpoint, $current_endpoint ) {
		if ( strpos( $current_endpoint, 'instawp-connect' ) !== false ) {
			$default_exclude_endpoint[] = $current_endpoint;
		}

		return $default_exclude_endpoint;
	}

	/**
	 * Check if Current REST route contains instawp or not
	 */
	public function perform_actions() {
		if ( $this->is_instawp_route() === true ) {
			remove_action( 'init', 'csmm_plugin_init' ); // minimal-coming-soon-maintenance-mode support
		}
	}

	/**
	 * Get bearer token from header
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return string|WP_Error
	 */
	public function get_bearer_token( WP_REST_Request $request ) {
		// get authorization header value.
		$bearer_token = sanitize_text_field( $request->get_header( 'authorization' ) );
		if ( ! empty( $bearer_token ) ) {
			$bearer_token = str_ireplace( 'bearer', '', $bearer_token );
		} else {
			$bearer_token = sanitize_text_field( $request->get_header( 'x_iwp_auth' ) );
		}
		$bearer_token = trim( $bearer_token );

		// check if the bearer token is empty
		if ( empty( $bearer_token ) ) {
			return new WP_Error( 401, esc_html__( 'Empty bearer token.', 'instawp-connect' ) );
		}

		return $bearer_token;
	}

	/**
	 * Valid api request and if invalid api key then stop executing.
	 *
	 * @param WP_REST_Request $request
	 * @param string $option
	 * @param boolean $match_key
	 *
	 * @return WP_Error|bool
	 */
	public function validate_api_request( WP_REST_Request $request, $option = '', $match_key = false ) {

		// get bearer token.
		$bearer_token = $this->get_bearer_token( $request );

		// check if the bearer token is a wp error
		if ( is_wp_error( $bearer_token ) ) {
			return $bearer_token;
		}

		//in some cases Laravel stores api key with ID attached in front of it.
		//so we need to remove it and then hash the key
		$api_key          = Helper::get_api_key();
		$api_key_exploded = explode( '|', $api_key );

		if ( count( $api_key_exploded ) > 1 ) {
			$api_key = $api_key_exploded[1];
		}

		if ( empty( $api_key ) ) {
			return new WP_Error( 403, esc_html__( 'Empty api key.', 'instawp-connect' ) );
		}

		$is_matched = false;

		// match the api key with bearer token
		if ( $match_key && hash_equals( $api_key, $bearer_token ) ) {
			$is_matched = true;
		}

		if ( ! $is_matched ) {
			$api_key_hash = hash( 'sha256', $api_key );

			// match the api key hash with bearer token
			if ( ! hash_equals( $api_key_hash, $bearer_token ) ) {
				return new WP_Error( 403, esc_html__( 'Invalid bearer token.', 'instawp-connect' ) );
			}
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
		mkdir( $dst ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		while ( $file = readdir( $dir ) ) {
			if ( ( $file !== '.' ) && ( $file !== '..' ) ) {
				if ( is_dir( $src . '/' . $file ) ) {
					$this->move_files_folders( $src . '/' . $file, $dst . '/' . $file );
				} else {
					copy( $src . '/' . $file, $dst . '/' . $file );
					wp_delete_file( $src . '/' . $file );
				}
			}
		}

		closedir( $dir );
		rmdir( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	public function override_plugin_zip_while_doing_config( $plugin_zip_url ) {

		if ( empty( $plugin_zip_url ) ) {
			return;
		}

		$plugins_path = WP_CONTENT_DIR . '/plugins/';
		$plugin_zip   = $plugins_path . INSTAWP_PLUGIN_SLUG . '.zip';

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

		if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php' );
		}

		if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php' );
		}

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
		}

		if ( ! defined( 'FS_METHOD' ) ) {
			define( 'FS_METHOD', 'direct' );
		}

		wp_cache_flush();

		$plugin_upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
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
					rmdir( $destination ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				}
			}
		}

		wp_delete_file( $plugin_zip );
	}

	/**
	 * Check if Current REST route contains instawp or not.
	 *
	 * @return bool|WP_Error
	 */
	protected function is_instawp_route() {
		$current_route = $this->get_current_route();

		if ( $current_route && strpos( $current_route, 'instawp-connect' ) !== false ) {
			$endpoints = array(
				'create' => array( 'pull', 'push', 'post-cleanup' ),
				'manage' => array( 'manage', 'content', 'woocommerce' ),
				'sync'   => array( 'sync' ),
			);

			if ( defined( 'IWP_PLUGIN_DISABLE_FEATURES' ) && is_array( IWP_PLUGIN_DISABLE_FEATURES ) ) {
				foreach ( IWP_PLUGIN_DISABLE_FEATURES as $key ) {
					if ( ! isset( $endpoints[ $key ] ) ) {
						continue;
					}

					foreach ( $endpoints[ $key ] as $endpoint ) {
						if ( strpos( $current_route, $endpoint ) !== false ) {
							return new WP_Error( 400, esc_html__( 'Route not allowed', 'instawp-connect' ) );
						}
					}
				}
			}

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