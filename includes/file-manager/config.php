<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

$use_auth = true;
$auth_users = [
    INSTAWP_FILE_MANAGER_USERNAME => password_hash( INSTAWP_FILE_MANAGER_PASSWORD, PASSWORD_DEFAULT ),
];

$wp_timezone_string = wp_timezone_string();
if ( strpos( $wp_timezone_string, '/' ) !== false ) {
    $default_timezone = $wp_timezone_string;
}

$wp_date_format = get_option( 'date_format' );
$wp_time_format = get_option( 'time_format' );
if ( $wp_date_format && $wp_time_format ) {
    $datetime_format = $wp_date_format . ' ' . $wp_time_format;
}

$wp_favicon_url = get_site_icon_url();
if ( $wp_favicon_url ) {
    $favicon_path = $wp_favicon_url;
}

if ( defined( 'ABSPATH' ) ) {
    $root_path = ABSPATH;
}

$exclude_items = [
    'instawp_exclude_tables_rows.json'
];

$global_readonly = false;
$edit_files = true;