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

		$fileSize  = filesize( $tmpZip );
		$fileMTime = filemtime( $tmpZip );
		$checksum  = hash( 'crc32b', $tmpZipName . $fileSize );

		header( 'x-iwp-sent-filename: ' . $tmpZipName );
		header( 'x-iwp-filesize: ' . $fileSize );
		header( 'x-iwp-checksum: ' . $checksum );

		try {
			if ( $archiveType === 'ziparchive' ) {
				$archive->close();
			}

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

// phpcs:enable