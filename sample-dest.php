<?php
set_time_limit( 0 );


if ( ! isset( $api_signature ) || ! isset( $_POST['api_signature'] ) || $api_signature !== $_POST['api_signature'] ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Mismatched api signature.' );
	die();
}

if ( ! isset( $_SERVER['HTTP_X_FILE_RELATIVE_PATH'] ) ) {
    header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Could not find the X-File-Relative-Path header in the request.' );
	die();
}

$level               = 0;
$save_directory_path = dirname( __DIR__ );
while( ! file_exists( $save_directory_path . '/wp-config.php' ) ) {
    $level++;
    $save_directory_path = dirname( __DIR__, $level );
}

$file_relative_path = trim( $_SERVER['HTTP_X_FILE_RELATIVE_PATH'] );
$file_save_path     = $save_directory_path . DIRECTORY_SEPARATOR . $file_relative_path;

$dir = dirname( $file_save_path );
if ( ! file_exists( $dir ) ) {
    mkdir( $dir, 0777, true );
}

$file_input_stream = fopen( 'php://input', 'rb' );
if ( $file_relative_path === 'db.sql' ) {
    $file_stream = fopen( $file_save_path, 'a+b' );
} else {
    $file_stream = fopen( $file_save_path, 'wb' );
}
stream_copy_to_stream( $file_input_stream, $file_stream );

fclose( $file_input_stream );
fclose( $file_stream );