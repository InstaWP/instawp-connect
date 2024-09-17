<?php

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;
use phpseclib3\Net\SFTP;

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class InstaWP_Tools {

	public static function send_migration_log( $migrate_id, $label = '', $description = '', $payload = array() ) {

		if ( ! is_int( $migrate_id ) ) {
			return new WP_Error( 'invalid_migration_id', esc_html__( 'Invalid Migration ID', 'instawp-connect' ) );
		}

		$log_args             = array(
			'migrate_id'  => $migrate_id,
			'url'         => site_url(),
			'type'        => 'plugin',
			'label'       => $label,
			'description' => $description,
			'payload'     => $payload,
		);
		$log_response         = Curl::do_curl( 'migrates-v3/log', $log_args );
		$log_response_success = (bool) Helper::get_args_option( 'success', $log_response, false );
		$log_response_message = Helper::get_args_option( 'message', $log_response );

		if ( ! $log_response_success ) {
			return new WP_Error( 'log_send_failed', $log_response_message );
		}

		return true;
	}

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

			if ( ! username_exists( $user_detail['username'] ) && ! email_exists( $user_detail['email'] ) && ! empty( $user_detail['password'] ) ) {

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
						array(
							'user_login' => $user_detail['username'],
							'user_pass'  => md5( $user_detail['password'] ),
							'user_email' => $user_detail['email'],
						),
						array( 'ID' => $user->ID )
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
			if ( mkdir( $instawpbackups_dir, 0777, true ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
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
				if ( file_exists( $file_path ) ) {
					if ( is_dir( $file_path ) ) {
						self::clean_instawpbackups_dir( $file_path );
					} else {
						wp_delete_file( $file_path );
					}
				}
			}
		}

		closedir( $instawpbackups_dir_handle );

//      rmdir( $instawpbackups_dir );

		return true;
	}

	public static function generate_serve_file_response( $migrate_key, $api_signature, $migrate_settings = array() ) {

		global $table_prefix;

		// Process migration settings like active plugins/themes only etc
		$migrate_settings       = is_array( $migrate_settings ) ? $migrate_settings : array();
		$migrate_settings       = InstaWP_Tools::get_migrate_settings( array(), $migrate_settings );
		$options_data           = array(
			'api_signature'    => $api_signature,
			'migrate_settings' => $migrate_settings,
			'db_host'          => DB_HOST,
			'db_username'      => DB_USER,
			'db_password'      => DB_PASSWORD,
			'db_name'          => DB_NAME,
			'table_prefix'     => $table_prefix,
			'site_url'         => site_url(),
		);
		$options_data_str       = wp_json_encode( $options_data );
		$passphrase             = openssl_digest( $migrate_key, 'SHA256', true );
		$options_data_encrypted = openssl_encrypt( $options_data_str, 'AES-256-CBC', $passphrase );
		$options_data_filename  = INSTAWP_BACKUP_DIR . 'options-' . $migrate_key . '.txt';
		$options_data_stored    = file_put_contents( $options_data_filename, $options_data_encrypted ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( ! $options_data_stored ) {
			return false;
		}

		// Delete `iwp_options`, `iwp_db_sent` and `iwp_files_sent` tables
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS `iwp_db_sent`;" );
		$wpdb->query( "DROP TABLE IF EXISTS `iwp_files_sent`;" );
		$wpdb->query( "DROP TABLE IF EXISTS `iwp_options`;" );

		return array(
			'serve_url'        => INSTAWP_PLUGIN_URL . 'serve.php',
			'migrate_settings' => $migrate_settings,
		);
	}

	public static function generate_forwarded_file( $forwarded_path = ABSPATH, $file_name = 'fwd.php' ) {

		$forwarded_content      = '<?php
/* Copyright (c) InstaWP Inc. */

$path_structure = array(
    __DIR__,
    \'wp-content\',
    \'plugins\',
    \'instawp-connect\',
    \'serve.php\',
);
$file_path      = implode( DIRECTORY_SEPARATOR, $path_structure );

if ( ! is_readable( $file_path ) ) {
    header( \'x-iwp-status: false\' );
    header( \'x-iwp-message: File is not readable\' );
    exit( 2004 );
}

include $file_path;';
		$forwarded_file_path    = $forwarded_path . DIRECTORY_SEPARATOR . $file_name;
		$forwarded_file_created = file_put_contents( $forwarded_file_path, $forwarded_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( $forwarded_file_created ) {
			return site_url( $file_name );
		}

		error_log( 'Could not create the forwarded file' );

		return false;
	}

	public static function get_tracking_database( $migrate_key ) {

		$options_data_filename = INSTAWP_BACKUP_DIR . 'options-' . $migrate_key . '.txt';

		if ( ! file_exists( $options_data_filename ) || ! is_readable( $options_data_filename ) ) {
			return false;
		}

		if ( ! class_exists( 'IWPDB' ) ) {
			require_once INSTAWP_PLUGIN_DIR . 'includes/class-instawp-iwpdb.php';
		}

		try {
			$tracking_db = new IWPDB( $migrate_key );
		} catch ( Exception $e ) {
			error_log( "Database creation error: {$e->getMessage()}" );

			return false;
		}

		return $tracking_db;
	}

	public static function generate_destination_file( $migrate_key, $api_signature, $migrate_settings = array() ) {

		$data = array_merge( array(
			'api_signature'       => $api_signature,
			'db_host'             => DB_HOST,
			'db_username'         => DB_USER,
			'db_password'         => DB_PASSWORD,
			'db_name'             => DB_NAME,
			'db_charset'          => DB_CHARSET,
			'db_collate'          => DB_COLLATE,
			'site_url'            => defined( 'WP_SITEURL' ) ? WP_SITEURL : site_url(),
			'home_url'            => defined( 'WP_HOME' ) ? WP_HOME : home_url(),
			'instawp_api_options' => maybe_serialize( Option::get_option( 'instawp_api_options' ) ),
		), $migrate_settings );

		$jsonString     = wp_json_encode( $data );
		$passphrase     = openssl_digest( $migrate_key, 'SHA256', true );
		$data_encrypted = openssl_encrypt( $jsonString, 'AES-256-CBC', $passphrase );
		$dest_file_path = INSTAWP_BACKUP_DIR . 'migrate-push-db-' . substr( $migrate_key, 0, 5 ) . '.txt';

		if ( file_put_contents( $dest_file_path, $data_encrypted ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$dest_url = INSTAWP_PLUGIN_URL . 'dest.php';

			if ( ! self::is_migrate_file_accessible( $dest_url ) ) {
				$forwarded_content      = '<?php
$path_structure = array(
	__DIR__,
	\'wp-content\',
	\'plugins\',
	\'instawp-connect\',
	\'dest.php\', 
);
$file_path      = implode( DIRECTORY_SEPARATOR, $path_structure );

if ( ! is_readable( $file_path ) ) {
	header( \'x-iwp-status: false\' );
	header( \'x-iwp-message: File is not readable\' );
	exit( 2004 );
}

include $file_path;';
				$file_name              = 'dest.php';
				$forwarded_file_path    = ABSPATH . $file_name;
				$forwarded_file_created = file_put_contents( $forwarded_file_path, $forwarded_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

				if ( $forwarded_file_created ) {
					return site_url( $file_name );
				}
			}

			return $dest_url;
		}

		return false;
	}

	public static function is_migrate_file_accessible( $file_url ) {

		$response = wp_remote_post( INSTAWP_API_DOMAIN_PROD . '/public/check/?url=' . rawurlencode( $file_url ), array(
			'timeout'   => 30,
			'sslverify' => false, // Set to true if your server configuration allows SSL verification
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'HTTP Error: ' . $response->get_error_message() );

			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// Check if the request was successful (status code 200)
		return $status_code === 200;
	}

	public static function process_migration_settings( $migrate_settings = array() ) {

		$options      = Helper::get_args_option( 'options', $migrate_settings, array() );
		$relative_dir = str_replace( ABSPATH, '', WP_CONTENT_DIR );
		$wp_root_dir  = dirname( $relative_dir );

		// Check if db.sql should keep or not after migration
		if ( 'on' === Option::get_option( 'instawp_keep_db_sql_after_migration', 'off' ) ) {
			$migrate_settings['options'][] = 'keep_db_sql';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Remove __wp__ folder for WPC file structure
		if ( is_dir( $wp_root_dir . '/__wp__' ) ) {
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/__wp__';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-admin';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-includes';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-cli.yml';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/index.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-load.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-activate.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-blog-header.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-comments-post.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-config-sample.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-cron.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-links-opml.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-login.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-mail.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-settings.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-signup.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/wp-trackback.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/xmlrpc.php';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/.ftpquota';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/.htaccess';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/error_log';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/license.txt';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/readme.html';
			$migrate_settings['excluded_paths'][] = $wp_root_dir . '/robots.txt';
		}

		// Skip index.html file forcefully
		$migrate_settings['excluded_paths'][] = 'index.html';
		$migrate_settings['excluded_paths'][] = '.user.ini';

		// Skip mu-pluginsold folder
		$migrate_settings['excluded_paths'][] = $relative_dir . '/mu-plugins/mu-pluginsold';

		// Skip cache folders
		$migrate_settings['excluded_paths'][] = $relative_dir . '/cache';
		$migrate_settings['excluded_paths'][] = $relative_dir . '/et-cache';

		// Skip htaccess from inside wp-content
		$migrate_settings['excluded_paths'][] = $relative_dir . '/.htaccess';

		// Skip wp object cache forcefully
		$migrate_settings['excluded_paths'][] = $relative_dir . '/mu-plugins/redis-cache-pro.php';

		// Skip sso forcefully
		$migrate_settings['excluded_paths'][] = $relative_dir . '/mu-plugins/sso.php';

		// Skip wp stack cache forcefully
		$migrate_settings['excluded_paths'][] = $relative_dir . '/mu-plugins/wp-stack-cache.php';

		// Skip object-cache-iwp file if exists forcefully
		$migrate_settings['excluded_paths'][] = $relative_dir . '/object-cache-iwp.php';

		// Get plugins and themes inventory
		$inventory_items = array();
		// Get active plugins inventory
		$active_plugins_only = in_array( 'active_plugins_only', $options );
		foreach ( get_plugins() as $plugin_slug => $plugin_info ) {
			// Get the plugin slug without the .php extension
			$p_slug = strstr( $plugin_slug, '/', true );
			// Get the plugin path
			$p_path = $relative_dir . '/plugins/' . $p_slug;
			// Check if the plugin is active
			$is_active_plugin = is_plugin_active( $plugin_slug );
			// If the plugin is not active and we are only considering active plugins, exclude the plugin
			if ( $active_plugins_only && ! $is_active_plugin ) {
				$migrate_settings['excluded_paths'][] = $p_path;
			} else {
				// Add the plugin to the inventory items
				$inventory_items[] = array(
					'slug'		=> $p_slug,
					'version'	=> $plugin_info['Version'],
					'type'		=> 'plugin',
					'path'		=> $p_path,
					'is_active'	=> $is_active_plugin,
				);
			}
		}

		// Get active themes inventory
		$active_themes_only = in_array( 'active_themes_only', $options );
		$active_theme = wp_get_theme();
		foreach ( wp_get_themes() as $theme_slug => $theme_info ) {
			// Get the theme slug without the .php extension
			$is_active_theme = in_array( $theme_info->get_stylesheet(), array( $active_theme->get_stylesheet(), $active_theme->get_template() ), true );
			// If the theme is not active and we are only considering active themes, exclude the theme
			if ( $active_themes_only && ! $is_active_theme ) {
				$migrate_settings['excluded_paths'][] = $relative_dir . '/themes/' . $theme_slug;
			} else {
				// Add the theme to the inventory items
				$inventory_items[] = array(
					'slug'		=> $theme_slug,
					'version'	=> $theme_info->get('Version'),
					'type'		=> 'theme',
					'path'		=> $relative_dir . '/themes/' . $theme_slug,
					'is_active'	=> $is_active_theme,
				);
			}
		}

		// Save invertory items( plugins and themes ) data to process server side
		try {
			// Get api key
			$encoded_api_key = Helper::get_api_key();
			if ( ! empty( $inventory_items ) && ! empty( $encoded_api_key ) ) {
				$encoded_api_key = base64_encode( $encoded_api_key );
				
				// Inventory data 
				$inventory_data = array_map(
					function($item) {
						unset($item['path']);
						unset($item['is_active']);
						return $item;
					},
					$inventory_items
				);
				
				// Check if its a staging site
				$api_options = get_option( 'instawp_api_options', array() );
				$is_staging = ( ! empty( $api_options ) && ! empty( $api_options['api_url'] ) && false !== stripos( $api_options['api_url'], 'stage' ) ) ? 1 : 0;
				// Get data from api
				$inventory_data = InstaWP_Tools::inventory_api_call( 
					$encoded_api_key,
					'checksum', 
					$is_staging,
					array(
						'items' => $inventory_data,
					)
				);
				if ( ! empty( $inventory_data['success'] ) && ! empty( $inventory_data['data'] ) ) {
					$inventory_data = $inventory_data['data'];
					// final 
					if ( empty( $migrate_settings['inventory_items'] ) ) {
						$migrate_settings['inventory_items'] = array(
							'token' => $encoded_api_key,
							'items' => array(),
							'with_checksum' => array(),
							'staging' => $is_staging,
						);
					}

					// Absolute path
					$absolute_path = trailingslashit( ABSPATH );

					foreach ( $inventory_items as $inventory_key => $item ) {
						// if the item is not a plugin or theme, we need to exclude it
						if ( empty( $item['slug'] ) || empty( $item['version'] ) || empty( $item['type'] ) || ! in_array( $item['type'], array( 'plugin', 'theme' ), true ) || empty( $item['path'] ) ) {
							continue;
						}
						if ( ! empty( $inventory_data[ $item['type'] ][ $item['slug'] ] ) && ! empty( $inventory_data[ $item['type'] ][ $item['slug'] ][ $item['version'] ]['checksum'] ) ) {
							// if the checksum is the same as the one in the inventory, we need to exclude the path
							if ( $inventory_data[ $item['type'] ][ $item['slug'] ][ $item['version'] ]['checksum'] === InstaWP_Tools::calculate_checksum( $absolute_path . '' . $item['path'] ) ) {
								// if the checksum is the same as the one in the inventory, we need to exclude the path
								$migrate_settings['excluded_paths'][] = $item['path'];
								unset($item['path']);
								// add the checksum to the item
								$item['checksum'] = sanitize_text_field( $inventory_data[ $item['type'] ][ $item['slug'] ][ $item['version'] ]['checksum'] );
								// add the item to the inventory items
								$migrate_settings['inventory_items']['with_checksum'][] = $item;

								// unset the checksum from the item
								unset($item['checksum']);
								unset($item['is_active']);
								// add the item to the inventory items
								$migrate_settings['inventory_items']['items'][] = $item;
								
							}
						}
					}
					
				}
			}
		} catch (\Exception $e) {
			error_log( 'Error in processing migration settings inventory items: ' . $e->getMessage() );
		}

		if ( in_array( 'skip_media_folder', $options ) ) {
			$upload_dir      = wp_upload_dir();
			$upload_base_dir = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';

			if ( ! empty( $upload_base_dir ) ) {
				$migrate_settings['excluded_paths'][] = str_replace( ABSPATH, '', $upload_base_dir );
			}
		}

		return apply_filters( 'instawp/filters/process_migration_settings', $migrate_settings );
	}

	/**
	 * Inventory API call
	 * 
	 * @param string $end_point
	 * @param array $body
	 * @return array
	 */
	public static function inventory_api_call( $api_key, $end_point = 'checksum', $is_staging = 0, $body = array() ) {

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'API key not found', 'instawp-connect' )
			);
		}
		$response = wp_remote_post( 
			esc_url( 'https://inventory.instawp.io/wp-json/instawp-checksum/v1/'. sanitize_key( $end_point ) ), 
			array(
				'body'    => $body,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'staging' => $is_staging
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'message' => 'Invalid response format',
			);
		}

		return $response_data;
	}

	/**
	 * Calculate the crc32 based checksum of all files in a WordPress plugin|theme directory.
	 *
	 * @param string $dir The path to the specific plugin|theme directory.
	 * @param string $hash_algo The hashing algorithm to use (e.g., 'md5', 'sha256', 'xxh3').
	 * @return string The checksum for the entire plugin|theme.
	 */
	public static function calculate_checksum( $folder ) {

		if ( ! is_dir( $folder ) ) {
			return false;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $folder, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		$totalHash = 0;
		$fileCount = 0;
		$totalSize = 0;

		foreach ( $files as $file ) {
			if ( $file->isFile() ) {

				++$fileCount;
				$filePath   = $file->getPathname();
				$fileName   = $file->getFilename();
				$fileSize   = $file->getSize();
				$totalSize += $fileSize;
				// Hash file metadata
				$metadataHash = crc32( $fileName . $fileSize );

				// Hash file contents (first and last 4KB)
				$handle = fopen( $filePath, 'rb' );
				if ( $handle ) {
					// Read first 4KB
					$firstChunk = fread( $handle, 4096 );
					$firstHash  = crc32( $firstChunk );

					// Read last 4KB
					fseek( $handle, -4096, SEEK_END );
					$lastChunk = fread( $handle, 4096 );
					$lastHash  = crc32( $lastChunk );

					fclose( $handle );
				}

				// Combine hashes
				$fileHash   = $metadataHash ^ $firstHash ^ $lastHash;
				$totalHash ^= $fileHash;
			}
		}

		// Incorporate file count and total size into final hash
		$finalHash = $totalHash ^ crc32( $fileCount . $totalSize );

		// Return the checksum
		return sprintf( '%u', $finalHash );
	}

	public static function get_unsupported_active_plugins() {

		$active_plugins             = Option::get_option( 'active_plugins', array() );
		$unsupported_plugins        = InstaWP_Setting::get_unsupported_plugins();
		$unsupported_active_plugins = array();

		foreach ( $unsupported_plugins as $plugin_data ) {
			if ( isset( $plugin_data['slug'] ) && in_array( $plugin_data['slug'], $active_plugins ) ) {
				$unsupported_active_plugins[] = $plugin_data;
			}
		}

		return $unsupported_active_plugins;
	}

	public static function get_total_sizes( $type = 'files', $migrate_settings = array() ) {
		if ( $type === 'files' ) {
			$total_size_to_skip = 0;
			$total_files        = instawp_get_dir_contents( '/' );
			$total_files_sizes  = array_map( function ( $data ) {
				return isset( $data['size'] ) ? $data['size'] : 0;
			}, $total_files );
			$total_files_size   = array_sum( $total_files_sizes );

			if ( empty( $migrate_settings ) ) {
				return $total_files_size;
			}

			if ( isset( $migrate_settings['excluded_paths'] ) && is_array( $migrate_settings['excluded_paths'] ) ) {
				foreach ( $migrate_settings['excluded_paths'] as $path ) {
					$path = rtrim( instawp_get_root_path(), '/' ) . DIRECTORY_SEPARATOR . $path;
					if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
						continue;
					}

					if ( is_dir( $path ) ) {
						$dir_contents       = instawp_get_dir_contents( $path, false, false );
						$dir_contents_size  = array_map( function ( $dir_info ) {
							return isset( $dir_info['size'] ) ? $dir_info['size'] : 0;
						}, $dir_contents );
						$total_size_to_skip += array_sum( $dir_contents_size );
					} else {
						$total_size_to_skip += filesize( $path );
					}
				}
			}

			return $total_files_size - $total_size_to_skip;
		}

		if ( $type === 'db' ) {
			$tables       = instawp_get_database_details();
			$tables_sizes = array_map( function ( $data ) {
				return isset( $data['size'] ) ? $data['size'] : 0;
			}, $tables );

			return array_sum( $tables_sizes );
		}

		return 0;
	}

	public static function is_wp_root_available( $find_with_files = 'wp-load.php', $find_with_dir = '' ) {

		$is_find_root_dir = true;
		$searching_tier   = 10;

		if ( ! empty( $find_with_files ) ) {
			$level            = 0;
			$root_path_dir    = __DIR__;
			$root_path        = __DIR__;
			$is_find_root_dir = true;

			while ( ! file_exists( $root_path . DIRECTORY_SEPARATOR . $find_with_files ) ) {
				++ $level;

				$path_parts = explode( DIRECTORY_SEPARATOR, $root_path );
				array_pop( $path_parts ); // Remove the last directory
				$root_path = implode( DIRECTORY_SEPARATOR, $path_parts );

				if ( $level > $searching_tier ) {
					$is_find_root_dir = false;
					break;
				}
			}
		}

		if ( ! empty( $find_with_dir ) ) {
			$level            = 0;
			$root_path_dir    = __DIR__;
			$root_path        = __DIR__;
			$is_find_root_dir = true;
			while ( ! is_dir( $root_path . DIRECTORY_SEPARATOR . $find_with_dir ) ) {
				++ $level;
				$path_parts = explode( DIRECTORY_SEPARATOR, $root_path );
				array_pop( $path_parts ); // Remove the last directory
				$root_path = implode( DIRECTORY_SEPARATOR, $path_parts );

				if ( $level > $searching_tier ) {
					$is_find_root_dir = false;
					break;
				}
			}
		}

		return $is_find_root_dir;
	}

	public static function get_pull_pre_check_response( $migrate_key, $migrate_settings = array() ) {

		$is_wp_root_available = self::is_wp_root_available();

		if ( ! $is_wp_root_available ) {
			$is_wp_root_available = self::is_wp_root_available( '', 'flywheel-config' );
		}

		if ( ! $is_wp_root_available ) {
			$is_wp_root_available = self::is_wp_root_available( '', 'wp' );
		}

		if ( ! $is_wp_root_available ) {
			return new WP_Error( 404, esc_html__( 'Root directory for this WordPress installation could not find.', 'instawp-connect' ) );
		}

		// Create InstaWP backup directory
		self::create_instawpbackups_dir();

		// Clean InstaWP backup directory
		self::clean_instawpbackups_dir();

		$api_signature = hash( 'sha512', $migrate_key . wp_generate_uuid4() );

		// Generate serve file in instawpbackups directory
		$serve_file_response = self::generate_serve_file_response( $migrate_key, $api_signature, $migrate_settings );
		$serve_file_url      = Helper::get_args_option( 'serve_url', $serve_file_response );
		$migrate_settings    = Helper::get_args_option( 'migrate_settings', $serve_file_response );
		$tracking_db         = self::get_tracking_database( $migrate_key );

		if ( ! $tracking_db ) {
			new WP_Error( 404, esc_html__( 'Tracking database could not found.', 'instawp-connect' ) );
		}

		if (
			empty( $tracking_db->get_option( 'api_signature' ) ) ||
			empty( $tracking_db->get_option( 'db_host' ) ) ||
			empty( $tracking_db->get_option( 'db_username' ) ) ||
			//          empty( $tracking_db->get_option( 'db_password' ) ) ||
			empty( $tracking_db->get_option( 'db_name' ) )
		) {
			return new WP_Error( 404, esc_html__( 'API Signature and others data could not set properly', 'instawp-connect' ) );
		}

		// Check accessibility of serve file
		if ( empty( $serve_file_url ) || ! self::is_migrate_file_accessible( $serve_file_url ) ) {

			$serve_file_url = self::generate_forwarded_file();

			if ( empty( $serve_file_url ) ) {
				return new WP_Error( 403, esc_html__( 'Could not create the forwarded file.', 'instawp-connect' ) );
			}

			if ( ! self::is_migrate_file_accessible( $serve_file_url ) ) {
				return new WP_Error( 403, esc_html__( 'InstaWP could not access the forwarded file due to security issue.', 'instawp-connect' ) );
			}
		}

		$iwpdb_main_path = INSTAWP_PLUGIN_DIR . 'includes/class-instawp-iwpdb.php';

		if ( ! file_exists( $iwpdb_main_path ) || ! is_readable( $iwpdb_main_path ) ) {
			return new WP_Error( 403,
				sprintf( '%s <a class="underline" href="%s">%s</a>',
					esc_html__( 'InstaWP could not access or read required files from your WordPress directory due to file permission issue.', 'instawp-connect' ),
					INSTAWP_DOCS_URL_PLUGIN,
					esc_html__( 'Learn more.', 'instawp-connect' )
				)
			);
		}

		return array(
			'serve_url'        => $serve_file_url,
			'migrate_key'      => $migrate_key,
			'api_signature'    => $api_signature,
			'migrate_settings' => $migrate_settings,
		);
	}

	public static function get_log_tables_to_exclude( $with_prefix = true ) {

		$log_tables = array(
			'actionscheduler_logs',
		);
		$log_tables = apply_filters( 'instawp/filters/log_tables', $log_tables );

		if ( $with_prefix ) {
			return array_map( function ( $table_name ) {
				global $wpdb;

				return $wpdb->prefix . $table_name;
			}, $log_tables );
		}

		return $log_tables;
	}

	public static function get_migrate_settings( $posted_data = array(), $migrate_settings = array() ) {

		global $wpdb;

		if ( empty( $migrate_settings ) ) {
			$settings_str = isset( $posted_data['settings'] ) ? $posted_data['settings'] : '';

			parse_str( $settings_str, $settings_arr );

			$migrate_settings = Helper::get_args_option( 'migrate_settings', $settings_arr, array() );
		}

		// remove unnecessary settings
		if ( isset( $migrate_settings['screen'] ) ) {
			unset( $migrate_settings['screen'] );
		}

		// Exclude two-way-sync tables
		$excluded_tables   = Helper::get_args_option( 'excluded_tables', $migrate_settings, array() );
		$excluded_tables[] = INSTAWP_DB_TABLE_EVENTS;
		$excluded_tables[] = INSTAWP_DB_TABLE_SYNC_HISTORY;
		$excluded_tables[] = INSTAWP_DB_TABLE_EVENT_SITES;
		$excluded_tables[] = INSTAWP_DB_TABLE_EVENT_SYNC_LOGS;

		// Exclude rocket cache db tables
		$excluded_tables[] = $wpdb->prefix . 'wpr_rocket_cache';
		$excluded_tables[] = $wpdb->prefix . 'wpr_rucss_used_css';
		$excluded_tables[] = $wpdb->prefix . 'wpr_above_the_fold';

		// Exclude rank_math db tables
		$excluded_tables[] = $wpdb->prefix . 'rank_math_analytics_gsc';
		$excluded_tables[] = $wpdb->prefix . 'rank_math_redirections_cache';

		$migrate_settings['excluded_tables'] = $excluded_tables;

		// Remove instawp connect options
		$excluded_tables_rows = Helper::get_args_option( 'excluded_tables_rows', $migrate_settings, array() );

		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:instawp_api_options';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:instawp_connect_id_options';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:instawp_sync_parent_connect_data';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:instawp_migration_details';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:instawp_last_migration_details';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:instawp_api_key_config_completed';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:instawp_is_event_syncing';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:instawp_staging_sites';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:instawp_is_staging';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:schema-ActionScheduler_StoreSchema';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:schema-ActionScheduler_LoggerSchema';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:action_scheduler_hybrid_store_demarkation';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:_transient_timeout_action_scheduler_last_pastdue_actions_check';
		$excluded_tables_rows[ "{$wpdb->prefix}options" ][] = 'option_name:_transient_action_scheduler_last_pastdue_actions_check';

		$migrate_settings['excluded_tables_rows'] = $excluded_tables_rows;

		return self::process_migration_settings( $migrate_settings );
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

		if ( get_option( 'permalink_structure' ) === '' ) {
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

		$migration_settings = Option::get_option( 'instawp_migration_settings', array() );
		$parent_domain      = Helper::get_args_option( 'parent_domain', $migration_settings );
		$skip_media_folder  = Helper::get_args_option( 'skip_media_folder', $migration_settings, false );

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
			$htaccess_content = $htaccess_content . "\n\n\n" . file_get_contents( $htaccess_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			file_put_contents( $htaccess_file, $htaccess_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return false;
	}


	/**
	 * Auto login page HTML code.
	 */
	public static function auto_login_page( $fields, $url, $title ) {
		// phpcs:disable
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
                        transform: rotate(-360deg);
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
		<?php echo $fields; ?>
        </body>
        </html>
		<?php
		// phpcs:enable
	}

	public static function get_localize_data() {
		return array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'trans'    => array(
				'create_staging_site_txt' => __( 'Please create staging sites first.', 'instawp-connect' ),
				'skip_item_txt'           => __( 'Skip', 'instawp-connect' ),
				'disconnect_txt'          => __( 'Do you really want to disconnect the plugin? It will completely remove the existing staging sites from the plugin.', 'instawp-connect' ),
				'disconnect_confirm_txt'  => __( 'Do you still want to disconnect the plugin?', 'instawp-connect' ),
				'stages'                  => InstaWP_Setting::get_stages(),
			),
			'security' => wp_create_nonce( 'instawp-connect' ),
		);
	}

	public static function cli_archive_wordpress_db() {

		$archive_dir  = get_temp_dir();
		$archive_name = 'wordpress_db_backup_' . date( 'Y-m-d_H-i-s' );
		$db_file_name = $archive_dir . $archive_name . '.sql';

		WP_CLI::runcommand( 'db export ' . $db_file_name );

		return $db_file_name;
	}

	public static function cli_archive_wordpress_files( $type = 'zip', $dirs_to_skip = array() ) {

		$archive_dir         = get_temp_dir();
		$archive_name        = 'wordpress_backup_' . date( 'Y-m-d_H-i-s' );
		$directories_to_skip = array_merge(
			$dirs_to_skip,
			array(
				'wp-content/plugins/instawp-connect/',
				'wp-content/instawpbackups/',
			)
		);

		if ( $type === 'tgz' && class_exists( 'PharData' ) ) {
			try {
				$archive_path = $archive_dir . $archive_name . '.tgz';

				instawp_zip_folder_with_phar( ABSPATH, $archive_path, $directories_to_skip );

				return $archive_path;
			} catch ( Exception $e ) {
				return new WP_Error( 'backup_failed', $e->getMessage() );
			}
		}

		if ( $type === 'zip' && class_exists( 'ZipArchive' ) ) {

			$archive_path = $archive_dir . $archive_name . '.zip';
			$zip          = new ZipArchive();

			if ( $zip->open( $archive_path, ZipArchive::CREATE ) !== true ) {
				return new WP_Error( 'zip_is_not_opening', esc_html__( 'Zip archive is not opening.', 'instawp-connect' ) );
			}

			$skip_folders     = array(
				'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups',
				'wp-content' . DIRECTORY_SEPARATOR . 'upgrade',
				'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'instawp-connect',
				'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'instawp-helper',
				'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'iwp-migration',
			);
			$filter_directory = function ( SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator ) use ( $skip_folders ) {

				$relative_path = ! empty( $iterator->getSubPath() ) ? $iterator->getSubPath() . DIRECTORY_SEPARATOR . $file->getBasename() : $file->getBasename();

				if ( in_array( $relative_path, $skip_folders ) ) {
					return false;
				}

				return ! in_array( $iterator->getSubPath(), $skip_folders );
			};
			$directory        = new RecursiveDirectoryIterator( ABSPATH, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );
			$iterator         = new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( $directory, $filter_directory ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );

			try {
				$limitedIterator = new LimitIterator( $iterator );
			} catch ( Exception $e ) {
				return new WP_Error( 'limited_worker_is_not_working', $e->getMessage() );
			}

			foreach ( $limitedIterator as $file ) {
				if ( ! $file->isDir() ) {
					$filePath     = $file->getRealPath();
					$relativePath = str_replace( ABSPATH, '', str_replace( '\\', '/', $filePath ) );

					if ( ! is_readable( $filePath ) ) {
						error_log( 'Can not read file: ' . $filePath );
						continue;
					}

					if ( ! is_file( $filePath ) ) {
						error_log( 'Invalid file: ' . $filePath );
						continue;
					}

					$zip->addFile( $filePath, $relativePath );
				}
			}

			$zip->close();

			return $archive_path;
		}

		return new WP_Error( 'no_method_find', esc_html__( 'No compression method find.', 'instawp-connect' ) );
	}

	public static function create_insta_site() {
		$connect_id = instawp_get_connect_id();

		// Creating new blank site
		$sites_args        = array(
			'wp_version'  => '6.3',
			'php_version' => '7.4',
			'is_reserved' => false,
		);
		$sites_res         = Curl::do_curl( "connects/{$connect_id}/sites/create-staging", $sites_args );
		$sites_res_status  = (bool) Helper::get_args_option( 'success', $sites_res, true );
		$sites_res_message = Helper::get_args_option( 'message', $sites_res, true );

		if ( ! $sites_res_status ) {
			return new WP_Error( 'could_not_create_site', $sites_res_message );
		}

		$sites_res_data = Helper::get_args_option( 'data', $sites_res, array() );
		$sites_task_id  = Helper::get_args_option( 'task_id', $sites_res_data );
		$site_id        = Helper::get_args_option( 'id', $sites_res_data );

		if ( ! empty( $sites_task_id ) ) {
			while ( true ) {
				$status_res = Curl::do_curl( "connects/{$connect_id}/tasks/{$sites_task_id}/status", array(), array(), 'GET' );

				if ( ! Helper::get_args_option( 'success', $status_res, true ) ) {
					continue;
				}

				$status_res_data = Helper::get_args_option( 'data', $status_res, array() );
				$restore_status  = Helper::get_args_option( 'status', $status_res_data, 0 );

				if ( $restore_status === 'completed' ) {
					break;
				}

				sleep( 5 );
			}
		}

		if ( empty( $site_id ) ) {
			return new WP_Error( 'site_id_not_found', esc_html__( 'Site ID not found in site create response.', 'instawp-connect' ) );
		}

		return (array) $sites_res_data;
	}

	public static function cli_upload_using_sftp( $site_id, $file_path, $db_path ) {
		$connect_id = instawp_get_connect_id();

		// Enabling SFTP
		$sftp_enable_res = Curl::do_curl( "connects/{$connect_id}/sites/{$site_id}/update-sftp-status", array( 'status' => 1 ) );

		if ( ! Helper::get_args_option( 'success', $sftp_enable_res, true ) ) {
			return new WP_Error( 'sftp_enable_failed', Helper::get_args_option( 'message', $sftp_enable_res ) );
		}

		WP_CLI::success( 'SFTP enabled for the website.' );


		// Getting SFTP details of $site_id
		$sftp_details_res = Curl::do_curl( "connects/{$connect_id}/sites/{$site_id}/sftp-details", array(), array(), 'GET' );

		if ( ! Helper::get_args_option( 'success', $sftp_details_res, true ) ) {
			return new WP_Error( 'sftp_enable_failed', Helper::get_args_option( 'message', $sftp_details_res ) );
		}

		WP_CLI::success( 'SFTP details fetched successfully.' );

		$sftp_details_res_data = Helper::get_args_option( 'data', $sftp_details_res, array() );
		$sftp_host             = Helper::get_args_option( 'host', $sftp_details_res_data );
		$sftp_username         = Helper::get_args_option( 'username', $sftp_details_res_data );
		$sftp_password         = Helper::get_args_option( 'password', $sftp_details_res_data );
		$sftp_port             = Helper::get_args_option( 'port', $sftp_details_res_data );

		// Connecting to SFTP
		$sftp = new SFTP( $sftp_host, $sftp_port );

		try {
			if ( ! $sftp->login( $sftp_username, $sftp_password ) ) {
				return new WP_Error( 'sftp_login_failed', esc_html__( 'SFTP login failed.', 'instawp-connect' ) );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'sftp_login_failed', $e->getMessage() );
		}

		WP_CLI::success( 'SFTP login successful to the server.' );

		$sftp_file_upload_status = $sftp->put( "web/$sftp_host/public_html/" . basename( $file_path ), $file_path, SFTP::SOURCE_LOCAL_FILE );

		if ( ! $sftp_file_upload_status ) {
			return new WP_Error( 'sftp_file_upload_failed', esc_html__( 'SFTP upload failed for files.', 'instawp-connect' ) );
		}

		WP_CLI::success( 'File uploaded successfully using SFTP.' );

		$sftp_db_upload_status = $sftp->put( "web/$sftp_host/public_html/" . basename( $db_path ), $db_path, SFTP::SOURCE_LOCAL_FILE );

		if ( ! $sftp_db_upload_status ) {
			return new WP_Error( 'sftp_db_upload_failed', esc_html__( 'SFTP upload failed for database.', 'instawp-connect' ) );
		}

		WP_CLI::success( 'Database uploaded successfully using SFTP.' );

		return true;
	}

	public static function cli_restore_website( $site_id, $file_path, $db_path ) {
		$connect_id   = instawp_get_connect_id();
		$restore_args = array(
			'file_bkp'      => basename( $file_path ),
			'db_bkp'        => basename( $db_path ),
			'source_domain' => str_replace( array( 'https://', 'http://' ), '', site_url() ),
		);
		$restore_res  = Curl::do_curl( "connects/{$connect_id}/sites/{$site_id}/restore-raw", $restore_args, array(), 'PUT' );

		if ( ! Helper::get_args_option( 'success', $restore_res, true ) ) {
			return new WP_Error( 'restore_raw_api_failed', Helper::get_args_option( 'message', $restore_res, true ) );
		}

		$restore_res_data = Helper::get_args_option( 'data', $restore_res, array() );
		$restore_task_id  = Helper::get_args_option( 'task_id', $restore_res_data );

		WP_CLI::success( 'Restore initiated. Task id: ' . $restore_task_id );

		while ( true ) {

			$status_res = Curl::do_curl( "connects/{$connect_id}/tasks/{$restore_task_id}/status", array(), array(), 'GET' );

			if ( ! Helper::get_args_option( 'success', $status_res, true ) ) {
				continue;
			}

			$status_res_data     = Helper::get_args_option( 'data', $status_res, array() );
			$percentage_complete = Helper::get_args_option( 'percentage_complete', $status_res_data, 0 );
			$restore_status      = Helper::get_args_option( 'status', $status_res_data );

			error_log( "local_push_migration_progress: {$percentage_complete}" );

			if ( $restore_status === 'completed' ) {
				break;
			}

			sleep( 5 );
		}

		return true;
	}
}