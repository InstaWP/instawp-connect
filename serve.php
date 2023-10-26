<?php
set_time_limit( 0 );

if ( ! isset( $_POST['migrate_key'] ) || empty( $migrate_key = $_POST['migrate_key'] ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Invalid migrate key.' );
	die();
}

$migrate_settings = isset( $migrate_settings ) ? unserialize( $migrate_settings ) : [];
$level            = 0;
$root_path_dir    = __DIR__;
$root_path        = __DIR__;

while ( ! file_exists( $root_path . '/wp-config.php' ) ) {

	$level ++;
	$root_path = dirname( $root_path_dir, $level );

	// If we have reached the root directory and still couldn't find wp-config.php
	if ( $level > 10 ) {
		header( 'x-iwp-status: false' );
		echo "Count not find wp-config.php in the parent directories.";
		exit( 2 );
	}
}

defined( 'CHUNK_SIZE' ) | define( 'CHUNK_SIZE', 2 * 1024 * 1024 );
defined( 'BATCH_ZIP_SIZE' ) | define( 'BATCH_ZIP_SIZE', 50 );
defined( 'MAX_ZIP_SIZE' ) | define( 'MAX_ZIP_SIZE', 1024 * 1024 ); //1mb
defined( 'CHUNK_DB_SIZE' ) | define( 'CHUNK_DB_SIZE', 100 );
defined( 'BATCH_SIZE' ) | define( 'BATCH_SIZE', 100 );
defined( 'WP_ROOT' ) | define( 'WP_ROOT', $root_path );

$iwpdb_main_path     = WP_ROOT . '/wp-content/plugins/instawp-connect/includes/class-instawp-iwpdb.php';
$iwpdb_git_path      = WP_ROOT . '/wp-content/plugins/instawp-connect-main/includes/class-instawp-iwpdb.php';
$instawpbackups_path = WP_ROOT . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups' . DIRECTORY_SEPARATOR;
$tracking_db_path    = $instawpbackups_path . 'files-sent-' . $migrate_key . '.db';

if ( file_exists( $iwpdb_main_path ) ) {
	require_once( $iwpdb_main_path );
} else if ( file_exists( $iwpdb_git_path ) ) {
	require_once( $iwpdb_git_path );
} else {
	header( 'x-iwp-status: false' );
	echo "Count not find class-instawp-iwpdb in the plugin directory.";
	exit( 2 );
}

try {
	$tracking_db = new IWPDB( $tracking_db_path );
} catch ( Exception $e ) {
	error_log( "Database creation error: {$e->getMessage()}" );

	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Can not access database.' );
	die();
}

if ( ! isset( $_POST['api_signature'] ) || $tracking_db->get_option( 'api_signature' ) !== $_POST['api_signature'] ) {
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
		function send_by_zip( IWPDB $tracking_db, $unsentFiles = array(), $progress_percentage = '' ) {
			header( 'Content-Type: zip' );
			header( 'x-file-type: zip' );
			header( 'x-iwp-progress: ' . $progress_percentage );

			$tmpZip = tempnam( sys_get_temp_dir(), 'batchzip' );
			$zip    = new ZipArchive();

			header( 'x-iwp-filename: ' . $tmpZip );

			if ( $zip->open( $tmpZip, ZipArchive::OVERWRITE ) !== true ) {
				die( "Cannot open zip archive" );
			}

			foreach ( $unsentFiles as $file ) {
				$filePath     = $file['filepath'] ?? '';
				$relativePath = ltrim( str_replace( WP_ROOT, "", $filePath ), DIRECTORY_SEPARATOR );
				if ( is_readable( $filePath ) && is_file( $filePath ) ) {
					$zip->addFile( $filePath, $relativePath );
				} else {
					error_log( 'File not found: ' . $filePath );
				}
			}

			try {
				$zip->close();

				readfile_chunked( $tmpZip );
			} catch ( Exception $exception ) {
				header( 'x-iwp-status: false' );
				header( 'x-iwp-message: Error in reading file. Message - ' . $exception->getMessage() );
			}

			foreach ( $unsentFiles as $file ) {
				$tracking_db->rawQuery( "UPDATE files_sent SET sent = 1 WHERE id=:id", array( ':id' => $file['id'] ) );
			}

//			unlink( $tmpZip );
		}
	}

	$total_files_path        = $instawpbackups_path . '.total-files-' . $migrate_key;
	$current_file_index_path = $instawpbackups_path . 'current_file_index.txt';
	$excluded_paths          = $migrate_settings['excluded_paths'] ?? [];
	$skip_folders            = array_merge( [ 'wp-content/cache', 'editor', 'wp-content/upgrade', 'wp-content/instawpbackups' ], $excluded_paths );
	$skip_folders            = array_unique( $skip_folders );
	$skip_files              = [];

	$tracking_db->rawQuery( "CREATE TABLE IF NOT EXISTS files_sent (id INTEGER PRIMARY KEY AUTOINCREMENT, filepath TEXT UNIQUE, sent INTEGER DEFAULT 0, size INTEGER)" );

	$unsent_query_response = $tracking_db->fetchRow( $tracking_db->rawQuery( "SELECT count(*) as count FROM files_sent WHERE sent = 0" ) );
	$unsent_files_count    = $unsent_query_response['count'] ?? 0;
	$progress_percentage   = 0;

	if ( file_exists( $total_files_path ) && ( $totalFiles = @file_get_contents( $total_files_path ) ) ) {

		$total_count_response = $tracking_db->fetchRow( $tracking_db->rawQuery( "SELECT count(*) as count FROM files_sent" ) );
		$total_files_count    = $total_count_response['count'] ?? 0;
		$total_files_sent     = $total_files_count - $unsent_files_count;
		$progress_percentage  = round( ( $total_files_sent / $totalFiles ) * 100, 2 );
	}

	if ( $unsent_files_count == 0 ) {

		$filter_directory = function ( SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator ) use ( $skip_folders ) {

			$relative_path = ! empty( $iterator->getSubPath() ) ? $iterator->getSubPath() . '/' . $file->getBasename() : $file->getBasename();

			if ( in_array( $relative_path, $skip_folders ) ) {
				return false;
			}

			return ! in_array( $iterator->getSubPath(), $skip_folders );
		};
		$directory        = new RecursiveDirectoryIterator( WP_ROOT, RecursiveDirectoryIterator::SKIP_DOTS );
		$iterator         = new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( $directory, $filter_directory ), RecursiveIteratorIterator::LEAVES_ONLY );

		// Get the current file index from the database or file
		$currentFileIndex = 0; // Set the default value to 0

		if ( file_exists( $current_file_index_path ) ) {
			$currentFileIndex = (int) file_get_contents( $current_file_index_path );
		}

		// Create a limited iterator to skip the files that are already indexed
		$limitedIterator = new LimitIterator( $iterator, $currentFileIndex, BATCH_SIZE );
		$totalFiles      = iterator_count( $iterator );
		$fileIndex       = 0;

		file_put_contents( $total_files_path, $totalFiles );

		foreach ( $limitedIterator as $file ) {

			$filepath   = $file->getPathname();
			$filesize   = $file->getSize();
			$currentDir = str_replace( WP_ROOT . '/', '', $file->getPath() );
			$row        = $tracking_db->fetchRow( $tracking_db->rawQuery( "SELECT id, filepath FROM files_sent WHERE filepath = :filepath  LIMIT 1" ) );

			if ( ! $row ) {
				$tracking_db->rawQuery( "INSERT OR IGNORE INTO files_sent (filepath, sent, size) VALUES ('$filepath', 0, '$filesize')" );
				$fileIndex ++;
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
			$db = null;
			exit;
		}

		$tracking_db->rawQuery( "CREATE INDEX IF NOT EXISTS idx_sent ON files_sent(sent)" );
		$tracking_db->rawQuery( "CREATE INDEX IF NOT EXISTS idx_file_path ON files_sent(filepath)" );
		$tracking_db->rawQuery( "CREATE INDEX IF NOT EXISTS idx_file_size ON files_sent(size)" );
	}

	//TODO: this query runs every time even if there are no files to zip, may be we can
	//cache the result in first time and don't run the query

	$unsentFiles = $tracking_db->fetchRows( $tracking_db->rawQuery( "SELECT id,filepath,size FROM files_sent WHERE sent = 0 and size < " . MAX_ZIP_SIZE . " ORDER by size LIMIT " . BATCH_ZIP_SIZE ) );

	if ( count( $unsentFiles ) > 0 && class_exists( 'ZipArchive' ) ) {
		send_by_zip( $tracking_db, $unsentFiles, $progress_percentage );
	} else {

		$row = $tracking_db->fetchRow( $tracking_db->rawQuery( "SELECT id, filepath FROM files_sent WHERE sent = 0 LIMIT 1" ) );

		if ( $row ) {
			$fileId       = $row['id'];
			$filePath     = $row['filepath'];
			$mimetype     = mime_content_type( $filePath );
			$relativePath = ltrim( str_replace( WP_ROOT, "", $filePath ), DIRECTORY_SEPARATOR );

			header( 'Content-Type: ' . $mimetype );
			header( 'x-file-relative-path: ' . $relativePath );
			header( 'x-iwp-progress: ' . $progress_percentage );
			header( 'x-file-type: single' );

			if ( file_exists( $filePath ) && is_file( $filePath ) ) {
				readfile_chunked( $filePath );
			}

			$tracking_db->rawQuery( "UPDATE files_sent SET sent = 1 WHERE id = '$fileId'" );
		} else {
			unlink( $current_file_index_path );
			header( 'x-iwp-status: true' );
			header( 'x-iwp-transfer-complete: true' );
			header( 'x-iwp-message: No more files left to download.' );
		}

		$db = null;
	}
}

if ( isset( $_REQUEST['serve_type'] ) && 'db' === $_REQUEST['serve_type'] ) {

	$db_host     = $tracking_db->get_option( 'db_host' );
	$db_username = $tracking_db->get_option( 'db_username' );
	$db_password = $tracking_db->get_option( 'db_password' );
	$db_name     = $tracking_db->get_option( 'db_name' );

	if ( empty( $db_host ) || empty( $db_username ) || empty( $db_password ) || empty( $db_name ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database information missing.' );
		die();
	}

	global $mysqli;

	$excluded_tables      = $migrate_settings['excluded_tables'] ?? [];
	$excluded_tables_rows = $migrate_settings['excluded_tables_rows'] ?? [];
	$trackingDb_path      = $instawpbackups_path . 'db-sent-' . $migrate_key . '.db';
	$trackingDb           = new SQLite3( $trackingDb_path );
	$createTableQuery     = "CREATE TABLE IF NOT EXISTS tracking (table_name TEXT PRIMARY KEY,offset INTEGER DEFAULT 0,completed INTEGER DEFAULT 0);";

	$trackingDb->exec( $createTableQuery );

	$mysqli = new mysqli( $db_host, $db_username, $db_password, $db_name );
	mysqli_set_charset( $mysqli, "utf8" );

	if ( $mysqli->connect_error ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database connection failed - ' . $mysqli->connect_error );
		die();
	}

	// Check if the SQLite tracking table is empty
	$total_tracking_tables = $trackingDb->querySingle( "SELECT COUNT(*) FROM tracking" );

	if ( $total_tracking_tables == 0 ) {

		$excluded_tables_sql      = array_map( function ( $table_name ) use ( $db_name ) {
			return "tables_in_{$db_name} NOT LIKE '{$table_name}'";
		}, $excluded_tables );
		$table_names_result_where = empty( $excluded_tables_sql ) ? '' : 'WHERE ' . implode( ' AND ', $excluded_tables_sql );
		$table_names_result       = $mysqli->query( "SHOW TABLES {$table_names_result_where}" );
		$insert_sql_statement     = $trackingDb->prepare( "INSERT INTO tracking (table_name) VALUES (:tableName)" );
		$total_source_tables      = 0;

		while ( $table = $table_names_result->fetch_row() ) {
			$insert_sql_statement->bindValue( ':tableName', $table[0], SQLITE3_TEXT );
			$insert_sql_statement->execute();
			$total_source_tables ++;
		}

		$total_tracking_tables = $total_source_tables;
	}

	$stmt   = $trackingDb->prepare( "SELECT table_name, offset FROM tracking WHERE completed = 0 ORDER BY table_name LIMIT 1" );
	$result = $stmt->execute();

	if ( ! $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
		header( 'x-iwp-status: true' );
		header( 'x-iwp-transfer-complete: true' );
		header( 'x-iwp-message: No more tables to process.' );
		die();
	}

	$tableName = $row['table_name'];
	$offset    = $row['offset'];

	// Check if it's the first batch of rows for this table
	if ( $offset == 0 ) {
		$createTableQuery = "SHOW CREATE TABLE `$tableName`";
		$createResult     = $mysqli->query( $createTableQuery );
		if ( $createResult ) {
			$createRow = $createResult->fetch_assoc();
			echo $createRow['Create Table'] . ";\n\n"; // This outputs the CREATE TABLE statement
		}
	}

	$where_clause = '1';

	if ( isset( $excluded_tables_rows[ $tableName ] ) && is_array( $excluded_tables_rows[ $tableName ] ) && ! empty( $excluded_tables_rows[ $tableName ] ) ) {

		$where_clause_arr = [];

		foreach ( $excluded_tables_rows[ $tableName ] as $excluded_info ) {

			$excluded_info_arr = explode( ':', $excluded_info );
			$column_name       = $excluded_info_arr[0] ?? '';
			$column_value      = $excluded_info_arr[1] ?? '';

			if ( ! empty( $column_name ) && ! empty( $column_value ) ) {
				$where_clause_arr[] = "{$column_name} != '{$column_value}'";
			}
		}

		$where_clause = implode( ' AND ', $where_clause_arr );
	}

	$query  = "SELECT * FROM `$tableName` WHERE {$where_clause} LIMIT " . CHUNK_DB_SIZE . " OFFSET $offset";
	$result = $mysqli->query( $query );

	if ( $mysqli->errno ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database query error - ' . $mysqli->connect_error );
		die();
	}

	$sqlStatements = [];

	while ( $dataRow = $result->fetch_assoc() ) {

		$columns         = array_map( function ( $value ) {

			global $mysqli;

			if ( is_array( $value ) && empty( $value ) ) {
				return [];
			} else if ( is_string( $value ) && empty( $value ) ) {
				return '';
			}

			return $mysqli->real_escape_string( $value );
		}, array_keys( $dataRow ) );
		$values          = array_map( function ( $value ) {

			global $mysqli;

			if ( is_numeric( $value ) ) {
				return $value;
			} else if ( is_null( $value ) ) {
				return "NULL";
			} else if ( is_array( $value ) && empty( $value ) ) {
				$value = [];
			} else if ( is_string( $value ) ) {
				$value = $mysqli->real_escape_string( $value );
			}

			return "'" . $value . "'";
		}, array_values( $dataRow ) );
		$sql             = "INSERT IGNORE INTO `$tableName` (`" . implode( "`, `", $columns ) . "`) VALUES (" . implode( ", ", $values ) . ");";
		$sqlStatements[] = $sql;
	}

	// Update progress in the SQLite tracking database
	$offset += count( $sqlStatements );
	$stmt   = $trackingDb->prepare( "UPDATE tracking SET offset = :offset WHERE table_name = :tableName" );
	$stmt->bindValue( ':offset', $offset, SQLITE3_INTEGER );
	$stmt->bindValue( ':tableName', $tableName, SQLITE3_TEXT );
	$stmt->execute();

	// Mark table as completed in the SQLite tracking database if all rows were fetched
	if ( count( $sqlStatements ) < CHUNK_DB_SIZE ) {
		$stmt = $trackingDb->prepare( "UPDATE tracking SET completed = 1 WHERE table_name = :tableName" );
		$stmt->bindValue( ':tableName', $tableName, SQLITE3_TEXT );
		$stmt->execute();
	}

	$completed_tracking_tables = $trackingDb->querySingle( "SELECT COUNT(*) FROM tracking WHERE completed=1" );
	$tracking_progress         = $completed_tracking_tables === 0 || $total_tracking_tables === 0 ? 0 : round( ( $completed_tracking_tables * 100 ) / $total_tracking_tables );

	header( "x-iwp-progress: $tracking_progress" );

	echo implode( "\n", $sqlStatements );

	$mysqli->close();
	$trackingDb->close();
}

