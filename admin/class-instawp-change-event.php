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

defined( 'INSTAWP_PLUGIN_DIR' ) || die;


class InstaWP_Change_event {

	protected static $_instance = null;

	public function __construct() {

		add_action( 'admin_menu', array( $this, 'add_change_event_menu' ) );
	}


	function render_change_event_page() {
		include_once( 'partials/instawp-admin-change-event.php' );
	}


	function add_change_event_menu() {
		add_management_page(
			esc_html__( 'Change Event', 'instawp-connect' ),
			esc_html__( 'Change Event', 'instawp-connect' ),
			'administrator', 'instawp-change-event',
			array( $this, 'render_change_event_page' ),
			2
		);
	}


	/**
	 * @return InstaWP_Change_event
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}

InstaWP_Change_event::instance();