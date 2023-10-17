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
		add_action( 'init', array( $this, 'get_source_site_detail' ) );
		add_action( 'admin_bar_menu', array( $this, 'instawp_add_sync_status_toolbar_link' ), 999 );
	}

	function listEvents() {
		$InstaWP_db = new InstaWP_DB();
		$tables     = $InstaWP_db->tables;
		if ( isset( $_POST['filter_action'] ) && ! empty( $_POST['event_type'] ) ) {
			$rel = $InstaWP_db->get_with_condition( $tables['ch_table'], 'event_type', $_POST['event_type'] );
		} elseif ( isset( $_GET['change_event_status'] ) && $_GET['change_event_status'] != 'all' ) {
			$rel = $InstaWP_db->get_with_condition( $tables['ch_table'], 'status', $_GET['change_event_status'] );
		} else {
			$rel = $InstaWP_db->getAllEvents();
		}
		$data = [];
		if ( ! empty( $rel ) && is_array( $rel ) ) {
			foreach ( $rel as $v ) {
				$btn    = ( $v->status != 'completed' ) ? '<button type="button" id="btn-sync-' . $v->id . '" data-id="' . $v->id . '" class="two-way-sync-btn btn-single-sync">Sync changes</button> <span class="sync-loader"></span><span class="sync-success"></span>' : '<p class="sync_completed">Synced</p>';
				$data[] = [
					'ID'             => $v->id,
					'event_name'     => $v->event_name,
					'event_slug'     => $v->event_slug,
					'event_type'     => $v->event_type,
					'source_id'      => $v->source_id,
					'title'          => $v->title,
					'status'         => $v->status,
					'user_id'        => $v->user_id,
					'synced_message' => $v->synced_message,
					'date'           => '<span class="synced_status">' . $v->status . '</span><br/><span>' . $v->date . '</span>',
					'sync'           => $btn,
				];
			}
		}

		return $data;
	}

	public function get_source_site_detail() {
		instawp_get_source_site_detail();
	}

	/**
	 * default sync settings
	 * @return null
	 */
	public function instawp_set_default_sync_settings() {
		//set default user for sync settings if user empty
		$default_user = InstaWP_Setting::get_option( 'instawp_default_user' );
		if ( empty( $default_user ) ) {
			add_option( 'instawp_default_user', get_current_user_id() );
		}

		$instawp_sync_tab_roles = InstaWP_Setting::get_option( 'instawp_sync_tab_roles' );
		if ( empty( $instawp_sync_tab_roles ) ) {
			$user  = wp_get_current_user();
			$roles = ( array ) $user->roles;
			add_option( 'instawp_sync_tab_roles', $roles );
		}
	}

	/**
	 * Register toolvar for sync status
	 *
	 * @param $wp_admin_bar
	 *
	 * @return null
	 */
	public function instawp_add_sync_status_toolbar_link( $wp_admin_bar ) {
		if ( get_option( 'instawp_is_event_syncing' ) != 1 ) {
			return;
		}
		$args = array(
			'id'    => 'instawp-sync-toolbar',
			'title' => __( 'Recording', 'instawp-connect' ),
			'href'  => admin_url( 'tools.php?page=instawp' ),
			'meta'  => array(
				'class' => 'instawp-sync-status-toolbar',
				'title' => __( 'Recording', 'instawp-connect' )
			)
		);
		$wp_admin_bar->add_node( $args );
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