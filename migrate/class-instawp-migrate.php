<?php
/**
 * InstaWP Migration Process
 */

use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'InstaWP_Migration' ) ) {
	class InstaWP_Migration {

		protected static $_instance = null;

		/**
		 * InstaWP_Migration Constructor
		 */
		public function __construct() {

			if ( isset( $_GET['page'] ) && in_array( sanitize_text_field( $_GET['page'] ), array( 'instawp', 'instawp-template-migrate' ) ) ) {
				add_filter( 'admin_footer_text', '__return_false' );
				add_filter( 'update_footer', '__return_false', 99 );
			}

			add_action( 'wp_ajax_instawp_update_settings', array( $this, 'update_settings' ) );
			add_action( 'wp_ajax_instawp_connect_api_url', array( $this, 'connect_api_url' ) );
			add_action( 'wp_ajax_instawp_reset_plugin', array( $this, 'reset_plugin' ) );
		}


		public function reset_plugin() {
			check_ajax_referer( 'instawp-connect', 'security' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'instawp-connect' ) ) );
			}

			$reset_type = isset( $_POST['reset_type'] ) ? sanitize_text_field( wp_unslash( $_POST['reset_type'] ) ) : '';
			$reset_type = empty( $reset_type ) ? Option::get_option( 'instawp_reset_type', 'soft' ) : $reset_type;

			if ( ! in_array( $reset_type, array( 'soft', 'hard' ) ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid reset type.', 'instawp-connect' ) ) );
			}

			if ( ! instawp_reset_running_migration( $reset_type ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Plugin reset unsuccessful.', 'instawp-connect' ) ) );
			}

			wp_send_json_success( array( 'message' => esc_html__( 'Plugin reset successfully.', 'instawp-connect' ) ) );
		}


		public function connect_api_url() {
			check_ajax_referer( 'instawp-connect', 'security' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'instawp-connect' ) ) );
			}

			$return_url      = urlencode( admin_url( 'tools.php?page=instawp&instawp-nonce=' . wp_create_nonce( 'instawp_connect_nonce' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
			$connect_api_url = Helper::get_api_domain() . '/authorize?source=connect_manage&return_url=' . $return_url;

			wp_send_json_success( array( 'connect_url' => $connect_api_url ) );
		}


		public function update_settings() {

			$_form_data = isset( $_REQUEST['form_data'] ) ? wp_kses_post( wp_unslash( $_REQUEST['form_data'] ) ) : '';
			$_form_data = str_replace( 'amp;', '', $_form_data );

			parse_str( $_form_data, $form_data );

            $form_data = wp_parse_args( $form_data, array(
                'instawp_hide_plugin_to_users' => array(),
                'instawp_sync_tab_roles'       => array(),
            ) );

			$settings_nonce = Helper::get_args_option( 'instawp_settings_nonce', $form_data );

			if ( ! wp_verify_nonce( $settings_nonce, 'instawp_settings_nonce_action' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed. Please try again reloading the page.', 'instawp-connect' ) ) );
			}

			foreach ( InstaWP_Setting::get_plugin_settings_fields() as $field_id ) {
				if ( ! isset( $form_data[ $field_id ] ) ) {
					continue;
				}
				$field_value = Helper::get_args_option( $field_id, $form_data );

				if ( 'instawp_api_options' === $field_id && is_array( $field_value ) ) {
					$api_key     = Helper::get_args_option( 'api_key', $field_value );
					$api_options = Option::get_option( 'instawp_api_options', array() );
					$old_api_key = Helper::get_args_option( 'api_key', $api_options );

					if ( ! empty( $api_key ) && $api_key !== $old_api_key ) {
						$api_key_check_response = Helper::generate_api_key( $api_key );
						if ( ! $api_key_check_response ) {
							wp_send_json_error( array(
								'message' => esc_html__( 'Error. Invalid API Key', 'instawp-connect' ),
							) );
						}
						continue;
					}
					$field_value = array_merge( $api_options, $field_value );
				}

				if ( 'instawp_hide_plugin_to_users' === $field_id && is_array( $field_value ) ) {
					$field_value = array_diff( $field_value,  array( get_current_user_id() ) );
				}

				Option::update_option( $field_id, $field_value );
			}

			wp_send_json_success( array( 'message' => esc_html__( 'Success. Settings updated.', 'instawp-connect' ) ) );
		}

		/**
		 * @return InstaWP_Migration
		 */
		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}
}

InstaWP_Migration::instance();