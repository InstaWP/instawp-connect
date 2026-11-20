<?php
namespace InstaWP\Connect\Helpers;

use Exception;

class DatabaseManager {

	public $file;
	public static $query_var = 'instawp-database-manager';
	public static $action = 'instawp_clean_database_manager';

	/** Transient holding the sha256 of the one-time login token. */
	public static $token_key = 'instawp_database_manager_login_token';

	/** Transient holding the sha256 of the per-session Adminer password. */
	public static $password_key = 'instawp_database_manager_password';

	/** sha256 of adminer-5.3.0-mysql.php as published on GitHub. */
	const ADMINER_SHA256 = '56efdc2c2c651eebc815c7d4ae21369da9c095163a54af053c2a34a02f0324ee';

    public function get() {
		$this->clean();

		$file_name = Helper::get_random_string( 10 );
		$token     = Helper::get_random_string( 64 );
		$url       = 'https://github.com/vrana/adminer/releases/download/v5.3.0/adminer-5.3.0-mysql.php';

		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		} elseif ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return [
				'success' => false,
				'message' => 'The database manager could not be downloaded. Please try again.',
			];
		} else {
			$file = wp_remote_retrieve_body( $response );
		}

		if ( ! hash_equals( self::ADMINER_SHA256, hash( 'sha256', $file ) ) ) {
			return [
				'success' => false,
				'message' => 'The database manager download could not be verified.',
			];
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

			set_transient( self::$token_key, hash( 'sha256', $token ), ( 5 * MINUTE_IN_SECONDS ) );
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::$action );
			flush_rewrite_rules();

			$results = [
				'login_url' => add_query_arg( [
					'action'   => 'instawp-database-manager-auto-login',
					'token'    => $token,
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
		delete_transient( self::$token_key );
		delete_transient( self::$password_key );

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