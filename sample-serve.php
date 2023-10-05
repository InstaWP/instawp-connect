<?php
set_time_limit( 0 );


if ( ! isset( $api_signature ) || ! isset( $_POST['api_signature'] ) || $api_signature !== $_POST['api_signature'] ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Mismatched api signature.' );
	die();
}

$level     = 0;
$root_path = dirname( __DIR__ );
while ( ! file_exists( $root_path . '/wp-config.php' ) ) {
	$level ++;
	$root_path = dirname( __DIR__, $level );
}

defined( 'CHUNK_SIZE' ) | define( 'CHUNK_SIZE', 2 * 1024 * 1024 );
defined( 'CHUNK_DB_SIZE' ) | define( 'CHUNK_DB_SIZE', 100 );
defined( 'BATCH_SIZE' ) | define( 'BATCH_SIZE', 100 );
defined( 'WP_ROOT' ) | define( 'WP_ROOT', $root_path );

$migrate_key = basename( __FILE__, '.php' );

if ( isset( $_POST['serve_type'] ) && 'files' === $_POST['serve_type'] ) {

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

	if ( ! function_exists( 'instawp_contains' ) ) {
		function instawp_contains( $str, array $arr ) {
			foreach ( $arr as $a ) {
				if ( stripos( $str, $a ) !== false ) {
					return true;
				}
			}

			return false;
		}
	}

	$tracking_db_path = 'files-sent-' . $migrate_key . '.db';
	$total_files_path = '.total-files-' . $migrate_key;
	$skip_folders     = [ 'wp-content/cache', 'editor', 'wp-content/upgrade' ];
	$skip_files       = [];
	$db               = new PDO( 'sqlite:' . $tracking_db_path );

	// Create table if not exists
	$db->exec( "CREATE TABLE IF NOT EXISTS files_sent (id INTEGER PRIMARY KEY AUTOINCREMENT, filepath TEXT UNIQUE, sent INTEGER DEFAULT 0)" );

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
			return ! in_array( $iterator->getSubPath(), $skip_folders );
		};
		$directory        = new RecursiveDirectoryIterator( WP_ROOT, RecursiveDirectoryIterator::SKIP_DOTS );
		$iterator         = new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( $directory, $filter_directory ), RecursiveIteratorIterator::LEAVES_ONLY );
		$totalFiles       = iterator_count( $iterator );
		$fileIndex        = 0;

		file_put_contents( $total_files_path, $totalFiles );

		foreach ( $iterator as $file ) {

			$filepath   = $file->getPathname();
			$currentDir = $file->getPath();
			$stmt       = $db->prepare( "SELECT id, filepath FROM files_sent WHERE filepath = :filepath  LIMIT 1" );

			$stmt->bindValue( ':filepath', $filepath, PDO::PARAM_STR );
			$stmt->execute();
			$row = $stmt->fetch( PDO::FETCH_ASSOC );

			if ( ! $row ) {
				if ( ! instawp_contains( $currentDir, $skip_folders ) ) {
					$stmt = $db->prepare( "INSERT OR IGNORE INTO files_sent (filepath, sent) VALUES (:filepath, 0)" );
					$stmt->execute( [ ':filepath' => $filepath ] );
					$fileIndex ++;
				}
			} else {
				continue;
			}

			if ( $fileIndex > BATCH_SIZE ) {
				break;
			}
		}

		if ( $fileIndex == 0 ) {

			header( 'x-iwp-status: true' );
			header( 'x-iwp-transfer-complete: true' );
			header( 'x-iwp-message: No more files left to download.' );

			$db = null;
			exit;
		}

		$db->exec( "CREATE INDEX IF NOT EXISTS idx_sent ON files_sent(sent)" );
		$db->exec( "CREATE INDEX IF NOT EXISTS idx_file_path ON files_sent(filepath)" );
	}


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

		readfile_chunked( $filePath );

		// Mark file as sent in database
		$stmt = $db->prepare( "UPDATE files_sent SET sent = 1 WHERE id = :id" );
		$stmt->execute( [ ':id' => $fileId ] );
	} else {
		header( 'x-iwp-status: true' );
		header( 'x-iwp-transfer-complete: true' );
		header( 'x-iwp-message: No more files left to download.' );
	}

	$db = null;
}


if ( isset( $_POST['serve_type'] ) && 'db' === $_POST['serve_type'] ) {

	if ( ! isset( $db_host ) || ! isset( $db_username ) || ! isset( $db_password ) || ! isset( $db_name ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Database information missing.' );
		die();
	}

	global $mysqli;

	$trackingDb_path  = 'db-sent-' . $migrate_key . '.db';
	$trackingDb       = new SQLite3( $trackingDb_path );
	$createTableQuery = "CREATE TABLE IF NOT EXISTS tracking (table_name TEXT PRIMARY KEY,offset INTEGER DEFAULT 0,completed INTEGER DEFAULT 0);";

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
		$tableNamesResult    = $mysqli->query( "SHOW TABLES" );
		$insertStmt          = $trackingDb->prepare( "INSERT INTO tracking (table_name) VALUES (:tableName)" );
		$total_source_tables = 0;

		while ( $table = $tableNamesResult->fetch_row() ) {
			$insertStmt->bindValue( ':tableName', $table[0], SQLITE3_TEXT );
			$insertStmt->execute();
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

	$query  = "SELECT * FROM `$tableName` LIMIT " . CHUNK_DB_SIZE . " OFFSET $offset"; // Assume 'id' for ordering
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