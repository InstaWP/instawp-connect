#!/usr/bin/env php

<?php
if ( ! function_exists( 'iwp_backoff_timer' ) ) {
	function iwp_backoff_timer( $attempt, $base = 2, $maxWait = 300 ) {
		// Calculate the backoff time
		$waitTime = min( $base ** $attempt, $maxWait );

		echo "Backing off .. $waitTime seconds\n";

		// Return the wait time
		return $waitTime;
	}
}

if ( ! function_exists( 'iwp_send_migration_log' ) ) {
	function iwp_send_migration_log( $label = '', $description = '', $payload = [], $echo = true ) {

		if ( $echo ) {
			echo $description . "\n";
		}

		return true;// Remove after complete iterative pull push

		global $migrate_id, $bearer_token;

		$log_data = array(
			'migrate_id'  => $migrate_id,
			'type'        => 'instacp',
			'label'       => $label,
			'description' => $description,
			'payload'     => $payload,
		);
		$curl     = curl_init();
		$hostname = gethostname();

		if ( strpos( $hostname, 'production' ) !== false ) {
			$apiDomain = 'app.instawp.io';
		} else {
			$apiDomain = 'stage.instawp.io';
		}

		curl_setopt_array( $curl, array(
			CURLOPT_URL            => 'https://' . $apiDomain . '/api/v2/migrates-v3/log',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT      => 'InstaWP Migration Service',
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode( $log_data ),
			CURLOPT_COOKIE         => 'instawp_skip_splash=true',
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'Accept: application/json',
				'Authorization: Bearer ' . $bearer_token
			),
		) );

		curl_exec( $curl );
		curl_close( $curl );
	}
}

if ( ! function_exists( 'iwp_send_progress' ) ) {
	function iwp_send_progress( $progress_files = 0, $progress_db = 0, $stages = [] ) {

		global $migrate_key, $migrate_id, $bearer_token;

		$curl      = curl_init();
		$post_data = array(
			'migrate_key' => $migrate_key,
			'stage'       => $stages
		);

		if ( $progress_files > 0 ) {
			$post_data['progress_files'] = $progress_files;
		}

		if ( $progress_db > 0 ) {
			$post_data['progress_db'] = $progress_db;
		}

		$hostname = gethostname();

		if ( strpos( $hostname, 'production' ) !== false ) {
			$apiDomain = 'app.instawp.io';
		} else {
			$apiDomain = 'stage.instawp.io';
		}

		curl_setopt_array( $curl, array(
			CURLOPT_URL            => 'https://' . $apiDomain . '/api/v2/migrates-v3/' . $migrate_id . '/update-status',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT      => 'InstaWP Migration Service - Pull Files',
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode( $post_data ),
			CURLOPT_COOKIE         => 'instawp_skip_splash=true',
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'Accept: application/json',
				'Authorization: Bearer ' . $bearer_token
			),
		) );

		curl_exec( $curl );
		curl_close( $curl );
	}
}

if ( ! function_exists( 'iwp_process_curl_response' ) ) {
	function iwp_process_curl_response( $curl_response, $curl_session, $response_headers, &$errors_counter, &$slow_sleep, $calling_from = '' ) {

		$header_message = $response_headers['x-iwp-message'] ?? '';
		$status_code    = curl_getinfo( $curl_session, CURLINFO_HTTP_CODE );

		if ( $curl_errno = curl_errno( $curl_session ) ) {

			$return_message = 'Request to the serve script is failed. Curl response: ' . json_encode( $curl_response ) . 'Here is the actual error message' . curl_error( $curl_session ) . ' -  ' . curl_errno( $curl_session );

			iwp_send_migration_log( $calling_from . ': Curl is failed', $return_message );

			sleep( iwp_backoff_timer( $errors_counter ) );

			$errors_counter ++;

			if ( $curl_errno == 52 ) {
				if ( $slow_sleep < 60 ) {
					$slow_sleep ++;
				}
			}
		} else if ( $curl_response === false ) {

			$return_message = 'Empty response received from the serve script. Actual error message: ' . curl_error( $curl_session );

			iwp_send_migration_log( $calling_from . ': Curl response is false', $return_message );

			sleep( iwp_backoff_timer( $errors_counter ) );

			$errors_counter ++;
		} else if ( $status_code != 200 ) {

			$return_message = 'Response status code is not 200 form serve script. Request ' . $errors_counter . ' is: ' . $status_code . ', Automatically the migration script is going to retry the same request soon. Full response:' . json_encode( $curl_response );

			iwp_send_migration_log( $calling_from . ': Curl status code is unexpected', $return_message );

			$errors_counter ++;

			// if empty , forbidden or too many requests
			if ( $status_code == 0 || $status_code == 403 || $status_code == 429 ) {
				sleep( iwp_backoff_timer( $errors_counter ) );
			}
		} else {
			$errors_counter = 0;

			return [ 'success' => true, 'status_code' => $status_code, 'message' => 'Response Header Message: ' . $header_message ];
		}

		$return_message .= ' Response header message: ' . $header_message;

		return [ 'success' => false, 'status_code' => $status_code, 'message' => $return_message ];
	}
}

if ( ! function_exists( 'iwp_process_curl_headers' ) ) {
	function iwp_process_curl_headers( $curl, $header, &$headers ) {
		$length      = strlen( $header );
		$headerParts = explode( ':', $header, 2 );

		if ( count( $headerParts ) < 2 ) {
			return $length;
		}

		$name             = strtolower( trim( $headerParts[0] ) );
		$value            = trim( $headerParts[1] );
		$headers[ $name ] = $value;

		return $length;
	}
}

if ( ! function_exists( 'iwp_is_serialized' ) ) {
	function iwp_is_serialized( $data, $strict = true ) {
		// If it isn't a string, it isn't serialized.
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}
		if ( $strict ) {
			$lastc = substr( $data, - 1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return false;
			}
		} else {
			$semicolon = strpos( $data, ';' );
			$brace     = strpos( $data, '}' );
			// Either ; or } must exist.
			if ( false === $semicolon && false === $brace ) {
				return false;
			}
			// But neither must be in the first X characters.
			if ( false !== $semicolon && $semicolon < 3 ) {
				return false;
			}
			if ( false !== $brace && $brace < 4 ) {
				return false;
			}
		}
		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( $strict ) {
					if ( '"' !== substr( $data, - 2, 1 ) ) {
						return false;
					}
				} elseif ( ! str_contains( $data, '"' ) ) {
					return false;
				}
			// Or else fall through.
			case 'a':
			case 'O':
			case 'E':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				$end = $strict ? '$' : '';

				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
		}

		return false;
	}
}

if ( ! function_exists( 'iwp_maybe_serialize' ) ) {
	function iwp_maybe_serialize( $data ) {
		if ( is_array( $data ) || is_object( $data ) ) {
			return serialize( $data );
		}

		if ( iwp_is_serialized( $data, false ) ) {
			return serialize( $data );
		}

		return $data;
	}
}


function iwp_recursive_unserialize_replace( $data, $search_replace ) {

	if ( is_string( $data ) ) {
		return str_replace( array_keys( $search_replace ), array_values( $search_replace ), $data );
	}

	if ( is_array( $data ) ) {
		$data = array_map( function ( $item ) use ( $search_replace ) {
			return iwp_recursive_unserialize_replace( $item, $search_replace );
		}, $data );
	} elseif ( is_object( $data ) ) {
		// Check if the object is __PHP_Incomplete_Class
		if ( $data instanceof __PHP_Incomplete_Class ) {
			$className = get_class( $data );
			iwp_send_migration_log( 'Incomplete Class Warning', "Encountered incomplete class: $className. Make sure this class is loaded before unserialization.", [ 'class' => $className ] );

			return $data;
		}

		$properties = [];

		try {
			$reflection = new ReflectionObject( $data );
			$properties = $reflection->getProperties();
		} catch ( Exception $e ) {
			iwp_send_migration_log(
				'Reflection Error',
				"Failed to reflect object of class " . get_class( $data ),
				[ 'error' => $e->getMessage() ]
			);

			return $data;
		}

		foreach ( $properties as $property ) {
			try {
				$property->setAccessible( true );
				$value     = $property->getValue( $data );
				$new_value = iwp_recursive_unserialize_replace( $value, $search_replace );
				$property->setValue( $data, $new_value );
			} catch ( Exception $e ) {
				// Skip this property if we can't access it
				continue;
			}
		}
	}

	return $data;
}

if ( ! function_exists( 'iwp_maybe_unserialize' ) ) {
	function iwp_maybe_unserialize( $data ) {
		if ( iwp_is_serialized( $data ) ) {
			global $search_replace;

			$data = @unserialize( trim( $data ) );

			if ( is_array( $data ) ) {
				$data = iwp_recursive_unserialize_replace( $data, $search_replace );
			}
		}

		return $data;
	}
}

if ( ! function_exists( 'iwp_array_filter_recursive' ) ) {
	function iwp_array_filter_recursive( array $array, callable $callback = null ) {
		$array = is_callable( $callback ) ? array_filter( $array, $callback ) : array_filter( $array );
		foreach ( $array as &$value ) {
			if ( is_array( $value ) ) {
				$value = call_user_func( __FUNCTION__, $value, $callback );
			}
		}

		return $array;
	}
}

if ( ! function_exists( 'iwp_parse_db_data' ) ) {
	function iwp_parse_db_data( $data ) {
		$values = iwp_maybe_unserialize( $data );

		if ( is_array( $values ) && ! empty( $values ) ) {
			$data = iwp_maybe_serialize( iwp_array_filter_recursive( $values ) );
		}

		return $data;
	}
}

if ( ! function_exists( 'iwp_search_and_comment_specific_line' ) ) {
	function iwp_search_and_comment_specific_line( $pattern, $file_contents ) {

		$matches = array();

		if ( preg_match_all( $pattern, $file_contents, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$line_content  = strtok( substr( $file_contents, $match[1] ), "\n" );
				$file_contents = str_replace( $line_content, "// $line_content", $file_contents );
			}
		}

		return $file_contents;
	}
}

if ( ! function_exists( 'iwp_process_files' ) ) {
	function iwp_process_files( $filePath, $relativePath ) {

		global $target_url;

		if ( basename( $relativePath ) === '.htaccess' ) {

			$content  = file_get_contents( $filePath );
			$tmp_file = tempnam( sys_get_temp_dir(), 'htaccess' );

			$dest_domain       = str_replace( array( 'http://', 'https://', 'wp-content/plugins/instawp-connect/dest.php', 'dest.php' ), '', $target_url );
			$dest_domain       = rtrim( $dest_domain, '/' );
			$dest_domain_parts = explode( '/', $dest_domain );

			if ( isset( $dest_domain_parts[0] ) ) {
				unset( $dest_domain_parts[0] );
			}

			$dest_subdomain = implode( '/', $dest_domain_parts );

			if ( ! empty( $dest_subdomain ) ) {
				$content = str_replace( "RewriteBase /", "RewriteBase /{$dest_subdomain}/", $content );
				$content = str_replace( "RewriteRule . /index.php", "RewriteRule . /{$dest_subdomain}/index.php", $content );
			}

			if ( file_put_contents( $tmp_file, $content ) ) {
				$filePath = $tmp_file;
			}
		}

		return $filePath;
	}
}

if ( ! function_exists( 'iwp_get_iterator_items' ) ) {
	function iwp_get_iterator_items( $skip_folders, $relativePath ) {
		$filter_directory = function ( SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator ) use ( $skip_folders ) {
			$relative_path = ! empty( $iterator->getSubPath() ) ? $iterator->getSubPath() . DIRECTORY_SEPARATOR . $file->getBasename() : $file->getBasename();

			if ( in_array( $relative_path, $skip_folders ) ) {
				return false;
			}

			return ! in_array( $iterator->getSubPath(), $skip_folders );
		};
		$directory        = new RecursiveDirectoryIterator( $relativePath, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );

		return new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( $directory, $filter_directory ), RecursiveIteratorIterator::LEAVES_ONLY );
	}
}

if ( ! function_exists( 'iwp_get_files_array' ) ) {
	/**
	 * Retrieves an array of files organized by specified WordPress directory groups.
	 *
	 * This function iterates over files within a given directory, filtering
	 * and categorizing them into predefined WordPress directory groups such as
	 * 'uploads', 'plugins', 'themes', etc. Files not matching any specific
	 * group are categorized under 'root_other'.
	 *
	 * @param array $excluded_paths An array of directory paths to be excluded from the iteration.
	 * @param string $relative_path The base directory path to iterate over and apply filtering.
	 *
	 * @return array An associative array where keys represent WordPress directory groups and values are
	 *               arrays of files belonging to those groups.
	 */
	function iwp_get_files_array( $excluded_paths, $relative_path ) {
		try {
			$iterator = iwp_get_iterator_items( $excluded_paths, $relative_path );
			// file categories
			$file_category = array(
				'uploads'     => 'wp-content' . DIRECTORY_SEPARATOR . 'uploads',
				'plugins'     => 'wp-content' . DIRECTORY_SEPARATOR . 'plugins',
				'themes'      => 'wp-content' . DIRECTORY_SEPARATOR . 'themes',
				'mu_plugins'  => 'wp-content' . DIRECTORY_SEPARATOR . 'mu-plugins',
				'wp_content'  => 'wp-content',
				'wp_admin'    => 'wp-admin',
				'wp_includes' => 'wp-includes',
				'index'       => 'index.php'
			);

			$files_array = array(
				'uploads'     => array(),
				'plugins'     => array(),
				'themes'      => array(),
				'mu_plugins'  => array(),
				'wp_content'  => array(),
				'wp_admin'    => array(),
				'wp_includes' => array(),
				'root_other'  => array(),
				'index'       => array()
			);

			// Get the file groups
			$file_groups = array_keys( array_intersect_key( $files_array, $file_category ) );
			// Array to check files details printing
			$check_file_groups = array();

			foreach ( $iterator as $file ) {
				$filepath          = $file->getPathname();
				$relative_filepath = str_replace( $relative_path, '', $filepath );
				$relative_filepath = trim( $relative_filepath, DIRECTORY_SEPARATOR );

				// Flag to check if the file belongs to any of the file groups
				$is_file_group_matched = false;

				// Check if the file belongs to any of the file groups
				foreach ( $file_groups as $file_group ) {
					if ( 0 === strpos( $relative_filepath, $file_category[ $file_group ] ) ) {
						$files_array[ $file_group ][] = $file;
						$is_file_group_matched        = true;
						if ( empty( $check_file_groups[ $file_group ] ) ) {
							echo "File group matched: {$file_group} \n filepath: $filepath \n relative filepath: $relative_filepath \n";
							$check_file_groups[ $file_group ] = 1;
						}
						break;
					}
				}

				// If the file does not belong to any of the file groups, add it to the root_other group
				if ( false === $is_file_group_matched ) {
					$files_array['root_other'][] = $file;
				}
			}

			return $files_array;
		} catch ( Exception $e ) {
			iwp_send_migration_log( 'push-files: Failed to get files', $e->getMessage() );

			return array();
		}
	}
}

if ( ! function_exists( 'iwp_get_zip_status_string' ) ) {
	function iwp_get_zip_status_string( $status ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return 'ZipArchive Extension is not enabled!';
		}

		switch ( $status ) {
			case ZipArchive::ER_OK:
				return 'No error';
			case ZipArchive::ER_MULTIDISK:
				return 'Multi-disk zip archives not supported';
			case ZipArchive::ER_RENAME:
				return 'Renaming temporary file failed';
			case ZipArchive::ER_CLOSE:
				return 'Closing zip archive failed';
			case ZipArchive::ER_SEEK:
				return 'Seek error';
			case ZipArchive::ER_READ:
				return 'Read error';
			case ZipArchive::ER_WRITE:
				return 'Write error';
			case ZipArchive::ER_CRC:
				return 'CRC error';
			case ZipArchive::ER_ZIPCLOSED:
				return 'Containing zip archive was closed';
			case ZipArchive::ER_NOENT:
				return 'No such file';
			case ZipArchive::ER_EXISTS:
				return 'File already exists';
			case ZipArchive::ER_OPEN:
				return 'Can\'t open file';
			case ZipArchive::ER_TMPOPEN:
				return 'Failure to create temporary file';
			case ZipArchive::ER_ZLIB:
				return 'Zlib error';
			case ZipArchive::ER_MEMORY:
				return 'Malloc failure';
			case ZipArchive::ER_CHANGED:
				return 'Entry has been changed';
			case ZipArchive::ER_COMPNOTSUPP:
				return 'Compression method not supported';
			case ZipArchive::ER_EOF:
				return 'Premature EOF';
			case ZipArchive::ER_INVAL:
				return 'Invalid argument';
			case ZipArchive::ER_NOZIP:
				return 'Not a zip archive';
			case ZipArchive::ER_INTERNAL:
				return 'Internal error';
			case ZipArchive::ER_INCONS:
				return 'Zip archive inconsistent';
			case ZipArchive::ER_REMOVE:
				return 'Can\'t remove file';
			case ZipArchive::ER_DELETED:
				return 'Entry has been deleted';
			default:
				return 'Unknown status: ' . $status;
		}
	}
}

if ( ! function_exists( 'iwp_verify_checksum' ) ) {
	function iwp_verify_checksum( $local_file_path, $expected_checksum, $received_filesize ) {

		if ( ! file_exists( $local_file_path ) ) {
			echo "File does not exist: $local_file_path\n";

			return false;
		}

		$local_file_name = basename( $local_file_path );
		$local_file_size = filesize( $local_file_path );

		if ( $local_file_size === false ) {
			$local_file_size = 0;
			iwp_send_migration_log( 'fetch-files: File size check failed', "Could not get file size for: $local_file_name" );
		}

		$calculated_checksum    = hash( 'crc32b', $local_file_name . $local_file_size );
		$verify_checksum_result = $calculated_checksum === $expected_checksum;

		if ( ! $verify_checksum_result ) {
			iwp_send_migration_log( 'fetch-files: Checksum mismatch', "Checksum verification failed for zip file: $local_file_name",
				array(
					'calculated_checksum' => $calculated_checksum,
					'expected_checksum'   => $expected_checksum,
					'local_file_path'     => $local_file_path,
					'local_file_size'     => $local_file_size,
					'received_filesize'   => $received_filesize,
				)
			);
		}

		return $verify_checksum_result;
	}
}

if ( ! function_exists( 'iwp_unmark_sent_files' ) ) {
	function iwp_unmark_sent_files( $target_url, $sent_filename, $checksum, $curl_fields ) {
		$curl          = curl_init( $target_url );
		$unmark_fields = array_merge( $curl_fields, array(
			'serve_type'    => 'unmark_sent_files',
			'sent_filename' => $sent_filename,
			'checksum'      => $checksum,
		) );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $unmark_fields );
		curl_setopt( $curl, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
			return iwp_process_curl_headers( $curl, $header, $headers );
		} );

		curl_exec( $curl );
		curl_close( $curl );
	}
}

if ( ! function_exists( 'iwp_inventory_sent_files' ) ) {
	function iwp_inventory_sent_files( $target_url, $slug, $item_type, $curl_fields, $failed_items = array() ) {
		$curl          = curl_init( $target_url );
		$unmark_fields = array_merge( $curl_fields, array(
			'serve_type'   => 'inventory_sent_files',
			'slug'         => $slug,
			'failed_items' => $failed_items,
			'item_type'    => $item_type,
		) );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $unmark_fields ) );
		curl_setopt( $curl, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
			return iwp_process_curl_headers( $curl, $header, $headers );
		} );

		curl_exec( $curl );
		curl_close( $curl );
	}
}

if ( ! function_exists( 'iwp_progress_bar' ) ) {
	/**
	 * Output a simple progress bar to the console.
	 *
	 * @param int $done  The number of items already processed.
	 * @param int $total The total number of items to be processed.
	 * @param int $width Optional. The width of the progress bar in characters. Default is 50.
	 */
	function iwp_progress_bar($done, $total, $width = 50, $migrate_mode = '') {
		// Calculate the progress
		$progress = ($done / $total);
		$barWidth = floor($progress * $width);
		$percent = floor($progress * 100);

		if ( in_array( $migrate_mode, array( 'push', 'pull' ) ) ) {
			echo date( "H:i:s" ) . ": Progress: $percent%\n";
		}

		if ( 'cli' !== php_sapi_name() ) {
			return;
		}
	
		$bar = str_repeat('-', $barWidth);
		$spaces = str_repeat(' ', $width - $barWidth);
	
		printf("\r[%s%s] %d%% ", $bar, $spaces, $percent);
	
		if ($done === $total) {
			echo PHP_EOL; // Move to the next line when complete
		}
	}
}
