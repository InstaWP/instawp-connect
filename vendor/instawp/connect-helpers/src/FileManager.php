<?php
declare( strict_types=1 );

namespace InstaWP\Connect\Helpers;

use Exception;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\WPConfig;

class FileManager {

	public string $file;
	public static string $query_var = 'instawp-file-manager';

    public function get(): array {
        $results = [];
		
		$this->clean();

		$username  = Helper::get_random_string( 15 );
		$password  = Helper::get_random_string( 20 );
		$file_name = Helper::get_random_string( 20 );
		$token     = md5( $username . '|' . $password . '|' . $file_name );
		$url       = 'https://raw.githubusercontent.com/prasathmani/tinyfilemanager/47359d3f4ee45f88e6881ebe5b004f45a092ded5/tinyfilemanager.php';

		$search  = [
			'Tiny File Manager',
			'CCP Programmers',
			'tinyfilemanager.github.io',
			'FM_SELF_URL',
			'FM_SESSION_ID',
			"'translation.json'",
			'</style>',
		];
		$replace = [
			'InstaWP File Manager',
			'InstaWP',
			'instawp.com',
			'INSTAWP_FILE_MANAGER_SELF_URL',
			'INSTAWP_FILE_MANAGER_SESSION_ID',
			"__DIR__ . '/translation.json'",
			'<?php if ( file_exists( __DIR__ . "/custom.css" ) ) { echo file_get_contents( __DIR__ . "/custom.css" ); } ?></style>',
		];

		$file = file_get_contents( $url );
		$file = str_replace( $search, $replace, $file );
		$file = preg_replace( '!/\*.*?\*/!s', '', $file );

		$file_path        = self::get_file_path( $file_name );
		$file_manager_url = self::get_file_manager_url( $file_name );

		$results = [
			'login_url' => add_query_arg( [
				'action' => 'instawp-file-manager-auto-login',
				'token'  => hash( 'sha256', $token ),
			], admin_url( 'admin-post.php' ) ),
		];

		try {
			$result = file_put_contents( $file_path, $file, LOCK_EX );
			if ( false === $result ) {
				throw new Exception( esc_html( 'Failed to create the file manager file.' ) );
			}

			$file       = file( $file_path );
			$new_line   = "if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) { die; }";
			$first_line = array_shift( $file );
			array_unshift( $file, $new_line );
			array_unshift( $file, $first_line );

			$fp = fopen( $file_path, 'w' );
			fwrite( $fp, implode( '', $file ) );
			fclose( $fp );

			$constants = [
				'INSTAWP_FILE_MANAGER_USERNAME'   => $username,
				'INSTAWP_FILE_MANAGER_PASSWORD'   => $password,
				'INSTAWP_FILE_MANAGER_SELF_URL'   => $file_manager_url,
				'INSTAWP_FILE_MANAGER_SESSION_ID' => 'instawp_file_manager',
			];

			$wp_config = new WPConfig( $constants );
			$wp_config->update();

			set_transient( 'instawp_file_manager_login_token', $token, ( 15 * MINUTE_IN_SECONDS ) );
			update_option( 'instawp_file_manager_name', $file_name );

			flush_rewrite_rules();
			do_action( 'instawp_connect_create_file_manager_task', $file_name );
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
		
        return $results;
    }

	public function clean( $file_name = null ): void {
		$file_name = $file_name ? $file_name : get_option( 'instawp_file_manager_name', '' );

		if ( ! empty( $file_name ) ) {
			$file_path = self::get_file_path( $file_name );
			if ( file_exists( $file_path ) ) {
				@unlink( $file_path );
			}

			$constants = [ 'INSTAWP_FILE_MANAGER_USERNAME', 'INSTAWP_FILE_MANAGER_PASSWORD', 'INSTAWP_FILE_MANAGER_SELF_URL', 'INSTAWP_FILE_MANAGER_SESSION_ID' ];
			$wp_config = new WPConfig( $constants );
			$wp_config->delete();
			
			delete_option( 'instawp_file_manager_name' );
			flush_rewrite_rules();

			do_action( 'instawp_connect_remove_file_manager_task', $file_name );
		}
	}

	public static function get_query_var(): string {
		return self::$query_var;
	}

	public static function get_file_path( $file_name ): string {
		return WP_PLUGIN_DIR . '/instawp-connect/includes/file-manager/instawp' . $file_name . '.php';
	}

	public static function get_file_manager_url( $file_name ): string {
		return home_url( self::$query_var . '/' . $file_name );
	}
}