<?php
declare( strict_types=1 );

namespace InstaWP\Connect\Helpers;

use Exception;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\WPConfig;

class DatabaseManager {

	public string $file;
	public static string $query_var = 'instawp-database-manager';

    public function get(): array {
        $results = [];
		
		$file_db_manager = Helper::get_option( 'instawp_file_db_manager', [] );
		$db_file_name    = Helper::get_args_option( 'db_name', $file_db_manager );
		if ( ! empty( $db_file_name ) ) {
			as_unschedule_all_actions( 'instawp_clean_database_manager', [ $db_file_name ], 'instawp-connect' );

			$file_path = self::get_file_path( $db_file_name );
			if ( file_exists( $file_path ) ) {
				@unlink( $file_path );
			}
		}

		$db_file_name = Helper::get_random_string( 20 );
		$token     = md5( $db_file_name );
		$url       = 'https://github.com/vrana/adminer/releases/download/v4.8.1/adminer-4.8.1-mysql.php';

		$search  = [
			'/\bjs_escape\b/',
			'/\bget_temp_dir\b/',
			'/\bis_ajax\b/',
			'/\bsid\b/',
		];
		$replace = [
			'instawp_js_escape',
			'instawp_get_temp_dir',
			'instawp_is_ajax',
			'instawp_sid',
		];

		$file = file_get_contents( $url );
		$file = preg_replace( $search, $replace, $file );

		$file_path            = self::get_file_path( $db_file_name );
		$database_manager_url = self::get_database_manager_url( $db_file_name );

		$results = [
			'login_url' => add_query_arg( [
				'action' => 'instawp-database-manager-auto-login',
				'token'  => hash( 'sha256', $token ),
			], admin_url( 'admin-post.php' ) ),
		];

		try {
			$result = file_put_contents( $file_path, $file, LOCK_EX );
			if ( false === $result ) {
				throw new Exception( esc_html( 'Failed to create the database manager file.' ) );
			}

			$file       = file( $file_path );
			$new_line   = "if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) { die; }";
			$first_line = array_shift( $file );
			array_unshift( $file, $new_line );
			array_unshift( $file, $first_line );

			$fp = fopen( $file_path, 'w' );
			fwrite( $fp, implode( '', $file ) );
			fclose( $fp );

			set_transient( 'instawp_database_manager_login_token', $token, ( 15 * MINUTE_IN_SECONDS ) );
			$file_db = [
				'db_name' => $db_file_name,
			];

			$file_db_manager = Helper::get_option( 'instawp_file_db_manager', [] );
			$file_name       = Helper::get_args_option( 'file_name', $file_db_manager );
			if ( $file_name ) {
				$file_db['file_name'] = $file_name;
			}
			update_option( 'instawp_file_db_manager', $file_db );

			flush_rewrite_rules();
			as_schedule_single_action( time() + DAY_IN_SECONDS, 'instawp_clean_database_manager', [ $db_file_name ], 'instawp-connect', false, 5 );
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
		
        return $results;
    }

	public function clean( $db_file_name = null ): void {
		$file_db_manager = Helper::get_option( 'instawp_file_db_manager', [] );
		$db_file_name    = $db_file_name ? $db_file_name : Helper::get_args_option( 'db_name', $file_db_manager );

		if ( ! empty( $db_file_name ) ) {
			$file_path = self::get_file_path( $db_file_name );
			if ( file_exists( $file_path ) ) {
				@unlink( $file_path );
			}

			if ( isset( $file_db_manager['db_name'] ) ) {
				unset( $file_db_manager['db_name'] );
			}
			
			if ( count( $file_db_manager ) < 1 ) {
				delete_option( 'instawp_file_db_manager' );
			} else {
				update_option( 'instawp_file_db_manager', $file_db_manager );
			}
			flush_rewrite_rules();

			do_action( 'instawp_connect_remove_database_manager_task', $db_file_name );
		}
	}

	public static function get_query_var(): string {
		return self::$query_var;
	}

	public static function get_file_path( $db_file_name ): string {
		return WP_PLUGIN_DIR . '/instawp-connect/includes/database-manager/instawp' . $db_file_name . '.php';
	}

	public static function get_database_manager_url( $db_file_name ): string {
		return home_url( self::$query_var . '/' . $db_file_name );
	}
}