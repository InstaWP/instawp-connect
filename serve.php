<?php
set_time_limit( 0 );
error_reporting( 0 );

$migrate_key   = isset( $_POST['migrate_key'] ) ? $_POST['migrate_key'] : '';
$api_signature = isset( $_POST['api_signature'] ) ? $_POST['api_signature'] : '';

if ( empty( $migrate_key ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Invalid migrate key.' );
	die();
}

$level         = 0;
$root_path_dir = __DIR__;
$root_path     = __DIR__;

while ( ! file_exists( $root_path . '/wp-load.php' ) ) {

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

defined( 'CHUNK_SIZE' ) | define( 'CHUNK_SIZE', 2 * 1024 * 1024 );
defined( 'BATCH_ZIP_SIZE' ) | define( 'BATCH_ZIP_SIZE', 50 );
defined( 'MAX_ZIP_SIZE' ) | define( 'MAX_ZIP_SIZE', 1024 * 1024 ); //1mb
defined( 'CHUNK_DB_SIZE' ) | define( 'CHUNK_DB_SIZE', 100 );
defined( 'BATCH_SIZE' ) | define( 'BATCH_SIZE', 500 );
defined( 'WP_ROOT' ) | define( 'WP_ROOT', $root_path );
defined( 'INSTAWP_BACKUP_DIR' ) | define( 'INSTAWP_BACKUP_DIR', WP_ROOT . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups' . DIRECTORY_SEPARATOR );

$iwpdb_main_path = WP_ROOT . '/wp-content/plugins/instawp-connect/includes/class-instawp-iwpdb.php';
$iwpdb_git_path  = WP_ROOT . '/wp-content/plugins/instawp-connect-main/includes/class-instawp-iwpdb.php';

if ( file_exists( $iwpdb_main_path ) && is_readable( $iwpdb_main_path ) ) {
	require_once( $iwpdb_main_path );
} else if ( file_exists( $iwpdb_git_path ) && is_readable( $iwpdb_main_path ) ) {
	require_once( $iwpdb_git_path );
} else {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Could not find class-instawp-iwpdb in the plugin directory.' );
	header( 'x-iwp-root-path: ' . WP_ROOT );
	echo "Could not find class-instawp-iwpdb in the plugin directory.";
	exit( 2 );
}

global $tracking_db;

try {
	$tracking_db = new IWPDB( $migrate_key );
} catch ( Exception $e ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Database connection error. Actual error: ' . $e->getMessage() );
	die();
}

if ( ! $tracking_db ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Could not find tracking database.' );
	die();
}

if ( $tracking_db->get_option( 'api_signature' ) !== $api_signature ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Mismatched api signature.' );
	die();
}

if ( isset( $_REQUEST['serve_type'] ) && 'files' === $_REQUEST['serve_type'] ) {

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
		function send_by_zip( IWPDB $tracking_db, $unsentFiles = array(), $progress_percentage = '', $archiveType = 'ziparchive' ) {
			header( 'Content-Type: zip' );
			header( 'x-file-type: zip' );
			header( 'x-iwp-progress: ' . $progress_percentage );

			$tmpZip = tempnam( sys_get_temp_dir(), 'batchzip' );

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

			header( 'x-iwp-filename: ' . $tmpZip );

			foreach ( $unsentFiles as $file ) {
				$filePath     = $file['filepath'] ?? '';
				$relativePath = ltrim( str_replace( WP_ROOT, "", $filePath ), DIRECTORY_SEPARATOR );
				$filePath     = process_files( $tracking_db, $filePath, $relativePath );

				$file_fopen_check = fopen( $filePath, 'r' );
				if ( ! $file_fopen_check ) {
					error_log( 'Can not open file: ' . $filePath );
					continue;
				}

				fclose( $file_fopen_check );

				if ( ! is_readable( $filePath ) ) {
					error_log( 'Can not read file: ' . $filePath );
					continue;
				}

				if ( ! is_file( $filePath ) ) {
					error_log( 'Invalid file: ' . $filePath );
					continue;
				}

				$added_to_zip = $archive->addFile( $filePath, $relativePath );

				if ( ! $added_to_zip ) {
					error_log( 'Could not add to zip. File: : ' . $filePath );
				}
			}

			try {
				if ( $archiveType === 'ziparchive' ) {
					$archive->close();
				}

				readfile_chunked( $tmpZip );
			} catch ( Exception $exception ) {
				header( 'x-iwp-status: false' );
				header( 'x-iwp-message: Error in reading file. Message - ' . $exception->getMessage() );
			}

			foreach ( $unsentFiles as $file ) {
				$tracking_db->update( 'iwp_files_sent', [ 'sent' => 1 ], [ 'id' => $file['id'] ] );
			}

			unlink( $tmpZip );
		}
	}

	if ( ! function_exists( 'process_files' ) ) {
		function process_files( IWPDB $tracking_db, $filePath, $relativePath ) {
			$site_url         = $tracking_db->get_option( 'site_url' );
			$dest_url         = $tracking_db->get_option( 'dest_url' );
			$migrate_settings = $tracking_db->get_option( 'migrate_settings' );
			$options          = $migrate_settings['options'] ?? [];

			if ( empty( $site_url ) || empty( $dest_url ) ) {
				return $filePath;
			}

			if ( $relativePath === '.htaccess' ) {
				$content  = file_get_contents( $filePath );
				$tmp_file = tempnam( sys_get_temp_dir(), 'htaccess' );
				$url_path = parse_url( $site_url, PHP_URL_PATH );

				if ( ! empty( $url_path ) && $url_path !== '/' ) {
					$content = str_replace( $url_path, '/', $content );
				}

				if ( in_array( 'skip_media_folder', $options ) ) {
					$htaccess_content = [
						'## BEGIN InstaWP Connect',
						'<IfModule mod_rewrite.c>',
						'RewriteEngine On',
						'RedirectMatch 301 ^/wp-content/uploads/(.*)$ ' . $site_url . '/wp-content/uploads/$1',
						'</IfModule>',
						'## END InstaWP Connect',
					];
					$htaccess_content = implode( "\n", $htaccess_content );
					$content          = $content . "\n" . $htaccess_content;
				}

				if ( file_put_contents( $tmp_file, $content ) ) {
					$filePath = $tmp_file;
				}
			} else if ( $relativePath === 'wp-config.php' ) {
				$fileContents = file_get_contents( $filePath );
				$fileContents = str_replace( $site_url, $dest_url, $fileContents );

				$tmp_file = tempnam( sys_get_temp_dir(), 'wp-config' );
				if ( file_put_contents( $tmp_file, $fileContents ) ) {
					$filePath = $tmp_file;
				}
			}

			return $filePath;
		}
	}

	if ( ! function_exists( 'is_valid_file' ) ) {
		function is_valid_file( $filepath ): bool {
			$filename = basename( $filepath );

			return is_file( $filepath ) && is_readable( $filepath ) && ( preg_match( '/^[a-zA-Z0-9_.\s-]+$/', $filename ) === 1 );
		}
	}

	$total_files_path         = INSTAWP_BACKUP_DIR . '.total-files-' . $migrate_key;
	$current_file_index_path  = INSTAWP_BACKUP_DIR . 'current_file_index.txt';
	$migrate_settings         = $tracking_db->get_option( 'migrate_settings' );
	$excluded_paths           = $migrate_settings['excluded_paths'] ?? [];
	$skip_folders             = array_merge( [ 'wp-content/cache', 'editor', 'wp-content/upgrade', 'wp-content/instawpbackups' ], $excluded_paths );
	$skip_folders             = array_unique( $skip_folders );
	$skip_files               = [];
	$config_file_path         = WP_ROOT . '/wp-config.php';
	$handle_config_separately = false;

	if ( ! file_exists( $config_file_path ) ) {
		$config_file_path = dirname( WP_ROOT ) . '/wp-config.php';

		if ( file_exists( $config_file_path ) ) {
			$handle_config_separately = true;
		} else {
			header( 'x-iwp-status: false' );
			header( 'x-iwp-message: WP Config file not found even in the one step above folder.' );
			die();
		}
	}

	$unsent_files_count  = $tracking_db->query_count( 'iwp_files_sent', [ 'sent' => '0' ] );
	$progress_percentage = 0;

	if ( file_exists( $total_files_path ) && ( $totalFiles = @file_get_contents( $total_files_path ) ) ) {
		$total_files_count   = $tracking_db->query_count( 'iwp_files_sent' );
		$total_files_sent    = $total_files_count - $unsent_files_count;
		$progress_percentage = round( ( $total_files_sent / $totalFiles ) * 100, 2 );
	}

	if ( $unsent_files_count == 0 ) {

		$filter_directory = function ( SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator ) use ( $skip_folders ) {

			$relative_path = ! empty( $iterator->getSubPath() ) ? $iterator->getSubPath() . '/' . $file->getBasename() : $file->getBasename();

			if ( in_array( $relative_path, $skip_folders ) ) {
				return false;
			}

			return ! in_array( $iterator->getSubPath(), $skip_folders );
		};
		$directory        = new RecursiveDirectoryIterator( WP_ROOT, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );
		$iterator         = new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( $directory, $filter_directory ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );

		// Get the current file index from the database or file
		$currentFileIndex = 0; // Set the default value to 0

		if ( file_exists( $current_file_index_path ) ) {
			$currentFileIndex = (int) file_get_contents( $current_file_index_path );
		}

		// Create a limited iterator to skip the files that are already indexed
		$limitedIterator = [];
		try {
			$limitedIterator = new LimitIterator( $iterator, $currentFileIndex, BATCH_SIZE );
		} catch ( Exception $e ) {
			header( 'x-iwp-status: false' );
			header( 'x-iwp-message: limitIterator error. Actual error: ' . $e->getMessage() );
			die();
		}

		$totalFiles = iterator_count( $iterator );
		$fileIndex  = 0;

		if ( $handle_config_separately ) {
			$totalFiles       += 1;
			$config_file_size = filesize( $config_file_path );

			$tracking_db->insert( 'iwp_files_sent', [ 'filepath' => "'$config_file_path'", 'sent' => 0, 'size' => "'$config_file_size'" ] );
		}

		file_put_contents( $total_files_path, $totalFiles );

		foreach ( $limitedIterator as $file ) {
			$filepath = $file->getPathname();
			if ( ! is_valid_file( $filepath ) ) {
				continue;
			}

			$filesize   = $file->getSize();
			$currentDir = str_replace( WP_ROOT . '/', '', $file->getPath() );
			$row        = $tracking_db->get_row( 'iwp_files_sent', [ 'filepath' => $filepath ] );

			if ( ! $row ) {
				try {
					$tracking_db->insert( 'iwp_files_sent', [ 'filepath' => "'$filepath'", 'sent' => 0, 'size' => "'$filesize'" ] );
					$fileIndex ++;
				} catch ( Exception $e ) {
					header( 'x-iwp-status: false' );
					header( 'x-iwp-message: Insert to iwp_files_sent failed. Actual error: ' . $e->getMessage() );
					die();
				}
			} else {
				continue;
			}

			// If we have indexed enough files, break the loop
			if ( $fileIndex > BATCH_SIZE ) {
				break;
			}
		}

		file_put_contents( $current_file_index_path, $currentFileIndex + BATCH_SIZE );

		if ( $fileIndex == 0 ) {
			header( 'x-iwp-status: true' );
			header( 'x-iwp-transfer-complete: true' );
			header( 'x-iwp-message: No more files left to download.' );
			unlink( $current_file_index_path );
			exit;
		}

		$tracking_db->create_file_indexes( 'iwp_files_sent', [ 'idx_sent' => 'sent', 'idx_file_path' => 'filepath', 'idx_file_size' => 'size' ] );
	}

	//TODO: this query runs every time even if there are no files to zip, may be we can
	//cache the result in first time and don't run the query

	$is_archive_available = false;
	$unsentFiles          = [];

	if ( class_exists( 'ZipArchive' ) || class_exists( 'PharData' ) ) {
		$is_archive_available   = true;
		$unsent_files_query_res = $tracking_db->query( "SELECT id,filepath,size FROM iwp_files_sent WHERE sent = 0 and size < " . MAX_ZIP_SIZE . " ORDER by size LIMIT " . BATCH_ZIP_SIZE );

		$tracking_db->fetch_rows( $unsent_files_query_res, $unsentFiles );
	}

	if ( $is_archive_available && count( $unsentFiles ) > 0 ) {
		if ( class_exists( 'ZipArchive' ) ) {
			// ZipArchive is available
			send_by_zip( $tracking_db, $unsentFiles, $progress_percentage, 'ziparchive' );
		} elseif ( class_exists( 'PharData' ) ) {
			// PharData is available
			send_by_zip( $tracking_db, $unsentFiles, $progress_percentage, 'phardata' );
		} else {
			// Neither ZipArchive nor PharData is available
			die( "No archive library available!" );
		}
	} else {

		$row = $tracking_db->get_row( 'iwp_files_sent', [ 'sent' => '0' ] );

		if ( $row ) {
			$fileId       = $row['id'];
			$filePath     = $row['filepath'];
			$mimetype     = mime_content_type( $filePath );
			$relativePath = ltrim( str_replace( WP_ROOT, "", $filePath ), DIRECTORY_SEPARATOR );
			$filePath     = process_files( $tracking_db, $filePath, $relativePath );

			header( 'Content-Type: ' . $mimetype );
			header( 'x-file-relative-path: ' . $relativePath );
			header( 'x-iwp-progress: ' . $progress_percentage );
			header( 'x-file-type: single' );

			if ( file_exists( $filePath ) && is_file( $filePath ) ) {
				readfile_chunked( $filePath );
			}

			$tracking_db->update( 'iwp_files_sent', [ 'sent' => '1' ], [ 'id' => $fileId ] );
		} else {
			unlink( $current_file_index_path );
			header( 'x-iwp-status: true' );
			header( 'x-iwp-transfer-complete: true' );
			header( 'x-iwp-message: No more files left to download.' );
		}
	}
}

if ( isset( $_REQUEST['serve_type'] ) && 'db' === $_REQUEST['serve_type'] ) {

	$migrate_settings = $tracking_db->get_option( 'migrate_settings' );
	$db_host          = $tracking_db->get_option( 'db_host' );
	$db_username      = $tracking_db->get_option( 'db_username' );
	$db_password      = $tracking_db->get_option( 'db_password' );
	$db_name          = $tracking_db->get_option( 'db_name' );

	if ( empty( $db_host ) || empty( $db_username ) || empty( $db_password ) || empty( $db_name ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database information missing.' );
		die();
	}

	$excluded_tables       = $migrate_settings['excluded_tables'] ?? [];
	$excluded_tables_rows  = $migrate_settings['excluded_tables_rows'] ?? [];
	$total_tracking_tables = $tracking_db->query_count( 'iwp_db_sent' );


	// Skip our files sent table
	if ( ! in_array( 'iwp_files_sent', $excluded_tables ) ) {
		$excluded_tables[] = 'iwp_files_sent';
	}

	// Skip our db sent table
	if ( ! in_array( 'iwp_db_sent', $excluded_tables ) ) {
		$excluded_tables[] = 'iwp_db_sent';
	}

	if ( $total_tracking_tables == 0 ) {
		foreach ( $tracking_db->get_all_tables() as $table_name => $rows_count ) {
			if ( ! in_array( $table_name, $excluded_tables ) ) {
				$tracking_db->insert( 'iwp_db_sent', [ 'table_name' => "'$table_name'", 'rows_total' => $rows_count ] );
			}
		}
	}

	$result = $tracking_db->get_row( 'iwp_db_sent', [ 'completed' => '0' ] );

	if ( empty( $result ) ) {
		header( 'x-iwp-status: true' );
		header( 'x-iwp-transfer-complete: true' );
		header( 'x-iwp-message: No more tables to process.' );
		die();
	}

	$curr_table_name = $result['table_name'] ?? '';
	$offset          = $result['offset'] ?? '';
	$sqlStatements   = [];

	// Check if it's the first batch of rows for this table
	if ( $offset == 0 && $create_table_sql = $tracking_db->query( "SHOW CREATE TABLE `$curr_table_name`" ) ) {
		$createRow = $create_table_sql->fetch_assoc();
		echo $createRow['Create Table'] . ";\n\n";
	}

	if ( ! in_array( $curr_table_name, $excluded_tables ) ) {

		$where_clause = '1';

		if ( isset( $excluded_tables_rows[ $curr_table_name ] ) && is_array( $excluded_tables_rows[ $curr_table_name ] ) && ! empty( $excluded_tables_rows[ $curr_table_name ] ) ) {

			$where_clause_arr = [];

			foreach ( $excluded_tables_rows[ $curr_table_name ] as $excluded_info ) {

				$excluded_info_arr = explode( ':', $excluded_info );
				$column_name       = $excluded_info_arr[0] ?? '';
				$column_value      = $excluded_info_arr[1] ?? '';

				if ( ! empty( $column_name ) && ! empty( $column_value ) ) {
					$where_clause_arr[] = "{$column_name} != '{$column_value}'";
				}
			}

			$where_clause = implode( ' AND ', $where_clause_arr );
		}

		$result = $tracking_db->query( "SELECT * FROM `$curr_table_name` WHERE {$where_clause} LIMIT " . CHUNK_DB_SIZE . " OFFSET $offset" );

		if ( ! $result ) {
			header( 'x-iwp-status: false' );
			header( 'x-iwp-message: Database query error - ' . $tracking_db->last_error );
			die();
		}

		while ( $dataRow = $result->fetch_assoc() ) {
			$columns         = array_map( function ( $value ) {

				global $tracking_db;

				if ( is_array( $value ) && empty( $value ) ) {
					return [];
				} else if ( is_string( $value ) && empty( $value ) ) {
					return '';
				}

				return $tracking_db->conn->real_escape_string( $value );
			}, array_keys( $dataRow ) );
			$values          = array_map( function ( $value ) {

				global $tracking_db;

				if ( is_numeric( $value ) ) {
					return $value;
				} else if ( is_null( $value ) ) {
					return "NULL";
				} else if ( is_array( $value ) && empty( $value ) ) {
					$value = [];
				} else if ( is_string( $value ) ) {
					$value = $tracking_db->conn->real_escape_string( $value );
				}

				return "'" . $value . "'";
			}, array_values( $dataRow ) );
			$sql             = "INSERT IGNORE INTO `$curr_table_name` (`" . implode( "`, `", $columns ) . "`) VALUES (" . implode( ", ", $values ) . ");";
			$sqlStatements[] = $sql;
		}
	}

	$sql_statements_count = count( $sqlStatements );
	$curr_table_info      = $tracking_db->get_row( 'iwp_db_sent', [ 'table_name' => $curr_table_name ] );
	$offset               += $sql_statements_count;

	$all_tables     = $tracking_db->get_rows( 'iwp_db_sent' );
	$rows_total_all = 0;
	$finished_total = 0;

	foreach ( $all_tables as $table_data ) {
		$rows_total_all += $table_data['rows_total'] ?? 0;
		$finished_total += $table_data['offset'] ?? 0;
	}

	// Update the offset and rows_finished
	$tracking_db->update( 'iwp_db_sent', [ 'offset' => $offset ], [ 'table_name' => $curr_table_name ] );

	// Mark table as completed if all rows were fetched
	if ( count( $sqlStatements ) < CHUNK_DB_SIZE ) {
		$tracking_db->update( 'iwp_db_sent', [ 'completed' => '1' ], [ 'table_name' => $curr_table_name ] );
	}

	$completed_tables   = $tracking_db->query_count( 'iwp_db_sent', [ 'completed' => '1' ] );
	$tracking_progress  = $completed_tables === 0 || $total_tracking_tables === 0 ? 0 : number_format( ( $completed_tables * 100 ) / $total_tracking_tables, 2, '.', '' );
	$row_based_progress = number_format( $finished_total / $rows_total_all * 100, 2, '.', '' );
	$avg_progress       = round( ( (float) $row_based_progress + (float) $tracking_progress ) / 2 );

	header( "x-iwp-progress: $avg_progress" );

	echo implode( "\n", $sqlStatements );
}