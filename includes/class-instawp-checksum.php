<?php
/**
 * Checksum calculation
 */

if ( ! class_exists( 'INSTAWP_Checksum' ) ) {
	class INSTAWP_Checksum {

		protected static $_instance = null;

		public function __construct() {
			add_filter( 'instawp/filters/process_migration_settings', array( $this, 'update_migration_settings' ), 10, 2 );
		}

		function update_migration_settings( $migrate_settings, $relative_dir ) {

			$migrate_settings['excluded_paths'][] = $relative_dir . '/plugins/instawp-connect';
			$migrate_settings['excluded_paths'][] = $relative_dir . '/plugins/sample';

			return $migrate_settings;
		}

		public function get_wp_plugin_checksum( $plugin_slug = '' ) {

			$api_url  = "https://api.wordpress.org/plugins/info/1.0/{$plugin_slug}.json";
			$response = wp_remote_get( $api_url );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$plugin_data   = json_decode( wp_remote_retrieve_body( $response ), true );
			$download_link = isset( $plugin_data['download_link'] ) ? $plugin_data['download_link'] : '';

			if ( empty( $download_link ) ) {
				return false;
			}

			$temp_dir  = sys_get_temp_dir();
			$temp_file = $temp_dir . $plugin_slug . '-' . uniqid() . '.zip';

			$ch = curl_init( $download_link );
			$fp = fopen( $temp_file, 'wb' );
			curl_setopt( $ch, CURLOPT_FILE, $fp );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 50 );

			if ( curl_exec( $ch ) === false ) {
				curl_close( $ch );
				fclose( $fp );
				@unlink( $temp_file );

				return false;
			}

			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );
			fclose( $fp );

			if ( $http_code !== 200 ) {
				@unlink( $temp_file );

				return false;
			}

			if ( ! file_exists( $temp_file ) ) {
				return false;
			}

			$zip = new ZipArchive();
			$res = $zip->open( $temp_file );

			if ( $res === false ) {
				return false;
			}

			$zip->extractTo( dirname( $temp_file ) );
			$zip->close();

			@unlink( $temp_file );

			$plugin_folder   = $temp_dir . $plugin_slug;
			$plugin_checksum = $this->get_folder_checksum( $plugin_folder );

			$this->delete_directory( $plugin_folder );

			return $plugin_checksum;
		}


		public function get_folder_checksum( $dir ) {
			if ( ! is_dir( $dir ) ) {
				return false;
			}

			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			$hash = hash_init( 'md5' );

			foreach ( $files as $file ) {
				if ( $file->isFile() ) {
					$filePath     = $file->getPathname();
					$relativePath = str_replace( $dir, '', $filePath );
					$fileContent  = file_get_contents( $filePath );
					hash_update( $hash, $relativePath . $fileContent );
				}
			}

			return hash_final( $hash );
		}


		private function delete_directory( $dirPath ) {
			if ( ! is_dir( $dirPath ) ) {
				throw new InvalidArgumentException( "$dirPath must be a directory" );
			}

			if ( substr( $dirPath, strlen( $dirPath ) - 1, 1 ) != '/' ) {
				$dirPath .= '/';
			}

			$files = glob( $dirPath . '*', GLOB_MARK );

			foreach ( $files as $file ) {
				if ( is_dir( $file ) ) {
					$this->delete_directory( $file );
				} else {
					unlink( $file );
				}
			}
			rmdir( $dirPath );
		}


		/**
		 * @return INSTAWP_Checksum
		 */
		public static function instance() {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}
}

INSTAWP_Checksum::instance();