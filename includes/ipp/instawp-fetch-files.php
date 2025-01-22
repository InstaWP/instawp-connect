<?php
if ( ! function_exists( 'instawp_fetch_files' ) ) {
	function instawp_fetch_files( $settings ) {

        global $tracking_db, $migrate_key, $migrate_id, $migrate_mode, $migrate_curl_title, $bearer_token, $target_url;
		$start = time();
		$migrate_curl_title = $migrate_curl_title . ' Files';

        $migrate_settings  = empty( $settings['migrate_settings'] ) ? array(): $settings['migrate_settings'];
        $save_to_directory = $settings['save_to_directory'];
        $serve_type        = $settings['serve_type'];
        $api_signature     = $settings['api_signature'];
        $app_username      = isset( $settings['app_username'] ) ? $settings['app_username']: '';
        $migrate_mode      = empty( $migrate_mode ) ? 'pull': $migrate_mode;
        $is_iterative_pull = 'iterative_pull' === $migrate_mode;
        $stopFetching      = false;
        $errors_counter    = 0;

        if ( ! file_exists( $save_to_directory ) ) {
            if ( $is_iterative_pull ) {
                iwp_send_migration_log( 'Iterative pull files: dir not found', 'Wrong root directory path: ' . $save_to_directory, [], true );
                return false;
            } else {
                mkdir( $save_to_directory, 0755, true );
            }
        }

        //add trailing slash if not exists in $save_to_directory
        if ( substr( $save_to_directory, - 1 ) != '/' ) {
            $save_to_directory .= '/';
        }

        // Iterative pull only
        if ( $is_iterative_pull && ! empty( $migrate_settings['file_actions'] ) && ( ! empty( $migrate_settings['file_actions']['to_delete'] ) || ! empty( $migrate_settings['file_actions']['to_delete_folders'] ) ) ) {
            
            if ( ! function_exists( 'iwp_ipp_delete_files' ) ) {
                echo "Error: iwp_ipp_delete_files function not found. \n";
                return false;
                
            } 
            // delete files and folders
            $delete_files_response = iwp_ipp_delete_files( 
                $save_to_directory, 
                empty( $migrate_settings['file_actions']['to_delete'] ) ? array() : $migrate_settings['file_actions']['to_delete'], 
                empty( $migrate_settings['file_actions']['to_delete_folders'] ) ? array() : $migrate_settings['file_actions']['to_delete_folders']
            );

            if ( false === $delete_files_response['status'] ) {
                foreach ( $delete_files_response['messages'] as $message ) {
                    echo "Error: $message  \n";
                }
            }
        }

        $curl_session      = curl_init( $target_url );
        $curl_fields       = array(
            'serve_type'    => $serve_type,
            'api_signature' => $api_signature,
            'migrate_key'   => $migrate_key,
            'migrate_mode'  => $migrate_mode,
        );
        $headers           = [];
        $progress_sent_at  = time();
        $slow_sleep        = 0;
        $previous_progress = 0;

        curl_setopt( $curl_session, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $curl_session, CURLOPT_TIMEOUT, 50 );
        curl_setopt( $curl_session, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $curl_session, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $curl_session, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $curl_session, CURLOPT_CUSTOMREQUEST, 'POST' );
        curl_setopt( $curl_session, CURLOPT_POSTFIELDS, $curl_fields );
        curl_setopt( $curl_session, CURLOPT_BUFFERSIZE, 2048 * 1024 );
        curl_setopt( $curl_session, CURLOPT_USERAGENT, 'InstaWP Migration Service - Pull Files' );
        curl_setopt( $curl_session, CURLOPT_COOKIE, "instawp_skip_splash=true" );

        while ( ! $stopFetching ) {

            if ( $errors_counter >= 20 ) {
                iwp_send_migration_log( 'fetch-files: Tried maximum times', 'Our migration script retried maximum times of fetching files. Now exiting the migration' );

                return false;
            }

        //	$temp_destination = tempnam( '/Volumes/Dev/iwp/instacp-local/tmp', "iwp-file" ) . '.zip';
        //	$temp_destination = tempnam( sys_get_temp_dir(), "iwp-file" );
            $temp_destination = @tempnam( empty( $app_username ) ? sys_get_temp_dir(): "/home/{$app_username}/tmp/", "iwp-file" );
            $fp               = fopen( $temp_destination, 'w+b' );

            curl_setopt( $curl_session, CURLOPT_FILE, $fp );
            curl_setopt( $curl_session, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
                return iwp_process_curl_headers( $curl, $header, $headers );
            } );

            $response                   = curl_exec( $curl_session );
            $processed_response         = iwp_process_curl_response( $response, $curl_session, $headers, $errors_counter, $slow_sleep, 'fetch-files' );
            $processed_response_success = isset( $processed_response['success'] ) ? (bool) $processed_response['success'] : false;
            $processed_response_message = isset( $processed_response['message'] ) ? $processed_response['message'] : '';

            $header_status     = $headers['x-iwp-status'] ?? false;
            $header_message    = $headers['x-iwp-message'] ?? '';
            $transfer_complete = $headers['x-iwp-transfer-complete'] ?? false;
            $relative_path     = $headers['x-file-relative-path'] ?? '';
            $transfer_progress = $headers['x-iwp-progress'] ?? 0;
            $file_type         = $headers['x-file-type'] ?? 'single';
            $sent_filename     = $headers['x-iwp-sent-filename'] ?? '';
            $expected_checksum = $headers['x-iwp-checksum'] ?? '';
            $received_filename = $headers['x-iwp-filename'] ?? '';
            $received_filesize = $headers['x-iwp-filesize'] ?? '';
            
            if ( ! $processed_response_success ) {
                echo $processed_response_message . "\n";

                // unmark all the files inside the
                iwp_unmark_sent_files( $target_url, $sent_filename, $expected_checksum, $curl_fields );

                continue;
            }

            fclose( $fp );

            // Rename $temp_destination file with the $received_filename
            if ( ! empty( $received_filename ) ) {
                $new_temp_destination = dirname( $temp_destination ) . '/' . $received_filename;
                if ( rename( $temp_destination, $new_temp_destination ) ) {
                    $temp_destination = $new_temp_destination;
                    // echo "File renamed to: $received_filename from: $temp_destination\n";
                } else {
                    echo "Failed to rename file to: $received_filename\n";
                }
            }

            if ( $header_status == 'false' || $transfer_complete == 'true' ) {
                $stopFetching = true;
                if ( 'true' == $header_status && 'true' == $transfer_complete ) {
                    echo $header_message . "\n";
                } else {
                    echo "Error: $header_message\n";
                }
                break;
            }

            if ( $file_type == 'single' && ! empty( $relative_path ) ) {
                $save_to_path      = $save_to_directory . $relative_path;
                $save_to_path_name = dirname( $save_to_path );

                if ( ! file_exists( $save_to_path_name ) ) {
                    mkdir( $save_to_path_name, 0755, true );
                }

                if ( ! iwp_verify_checksum( $save_to_path, $expected_checksum, $received_filesize ) && ! empty( $sent_filename ) ) {
                    iwp_unmark_sent_files( $target_url, $sent_filename, $expected_checksum, $curl_fields );
                }

                //send progress update if 5 seconds passed
                if ( ( time() - $progress_sent_at > 5 ) && ( $transfer_progress - $previous_progress >= 0.1 ) ) {

                    iwp_send_progress( $transfer_progress );
                    iwp_progress_bar( $transfer_progress, 100, 50, $migrate_mode );
                    $progress_sent_at  = time();
                    $previous_progress = $transfer_progress;
                }

                rename( $temp_destination, $save_to_path );

                $errors_counter = 0;
            } elseif ( $file_type == 'zip' ) {

                if ( empty( $expected_checksum ) ) {

                    iwp_send_migration_log( 'fetch-files: Missing checksum', "Checksum not received for zip file: $temp_destination" );

                    if ( ! empty( $sent_filename ) ) {
                        iwp_unmark_sent_files( $target_url, $sent_filename, $expected_checksum, $curl_fields );
                    }
                }

                $calculated_filesize = filesize( $temp_destination );
                if ( $calculated_filesize === false ) {
                    $calculated_filesize = 0;
                    iwp_send_migration_log( 'fetch-files: File size check failed', "Could not get file size for: $temp_destination" );
                }

                if ( ! iwp_verify_checksum( $temp_destination, $expected_checksum, $received_filesize ) && ! empty( $sent_filename ) ) {
                    iwp_unmark_sent_files( $target_url, $sent_filename, $expected_checksum, $curl_fields );
                }

                $zip = new ZipArchive;
                $res = $zip->open( $temp_destination );
                if ( $res === true && $zip->status == 0 ) {

                    //send progress update if 5 seconds passed
                    if ( time() - $progress_sent_at > 5 ) {
                        iwp_send_progress( $transfer_progress );
                        iwp_progress_bar( $transfer_progress, 100, 50, $migrate_mode );
                        $progress_sent_at = time();
                    }

                    try {
                        $zip->extractTo( $save_to_directory );
                    } catch ( Exception $e ) {
                        iwp_send_migration_log( 'fetch-files: Extraction failed', "extractTo function failed to extract $temp_destination. in staging website. Actual error message is: " . $e->getMessage() );
                    }

                    $zip->close();

                    $errors_counter = 0;
                } else {
                    $errors_counter ++;

                    iwp_send_migration_log( 'fetch-files: Invalid ZIP', "Could not open $temp_destination ZipArchive System Error (statusSys): {$zip->statusSys} ZIP status: {$zip->status} - " );

                    iwp_unmark_sent_files( $target_url, $sent_filename, $expected_checksum, $curl_fields );
                }
            } else if ( ! $is_iterative_pull && 'inventory' == $file_type && ! class_exists( 'InstawpInventoryHandler' ) ) {

                /**
                 * InstawpInventoryHandler class
                 *
                 * @package InstaWP
                 */
                class InstawpInventoryHandler {
                    /**
                     * Temporary destination for downloaded files.
                     *
                     * @var string
                     */
                    private $temp_destination;

                    /**
                     * Directory to save extracted files.
                     *
                     * @var string
                     */
                    private $save_to_directory;

                    /**
                     * Domain for the inventory API.
                     *
                     * @var string
                     */
                    private $inventory_api_domain = 'inventory.instawp.io';

                    /**
                     * Maximum retries for wordpress item installation.
                     *
                     * @var int
                     */
                    private $maxRetries = 5;

                    /**
                     * Target URL for source website
                     *
                     * @var string
                     */
                    private $target_url;

                    /**
                     * Curl fields for source website
                     *
                     * @var array
                     */
                    private $curl_fields;

                    /**
                     * Failed items
                     */
                    private $failed_items = array();

                    /**
                     * Constructor
                     *
                     * @param string $temp_destination
                     * @param string $save_to_directory
                     */
                    public function __construct( $temp_destination, $save_to_directory, $target_url, $curl_fields ) {
                        $this->temp_destination  = $temp_destination;
                        $this->save_to_directory = $save_to_directory;
                        $this->target_url        = $target_url;
                        $this->curl_fields       = $curl_fields;
                    }

                    /**
                     * Adds a failed item to the list of failed items.
                     *
                     * @param array $item Item to be added. Should have 'slug' and 'type' keys.
                     *
                     * @return void
                     */
                    private function add_failed_item( $item ) {
                        if ( ! empty( $item['slug'] ) && ! empty( $item['type'] ) ) {
                            $key = $item['type'] . '_' . $item['slug'];
                            if ( empty( $this->failed_items[ $key ] ) ) {
                                $this->failed_items[ $key ] = $item;
                            }
                        }
                    }

                    /**
                     * Echoes the message and sends migration log.
                     *
                     * @param string $message Message to be sent.
                     * @param string $label Label for the log.
                     * @param array $payload Payload for the log.
                     *
                     * @return void
                     */
                    private function echo_send_migration_log( $message, $label = "Installation Failed", $payload = array() ) {
                        iwp_send_migration_log( $label, $message, $payload, true );
                    }

                    /**
                     * Process Inventory
                     *
                     * @param float $transfer_progress
                     *
                     * @return void
                     */
                    public function process( $transfer_progress ) {
                        try {
                            // Decode the inventory JSON
                            $inventory_items = $this->decodeInventoryJson();
                            if ( empty( $inventory_items ) || ! is_array( $inventory_items ) || empty( $inventory_items['token'] ) || empty( $inventory_items['items'] ) || empty( $inventory_items['with_checksum'] ) ) {
                                $this->echo_send_migration_log( "Error: inventory items not found." );

                                return;
                            }
                            $inventory_items['staging'] = empty( $inventory_items['staging'] ) ? 0 : 1;
                            // Get inventory data from the inventory API
                            $inventory_data = $this->api_call(
                                $inventory_items['token'],
                                'checksum',
                                $inventory_items['staging'],
                                array(
                                    'items'             => $inventory_items['items'],
                                    'with_download_url' => true,
                                    'max_download_size' => 100
                                )
                            );
                            // Check if the inventory data is valid
                            if ( empty( $inventory_data ) || ! is_array( $inventory_data ) || empty( $inventory_data['data'] ) || empty( $inventory_data['success'] ) ) {
                                $this->echo_send_migration_log(
                                    "Inventory API error found. Fall back to files migration.",
                                    "Inventory API Error",
                                    array(
                                        'response' => $inventory_data,
                                        'items'    => $inventory_items['items']
                                    )
                                );
                                iwp_inventory_sent_files( $this->target_url, 'failed', 'plugin_theme', $this->curl_fields, $inventory_items['with_checksum'] );

                                return;
                            }

                            // Get the inventory data
                            $inventory_data   = $inventory_data['data'];
                            $total_files_sent = 0;
                            // Get installed items list
                            $installed_items_list = array();
                            // Process each item version
                            foreach ( $inventory_items['with_checksum'] as $wp_item ) {
                                $item_type = 'plugin' === $wp_item['type'] ? 'plugin' : 'theme';
                                // Check if the item is available to install
                                if ( empty( $inventory_data[ $item_type ] ) ||
                                    empty( $inventory_data[ $item_type ][ $wp_item['slug'] ] ) ||
                                    empty( $inventory_data[ $item_type ][ $wp_item['slug'] ][ $wp_item['version'] ] ) ||
                                    empty( $inventory_data[ $item_type ][ $wp_item['slug'] ][ $wp_item['version'] ]['download_url'] ) ||
                                    empty( $inventory_data[ $item_type ][ $wp_item['slug'] ][ $wp_item['version'] ]['checksum'] ) ||
                                    $wp_item['checksum'] !== $inventory_data[ $item_type ][ $wp_item['slug'] ][ $wp_item['version'] ]['checksum'] ) {

                                    $this->echo_send_migration_log(
                                        "Failed to install {$item_type}: {$wp_item['slug']} (version {$wp_item['version']}) is not available or valid to install. Fall back to files migration.",
                                        "Inventory API Error",
                                        array(
                                            'response' => $inventory_data,
                                            'items'    => $inventory_items['items']
                                        )
                                    );
                                    $this->add_failed_item( $wp_item );
                                    continue;
                                }

                                // Check if the item is already installed
                                $installed_item_name = $item_type . '_' . $wp_item['slug'] . '_' . $wp_item['version'];
                                if ( in_array( $installed_item_name, $installed_items_list ) ) {
                                    continue;
                                }
                                $installed_items_list[] = $installed_item_name;

                                $download_url = $inventory_data[ $item_type ][ $wp_item['slug'] ][ $wp_item['version'] ]['download_url'];
                                // filter the download url
                                if ( filter_var( $download_url, FILTER_VALIDATE_URL ) === false ) {
                                    $this->echo_send_migration_log( "Invalid download url {$download_url} for Item {$wp_item['slug']} with version {$wp_item['version']}." );
                                    $this->add_failed_item( $wp_item );
                                    continue;
                                }
                                //$activate = empty( $wp_item['is_active'] ) ? '' : '--activate';
                                $activate = '';

                                $retries = 0;

                                // Retry the installation if the installation failed
                                while ( $retries < $this->maxRetries ) {
                                    // Install the item
                                    exec(
                                        'wp --path="' . $this->save_to_directory . '" ' . $item_type . ' install "' . $download_url . '" ' . $activate . ' --force',
                                        $output,
                                        $return_var
                                    );
                                    // Check if the installation is successful
                                    if ( $return_var !== 0 ) {
                                        $retries ++;
                                        if ( $retries >= $this->maxRetries ) {
                                            $this->echo_send_migration_log(
                                                "Failed to install {$item_type}: {$wp_item['slug']} (version {$wp_item['version']}). Our migration script retried maximum times of installation. Fall back to files migration.",
                                                "Installation Failed",
                                                array(
                                                    'output' => $output,
                                                    'item'   => $wp_item
                                                )
                                            );
                                            $this->add_failed_item( $wp_item );
                                            break;
                                        }
                                        sleep( iwp_backoff_timer( $retries ) );
                                    } else {
                                        $retries = $this->maxRetries;
                                        echo "Successfully installed {$item_type}: {$wp_item['slug']} (version {$wp_item['version']}) \n";

                                        // Check if the checksum is correct
                                        if ( ! empty( $wp_item['path'] ) ) {
                                            $wp_item_path = $this->save_to_directory . $wp_item['path'];
                                            if ( ! is_dir( $wp_item_path ) ) {
                                                $this->echo_send_migration_log(
                                                    "Directory not found {$wp_item_path}. Fall back to files migration.",
                                                    "installed {$item_type} path different"
                                                );
                                                $this->add_failed_item( $wp_item );
                                                break;
                                            } else if ( $wp_item['checksum'] !== $this->calculate_checksum( $wp_item_path ) ) {
                                                // exit migration if checksum not matched
                                                $this->echo_send_migration_log(
                                                    "Checksum mismatch {$item_type}: {$wp_item['slug']} (version {$wp_item['version']}). Fall back to files migration.",
                                                    "installed {$item_type} checksum mismatch",
                                                    array(
                                                        'destination_path' => $wp_item_path,
                                                    )
                                                );
                                                $this->add_failed_item( $wp_item );
                                                break;
                                            } else {
                                                echo "Checksum matched {$item_type}: {$wp_item['slug']} (version {$wp_item['version']}) \n";
                                                // Send inventory progress to API
                                                iwp_inventory_sent_files( $this->target_url, $wp_item['slug'], $item_type, $this->curl_fields );
                                                // Send inventory progress to App
                                                if ( ! empty( $wp_item['file_count'] ) && ! empty( $inventory_items['total_files_count'] ) ) {
                                                    $wp_item['file_count']                = intval( $wp_item['file_count'] );
                                                    $inventory_items['total_files_count'] = intval( $inventory_items['total_files_count'] );

                                                    if ( 0 < $wp_item['file_count'] && 0 < $inventory_items['total_files_count'] ) {
                                                        $total_files_sent  += $wp_item['file_count'];
                                                        $transfer_progress = round( ( $total_files_sent / $inventory_items['total_files_count'] ) * 100, 2 );
                                                        echo "Inventory transfer progress " . $transfer_progress . " \n";
                                                        iwp_send_progress( $transfer_progress );
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            if ( 0 < count( $this->failed_items ) ) {
                                iwp_inventory_sent_files( $this->target_url, 'failed', 'plugin_theme', $this->curl_fields, $this->failed_items );
                            }

                            echo date( "H:i:s" ) . ": Inventory processing completed: $transfer_progress%.\n";
                        } catch ( Exception $e ) {
                            $this->echo_send_migration_log( $e->getMessage(), "Installation Failed" );
                        }

                        return $transfer_progress;
                    }

                    /**
                     * Decode Inventory JSON
                     *
                     * @return array decoded inventory items
                     */
                    private function decodeInventoryJson() {
                        $inventory_items = json_decode( file_get_contents( $this->temp_destination ), true );
                        if ( json_last_error() !== JSON_ERROR_NONE ) {
                            echo "Error decoding inventory JSON: " . json_last_error_msg() . "\n";

                            return [];
                        }

                        return $inventory_items;
                    }

                    /**
                     * API Call
                     *
                     * @param string $end_point api end point
                     * @param array $body
                     *
                     * @return array response
                     */
                    public function api_call( $api_key, $end_point, $is_staging, $body = array() ) {

                        $ch = curl_init( "https://{$this->inventory_api_domain}/wp-json/instawp-checksum/v1/" . $end_point );

                        // Set the request method to POST
                        curl_setopt( $ch, CURLOPT_POST, true );

                        // Set the POST fields
                        curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $body ) );

                        // Set headers if any
                        curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                            'Authorization: Bearer ' . $api_key,
                            'staging: ' . $is_staging
                        ) );

                        // Return the response instead of printing it
                        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

                        // Execute the POST request
                        $response = curl_exec( $ch );

                        $response = json_decode( $response, true );
                        // Get the HTTP response status code
                        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
                        if ( 200 !== $http_code ) {
                            $message = $response['message'] ?? 'Unknown error.';
                            echo "Error: { $message }\n HTTP Code: $http_code\n";

                            return array();
                        }

                        // Close the cURL session
                        curl_close( $ch );

                        // Return the response and status code
                        return $response;
                    }

                    /**
                     * Calculate the crc32 based checksum of all files in a WordPress plugin|theme directory.
                     *
                     * @param string $folder The path to the specific plugin|theme directory.
                     *
                     * @return string checksum.
                     */
                    private function calculate_checksum( $folder ) {

                        if ( ! is_dir( $folder ) ) {
                            return false;
                        }

                        $files = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator( $folder, RecursiveDirectoryIterator::SKIP_DOTS ),
                            RecursiveIteratorIterator::LEAVES_ONLY
                        );

                        $totalHash = 0;
                        $fileCount = 0;
                        $totalSize = 0;

                        foreach ( $files as $file ) {
                            if ( $file->isFile() ) {

                                ++ $fileCount;
                                $filePath  = $file->getPathname();
                                $fileName  = $file->getFilename();
                                $fileSize  = $file->getSize();
                                $totalSize += $fileSize;
                                // Hash file metadata
                                $metadataHash = crc32( $fileName . $fileSize );

                                // Hash file contents (first and last 4KB)
                                $handle = fopen( $filePath, 'rb' );
                                if ( $handle ) {
                                    // Read first 4KB
                                    $firstChunk = fread( $handle, 4096 );
                                    $firstHash  = crc32( $firstChunk );

                                    // Read last 4KB
                                    fseek( $handle, - 4096, SEEK_END );
                                    $lastChunk = fread( $handle, 4096 );
                                    $lastHash  = crc32( $lastChunk );

                                    fclose( $handle );
                                }

                                // Combine hashes
                                $fileHash  = $metadataHash ^ $firstHash ^ $lastHash;
                                $totalHash ^= $fileHash;
                            }
                        }

                        // Incorporate file count and total size into final hash
                        $finalHash = $totalHash ^ crc32( $fileCount . $totalSize );

                        // Return the checksum
                        return sprintf( '%u', $finalHash );
                    }
                }

                // Send Progress report
                iwp_send_progress( 0, 0, [ 'inventory-installation-started' => true ] );
                // Check if the file is inventory file
                $inventoryHandler   = new InstawpInventoryHandler( $temp_destination, $save_to_directory, $target_url, $curl_fields );
                $inventory_progress = $inventoryHandler->process( $transfer_progress );
                $transfer_progress  = ( ! empty( $inventory_progress ) && is_numeric( $inventory_progress ) ) ? $inventory_progress : $transfer_progress;
                $progress_sent_at   = time();
                $previous_progress  = $transfer_progress;
                // Send Progress report
                iwp_send_progress( $transfer_progress, 0, [ 'inventory-installation-completed' => true ] );
                iwp_send_progress( $transfer_progress, 0, [ 'pull-files-in-progress' => true ] );
            } else {
                $errors_counter ++;

                iwp_send_migration_log( 'fetch-files: X-File-Relative-Path failed', "Error: File type not set or could not find the X-File-Relative-Path header in the response. Received file type: $file_type" );

                if ( ! empty( $sent_filename ) ) {
                    iwp_unmark_sent_files( $target_url, $sent_filename, $expected_checksum, $curl_fields );
                }
            }


            if ( file_exists( $temp_destination ) ) {
                unlink( $temp_destination );
            } else {
                echo "File does not exist: $temp_destination\n";
            }

            if ( $slow_sleep ) {
                sleep( $slow_sleep );
            }
        }

        curl_close( $curl_session );

        // Files pulling is completed
        iwp_send_progress( 100, 0, [ 'pull-files-finished' => true ] );
        iwp_progress_bar( 100, 100, 50, $migrate_mode );
        if ( $is_iterative_pull ) {
            echo "File pull completed in " . ( time() - $start ) . " seconds. \n";
        } else {
            echo "All files are transferred successfully from the source website to the staging website. \n";
        }
        
        return true;
    }
}