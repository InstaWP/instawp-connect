<?php
/**
 * Standalone search-replace API endpoint.
 *
 * Performs serialization-aware search-replace on SQL dump files.
 * Runs without WordPress — usable by InstaWP client app or cloud app.
 *
 * Authentication: X-IWP-API-KEY header validated against stored key.
 * Input: POST request with JSON body containing input_file, output_file, replacements.
 * Output: JSON response with status, message, and statistics.
 *
 * @package InstaWP
 */

set_time_limit( 0 );
error_reporting( 0 );

// Only accept POST requests.
if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
	iwp_sr_send_error( 'Only POST requests are allowed.', 405 );
}

// Validate API key.
$api_key = isset( $_SERVER['HTTP_X_IWP_API_KEY'] ) ? $_SERVER['HTTP_X_IWP_API_KEY'] : '';
if ( empty( $api_key ) ) {
	iwp_sr_send_error( 'Missing X-IWP-API-KEY header.', 401 );
}

// Load the stored API key from the key file.
$key_file = __DIR__ . DIRECTORY_SEPARATOR . '.api-key';
if ( ! file_exists( $key_file ) || ! is_readable( $key_file ) ) {
	iwp_sr_send_error( 'API key not configured on server.', 500 );
}

$stored_key = trim( file_get_contents( $key_file ) );
if ( empty( $stored_key ) ) {
	iwp_sr_send_error( 'API key not configured on server.', 500 );
}

if ( ! hash_equals( $stored_key, $api_key ) ) {
	iwp_sr_send_error( 'Invalid API key.', 403 );
}

// Parse JSON input.
$raw_input = file_get_contents( 'php://input' );
$input     = json_decode( $raw_input, true );

if ( null === $input || JSON_ERROR_NONE !== json_last_error() ) {
	iwp_sr_send_error( 'Invalid JSON input: ' . json_last_error_msg(), 400 );
}

// Validate required fields.
if ( empty( $input['input_file'] ) ) {
	iwp_sr_send_error( 'Missing required field: input_file', 400 );
}

if ( empty( $input['output_file'] ) ) {
	iwp_sr_send_error( 'Missing required field: output_file', 400 );
}

if ( empty( $input['replacements'] ) || ! is_array( $input['replacements'] ) ) {
	iwp_sr_send_error( 'Missing or invalid required field: replacements (must be a non-empty object)', 400 );
}

$input_file   = $input['input_file'];
$output_file  = $input['output_file'];
$replacements = $input['replacements'];

// Security: Validate file paths to prevent path traversal.
$input_real = realpath( $input_file );
if ( false === $input_real ) {
	iwp_sr_send_error( 'Input file does not exist: ' . $input_file, 400 );
}

// Ensure output directory exists and is writable.
$output_dir = dirname( $output_file );
if ( ! is_dir( $output_dir ) ) {
	iwp_sr_send_error( 'Output directory does not exist: ' . $output_dir, 400 );
}

if ( ! is_writable( $output_dir ) ) {
	iwp_sr_send_error( 'Output directory is not writable: ' . $output_dir, 400 );
}

// Prevent path traversal in output file.
$output_real_dir = realpath( $output_dir );
if ( false === $output_real_dir ) {
	iwp_sr_send_error( 'Invalid output directory path.', 400 );
}

// Load search-replace functions.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

// Perform search-replace.
$result = iwp_search_replace_in_sql_file( $input_real, $output_file, $replacements );

// Send response.
if ( $result['success'] ) {
	iwp_sr_send_response(
		array(
			'success'            => true,
			'message'            => $result['message'],
			'statements_count'   => $result['statements'],
			'replacements_count' => $result['replacements'],
		)
	);
} else {
	iwp_sr_send_error( $result['message'], 500 );
}

/**
 * Sends a JSON success response and exits.
 *
 * @param array $data Response data.
 */
function iwp_sr_send_response( $data ) {
	header( 'Content-Type: application/json' );
	http_response_code( 200 );
	echo json_encode( $data );
	exit;
}

/**
 * Sends a JSON error response and exits.
 *
 * @param string $message Error message.
 * @param int    $code    HTTP status code.
 */
function iwp_sr_send_error( $message, $code = 400 ) {
	header( 'Content-Type: application/json' );
	http_response_code( $code );
	echo json_encode(
		array(
			'success' => false,
			'message' => $message,
		)
	);
	exit;
}
