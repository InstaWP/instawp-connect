<?php
/**
 * Iterative push database
 * Handles database pushing operations for InstaWP migrations
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'instawp_iterative_push_db_curl' ) ) {
	/**
	 * Iterative push database curl
	 * Handles database pushing operations for InstaWP migrations
	 * 
	 * @param string $temp_destination
	 * @param resource $curl_session
	 * @param string $api_signature
	 * @param int $index
	 * @param array $tracking_progress_req
	 * @param object $ipp_helper
	 * 
	 * @return boolean status
	 */
	function instawp_iterative_push_db_curl( $statements, $curl_session, $api_signature, $index, $tracking_progress_req, $ipp_helper, $progress_sent_at ) {
		global $tracking_db, $table_prefix, $migrate_key, $migrate_id, $migrate_mode, $bearer_token, $search_replace, $target_url;
		try {

			$temp_destination = tempnam( sys_get_temp_dir(), "iwp-db" );
			$file_pointer     = fopen( $temp_destination, 'w+b' );

			fwrite( $file_pointer, $statements );
			fclose( $file_pointer );
			
			$file_stream = fopen( $temp_destination, 'r' );

			curl_setopt( $curl_session, CURLOPT_USERAGENT, 'InstaWP Migration Service - Iterative Push DB' );
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
				'x-iwp-mode: ' . $migrate_mode,
				'x-iwp-progress: ' . $tracking_progress_req,
				'x-iwp-api-signature: ' . $api_signature,
				'x-iwp-migrate-key: ' . $migrate_key,
				'x-iwp-table-prefix: ' . $table_prefix,
			] );

			$response                   = curl_exec( $curl_session );
			$processed_response         = iwp_process_curl_response( $response, $curl_session, $headers, $errors_counter, $slow_sleep, 'push-db' );
			$processed_response_success = isset( $processed_response['success'] ) ? (bool) $processed_response['success'] : false;
			$processed_response_message = isset( $processed_response['message'] ) ? $processed_response['message'] : '';

			fclose( $file_stream );
			
			if ( ! $processed_response_success ) {
				$ipp_helper->print_message( 'iterative-push-db: ' . $processed_response_message );	
				return false;
			}

			$header_status  = $headers['x-iwp-status'] ?? false;
			$header_message = $headers['x-iwp-message'] ?? '';

			if ( $header_status == 'false' ) {

				iwp_send_migration_log( 'iterative-push-db: Header status is false from dest.php', "Header status $header_status, Full response: " . json_encode( $response ) );

				return false;
			}

			$tracking_progress = $tracking_progress_req;

			if ( time() - $progress_sent_at > 3 ) {
				iwp_send_progress( 0, $tracking_progress );
				$ipp_helper->progress_bar( $tracking_progress, 100, 50, $migrate_mode );
				$progress_sent_at = time();
			}
		} catch ( Exception $e ) {
			iwp_send_migration_log( 'iterative-push-db: Curl exception',  $e->getMessage() );
			$ipp_helper->print_message( 'iterative-push-db: Curl exception error - ' . $e->getMessage(), true );
			return false;
		}
		return true;
	}
}
if ( ! function_exists( 'instawp_iterative_push_db' ) ) {
	function instawp_iterative_push_db( $settings ) {
		global $tracking_db, $table_prefix, $migrate_key, $migrate_id, $migrate_mode, $bearer_token, $search_replace, $target_url;

		define( 'CHUNK_DB_SIZE', 100 );

		$ipp_helper = new INSTAWP_IPP_HELPER();
		$required_parameters = array(
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
		$start = time();
		$working_directory = $settings['working_directory'];
		$api_signature     = $settings['api_signature'];
		$bearer_token      = $settings['bearer_token'];
		$source_domain     = $settings['source_domain'];
		$mig_settings      = $settings['migrate_settings'];
		$db_host           = $settings['db_host'];
		$db_username       = $settings['db_username'];
		$db_password       = $settings['db_password'];
		$db_name           = $settings['db_name'];
		$dest_domain       = $settings['dest_domain'];
		$table_prefix      = isset( $mig_settings['table_prefix'] ) ? $mig_settings['table_prefix'] : '';
		$db_schema_only    = ! empty( $settings['db_schema_only'] );

		if ( empty( $mig_settings['db_actions'] ) ) {
			$ipp_helper->print_message( 'Missing parameter: db_actions', true );
			return false;
		}

		$table_rows_count = $mig_settings['db_actions']['source']['table_rows_count'];

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
			iwp_send_migration_log( 'iterative-push-db: DB information missing', "Could not found database information. Arguments: " . json_encode( $argv ) );

			return false;
		}

		$excluded_tables      = [];
		$excluded_tables_rows = [ 'option_name:_transient_wp_core_block_css_files' ];
		$relative_path        = realpath( $working_directory ) . DIRECTORY_SEPARATOR;

		if ( ! $db_schema_only ) {
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
				foreach ( $table_rows_count as $table_name => $rows_count ) {
					if ( ! in_array( $table_name, $excluded_tables ) ) {
						$total_tracking_tables++;
						$table_name_hash = hash( 'md5', $table_name );
						$tracking_db->insert( 'iwp_db_sent', array(
							'table_name'      => "'$table_name'",
							'table_name_hash' => "'$table_name_hash'",
							'rows_total'      => $rows_count,
						) );
					} else {
						unset( $table_rows_count[ $table_name ] );
					}
				}
			}

			$result = $tracking_db->get_row( 'iwp_db_sent', array( 'completed' => '2' ) );
			if ( empty( $result ) ) {
				$result = $tracking_db->get_row( 'iwp_db_sent', array( 'completed' => '0' ) );
			}

			if ( empty( $result ) ) {
				$ipp_helper->print_message( 'No more tables to process.' );
				return true;
			}
		}

		curl_setopt( $curl_session, CURLOPT_USERAGENT, 'InstaWP Migration Service - Iterative Push DB' );
		curl_setopt( $curl_session, CURLOPT_REFERER, $source_domain );
		curl_setopt( $curl_session, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl_session, CURLOPT_TIMEOUT, 120 );
		curl_setopt( $curl_session, CURLOPT_BUFFERSIZE, 2048 * 1024 ); // Set buffer size to 1MB;
		curl_setopt( $curl_session, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $curl_session, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $curl_session, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $curl_session, CURLOPT_COOKIE, "instawp_skip_splash=true" );
		curl_setopt( $curl_session, CURLOPT_HTTPHEADER, [
			'x-iwp-mode: ' . $migrate_mode,
			'x-iwp-api-signature: ' . $api_signature,
			'x-iwp-migrate-key: ' . $migrate_key,
			'x-iwp-table-prefix: ' . $table_prefix,
		] );

		iwp_send_progress( 0, 0, [ 'push-db-in-progress' => true ] );
		$ipp_helper->progress_bar( 0, 100, 50, $migrate_mode );
		
		if ( ! empty( $mig_settings['db_actions']['schema_queries'] ) && is_array( $mig_settings['db_actions']['schema_queries'] ) ) {
			$statements = implode( ";\n\n", $mig_settings['db_actions']['schema_queries'] );
			$curl_response  = instawp_iterative_push_db_curl( $statements, $curl_session, $api_signature, 0, $db_schema_only ? 100: 0, $ipp_helper, $progress_sent_at );
		}
		
		// Return if database schema only push is required
		if ( $db_schema_only ) {
			// Files pushing is completed
			iwp_send_progress( 0, 100, [ 'push-db-finished' => true ] );
			$ipp_helper->progress_bar( 100, 100, 50, $migrate_mode );
			$ipp_helper->print_message( 'Database schema push completed in ' . ( time() - $start ) . ' seconds' );
			return true;
		}

		$index             = 0;
		$slow_sleep        = 0;
		$tracking_progress = 0;
		/** 
		foreach ( $table_rows_count as $table_name => $rows_count ) {

			if ( $errors_counter >= 50 ) {
				iwp_send_migration_log( 'iterative-push-db: Tried maximum times', 'Our migration script retried maximum times of pushing db. Now exiting the migration', array(), true );

				return false;
			}

			$sqlStatements   = array();

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
				iwp_send_migration_log( 'iterative-push-db: Database query failed', "Database query error found. Actual error: {$mysqli->connect_error}, Query: $query" );

				return false;
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

			$curl_response  = instawp_iterative_push_db_curl( $statements, $curl_session, $api_signature, $index, $tracking_progress_req, $ipp_helper, $progress_sent_at );

			if ( false === $curl_response ) {
				continue;
			}
			$index ++;
		}
		**/

		// Files pushing is completed
		iwp_send_progress( 0, 100, [ 'push-db-finished' => true ] );
		$ipp_helper->progress_bar( 100, 100, 50, $migrate_mode );
		$ipp_helper->print_message( 'Database push completed in ' . ( time() - $start ) . ' seconds' );
		// echo 'DB pushing completed';
		// echo "Whole database is transferred successfully to the destination website. \n";
		return true;
	}
}