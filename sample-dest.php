<?php
set_time_limit( 0 );


$has_zip_archive = class_exists( 'ZipArchive' );
if ( ! isset( $api_signature ) || ! isset( $_SERVER['HTTP_X_IWP_API_SIGNATURE'] ) || $api_signature !== $_SERVER['HTTP_X_IWP_API_SIGNATURE'] ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Mismatched api signature.' );
	die();
}

if ( isset( $_POST['check_zip'] ) ) {
	header( 'x-iwp-zip: ' . $has_zip_archive );
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

$migrate_key   = basename( __FILE__, '.php' );
$level         = 0;
$root_path_dir = __DIR__;
$root_path     = dirname( $root_path_dir );

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

$excluded_paths     = [ '.htaccess' ];
$file_relative_path = trim( $_SERVER['HTTP_X_FILE_RELATIVE_PATH'] );
$file_type          = isset( $_SERVER['HTTP_X_FILE_TYPE'] ) ? trim( $_SERVER['HTTP_X_FILE_TYPE'] ) : 'single';

if ( ! in_array( $file_relative_path, $excluded_paths ) ) {
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

	$file = fopen( $file_save_path, 'r+' );
    for ( $i = 0; $i < 4; $i++ ) {
        fgets( $file );
    }

    $content = [];
    while ( ! feof( $file ) ) {
        $content[] = fgets( $file );
    }
    $content = array_slice( $content, 0, -2 );

    rewind( $file );
    fwrite( $file, implode( '', $content ) );
    ftruncate( $file, ftell( $file ) );
    fclose( $file );

	if ( $file_type === 'zip' ) {
		$zip = new ZipArchive();
		$res = $zip->open( $file_save_path );

		if ( $res === TRUE || $zip->status == 0 ) {
			$zip->extractTo( $directory_name );
			$zip->close();

			unlink( $file_save_path );
		} else {
			echo "Couldn't extract $file_save_path.zip.\n";
			echo "ZipArchive Error (status): " . $zip->status . " - " . zipStatusString( $zip->status ) . "\n";
			echo "ZipArchive System Error (statusSys): " . $zip->statusSys . "\n";

			header( 'x-iwp-status: false' );
			header( "x-iwp-message: Couldn\'t extract $file_save_path .zip.\n" );
			die();
		}
	}

	header( 'x-iwp-status: true' );
	header( 'x-iwp-message: Success! ' . $file_relative_path );
}