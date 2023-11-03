<?php

defined( 'ABSPATH' ) || die;

class InstaWP_Curl {

	public $api_key;
	public $response;

	public static function do_curl( $endpoint, $body = array(), $headers = array(), $is_post = true, $api_version = 'v2' ) {
		$api_url = InstaWP_Setting::get_api_domain();
		$api_key = InstaWP_Setting::get_api_key();

		if ( empty( $api_url ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'Invalid or Empty API Domain', 'instawp-connect' ) );
		}

		if ( empty( $api_key ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'Invalid or Empty API Key', 'instawp-connect' ) );
		}

		$api_url = $api_url . '/api/' . $api_version . '/' . $endpoint;
		$headers = wp_parse_args( $headers, [
			'Authorization: Bearer ' . $api_key,
			'Accept: application/json',
			'Content-Type: application/json',
		] );

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
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_USERAGENT      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				CURLOPT_REFERER        => 'InstaWP Connect - General Curl Request',
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

	public function curl( $url, $body, $header = array(), $is_post = true ) {
		$this->set_api_key();

		$res     = array();
		$headers = array(
			'Authorization: Bearer ' . $this->api_key,
			'Accept: application/json',
			'Content-Type: application/json',
		);

		if ( ! empty( $header ) ) {
			array_push( $headers, $header );
		}

		$useragent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$api_method = $is_post ? 'POST' : 'GET';

		$args           = array(
			'method'     => $api_method,
			'body'       => $body,
			'timeout'    => 0,
			'decompress' => false,
			'stream'     => false,
			'filename'   => '',
			'user-agent' => $useragent,
			'headers'    => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json'
			)
		);
		$WP_Http_Curl   = new WP_Http_Curl();
		$this->response = $WP_Http_Curl->request( $url, $args );
		update_option( 'main_curl_1', $this->response );

		if ( $this->response instanceof WP_Error || is_wp_error( $this->response ) ) {
			return array(
				'error'    => true,
				'curl_res' => $this->response,
				'message'  => $this->response->get_error_message(),
			);
		} else if ( ! $this->response ) {
			$res['message']  = '';
			$res['error']    = 1;
			$res['curl_res'] = $this->response;
		} else {
			$respons_arr = (array) json_decode( $this->response['body'] );
			if ( isset( $respons_arr['status'] ) && $respons_arr['status'] == 1 ) {
				$res['error']    = 0;
				$res['curl_res'] = $this->response['body'];
				$res['message']  = $respons_arr['message'];
			} else {
				$res['message']  = $respons_arr['message'];
				$res['error']    = 1;
				$res['curl_res'] = $this->response;
			}
		}

		return $res;
	}

	public function set_api_key() {
		$api_key = InstaWP_Setting::get_api_key();

		if ( ! empty( $api_key ) ) {
			$this->api_key = $api_key;
		} else {
			$res            = [];
			$res['error']   = true;
			$res['message'] = 'API Key Is Required';
			echo json_encode( $res );
			wp_die();
		}
	}
}

global $InstaWP_Curl;
$InstaWP_Curl = new InstaWP_Curl();
