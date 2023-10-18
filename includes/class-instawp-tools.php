<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class InstaWP_Tools {

	public static function write_log( $message = '', $type = 'notice' ) {

		global $instawp_log;

		$instawp_log->WriteLog( $message, $type );
	}

	public static function create_user( $user_details ) {

		global $wpdb;

		foreach ( $user_details as $user_detail ) {

			if ( ! isset( $user_detail['username'] ) || ! isset( $user_detail['email'] ) || ! isset( $user_detail['password'] ) ) {
				continue;
			}

			if ( username_exists( $user_detail['username'] ) == null && email_exists( $user_detail['email'] ) == false && ! empty( $user_detail['password'] ) ) {

				// Create the new user
				$user_id = wp_create_user( $user_detail['username'], $user_detail['password'], $user_detail['email'] );

				// Get current user object
				$user = get_user_by( 'id', $user_id );

				// Remove role
				$user->remove_role( 'subscriber' );

				// Add role
				$user->add_role( 'administrator' );
			} elseif ( email_exists( $user_detail['email'] ) || username_exists( $user_detail['username'] ) ) {
				$user = get_user_by( 'email', $user_detail['email'] );

				if ( $user !== false ) {
					$wpdb->update(
						$wpdb->users,
						[
							'user_login' => $user_detail['username'],
							'user_pass'  => md5( $user_detail['password'] ),
							'user_email' => $user_detail['email'],
						],
						[ 'ID' => $user->ID ]
					);

					$user->remove_role( 'subscriber' );

					// Add role
					$user->add_role( 'administrator' );
				}
			}
		}
	}

	public static function create_instawpbackups_dir( $instawpbackups_dir = '' ) {

		if ( empty( $instawpbackups_dir ) ) {
			$instawpbackups_dir = WP_CONTENT_DIR . '/' . INSTAWP_DEFAULT_BACKUP_DIR;
		}

		if ( ! is_dir( $instawpbackups_dir ) ) {
			if ( mkdir( $instawpbackups_dir, 0777, true ) ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	public static function clean_instawpbackups_dir( $instawpbackups_dir = '' ) {

		if ( empty( $instawpbackups_dir ) ) {
			$instawpbackups_dir = WP_CONTENT_DIR . '/' . INSTAWP_DEFAULT_BACKUP_DIR;
		}

		if ( ! is_dir( $instawpbackups_dir ) || ! $instawpbackups_dir_handle = opendir( $instawpbackups_dir ) ) {
			return false;
		}


		while ( false !== ( $file = readdir( $instawpbackups_dir_handle ) ) ) {
			if ( $file !== '.' && $file !== '..' ) {
				$file_path = $instawpbackups_dir . DIRECTORY_SEPARATOR . $file;
				if ( is_dir( $file_path ) ) {
					self::clean_instawpbackups_dir( $file_path );
				} else {
					unlink( $file_path );
				}
			}
		}

		closedir( $instawpbackups_dir_handle );

//		rmdir( $instawpbackups_dir );

		return true;
	}

	public static function generate_serve_file( $migrate_key, $api_signature, $migrate_settings = [], $serve_file_dir = '' ) {

		$migrate_settings  = is_array( $migrate_settings ) ? $migrate_settings : [];
		$sample_serve_file = fopen( INSTAWP_PLUGIN_DIR . '/sample-serve.php', 'rb' );
		$serve_file_dir    = empty( $serve_file_dir ) ? WP_CONTENT_DIR . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR : $serve_file_dir;
		$serve_file_path   = $serve_file_dir . DIRECTORY_SEPARATOR . $migrate_key . '.php';
		$serve_file        = fopen( $serve_file_path, 'wb' );
		$line_number       = 1;

		// Process migration settings like active plugins/themes only etc
		$migrate_settings = instawp()->tools::process_migration_settings( $migrate_settings );

		while ( ( $line = fgets( $sample_serve_file ) ) !== false ) {

			// Add api signature
			if ( $line_number === 4 ) {
				fputs( $serve_file, '$api_signature = "' . $api_signature . '";' . "\n" );
				fputs( $serve_file, '$migrate_settings = \'' . serialize( $migrate_settings ) . '\';' . "\n" );
				fputs( $serve_file, '$db_host = "' . DB_HOST . '";' . "\n" );
				fputs( $serve_file, '$db_username = "' . DB_USER . '";' . "\n" );
				fputs( $serve_file, '$db_password = "' . DB_PASSWORD . '";' . "\n" );
				fputs( $serve_file, '$db_name = "' . DB_NAME . '";' . "\n" );
			}

			fputs( $serve_file, $line );

			$line_number ++;
		}

		fclose( $serve_file );
		fclose( $sample_serve_file );

		if ( $serve_file_dir === ABSPATH ) {
			return site_url( $migrate_key . '.php' );
		}

		return content_url( INSTAWP_DEFAULT_BACKUP_DIR . '/' . $migrate_key . '.php' );
	}

	public static function is_serve_file_accessible( $serve_file_url ) {

		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL            => $serve_file_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 5,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST'
		) );
		curl_exec( $curl );
		$status_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		return $status_code !== 403;
	}

	public static function process_migration_settings( $migrate_settings = [] ) {

		$options      = $migrate_settings['options'] ?? [];
		$relative_dir = str_replace( ABSPATH, '', WP_CONTENT_DIR );

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-includes/plugin.php';
		}

		if ( in_array( 'active_plugins_only', $options ) ) {
			foreach ( get_plugins() as $plugin_slug => $plugin_info ) {
				if ( ! is_plugin_active( $plugin_slug ) ) {
					$migrate_settings['excluded_paths'][] = $relative_dir . '/plugins/' . strstr( $plugin_slug, '/', true );
				}
			}
		}

		if ( in_array( 'active_themes_only', $options ) ) {
			$active_theme_stylesheet = wp_get_theme()->get_stylesheet();
			foreach ( wp_get_themes() as $theme_slug => $theme_info ) {
				if ( $theme_info->get_stylesheet() !== $active_theme_stylesheet ) {
					$migrate_settings['excluded_paths'][] = $relative_dir . '/themes/' . $theme_slug;
				}
			}
		}


		if ( in_array( 'skip_media_folder', $options ) ) {

			$upload_dir      = wp_upload_dir();
			$upload_base_dir = $upload_dir['basedir'] ?? '';

			if ( ! empty( $upload_base_dir ) ) {
				$migrate_settings['excluded_paths'][] = str_replace( ABSPATH, '', $upload_base_dir );
			}
		}

		return apply_filters( 'INSTAWP_CONNECT/Filters/process_migration_settings', $migrate_settings );
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

		$migration_settings = InstaWP_Setting::get_option( 'instawp_migration_settings', [] );
		$parent_domain      = InstaWP_Setting::get_args_option( 'parent_domain', $migration_settings );
		$skip_media_folder  = InstaWP_Setting::get_args_option( 'skip_media_folder', $migration_settings, false );

//		echo "<pre>";
//		print_r( [ $migration_settings, $parent_domain, $skip_media_folder, ( $skip_media_folder && ! empty( $parent_domain ) ) ] );
//		echo "</pre>";

		if ( $skip_media_folder && ! empty( $parent_domain ) ) {

			$htaccess_file    = get_home_path() . '.htaccess';
			$htaccess_content = array(
				'## BEGIN InstaWP Connect',
				'<IfModule mod_rewrite.c>',
				'RewriteEngine On',
				'RedirectMatch 301 ^/wp-content/uploads/(.*)$ ' . $parent_domain . '/wp-content/uploads/$1',
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