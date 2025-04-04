<?php

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

function instawp_connect_0_1_0_65_migration() {
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
				'Referer'       => Helper::wp_site_url('', true ),
			),
			'timeout'         => 60,
			'redirection'     => 10,
			'httpversion'     => '1.1',
			'user-agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'sslverify'       => false,
			'sslverifyhost'   => false,
			'follow_location' => true,
			'max_redirects'   => 10,
		);

		$response = wp_remote_get( "{$api_url}/api/v2/connects/{$connect_id}/generate-token", $args );

		if ( ! is_wp_error( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			$response_body = json_decode( $response_body );

			if ( ! empty( $response_body->data->token ) ) {
				$api_options['jwt'] = $response_body->data->token;
				update_option( 'instawp_api_options', $api_options );
			}
		}
	}
}

instawp_connect_0_1_0_65_migration();