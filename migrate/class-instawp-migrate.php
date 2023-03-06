<?php
/**
 * InstaWP Migration Process
 */


if ( ! class_exists( 'INSTAWP_Migration' ) ) {
	class INSTAWP_Migration {

		protected static $_instance = null;

		/**
		 * INSTAWP_Migration Constructor
		 */
		public function __construct() {

			add_action( 'admin_menu', array( $this, 'add_migrate_menu' ) );

			if ( isset( $_GET['page'] ) && 'instawp' === sanitize_text_field( $_GET['page'] ) ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );

				add_filter( 'admin_footer_text', '__return_false' );
				add_filter( 'update_footer', '__return_false', 99 );
			}

			add_action( 'wp_ajax_instawp_update_settings', array( $this, 'update_settings' ) );
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
				if ( isset( $form_data[ $field_id ] ) ) {
					InstaWP_Setting::update_option( $field_id, InstaWP_Setting::get_args_option( $field_id, $form_data ) );
				}
			}

			wp_send_json_success( array( 'message' => esc_html__( 'Success. Settings updated.' ) ) );

			die();
		}


		/**
		 * @return void
		 */
		function enqueue_styles_scripts() {

			wp_enqueue_style( 'instawp-migrate', instawp()::get_asset_url( 'migrate/assets/css/style.css' ), [], current_time( 'U' ) );

			wp_enqueue_script( 'instawp-tailwind', instawp()::get_asset_url( 'migrate/assets/js/tailwind.js' ) );
			wp_enqueue_script( 'instawp-migrate', instawp()::get_asset_url( 'migrate/assets/js/scripts.js' ), array( 'instawp-tailwind' ), current_time( 'U' ) );
			wp_localize_script( 'instawp-migrate', 'instawp_migrate',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
		}


		/**
		 * @return void
		 */
		function render_migrate_page() {
			include INSTAWP_PLUGIN_DIR . '/migrate/templates/main.php';
		}


		/**
		 * @return void
		 */
		function add_migrate_menu() {
			add_menu_page(
				esc_html__( 'InstaWP', 'instawp-connect' ),
				esc_html__( 'InstaWP', 'instawp-connect' ),
				'administrator', 'instawp',
				array( $this, 'render_migrate_page' ),
				esc_url( INSTAWP_PLUGIN_IMAGES_URL . 'cloud.svg' ),
				2
			);
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