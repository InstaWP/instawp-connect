<?php
/**
 * Class for all hooks
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'InstaWP_WhiteLabel' ) ) {
	class InstaWP_WhiteLabel {

		public function __construct() {
			add_filter( 'instawp/filters/plugin_nav_items', array( $this, 'nav_menu_items' ), 9999 );
			add_action( 'admin_init', array( $this, 'disconnect_whitelabel_site' ) );
		}

		public function nav_menu_items( $menu_items ) {
			if ( ! instawp_is_connect_whitelabelled() ) {
				return $menu_items;
			}

			$menu_items = array(
				'site-management' => array(
					'label' => __( 'Site Management', 'instawp-connect' ),
					'icon'  => '<svg class="mr-2" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 4C5 3.44772 4.55228 3 4 3C3.44772 3 3 3.44772 3 4V11.2676C2.4022 11.6134 2 12.2597 2 13C2 13.7403 2.4022 14.3866 3 14.7324V16C3 16.5523 3.44772 17 4 17C4.55228 17 5 16.5523 5 16V14.7324C5.5978 14.3866 6 13.7403 6 13C6 12.2597 5.5978 11.6134 5 11.2676V4Z" fill="#005E54"/><path d="M11 4C11 3.44772 10.5523 3 10 3C9.44772 3 9 3.44772 9 4V5.26756C8.4022 5.61337 8 6.25972 8 7C8 7.74028 8.4022 8.38663 9 8.73244V16C9 16.5523 9.44772 17 10 17C10.5523 17 11 16.5523 11 16V8.73244C11.5978 8.38663 12 7.74028 12 7C12 6.25972 11.5978 5.61337 11 5.26756V4Z" fill="#005E54"/><path d="M16 3C16.5523 3 17 3.44772 17 4V11.2676C17.5978 11.6134 18 12.2597 18 13C18 13.7403 17.5978 14.3866 17 14.7324V16C17 16.5523 16.5523 17 16 17C15.4477 17 15 16.5523 15 16V14.7324C14.4022 14.3866 14 13.7403 14 13C14 12.2597 14.4022 11.6134 15 11.2676V4C15 3.44772 15.4477 3 16 3Z" fill="#005E54"/></svg>',
				),
			);

			if ( isset( $_REQUEST['internal'] ) && 1 === intval( $_REQUEST['internal'] ) ) {
				$menu_items['settings'] = array(
					'label' => __( 'Settings', 'instawp-connect' ),
					'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.34035 1.8539C8.99923 0.448767 7.00087 0.448766 6.65975 1.8539C6.43939 2.76159 5.39945 3.19235 4.6018 2.70633C3.36701 1.95396 1.95396 3.36701 2.70633 4.6018C3.19235 5.39945 2.76159 6.43939 1.8539 6.65975C0.448766 7.00087 0.448767 8.99923 1.8539 9.34035C2.76159 9.56071 3.19235 10.6006 2.70633 11.3983C1.95396 12.6331 3.36701 14.0461 4.6018 13.2938C5.39945 12.8077 6.43939 13.2385 6.65975 14.1462C7.00087 15.5513 8.99923 15.5513 9.34035 14.1462C9.56071 13.2385 10.6006 12.8077 11.3983 13.2938C12.6331 14.0461 14.0461 12.6331 13.2938 11.3983C12.8077 10.6006 13.2385 9.56071 14.1462 9.34035C15.5513 8.99923 15.5513 7.00087 14.1462 6.65975C13.2385 6.43939 12.8077 5.39945 13.2938 4.6018C14.0461 3.36701 12.6331 1.95396 11.3983 2.70633C10.6006 3.19235 9.56071 2.76159 9.34035 1.8539ZM8.00005 10.7C9.49122 10.7 10.7 9.49122 10.7 8.00005C10.7 6.50888 9.49122 5.30005 8.00005 5.30005C6.50888 5.30005 5.30005 6.50888 5.30005 8.00005C5.30005 9.49122 6.50888 10.7 8.00005 10.7Z"/> </svg>',
				);
			}

			return $menu_items;
		}

		public function disconnect_whitelabel_site() {
			if ( ! instawp_is_connect_whitelabelled() ) {
				return;
			}

			$connect_plan = Helper::get_connect_plan();
			if ( empty( $connect_plan ) ) {
				return;
			}

			$current_plan = array_filter( CONNECT_WHITELABEL_PLAN_DETAILS, function ( $plan ) use ( $connect_plan ) {
				return $plan['plan_id'] === $connect_plan['plan_id'];
			} );
			
			if ( empty( $current_plan ) ) {
				return;
			}

			$connect_id = instawp_get_connect_id();
			if ( empty( $connect_id ) ) {
				return;
			}

			$trial = (int) $current_plan[0]['trial'];
			if ( $trial <= 0 ) {
				return;
			}

			$plan_activated_date = new DateTime( $connect_plan['plan_timestamp'] );
			$today_date          = new DateTime( current_time( 'mysql' ) );
			$diff                = $today_date->diff( $plan_activated_date );
			$remaining_days      = $trial - $diff->days;

			if ( $remaining_days <= 0 ) {
				$api_response = Curl::do_curl( "connects/{$connect_id}/delete", array(), array(), 'DELETE' );

				if ( empty( $api_response['success'] ) ) {
					error_log( 'Error disconnecting site: ' . $api_response['message'] );
				} else {
					Helper::set_connect_plan_id( 0 );
				}
			}
		}
	}
}

new InstaWP_WhiteLabel();
