<?php
set_time_limit( 0 );
error_reporting( 0 );

file_put_contents( 'iwp_log.txt', "Migration log started \n" );

if ( ! isset( $_SERVER['HTTP_X_IWP_MIGRATE_KEY'] ) || empty( $migrate_key = $_SERVER['HTTP_X_IWP_MIGRATE_KEY'] ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Empty migrate key.' );
	die();
}

$level         = 0;
$root_path_dir = __DIR__;
$root_path     = __DIR__;

while ( ! file_exists( $root_path . DIRECTORY_SEPARATOR . 'wp-config.php' ) ) {
	$level ++;
	$root_path = dirname( $root_path_dir, $level );

	// If we have reached the root directory and still couldn't find wp-config.php
	if ( $level > 10 ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Could not find wp-config.php in the parent directories.' );
		echo "Could not find wp-config.php in the parent directories.";
		exit( 2 );
	}
}

$json_path = $root_path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups' . DIRECTORY_SEPARATOR . $migrate_key . '.json';

if ( file_exists( $json_path ) ) {
	$jsonString = file_get_contents( $json_path );
	$jsonData   = json_decode( $jsonString, true );

	if ( $jsonData !== null ) {
		extract( $jsonData );
	} else {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Error: Unable to parse JSON data.' );
		die();
	}
} else {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Error: JSON file not found.' );
	die();
}

if ( ! isset( $api_signature ) || ! isset( $_SERVER['HTTP_X_IWP_API_SIGNATURE'] ) || $api_signature !== $_SERVER['HTTP_X_IWP_API_SIGNATURE'] ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Mismatched api signature.' );
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
	header( 'x-iwp-message: Could not find the X-File-Relative-Path header in the request.' );
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

$excluded_paths     = [];
$file_relative_path = trim( $_SERVER['HTTP_X_FILE_RELATIVE_PATH'] );
$file_type          = isset( $_SERVER['HTTP_X_FILE_TYPE'] ) ? trim( $_SERVER['HTTP_X_FILE_TYPE'] ) : 'single';
$req_order          = isset( $_GET['r'] ) ? intval( $_GET['r'] ) : 1;

if ( in_array( $file_relative_path, $excluded_paths ) ) {
	exit( 0 );
}

$file_save_path = $root_path . DIRECTORY_SEPARATOR . $file_relative_path;
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
//	$file_save_path = $root_path . DIRECTORY_SEPARATOR . time() . '.sql'; // added for debugging
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

	if ( extension_loaded( 'mysqli' ) ) {
		$mysqli = new mysqli( $db_host, $db_username, $db_password, $db_name );
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

			$log_content = file_get_contents( 'iwp_log.txt' );
			$log_content .= "x-iwp-progress: {$_SERVER['HTTP_X_IWP_PROGRESS']}\n";
			file_put_contents( 'iwp_log.txt', $log_content );
		}

		if ( isset( $_SERVER['HTTP_X_IWP_PROGRESS'] ) && $_SERVER['HTTP_X_IWP_PROGRESS'] == 100 ) {
			// update instawp_api_options after the push db finished
			if ( ! empty( $instawp_api_options ) ) {

				$show_table_result = $mysqli->query( "SHOW TABLES" );
				$table_prefix      = '';

				if ( $show_table_result->num_rows > 0 ) {
					while ( $row = $show_table_result->fetch_assoc() ) {

						$table_name = $row[ "Tables_in_" . $db_name ];
						$position   = strpos( $table_name, 'options' );

						if ( $position !== false ) {
							$table_prefix = substr( $table_name, 0, $position );
							break;
						}
					}
				}

//				$instawp_api_options = stripslashes( $instawp_api_options );
				$is_insert_failed    = false;

				try {
					$insert_response = $mysqli->query( "INSERT INTO `{$table_prefix}options` (`option_name`, `option_value`) VALUES('instawp_api_options', '{$instawp_api_options}')" );

					// log start
					$log_content = file_get_contents( 'iwp_log.txt' );
					$log_content .= "insert response: " . var_dump( $insert_response ). "\n";
					file_put_contents( 'iwp_log.txt', $log_content );
					// log end

				} catch ( Exception $e ) {
					$is_insert_failed = true;
				}

				if ( $is_insert_failed ) {
					try {
						$insert_response = $mysqli->query( "UPDATE `{$table_prefix}options` SET `option_value` = '{$instawp_api_options}' WHERE `option_name` = 'instawp_api_options'" );

						// log start
						$log_content = file_get_contents( 'iwp_log.txt' );
						$log_content .= "update response: " . var_dump( $insert_response ). "\n";
						file_put_contents( 'iwp_log.txt', $log_content );
						// log end

					} catch ( Exception $e ) {
						header( 'x-iwp-status: false' );
						header( "x-iwp-message: Update failed. Error message: {$e->getMessage()}\n" );
						die();
					}
				}

				// log start
				$log_content = file_get_contents( 'iwp_log.txt' );
				$log_content .= "full-json-data: " . json_encode( $jsonData ) . "\n";
				$log_content .= "api-options-data: " . $instawp_api_options . "\n";
				$log_content .= "table_prefix: {$table_prefix}\n";
				$log_content .= "mysql-success: " . json_encode( $insert_response ) . "\n";
				file_put_contents( 'iwp_log.txt', $log_content );
				// log end
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

if ( $file_type === 'zip' ) {
	if ( class_exists( 'ZipArchive' ) ) {
		try {
			$zip = new ZipArchive();
			$res = $zip->open( $file_save_path );

			if ( $res === true || $zip->status == 0 ) {
				$zip->extractTo( $directory_name );
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
	} else if ( class_exists( 'PharData' ) ) {
		try {
			$phar = new PharData( $file_save_path );
			$phar->extractTo( $directory_name, null, true );

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

if ( $file_relative_path === 'wp-config.php' ) {
	if ( ! isset( $db_host ) || ! isset( $db_username ) || ! isset( $db_password ) || ! isset( $db_name ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database information missing.' );
		die();
	}

	$wp_config_path = $root_path . DIRECTORY_SEPARATOR . 'wp-config.php';
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

	if ( isset( $site_url ) ) {
		$wp_config = preg_replace(
			"/'WP_SITEURL',\s*'[^']*'/",
			"'WP_SITEURL', '$site_url'",
			$wp_config
		);
	}

	if ( isset( $home_url ) ) {
		$wp_config = preg_replace(
			"/'WP_HOME',\s*'[^']*'/",
			"'WP_HOME', '$home_url'",
			$wp_config
		);
	}

	file_put_contents( $wp_config_path, $wp_config, LOCK_EX );
}

header( 'x-iwp-status: true' );
header( 'x-iwp-message: Success! ' . $file_relative_path );