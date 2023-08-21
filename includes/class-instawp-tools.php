<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class InstaWP_Tools {

	public static function write_log( $message = '', $type = 'notice' ) {

		global $instawp_log;

		$instawp_log->WriteLog( $message, $type );
	}

	public static function clean_junk_cache() {
		$home_url_prefix = get_home_url();
		$parse           = parse_url( $home_url_prefix );
		$tmppath         = str_replace( '/', '_', $parse['path'] );
		$home_url_prefix = $parse['host'] . $tmppath;
		$path            = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . InstaWP_Setting::get_backupdir();
		$handler         = opendir( $path );
		if ( $handler === false ) {
			return;
		}
		while ( ( $filename = readdir( $handler ) ) !== false ) {
			/*if(is_dir($path.DIRECTORY_SEPARATOR.$filename) && preg_match('#temp-'.$home_url_prefix.'_'.'#',$filename))
			{
				InstaWP_Tools::deldir($path.DIRECTORY_SEPARATOR.$filename,'',true);
			}
			if(is_dir($path.DIRECTORY_SEPARATOR.$filename) && preg_match('#temp-'.'#',$filename))
			{
				InstaWP_Tools::deldir($path.DIRECTORY_SEPARATOR.$filename,'',true);
			}*/
			if ( preg_match( '#pclzip-.*\.tmp#', $filename ) ) {
				@unlink( $path . DIRECTORY_SEPARATOR . $filename );
			}
			if ( preg_match( '#pclzip-.*\.gz#', $filename ) ) {
				@unlink( $path . DIRECTORY_SEPARATOR . $filename );
			}
		}
		@closedir( $handler );
	}

	public static function deldir( $path, $exclude = '', $flag = false ) {
		if ( ! is_dir( $path ) ) {
			return;
		}
		$handler = opendir( $path );
		if ( empty( $handler ) ) {
			return;
		}
		while ( ( $filename = readdir( $handler ) ) !== false ) {
			if ( $filename != "." && $filename != ".." ) {
				if ( is_dir( $path . DIRECTORY_SEPARATOR . $filename ) ) {
					if ( empty( $exclude ) || InstaWP_Tools::regex_match( $exclude['directory'], $path . DIRECTORY_SEPARATOR . $filename, 0 ) ) {
						self::deldir( $path . DIRECTORY_SEPARATOR . $filename, $exclude, $flag );
						@rmdir( $path . DIRECTORY_SEPARATOR . $filename );
					}
				} else {
					if ( empty( $exclude ) || InstaWP_Tools::regex_match( $exclude['file'], $path . DIRECTORY_SEPARATOR . $filename, 0 ) ) {
						@unlink( $path . DIRECTORY_SEPARATOR . $filename );
					}
				}
			}
		}
		if ( $handler ) {
			@closedir( $handler );
		}
		if ( $flag ) {
			@rmdir( $path );
		}
	}

	public static function regex_match( $regex_array, $string, $mode ) {
		if ( empty( $regex_array ) ) {
			return true;
		}

		if ( $mode == 0 ) {
			foreach ( $regex_array as $regex ) {
				if ( preg_match( $regex, $string ) ) {
					return false;
				}
			}

			return true;
		}

		if ( $mode == 1 ) {
			foreach ( $regex_array as $regex ) {
				if ( preg_match( $regex, $string ) ) {
					return true;
				}
			}

			return false;
		}

		return true;
	}

	public static function file_put_array( $json, $file ) {
		file_put_contents( $file, json_encode( $json ) );
	}

	public static function file_get_array( $file ) {
		global $instawp_plugin;
		if ( file_exists( $file ) ) {
			$get_file_ret = json_decode( file_get_contents( $file ), true );
			if ( empty( $get_file_ret ) ) {
				sleep( 1 );
				$contents = file_get_contents( $file );
				if ( $contents == false ) {
					if ( $instawp_plugin->restore_data ) {
						$instawp_plugin->restore_data->write_log( 'file_get_contents failed.', 'notice' );
					}
				}
				$get_file_ret = json_decode( $contents, true );
				if ( empty( $get_file_ret ) ) {
					if ( $instawp_plugin->restore_data ) {
						$instawp_plugin->restore_data->write_log( 'Failed to decode restore data file.', 'notice' );
					}
				}

				return $get_file_ret;
			}

			return $get_file_ret;
		} else {

			if ( $instawp_plugin->restore_data ) {
				$instawp_plugin->restore_data->write_log( 'Failed to open restore data file, the file may not exist.', 'notice' );
			}

			return array();
		}
	}

	/**
	 * Returns the random string based on length.
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	public static function get_random_string( $length = 6 ) {
		try {
			$length        = ceil( absint( $length ) / 2 );
			$bytes         = function_exists( 'random_bytes' ) ? random_bytes( $length ) : openssl_random_pseudo_bytes( $length );
			$random_string = bin2hex( $bytes );
		} catch ( Exception $e ) {
			$random_string = substr( hash( 'sha256', wp_generate_uuid4() ), 0, absint( $length ) );
		}

		return $random_string;
	}


	/**
	 * Reset permalink structure
	 *
	 * @param $hard
	 *
	 * @return void
	 */
	public static function instawp_reset_permalink( $hard = true ) {

		global $wp_rewrite;

		if ( get_option( 'permalink_structure' ) == '' ) {
			$wp_rewrite->set_permalink_structure( '/%postname%/' );
		}

		flush_rewrite_rules( $hard );
	}


	/**
	 * Write htaccess rules
	 *
	 * @return false
	 */
	public static function write_htaccess_rule() {

		if ( is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'get_home_path' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		$parent_url  = get_option( 'instawp_sync_parent_url' );
		$backup_type = get_option( 'instawp_site_backup_type' );

		if ( 1 == $backup_type && ! empty( $parent_url ) ) {

			$htaccess_file    = get_home_path() . '.htaccess';
			$htaccess_content = array(
				'## BEGIN InstaWP Connect',
				'<IfModule mod_rewrite.c>',
				'RewriteEngine On',
				'RedirectMatch 301 ^/wp-content/uploads/(.*)$ ' . $parent_url . '/wp-content/uploads/$1',
				'</IfModule>',
				'## END InstaWP Connect',
			);
			$htaccess_content = implode( "\n", $htaccess_content );
			$htaccess_content = $htaccess_content . "\n\n\n" . file_get_contents( $htaccess_file );

			file_put_contents( $htaccess_file, $htaccess_content );
		}

		return false;
	}


	/**
	 * Update Search engine visibility
	 *
	 * @return void
	 */
	public static function update_search_engine_visibility( $should_visible = false ) {
		update_option( 'blog_public', (bool) $should_visible );
	}
}