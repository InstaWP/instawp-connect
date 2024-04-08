<?php

namespace InstaWP\Connect\Helpers;

class Curl {

	public static function do_curl( $endpoint, $body = array(), $headers = array(), $is_post = true, $api_version = 'v2', $api_key = '' ) {

		$api_url = Helper::get_api_domain();
		if ( empty( $api_url ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Invalid or Empty API Domain', 'instawp-connect' ),
			);
		}

		if ( empty( $api_key ) ) {
			$api_key = Helper::get_api_key();
		}

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Invalid or Empty API Key', 'instawp-connect' ),
			);
		}

		$api_url = $api_url . '/api/' . $api_version . '/' . $endpoint;
		$headers = wp_parse_args( $headers, array(
			'Authorization' => 'Bearer ' . $api_key,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
			'Referer'       => site_url(),
		) );

		if ( $is_post === 'patch' ) {
			$api_method = 'PATCH';
		} elseif ( $is_post === 'put' ) {
			$api_method = 'PUT';
		} else {
			$api_method = $is_post ? 'POST' : 'GET';
		}

		$args = array(
			'method'          => $api_method,
			'headers'         => $headers,
			'timeout'         => 60,
			'redirection'     => 10,
			'httpversion'     => '1.1',
			'user-agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'sslverify'       => false,
			'sslverifyhost'   => false,
			'follow_location' => true,
			'max_redirects'   => 10,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $api_url, $args );

		if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
			error_log( 'API URL - ' . $api_url );
			error_log( 'API ARGS - ' . wp_json_encode( $body ) );
			error_log( 'API HEADERS - ' . wp_json_encode( $headers ) );
			error_log( 'API Response - ' . wp_json_encode( $response ) );
		}

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
				error_log( 'Error - ' . $error_message );
			}

			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_body    = wp_remote_retrieve_body( $response );
		$api_response     = json_decode( $response_body, true );
		$response_status  = Helper::get_args_option( 'status', $api_response );
		$response_data    = Helper::get_args_option( 'data', $api_response, array() );
		$response_message = Helper::get_args_option( 'message', $api_response );

		return array(
			'success' => $response_status,
			'message' => $response_message,
			'data'    => $response_data,
			'code'    => $response_code,
		);
	}
}