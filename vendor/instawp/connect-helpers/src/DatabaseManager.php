<?php
namespace InstaWP\Connect\Helpers;

use Exception;

class DatabaseManager {

	public $file;
	public static $query_var = 'instawp-database-manager';
	public static $action = 'instawp_clean_database_manager';

    public function get() {
		$this->clean();

		$file_name = Helper::get_random_string( 10 );
		$token     = md5( $file_name );
		$url       = 'https://github.com/vrana/adminer/releases/download/v5.3.0/adminer-5.3.0-mysql.php';

		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		} else {
			$file = wp_remote_retrieve_body( $response );
		}

		$file = preg_replace(
			'/(namespace\s+Adminer;\s*)/',
			'$1' . "if(!defined('INSTAWP_PLUGIN_DIR')){die;}",
			$file,
			1
		);

		$file_path = self::get_file_path( $file_name );

		try {
			$result = file_put_contents( $file_path, $file, LOCK_EX );
			if ( false === $result ) {
				throw new Exception( esc_html( 'Failed to create the database manager file.' ) );
			}

			$file_arr   = file( $file_path );
			$new_line   = "/* Copyright (c) InstaWP Inc. */\n";
			array_splice( $file_arr, 1, 0, $new_line );
			file_put_contents( $file_path, implode( '', $file_arr ) );

			set_transient( 'instawp_database_manager_login_token', $token, ( 5 * MINUTE_IN_SECONDS ) );
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::$action );
			flush_rewrite_rules();

			$results = [
				'login_url' => add_query_arg( [
					'action'   => 'instawp-database-manager-auto-login',
					'token'    => hash( 'sha256', $token ),
					'template' => base64_encode( $file_name ),
				], admin_url( 'admin-post.php' ) ),
			];
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
		
        return $results;
    }

	public function clean() {
		Helper::clean_file( self::get_directory() );

		flush_rewrite_rules();
		wp_clear_scheduled_hook( self::$action );
	}

	public static function get_directory() {
		return INSTAWP_PLUGIN_DIR . 'includes/database-manager/';
	}

	public static function get_file_path( $file_name ) {
		return self::get_directory() . 'instawp' . $file_name . '.php';
	}

	public static function get_database_manager_url( $file_name ) {
		return home_url( self::$query_var . '/' . $file_name );
	}
}