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

		// Stop loading admin menu
		add_filter( 'instawp_add_plugin_admin_menu', '__return_false' );

		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_button' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts_styles' ) );
		add_action( 'admin_menu', array( $this, 'add_go_live_integration_menu' ) );
		add_filter( 'admin_footer_text', array( $this, 'update_footer_credit_text' ) );
		add_filter( 'admin_title', array( $this, 'update_admin_page_title' ) );
		add_action( 'wp_ajax_instawp_go_live_clean', array( $this, 'go_live_clean' ) );
		add_action( 'wp_ajax_instawp_go_live_restore_init', array( $this, 'go_live_restore_init' ) );
		add_action( 'wp_ajax_instawp_go_live_restore', array( $this, 'go_live_restore' ) );
		add_action( 'wp_ajax_instawp_go_live_restore_status', array( $this, 'go_live_restore_status' ) );
	}


	function go_live_restore_status() {

//		wp_send_json_success( array( 'progress' => rand( 90, 100 ), 'message' => esc_html__( 'This is sample message.', 'instawp-connect' ) ) );

		global $InstaWP_Curl;

		$task_id = isset( $_POST['task_id'] ) ? sanitize_text_field( $_POST['task_id'] ) : '';

		if ( empty( $task_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid or empty task id.', 'instawp-connect' ) ) );
		}

		$api_url       = InstaWP_Setting::get_api_domain() . INSTAWP_API_URL . '/connects/get_restore_status';
		$body          = array(
			'task_id'    => $task_id,
			'connect_id' => self::$_connect_id,
		);
		$body_json     = ! empty( $body ) ? json_encode( $body ) : '';
		$curl_response = $InstaWP_Curl->curl( $api_url, $body_json );

		if ( isset( $curl_response['error'] ) && $curl_response['error'] == 1 ) {
			wp_send_json_error( $curl_response );
		}

		$curl_response = $curl_response['curl_res'] ?? '';
		$curl_response = json_decode( $curl_response, true );

		if ( isset( $curl_response['error'] ) && $curl_response['error'] == 0 ) {
			wp_send_json_error( $curl_response );
		}

		wp_send_json_error( ( $curl_response['data'] ?? array() ) );
	}


	/**
	 * Process go live action - Restore
	 *
	 * @return void
	 */
	function go_live_restore() {

		$restore_id = isset( $_POST['restore_id'] ) ? sanitize_text_field( $_POST['restore_id'] ) : '';

		if ( empty( $restore_id ) ) {
			wp_send_json_error( array( 'progress' => 20, 'message' => esc_html__( 'Invalid or empty restore id.', 'instawp-connect' ) ) );
		}

		$backup_task = new InstaWP_Backup_Task();

		if ( empty( $backup_task->get_id() ) ) {
			wp_send_json_error( array( 'progress' => 20, 'message' => esc_html__( 'Invalid or empty task id.', 'instawp-connect' ) ) );
		}

		$backup_files     = array_map( function ( $file_path ) {
			return home_url() . '/wp-content/instawpbackups/' . basename( $file_path );
		}, $backup_task->get_backup_files() );
		$restore_response = $this->get_api_response( 'restore', true, array(
			"restore_id"        => $restore_id,
			"progress"          => 100,
			"task_id"           => $backup_task->get_id(),
			"restore_file_path" => $backup_files,
		) );

		$response = $restore_response['response'] ?? '';
		$response = json_decode( $response, true );

		$response['task_id'] = $backup_task->get_id();

		if ( isset( $response['error'] ) && $response['error'] === true ) {
			$response['progress'] = 20;
			wp_send_json_error( $response );
		}

		$response['progress'] = 30;
		$response['message']  = esc_html__( 'Restore completed successfully.', 'instawp-connect' );

		wp_send_json_success( $response );
	}


	/**
	 * Restore Init
	 *
	 * @return void
	 */
	function go_live_restore_init() {

		$restore_init_response = $this->get_api_response( 'restore-init' );
		$restore_id            = $restore_init_response['restore_id'] ?? '';

		wp_send_json_success( array( 'restore_id' => $restore_id, 'progress' => 20, 'message' => esc_html__( 'Initializing restoration.', 'instawp-connect' ) ) );
	}


	/**
	 * Clean previous backup before taking new backup for go live
	 *
	 * @return void
	 */
	function go_live_clean() {

		delete_option( 'instawp_task_list' );

		$backup = new InstaWP_Backup();
		$backup->clean_backup();

		wp_send_json_success( array( 'progress' => 10, 'message' => esc_html__( 'Preparing to initiate restoration.', 'instawp-connect' ) ) );
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
                                <input type="hidden" name="instawp_go_live_restore_id" id="instawp_go_live_restore_id" value="">
                                <input type="hidden" name="instawp_go_live_task_id" id="instawp_go_live_task_id" value="">
								<?php // wp_nonce_field( 'instawp_ajax', 'instawp_ajax_nonce_field' ); ?>
                                <button class="live-btn instawp-btn-go-live" data-cloudways="https://wordpress-891015-3243964.cloudwaysapps.com/wp-admin/"><?php echo esc_html__( 'Go Live', 'instawp-connect' ); ?></button>
                                <div class="trial-footer-flex go-live-loader">
                                    <img src="<?php echo esc_url( $this->get_asset_url( 'images/loader.svg' ) ); ?>" alt="" class="spin">
                                    <p class="go-live-status-message"></p>
                                    <p class="go-live-status-progress"></p>
                                </div>
                            </div>
                            <a class="manage-account-link" href=""><?php echo esc_html__( 'My Cloudways Account', 'instawp-connect' ); ?> <img src="<?php echo esc_url( $this->get_asset_url( 'images/link-icon.svg' ) ); ?>" alt=""></a>
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
	function update_admin_page_title() {
		return esc_html__( 'InstaWP Cloudways Integration', 'instawp-connect' );
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