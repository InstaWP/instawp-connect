<?php
/**
 * Iterative push database
 * Handles database pushing operations for InstaWP migrations
 */
defined( 'ABSPATH' ) || exit;
if ( ! function_exists( 'instawp_iterative_push_db' ) ) {
	function instawp_iterative_push_db( $settings ) {
		global $mysqli, $migrate_key, $migrate_id, $bearer_token, $search_replace;

		define( 'CHUNK_DB_SIZE', 100 );

		$ipp_helper = new INSTAWP_IPP_HELPER();
		$required_parameters = array(
			'db_actions',
			'target_url',
			'working_directory',
			'api_signature',
			'migrate_key',
			'migrate_id',
			'bearer_token',
			'source_domain',
			'migrate_settings'
		);
		foreach ( $required_parameters as $req_param ) {
			if ( empty( $settings[$req_param] ) ) {
				$ipp_helper->print_message( 'Missing parameter: ' . $req_param , true );
				return false;
			}
		}


		$target_url        = $settings['target_url'];
		$working_directory = $settings['working_directory'];
		$api_signature     = $settings['api_signature'];
		$migrate_key       = $settings['migrate_key'];
		$migrate_id        = $settings['migrate_id'];
		$bearer_token      = $settings['bearer_token'];
		$source_domain     = $settings['source_domain'];
		$mig_settings      = $settings['migrate_settings'];
		$db_host           = $settings['db_host'];
		$db_username       = $settings['db_username'];
		$db_password       = $settings['db_password'];
		$db_name           = $settings['db_name'];
		$dest_domain       = $settings['dest_domain'];
		$table_prefix      = isset( $mig_settings['table_prefix'] ) ? $mig_settings['table_prefix'] : '';

		if ( ! empty( $target_url ) ) {
			$dest_domain = str_replace( array( 'http://', 'https://', 'wp-content/plugins/instawp-connect/dest.php', 'dest.php' ), '', $target_url );
			$dest_domain = rtrim( $dest_domain, '/' );
		}

		$search_replace   = [
			'//' . $source_domain   => '//' . $dest_domain,
			'\/\/' . $source_domain => '\/\/' . $dest_domain,
		];
		$curl_session     = curl_init( $target_url );
		$errors_counter   = 0;
		$headers          = [];
		$progress_sent_at = time();

		if ( ! isset( $db_host ) || ! isset( $db_username ) || ! isset( $db_password ) || ! isset( $db_name ) ) {
			iwp_send_migration_log( 'push-db: DB information missing', "Could not found database information. Arguments: " . json_encode( $argv ) );

			exit( 1 );
		}

		$excluded_tables      = [];
		$excluded_tables_rows = [ 'option_name:_transient_wp_core_block_css_files' ];
		$relative_path        = realpath( $working_directory ) . DIRECTORY_SEPARATOR;
		$tracking_db_path     = $relative_path . 'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups' . DIRECTORY_SEPARATOR . 'db-sent-' . $migrate_key . '.db';
		$directory_name       = dirname( $tracking_db_path );

		if ( ! file_exists( $directory_name ) ) {
			mkdir( $directory_name, 0777, true );
		}

		if ( file_exists( $tracking_db_path ) ) {
			unlink( $tracking_db_path );
		}

		try {
			$tracking_db = new InstaWPConnectDB( $tracking_db_path );
			$tracking_db->createTableTracking();
		} catch ( Exception $e ) {
			iwp_send_migration_log( 'push-db: DB tracking connection failed', $e->getMessage() );
			exit( 1 );
		}

		$mysqli = new mysqli( $db_host, $db_username, $db_password, $db_name );
		$mysqli->set_charset( 'utf8' );

		if ( $mysqli->connect_error ) {
			iwp_send_migration_log( 'push-db: DB connection failed', "Could not connect database. Error: {$mysqli->connect_error}. Hostname: $db_host, Username: $db_username, Password: $db_password, DB Name: $db_name" );

			exit( 1 );
		}

		$total_tracking_tables = $tracking_db->getTotalTablesCount();

		if ( $total_tracking_tables == 0 ) {
			$excluded_tables_sql      = array_map( function ( $table_name ) use ( $db_name ) {
				return "tables_in_{$db_name} NOT LIKE '{$table_name}'";
			}, $excluded_tables );
			$table_names_result_where = empty( $excluded_tables_sql ) ? '' : 'WHERE ' . implode( ' AND ', $excluded_tables_sql );
			$table_names_result       = $mysqli->query( "SHOW TABLES {$table_names_result_where}" );
			$total_source_tables      = 0;

			$tracking_db->beginTransaction();
			while ( $table = $table_names_result->fetch_row() ) {
				$tracking_db->insertTrackingTable( $table[0] );
				$total_source_tables ++;
			}
			$tracking_db->commit();

			$total_tracking_tables = $total_source_tables;
		}

		curl_setopt( $curl_session, CURLOPT_USERAGENT, 'InstaWP Migration Service - Push DB' );
		curl_setopt( $curl_session, CURLOPT_REFERER, $source_domain );
		curl_setopt( $curl_session, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl_session, CURLOPT_TIMEOUT, 120 );
		curl_setopt( $curl_session, CURLOPT_BUFFERSIZE, 2048 * 1024 ); // Set buffer size to 1MB;
		curl_setopt( $curl_session, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $curl_session, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $curl_session, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $curl_session, CURLOPT_COOKIE, "instawp_skip_splash=true" );
		curl_setopt( $curl_session, CURLOPT_HTTPHEADER, [
			'x-iwp-api-signature: ' . $api_signature,
			'x-iwp-migrate-key: ' . $migrate_key,
			'x-iwp-table-prefix: ' . $table_prefix,
		] );

		iwp_send_progress( 0, 0, [ 'push-db-in-progress' => true ] );

		$stopPushing       = false;
		$index             = 0;
		$slow_sleep        = 0;
		$tracking_progress = 0;

		while ( ! $stopPushing ) {

			if ( $errors_counter >= 50 ) {
				iwp_send_migration_log( 'push-db: Tried maximum times', 'Our migration script retried maximum times of pushing db. Now exiting the migration' );

				exit( 1 );
			}

			$row = $tracking_db->getUncompletedTable();

			if ( ! $row ) {
				$stopPushing = true;
				if ( $index == 0 ) {
					echo 'No more tables to process.';
					exit( 2 );
				}
				break;
			}

			$statements = '';
			$statements .= 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"' . ";\n\n";
			$tableName  = $row['table_name'];
			$offset     = $row['offset'];

			// Check if it's the first batch of rows for this table
			if ( $offset == 0 ) {
				$createTableQuery = "SHOW CREATE TABLE `$tableName`";
				$createResult     = $mysqli->query( $createTableQuery );
				if ( $createResult ) {
					$createRow  = $createResult->fetch_assoc();
					$statements .= str_replace( 'CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $createRow['Create Table'] ) . ";\n\n"; // This outputs the CREATE TABLE statement
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
				iwp_send_migration_log( 'push-db: Database query failed', "Database query error found. Actual error: {$mysqli->connect_error}, Query: $query" );

				exit( 1 );
			}

			$sqlStatements = [];

			while ( $dataRow = $result->fetch_assoc() ) {
				$columns = array_map( function ( $value ) {
					global $mysqli;

					if ( empty( $value ) ) {
						return is_array( $value ) ? [] : '';
					}

					return $mysqli->real_escape_string( $value );
				}, array_keys( $dataRow ) );

				$values = array_map( function ( $value ) {
					global $mysqli;

					if ( is_numeric( $value ) ) {
						// If $value has leading zero it will mark as string and bypass returning as numeric
						if ( substr( $value, 0, 1 ) !== '0' ) {
							return $value;
						}
					} else if ( is_null( $value ) ) {
						return "NULL";
					} else if ( is_array( $value ) && empty( $value ) ) {
						$value = [];
					} else if ( is_string( $value ) ) {
						if ( iwp_is_serialized( $value ) ) {
							$value = iwp_maybe_unserialize( $value );
							$value = iwp_maybe_serialize( $value );
						}
						$value = $mysqli->real_escape_string( $value );
					}

					return "'" . $value . "'";
				}, array_values( $dataRow ) );
				//$values = array_map( 'iwp_parse_db_data', $values );

				$sql_query = "INSERT IGNORE INTO `$tableName` (`" . implode( "`, `", $columns ) . "`) VALUES (" . implode( ", ", $values ) . ");";

				// Check if the table name matches exactly with $table_prefix + 'blogs' or $table_prefix + 'sites'
				if ( in_array( $tableName, [ $table_prefix . 'blogs', $table_prefix . 'sites' ] ) ) {
					$sql_query = str_replace( $source_domain, $dest_domain, $sql_query );
				}

				$sqlStatements[] = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $sql_query );
			}

			// Update progress in the SQLite tracking database
			$offset += count( $sqlStatements );
			$tracking_db->updateTableOffset( $tableName, $offset );

			// Mark table as completed in the SQLite tracking database if all rows were fetched
			if ( count( $sqlStatements ) < CHUNK_DB_SIZE ) {
				$tracking_db->markTableCompleted( $tableName );
			}

			$completed_tracking_tables = $tracking_db->getCompletedTablesCount();
			$tracking_progress_req     = $completed_tracking_tables === 0 || $total_tracking_tables === 0 ? 0 : round( ( $completed_tracking_tables * 100 ) / $total_tracking_tables );

			$statements .= implode( "\n\n", $sqlStatements );

			$temp_destination = tempnam( sys_get_temp_dir(), "iwp-db" );
			$file_pointer     = fopen( $temp_destination, 'w+b' );

			fwrite( $file_pointer, $statements );
			fclose( $file_pointer );

			$file_stream = fopen( $temp_destination, 'r' );

			curl_setopt( $curl_session, CURLOPT_USERAGENT, 'InstaWP Migration Service - Push DB' );
			curl_setopt( $curl_session, CURLOPT_URL, $target_url . '?r=' . $index );
			curl_setopt( $curl_session, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
				return iwp_process_curl_headers( $curl, $header, $headers );
			} );
			curl_setopt( $curl_session, CURLOPT_INFILE, $file_stream );
			curl_setopt( $curl_session, CURLOPT_INFILESIZE, filesize( $temp_destination ) );
			curl_setopt( $curl_session, CURLOPT_PUT, true );
			curl_setopt( $curl_session, CURLOPT_COOKIE, "instawp_skip_splash=true" );
			curl_setopt( $curl_session, CURLOPT_HTTPHEADER, [
				'Content-Type: application/octet-stream',
				'x-file-relative-path: db.sql',
				'x-file-type: db',
				'x-iwp-progress: ' . $tracking_progress_req,
				'x-iwp-api-signature: ' . $api_signature,
				'x-iwp-migrate-key: ' . $migrate_key,
				'x-iwp-table-prefix: ' . $table_prefix,
			] );

			$response                   = curl_exec( $curl_session );
			$processed_response         = iwp_process_curl_response( $response, $curl_session, $headers, $errors_counter, $slow_sleep, 'push-db' );
			$processed_response_success = isset( $processed_response['success'] ) ? (bool) $processed_response['success'] : false;
			$processed_response_message = isset( $processed_response['message'] ) ? $processed_response['message'] : '';

			if ( ! $processed_response_success ) {
				echo $processed_response_message;

				continue;
			}

			fclose( $file_stream );

			$header_status  = $headers['x-iwp-status'] ?? false;
			$header_message = $headers['x-iwp-message'] ?? '';

			if ( $header_status == 'false' ) {

				iwp_send_migration_log( 'push-db: Header status is false from dest.php', "Header status $header_status, Full response: " . json_encode( $response ) );

				$mysqli->close();
				$tracking_db->close();

				exit( 1 );
			}

			$tracking_progress = $tracking_progress_req;

			if ( time() - $progress_sent_at > 3 ) {
				iwp_send_progress( 0, $tracking_progress );
				echo date( "H:i:s" ) . ": Progress: $tracking_progress%\n";
				$progress_sent_at = time();
			}

			$index ++;
		}

		$mysqli->close();
		$tracking_db->close();

		// Files pushing is completed
		iwp_send_progress( 0, 100, [ 'push-db-finished' => true ] );

		try {
			unlink( $tracking_db_path );
		} catch ( Exception $e ) {
			iwp_send_migration_log( 'push-db: Tracking path deletion failed', "An error occurred while deleting the DB file: {$e->getMessage()}, Database file path: $tracking_db_path" );
		}

		echo 'DB pushing completed';
		echo "Whole database is transferred successfully to the destination website. \n";
		exit( 0 );
	}
}