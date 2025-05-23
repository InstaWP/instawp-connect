<?php
// phpcs:disable
if ( ! function_exists( 'iwp_debug' ) ) {
	function iwp_debug( $data ) {

		if ( is_array( $data ) || is_object( $data ) ) {
			$data = json_encode( $data );
		}

		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: ' . $data );

		exit();
	}
}

if ( ! function_exists( 'iwp_get_wp_root_directory' ) ) {
	function iwp_get_wp_root_directory( $find_with_files = 'wp-load.php', $find_with_dir = '' ) {
		$is_find_root_dir = true;
		$root_path        = '';

		if ( ! empty( $find_with_files ) ) {
			$level            = 0;
			$root_path_dir    = __DIR__;
			$root_path        = __DIR__;
			$is_find_root_dir = true;

			while ( ! file_exists( $root_path . DIRECTORY_SEPARATOR . $find_with_files ) ) {

				++ $level;
				$root_path = dirname( $root_path_dir, $level );

				if ( $level > 10 ) {
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
				$root_path = dirname( $root_path_dir, $level );

				if ( $level > 10 ) {
					$is_find_root_dir = false;
					break;
				}
			}
		}

		return array(
			'status'    => $is_find_root_dir,
			'root_path' => $root_path,
		);
	}
}

if ( ! function_exists( 'iwp_get_root_dir' ) ) {
	function iwp_get_root_dir() {

		$root_dir_data = iwp_get_wp_root_directory();
		$root_dir_find = isset( $root_dir_data['status'] ) ? $root_dir_data['status'] : false;

		if ( ! $root_dir_find ) {
			$root_dir_data = iwp_get_wp_root_directory( 'wp-config.php' );
			$root_dir_find = isset( $root_dir_data['status'] ) ? $root_dir_data['status'] : false;
		}

		if ( ! $root_dir_find ) {
			$root_dir_data = iwp_get_wp_root_directory( '', 'flywheel-config' );
			$root_dir_find = isset( $root_dir_data['status'] ) ? $root_dir_data['status'] : false;
		}

		if ( ! $root_dir_find ) {
			$root_dir_data = iwp_get_wp_root_directory( '', 'wp' );
		}

		return $root_dir_data;
	}
}

if ( ! function_exists( 'parse_wp_db_host' ) ) {
	function parse_wp_db_host( $host ) {
		$socket  = null;
		$is_ipv6 = false;

		$socket_pos = strpos( $host, ':/' );
		if ( false !== $socket_pos ) {
			$socket = substr( $host, $socket_pos + 1 );
			$host   = substr( $host, 0, $socket_pos );
		}

		if ( substr_count( $host, ':' ) > 1 ) {
			$pattern = '#^(?:\[)?(?P<host>[0-9a-fA-F:]+)(?:\]:(?P<port>[\d]+))?#';
			$is_ipv6 = true;
		} else {
			$pattern = '#^(?P<host>[^:/]*)(?::(?P<port>[\d]+))?#';
		}

		$matches = array();
		$result  = preg_match( $pattern, $host, $matches );

		if ( 1 !== $result ) {
			return false;
		}

		$host = ! empty( $matches['host'] ) ? $matches['host'] : '';
		$port = ! empty( $matches['port'] ) ? abs( (int) $matches['port'] ) : null;

		return array( $host, $port, $socket, $is_ipv6 );
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	function str_contains( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}

		return false !== strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	function str_starts_with( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	function str_ends_with( $haystack, $needle ) {
		if ( '' === $haystack ) {
			return '' === $needle;
		}

		$len = strlen( $needle );

		return substr( $haystack, - $len, $len ) === $needle;
	}
}

if ( ! function_exists( 'array_contains_str' ) ) {
	function array_contains_str( $string, $array ) {
		if ( in_array( $string, $array, true ) ) {
			return true;
		}

//		foreach ( $array as $item ) {
//			if ( str_contains( $string, $item ) ) {
//				return true;
//			}
//		}

		return false;
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

if ( ! function_exists( 'zipStatusString' ) ) {
	function zipStatusString( $status ) {
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

if ( ! function_exists( 'iwp_backoff_timer' ) ) {
	function iwp_backoff_timer( $attempt, $base = 2, $maxWait = 300 ) {
		// Calculate the backoff time
		$waitTime = min( $base ** $attempt, $maxWait );

		echo "Backing off .. $waitTime seconds\n";

		// Return the wait time
		return $waitTime;
	}
}

if ( ! function_exists( 'get_server_temp_dir' ) ) {
	function get_server_temp_dir() {
		if ( function_exists( 'sys_get_temp_dir' ) ) {
			$temp = sys_get_temp_dir();
			if ( @is_dir( $temp ) && is_writable( $temp ) ) {
				return $temp . '/';
			}
		}

		$temp = ini_get( 'upload_tmp_dir' );
		if ( @is_dir( $temp ) && is_writable( $temp ) ) {
			return $temp . '/';
		}

		$temp = WP_ROOT . '/';
		if ( is_dir( $temp ) && is_writable( $temp ) ) {
			return $temp;
		}

		return '/tmp/';
	}
}

if ( ! function_exists( 'readfile_chunked' ) ) {
	function readfile_chunked( $filename, $retbytes = true ) {
		$cnt    = 0;
		$handle = fopen( $filename, 'rb' );

		if ( $handle === false ) {
			return false;
		}

		while ( ! feof( $handle ) ) {
			$buffer = fread( $handle, CHUNK_SIZE );
			echo $buffer;
			ob_flush();
			flush();

			if ( $retbytes ) {
				$cnt += strlen( $buffer );
			}
		}

		$status = fclose( $handle );

		if ( $retbytes && $status ) {
			return $cnt;
		}

		return $status;
	}
}

if ( ! function_exists( 'send_by_zip' ) ) {
	function send_by_zip( IWPDB $tracking_db, $unsentFiles = array(), $progress_percentage = '', $archiveType = 'ziparchive', $handle_config_separately = false ) {
		header( 'Content-Type: zip' );
		header( 'x-file-type: zip' );
		header( 'x-iwp-progress: ' . $progress_percentage );

		$tmpZip          = tempnam( get_server_temp_dir(), 'batchzip' );
		$zipSuccessFiles = array();

		if ( $archiveType === 'ziparchive' ) {
			$archive = new ZipArchive();

			if ( $archive->open( $tmpZip, ZipArchive::OVERWRITE ) !== true ) {
				die( "Cannot open zip archive" );
			}
		} elseif ( $archiveType === 'phardata' ) {
			$tmpZip  .= '.zip';
			$archive = new PharData( $tmpZip );
		} else {
			die( "Invalid archive type" );
		}

		$tmpZipName = basename( $tmpZip );
		header( 'x-iwp-filename: ' . $tmpZipName );
		header( 'x-iwp-filepath: ' . $tmpZip );

		foreach ( $unsentFiles as $file ) {
			$tracking_db->update( 'iwp_files_sent', array( 'sent' => 2 ), array( 'id' => $file['id'] ) );

			$filePath         = isset( $file['filepath'] ) ? $file['filepath'] : '';
			$relativePath     = ltrim( str_replace( WP_ROOT, "", $filePath ), DIRECTORY_SEPARATOR );
			$filePath         = process_files( $tracking_db, $filePath, $relativePath );
			$file_fopen_check = fopen( $filePath, 'r' );
			$file_name        = basename( $filePath );

			if ( ! $file_fopen_check ) {
				$tracking_db->update( 'iwp_files_sent', array( 'sent' => 3 ), array( 'id' => $file['id'] ) ); // mark as failed
				error_log( 'Can not open file: ' . $filePath );

				header( 'x-iwp-error: ' . 'Can not open the file: : ' . $filePath );

				continue;
			}

			fclose( $file_fopen_check );

			if ( ! is_readable( $filePath ) ) {
				$tracking_db->update( 'iwp_files_sent', array( 'sent' => 3 ), array( 'id' => $file['id'] ) ); // mark as failed
				error_log( 'Can not read file: ' . $filePath );

				header( 'x-iwp-error: ' . 'Can not read the file: : ' . $filePath );

				continue;
			}

			if ( ! is_file( $filePath ) ) {
				$tracking_db->update( 'iwp_files_sent', array( 'sent' => 3 ), array( 'id' => $file['id'] ) ); // mark as failed
				error_log( 'Invalid file: ' . $filePath );

				header( 'x-iwp-error: ' . 'Invalid file: : ' . $filePath );

				continue;
			}

			if ( $handle_config_separately && $file_name === 'wp-config.php' ) {
				$relativePath = $file_name;
			}

			$added_to_zip = true;
			try {
				if ( $archiveType === 'ziparchive' ) {
					$added_to_zip = $archive->addFile( $filePath, $relativePath );
				} else {
					$archive->addFile( $filePath, $relativePath );
				}
			} catch ( Exception $e ) {
				$added_to_zip = false;
				header( 'x-iwp-error: ' . 'Exception when adding file to archive: ' . $e->getMessage() );
			}

			if ( ! $added_to_zip ) {
				$tracking_db->update( 'iwp_files_sent', array( 'sent' => 3 ), array( 'id' => $file['id'] ) ); // mark as failed
				header( 'x-iwp-error: ' . 'Could not add to zip, here is the file path - ' . $filePath );
			} else {
				$zipSuccessFiles[] = $file;
			}
		}

		header( 'x-iwp-sent-filename: ' . $tmpZipName );

		try {
			if ( $archiveType === 'ziparchive' ) {
				$archive->close();
			}

			$fileSize  = filesize( $tmpZip );
			$fileMTime = filemtime( $tmpZip );
			$checksum  = hash( 'crc32b', $tmpZipName . $fileSize );

			header( 'x-iwp-filesize: ' . $fileSize );
			header( 'x-iwp-checksum: ' . $checksum );

			readfile_chunked( $tmpZip );
		} catch ( Exception $exception ) {
			header( 'x-iwp-status: false' );
			header( 'x-iwp-message: The migration script could not read this specific file. Actual exception message is: ' . $exception->getMessage() );
		}

		foreach ( $zipSuccessFiles as $file ) {
			$tracking_db->update( 'iwp_files_sent', array( 'sent' => 1, 'sent_filename' => $tmpZipName, 'checksum' => $checksum ), array( 'id' => $file['id'] ) );
		}

		unlink( $tmpZip );
	}
}

if ( ! function_exists( 'search_and_comment_specific_line' ) ) {
	function search_and_comment_specific_line( $pattern, $file_contents ) {
		$lines         = explode( "\n", $file_contents );
		$updated_lines = array();

		foreach ( $lines as $line ) {
			$trimmed_line = trim( $line );
			if ( empty( $trimmed_line ) || strpos( $trimmed_line, '//' ) === 0 || strpos( $trimmed_line, '*' ) === 0 ) {
				$updated_lines[] = $line;
			} elseif ( preg_match( $pattern, $line ) ) {
				$updated_lines[] = "// " . $line;
			} else {
				$updated_lines[] = $line;
			}
		}

		return implode( "\n", $updated_lines );
	}
}

if ( ! function_exists( 'process_files' ) ) {
	function process_files( IWPDB $tracking_db, $filePath, $relativePath ) {
		$site_url         = $tracking_db->get_option( 'site_url' );
		$dest_url         = $tracking_db->get_option( 'dest_url' );
		$migrate_settings = $tracking_db->get_option( 'migrate_settings' );
		$options          = isset( $migrate_settings['options'] ) ? $migrate_settings['options'] : array();

		if ( basename( $relativePath ) === '.htaccess' ) {

			$content  = file_get_contents( $filePath );
			$tmp_file = tempnam( get_server_temp_dir(), 'htaccess' );

			// RSSR Support
			$pattern = '/#Begin Really Simple SSL Redirect.*?#End Really Simple SSL Redirect/s';
			$content = preg_replace( $pattern, '', $content );

			// MalCare Support
			$pattern = '/#MalCare WAF.*?#END MalCare WAF/s';
			$content = preg_replace( $pattern, '', $content );

			// Comment any any php_value
			$content = preg_replace( '/^\s*php_value\s+/m', '# php_value ', $content );
			$content = preg_replace( '/^\s*php_flag\s+/m', '# php_flag ', $content );

			// Comment some unnecessary lines in htaccess
			$content = preg_replace( '/^(.*AuthGroupFile.*)$/m', '# $1', $content );
			$content = preg_replace( '/^(.*AuthUserFile.*)$/m', '# $1', $content );
			$content = preg_replace( '/^(.*AuthName.*)$/m', '# $1', $content );
			$content = preg_replace( '/^(.*ErrorDocument.*)$/m', '# $1', $content );
			$content = str_replace( 'SetHandler proxy:fcgi://continental-php82', '# SetHandler proxy:fcgi://continental-php82', $content );
			$content = preg_replace( '/^(.*proxy:fcgi.*)$/m', '# $1', $content );

			if ( ! empty( $site_url ) ) {
				$url_path = parse_url( $site_url, PHP_URL_PATH );
				$content  .= "\n# url_path: $url_path\n";

				if ( ! empty( $url_path ) && $url_path !== '/' ) {
					$content .= "\n# url_path_inside: $url_path\n";

					$content = preg_replace( '/RewriteBase\s+\/([^\/]+)\//', 'RewriteBase /', $content );
					$content = preg_replace( "/(RewriteRule\s+\.\s+\/)([^\/]+)/", 'RewriteRule . ', $content );

					/**
					 * @todo will finalize the logic latter
					 */
//						$content = str_replace( $url_path, '/', $content );
//						$content = str_replace( "RewriteBase //", "RewriteBase /", $content );
//						$content = str_replace( "RewriteRule . //index.php", "RewriteRule . /index.php", $content );
				}

				if ( in_array( 'skip_media_folder', $options ) ) {
					$htaccess_content = array(
						'## BEGIN InstaWP Connect',
						'<IfModule mod_rewrite.c>',
						'RewriteEngine On',
						'RewriteCond %{REQUEST_FILENAME} !-f',
						'RewriteRule ^wp-content/uploads/(.*)$ ' . $site_url . '/wp-content/uploads/$1 [R=301,L]',
						'</IfModule>',
						'## END InstaWP Connect',
					);
					$htaccess_content = implode( "\n", $htaccess_content );
					$content          = $htaccess_content . "\n\n" . $content;
				}
			}

			if ( file_put_contents( $tmp_file, $content ) ) {
				$filePath = $tmp_file;
			}
		} elseif ( $relativePath === 'wp-config.php' ) {
			$file_contents = file_get_contents( $filePath );
			$file_contents = str_replace( $site_url, $dest_url, $file_contents );

			// Flywheel support
			$file_contents = str_replace( "define('ABSPATH', dirname(__FILE__) . '/.wordpress/');", "define( 'ABSPATH', dirname( __FILE__ ) . '/' );", $file_contents );

			// GridPane Support
			$file_contents = str_replace( "include __DIR__ . '/user-configs.php';", "// include __DIR__ . '/user-configs.php';", $file_contents );
			$file_contents = str_replace( "include __DIR__ . '/wp-fail2ban-configs.php';", "// include __DIR__ . '/wp-fail2ban-configs.php';", $file_contents );
			$file_contents = str_replace( "include __DIR__ . '/smtp-provider-wp-configs.php';", "// include __DIR__ . '/smtp-provider-wp-configs.php';", $file_contents );

			// Comment WP_SITEURL constant
			$file_contents = search_and_comment_specific_line( "/define\(\s*'WP_SITEURL'/", $file_contents );

			// Comment WP_HOME constant
			$file_contents = search_and_comment_specific_line( "/define\(\s*'WP_HOME'/", $file_contents );

			// Comment COOKIE_DOMAIN constant
			$file_contents = search_and_comment_specific_line( "/define\(\s*'COOKIE_DOMAIN'/", $file_contents );

			$tmp_file = tempnam( get_server_temp_dir(), 'wp-config' );
			if ( file_put_contents( $tmp_file, $file_contents ) ) {
				$filePath = $tmp_file;
			}
		} elseif ( $relativePath === 'index.php' ) {
			$file_contents = file_get_contents( $filePath );
			$file_contents = str_replace( "/.wordpress/wp-blog-header.php", "/wp-blog-header.php", $file_contents );

			$tmp_file = tempnam( get_server_temp_dir(), 'index' );
			if ( file_put_contents( $tmp_file, $file_contents ) ) {
				$filePath = $tmp_file;
			}
		}

		return $filePath;
	}
}

if ( ! function_exists( 'is_valid_file' ) ) {
	function is_valid_file( $filepath ) {
		$filename = basename( $filepath );
		if ( empty( $filename ) ) {
			return false;
		}

		// Check for disallowed characters
		$disallowed = array( '/', '\\', ':', '*', '?', '"', '<', '>', '|' );
		foreach ( $disallowed as $char ) {
			if ( strpos( $filename, $char ) !== false ) {
				return false;
			}
		}

		if ( $filename === '.' || $filename === '..' ) {
			return false;
		}

		return is_file( $filepath ) && is_readable( $filepath );
	}
}

if ( ! function_exists( 'get_iterator_items' ) ) {
	function get_iterator_items( $skip_folders, $root ) {
		$filter_directory = function ( SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator ) use ( $skip_folders ) {

			$relative_path = ! empty( $iterator->getSubPath() ) ? $iterator->getSubPath() . '/' . $file->getBasename() : $file->getBasename();

			if ( in_array( $relative_path, $skip_folders ) ) {
				return false;
			}

			return ! in_array( $iterator->getSubPath(), $skip_folders );
		};
		$directory        = new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );

		return new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( $directory, $filter_directory ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
	}
}

if ( ! function_exists( 'iwp_sanitize_key' ) ) {
	/**
	 * Sanitizes a key by lowercasing it and removing all non-alphanumeric
	 * characters except dashes and underscores.
	 *
	 * @param string $key The key to be sanitized.
	 *
	 * @return string The sanitized key.
	 */
	function iwp_sanitize_key( $key ) {
		// Lowercase the key
		$key = strtolower( $key );

		// Remove all characters except lowercase alphanumeric, dashes and underscores
		$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

		return $key;
	}
}

if ( ! function_exists( 'iwp_backup_wp_core_folders' ) ) {
	/**
	 * Backs up core WordPress folders (plugins, themes, mu-plugins) to a datestamped
	 * folder. If the source folder does not exist, it will be skipped. If the backup
	 * folder already exists, it will be skipped. If the backup folder cannot be
	 * created, an error message will be added to the result.
	 *
	 * @param string $root_dir_path The root directory of WordPress.
	 * @param array $excluded_paths Paths to exclude from deletion.
	 *
	 * @return array An associative array with the following keys:
	 *     - status: A boolean indicating whether the backup was successful.
	 *     - messages: An array of success or error messages.
	 *     - excluded_deletion: An array of paths that were excluded from deletion.
	 */
	function iwp_backup_wp_core_folders( $root_dir_path, $excluded_paths = array(), $timestamp = '' ) {
		$timestamp = empty( $timestamp ) ? date( 'YmdHi' ) : $timestamp;
		$result    = array(
			'status'   => true,
			'messages' => array(),
		);

		$folders_to_backup = array(
			'plugins',
			'themes',
			'mu-plugins',
		);

		foreach ( $folders_to_backup as $folder ) {
			try {
				$source_path = $root_dir_path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . $folder;
				// Skip if source doesn't exist
				if ( ! file_exists( $source_path ) ) {
					$result['messages'][] = "Notice: {$folder} folder does not exist, skipping backup.";
					continue;
				}
				// Add datestamp to backup folder
				$backup_path = $source_path . '-' . $timestamp;

				// Skip if backup directory already exists
				if ( file_exists( $backup_path ) ) {
					$result['messages'][] = "Notice: {$backup_path} folder already exist, skipping backup.";
					continue;
				}

				// Create backup directory if it doesn't exist
				if ( ! is_dir( $backup_path ) && ! mkdir( $backup_path, 0777, true ) ) {
					$result['messages'][] = "Failed to create {$folder} backup directory: {$backup_path}";
					continue;
				}

				// Copy files from source to backup directory
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $source_path, RecursiveDirectoryIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::SELF_FIRST
				);

				foreach ( $iterator as $item ) {
					// Get relative path of source file or folder
					$relative_path = str_replace( $source_path, '', $item->getPathname() );
					// Remove the first and last slash from the relative path
					$relative_path = trim( $relative_path, DIRECTORY_SEPARATOR );
					// Get target file or folder
					$target = $backup_path . DIRECTORY_SEPARATOR . $relative_path;
					if ( $item->isDir() ) {
						if ( ! is_dir( $target ) && ! mkdir( $target, 0777, true ) ) {
							$result['messages'][] = "Failed to create backup directory: {$target}";
							continue 2;
						}
					} else if ( ! file_exists( $target ) && ! copy( $item->getPathname(), $target ) ) {
						$result['messages'][] = "Failed to copy file: {$item->getPathname()} to {$target}";
						continue 2;
					}
				}

				// Success
				$result['messages'][] = "Success: {$folder} folder backed up to " . basename( $backup_path );

				// Delete the source folder
				$folder_iterator = new DirectoryIterator( $source_path );
				foreach ( $folder_iterator as $folder_item ) {
					if ( $folder_item->isDot() ) {
						continue;
					}

					$remove_path          = $folder_item->getPathname();
					$remove_relative_path = str_replace( $root_dir_path . DIRECTORY_SEPARATOR, '', $remove_path );

					/**
					 * Skip excluded paths, folder items and files with "instawp-connect" in
					 * their name
					 */
					if ( in_array( $remove_relative_path, $excluded_paths ) || false !== stripos( $folder_item->getFilename(), 'instawp-connect' ) || ! is_dir( $remove_path ) ) {
						if ( is_dir( $remove_path ) ) {
							$result['excluded_deletion'][] = $remove_relative_path;
						}
						continue;
					}

					/**
					 * Recursively remove all files and directories in the folder. Process deepest items first
					 * UNIX_PATHS ensure proper handling of hidden files during directory deletion
					 */
					$remove_items = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator(
							$remove_path,
							RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS
						),
						RecursiveIteratorIterator::CHILD_FIRST
					);

					foreach ( $remove_items as $remove_item ) {
						if ( $remove_item->isDir() ) {
							if ( ! rmdir( $remove_item->getPathname() ) ) {
								$result['messages'][] = "Failed to remove directory: {$remove_item->getPathname()}";
								continue 2;
							}
						} else if ( ! unlink( $remove_item->getPathname() ) ) {
							$result['messages'][] = "Failed to remove file: {$remove_item->getPathname()}";
							continue 2;
						}
					}

					if ( ! rmdir( $remove_path ) ) {
						$result['messages'][] = "Failed to remove parent directory: {$remove_path}";
					}
				}
			} catch ( Exception $e ) {
				$result['status']     = false;
				$result['messages'][] = "Error backing up {$folder}: " . $e->getMessage();
			}
		}

		return $result;
	}
}

if ( ! function_exists( 'iwp_backup_wp_database' ) ) {
	function iwp_backup_wp_database( $db_host, $db_username, $db_password, $db_name, $root_dir_path = '', $timestamp = '' ) {

		$root_dir_path = empty( $root_dir_path ) ? dirname( __FILE__ ) : $root_dir_path;
		$timestamp     = empty( $timestamp ) ? date( 'YmdHi' ) : $timestamp;
		$backup_file   = $root_dir_path . DIRECTORY_SEPARATOR . "wp-content" . DIRECTORY_SEPARATOR . "db-{$timestamp}.sql";
		$pdo           = null;
		$mysqli        = null;

		if ( extension_loaded( 'pdo_mysql' ) ) {
			try {
				$pdo = new PDO( "mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_username, $db_password );
				$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			} catch ( PDOException $e ) {
			}
		}

		if ( $pdo === null ) {
			if ( extension_loaded( 'mysqli' ) ) {
				$mysqli = new mysqli( $db_host, $db_username, $db_password, $db_name );
				if ( $mysqli->connect_error ) {
					die( "Connection failed: " . $mysqli->connect_error );
				}
				$mysqli->set_charset( "utf8" );
			} else {
				die( "Neither PDO nor mysqli extensions are available" );
			}
		}

		$output = "-- IWP Database Backup\n";
		$output .= "-- Generated: " . date( 'Y-m-d H:i:s' ) . "\n";
		$output .= "-- Database: " . $db_name . "\n\n";

		if ( $pdo ) {
			$tables = $pdo->query( "SHOW TABLES" )->fetchAll( PDO::FETCH_COLUMN );
		} else {
			$tables = array();
			$result = $mysqli->query( "SHOW TABLES" );
			while ( $row = $result->fetch_array( MYSQLI_NUM ) ) {
				$tables[] = $row[0];
			}
		}

		foreach ( $tables as $table ) {
			$output .= "\n-- Table structure for $table\n";
			$output .= "DROP TABLE IF EXISTS `$table`;\n";

			if ( $pdo ) {
				$create_table = $pdo->query( "SHOW CREATE TABLE `$table`" )->fetch( PDO::FETCH_ASSOC );
				$output       .= $create_table['Create Table'] . ";\n\n";

				// Get table data
				$rows = $pdo->query( "SELECT * FROM `$table`" )->fetchAll( PDO::FETCH_ASSOC );
			} else {
				$result       = $mysqli->query( "SHOW CREATE TABLE `$table`" );
				$create_table = $result->fetch_assoc();
				$output       .= $create_table['Create Table'] . ";\n\n";

				// Get table data
				$result = $mysqli->query( "SELECT * FROM `$table`" );
				$rows   = array();
				while ( $row = $result->fetch_assoc() ) {
					$rows[] = $row;
				}
			}

			if ( $rows ) {
				$output .= "-- Dumping data for table $table\n";

				foreach ( $rows as $row ) {
					$values = array_map( function ( $value ) use ( $pdo, $mysqli ) {
						if ( $value === null ) {
							return 'NULL';
						}

						return $pdo ? $pdo->quote( $value ) : "'" . $mysqli->real_escape_string( $value ) . "'";
					}, $row );

					$output .= "INSERT INTO `$table` (`" .
					           implode( '`, `', array_keys( $row ) ) .
					           "`) VALUES (" .
					           implode( ', ', $values ) .
					           ");\n";
				}
			}

			$output .= "\n";
		}

		if ( file_put_contents( $backup_file, $output, LOCK_EX ) === false ) {
			die( 'Failed to save backup file' );
		}

		chmod( $backup_file, 0644 );

		return [
			'db_file_path' => $backup_file,
		];
	}
}

if ( ! function_exists( 'iwp_send_plugin_theme_inventory' ) ) {

	/**
	 * Send plugin and theme inventory.
	 *
	 * @param array $migrate_settings Migration settings.
	 */
	function iwp_send_plugin_theme_inventory( $migrate_settings ) {
		if ( empty( $migrate_settings['inventory_items'] ) ) {
			$migrate_settings['inventory_items'] = array();
		}

		global $tracking_db;
		// Check if the function has already been run
		$has_run     = $tracking_db->get_option( 'instawp_inventory_sent', 0 );
		$total_files = intval( $tracking_db->db_get_option( 'total_files', '0' ) );

		if ( empty( $has_run ) && ( empty( $migrate_settings['inventory_items']['total_files'] ) || $total_files > intval( $migrate_settings['inventory_items']['total_files'] ) ) ) {
			// Set the flag to indicate the function has run
			$tracking_db->update_option( 'instawp_inventory_sent', 1 );
			if ( ! empty( $migrate_settings['inventory_items']['with_checksum'] ) ) {
				foreach ( $migrate_settings['inventory_items']['with_checksum'] as $inventory_key => $wp_item ) {
					if ( ! empty( $wp_item['absolute_path'] ) ) {
						$filepath      = $wp_item['absolute_path'];
						$filepath_hash = hash( 'sha256', $filepath );

						$row = $tracking_db->get_row( 'iwp_files_sent', array( 'filepath_hash' => $filepath_hash ) );
						if ( ! $row ) {
							try {
								$slug     = $wp_item['slug'];
								$checksum = $wp_item['checksum'];
								$tracking_db->insert( 'iwp_files_sent', array(
									'filepath'      => "'$filepath'",
									'filepath_hash' => "'$filepath_hash'",
									'sent'          => 0,
									'size'          => $wp_item['size'],
									'file_type'     => "'inventory'",
									'sent_filename' => "'$slug'",
									'file_count'    => $wp_item['file_count'],
									'checksum'      => "'$checksum'",
								) );

								// Add only necesssary data to inventory items
								$migrate_settings['inventory_items']['with_checksum'][ $inventory_key ] = array(
									'slug'       => $wp_item['slug'],
									'version'    => $wp_item['version'],
									'type'       => $wp_item['type'],
									'path'       => $wp_item['path'],
									'file_count' => $wp_item['file_count'],
									'checksum'   => $wp_item['checksum'],
								);
							} catch ( Exception $e ) {
								header( 'x-iwp-status: false' );
								header( 'x-iwp-message: Insert to tracking database (iwp_files_sent table) was failed. Actual error message is: ' . $e->getMessage() );
								die();
							}
						}
					}

				}
			}

			$migrate_settings['inventory_items']['total_files_count'] = $total_files;

			header( 'x-iwp-status: true' );
			header( 'x-iwp-message: Inventory items sent' );
			header( 'Content-Type: application/json' );
			header( 'x-file-type: inventory' );
			echo json_encode( $migrate_settings['inventory_items'] );
			die();
		}
	}
}
// phpcs:enable