<?php
/**
 * Iterative push files
 * Handles file pushing operations for InstaWP migrations
 * File statuses
 * 1 => 'sent',
 * 2 => 'in-progress',
 * 3 => 'failed',
 * 4 => 'skipped',
 * 5 => 'invalid',
 */
defined( 'ABSPATH' ) || exit;
if ( ! function_exists( 'instawp_iterative_push_files' ) ) {
	function instawp_iterative_push_files( $settings ) {

		global $tracking_db, $migrate_key, $migrate_id, $migrate_mode, $bearer_token, $target_url;
		$max_zip_size =  1024 * 1024;
		$max_retry = 5;
		$retry_wait = 5;//in seconds
		$batch_size = 100;
		$batch_zip_size = 50;
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

		$working_directory = $settings['working_directory'];
		$api_signature     = $settings['api_signature'];
		$source_domain     = $settings['source_domain'];
		$mig_settings      = $settings['migrate_settings'];
		$curl_session      = curl_init( $target_url );
		$errors_counter    = 0;
		$headers           = [];
		$progress_sent_at  = time();
		$relative_path     = realpath( $working_directory ) . DIRECTORY_SEPARATOR;	
		if ( empty( $mig_settings['file_actions'] ) ) {
			$ipp_helper->print_message( 'Missing parameter: file_actions', true );
			return false;
		}

		// Delete files
		if ( ! empty( $mig_settings['file_actions']['to_delete'] ) ) {	
			curl_setopt( $curl_session, CURLOPT_USERAGENT, 'InstaWP Migration Service - Push delete Files' );
			curl_setopt( $curl_session, CURLOPT_REFERER, $source_domain );
			curl_setopt( $curl_session, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl_session, CURLOPT_TIMEOUT, 120 );
			curl_setopt( $curl_session, CURLOPT_BUFFERSIZE, 2048 * 1024 ); // Set buffer size to 1MB;
			curl_setopt( $curl_session, CURLOPT_SSL_VERIFYPEER, 0 );
			curl_setopt( $curl_session, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
				return iwp_process_curl_headers( $curl, $header, $headers );
			} );
			curl_setopt( $curl_session, CURLOPT_POSTFIELDS, $ipp_helper->get_iterative_push_curl_params(
				array(
					'delete_files' => $mig_settings['file_actions']['to_delete']
				)
			) );
			curl_setopt( $curl_session, CURLOPT_COOKIE, "instawp_skip_splash=true" );
			curl_setopt( $curl_session, CURLOPT_HTTPHEADER, [
				'x-iwp-mode: ' . $migrate_mode,
				'x-iwp-api-signature: ' . $api_signature,
				'x-iwp-migrate-key: ' . $migrate_key,
			] );

			$response                   = curl_exec( $curl_session );
			$processed_response         = iwp_process_curl_response( $response, $curl_session, $headers, $errors_counter, $slow_sleep, 'push-files-1' );
			$processed_response_success = isset( $processed_response['success'] ) ? (bool) $processed_response['success'] : false;
			$processed_response_message = isset( $processed_response['message'] ) ? $processed_response['message'] : '';
			if ( ! $processed_response_success ) {
				$ipp_helper->print_message( $processed_response_message );	
			}
		}

		if ( empty( $mig_settings['file_actions']['to_send'] ) ) {	
			$ipp_helper->print_message( 'Files push completed.' );
			return false;
		}

		$excluded_paths_def = array(
			'editor',
			'wp-content' . DIRECTORY_SEPARATOR . 'cache',
			'wp-content' . DIRECTORY_SEPARATOR . 'upgrade',
			'wp-content' . DIRECTORY_SEPARATOR . 'instawpbackups',
			'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'iwp-migration',
			'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'iwp-migration-main',
			'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'instawp-connect',
		);
		$excluded_paths     = is_array( $mig_settings ) && isset( $mig_settings['excluded_paths'] ) ? $mig_settings['excluded_paths'] : [];
		$excluded_paths     = array_merge( $excluded_paths_def, $excluded_paths );
		$skip_files         = [];

		$unsentFilesCount = $tracking_db->query_count( 'iwp_files_sent', array( 'sent' => '0' ) );;
		$progressPer      = 0;
		$sentFilesCount   = 0;
		$totalFiles       = $unsentFilesCount;
		$files_to_send	   = empty( $mig_settings['file_actions']['to_send'] ) ? array(): $mig_settings['file_actions']['to_send'];

		if ( $unsentFilesCount == 0 && ! empty( $files_to_send ) ) {
			try { 
				// Get the files array
				$files_array = $ipp_helper->reorder_files_for_push( $files_to_send, $relative_path );
				$totalFiles  = 0;

				if ( empty( $files_array ) ) {
					iwp_send_migration_log( 'push-files: Failed to get files', 'Failed to get files', array(), true );
					return false;
				}

				// Calculate total files
				foreach ( $files_array as $file_category => $files ) {
					$totalFiles += count( $files );
				}

				// Process each file category in separate loops
				foreach ( $files_array as $file_category => $files ) {
					$file_category_count = count( $files );
					$currentIndex  = 0;
					$batchSize     = 5000;
					//$ipp_helper->print_message( "Processing $file_category $file_category_count files start" );

					// Process each file in batches
					while ( $currentIndex < $file_category_count ) {
						$endIndex = min($currentIndex + $batchSize, $file_category_count);
						for ( $i = $currentIndex; $i < $endIndex; $i++ ) {
							$file = $files[ $i ];
							$tracking_db->insert( 'iwp_files_sent', array(
								'filepath'      => $file['filepath'],
								'filepath_hash' => $file['filepath_hash'],
								'sent'          => 0,
								'size'          => $file['size'],
							) );
						}
						$currentIndex += $batchSize;

						// Add progress logging for each file category
						$ipp_helper->print_message( "Processing $file_category entries: " . round(($endIndex / $file_category_count) * 100) . "%\n" );
					}
					
					// Log completion of file category
					//echo "Completed entries for $file_category\n";
				}

			} catch ( Exception $e ) {
				iwp_send_migration_log( 'push-files: Failed to get files', $e->getMessage(), array(), true );
				return false;
			}
		}

		if ( $totalFiles == 0 ) {
			iwp_send_migration_log( 'push-files: All files sent', "All the files have been sent to the destination website.", array(), true );
			return true;
		}

		$slow_sleep = 0;

		curl_setopt( $curl_session, CURLOPT_USERAGENT, 'InstaWP Migration Service - Push Files' );
		curl_setopt( $curl_session, CURLOPT_REFERER, $source_domain );
		curl_setopt( $curl_session, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl_session, CURLOPT_TIMEOUT, 120 );
		curl_setopt( $curl_session, CURLOPT_BUFFERSIZE, 2048 * 1024 ); // Set buffer size to 1MB;
		curl_setopt( $curl_session, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $curl_session, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
			return iwp_process_curl_headers( $curl, $header, $headers );
		} );
		curl_setopt( $curl_session, CURLOPT_POSTFIELDS, $ipp_helper->get_iterative_push_curl_params(
			array(
				'check' => true
			)
		) );
		curl_setopt( $curl_session, CURLOPT_COOKIE, "instawp_skip_splash=true" );
		curl_setopt( $curl_session, CURLOPT_HTTPHEADER, [
			'x-iwp-mode: ' . $migrate_mode,
			'x-iwp-api-signature: ' . $api_signature,
			'x-iwp-migrate-key: ' . $migrate_key,
		] );

		$response                   = curl_exec( $curl_session );
		$processed_response         = iwp_process_curl_response( $response, $curl_session, $headers, $errors_counter, $slow_sleep, 'push-files-1' );
		$processed_response_success = isset( $processed_response['success'] ) ? (bool) $processed_response['success'] : false;
		$processed_response_message = isset( $processed_response['message'] ) ? $processed_response['message'] : '';

		if ( ! $processed_response_success ) {
			$ipp_helper->print_message( $processed_response_message );
		}

		$has_zip_archive = $headers['x-iwp-zip'] ?? false;
		$has_phar_data   = $headers['x-iwp-phar'] ?? false;
		$stopFetching    = false;
		$status_codes    = [];

		iwp_send_progress( 0, 0, [ 'push-files-in-progress' => true ] );

		// Log message
		if ( ! empty( $headers['x-iwp-message'] ) ) {
			echo "Message: " . $headers['x-iwp-message'] . "\n";
		}

		if ( $has_zip_archive || $has_phar_data ) {
			$unsentFiles = [];
			$index       = 0;

			while ( ! $stopFetching ) {

				if ( $errors_counter >= 50 ) {
					$status_codes = array_filter( $status_codes, function ( $code ) {
						return $code == 418;
					} );

					if ( count( $status_codes ) > 5 ) {
						$stopFetching = true;
						break;
					}

					iwp_send_migration_log( 'push-files: Tried maximum times (Zip)', 'Our migration script retried maximum times of pushing zip files. Now exiting the migration' );

					return false;
				}

				$files_data = $tracking_db->getUnsentFiles( $batch_size, $max_zip_size );

				$tmpZip  = tempnam( sys_get_temp_dir(), 'batchzip' );
				$archive = new ZipArchive();

				if ( $archive->open( $tmpZip, ZipArchive::OVERWRITE ) !== true ) {

					iwp_send_migration_log( 'push-files: Failed to open Zip', "Cannot open zip archive. Zip: $tmpZip" );

					die( "Cannot open zip archive" );
				}

				$sent_files = [];

				foreach ( $files_data as $file_data ) {
					$filePath         = $file_data['filepath'];
					$relativePath     = ltrim( str_replace( $relative_path, '', $filePath ), DIRECTORY_SEPARATOR );
					$file_fopen_check = fopen( $filePath, 'r' );

					if ( ! $file_fopen_check ) {
						$tracking_db->update( 'iwp_files_sent', array( 'sent' => '3' ), array( 'id' => $file_data['id'] ) );
						iwp_send_migration_log( 'push-files: File opening failed', "Can not open file: $filePath", array(), true );
						continue;
					}

					fclose( $file_fopen_check );

					if ( ! is_readable( $filePath ) ) {
						$tracking_db->update( 'iwp_files_sent', array( 'sent' => '3' ), array( 'id' => $file_data['id'] ) );
						iwp_send_migration_log( 'push-files: File reading failed', "Can not read file: $filePath", array(), true );
						continue;
					}

					if ( ! is_file( $filePath ) ) {
						$tracking_db->update( 'iwp_files_sent', array( 'sent' => '3' ), array( 'id' => $file_data['id'] ) );
						iwp_send_migration_log( 'push-files: Invalid file', "Invalid file: $filePath", array(), true );
						continue;
					}

					$filePath = iwp_process_files( $filePath, $relativePath );

					$added_to_zip = $archive->addFile( $filePath, str_replace( '\\', '/', $relativePath ) );

					if ( $added_to_zip === true ) {
						$sent_files[] = $file_data['id'];
					} else {
						$tracking_db->update( 'iwp_files_sent', array( 'sent' => '3' ), array( 'id' => $file_data['id'] ) );
						iwp_send_migration_log( 'push-files: Failed to zip the files', "Can not add file: $filePath to the zip", array(), true );
					}
				}

				$archive->close();

				if ( count( $sent_files ) < 1 ) {
					$stopFetching = true;
					break;
				}

				$file_stream  = fopen( $tmpZip, 'r' );
				$progress_per = round( ( ( $sentFilesCount + count( $sent_files ) ) / $totalFiles ) * 100, 2 );

				curl_setopt( $curl_session, CURLOPT_URL, $target_url . '?r=' . $index );
				curl_setopt( $curl_session, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
					return iwp_process_curl_headers( $curl, $header, $headers );
				} );
				curl_setopt( $curl_session, CURLOPT_INFILE, $file_stream );
				curl_setopt( $curl_session, CURLOPT_INFILESIZE, filesize( $tmpZip ) );
				curl_setopt( $curl_session, CURLOPT_PUT, true );
				curl_setopt( $curl_session, CURLOPT_COOKIE, "instawp_skip_splash=true" );
				curl_setopt( $curl_session, CURLOPT_HTTPHEADER, [
					'Content-Type: application/octet-stream',
					'x-file-relative-path: batch-' . $index . '.zip',
					'x-file-type: zip',
					'x-iwp-progress: ' . $progress_per,
					'x-iwp-api-signature: ' . $api_signature,
					'x-iwp-migrate-key: ' . $migrate_key,
					'x-iwp-mode: ' . $migrate_mode,
				] );
				// curl_setopt($curl_session, CURLOPT_VERBOSE, true);

				$response                   = curl_exec( $curl_session );
				$processed_response         = iwp_process_curl_response( $response, $curl_session, $headers, $errors_counter, $slow_sleep, 'push-files-2' );
				$processed_response_success = isset( $processed_response['success'] ) ? (bool) $processed_response['success'] : false;
				$processed_response_code    = isset( $processed_response['status_code'] ) ? (int) $processed_response['status_code'] : 0;
				$processed_response_message = isset( $processed_response['message'] ) ? $processed_response['message'] : '';

				if ( ! $processed_response_success ) {
					$status_codes[] = $processed_response_code;

					echo $processed_response_message;

					continue;
				}

				fclose( $file_stream );

				$header_status  = $headers['x-iwp-status'] ?? false;
				$header_message = $headers['x-iwp-message'] ?? '';

				if ( $header_status === 'false' ) {
					echo "Error: $header_message\n";
					die;
				}

				unlink( $tmpZip );

				foreach ( $sent_files as $file_id ) {
					$tracking_db->update( 'iwp_files_sent', array( 'sent' => '1' ), array( 'id' => $file_id ) );
					$sentFilesCount ++;
				}

				$progressPer = round( ( $sentFilesCount / $totalFiles ) * 100, 2 );

				if ( time() - $progress_sent_at > 5 ) {
					iwp_send_progress( $progressPer );
					echo date( "H:i:s" ) . ": Progress: $progressPer%\n";
					$progress_sent_at = time();
				}

				$index ++;

				if ( $slow_sleep ) {
					sleep( $slow_sleep );
				}
			}
		}

		$stopFetchingSingle = false;

		while ( ! $stopFetchingSingle ) {
			if ( $errors_counter >= 50 ) {
				iwp_send_migration_log( 'push-files: Tried maximum times (Single)', 'Our migration script retried maximum times of pushing single files. Now exiting the migration', array(), true );

				return false;
			}

			// Now iterate through unsent files and send them
			$index      = 0;
			$files_data = $tracking_db->getUnsentFiles( $batch_size );;

			foreach ( $files_data as $file_data ) {
				$filePath     = $file_data['filepath'];
				$fileId       = $file_data['id'];
				$relativePath = ltrim( str_replace( '\\', '/', str_replace( $relative_path, '', $filePath ) ), DIRECTORY_SEPARATOR );
				$file_stream  = fopen( $filePath, 'r' );
				$progress_per = round( ( ( $sentFilesCount + 1 ) / $totalFiles ) * 100, 2 );

				curl_setopt( $curl_session, CURLOPT_URL, $target_url . '?r=' . $index );
				curl_setopt( $curl_session, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
					return iwp_process_curl_headers( $curl, $header, $headers );
				} );
				curl_setopt( $curl_session, CURLOPT_INFILE, $file_stream );
				curl_setopt( $curl_session, CURLOPT_INFILESIZE, filesize( $filePath ) );
				curl_setopt( $curl_session, CURLOPT_PUT, true );
				curl_setopt( $curl_session, CURLOPT_HTTPHEADER, [
					'Content-Type: application/octet-stream',
					'x-file-relative-path: ' . $relativePath,
					'x-file-type: single',
					'x-iwp-progress: ' . $progress_per,
					'x-iwp-api-signature: ' . $api_signature,
					'x-iwp-migrate-key: ' . $migrate_key,
					'x-iwp-mode: ' . $migrate_mode,
				] );

				$response                   = curl_exec( $curl_session );
				$processed_response         = iwp_process_curl_response( $response, $curl_session, $headers, $errors_counter, $slow_sleep, 'push-files-3' );
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
					echo "Error: $header_message\n";
					die;
				}
				// Mark sent success
				$tracking_db->update( 'iwp_files_sent', array( 'sent' => '1' ), array( 'id' => $fileId ) );

				$sentFilesCount ++;
				$progressPer = round( ( $sentFilesCount / $totalFiles ) * 100, 2 );

				if ( time() - $progress_sent_at > 5 ) {
					iwp_send_progress( $progressPer );
					echo date( "H:i:s" ) . ": Progress: $progressPer%\n";
					$progress_sent_at = time();
				}

				$index ++;

				if ( $slow_sleep ) {
					sleep( $slow_sleep );
				}
			}

			if ( $index < 1 ) {
				$stopFetchingSingle = true;
				break;
			}
		}

		curl_close( $curl_session );

		// Files pushing is completed
		iwp_send_progress( 100, 0, [ 'push-files-finished' => true ] );

		$ipp_helper->print_message( 'Files push completed.' );
		return true;
	}
}