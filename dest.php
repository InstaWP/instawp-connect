<?php
set_time_limit( 0 );
error_reporting( 0 );

if ( ! file_exists( 'iwp_log.txt' ) ) {
	file_put_contents( 'iwp_log.txt', "Migration log started \n" );
}

if ( ! isset( $_SERVER['HTTP_X_IWP_MIGRATE_KEY'] ) || empty( $migrate_key = $_SERVER['HTTP_X_IWP_MIGRATE_KEY'] ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Empty migrate key.' );
	die();
}

if ( ! function_exists( 'get_wp_root_directory' ) ) {
	function get_wp_root_directory( $find_with_files = 'wp-load.php', $find_with_dir = '' ) {
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

$root_dir_data = get_wp_root_directory();
$root_dir_find = isset( $root_dir_data['status'] ) ? $root_dir_data['status'] : false;
$root_dir_path = isset( $root_dir_data['root_path'] ) ? $root_dir_data['root_path'] : '';

if ( ! $root_dir_find ) {
	$root_dir_data = get_wp_root_directory( '', 'flywheel-config' );
	$root_dir_find = isset( $root_dir_data['status'] ) ? $root_dir_data['status'] : false;
	$root_dir_path = isset( $root_dir_data['root_path'] ) ? $root_dir_data['root_path'] : '';
}

if ( ! $root_dir_find ) {
	$root_dir_data = get_wp_root_directory( '', 'wp' );
	$root_dir_find = isset( $root_dir_data['status'] ) ? $root_dir_data['status'] : false;
	$root_dir_path = isset( $root_dir_data['root_path'] ) ? $root_dir_data['root_path'] : '';
}

if ( ! $root_dir_find ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Could not find wp-config.php in the parent directories.' );
	echo "Could not find wp-config.php in the parent directories.";
	exit( 2 );
}

$options_data_path = $root_dir_path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups' . DIRECTORY_SEPARATOR . 'migrate-push-db-' . substr( $migrate_key, 0, 5 ) . '.txt';

if ( file_exists( $options_data_path ) ) {
	$options_data_encrypted = file_get_contents( $options_data_path );
	$passphrase             = openssl_digest( $migrate_key, 'SHA256', true );
	$options_data_decrypted = openssl_decrypt( $options_data_encrypted, 'AES-256-CBC', $passphrase );
	$jsonData               = json_decode( $options_data_decrypted, true );

	if ( $jsonData !== null ) {
		extract( $jsonData );
	} else {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Migration push-script could not parse JSON data.' );
		die();
	}
} else {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Migration push-script could not find the info file.' );
	die();
}

if ( ! isset( $api_signature ) || ! isset( $_SERVER['HTTP_X_IWP_API_SIGNATURE'] ) || ! hash_equals( $api_signature, $_SERVER['HTTP_X_IWP_API_SIGNATURE'] ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: (Push) The given api signature and the stored one are not matching, maybe the tracking database reset or wrong api signature passed to migration script.' );
	die();
}

$has_zip_archive = class_exists( 'ZipArchive' );
$has_phar_data   = class_exists( 'PharData' );

if ( isset( $_POST['check'] ) ) {
	header( 'x-iwp-zip: ' . $has_zip_archive );
	header( 'x-iwp-phar: ' . $has_phar_data );
	die();
}

if ( ! isset( $_SERVER['HTTP_X_FILE_RELATIVE_PATH'] ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: The migration script could not find the X-File-Relative-Path header in the request.' );
	die();
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

$excluded_paths     = isset( $excluded_paths ) ? $excluded_paths : array();
$file_relative_path = trim( $_SERVER['HTTP_X_FILE_RELATIVE_PATH'] );
$file_type          = isset( $_SERVER['HTTP_X_FILE_TYPE'] ) ? trim( $_SERVER['HTTP_X_FILE_TYPE'] ) : 'single';
$req_order          = isset( $_GET['r'] ) ? intval( $_GET['r'] ) : 1;

//if ( ! file_exists( $root_dir_path . DIRECTORY_SEPARATOR . 'iwp_log.txt' ) ) {
//    file_put_contents( $root_dir_path . DIRECTORY_SEPARATOR . 'iwp_log.txt', json_encode($excluded_paths) );
//}

if ( in_array( $file_relative_path, $excluded_paths ) ) {
	exit( 0 );
}

$file_save_path = $root_dir_path . DIRECTORY_SEPARATOR . $file_relative_path;
//file_put_contents( $root_dir_path . DIRECTORY_SEPARATOR . 'iwp_log.txt', "full path: " . $file_save_path . "\n", FILE_APPEND );
if ( in_array( $file_save_path, $excluded_paths ) || str_contains( $file_save_path, 'instawp-autologin' ) ) {
	exit( 0 );
}
//file_put_contents( $root_dir_path . DIRECTORY_SEPARATOR . 'iwp_log.txt', "full path success" . "\n", FILE_APPEND );

$directory_name = dirname( $file_save_path );

if ( ! file_exists( $directory_name ) ) {
	mkdir( $directory_name, 0777, true );
}

$file_input_stream = fopen( 'php://input', 'rb' );
if ( ! $file_input_stream ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Can\'t open input file stream. ' . $file_relative_path );
	die();
}

if ( $file_relative_path === 'db.sql' ) {
	if ( file_exists( $file_save_path ) ) {
		unlink( $file_save_path );
	}
//  $file_save_path = $root_dir_path . DIRECTORY_SEPARATOR . time() . '.sql'; // added for debugging
	$file_stream = fopen( $file_save_path, 'a+b' );
} else {
	$file_stream = fopen( $file_save_path, 'wb' );
}

if ( ! $file_stream ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Can\'t open file stream. ' . $file_save_path );
	die();
}

stream_copy_to_stream( $file_input_stream, $file_stream );

fclose( $file_input_stream );
fclose( $file_stream );

if ( $file_type === 'db' ) {
	if ( ! isset( $db_host ) || ! isset( $db_username ) || ! isset( $db_password ) || ! isset( $db_name ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database information missing.' );
		die();
	}

	if ( ! isset( $_SERVER['HTTP_X_IWP_TABLE_PREFIX'] ) || empty( $table_prefix = $_SERVER['HTTP_X_IWP_TABLE_PREFIX'] ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Empty table prefix. Headers are: ' . json_encode( $_SERVER ) );
		die();
	}

	if ( extension_loaded( 'mysqli' ) ) {
		$host    = $db_host;
		$port    = null;
		$socket  = null;
		$is_ipv6 = false;

		$host_data = parse_wp_db_host( $db_host );
		if ( $host_data ) {
			list( $host, $port, $socket, $is_ipv6 ) = $host_data;
		}

		if ( $is_ipv6 && extension_loaded( 'mysqlnd' ) ) {
			$host = "[$host]";
		}

		$mysqli = new mysqli( $host, $db_username, $db_password, $db_name, $port, $socket );
		if ( $mysqli->connect_error ) {
			die( 'Connection failed: ' . $mysqli->connect_error );
		}

		$mysqli->set_charset( 'utf8' );
	} else {
		$connection = mysql_connect( $db_host, $db_username, $db_password );
		if ( ! $connection ) {
			die( 'Connection failed: ' . mysql_error() );
		}

		if ( ! mysql_select_db( $db_name, $connection ) ) {
			die( 'Could not select database: ' . mysql_error() );
		}

		mysql_set_charset( 'UTF8', $connection );
	}

	if ( $req_order < 1 ) {
		if ( extension_loaded( 'mysqli' ) ) {
			$mysqli->query( 'SET foreign_key_checks = 0' );

			if ( $result = $mysqli->query( 'SHOW TABLES' ) ) {
				while ( $row = $result->fetch_array( MYSQLI_NUM ) ) {
					$mysqli->query( 'DROP TABLE IF EXISTS ' . $row[0] );
				}
			}

			$mysqli->query( 'SET foreign_key_checks = 1' );
		} else {
			mysql_query( 'SET foreign_key_checks = 0', $connection );

			if ( $result = mysql_query( 'SHOW TABLES', $connection ) ) {
				while ( $row = mysql_fetch_row( $result ) ) {
					mysql_query( 'DROP TABLE IF EXISTS ' . $row[0], $connection );
				}
			}

			mysql_query( 'SET foreign_key_checks = 1', $connection );
		}
	}

	$sql_commands = file_get_contents( $file_save_path );
	$commands     = explode( ";\n\n", $sql_commands );

	foreach ( $commands as $command ) {
		if ( ! empty( trim( $command ) ) ) {
			if ( extension_loaded( 'mysqli' ) ) {
				if ( ! $mysqli->query( $command ) ) {
					die( 'Error executing command: ' . $mysqli->error );
				}
			} else {
				$result = mysql_query( $command );
				if ( ! $result ) {
					die( 'Error executing command: ' . mysql_error() );
				}
			}
		}
	}

	if ( extension_loaded( 'mysqli' ) ) {

		if ( isset( $_SERVER['HTTP_X_IWP_PROGRESS'] ) ) {
			$log_content = "x-iwp-progress: {$_SERVER['HTTP_X_IWP_PROGRESS']}\n";
			file_put_contents( 'iwp_log.txt', $log_content, FILE_APPEND );
		}

		if ( isset( $_SERVER['HTTP_X_IWP_PROGRESS'] ) && $_SERVER['HTTP_X_IWP_PROGRESS'] == 100 ) {

			// update instawp_api_options after the push db finished
			if ( ! empty( $instawp_api_options ) ) {

//				$show_table_result = $mysqli->query( "SHOW TABLES" );
//				$table_prefix      = '';
//				$table_names       = [];
//
//				if ( $show_table_result->num_rows > 0 ) {
//					while ( $row = $show_table_result->fetch_assoc() ) {
//						$table_name = $row[ "Tables_in_" . $db_name ];
//
//						if ( str_ends_with( $table_name, '_options' ) ) {
//							$table_names[] = explode( '_', str_replace( '_options', '', $table_name ) );
//						}
//					}
//				}
//
//				if ( ! empty( $table_names ) ) {
//					if ( count( $table_names ) > 1 ) {
//						$table_name_items = call_user_func_array( 'array_intersect', $table_names );
//					} else {
//						$table_name_items = $table_names[0];
//					}
//
//					$table_prefix = implode( '_', $table_name_items ) . '_';
//				}

//              $instawp_api_options = stripslashes( $instawp_api_options );
				$is_insert_failed = false;

				try {
					$query = "INSERT INTO `{$table_prefix}options` (`option_name`, `option_value`) VALUES('instawp_api_options', '{$instawp_api_options}')";

					// log start
					file_put_contents( 'iwp_log.txt', "insert query: " . $query . "\n", FILE_APPEND );
					// log end

					$insert_response = $mysqli->query( $query );

					// log start
					file_put_contents( 'iwp_log.txt', "insert response: " . var_dump( $insert_response ) . "\n", FILE_APPEND );
					// log end

					if ( ! $insert_response ) {
						$is_insert_failed = true;
					}
				} catch ( Exception $e ) {
					// log start
					file_put_contents( 'iwp_log.txt', "insert exception: " . $e->getMessage() . "\n", FILE_APPEND );
					// log end

					$is_insert_failed = true;
				}

				if ( $is_insert_failed ) {
					try {
						$query = "UPDATE `{$table_prefix}options` SET `option_value` = '{$instawp_api_options}' WHERE `option_name` = 'instawp_api_options'";

						// log start
						file_put_contents( 'iwp_log.txt', "update query: " . $query . "\n", FILE_APPEND );
						// log end

						$update_response = $mysqli->query( $query );

						// log start
						file_put_contents( 'iwp_log.txt', "update response: " . var_dump( $update_response ) . "\n", FILE_APPEND );
						// log end
					} catch ( Exception $e ) {
						header( 'x-iwp-status: false' );
						header( "x-iwp-message: Update failed. Error message: {$e->getMessage()}\n" );
						die();
					}
				}

				// Delete unnecessary options and update required settings
				$mysqli->query( "DELETE FROM `{$table_prefix}options` WHERE `option_name` = 'instawp_is_staging'" );
				$mysqli->query( "DELETE FROM `{$table_prefix}options` WHERE `option_name` = 'instawp_sync_connect_id'" );
				$mysqli->query( "UPDATE `{$table_prefix}options` SET `option_value` = '1' WHERE `option_name` = 'blog_public'" );
			}
		}

		$mysqli->close();
	} else {
		mysql_close( $connection );
	}

	if ( file_exists( $file_save_path ) ) {
		unlink( $file_save_path );
	}
}

$is_wp_config_file = false;

if ( $file_type === 'zip' ) {
	if ( class_exists( 'ZipArchive' ) ) {
		try {
			$zip = new ZipArchive();
			$res = $zip->open( $file_save_path );

			if ( $res === true || $zip->status == 0 ) {
				$extracted_files = [];
				for ( $i = 0; $i < $zip->numFiles; $i ++ ) {
					$file_name = $zip->getNameIndex( $i );

					if ( ! array_contains_str( $directory_name . DIRECTORY_SEPARATOR . $file_name, $excluded_paths ) && ! str_contains( $file_name, 'instawp-autologin' ) ) {
						//file_put_contents( $root_dir_path . DIRECTORY_SEPARATOR . 'iwp_log.txt', "zip path: " . $directory_name . DIRECTORY_SEPARATOR . $file_name . "\n", FILE_APPEND );
						$extracted_files[] = $file_name;
					}
				}

				foreach ( $extracted_files as $file ) {
					if ( str_contains( $file, 'wp-config.php' ) ) {
						$is_wp_config_file = true;
					}
					$zip->extractTo( $directory_name, $file );
				}
				$zip->close();

				if ( file_exists( $file_save_path ) ) {
					unlink( $file_save_path );
				}
			} else {
				echo "Couldn't extract $file_save_path.zip.\n";
				echo "ZipArchive Error (status): " . $zip->status . " - " . zipStatusString( $zip->status ) . "\n";
				echo "ZipArchive System Error (statusSys): " . $zip->statusSys . "\n";

				header( 'x-iwp-status: false' );
				header( "x-iwp-message: Couldn\'t extract $file_save_path .zip.\n" );
				die();
			}
		} catch ( Exception $e ) {
			echo "Error: " . $e->getMessage();

			header( 'x-iwp-status: false' );
			header( 'x-iwp-message: ' . $e->getMessage() . "\n" );
			die();
		}
	} elseif ( class_exists( 'PharData' ) ) {
		try {
			$phar            = new PharData( $file_save_path );
			$extracted_files = [];
			foreach ( new RecursiveIteratorIterator( $phar ) as $file ) {
				$file_name = $file->getRelativePathname();

				if ( ! array_contains_str( $directory_name . DIRECTORY_SEPARATOR . $file_name, $excluded_paths ) && ! str_contains( $file_name, 'instawp-autologin' ) ) {
					//file_put_contents( $root_dir_path . DIRECTORY_SEPARATOR . 'iwp_log.txt', "phar path: " . $directory_name . DIRECTORY_SEPARATOR . $file_name . "\n", FILE_APPEND );
					$extracted_files[] = $file_name;
				}
			}

			foreach ( $extracted_files as $file ) {
				if ( str_contains( $file, 'wp-config.php' ) ) {
					$is_wp_config_file = true;
				}
				$phar->extractTo( $directory_name, $file, true );
			}

			if ( file_exists( $file_save_path ) ) {
				unlink( $file_save_path );
			}
		} catch ( Exception $e ) {
			echo "Error: " . $e->getMessage();

			header( 'x-iwp-status: false' );
			header( 'x-iwp-message: ' . $e->getMessage() . "\n" );
			die();
		}
	}
}

if ( str_contains( $file_relative_path, 'wp-config.php' ) || $is_wp_config_file ) {
	//file_put_contents( $root_dir_path . DIRECTORY_SEPARATOR . 'iwp_log.txt', "wp-config.php" . "\n", FILE_APPEND );
	if ( ! isset( $db_host ) || ! isset( $db_username ) || ! isset( $db_password ) || ! isset( $db_name ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database information missing.' );
		die();
	}

	$wp_config_path = $root_dir_path . DIRECTORY_SEPARATOR . 'wp-config.php';
	$wp_config      = file_get_contents( $wp_config_path );

	$wp_config = preg_replace(
		"/'DB_NAME',\s*'[^']*'/",
		"'DB_NAME', '$db_name'",
		$wp_config
	);

	$wp_config = preg_replace(
		"/'DB_USER',\s*'[^']*'/",
		"'DB_USER', '$db_username'",
		$wp_config
	);

	$wp_config = preg_replace(
		"/'DB_PASSWORD',\s*'[^']*'/",
		"'DB_PASSWORD', '$db_password'",
		$wp_config
	);

	$wp_config = preg_replace(
		"/'DB_HOST',\s*'[^']*'/",
		"'DB_HOST', '$db_host'",
		$wp_config
	);

	$wp_config = preg_replace(
		"/'DB_CHARSET',\s*'[^']*'/",
		"'DB_CHARSET', '$db_charset'",
		$wp_config
	);

	$wp_config = preg_replace(
		"/'DB_COLLATE',\s*'[^']*'/",
		"'DB_COLLATE', '$db_collate'",
		$wp_config
	);

	$wp_config = preg_replace(
		"/'WP_SITEURL',\s*'[^']*'/",
		"'WP_SITEURL', '$site_url'",
		$wp_config
	);

	$wp_config = preg_replace(
		"/'WP_HOME',\s*'[^']*'/",
		"'WP_HOME', '$home_url'",
		$wp_config
	);

	$current_domain = str_replace( [ 'https://', 'http://' ], '', rtrim( $home_url, '/\\' ) );
	$wp_config      = preg_replace(
		"/'DOMAIN_CURRENT_SITE',\s*'[^']*'/",
		"'DOMAIN_CURRENT_SITE', '$current_domain'",
		$wp_config
	);

	file_put_contents( $wp_config_path, $wp_config, LOCK_EX );

	/**
	 * Adding support for Elementor cloud
	 */
	if ( str_contains( $site_url, 'elementor.cloud' ) ) {
		$line_number  = false;
		$config_lines = file( $wp_config_path );
		$new_lines    = array(
			'if ( isset( $_SERVER["HTTP_X_FORWARDED_PROTO"] ) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https" ) { $_SERVER["HTTPS"] = "on"; }',
		);

		foreach ( $config_lines as $key => $line ) {
			if ( str_contains( $line, "DB_COLLATE" ) ) {
				$line_number = $key;
				break;
			}
		}

		if ( $line_number !== false ) {
			array_splice( $config_lines, $line_number + 1, 0, $new_lines );
		}

		file_put_contents( $wp_config_path, implode( "", $config_lines ) );
	}
}

header( 'x-iwp-status: true' );
header( 'x-iwp-message: Success! ' . $file_relative_path );