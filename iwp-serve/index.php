<?php
set_time_limit( 0 );
error_reporting( 0 );

if ( ! defined( 'IWP_PLUGIN_DIR' ) ) {
	define( 'IWP_PLUGIN_DIR', dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR );
}

include_once IWP_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . 'functions-pull-push.php';

$migrate_key   = isset( $_POST['migrate_key'] ) ? $_POST['migrate_key'] : '';
$api_signature = isset( $_POST['api_signature'] ) ? $_POST['api_signature'] : '';

if ( empty( $migrate_key ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: The migration key from fetch script is empty 222. All received data: ' . json_encode( $_POST ) );
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

$root_dir_path_arr = explode( '/', $root_dir_path );

if ( end( $root_dir_path_arr ) === 'wp-content' ) {
	array_pop( $root_dir_path_arr );
}

$root_dir_path = implode( DIRECTORY_SEPARATOR, $root_dir_path_arr );

defined( 'CHUNK_SIZE' ) | define( 'CHUNK_SIZE', 2 * 1024 * 1024 );
defined( 'BATCH_ZIP_SIZE' ) | define( 'BATCH_ZIP_SIZE', 50 );
defined( 'MAX_ZIP_SIZE' ) | define( 'MAX_ZIP_SIZE', 1024 * 1024 ); //1mb
defined( 'CHUNK_DB_SIZE' ) | define( 'CHUNK_DB_SIZE', 100 );
defined( 'BATCH_SIZE' ) | define( 'BATCH_SIZE', 100 );
defined( 'WP_ROOT' ) | define( 'WP_ROOT', $root_dir_path );
defined( 'INSTAWP_BACKUP_DIR' ) | define( 'INSTAWP_BACKUP_DIR', WP_ROOT . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups' . DIRECTORY_SEPARATOR );

$iwpdb_main_path = WP_ROOT . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'instawp-connect' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-instawp-iwpdb.php';
$iwpdb_git_path  = WP_ROOT . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'instawp-connect-main' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-instawp-iwpdb.php';

if ( file_exists( $iwpdb_main_path ) && is_readable( $iwpdb_main_path ) ) {
	require_once( $iwpdb_main_path );
} elseif ( file_exists( $iwpdb_git_path ) && is_readable( $iwpdb_git_path ) ) {
	require_once( $iwpdb_git_path );
} else {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: The migration script could not find `class-instawp-iwpdb.php` inside the plugin directory.' );
	header( 'x-iwp-root-path: ' . WP_ROOT );
	echo "The migration script could not find the `class-instawp-iwpdb` inside the plugin directory.";
	exit( 2 );
}

global $tracking_db;

try {
	$tracking_db = new IWPDB( $migrate_key );
} catch ( Exception $e ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: Migration script could not connect to the database. Actual error is: ' . $e->getMessage() );
	die();
}

if ( ! $tracking_db ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: The migration tracking database could not find or load properly.' );
	die();
}

$db_api_signature = $tracking_db->get_option( 'api_signature' );

if ( ! hash_equals( $db_api_signature, $api_signature ) ) {
	header( 'x-iwp-status: false' );
	header( 'x-iwp-message: The given api signature and the stored one are not matching, maybe the tracking database reset or wrong api signature passed to migration script.' );
	die();
}


if ( isset( $_REQUEST['serve_type'] ) && 'files' === $_REQUEST['serve_type'] ) {
	$migrate_settings         = $tracking_db->get_option( 'migrate_settings' );
	$excluded_paths           = isset( $migrate_settings['excluded_paths'] ) ? $migrate_settings['excluded_paths'] : array();
	$skip_folders             = array_merge( array( 'wp-content/cache', 'editor', 'wp-content/upgrade', 'wp-content/instawpbackups' ), $excluded_paths );
	$skip_folders             = array_unique( $skip_folders );
	$skip_files               = array();
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

	$unsent_files_count  = $tracking_db->query_count( 'iwp_files_sent', array( 'sent' => '0' ) );
	$progress_percentage = 0;

	if ( $totalFiles = (int) $tracking_db->db_get_option( 'total_files', '0' ) ) {
		$total_files_count   = $tracking_db->query_count( 'iwp_files_sent' );
		$total_files_sent    = $total_files_count - $unsent_files_count;
		$progress_percentage = round( ( $total_files_sent / $totalFiles ) * 100, 2 );
	}

	if ( $unsent_files_count == 0 ) {
		$iterator = get_iterator_items( $skip_folders, WP_ROOT );

		// Get the current file index from the database or file
		$currentFileIndex = (int) $tracking_db->db_get_option( 'current_file_index', '0' );

		// Create a limited iterator to skip the files that are already indexed
		$limitedIterator = array();
		try {
			$iterator   = get_iterator_items( $skip_folders, WP_ROOT );
			$totalFiles = iterator_count( $iterator );

			if ( $totalFiles == 0 ) {
				throw new Exception( "No files found in the iterator." );
			}

			$limitedIterator = new LimitIterator( $iterator, $currentFileIndex, BATCH_SIZE );

			// Test if the limited iterator has any items
			$limitedIterator->rewind();
			if ( ! $limitedIterator->valid() ) {
				header( 'x-iwp-status: false' );
				header( "x-iwp-message: LimitIterator is empty. Current file index: $currentFileIndex, Batch size: " . BATCH_SIZE );
				die();
			}
		} catch ( Exception $e ) {
			header( 'x-iwp-status: false' );
			header( 'x-iwp-message: Migration script could not traverse files using the limited iterator. Error: ' . $e->getMessage() );
			die();
		}

		$totalFiles = iterator_count( $iterator );
		// Add plugins and themes files in total files count
		if ( ! empty( $totalFiles ) && ! empty( $migrate_settings['inventory_items'] ) && ! empty( $migrate_settings['inventory_items']['total_files'] ) ) {
			$totalFiles = intval( $totalFiles ) + intval( $migrate_settings['inventory_items']['total_files'] );
		}
		$fileIndex = 0;

		if ( $handle_config_separately ) {
			$totalFiles            += 1;
			$config_file_size      = filesize( $config_file_path );
			$config_file_path_hash = hash( 'sha256', $config_file_size );

			$tracking_db->insert( 'iwp_files_sent', array(
				'filepath'      => $tracking_db->use_wpdb ? $config_file_path : "'$config_file_path'",
				'filepath_hash' => $tracking_db->use_wpdb ? $config_file_path_hash : "'$config_file_path_hash'",
				'sent'          => 0,
				'size'          => $tracking_db->use_wpdb ? $config_file_size : "'$config_file_size'",
			) );
		}

		$tracking_db->db_update_option( 'total_files', $totalFiles );

		foreach ( $limitedIterator as $file ) {

			$filepath = '';
			$filesize = 0;

			try {
				$filepath = $file->getPathname();
				$filesize = $file->getSize();
			} catch ( Exception $e ) {
				$tracking_db->insert( 'iwp_files_sent', array(
					'filepath'      => $tracking_db->use_wpdb ? $filepath : "'$filepath'",
					'filepath_hash' => "",
					'sent'          => 5,
					'size'          => $tracking_db->use_wpdb ? $filesize : "'$filesize'",
				) );
				continue;
			}

			$filepath_hash = hash( 'sha256', $filepath );

			if ( ! is_valid_file( $filepath ) ) {
				try {
					$tracking_db->insert( 'iwp_files_sent', array(
						'filepath'      => $tracking_db->use_wpdb ? $filepath : "'$filepath'",
						'filepath_hash' => $tracking_db->use_wpdb ? $filepath_hash : "'$filepath_hash'",
						'sent'          => 5,
						'size'          => $tracking_db->use_wpdb ? $filesize : "'$filesize'",
					) );
				} catch ( Exception $e ) {
				}
				continue;
			}

			$currentDir = str_replace( WP_ROOT . '/', '', $file->getPath() );
			$row        = $tracking_db->get_row( 'iwp_files_sent', array( 'filepath_hash' => $filepath_hash ) );

			if ( ! $row ) {
				try {
					$tracking_db->insert( 'iwp_files_sent', array(
						'filepath'      => $tracking_db->use_wpdb ? $filepath : "'$filepath'",
						'filepath_hash' => $tracking_db->use_wpdb ? $filepath_hash : "'$filepath_hash'",
						'sent'          => 0,
						'size'          => $tracking_db->use_wpdb ? $filesize : "'$filesize'",
					) );
					++ $fileIndex;
				} catch ( Exception $e ) {
					header( 'x-iwp-status: false' );
					header( 'x-iwp-message: Insert to tracking database (iwp_files_sent table) was failed. Actual error message is: ' . $e->getMessage() );
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

		$current_file_index = ( $currentFileIndex + BATCH_SIZE );
		$ret                = $tracking_db->db_update_option( 'current_file_index', $current_file_index );

		if ( $fileIndex == 0 ) {
			header( 'x-iwp-status: true' );
			header( 'x-iwp-transfer-complete: true' );
			header( 'x-iwp-message: No more files left to download as the FileIndex is 0. Current file index is: ' . json_encode( $ret ) );
			exit;
		}

		$tracking_db->create_file_indexes( 'iwp_files_sent', array(
			'idx_sent'      => 'sent',
			'idx_file_size' => 'size',
		) );

		// Send plugin and theme inventory
		iwp_send_plugin_theme_inventory( $migrate_settings );
	}


	//TODO: this query runs every time even if there are no files to zip, may be we can cache the result in first time and don't run the query

	$is_archive_available = false;
	$unsentFiles          = array();

	if ( class_exists( 'ZipArchive' ) || class_exists( 'PharData' ) ) {
		$is_archive_available   = true;
		$unsent_files_query_res = $tracking_db->query( "SELECT id,filepath,size FROM iwp_files_sent WHERE sent = 0 and size < " . MAX_ZIP_SIZE . " ORDER by size LIMIT " . BATCH_ZIP_SIZE );

		if ( $unsent_files_query_res instanceof mysqli_result ) {
			$tracking_db->fetch_rows( $unsent_files_query_res, $unsentFiles );
		}
	}

	if ( $is_archive_available && count( $unsentFiles ) > 0 ) {
		if ( class_exists( 'ZipArchive' ) ) {
			// ZipArchive is available
			send_by_zip( $tracking_db, $unsentFiles, $progress_percentage, 'ziparchive', $handle_config_separately );
		} elseif ( class_exists( 'PharData' ) ) {
			// PharData is available
			send_by_zip( $tracking_db, $unsentFiles, $progress_percentage, 'phardata', $handle_config_separately );
		} else {
			// Neither ZipArchive nor PharData is available
			die( "No archive library available!" );
		}
	} else {

		$row = $tracking_db->get_row( 'iwp_files_sent', array( 'sent' => '0' ) );

		if ( $row ) {
			$tracking_db->update( 'iwp_files_sent', array( 'sent' => 2 ), array( 'id' => $row['id'] ) ); // mark as sending

			$fileId       = $row['id'];
			$filePath     = $row['filepath'];
			$file_name    = basename( $filePath );
			$relativePath = ltrim( str_replace( WP_ROOT, "", $filePath ), DIRECTORY_SEPARATOR );
			$filePath     = process_files( $tracking_db, $filePath, $relativePath );

			if ( $handle_config_separately && $file_name === 'wp-config.php' ) {
				$relativePath = $file_name;
			}
			header( 'Content-Type: application/octet-stream' );
			header( 'x-file-relative-path: ' . $relativePath );
			header( 'x-iwp-progress: ' . $progress_percentage );
			header( 'x-file-type: single' );

			if ( file_exists( $filePath ) && is_file( $filePath ) ) {
				readfile_chunked( $filePath );
			}

			$tracking_db->update( 'iwp_files_sent', array( 'sent' => '1' ), array( 'id' => $fileId ) );
		} else {
			$iterator   = get_iterator_items( $skip_folders, WP_ROOT );
			$totalFiles = iterator_count( $iterator );
			// Add plugins and themes files in total files count
			if ( ! empty( $totalFiles ) && ! empty( $migrate_settings['inventory_items'] ) && ! empty( $migrate_settings['inventory_items']['total_files'] ) ) {
				$totalFiles = intval( $totalFiles ) + intval( $migrate_settings['inventory_items']['total_files'] );
			}
			$fileIndex = 0;

			$tracking_db->db_update_option( 'total_files', $totalFiles );

			foreach ( $iterator as $file ) {
				try {
					$filepath = $file->getPathname();
					$filesize = $file->getSize();
				} catch ( Exception $e ) {
					continue;
				}

				$filepath_hash = hash( 'sha256', $filepath );

				if ( ! is_valid_file( $filepath ) ) {
					try {
						$tracking_db->insert( 'iwp_files_sent', array(
							'filepath'      => $tracking_db->use_wpdb ? $filepath : "'$filepath'",
							'filepath_hash' => $tracking_db->use_wpdb ? $filepath_hash : "'$filepath_hash'",
							'sent'          => 5,
							'size'          => $tracking_db->use_wpdb ? $filesize : "'$filesize'",
						) );
					} catch ( Exception $e ) {
					}
					continue;
				}

				$currentDir = str_replace( WP_ROOT . '/', '', $file->getPath() );
				$row        = $tracking_db->get_row( 'iwp_files_sent', array( 'filepath_hash' => $filepath_hash ) );

				if ( ! $row ) {
					try {
						$tracking_db->insert( 'iwp_files_sent', array(
							'filepath'      => "'$filepath'",
							'filepath_hash' => "'$filepath_hash'",
							'sent'          => 0,
							'size'          => "'$filesize'",
						) );
						++ $fileIndex;
					} catch ( Exception $e ) {
						header( 'x-iwp-status: false' );
						header( 'x-iwp-message: Insert to iwp_files_sent failed. Actual error: ' . $e->getMessage() );
						die();
					}
				}
			}

			if ( $fileIndex === 0 ) {
				header( 'x-iwp-status: true' );
				header( 'x-iwp-transfer-complete: true' );
				header( 'x-iwp-message: No more files left to download according to iwp_files_sent table.' );
			}
		}
	}
}


/**
 * Inventory success - If all plugins and themes have been installed
 * and if so, mark all files as sent.
 */
if ( isset( $_REQUEST['serve_type'] ) && 'inventory_sent_files' === $_REQUEST['serve_type'] && function_exists( 'iwp_sanitize_key' ) ) {

	$slug = empty( $_POST['slug'] ) ? '' : iwp_sanitize_key( $_POST['slug'] );
	if ( empty( $slug ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Empty slug provided.' );
		die();
	}
	$message = '';
	if ( ! empty( $_POST['failed_items'] ) ) {
		$mig_settings = $tracking_db->get_option( 'migrate_settings' );
		if ( ! empty( $mig_settings ) && ! empty( $mig_settings['inventory_items'] ) && ! empty( $mig_settings['excluded_paths'] ) && ! empty( $mig_settings['inventory_items']['total_files'] ) ) {
			$failed_item_files = 0;
			$include_paths     = array();
			// Failed to install items
			foreach ( $_POST['failed_items'] as $failed_item ) {
				if ( empty( $failed_item['slug'] ) || empty( $failed_item['path'] ) || empty( $failed_item['file_count'] ) ) {
					continue;
				}
				// failed item files count
				$failed_item_files += intval( $failed_item['file_count'] );

				$include_paths[] = $failed_item['path'];

				$slug = iwp_sanitize_key( $failed_item['slug'] );
				// Update inventory sent files
				$tracking_db->update(
					'iwp_files_sent',
					array(
						'size'       => 0,
						'file_count' => 0,
					),
					array(
						'file_type'     => 'inventory',
						'sent_filename' => $slug,
					)
				);
			}

			if ( ! empty( $include_paths ) ) {
				// Update excluded paths
				$mig_settings['excluded_paths'] = array_diff( $mig_settings['excluded_paths'], $include_paths );
				// Update total files
				$mig_settings['inventory_items']['total_files'] = intval( $mig_settings['inventory_items']['total_files'] ) - $failed_item_files;
				// Update settings
				$tracking_db->update_option( 'migrate_settings', $mig_settings );
				// Update total files
				$totalFiles = (int) $tracking_db->db_get_option( 'total_files', '0' );
				if ( $failed_item_files < $totalFiles ) {
					$tracking_db->update_option( 'total_files', $totalFiles - $failed_item_files );
				}
			}
			$message = 'Inventory';
		}
	} else {
		// Update inventory sent files
		$tracking_db->update(
			'iwp_files_sent',
			array( 'sent' => '1' ),
			array(
				'file_type'     => 'inventory',
				'sent_filename' => $slug,
			)
		);
		$message = empty( $_POST['item_type'] ) ? 'Plugin or theme' : ucfirst( iwp_sanitize_key( $_POST['item_type'] ) );
	}

	$message = $message . '' . $slug . ' installation report sent';
	header( 'x-iwp-status: true' );
	header( 'x-iwp-message: ' . $message );
	die();
}


if ( isset( $_REQUEST['serve_type'] ) && 'unmark_sent_files' === $_REQUEST['serve_type'] ) {

	$sent_filename = isset( $_POST['sent_filename'] ) ? $_POST['sent_filename'] : '';
	$checksum      = isset( $_POST['checksum'] ) ? $_POST['checksum'] : '';

	if ( empty( $sent_filename ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Empty zip(sent_filename) provided, Received the name as: ' . $sent_filename );
		die();
	}

	if ( empty( $checksum ) ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Empty zip(checksum) provided, Received checksum: ' . $checksum );
		die();
	}

	$update_response = $tracking_db->update( 'iwp_files_sent', array( 'sent' => '0' ), array( 'checksum' => "'$checksum'" ) );

	if ( ! $update_response ) {
		header( 'x-iwp-status: false' );
		header( 'x-iwp-message: Could not reset the sent status for: ' . $sent_filename . ' with checksum: ' . $checksum );
		die();
	}

	header( 'x-iwp-status: true' );
	header( 'x-iwp-message: Reset the sent status for: ' . $sent_filename . ' with checksum: ' . $checksum );
	die();
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

	$excluded_tables       = isset( $migrate_settings['excluded_tables'] ) ? $migrate_settings['excluded_tables'] : array();
	$excluded_tables_rows  = isset( $migrate_settings['excluded_tables_rows'] ) ? $migrate_settings['excluded_tables_rows'] : array();
	$total_tracking_tables = (int) $tracking_db->query_count( 'iwp_db_sent' );

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
				$table_name_hash = hash( 'sha256', $table_name );
				$tracking_db->insert( 'iwp_db_sent', array(
					'table_name'      => "'$table_name'",
					'table_name_hash' => "'$table_name_hash'",
					'rows_total'      => $rows_count,
				) );
			}
		}
	}

	$result = $tracking_db->get_row( 'iwp_db_sent', array( 'completed' => '2' ) );
	if ( empty( $result ) ) {
		$result = $tracking_db->get_row( 'iwp_db_sent', array( 'completed' => '0' ) );
	}

	if ( empty( $result ) ) {
		header( 'x-iwp-status: true' );
		header( 'x-iwp-transfer-complete: true' );
		header( 'x-iwp-message: No more tables to process.' );
		die();
	}

	$curr_table_name = isset( $result['table_name'] ) ? $result['table_name'] : '';
	$offset          = isset( $result['offset'] ) ? $result['offset'] : '';
	$sqlStatements   = array();

	// Check if it's the first batch of rows for this table
	if ( $offset == 0 && $create_table_sql = $tracking_db->query( "SHOW CREATE TABLE `$curr_table_name`" ) ) {
		$createRow = $create_table_sql->fetch_assoc();
		echo $createRow['Create Table'] . ";\n\n";
	}

	if ( ! in_array( $curr_table_name, $excluded_tables ) ) {

		$where_clause = '1';

		if ( isset( $excluded_tables_rows[ $curr_table_name ] ) && is_array( $excluded_tables_rows[ $curr_table_name ] ) && ! empty( $excluded_tables_rows[ $curr_table_name ] ) ) {

			$where_clause_arr = array();

			foreach ( $excluded_tables_rows[ $curr_table_name ] as $excluded_info ) {

				$excluded_info_arr = explode( ':', $excluded_info );
				$column_name       = isset( $excluded_info_arr[0] ) ? $excluded_info_arr[0] : '';
				$column_value      = isset( $excluded_info_arr[1] ) ? $excluded_info_arr[1] : '';

				if ( ! empty( $column_name ) && ! empty( $column_value ) ) {
					$where_clause_arr[] = "{$column_name} != '{$column_value}'";
				}
			}

			$where_clause = implode( ' AND ', $where_clause_arr );
		}

		$result = $tracking_db->query( "SELECT * FROM `$curr_table_name` WHERE {$where_clause} LIMIT " . CHUNK_DB_SIZE . " OFFSET $offset" );

		if ( ! $result ) {
			header( 'x-iwp-status: false' );
			header( 'x-iwp-message: There is an error in the database query operation. Actual error message is: ' . $tracking_db->last_error );
			die();
		}

		while ( $dataRow = $result->fetch_assoc() ) {
			$columns         = array_map( function ( $value ) {

				global $tracking_db;

				if ( is_array( $value ) && empty( $value ) ) {
					return array();
				} elseif ( is_string( $value ) && empty( $value ) ) {
					return '';
				}

				return $tracking_db->conn->real_escape_string( $value );
			}, array_keys( $dataRow ) );
			$values          = array_map( function ( $value ) {

				global $tracking_db;

				if ( is_numeric( $value ) ) {
					// If $value has leading zero it will mark as string and bypass returning as numeric
					if ( substr( $value, 0, 1 ) !== '0' ) {
						return $value;
					}
				} elseif ( is_null( $value ) ) {
					return "NULL";
				} elseif ( is_array( $value ) && empty( $value ) ) {
					$value = array();
				} elseif ( is_string( $value ) ) {
					$value = $tracking_db->conn->real_escape_string( $value );
				}

				return "'" . $value . "'";
			}, array_values( $dataRow ) );
			$sql             = "INSERT IGNORE INTO `$curr_table_name` (`" . implode( "`, `", $columns ) . "`) VALUES (" . implode( ", ", $values ) . ");";
			$sqlStatements[] = $sql;
		}
	}

	$sql_statements_count = count( $sqlStatements );
	$curr_table_info      = $tracking_db->get_row( 'iwp_db_sent', array( 'table_name_hash' => hash( 'sha256', $curr_table_name ) ) );
	$offset               += $sql_statements_count;

	$all_tables     = $tracking_db->get_rows( 'iwp_db_sent' );
	$rows_total_all = 0;
	$finished_total = 0;

	foreach ( $all_tables as $table_data ) {
		$rows_total_all += isset( $table_data['rows_total'] ) ? $table_data['rows_total'] : 0;
		$finished_total += isset( $table_data['offset'] ) ? $table_data['offset'] : 0;
	}

	// Update the offset and rows_finished
	$tracking_db->update( 'iwp_db_sent', array( 'offset' => $offset, 'completed' => '2' ), array( 'table_name_hash' => hash( 'sha256', $curr_table_name ) ) );

	// Mark table as completed if all rows were fetched
	if ( count( $sqlStatements ) < CHUNK_DB_SIZE ) {
		$tracking_db->update( 'iwp_db_sent', array( 'completed' => '1' ), array( 'table_name_hash' => hash( 'sha256', $curr_table_name ) ) );
	}

	$completed_tables   = (int) $tracking_db->query_count( 'iwp_db_sent', array( 'completed' => '1' ) );
	$tracking_progress  = $completed_tables === 0 || $total_tracking_tables === 0 ? 0 : number_format( ( $completed_tables * 100 ) / $total_tracking_tables, 2, '.', '' );
	$row_based_progress = number_format( $finished_total / $rows_total_all * 100, 2, '.', '' );
	$avg_progress       = round( ( (float) $row_based_progress + (float) $tracking_progress ) / 2 );

	header( "x-iwp-progress: $avg_progress" );

	echo implode( "\n", $sqlStatements );
}