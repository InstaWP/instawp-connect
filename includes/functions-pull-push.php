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

		foreach ( $array as $item ) {
			if ( str_contains( $string, $item ) ) {
				return true;
			}
		}

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



if ( ! function_exists( 'iwp_ipp_delete_files' ) ) {
	/**
	 * Delete files.
	 * Only files that are in wp content folder will be deleted.
	 */
	function iwp_ipp_delete_files( $root_dir_path, $files = array(), $delete_folders = array() ) {
		$result    = array(
			'status'   => true,
			'messages' => array(),
		); 
		try {
			
			// Delete files
			if ( ! empty( $files ) && is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( ! empty( $file['relative_path'] ) && 0 === strpos( $file['relative_path'], 'wp-content' ) && false === strpos( $file['relative_path'], '/iwp-' ) && false === strpos( $file['relative_path'], '/instawp' ) ) {
						// file path
						$file_path =$root_dir_path . DIRECTORY_SEPARATOR . $file['relative_path'];
						// delete file
						if ( file_exists( $file_path ) && is_file( $file_path ) && ! unlink( $file_path ) ) {
							$result['status'] = false;
							$result['messages'][] = 'Failed to delete file: ' . $file_path;
						}
					}
				}
			}

			// Delete empty folders
			if ( ! empty( $delete_folders ) && is_array( $delete_folders ) ) {
				foreach ( $delete_folders as $folder ) {
					if ( ! empty( $folder['relative_path'] ) && 0 === strpos( $folder['relative_path'], 'wp-content' ) && false === strpos( $folder['relative_path'], '/iwp-' ) && false === strpos( $folder['relative_path'], '/instawp' ) ) {
						// folder path
						$folder_path = $root_dir_path . DIRECTORY_SEPARATOR . $folder['relative_path'];
						// delete empty folder
						if ( file_exists( $folder_path ) && is_dir( $folder_path ) && empty( array_diff( scandir( $folder_path ), ['.', '..'] ) ) && ! rmdir( $folder_path ) ) {
							$result['status'] = false;
							$result['messages'][] = 'Failed to delete empty folder: ' . $folder_path;
						}
					}
				}
			}
		} catch ( Exception $e ) {
			$result['status'] = false;
			$result['messages'][] = "Failed to delete files or folders: " . $e->getMessage();
		}

		return $result;
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

/*******INSTACP FUNCTIONS  ***************/
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

		return true;

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
		return true;
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
	 * @param array  $excluded_paths An array of directory paths to be excluded from the iteration.
	 * @param string $relative_path  The base directory path to iterate over and apply filtering.
	 *
	 * @return array An associative array where keys represent WordPress directory groups and values are 
	 *               arrays of files belonging to those groups.
	 */
	function iwp_get_files_array( $excluded_paths, $relative_path ) {
		try {
			$iterator = iwp_get_iterator_items( $excluded_paths, $relative_path );
			// file categories
			$file_category = array(
				'uploads' => 'wp-content' . DIRECTORY_SEPARATOR . 'uploads',
				'plugins' => 'wp-content' . DIRECTORY_SEPARATOR . 'plugins',
				'themes' => 'wp-content' . DIRECTORY_SEPARATOR . 'themes',
				'mu_plugins' => 'wp-content' . DIRECTORY_SEPARATOR . 'mu-plugins',
				'wp_content' => 'wp-content',
				'wp_admin' => 'wp-admin',
				'wp_includes' => 'wp-includes',
				'index' => 'index.php'
			);

			$files_array = array(
				'uploads' => array(),
				'plugins' => array(),
				'themes' => array(),
				'mu_plugins' => array(),
				'wp_content' => array(),
				'wp_admin' => array(),
				'wp_includes' => array(),
				'root_other' => array(),
				'index' => array()
			);

			// Get the file groups
			$file_groups = array_keys( array_intersect_key( $files_array, $file_category ) );
			// Array to check files details printing
			$check_file_groups = array();

			foreach ($iterator as $file) {
				$filepath = $file->getPathname();
				$relative_filepath = str_replace($relative_path, '', $filepath);
				$relative_filepath = trim( $relative_filepath, DIRECTORY_SEPARATOR );

				// Flag to check if the file belongs to any of the file groups
				$is_file_group_matched = false;

				// Check if the file belongs to any of the file groups
				foreach ( $file_groups as $file_group ) {
					if ( 0 === strpos( $relative_filepath, $file_category[ $file_group ] ) ) {
						$files_array[ $file_group ][] = $file;
						$is_file_group_matched = true;
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
	function iwp_verify_checksum( $file_path, $expected_checksum ) {
		if ( ! file_exists( $file_path ) ) {
			echo "File does not exist: $file_path\n";

			return false;
		}

		$file_name              = basename( $file_path );
		$file_size              = filesize( $file_path );
		$calculated_checksum    = hash( 'crc32b', $file_name . $file_size );
		$verify_checksum_result = $calculated_checksum === $expected_checksum;

//		if ( ! $verify_checksum_result ) {
//			echo "File path: $file_path\n";
//			echo "File name: $file_name\n";
//			echo "File size: $file_size\n";
//			echo "Calculated checksum: $calculated_checksum\n";
//			echo "Expected checksum: $expected_checksum\n";
//		}

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
// phpcs:enable