<?php
/**
 * InstaWP Migration Process
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'INSTAWP_Migration' ) ) {
	class INSTAWP_Migration {

		protected static $_instance = null;

		/**
		 * INSTAWP_Migration Constructor
		 */
		public function __construct() {

			if ( isset( $_GET['page'] ) && in_array( sanitize_text_field( $_GET['page'] ), [ 'instawp', 'instawp-template-migrate' ] ) ) {
				add_filter( 'admin_footer_text', '__return_false' );
				add_filter( 'update_footer', '__return_false', 99 );
			}

			add_action( 'wp_ajax_instawp_update_settings', array( $this, 'update_settings' ) );
			add_action( 'wp_ajax_instawp_connect_api_url', array( $this, 'connect_api_url' ) );
			add_action( 'wp_ajax_instawp_reset_plugin', array( $this, 'reset_plugin' ) );
			add_action( 'wp_ajax_instawp_check_limit', array( $this, 'check_limit' ) );
			add_action( 'wp_ajax_instawp_check_domain_availability', array( $this, 'check_domain_availability' ) );
			add_action( 'wp_ajax_instawp_check_domain_connect_status', array( $this, 'check_domain_connect_status' ) );

			add_action( 'INSTAWP/Actions/restore_completed', array( $this, 'restore_completed' ), 10, 2 );
		}


		function check_domain_connect_status() {

			$destination_domain = isset( $_POST['destination_domain'] ) ? sanitize_url( $_POST['destination_domain'] ) : '';

			if ( empty( $destination_domain ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Empty destination domain is not allowed.', 'instawp-connect' ) ) );
			}

			$response      = InstaWP_Curl::do_curl( 'check-is-config', array( 'url' => $destination_domain ) );
			$response_data = InstaWP_Setting::get_args_option( 'data', $response );
			$is_config     = (bool) InstaWP_Setting::get_args_option( 'is_config', $response_data );

			if ( ! $is_config ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Destination domain is not configured.', 'instawp-connect' ) ) );
			}

			wp_send_json_success( array( 'message' => esc_html__( 'Destination domain is configured.', 'instawp-connect' ) ) );
		}


		function check_domain_availability() {

			$domain_name  = isset( $_POST['domain_name'] ) ? sanitize_text_field( $_POST['domain_name'] ) : '';
			$alert_icon   = instawp()::get_asset_url( 'migrate/assets/images/alert-icon.svg' );
			$success_icon = instawp()::get_asset_url( 'migrate/assets/images/check-icon.png' );

			if ( empty( $domain_name ) ) {
				wp_send_json_error( array( 'icon_url' => $alert_icon, 'message' => esc_html__( 'Empty domain name is not allowed.', 'instawp-connect' ) ) );
			}

			$search_response = instawp_domain_search( $domain_name );
			$status          = InstaWP_Setting::get_args_option( 'status', $search_response );

			if ( 'active' === $status ) {
				wp_send_json_error( array( 'icon_url' => $alert_icon, 'message' => esc_html__( 'This domain name is not available.', 'instawp-connect' ) ) );
			}

			wp_send_json_success( array( 'icon_url' => $success_icon, 'message' => esc_html__( 'This domain is available.', 'instawp-connect' ) ) );
		}


		function check_limit() {

			$_settings = isset( $_POST['settings'] ) ? $_POST['settings'] : [];
			$settings  = [];

			if ( ! empty( $_settings ) ) {
				parse_str( $_settings, $settings );
			}

			$api_response = instawp()->instawp_check_usage_on_cloud( $settings );
			$can_proceed  = (bool) InstaWP_Setting::get_args_option( 'can_proceed', $api_response, false );

			if ( $can_proceed ) {
				$api_response['nonce'] = wp_create_nonce( 'instawp_migration_nonce' );

				update_option( 'instawp_migration_running', time() );
				delete_transient( 'instawp_migration_completed' );

				wp_send_json_success( $api_response );
			}

			$api_response['button_text'] = esc_html__( 'Increase Limit', 'instawp-connect' );
			$api_response['button_url']  = InstaWP_Setting::get_pro_subscription_url( 'subscriptions?source=connect_limit_warning' );

			wp_send_json_error( $api_response );
		}


		function reset_plugin() {

			$reset_type = isset( $_POST['reset_type'] ) ? sanitize_text_field( $_POST['reset_type'] ) : '';
			$reset_type = empty( $reset_type ) ? InstaWP_Setting::get_option( 'instawp_reset_type', 'soft' ) : $reset_type;

			if ( ! in_array( $reset_type, array( 'soft', 'hard' ) ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid reset type.' ) ) );
			}

			if ( ! instawp_reset_running_migration( $reset_type ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Plugin reset unsuccessful.' ) ) );
			}

			wp_send_json_success( array( 'message' => esc_html__( 'Plugin reset successfully.' ) ) );
		}


		function connect_api_url() {

			$return_url      = urlencode( admin_url( 'tools.php?page=instawp' ) );
			$connect_api_url = InstaWP_Setting::get_api_domain() . '/authorize?source=InstaWP Connect&return_url=' . $return_url;

			wp_send_json_success( array( 'connect_url' => $connect_api_url ) );
		}


		function update_settings() {

			$_form_data = isset( $_REQUEST['form_data'] ) ? wp_kses_post( $_REQUEST['form_data'] ) : '';
			$_form_data = str_replace( 'amp;', '', $_form_data );

			parse_str( $_form_data, $form_data );

			$settings_nonce = InstaWP_Setting::get_args_option( 'instawp_settings_nonce', $form_data );

			if ( ! wp_verify_nonce( $settings_nonce, 'instawp_settings_nonce_action' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed. Please try again reloading the page.' ) ) );
			}

			foreach ( InstaWP_Setting::get_migrate_settings_fields() as $field_id ) {

				if ( ! isset( $form_data[ $field_id ] ) ) {
					continue;
				}

				$field_value = InstaWP_Setting::get_args_option( $field_id, $form_data );

				if ( 'instawp_api_key' == $field_id && ! empty( $field_value ) && $field_value != InstaWP_Setting::get_option( 'instawp_api_key' ) ) {

					$api_key_check_response = InstaWP_Backup_Api::config_check_key( $field_value );

					if ( isset( $api_key_check_response['error'] ) && $api_key_check_response['error'] == 1 ) {
						wp_send_json_error( array( 'message' => InstaWP_Setting::get_args_option( 'message', $api_key_check_response, esc_html__( 'Error. Invalid API Key', 'instawp-connect' ) ) ) );
					}

					continue;
				}

				InstaWP_Setting::update_option( $field_id, $field_value );
			}

			wp_send_json_success( array( 'message' => esc_html__( 'Success. Settings updated.' ) ) );

			die();
		}


		function enqueue_styles_scripts() {

		}


		/**
		 * @return INSTAWP_Migration
		 */
		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}
}

INSTAWP_Migration::instance();


