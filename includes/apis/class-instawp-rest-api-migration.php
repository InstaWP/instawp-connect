<?php

use InstaWP\Connect\Helpers;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || die;

class InstaWP_Rest_Api_Migration extends InstaWP_Rest_Api {

	public function __construct() {
		parent::__construct();

		add_action( 'rest_api_init', array( $this, 'add_api_routes' ) );
	}

	public function add_api_routes() {
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

		if ( ! ini_get( 'allow_url_fopen' ) ) {
			return $this->throw_error( new WP_Error( 403, esc_html__( 'Migration could not initiate because the allow_url_fopen is set to false.', 'instawp-connect' ) ) );
		}

		global $wp_version;

		// Create InstaWP backup directory
		InstaWP_Tools::create_instawpbackups_dir();

		// Clean InstaWP backup directory
		InstaWP_Tools::clean_instawpbackups_dir();

		$migrate_key      = Helper::get_random_string( 40 );
		$migrate_settings = InstaWP_Tools::get_migrate_settings();
		$api_signature    = hash( 'sha512', $migrate_key . wp_generate_uuid4() );
		$dest_file_url    = InstaWP_Tools::generate_destination_file( $migrate_key, $api_signature, $migrate_settings );

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

		$migrate_settings['has_zip_archive'] = class_exists( 'ZipArchive' );
		$migrate_settings['has_phar_data']   = class_exists( 'PharData' );

		return $this->send_response(
			array(
				'php_version'      => PHP_VERSION,
				'wp_version'       => $wp_version,
				'plugin_version'   => INSTAWP_PLUGIN_VERSION,
				'active_plugins'   => Option::get_option( 'active_plugins' ),
				'migrate_settings' => $migrate_settings,
				'migrate_key'      => $migrate_key,
				'dest_url'         => $dest_file_url,
				'api_signature'    => $api_signature,
			)
		);
	}

	/**
	 * Handle response for post migration cleanup api
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function handle_post_migration_cleanup( WP_REST_Request $request ) {

		// Flushing db cache after migration
		wp_cache_flush();

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
			$installer         = new Helpers\Installer( $post_installs );
			$post_installs_res = $installer->start();

			foreach ( $post_installs_res as $install_res ) {
				if ( ! isset( $install_res['success'] ) || ! $install_res['success'] ) {
					$response['success']            = false;
					$response['post_install_error'] = esc_html__( 'Installation failed', 'instawp-connect' );
					break;
				}
			}

			$response['post_installs'] = $post_installs_res;
		}

		// SSO Url for the Bluehost
		if ( class_exists( 'NewfoldLabs\WP\Module\Migration\Services\MigrationSSO' ) ) {
			$login_url_response = NewfoldLabs\WP\Module\Migration\Services\MigrationSSO::get_magic_login_url();

			if ( $login_url_response instanceof WP_REST_Response && $login_url_response->get_status() === 200 ) {
				$response['sso_login_url'] = $login_url_response->get_data();
			} else {
				$response['success']       = false;
				$response['cleanup_error'] = esc_html__( 'Error getting SSO login url.', 'instawp-connect' );

				error_log( 'sso_url_response: ' . wp_json_encode( $login_url_response ) );
			}
		} else {
			error_log( esc_html__( 'sso_url_class_not_found: This class NewfoldLabs\WP\Module\Migration\Services\MigrationSSO not found.', 'instawp-connect' ) );
		}

		// disable bluehost coming soon notice
		update_option( 'nfd_coming_soon', false );

		// reset everything and remove connection
		instawp_reset_running_migration( 'hard', true );

		// deactivate instawp-connect plugin
		deactivate_plugins( $plugin_slug );

		$is_deleted = delete_plugins( array( $plugin_slug ) );

		if ( is_wp_error( $is_deleted ) ) {
			$response['success']       = false;
			$response['cleanup_error'] = $is_deleted->get_error_message();
		}

		if ( $response['success'] ) {
			$response['message'] = esc_html__( 'Post migration cleanup completed.', 'instawp-connect' );
		}

		return $this->send_response( $response );
	}
}

new InstaWP_Rest_Api_Migration();