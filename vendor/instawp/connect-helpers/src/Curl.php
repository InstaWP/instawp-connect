<?php

namespace InstaWP\Connect\Helpers;

class Curl {

	public static function do_curl( $endpoint, $body = array(), $headers = array(), $method = 'POST', $api_version = 'v2', $api_key = '', $api_domain = '' ) {
		$api_url = ! empty( $api_domain ) ? $api_domain : Helper::get_api_domain();

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

		if ( $api_version !== null ) {
			$api_url = $api_url . '/api/' . $api_version . '/' . $endpoint;
		} else {
			$api_url = $api_url . '/api/' . $endpoint;
		}

		$headers = wp_parse_args(
			$headers,
			array(
				'Authorization' => 'Bearer ' . $api_key,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Referer'       => Helper::wp_site_url( '', true ),
			)
		);

		if ( is_bool( $method ) ) {
			$method = $method ? 'POST' : 'GET';
		} else {
			$method = strtoupper( $method );
		}

		$timeout = 60;

		// Set timeout based on max_execution_time.
		if ( function_exists( 'ini_get' ) ) {
			$timeout = (int) @ini_get( 'max_execution_time' );
			if ( $timeout >= 300 ) {
				$timeout = 290;
			} elseif ( $timeout >= 120 ) {
				$timeout = 110;
			} elseif ( $timeout >= 90 ) {
				$timeout = 80;
			} else {
				$timeout = 60;
			}
		}

		$args = array(
			'method'          => $method,
			'headers'         => $headers,
			'timeout'         => $timeout,
			'redirection'     => 10,
			'httpversion'     => '1.1',
			'user-agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'sslverify'       => false,
			'sslverifyhost'   => false,
			'follow_location' => true,
			'max_redirects'   => 10,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = is_array( $body ) ? wp_json_encode( $body ) : $body;
		}

		$response = wp_remote_request( $api_url, $args );

		if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
			error_log( 'API URL - ' . $api_url );
			error_log( 'API ARGS - ' . is_array( $body ) ? wp_json_encode( $body ) : $body );
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
