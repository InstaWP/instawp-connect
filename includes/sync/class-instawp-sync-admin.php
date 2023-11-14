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


class InstaWP_Sync_Admin {

	protected static $_instance = null;

	public function __construct() {
		add_action( 'init', array( $this, 'get_source_site_detail' ) );
	}

	function listEvents() {
		$InstaWP_db = new InstaWP_DB();
		$rel = $InstaWP_db->getAllEvents();
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
	 * @return InstaWP_Change_event
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}

InstaWP_Sync_Admin::instance();