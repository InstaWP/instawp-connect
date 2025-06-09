<?php
set_time_limit( 0 );
error_reporting( 0 );

if ( ! defined( 'IWP_PLUGIN_DIR' ) ) {
	define( 'IWP_PLUGIN_DIR', dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR );
}

include_once IWP_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . 'functions-pull-push.php';

if ( ! isset( $_SERVER['HTTP_X_IWP_MIGRATE_KEY'] ) || empty( $migrate_key = $_SERVER['HTTP_X_IWP_MIGRATE_KEY'] ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Empty migrate key.' );
	die();
}

$root_dir_data = iwp_get_root_dir();
$root_dir_find = isset( $root_dir_data['status'] ) ? $root_dir_data['status'] : false;
$root_dir_path = isset( $root_dir_data['root_path'] ) ? $root_dir_data['root_path'] : '';

if ( ! $root_dir_find ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Could not find wp-config.php in the parent directories.' );
	echo "Could not find wp-config.php in the parent directories.";
	exit( 2 );
}

$log_file_path     = $root_dir_path . DIRECTORY_SEPARATOR . 'iwp-push-log.txt';
$received_db_path  = $root_dir_path . DIRECTORY_SEPARATOR . 'iwp-db-received.sql';
$options_data_path = $root_dir_path . DIRECTORY_SEPARATOR . 'migrate-push-db-' . substr( $migrate_key, 0, 5 ) . '.txt';

if ( file_exists( $options_data_path ) ) {
	$options_data_encrypted = file_get_contents( $options_data_path );
	$decoded_data           = base64_decode( $options_data_encrypted );
	$openssl_iv             = substr( $decoded_data, 0, 16 );
	$encrypted_data         = substr( $decoded_data, 16 );
	$passphrase             = openssl_digest( $migrate_key, 'SHA256', true );
	$options_data_decrypted = openssl_decrypt( base64_encode( $encrypted_data ), 'AES-256-CBC', $passphrase, 0, $openssl_iv );
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
$excluded_paths  = isset( $excluded_paths ) ? $excluded_paths : array();

if ( isset( $_POST['check'] ) ) {

	if ( ! isset( $db_host ) || ! isset( $db_username ) || ! isset( $db_password ) || ! isset( $db_name ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database information missing.' );
		die();
	}

	$timestamp            = date( 'YmdHi' );
	$db_backup_response   = iwp_backup_wp_database( $db_host, $db_username, $db_password, $db_name, $root_dir_path, $timestamp );
	$core_backup_response = iwp_backup_wp_core_folders( $root_dir_path, $excluded_paths, $timestamp );

	header( 'x-iwp-zip: ' . $has_zip_archive );
	header( 'x-iwp-phar: ' . $has_phar_data );
	header( 'x-iwp-message: ' . json_encode( $core_backup_response ) . json_encode( $db_backup_response ) );
	die();
}

if ( ! isset( $_SERVER['HTTP_X_FILE_RELATIVE_PATH'] ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: The migration script could not find the X-File-Relative-Path header in the request.' );
	die();
}

$file_relative_path = trim( $_SERVER['HTTP_X_FILE_RELATIVE_PATH'] );
$file_type          = isset( $_SERVER['HTTP_X_FILE_TYPE'] ) ? trim( $_SERVER['HTTP_X_FILE_TYPE'] ) : 'single';
$req_order          = isset( $_GET['r'] ) ? intval( $_GET['r'] ) : 1;

if ( in_array( $file_relative_path, $excluded_paths ) ) {
	exit( 0 );
}

$file_save_path = $root_dir_path . DIRECTORY_SEPARATOR . $file_relative_path;

if ( in_array( $file_save_path, $excluded_paths ) || str_contains( $file_save_path, 'instawp-autologin' ) ) {
	exit( 0 );
}

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

	file_put_contents( $received_db_path, $sql_commands, FILE_APPEND );

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
			file_put_contents( $log_file_path, $log_content, FILE_APPEND );
		}

		if ( isset( $_SERVER['HTTP_X_IWP_PROGRESS'] ) && $_SERVER['HTTP_X_IWP_PROGRESS'] == 100 ) {

			// Retaining user after migration
			if ( $retain_user ) {

				$user_details_data  = isset( $user_details['data'] ) ? (array) $user_details['data'] : array();
				$user_details_caps  = isset( $user_details['caps'] ) ? (array) $user_details['caps'] : array();
				$user_details_roles = isset( $user_details['roles'] ) ? (array) $user_details['roles'] : array();

				$user_data = array(
					'user_login'          => isset( $user_details_data['user_login'] ) ? $user_details_data['user_login'] : '',
					'user_pass'           => isset( $user_details_data['user_pass'] ) ? base64_decode( $user_details_data['user_pass'] ) : '',
					'user_nicename'       => isset( $user_details_data['user_nicename'] ) ? $user_details_data['user_nicename'] : '',
					'user_email'          => isset( $user_details_data['user_email'] ) ? $user_details_data['user_email'] : '',
					'user_url'            => isset( $user_details_data['user_url'] ) ? $user_details_data['user_url'] : '',
					'user_registered'     => isset( $user_details_data['user_registered'] ) ? $user_details_data['user_registered'] : '',
					'user_activation_key' => isset( $user_details_data['user_activation_key'] ) ? $user_details_data['user_activation_key'] : '',
					'user_status'         => isset( $user_details_data['user_status'] ) ? $user_details_data['user_status'] : '',
					'display_name'        => isset( $user_details_data['display_name'] ) ? $user_details_data['display_name'] : '',
				);

				$fields = implode( ', ', array_keys( $user_data ) );
				$values = "'" . implode( "', '", array_map( array( $mysqli, 'real_escape_string' ), $user_data ) ) . "'";
				$query  = "INSERT INTO {$table_prefix}users ($fields) VALUES ($values)";

				$query_response = $mysqli->query( $query );

				if ( $query_response ) {
					$user_id = $mysqli->insert_id;

					if ( $user_id ) {
						// Set user capabilities
						$caps_key   = $mysqli->real_escape_string( $table_prefix . 'capabilities' );
						$caps_value = $mysqli->real_escape_string( iwp_maybe_serialize( $user_details_caps ) );
						$caps_query = "INSERT INTO {$table_prefix}usermeta (user_id, meta_key, meta_value) VALUES ($user_id, '$caps_key', '$caps_value')";
						$mysqli->query( $caps_query );

						// Set user roles
						$roles_key   = $mysqli->real_escape_string( $table_prefix . 'user_level' );
						$roles_value = $mysqli->real_escape_string( max( array_keys( $user_details_roles ) ) );
						$roles_query = "INSERT INTO {$table_prefix}usermeta (user_id, meta_key, meta_value) VALUES ($user_id, '$roles_key', '$roles_value')";
						$mysqli->query( $roles_query );
					}
				}

				if ( $mysqli->error ) {
					file_put_contents( $log_file_path, "insert response: " . $mysqli->error . "\n", FILE_APPEND );
				}
			}


			// update instawp_api_options after the push db finished
			if ( ! empty( $instawp_api_options ) ) {
				$is_insert_failed = false;

				try {
					$query           = "INSERT INTO `{$table_prefix}options` (`option_name`, `option_value`) VALUES('instawp_api_options', '{$instawp_api_options}')";
					$insert_response = $mysqli->query( $query );

					if ( ! $insert_response ) {
						$is_insert_failed = true;
					}
				} catch ( Exception $e ) {
					file_put_contents( $log_file_path, "insert exception: " . $e->getMessage() . "\n", FILE_APPEND );

					$is_insert_failed = true;
				}

				if ( $is_insert_failed ) {
					try {
						$query           = "UPDATE `{$table_prefix}options` SET `option_value` = '{$instawp_api_options}' WHERE `option_name` = 'instawp_api_options'";
						$update_response = $mysqli->query( $query );
					} catch ( Exception $e ) {
						file_put_contents( $log_file_path, "Update failed. Error message: {$e->getMessage()}\n", FILE_APPEND );

						header( 'x-iwp-status: false' );
						header( "x-iwp-message: Update failed. Error message: {$e->getMessage()}\n" );
						die();
					}
				}

				// Delete unnecessary options and update required settings
				$mysqli->query( "DELETE FROM `{$table_prefix}options` WHERE `option_name` = 'instawp_is_staging'" );
				$mysqli->query( "DELETE FROM `{$table_prefix}options` WHERE `option_name` = 'instawp_sync_connect_id'" );
				$mysqli->query( "UPDATE `{$table_prefix}options` SET `option_value` = '1' WHERE `option_name` = 'blog_public'" );
				// Remove trailing index.php
				$mysqli->query( "UPDATE `{$table_prefix}options` SET `option_value` = TRIM(TRAILING '/' FROM REPLACE(option_value, '/index.php', '')) WHERE `option_name` IN ('siteurl', 'home')" );
			}
		}

		$mysqli->close();
	} else {
		mysql_close( $connection );
	}

//	if ( file_exists( $file_save_path ) ) {
//		unlink( $file_save_path );
//	}
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

					if ( false !== strpos( $directory_name, DIRECTORY_SEPARATOR . 'wp-content' ) || false !== strpos( $directory_name, DIRECTORY_SEPARATOR . 'wp-includes' ) || false !== strpos( $directory_name, DIRECTORY_SEPARATOR . 'wp-admin' ) ) {
						if ( ! array_contains_str( $directory_name . DIRECTORY_SEPARATOR . $file_name, $excluded_paths ) && ! str_contains( $file_name, 'instawp-autologin' ) ) {
							$extracted_files[] = $file_name;
						}
					} else if ( ! in_array( $file_name, $excluded_paths ) && ! str_contains( $file_name, 'instawp-autologin' ) ) {
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

			try {
				$phar     = new PharData( $file_save_path );
				$iterator = new RecursiveIteratorIterator( $phar );

				foreach ( $iterator as $file ) {
					$file_name = str_replace( $phar->getPath() . '/', '', $file->getPathname() );
					// $file_name         = str_replace( 'phar://', '', $file_name );
					$extracted_files[] = $file_name;
				}

			} catch ( Throwable $e ) {
				header( 'x-iwp-status: false' );
				header( 'x-iwp-message: Error in extracting zip file using PharData. Actual error message is - ' . $e->getMessage() );
				die();
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

