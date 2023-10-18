<?php
$instawpbackups_path = str_replace( 'plugins/instawp-connect', 'instawpbackups', __DIR__ );
$file_name           = $_REQUEST['filename'] ?? '';
$file_path           = $instawpbackups_path . DIRECTORY_SEPARATOR . basename( $file_name );

if ( ! is_readable( $file_path ) ) {
	echo 'File not found';
	exit( 2004 );
}

include $file_path;
