<?php
set_time_limit( 0 );

defined( 'CHUNK_SIZE' ) | define( 'CHUNK_SIZE', 1024 * 1024 );
defined( 'BATCH_SIZE' ) | define( 'BATCH_SIZE', 100 );
defined( 'WP_ROOT' ) | define( 'WP_ROOT', '../../' );

if ( ! function_exists( 'site_url' ) ) {
	require_once WP_ROOT . 'wp-includes/link-template.php';
}

if ( ! function_exists( 'readfile_chunked' ) ) {
	function readfile_chunked( $filename, $retbytes = true ) {
		$buffer = '';
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

$migrate_key      = basename( __FILE__, '.php' );
$tracking_db_path = 'files-sent-' . $migrate_key . '.db';
$total_files_path = '.total-files-' . $migrate_key;
$skip_folders     = [ "wp-content/cache/" ];
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

	$directory  = new RecursiveDirectoryIterator( WP_ROOT, RecursiveDirectoryIterator::SKIP_DOTS );
	$iterator   = new RecursiveIteratorIterator( $directory, RecursiveIteratorIterator::LEAVES_ONLY );
	$totalFiles = iterator_count( $iterator );
	$fileIndex  = 0;

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
		echo "No more files left to download!";
		header( 'x-instawp-transfer-complete: true' );
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
	// echo "No more files left to download!";
	header( 'x-instawp-transfer-complete: true' );
}

$db = null; // Close the database connection
