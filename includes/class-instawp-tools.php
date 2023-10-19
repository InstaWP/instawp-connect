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

	public static function generate_destination_file( $migrate_key, $api_signature, $dest_file_dir = '' ) {

		$sample_dest_file = fopen( INSTAWP_PLUGIN_DIR . '/sample-dest.php', 'rb' );
		$dest_file_dir    = empty( $dest_file_dir ) ? WP_CONTENT_DIR . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR : $dest_file_dir;
		$dest_file_path   = $dest_file_dir . DIRECTORY_SEPARATOR . $migrate_key . '.php';
		$dest_file        = fopen( $dest_file_path, 'wb' );
		$line_number       = 1;

		while ( ( $line = fgets( $sample_dest_file ) ) !== false ) {

			// Add api signature
			if ( $line_number === 4 ) {
				fputs( $dest_file, '$api_signature = "' . $api_signature . '";' . "\n" );
				fputs( $dest_file, '$db_host = "' . DB_HOST . '";' . "\n" );
				fputs( $dest_file, '$db_username = "' . DB_USER . '";' . "\n" );
				fputs( $dest_file, '$db_password = "' . DB_PASSWORD . '";' . "\n" );
				fputs( $dest_file, '$db_name = "' . DB_NAME . '";' . "\n" );
				fputs( $dest_file, '$db_charset = "' . DB_CHARSET . '";' . "\n" );
				fputs( $dest_file, '$db_collate = "' . DB_COLLATE . '";' . "\n" );

				if ( defined( 'WP_SITEURL' ) ) {
					fputs( $dest_file, '$site_url = "' . WP_SITEURL . '";' . "\n" );
				}

				if ( defined( 'WP_HOME' ) ) {
					fputs( $dest_file, '$home_url = "' . WP_HOME . '";' . "\n" );
				}
			}

			fputs( $dest_file, $line );

			$line_number ++;
		}

		fclose( $dest_file );
		fclose( $sample_dest_file );

		if ( $dest_file_dir === ABSPATH ) {
			return site_url( $migrate_key . '.php' );
		}

		return content_url( INSTAWP_DEFAULT_BACKUP_DIR . '/' . $migrate_key . '.php' );
	}

	public static function is_migrate_file_accessible( $file_url ) {

		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL            => $file_url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 5,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
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

	public static function get_unsupported_active_plugins() {

		$active_plugins             = InstaWP_Setting::get_option( 'active_plugins', [] );
		$unsupported_plugins        = InstaWP_Setting::get_unsupported_plugins();
		$unsupported_active_plugins = [];

		foreach ( $unsupported_plugins as $plugin_data ) {
			if ( isset( $plugin_data['slug'] ) && in_array( $plugin_data['slug'], $active_plugins ) ) {
				$unsupported_active_plugins[] = $plugin_data;
			}
		}

		return $unsupported_active_plugins;
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

	/**
	 * Auto login page HTML code.
	 */
	public static function auto_login_page( $fields, $url, $title ) {
		?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="author" content="InstaWP">
            <meta name="robots" content="noindex, nofollow">
            <meta name="googlebot" content="noindex">
            <link href="https://cdn.jsdelivr.net/npm/reset-css@5.0.1/reset.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
			<?php wp_site_icon(); ?>
            <title><?php printf( __( 'Launch %s', 'instawp-connect' ), esc_html( $title ) ); ?></title>
            <style>
                body {
                    background-color: #f3f4f6;
                    width: calc(100vw + 0px);
                    overflow-x: hidden;
                    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, Noto Sans, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", Segoe UI Symbol, "Noto Color Emoji";
                }

                .instawp-auto-login-container {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }

                .instawp-logo svg {
                    width: 100%;
                }

                .instawp-details {
                    padding: 5rem;
                    border-radius: 0.5rem;
                    max-width: 42rem;
                    box-shadow: 0 0 #0000, 0 0 #0000, 0 4px 6px -1px rgb(0 0 0 / .1), 0 2px 4px -2px rgb(0 0 0 / .1);
                    background-color: rgb(255 255 255 / 1);
                    margin-top: 1.5rem;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 2.75rem;
                }

                .instawp-details-title {
                    font-weight: 600;
                    text-align: center;
                    line-height: 1.75;
                }

                .instawp-details-info {
                    text-align: center;
                    font-size: 1.125rem;
                    line-height: 1.75rem;
                    font-size: 1rem;
                }

                .instawp-details-info svg {
                    height: 1.5rem;
                    width: 1.5rem;
                    display: inline;
                    vertical-align: middle;
                    animation: spin 1s linear infinite;
                }

                @keyframes spin {
                    100% {
                        transform: rotate(360deg);
                    }
                }
            </style>
        </head>
        <body>
        <div class="instawp-auto-login-container">
            <div class="instawp-logo">
                <img class="instawp-logo-image" src="https://app.instawp.io/images/insta-logo-image.svg" alt="InstaWP Logo">
            </div>
            <div class="instawp-details">
                <h3 class="instawp-details-title"><?php echo esc_url( site_url() ); ?></h3>
                <p class="instawp-details-info">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 animate-spin inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    You are being redirected to the <?php echo esc_html( $title ); ?>.
                </p>
            </div>
        </div>
        <form id="instawp-auto-login" action="<?php echo esc_url( $url ); ?>" method="POST">
			<?php echo $fields; ?>
        </form>
        <script type="text/javascript">
            window.onload = function () {
                setTimeout(function () {
                    document.getElementById('instawp-auto-login').submit();
                }, 2000);
            }
        </script>
        </body>
        </html>
		<?php
	}
}