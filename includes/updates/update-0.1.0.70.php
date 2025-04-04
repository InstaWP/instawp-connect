<?php

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

function instawp_connect_0_1_0_70_migration() {
	$plan_id = get_option( 'instawp_connect_plan_id' );
	if ( ! empty( $plan_id ) ) {
		return;
	}

	$default_plan_id          = INSTAWP_CONNECT_PLAN_ID;
	$default_plan_expire_days = INSTAWP_CONNECT_PLAN_EXPIRE_DAYS;

	if ( defined( 'CONNECT_WHITELABEL' ) && CONNECT_WHITELABEL && defined( 'CONNECT_WHITELABEL_PLAN_DETAILS' ) && is_array( CONNECT_WHITELABEL_PLAN_DETAILS ) ) {
		$default_plan = array_filter( CONNECT_WHITELABEL_PLAN_DETAILS, function ( $plan ) {
			return $plan['default'] === true;
		} );

		if ( ! empty( $default_plan ) ) {
			$default_plan_id          = $default_plan[0]['plan_id'];
			$default_plan_expire_days = $default_plan[0]['trial'];
		}
	}

	update_option( 'instawp_connect_plan_id', $default_plan_id );
	update_option( 'instawp_connect_plan_expire_days', $default_plan_expire_days );

	$api_options = get_option( 'instawp_api_options', array() );
	$connect_id  = isset( $api_options['connect_id'] ) ? $api_options['connect_id'] : '';
	$api_key     = isset( $api_options['api_key'] ) ? $api_options['api_key'] : '';
	$api_url     = isset( $api_options['api_url'] ) ? $api_options['api_url'] : 'https://app.instawp.io';

	if ( ! empty( $connect_id ) && ! empty( $api_key ) ) {
		$args = array(
			'headers'         => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Referer'       => Helper::wp_site_url( '', true ),
			),
			'timeout'         => 60,
			'redirection'     => 10,
			'httpversion'     => '1.1',
			'user-agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'sslverify'       => false,
			'sslverifyhost'   => false,
			'follow_location' => true,
			'max_redirects'   => 10,
			'body'            => wp_json_encode( array(
				'plan_id' => $default_plan_id,
			) ),
		);

		$response = wp_remote_post( "{$api_url}/api/v2/connects/{$connect_id}/subscribe", $args );

		if ( ! is_wp_error( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			$response_body = json_decode( $response_body );

			if ( isset( $response_body->status ) && $response_body->status ) {
				if ( ! get_option( "instawp_connect_plan_{$default_plan_id}_timestamp" ) ) {
					update_option( "instawp_connect_plan_{$default_plan_id}_timestamp", current_time( 'mysql' ) );
				}
			}
		}
	}
}

instawp_connect_0_1_0_70_migration();