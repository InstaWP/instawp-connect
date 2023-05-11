<?php
/**
 * This is for go live integration.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instaWP
 * @subpackage instaWP/admin
 */

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

// Check if the connect mode is DEPLOYER, otherwise return.
if ( ! defined( 'INSTAWP_CONNECT_MODE' ) || 'DEPLOYER' != INSTAWP_CONNECT_MODE ) {
	return;
}

class InstaWP_Go_Live {

	protected static $_instance = null;

	protected static $_platform_title = '';

	protected static $_platform_whitelabel = false;

	protected static $_connect_id = false;

	protected static $_response = array();


	/**
	 * InstaWP_Go_Live constructor
	 */
	public function __construct() {

		if ( defined( 'INSTAWP_CONNECT_WHITELABEL_TITLE' ) ) {
			self::$_platform_title = INSTAWP_CONNECT_WHITELABEL_TITLE;
		}

		if ( defined( 'INSTAWP_CONNECT_WHITELABEL' ) && INSTAWP_CONNECT_WHITELABEL ) {
			self::$_platform_whitelabel = INSTAWP_CONNECT_WHITELABEL;
		}

		$connect_ids       = get_option( 'instawp_connect_id_options' );
		self::$_connect_id = $connect_ids['data']['id'] ?? 0;

		if ( empty( self::$_connect_id ) ) {
			self::$_connect_id = $connect_ids['data']['connect_id'] ?? 0;
		}

		self::$_response = array(
			'backup'     => array(
				'progress' => 0,
				'message'  => esc_html__( 'Backup creation is in process.', 'instawp-connect' ),
			),
			'upload'     => array(
				'progress' => 0,
			),
			'migrate'    => array(
				'progress' => 0,
				'message'  => esc_html__( 'Migration is in process.', 'instawp-connect' ),
			),
			'status'     => 'running',
			'migrate_id' => '',
		);


		// Stop loading admin menu
		add_filter( 'instawp_add_plugin_admin_menu', '__return_false' );
		add_filter( 'all_plugins', array( $this, 'handle_instawp_plugin_display' ) );

		if (
			defined( 'INSTAWP_CONNECT_MODE' ) &&
			INSTAWP_CONNECT_MODE === 'DEPLOYER' &&
			(
				get_option( 'instawp_is_staging', true ) === false ||
				get_option( 'instawp_is_staging', true ) === 'false' ||
				get_option( 'instawp_is_staging', true ) === '0' ||
				get_option( 'instawp_is_staging', true ) === 0 ||
				get_option( 'instawp_is_staging', true ) === ''
			)
		) {
			return;
		}

		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_button' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts_styles' ) );
		add_action( 'admin_menu', array( $this, 'add_go_live_integration_menu' ) );
		add_filter( 'admin_footer_text', array( $this, 'update_footer_credit_text' ) );
		add_filter( 'admin_title', array( $this, 'update_admin_page_title' ) );

		add_action( 'wp_ajax_instawp_go_live_clean', array( $this, 'go_live_clean' ) );
		add_action( 'wp_ajax_instawp_go_live_restore_init', array( $this, 'go_live_restore_init' ) );
		add_action( 'wp_ajax_instawp_go_live_restore_status', array( $this, 'go_live_restore_status' ) );
	}


	/**
	 * Remove the plugin from plugins list
	 *
	 * @param $plugins
	 *
	 * @return mixed
	 */
	function handle_instawp_plugin_display( $plugins ) {

		if (
			in_array( INSTAWP_PLUGIN_NAME, array_keys( $plugins ) ) &&
			defined( 'INSTAWP_CONNECT_MODE' ) &&
			INSTAWP_CONNECT_MODE === 'DEPLOYER' &&
			get_option( 'instawp_is_staging', true ) === false
		) {
			unset( $plugins[ INSTAWP_PLUGIN_NAME ] );
		}

		return $plugins;
	}


	function go_live_restore_status() {

		$migrate_id = isset( $_POST['migrate_id'] ) ? sanitize_text_field( $_POST['migrate_id'] ) : '';

		if ( empty( $migrate_id ) ) {

			self::$_response['migrate']['message'] = esc_html__( 'Invalid migrate id.', 'instawp-connect' );

			wp_send_json_error( self::$_response );
		}

		self::$_response['migrate_id']         = $migrate_id;
		self::$_response['backup']['progress'] = 100;
		self::$_response['upload']['progress'] = 100;

		self::$_response = instawp_get_response_progresses( 0, $migrate_id, self::$_response );

		if ( InstaWP_Setting::get_args_option( 'status', self::$_response ) == 'completed' ) {
			update_option( 'instawp_is_staging', false );
		}

		wp_send_json_success( self::$_response );
	}


	/**
	 * Restore Init
	 *
	 * @return void
	 */
	function go_live_restore_init() {


		global $instawp_plugin;

		$backup_options      = array(
			'ismerge'      => '',
			'backup_files' => 'files+db',
			'local'        => '1',
			'type'         => 'Manual',
			'action'       => 'backup',
			'is_migrate'   => true,
		);
		$backup_options      = apply_filters( 'INSTAWP_CONNECT/Filters/migrate_backup_options', $backup_options );
		$incomplete_task_ids = InstaWP_taskmanager::is_there_any_incomplete_task_ids();

		if ( empty( $incomplete_task_ids ) ) {
			$pre_backup_response = $instawp_plugin->pre_backup( $backup_options );
			$migrate_task_id     = InstaWP_Setting::get_args_option( 'task_id', $pre_backup_response );
		} else {
			$migrate_task_id = reset( $incomplete_task_ids );
		}

		$migrate_task_obj = new InstaWP_Backup_Task( $migrate_task_id );
		$migrate_task     = InstaWP_taskmanager::get_task( $migrate_task_id );

		// Backing up the files
		foreach ( InstaWP_taskmanager::get_task_backup_data( $migrate_task_id ) as $key => $data ) {

			$backup_status = InstaWP_Setting::get_args_option( 'backup_status', $data );

			if ( 'completed' != $backup_status && 'backup_db' == $key ) {
				$backup_database = new InstaWP_Backup_Database();
				$backup_response = $backup_database->backup_database( $data, $migrate_task_id );

				if ( INSTAWP_SUCCESS == InstaWP_Setting::get_args_option( 'result', $backup_response ) ) {
					$migrate_task['options']['backup_options']['backup'][ $key ]['files'] = $backup_response['files'];
				} else {
					$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status'] = 'in_progress';
				}

				$packages = instawp_get_packages( $migrate_task_obj, $migrate_task['options']['backup_options']['backup'][ $key ] );
				$result   = instawp_build_zip_files( $migrate_task_obj, $packages, $migrate_task['options']['backup_options']['backup'][ $key ] );

				if ( isset( $result['files'] ) && ! empty( $result['files'] ) ) {
					$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files']     = $result['files'];
					$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status'] = 'completed';
				}

				InstaWP_taskmanager::update_task( $migrate_task );
				break;
			}

			if ( 'completed' != $backup_status ) {

				$migrate_task['options']['backup_options']['backup'][ $key ]['files'] = $migrate_task_obj->get_need_backup_files( $migrate_task['options']['backup_options']['backup'][ $key ] );

				$packages = instawp_get_packages( $migrate_task_obj, $migrate_task['options']['backup_options']['backup'][ $key ] );
				$result   = instawp_build_zip_files( $migrate_task_obj, $packages, $migrate_task['options']['backup_options']['backup'][ $key ] );

				if ( isset( $result['files'] ) && ! empty( $result['files'] ) ) {
					$migrate_task['options']['backup_options']['backup'][ $key ]['zip_files']     = $result['files'];
					$migrate_task['options']['backup_options']['backup'][ $key ]['backup_status'] = 'completed';
				}

				InstaWP_taskmanager::update_task( $migrate_task );

				break;
			}
		}


		// Cleaning the non-zipped files and folders
		instawp_clean_non_zipped_files_folder( $migrate_task );


		self::$_response = instawp_get_response_progresses( $migrate_task_id, 0, self::$_response, array( 'generate_local_parts_urls' => true ) );

		if ( isset( self::$_response['backup']['progress'] ) && self::$_response['backup']['progress'] == 100 ) {

			if ( empty( $migrate_id = InstaWP_Setting::get_args_option( 'migrate_id', $migrate_task ) ) ) {

				$migrate_args = array(
					'source_domain'  => site_url(),
					'php_version'    => '6.0',
					'plugin_version' => '2.0',
					'migration_mode' => 'wizard',
					'part_urls'      => InstaWP_Setting::get_args_option( 'part_urls', self::$_response, array() ),
				);

				error_log( "Migrate api args: " . json_encode( $migrate_args ) );

				$migrate_response = InstaWP_Curl::do_curl( 'migrates', $migrate_args );
				$migrate_id       = isset( $migrate_response['data']['migrate_id'] ) ? $migrate_response['data']['migrate_id'] : '';

				if ( empty( $migrate_id ) ) {
					self::$_response['migrate']['message'] = esc_html__( 'Error creating migrate id.', 'instawp-connect' );

					wp_send_json_error( self::$_response );
				}

				$migrate_task['migrate_id'] = $migrate_id;

				InstaWP_taskmanager::update_task( $migrate_task );
			}

			self::$_response['backup']['message'] = esc_html__( 'Backup created successfully.', 'instawp-connect' );
			self::$_response['migrate_id']        = $migrate_id;
		}

		wp_send_json_success( self::$_response );
	}


	/**
	 * Clean previous backup before taking new backup for go live
	 *
	 * @return void
	 */
	function go_live_clean() {

		instawp_reset_running_migration();

		wp_send_json_success( array( 'progress' => 10, 'message' => esc_html__( 'Preparing to initiate the migration.', 'instawp-connect' ) ) );
	}


	/**
	 * Render go live integration
	 *
	 * @return void
	 */
	function render_go_live_integration() {

		$trial_details  = $this->get_api_response( '', false );
		$trial_domain   = $trial_details['domain'] ?? '';
		$time_to_expire = $trial_details['time_to_expire'] ?? '';

		?>
        <div class="wrap instawp-go-live-wrap">
            <div>
                <h2><?php echo esc_html__( 'Cloudways Trial Site', 'instawp-connect' ); ?></h2>
                <div class="main-wrapper">
                    <h3><?php echo esc_html__( 'Trial Details', 'instawp-connect' ); ?></h3>
                    <div class="trial-wrapper trial-wrapper-margin">
                        <div class="trail-padding">
                            <div class="trial-flex">
                                <h4><?php echo esc_html__( 'Trial Domain', 'instawp-connect' ); ?></h4>
                                <h6><?php echo esc_url( $trial_domain ); ?></h6>
                            </div>
                            <div class="trial-flex trial-margin">
                                <h4><?php echo esc_html__( 'Trial Period', 'instawp-connect' ); ?></h4>
                                <h6><?php echo sprintf( esc_html__( '%s Remaining', 'instawp-connect' ), $time_to_expire ); ?></h6>
                            </div>
                        </div>
                        <div class="trial-footer">
                            <div class="trial-footer-flex">
                                <input type="hidden" name="instawp_go_live_connect_id" id="instawp_go_live_connect_id" value="<?php echo esc_attr( self::$_connect_id ); ?>">
                                <input type="hidden" name="instawp_go_live_step" id="instawp_go_live_step" value="1">
                                <input type="hidden" name="instawp_go_live_restore_id" id="instawp_go_live_restore_id" value="">
								<?php // wp_nonce_field( 'instawp_ajax', 'instawp_ajax_nonce_field' ); ?>
                                <button class="live-btn instawp-btn-go-live" data-cloudways="https://wordpress-891015-3243964.cloudwaysapps.com/wp-admin/"><?php echo esc_html__( 'Go Live', 'instawp-connect' ); ?></button>
                                <div class="trial-footer-flex go-live-loader">
                                    <img src="<?php echo esc_url( $this->get_asset_url( 'images/loader.svg' ) ); ?>" alt="" class="spin">
                                    <p class="go-live-status-message"></p>
                                    <p class="go-live-status-progress"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

		<?php
	}


	/**
	 * Add menu for go live integration
	 *
	 * @return void
	 */
	function add_go_live_integration_menu() {

		add_menu_page( esc_html__( 'InstaWP Cloudways Integration', 'instawp-connect' ), esc_html__( 'InstaWP Cloudways Integration', 'instawp-connect' ), 'administrator', 'instawp-connect-go-live', array( $this, 'render_go_live_integration' ) );
		remove_menu_page( 'instawp-connect-go-live' );
	}


	/**
	 * Update admin page title
	 *
	 * @return string
	 */
	function update_admin_page_title( $page_title ) {

		if ( isset( $_GET['page'] ) && sanitize_text_field( $_GET['page'] ) === 'instawp-connect-go-live' ) {
			$page_title = esc_html__( 'InstaWP Cloudways Integration', 'instawp-connect' );
		}

		return $page_title;
	}


	/**
	 * Update footer credit text
	 *
	 * @param $credit_text
	 *
	 * @return mixed|string
	 */
	function update_footer_credit_text( $credit_text ) {

		global $current_screen;

		if ( 'toplevel_page_instawp-connect-go-live' == $current_screen->base ) {
			$credit_text = '';
		}

		return $credit_text;
	}


	/**
	 * Add admin styles
	 *
	 * @return void
	 */
	function admin_scripts_styles() {
		wp_enqueue_style( 'instawp-go-live', $this->get_asset_url( 'css/instawp-go-live.css' ) );
		wp_enqueue_script( 'instawp-go-live', $this->get_asset_url( 'js/instawp-go-live.js' ), array( 'jquery' ) );
		wp_localize_script( 'instawp-go-live', 'instawp_ajax_go_live_obj',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);
	}


	/**
	 * Add top menu bar button
	 *
	 * @param WP_Admin_Bar $admin_bar
	 *
	 * @return void
	 */
	function add_admin_bar_button( WP_Admin_Bar $admin_bar ) {

		$admin_bar->add_menu(
			array(
				'id'     => 'instawp-go-live',
				'title'  => esc_html__( 'Go Live', 'instawp-connect' ),
				'href'   => admin_url( 'admin.php?page=instawp-connect-go-live' ),
				'parent' => 'top-secondary',
			)
		);
	}


	/**
	 * Return asset URL, it could be images, css files, js files etc.
	 *
	 * @param $asset_name
	 *
	 * @return string
	 */
	protected function get_asset_url( $asset_name ) {
		return INSTAWP_PLUGIN_DIR_URL . $asset_name;
	}


	/**
	 * Send api request and return processed response
	 *
	 * @param $endpoint
	 * @param $is_post
	 *
	 * @return array|mixed
	 */
	protected function get_api_response( $endpoint = '', $is_post = true, $body = array(), $version = 2 ) {

		global $InstaWP_Curl;

		$api_version = $version === 1 ? INSTAWP_API_URL : INSTAWP_API_2_URL;
		$api_url     = InstaWP_Setting::get_api_domain() . $api_version . '/connects/' . self::$_connect_id;

		if ( ! empty( $endpoint ) ) {
			$api_url .= '/' . $endpoint;
		}

		$body_json     = ! empty( $body ) ? json_encode( $body ) : '';
		$curl_response = $InstaWP_Curl->curl( $api_url, $body_json, [], $is_post );

		if ( is_wp_error( $curl_response ) ) {
			return array( 'status' => false );
		}

		if ( isset( $curl_response['error'] ) && $curl_response['error'] == 1 ) {
			return $curl_response;
		}

		$curl_response = $curl_response['curl_res'] ?? array();
		$curl_response = json_decode( $curl_response, true );

		if ( isset( $curl_response['error'] ) && $curl_response['error'] == 0 ) {
			return $curl_response;
		}

		return $curl_response['data'] ?? array();
	}


	/**
	 * @return InstaWP_Go_Live
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}

InstaWP_Go_Live::instance();