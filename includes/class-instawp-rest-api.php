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
			'callback'            => array( $this, 'config' ),
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

		register_rest_route( $this->namespace . '/' . $this->version_2, '/user', array(
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

		// Content
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/content', '/posts', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_posts_count' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/content', '/posts/(?P<post_type>[a-z_-]+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_posts' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/content', '/media', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_media' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/content', '/users', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_users' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/3rdparty', '/woocommerce/orders', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_orders' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/3rdparty', '/woocommerce/users', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_customers' ),
			'permission_callback' => '__return_true',
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

		register_rest_route( $this->namespace . '/' . $this->version_3, '/post-cleanup', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_post_migration_cleanup' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function handle_post_migration_cleanup( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$plugin_slug        = INSTAWP_PLUGIN_SLUG . '/' . INSTAWP_PLUGIN_SLUG . '.php';
		$response           = array(
			'success'       => true,
			'sso_login_url' => site_url(),
			'message'       => esc_html__( 'Post migration cleanup completed.', 'instawp-connect' ),
		);
		$migrate_group_uuid = $request->get_param( 'migrate_group_uuid' );
		$migration_status   = $request->get_param( 'status' );
		$migration_details  = Option::get_option( 'instawp_migration_details' );

		$migration_details['migrate_group_uuid'] = $migrate_group_uuid;
		$migration_details['status']             = $migration_status;

		Option::update_option( 'instawp_last_migration_details', $migration_details );

		// Install the plugins if there is any in the request
		$post_installs = $request->get_param( 'post_installs' );

		if ( ! empty( $post_installs ) && is_array( $post_installs ) ) {
			$installer = new Helpers\Installer( $post_installs );

			$response['post_installs'] = $installer->start();
		}

		// SSO Url for the Bluehost
		if ( class_exists( 'NewfoldLabs\WP\Module\Migration\Services\MigrationSSO' ) ) {
			$login_url_response = NewfoldLabs\WP\Module\Migration\Services\MigrationSSO::get_magic_login_url();

			if ( $login_url_response instanceof WP_REST_Response && $login_url_response->get_status() === 200 ) {
				$response['sso_login_url'] = $login_url_response->get_data();
			} else {
				error_log( 'sso_url_response: ' . wp_json_encode( $login_url_response ) );
			}
		} else {
			error_log( esc_html__( 'sso_url_class_not_found: This class NewfoldLabs\WP\Module\Migration\Services\MigrationSSO not found.', 'instawp-connect' ) );
		}

		// reset everything and remove connection
		instawp_reset_running_migration( 'hard', true );

		// deactivate plugin
		deactivate_plugins( $plugin_slug );

		$is_deleted = delete_plugins( array( $plugin_slug ) );

		if ( is_wp_error( $is_deleted ) ) {
			return $this->throw_error( $is_deleted );
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_posts_count( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response   = array();
		$post_types = get_post_types( array(
			'public' => true,
		) );
		foreach ( $post_types as $post_type ) {
			$response[ $post_type ] = array_sum( ( array ) wp_count_posts( $post_type ) );
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_posts( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$post_type = $request->get_param( 'post_type' );
		$exists    = post_type_exists( $post_type );
		if ( ! $exists ) {
			return $this->send_response( array(
				'success' => false,
				'message' => 'Post type not exists!',
			) );
		}

		$response       = array();
		$posts_per_page = $request->get_param( 'count' );
		$offset         = $request->get_param( 'offset' );
		$posts          = get_posts( array(
			'posts_per_page' => ! empty( $posts_per_page ) ? $posts_per_page : 50,
			'offset'         => ! empty( $offset ) ? $offset : 0,
			'post_type'      => $post_type,
			'post_status'    => 'any',
		) );

		foreach ( $posts as $post ) {
			$response[] = array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'excerpt'        => $post->post_excerpt,
				'slug'           => $post->post_name,
				'status'         => $post->post_status,
				'parent_id'      => $post->post_parent,
				'author'         => get_the_author_meta( 'display_name', $post->post_author ),
				'created_at'     => $post->post_date_gmt,
				'updated_at'     => $post->post_modified_gmt,
				'comment_status' => $post->comment_status,
				'comment_count'  => $post->comment_count,
				'preview_url'    => get_permalink( $post->ID ),
				'edit_url'       => apply_filters( 'get_edit_post_link', admin_url( 'post.php?post=' . $post->ID . '&action=edit' ), $post->ID, '' ),
			);
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_media( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response       = array();
		$sizes          = get_intermediate_image_sizes();
		$posts_per_page = $request->get_param( 'count' );
		$offset         = $request->get_param( 'offset' );
		$attachments    = get_posts( array(
			'posts_per_page' => ! empty( $posts_per_page ) ? $posts_per_page : 50,
			'offset'         => ! empty( $offset ) ? $offset : 0,
			'post_type'      => 'attachment',
			'post_status'    => 'any',
		) );

		foreach ( $attachments as $attachment ) {
			$image_sizes = array();
			foreach ( $sizes as $size ) {
				$image_sizes[ $size ] = wp_get_attachment_image_url( $attachment->ID, $size );
			}
			$response[] = array(
				'id'          => $attachment->ID,
				'title'       => $attachment->post_title,
				'slug'        => $attachment->post_name,
				'caption'     => apply_filters( 'wp_get_attachment_caption', $attachment->post_excerpt, $attachment->ID ),
				'description' => $attachment->post_content,
				'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
				'images'      => array_filter( $image_sizes ),
				'status'      => $attachment->post_status,
				'parent_id'   => $attachment->post_parent,
				'author'      => get_the_author_meta( 'display_name', $attachment->post_author ),
				'created_at'  => $attachment->post_date_gmt,
				'updated_at'  => $attachment->post_modified_gmt,
				'preview_url' => wp_get_attachment_url( $attachment->ID ),
				'edit_url'    => apply_filters( 'get_edit_post_link', admin_url( 'post.php?post=' . $attachment->ID . '&action=edit' ), $attachment->ID, '' ),
			);
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_users( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response = array();
		$users    = get_users();
		foreach ( $users as $user ) {
			$response[] = array(
				'id'             => $user->ID,
				'roles'          => $user->roles,
				'username'       => $user->data->user_login,
				'email'          => $user->data->user_email,
				'display_name'   => $user->data->display_name,
				'gravatar_image' => get_avatar_url( $user->ID ),
			);
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_orders( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response = array();
		$limit    = $request->get_param( 'limit' );
		$offset   = $request->get_param( 'offset' );
		$orders   = wc_get_orders( array(
			'limit'  => ! empty( $limit ) ? $limit : 10,
			'offset' => ! empty( $offset ) ? $offset : 0,
		) );

		foreach ( $orders as $order ) {
			$order_data = $order->get_data();
			$data       = $order_data;

			$data['fee_lines'] = array();
			foreach ( $order_data['fee_lines'] as $fee ) {
				$data['fee_lines'][] = $fee->get_data();
			}

			$data['shipping_lines'] = array();
			foreach ( $order_data['shipping_lines'] as $shipping ) {
				$data['shipping_lines'][] = $shipping->get_data();
			}

			$data['tax_lines'] = array();
			foreach ( $order_data['tax_lines'] as $tax ) {
				$data['tax_lines'][] = $tax->get_data();
			}

			$data['line_items'] = array();
			foreach ( $order_data['line_items'] as $product ) {
				$data['line_items'][] = $product->get_data();
			}

			$response[] = $data;
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for pull api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_customers( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response  = array();
		$customers = get_users( array( 'role' => array( 'customer' ) ) );
		foreach ( $customers as $customer ) {
			$customer   = new \WC_Customer( $customer->ID );
			$response[] = $this->get_formatted_item_data( $customer );
		}

		return $this->send_response( $response );
	}

	protected function get_formatted_item_data( $object_data ) {
		$formatted_data                 = $this->get_formatted_item_data_core( $object_data );
		$formatted_data['orders_count'] = $object_data->get_order_count();
		$formatted_data['total_spent']  = $object_data->get_total_spent();

		return $formatted_data;
	}

	protected function get_formatted_item_data_core( $object_data ) {
		$data        = $object_data->get_data();
		$format_date = array( 'date_created', 'date_modified' );

		// Format date values.
		foreach ( $format_date as $key ) {
			// Date created is stored UTC, date modified is stored WP local time.
			$datetime              = 'date_created' === $key && is_subclass_of( $data[ $key ], 'DateTime' ) ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $data[ $key ]->getTimestamp() ) ) : $data[ $key ];
			$data[ $key ]          = wc_rest_prepare_date_response( $datetime, false );
			$data[ $key . '_gmt' ] = wc_rest_prepare_date_response( $datetime );
		}

		$formatted_data = array(
			'id'                 => $object_data->get_id(),
			'date_created'       => $data['date_created'],
			'date_created_gmt'   => $data['date_created_gmt'],
			'date_modified'      => $data['date_modified'],
			'date_modified_gmt'  => $data['date_modified_gmt'],
			'email'              => $data['email'],
			'first_name'         => $data['first_name'],
			'last_name'          => $data['last_name'],
			'role'               => $data['role'],
			'username'           => $data['username'],
			'billing'            => $data['billing'],
			'shipping'           => $data['shipping'],
			'is_paying_customer' => $data['is_paying_customer'],
			'avatar_url'         => $object_data->get_avatar_url(),
		);

		if ( wc_current_user_has_role( 'administrator' ) ) {
			$formatted_data['meta_data'] = $data['meta_data'];
		}

		return $formatted_data;
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
		$pre_check_response = InstaWP_Tools::get_pull_pre_check_response( $migrate_key, $migrate_settings );

		if ( is_wp_error( $pre_check_response ) ) {
			return $this->throw_error( $pre_check_response );
		}

		global $wp_version;

		$pre_check_response['source_domain']       = site_url();
		$pre_check_response['php_version']         = PHP_VERSION;
		$pre_check_response['wp_version']          = $wp_version;
		$pre_check_response['plugin_version']      = INSTAWP_PLUGIN_VERSION;
		$pre_check_response['file_size']           = InstaWP_Tools::get_total_sizes( 'files', $migrate_settings );
		$pre_check_response['db_size']             = InstaWP_Tools::get_total_sizes( 'db' );
		$pre_check_response['is_website_on_local'] = instawp_is_website_on_local();
		$pre_check_response['active_plugins']      = Option::get_option( 'active_plugins' );
		$pre_check_response['wp_admin_email']      = get_bloginfo( 'admin_email' );

		Option::update_option( 'instawp_migration_details', array(
			'migrate_key' => $migrate_key,
			//'dest_url'    => Helper::get_args_option( 'serve_url', $pre_check_response ),
			'started_at'  => current_time( 'mysql', 1 ),
			'status'      => 'initiated',
			'mode'        => 'pull',
		) );

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
		InstaWP_Tools::create_instawpbackups_dir();

		// Clean InstaWP backup directory
		InstaWP_Tools::clean_instawpbackups_dir();

		$migrate_key      = InstaWP_Tools::get_random_string( 40 );
		$migrate_settings = InstaWP_Tools::get_migrate_settings();
		$api_signature    = hash( 'sha512', $migrate_key . wp_generate_uuid4() );
		$dest_file_url    = InstaWP_Tools::generate_destination_file( $migrate_key, $api_signature );

		// Check accessibility of serve file
		if ( ! InstaWP_Tools::is_migrate_file_accessible( $dest_file_url ) ) {
			return $this->throw_error( new WP_Error( 403, esc_html__( 'Could not create destination file.', 'instawp-connect' ) ) );
		}

		Option::update_option( 'instawp_migration_details', array(
			'migrate_key' => $migrate_key,
			'dest_url'    => $dest_file_url,
			'started_at'  => current_time( 'mysql', 1 ),
			'status'      => 'initiated',
			'mode'        => 'push',
		) );

		return $this->send_response(
			array(
				'php_version'      => PHP_VERSION,
				'wp_version'       => $wp_version,
				'plugin_version'   => INSTAWP_PLUGIN_VERSION,
				'file_size'        => InstaWP_Tools::get_total_sizes( 'files', $migrate_settings ),
				'db_size'          => InstaWP_Tools::get_total_sizes( 'db' ),
				'active_plugins'   => Option::get_option( 'active_plugins', array() ),
				'migrate_settings' => $migrate_settings,
				'migrate_key'      => $migrate_key,
				'dest_url'         => $dest_file_url,
				'api_signature'    => $api_signature,
			)
		);
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


	function override_plugin_zip_while_doing_config( $plugin_zip_url ) {

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

	public function config( $request ) {

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
			$type  = isset( $param['type'] ) ? $param['type'] : 'plugin';
			$asset = isset( $param['asset'] ) ? $param['asset'] : '';
			$state = isset( $param['state'] ) ? $param['state'] : 'disable';

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
					'message' => __( 'Invalid data. Unknown type.', 'instawp-connect' ),
				);
				continue;
			}

			if ( ! array_key_exists( $asset, $all_items ) ) {
				$response[ $key ] = array(
					'success' => false,
					'message' => __( 'Invalid data. The item does not exist.', 'instawp-connect' ),
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
				'success' => true,
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

		$wp_config_params = $request->get_param( 'wp-config' );
		$params           = ! is_array( $wp_config_params ) ? array() : $wp_config_params;
		$wp_config        = new Helpers\WPConfig( $params );
		$response         = $wp_config->fetch();

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
		$wp_config        = new Helpers\WPConfig( $params );
		$response         = $wp_config->update();

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
		$wp_config        = new Helpers\WPConfig( $params );
		$response         = $wp_config->delete();

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

		if ( empty( $params['user_login'] ) ) {
			return $this->send_response( array(
				'success' => false,
				'message' => 'Username can\'t be empty!',
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

		$user = get_user_by( 'id', $user_id );

		return $this->send_response( array(
			'success'  => true,
			'userdata' => $user,
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

		$file_manager = new Helpers\FileManager();
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
	 * Check if Current REST route contains instawp or not.
	 *
	 * @return bool
	 */
	private function is_instawp_route() {
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
	private function get_current_route() {
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
	private function is_enabled( $key ) {
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
	private function filter_params( WP_REST_Request $request ) {
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
	private function get_management_options( $name = '' ) {
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