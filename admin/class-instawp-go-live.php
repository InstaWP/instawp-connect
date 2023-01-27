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

		// Stop loading admin menu
		add_filter( 'instawp_add_plugin_admin_menu', '__return_false' );

		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_button' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts_styles' ) );
		add_action( 'admin_menu', array( $this, 'add_go_live_integration_menu' ) );
		add_filter( 'admin_footer_text', array( $this, 'update_footer_credit_text' ) );
		add_filter( 'admin_title', array( $this, 'update_admin_page_title' ) );
	}


	/**
	 * Render go live integration
	 *
	 * @return void
	 */
	function render_go_live_integration() {
		?>
        <div class="wrap instawp-go-live-wrap">
            <div>
                <h2><?php echo esc_html__( 'Cloudways Manage Sites', 'instawp-connect' ); ?></h2>
                <div class="main-wrapper">
                    <h3><?php echo esc_html__( 'Trial Details', 'instawp-connect' ); ?></h3>
                    <div class="trial-wrapper trial-wrapper-margin">
                        <div class="trail-padding">
                            <div class="trial-flex">
                                <h4><?php echo esc_html__( 'Trial Domain', 'instawp-connect' ); ?></h4>
                                <h6><?php echo esc_attr( 'abcd.hostingcompany.com' ); ?></h6>
                            </div>
                            <div class="trial-flex trial-margin">
                                <h4><?php echo esc_html__( 'Trial Period', 'instawp-connect' ); ?></h4>
                                <h6><?php echo esc_html__( '4 days Remaining', 'instawp-connect' ); ?></h6>
                            </div>
                        </div>
                        <div class="trial-footer">
                            <div class="trial-footer-flex">
                                <button class="live-btn instawp-btn-go-live"><?php echo esc_html__( 'Go Live', 'instawp-connect' ); ?></button>
                                <div class="trial-footer-flex go-live-loader">
                                    <img src="<?php echo esc_url( $this->get_asset_url( 'images/loader.svg' ) ); ?>" alt="" class="spin">
                                    <p class="go-live-status-message"></p>
                                    <p class="go-live-status-progress"></p>
                                </div>
                            </div>
                            <a class="manage-account-link" href=""><?php echo esc_html__( 'My Cloudways Account', 'instawp-connect' ); ?> <img src="<?php echo esc_url( $this->get_asset_url( 'images/link-icon.svg' ) ); ?>" alt=""></a>
                        </div>
                    </div>

                    <div class="instawp-manage-sites">
                        <h3><?php echo esc_html__( 'Manage Site (Active after go live)', 'instawp-connect' ); ?></h3>
                        <div class="trial-wrapper">
                            <div class="trail-padding">
                                <label for="domain"><?php echo esc_html__( 'Connect Domain', 'instawp-connect' ); ?></label>
                                <div class="input-position">
                                    <input type="text" class="form-control" autocomplete="off" id="domain" placeholder="lorem ipsum">
                                    <div class="input-button">
                                        <button class="verify-btn"><?php echo esc_html__( 'Verify', 'instawp-connect' ); ?></button>
                                    </div>
                                </div>
                                <p><?php echo esc_html__( 'Point A record to', 'instawp-connect' ); ?> <span>123.123.123.123</span> <img src="<?php echo esc_url( $this->get_asset_url( 'images/copy-icon.svg' ) ); ?>" alt=""></p>
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