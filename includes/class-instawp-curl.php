<?php

defined( 'ABSPATH' ) || die;

class InstaWP_Curl {
	public static function do_curl( $endpoint, $body = array(), $headers = array(), $is_post = true, $api_version = 'v2' ) {

		$connect_options = InstaWP_Setting::get_option( 'instawp_api_options', array() );

		if ( empty( $api_url = InstaWP_Setting::get_api_domain() ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'Invalid or Empty API Domain', 'instawp-connect' ) );
		}

		if ( empty( $api_key = InstaWP_Setting::get_args_option( 'api_key', $connect_options ) ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'Invalid or Empty API Key', 'instawp-connect' ) );
		}

		$api_url = $api_url . '/api/' . $api_version . '/' . $endpoint;
		$headers = wp_parse_args( $headers,
			array(
				'Authorization: Bearer ' . $api_key,
				'Accept: application/json',
				'Content-Type: application/json',
			)
		);

		if ( is_string( $is_post ) && $is_post == 'patch' ) {
			$api_method = 'PATCH';
		} else {
			$api_method = $is_post ? 'POST' : 'GET';
		}

		$curl = curl_init();

		curl_setopt_array( $curl,
			array(
				CURLOPT_URL            => $api_url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => $api_method,
				CURLOPT_POSTFIELDS     => json_encode( $body ),
				CURLOPT_HTTPHEADER     => $headers,
			)
		);
		$api_response = curl_exec( $curl );
		curl_close( $curl );

		if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
			error_log( 'API URL - ' . $api_url );
			error_log( 'API ARGS - ' . json_encode( $body ) );
			error_log( 'API Response - ' . $api_response );
		}

		$api_response     = json_decode( $api_response, true );
		$response_status  = InstaWP_Setting::get_args_option( 'status', $api_response );
		$response_data    = InstaWP_Setting::get_args_option( 'data', $api_response, array() );
		$response_message = InstaWP_Setting::get_args_option( 'message', $api_response );

		return array( 'success' => $response_status, 'message' => $response_message, 'data' => $response_data );
	}
}

global $InstaWP_Curl;
$InstaWP_Curl = new InstaWP_Curl();
