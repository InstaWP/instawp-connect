<?php
set_time_limit( 0 );


if ( ! isset( $api_signature ) || ! isset( $_POST['api_signature'] ) || $api_signature !== $_POST['api_signature'] ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Mismatched api signature.' );
	die();
}

$migrate_settings = isset( $migrate_settings ) ? unserialize( $migrate_settings ) : [];
$migrate_key      = basename( __FILE__, '.php' );
$level            = 0;
$root_path_dir    = __DIR__;
$root_path        = dirname( $root_path_dir );

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
defined( 'MAX_ZIP_SIZE' ) | define( 'MAX_ZIP_SIZE', 1000000 ); //1MB
defined( 'CHUNK_DB_SIZE' ) | define( 'CHUNK_DB_SIZE', 100 );
defined( 'BATCH_SIZE' ) | define( 'BATCH_SIZE', 100 );
defined( 'WP_ROOT' ) | define( 'WP_ROOT', $root_path );

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

	$tracking_db_path = 'files-sent-' . $migrate_key . '.db';
	$total_files_path = '.total-files-' . $migrate_key;
	$excluded_paths   = $migrate_settings['excluded_paths'] ?? [];
	$skip_folders     = array_merge( [ 'wp-content/cache', 'editor', 'wp-content/upgrade', 'wp-content/instawpbackups' ], $excluded_paths );
	$skip_folders     = array_unique( $skip_folders );
	$skip_files       = [];
	$db               = new PDO( 'sqlite:' . $tracking_db_path );


	// Create table if not exists
	$db->exec( "CREATE TABLE IF NOT EXISTS files_sent (id INTEGER PRIMARY KEY AUTOINCREMENT, filepath TEXT UNIQUE, sent INTEGER DEFAULT 0, size INTEGER)" );

	$stmt = $db->prepare( "SELECT count(*) as count FROM files_sent WHERE sent = 0" );
	$stmt->execute();
	$row              = $stmt->fetch( PDO::FETCH_ASSOC );
	$unsentFilesCount = $row['count'];
	$progressPer      = 0;

	if ( file_exists( $total_files_path ) && ( $totalFiles = @file_get_contents( $total_files_path ) ) ) {

		$stmt = $db->prepare( "SELECT count(*) as count FROM files_sent" );
		$stmt->execute();

		$row          = $stmt->fetch( PDO::FETCH_ASSOC );
		$dbFilesCount = $row['count'];
		$sentFiles    = $dbFilesCount - $unsentFilesCount;
		$progressPer  = round( ( $sentFiles / $totalFiles ) * 100, 2 );
	}

	if ( $unsentFilesCount == 0 ) {

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

		if ( file_exists( 'current_file_index.txt' ) ) {
			$currentFileIndex = (int) file_get_contents( 'current_file_index.txt' );
		}

		// Create a limited iterator to skip the files that are already indexed
		$limitedIterator = new LimitIterator( $iterator, $currentFileIndex, BATCH_SIZE );
		$totalFiles      = iterator_count( $iterator );
		$fileIndex       = 0;

		file_put_contents( $total_files_path, $totalFiles );

		foreach ( $limitedIterator as $file ) {

			$filepath = $file->getPathname();
			$filesize = $file->getSize();;
			$currentDir = str_replace( WP_ROOT . '/', '', $file->getPath() );
			//Find if the file is already indexed
			$stmt = $db->prepare( "SELECT id, filepath FROM files_sent WHERE filepath = :filepath  LIMIT 1" );

			$stmt->bindValue( ':filepath', $filepath, PDO::PARAM_STR );
			$stmt->execute();
			$row = $stmt->fetch( PDO::FETCH_ASSOC );

			//If file is not indexed, index it
			if ( ! $row ) {
				$stmt = $db->prepare( "INSERT OR IGNORE INTO files_sent (filepath, sent, size) VALUES (:filepath, 0, :filesize)" );
				$stmt->bindValue( ':filesize', $filesize, PDO::PARAM_INT );
				$stmt->bindValue( ':filepath', $filepath, PDO::PARAM_STR );
				$stmt->execute();
				$fileIndex ++;
			} else {
				continue;
			}

			// If we have indexed enough files, break the loop
			if ( $fileIndex > BATCH_SIZE ) {
				break;
			}
		}

		file_put_contents( 'current_file_index.txt', $currentFileIndex + BATCH_SIZE );

		if ( $fileIndex == 0 ) {

			header( 'x-iwp-status: true' );
			header( 'x-iwp-transfer-complete: true' );
			header( 'x-iwp-message: No more files left to download.' );
			unlink( 'current_file_index.txt' );
			$db = null;
			exit;
		}

		$db->exec( "CREATE INDEX IF NOT EXISTS idx_sent ON files_sent(sent)" );
		$db->exec( "CREATE INDEX IF NOT EXISTS idx_file_path ON files_sent(filepath)" );
		$db->exec( "CREATE INDEX IF NOT EXISTS idx_file_size ON files_sent(size)" );
	}

	//TODO: this query runs every time even if there are no files to zip, may be we can 
	//cache the result in first time and don't run the query

	$stmt = $db->prepare( "SELECT id,filepath,size FROM files_sent WHERE sent = 0 and size < " . MAX_ZIP_SIZE . " ORDER by size LIMIT " . BATCH_ZIP_SIZE );

	$stmt->execute();
	$unsentFiles = [];

	while ( $row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {
		$unsentFiles[ $row['id'] ] = $row['filepath'];
	}

	if ( count( $unsentFiles ) > 0 ) {
		//if there are files to zip

		header( 'Content-Type: zip' );
		header( 'x-iwp-progress: ' . $progressPer );
		header( 'x-file-type: zip' );

		$tmpZip = tempnam( sys_get_temp_dir(), 'batchzip' );
		$zip    = new ZipArchive();
		if ( $zip->open( $tmpZip, ZipArchive::OVERWRITE ) !== true ) {
			die( "Cannot open zip archive" );
		}

		foreach ( $unsentFiles as $file ) {
			$relativePath = ltrim( str_replace( WP_ROOT, "", $file ), DIRECTORY_SEPARATOR );
			if ( file_exists( $file ) && is_file( $file ) ) {
				$zip->addFile( $file, $relativePath );
			} else {
				error_log( 'File not found: ' . $file );
			}
		}

		$zip->close();

		readfile_chunked( $tmpZip );

		foreach ( $unsentFiles as $id => $file ) {
			$stmt = $db->prepare( "UPDATE files_sent SET sent = 1 WHERE id = $id" );
			$stmt->execute();
		}

		unlink( $tmpZip );

	} else {


		// Fetch next unsent file
		$stmt = $db->prepare( "SELECT id, filepath FROM files_sent WHERE sent = 0 LIMIT 1" );
		$stmt->execute();

		$row = $stmt->fetch( PDO::FETCH_ASSOC );

		if ( $row ) {
			$fileId       = $row['id'];
			$filePath     = $row['filepath'];
			$mimetype     = mime_content_type( $filePath );
			$relativePath = ltrim( str_replace( WP_ROOT, "", $filePath ), DIRECTORY_SEPARATOR );

			header( 'Content-Type: ' . $mimetype );
			header( 'x-file-relative-path: ' . $relativePath );
			header( 'x-iwp-progress: ' . $progressPer );
			header( 'x-file-type: single' );

			if ( file_exists( $filePath ) && is_file( $filePath ) ) {
				readfile_chunked( $filePath );
			}


			// Mark file as sent in database
			$stmt = $db->prepare( "UPDATE files_sent SET sent = 1 WHERE id = :id" );
			$stmt->execute( [ ':id' => $fileId ] );
		} else {
			unlink( 'current_file_index.txt' );
			header( 'x-iwp-status: true' );
			header( 'x-iwp-transfer-complete: true' );
			header( 'x-iwp-message: No more files left to download.' );
		}

		$db = null;
	}
}


if ( isset( $_REQUEST['serve_type'] ) && 'db' === $_REQUEST['serve_type'] ) {

	if ( ! isset( $db_host ) || ! isset( $db_username ) || ! isset( $db_password ) || ! isset( $db_name ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database information missing.' );
		die();
	}

	global $mysqli;

	$excluded_tables      = $migrate_settings['excluded_tables'] ?? [];
	$excluded_tables_rows = $migrate_settings['excluded_tables_rows'] ?? [];
	$trackingDb_path      = 'db-sent-' . $migrate_key . '.db';
	$trackingDb           = new SQLite3( $trackingDb_path );
	$createTableQuery     = "CREATE TABLE IF NOT EXISTS tracking (table_name TEXT PRIMARY KEY,offset INTEGER DEFAULT 0,completed INTEGER DEFAULT 0);";

	$trackingDb->exec( $createTableQuery );

	$mysqli = new mysqli( $db_host, $db_username, $db_password, $db_name );

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

			if ( empty( $value ) ) {
				return is_array( $value ) ? [] : '';
			}

			return $mysqli->real_escape_string( $value );
		}, array_keys( $dataRow ) );
		$values          = array_map( function ( $value ) {

			global $mysqli;

			if ( empty( $value ) ) {
				return is_array( $value ) ? [] : '';
			}

			return $mysqli->real_escape_string( $value );
		}, array_values( $dataRow ) );
		$sql             = "INSERT INTO `$tableName` (`" . implode( "`, `", $columns ) . "`) VALUES ('" . implode( "', '", $values ) . "');";
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
