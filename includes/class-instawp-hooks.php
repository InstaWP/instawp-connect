<?php
/**
 * Class for all hooks
 */

defined( 'ABSPATH' ) || exit;


if ( ! class_exists( 'InstaWP_Hooks' ) ) {
	class InstaWP_Hooks {

		public function __construct() {

			add_action( 'init', array( $this, 'ob_start' ) );
			add_action( 'wp_footer', array( $this, 'ob_end' ) );

			add_action( 'init', array( $this, 'handle_hard_disable_seo_visibility' ) );
			add_action( 'admin_init', array( $this, 'handle_clear_all' ) );
			add_action( 'admin_bar_menu', array( $this, 'add_instawp_menu_icon' ), 999 );
			add_action( 'wp_enqueue_scripts', array( $this, 'front_enqueue_scripts' ) );
		}


		public function front_enqueue_scripts() {
			wp_enqueue_style( 'instawp-connect', instawp()::get_asset_url( 'assets/css/style.min.css' ) );
		}

		function add_instawp_menu_icon( WP_Admin_Bar $admin_bar ) {

			global $current_user;

			$sync_tab_roles = InstaWP_Setting::get_option( 'instawp_sync_tab_roles', [ 'administrator' ] );
			$sync_tab_roles = ! is_array( $sync_tab_roles ) || empty( $sync_tab_roles ) ? [ 'administrator' ] : $sync_tab_roles;
			$meta_classes   = [ 'instawp-sync-recording' ];

			if ( '1' == InstaWP_Setting::get_option( 'instawp_is_event_syncing', '0' ) ) {
				$meta_classes[] = 'recording-on';
			}

			if ( ! empty( array_intersect( $sync_tab_roles, $current_user->roles ) ) ) {
				$admin_bar->add_menu(
					array(
						'id'    => 'instawp',
						'title' => '',
						'href'  => admin_url( 'tools.php?page=instawp' ),
						'meta'  => array(
							'class' => implode( ' ', $meta_classes ),
						),
					)
				);
			}
		}


		function handle_hard_disable_seo_visibility() {
			if ( instawp()->is_staging && (int) INSTAWP_Setting::get_option( 'blog_public' ) === 1 ) {
				update_option( 'blog_public', '0' );
			}
		}


		function handle_clear_all() {

			$admin_page   = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
			$clear_action = isset( $_GET['clear'] ) ? sanitize_text_field( $_GET['clear'] ) : '';

			if ( isset( $_GET['connect_id'] ) && ! empty( $_GET['connect_id'] ) ) {
				$instawp_api_options = get_option( 'instawp_api_options', [] );

				$instawp_api_options['connect_id'] = sanitize_text_field( $_GET['connect_id'] );

				update_option( 'instawp_api_options', $instawp_api_options );
			}


			if ( 'instawp' === $admin_page && 'all' === $clear_action ) {

				instawp_reset_running_migration( 'soft', true );

				wp_redirect( admin_url( 'tools.php?page=instawp' ) );
				exit();
			}
		}


		/**
		 * Return Buffered Content
		 *
		 * @param $buffer
		 *
		 * @return mixed
		 */
		function ob_callback( $buffer ) {
			return $buffer;
		}


		/**
		 * Start of Output Buffer
		 */
		function ob_start() {
			ob_start( array( $this, 'ob_callback' ) );
		}


		/**
		 * End of Output Buffer
		 */
		function ob_end() {
			if ( ob_get_length() ) {
				ob_end_flush();
			}
		}
	}
}

new InstaWP_Hooks();
