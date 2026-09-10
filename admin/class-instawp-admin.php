<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instaWP
 * @subpackage instaWP/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    instaWP
 * @subpackage instaWP/admin
 * @author     instawp team
 */

use InstaWP\Connect\Helpers\Option;

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class InstaWP_Admin {

	private $plugin_name;

	private $version;

	protected static $_is_waas_mode = false;

	protected static $_is_template_migration_mode = false;

	protected static $_assets_version = null;

	public function __construct( $plugin_name, $version ) {

		if ( defined( 'INSTAWP_CONNECT_MODE' ) && 'TEMPLATE_MIGRATE' === INSTAWP_CONNECT_MODE ) {
			self::$_is_template_migration_mode = true;
		}

		if ( defined( 'INSTAWP_CONNECT_MODE' ) && 'WAAS_GO_LIVE' === INSTAWP_CONNECT_MODE ) {
			self::$_is_waas_mode = true;
		}

		/*
		 * Debug builds version assets by their newest MTIME, not by time().
		 *
		 * time() changes on every request, so the browser re-downloaded every admin asset on every
		 * page load and nothing was ever cached -- the version string was different before the reader
		 * had finished the page. An mtime changes only when a file actually changes, so an edit is
		 * picked up on the next load AND an unchanged asset still caches.
		 *
		 * Only OUR files are stat'ed. The vendored ones (select2, hint, tailwind) do not change during
		 * development, so including them would cost stat calls to learn nothing.
		 */
		if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
			$asset_mtimes = array();

			foreach ( array(
				'assets/js/scripts.js',
				'assets/js/common.js',
				'assets/css/common.min.css',
				'migrate/assets/css/style.css',
				'admin/js/instawp-change-event.js',
				'admin/css/instawp-change-event.css',
			) as $asset_rel_path ) {
				$asset_abs_path = INSTAWP_PLUGIN_DIR . $asset_rel_path;

				if ( is_readable( $asset_abs_path ) ) {
					$asset_mtimes[] = (int) filemtime( $asset_abs_path );
				}
			}

			// Falls back to the plugin version when nothing could be stat'ed, so a missing or
			// unreadable asset never yields an empty ?ver= -- which caches worse than any value.
			self::$_assets_version = empty( $asset_mtimes ) ? INSTAWP_PLUGIN_VERSION : (string) max( $asset_mtimes );
		} else {
			self::$_assets_version = INSTAWP_PLUGIN_VERSION;
		}

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		if ( ! is_multisite() || is_main_site() ) {
			add_action( 'admin_menu', array( $this, 'add_migrate_plugin_menu_items' ) );
		}

		// For Displaying Migrate and Go Live
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_button' ), 100 );

		if ( defined( 'INSTAWP_CONNECT_MODE' ) && in_array( INSTAWP_CONNECT_MODE, array( 'WAAS_GO_LIVE', 'TEMPLATE_MIGRATE' ) ) ) {
			add_filter( 'all_plugins', array( $this, 'handle_instawp_plugin_display' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );
	}

	public function add_action_links( $links ) {
		if ( defined( 'IWP_PLUGIN_NAME' ) ) {
			return $links;
		}

		$action_links = array(
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'tools.php?page=instawp&step=1' ) ), instawp()->is_staging ? esc_html__( 'Settings', 'instawp-connect' ) : esc_html__( 'Create Staging', 'instawp-connect' ) ),
		);

		return array_merge( $action_links, $links );
	}

	function add_admin_bar_button( WP_Admin_Bar $admin_bar ) {

		if ( self::$_is_template_migration_mode ) {
			$admin_bar->add_menu(
				array(
					'id'     => 'instawp-template-migrate',
					'title'  => esc_html__( 'Migrate', 'instawp-connect' ),
					'href'   => admin_url( 'admin.php?page=instawp-template-migrate' ),
					'parent' => 'top-secondary',
				)
			);

			return;
		}

		if ( self::$_is_waas_mode ) {
			$admin_bar->add_menu(
				array(
					'id'     => 'instawp-go-live',
					'title'  => esc_html__( 'Go Live', 'instawp-connect' ),
					'href'   => defined( 'INSTAWP_CONNECT_WAAS_URL' ) && ! empty( INSTAWP_CONNECT_WAAS_URL ) ? INSTAWP_CONNECT_WAAS_URL : '#',
					'meta'   => array(
						'target' => '_blank',
					),
					'parent' => 'top-secondary',
				)
			);

			return;
		}
	}

	function handle_instawp_plugin_display( $plugins ) {

		if ( in_array( INSTAWP_PLUGIN_NAME, array_keys( $plugins ) ) ) {
			unset( $plugins[ INSTAWP_PLUGIN_NAME ] );
		}

		return $plugins;
	}

	function add_migrate_plugin_menu_items() {

		// Hosting migrate mode
		if ( self::$_is_template_migration_mode ) {
			add_menu_page(
				esc_html__( 'InstaWP - Migrate', 'instawp-connect' ),
				esc_html__( 'InstaWP - Migrate', 'instawp-connect' ),
				'manage_options',
				'instawp-template-migrate',
				array( $this, 'render_template_migrate_page' ),
				2
			);
			remove_menu_page( 'instawp-template-migrate' );

			return;
		}

		// Go Live mode
		if ( self::$_is_waas_mode ) {
			return;
		}

		$selected_users = Option::get_option( 'instawp_hide_plugin_to_users' );
		if ( ! empty( $selected_users ) && is_array( $selected_users ) && in_array( get_current_user_id(), $selected_users ) ) {
			return;
		}

		add_management_page(
			defined( 'IWP_PLUGIN_NAME' ) ? IWP_PLUGIN_NAME : esc_html__( 'InstaWP', 'instawp-connect' ),
			defined( 'IWP_PLUGIN_NAME' ) ? IWP_PLUGIN_NAME : esc_html__( 'InstaWP', 'instawp-connect' ),
			InstaWP_Setting::get_allowed_role(),
			'instawp',
			array( $this, 'render_migrate_page' ),
			1
		);
	}

	function render_template_migrate_page() {
		include INSTAWP_PLUGIN_DIR . 'migrate/templates/main-migrate.php';
	}

	function render_migrate_page() {
		include INSTAWP_PLUGIN_DIR . 'migrate/templates/main.php';
	}

	public function enqueue_scripts() {

		if ( isset( $_GET['page'] ) && in_array( sanitize_text_field( $_GET['page'] ), array( 'instawp', 'instawp-template-migrate' ) ) ) {

			wp_enqueue_style( 'instawp-hint', instaWP::get_asset_url( 'assets/css/hint.min.css' ), array(), self::$_assets_version );
			wp_enqueue_style( 'instawp-tailwind', instaWP::get_asset_url( 'assets/css/tailwind.min.css' ), array(), self::$_assets_version );
			wp_enqueue_style( 'instawp-select2', instaWP::get_asset_url( 'admin/css/select2.min.css' ), array(), self::$_assets_version );
			wp_enqueue_script( 'instawp-select2', instaWP::get_asset_url( 'admin/js/select2.min.js' ), array( 'jquery' ), self::$_assets_version, true );

			wp_enqueue_style( 'instawp-change-event', instaWP::get_asset_url( 'admin/css/instawp-change-event.css' ), array(), self::$_assets_version );
			wp_enqueue_script( 'instawp-change-event', instaWP::get_asset_url( 'admin/js/instawp-change-event.js' ), array( 'jquery' ), self::$_assets_version, true );
			wp_localize_script( 'instawp-change-event', 'instawp_tws', InstaWP_Tools::get_localize_data() );

			wp_enqueue_style( 'instawp-migrate', instaWP::get_asset_url( 'migrate/assets/css/style.css' ), array(), self::$_assets_version );
			wp_enqueue_script( 'instawp-migrate', instaWP::get_asset_url( 'assets/js/scripts.js' ), array( 'jquery' ), self::$_assets_version, true );
			wp_localize_script( 'instawp-migrate', 'instawp_migrate', InstaWP_Tools::get_localize_data() );
		}

		wp_enqueue_style( 'instawp-common', instaWP::get_asset_url( 'assets/css/common.min.css' ), array(), self::$_assets_version );
		wp_enqueue_script( 'instawp-common', instaWP::get_asset_url( 'assets/js/common.js' ), array( 'jquery' ), self::$_assets_version, true );
		wp_localize_script( 'instawp-common', 'instawp_common', InstaWP_Tools::get_localize_data() );
	}
}
