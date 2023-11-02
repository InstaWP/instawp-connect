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

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class InstaWP_Admin {

	private $plugin_name;

	private $version;

	private $screen_ids = [];

	protected static $_is_deployer_mode = false;

	protected static $_is_template_migration_mode = false;


	public function __construct( $plugin_name, $version ) {


		if ( defined( 'INSTAWP_CONNECT_MODE' ) && 'TEMPLATE_MIGRATE' == INSTAWP_CONNECT_MODE ) {
			self::$_is_template_migration_mode = true;
		}

		if ( defined( 'INSTAWP_CONNECT_MODE' ) && 'WAAS_GO_LIVE' == INSTAWP_CONNECT_MODE ) {
			self::$_is_deployer_mode = true;
		}

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'admin_menu', array( $this, 'add_migrate_plugin_menu_items' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_button' ), 100 );

		if ( defined( 'INSTAWP_CONNECT_MODE' ) && in_array( INSTAWP_CONNECT_MODE, [ 'WAAS_GO_LIVE', 'TEMPLATE_MIGRATE' ] ) ) {
			add_filter( 'all_plugins', array( $this, 'handle_instawp_plugin_display' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function add_action_links( $links ) {

		$action_links = array(
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'tools.php?page=instawp' ) ), esc_html__( 'Create Staging', 'instawp-connect' ) ),
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

		if ( self::$_is_deployer_mode ) {
			$admin_bar->add_menu(
				array(
					'id'     => 'instawp-go-live',
					'title'  => esc_html__( 'Go Live', 'instawp-connect' ),
					'href'   => '#',
					'parent' => 'top-secondary',
				)
			);

			return;
		}

		$admin_bar->add_menu(
			array(
				'id'    => 'instawp',
				'title' => '',
				'href'  => admin_url( 'tools.php?page=instawp' ),
			)
		);
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
				'administrator', 'instawp-template-migrate', array( $this, 'render_template_migrate_page' ), 2
			);
			remove_menu_page( 'instawp-template-migrate' );

			return;
		}

		// Go Live mode
		if ( self::$_is_deployer_mode ) {
			return;
		}

		add_management_page(
			esc_html__( 'InstaWP', 'instawp-connect' ),
			esc_html__( 'InstaWP', 'instawp-connect' ),
			InstaWP_Setting::get_allowed_role(), 'instawp', array( $this, 'render_migrate_page' ), 1
		);
	}


	function render_template_migrate_page() {
		include INSTAWP_PLUGIN_DIR . '/migrate/templates/main-migrate.php';
	}

	function render_migrate_page() {
		include INSTAWP_PLUGIN_DIR . '/migrate/templates/main.php';
	}


	public function enqueue_scripts() {
		//change events scripts [start]
		wp_enqueue_style( 'instawp-select2', INSTAWP_PLUGIN_DIR_URL . 'css/select2.min.css' );
		wp_enqueue_script( 'instawp-select2', INSTAWP_PLUGIN_DIR_URL . 'js/select2.min.js', array( 'jquery' ) );
		wp_enqueue_style( 'change-event-css', INSTAWP_PLUGIN_DIR_URL . 'css/instawp-change-event.css' );
		wp_enqueue_script( 'ajax_script', INSTAWP_PLUGIN_DIR_URL . 'js/instawp-change-event.js', array( 'jquery' ), $this->version, false );
		wp_localize_script( 'ajax_script', 'ajax_obj',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'instaWp_change_event' ),
				'plugin_images_url' => INSTAWP_PLUGIN_IMAGES_URL,
				'data'              => [
					'event_toolbar_html' => '<li id="wp-admin-bar-instawp-sync-toolbar" class="instawp-sync-status-toolbar"><a class="ab-item" href="' . admin_url( 'tools.php?page=instawp' ) . '" title="' . __( "Recording", "instawp-connect" ) . '">' . __( "Recording", "instawp-connect" ) . '</a></li>',
				],
				'trans'             => [
					'create_staging_site_txt' => __( 'Please create staging sites first.', 'instawp-connect' )
				]
			)
		);


		if ( isset( $_GET['page'] ) && in_array( sanitize_text_field( $_GET['page'] ), [ 'instawp', 'instawp-template-migrate' ] ) ) {
			wp_enqueue_style( 'instawp-tailwind', instawp()::get_asset_url( 'assets/css/tailwind.min.css' ), [], current_time( 'U' ) );
		}

		wp_enqueue_style( 'instawp-hint', instawp()::get_asset_url( 'migrate/assets/css/hint.min.css' ), [ 'instawp-migrate' ], '2.7.0' );
		wp_enqueue_style( 'instawp-migrate', instawp()::get_asset_url( 'migrate/assets/css/style.css' ), [], current_time( 'U' ) );
		wp_enqueue_style( 'instawp-connect', instawp()::get_asset_url( 'assets/css/style.min.css' ), [], current_time( 'U' ) );

		wp_enqueue_script( 'instawp-migrate', instawp()::get_asset_url( 'assets/js/scripts.js' ), array(), current_time( 'U' ) );
		wp_localize_script( 'instawp-migrate', 'instawp_migrate',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'security' => wp_create_nonce( 'instawp-migrate' )
			)
		);


		wp_enqueue_script( $this->plugin_name, INSTAWP_PLUGIN_DIR_URL . 'js/instawp-admin.js', array( 'jquery' ), $this->version, false );
		$this->screen_ids = apply_filters( 'instawp_get_screen_ids', $this->screen_ids );

		if ( in_array( get_current_screen()->id, $this->screen_ids ) ) {

			wp_enqueue_script( $this->plugin_name, INSTAWP_PLUGIN_DIR_URL . 'js/instawp-admin.js', array( 'jquery' ), $this->version, false );

			$instawp_api_url = InstaWP_Setting::get_api_domain();
			wp_localize_script( $this->plugin_name, 'instawp_ajax_object', array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'cloud_url'          => $instawp_api_url,
				'admin_url'          => admin_url(),
				'ajax_nonce'         => wp_create_nonce( 'instawp_ajax' ),
				'nlogger'            => wp_create_nonce( 'instawp_nlogger_update_option_by-nlogger' ),
				'plugin_connect_url' => admin_url( "admin.php?page=instawp-connect" ),
			) );

			wp_localize_script( $this->plugin_name, 'instawplion', array(
				'warning'             => __( 'Warning:', 'instawp-connect' ),
				'error'               => __( 'Error:', 'instawp-connect' ),
				'remotealias'         => __( 'Warning: An alias for remote storage is required.', 'instawp-connect' ),
				'remoteexist'         => __( 'Warning: The alias already exists in storage list.', 'instawp-connect' ),
				'backup_calc_timeout' => __( 'Calculating the size of files, folder and database timed out. If you continue to receive this error, please go to the plugin settings, uncheck \'Calculate the size of files, folder and database before backing up\', save changes, then try again.', 'instawp-connect' ),
				'restore_step1'       => __( 'Step One: In the backup list, click the \'Restore\' button on the backup you want to restore. This will bring up the restore tab', 'instawp-connect' ),
				'restore_step2'       => __( 'Step Two: Choose an option to complete restore, if any', 'instawp-connect' ),
				'restore_step3'       => __( 'Step Three: Click \'Restore\' button', 'instawp-connect' ),
				'get_key_step1'       => __( '1. Visit Key tab page of instaWP backup plugin of destination site.', 'instawp-connect' ),
				'get_key_step2'       => __( '2. Generate a key by clicking Generate button and copy it.', 'instawp-connect' ),
				'get_key_step3'       => __( '3. Go back to this page and paste the key in key box below. Lastly, click Save button.', 'instawp-connect' ),

			) );
			wp_enqueue_script( 'plupload-all' );
			do_action( 'instawp_do_enqueue_scripts' );
		}
	}
}