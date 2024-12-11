<?php
/**
 * Class for handling file fetching operations in InstaWP migrations
 *
 * @package InstaWP
 */

if ( ! defined( 'WP_CLI' ) ) {
    return;
}

/**
 * Class InstaWP_Fetch_Files
 * Handles file fetching operations for InstaWP migrations
 */
class InstaWP_Fetch_Files {

    /**
     * Target URL for file fetching.
     *
     * @var string
     */
    private $target_url;

    /**
     * Directory to save files.
     *
     * @var string
     */
    private $save_to_directory;

    /**
     * Type of service (files/db).
     *
     * @var string
     */
    private $serve_type;

    /**
     * API signature for authentication.
     *
     * @var string
     */
    private $api_signature;

    /**
     * Migration key.
     *
     * @var string
     */
    private $migrate_key;

    /**
     * Migration ID.
     *
     * @var string
     */
    private $migrate_id;

    /**
     * Bearer token for authentication.
     *
     * @var string
     */
    private $bearer_token;

    /**
     * CURL session handle.
     *
     * @var resource|false
     */
    private $curl_session;

    /**
     * Counter for errors encountered.
     *
     * @var int
     */
    private $errors_counter = 0;

    /**
     * Timestamp of last progress update.
     *
     * @var int
     */
    private $progress_sent_at;

    /**
     * Previous progress percentage.
     *
     * @var float
     */
    private $previous_progress = 0;

    /**
     * Sleep duration for rate limiting.
     *
     * @var int
     */
    private $slow_sleep = 0;

	/**
	 * @var INSTAWP_IPP_Helper
	 */
	private $helper;

    /**
     * Constructor
     *
     * @param array $args {
     *     Array of arguments for file fetching.
     *
     *     @type string $target_url        URL to fetch files from
     *     @type string $save_directory    Directory to save files to
     *     @type string $serve_type        Type of service (files/db)
     *     @type string $api_signature     API signature for authentication
     *     @type string $migrate_key       Migration key
     *     @type string $migrate_id        Migration ID
     *     @type string $bearer_token      Bearer token for authentication
     * }
     */
    public function __construct( $args ) {
        if ( ! isset( 
            $args['target_url'],
            $args['save_directory'],
            $args['serve_type'],
            $args['api_signature'],
            $args['migrate_key'],
            $args['migrate_id'],
            $args['bearer_token']
        ) ) {
            WP_CLI::error( 'Required parameters missing. Usage: URL directory files/db api_sig migrate_key migrate_id bearer_token' );
        }

		$this->helper 			 = new INSTAWP_IPP_Helper();
        $this->target_url        = $args['target_url'];
        $this->save_to_directory = $args['save_directory'];
        $this->serve_type        = $args['serve_type'];
        $this->api_signature     = $args['api_signature'];
        $this->migrate_key       = $args['migrate_key'];
        $this->migrate_id        = $args['migrate_id'];
        $this->bearer_token      = $args['bearer_token'];
        $this->progress_sent_at  = time();

        $this->initialize_directory();
        $this->initialize_curl();
    }

    /**
     * Initialize directory for saving files
     *
     * @return void
     */
    private function initialize_directory() {
        if ( ! file_exists( $this->save_to_directory ) ) {
            mkdir( $this->save_to_directory, 0755, true );
        }

        if ( '/' !== substr( $this->save_to_directory, -1 ) ) {
            $this->save_to_directory .= '/';
        }
    }

    /**
     * Initialize CURL session with required settings
     *
     * @return void
     */
    private function initialize_curl() {
        $this->curl_session = curl_init( $this->target_url );
        $curl_fields        = array(
            'serve_type'    => $this->serve_type,
            'api_signature' => $this->api_signature,
            'migrate_key'   => $this->migrate_key,
        );

        curl_setopt( $this->curl_session, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $this->curl_session, CURLOPT_TIMEOUT, 50 );
        curl_setopt( $this->curl_session, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $this->curl_session, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $this->curl_session, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $this->curl_session, CURLOPT_CUSTOMREQUEST, 'POST' );
        curl_setopt( $this->curl_session, CURLOPT_POSTFIELDS, $curl_fields );
        curl_setopt( $this->curl_session, CURLOPT_BUFFERSIZE, 2048 * 1024 );
        curl_setopt( $this->curl_session, CURLOPT_USERAGENT, 'InstaWP Migration Service - Pull Files' );
        curl_setopt( $this->curl_session, CURLOPT_COOKIE, 'instawp_skip_splash=true' );
    }

    /**
     * Process CURL headers
     *
     * @param resource $curl   CURL handle.
     * @param string   $header Header line.
     * @param array    $headers Headers array to populate.
     * @return int Length of header line.
     */
    private function process_curl_headers( $curl, $header, &$headers ) {
        $len    = strlen( $header );
        $header = explode( ':', $header, 2 );
        
        if ( count( $header ) < 2 ) {
            return $len;
        }

        $headers[ strtolower( trim( $header[0] ) ) ] = trim( $header[1] );
        return $len;
    }

    /**
     * Start the file fetching process
     *
     * @return void
     */
    public function start() {
        WP_CLI::log( 'Fetch files started' );
        $this->helper->iwp_send_migration_log( 'Executed', 'Fetch files started' );

        $stop_fetching = false;
        $headers       = array();

        while ( ! $stop_fetching ) {
            if ( $this->errors_counter >= 20 ) {
                $error_message = 'Our migration script retried maximum times of fetching files. Now exiting the migration';
                WP_CLI::error( $error_message );
                $this->helper->iwp_send_migration_log( 'fetch-files: Tried maximum times', $error_message );
                exit( 1 );
            }

            $temp_destination = tempnam( sys_get_temp_dir(), 'iwp-file' );
            $file_handle     = fopen( $temp_destination, 'w+b' );

            curl_setopt( $this->curl_session, CURLOPT_FILE, $file_handle );
            curl_setopt( $this->curl_session, CURLOPT_HEADERFUNCTION, function( $curl, $header ) use ( &$headers ) {
                return $this->process_curl_headers( $curl, $header, $headers );
            });

            $response = curl_exec( $this->curl_session );
            $this->handle_response( $response, $headers, $temp_destination, $stop_fetching, $file_handle );
        }

        // Files pulling is completed
        $this->helper->iwp_send_progress( 100, 0, array( 'pull-files-finished' => true ) );
    }

    /**
     * Handle CURL response
     *
     * @param mixed    $response         CURL response.
     * @param array    $headers          Response headers.
     * @param string   $temp_destination Temporary file path.
     * @param boolean  $stop_fetching    Reference to stop fetching flag.
     * @param resource $file_handle      File handle.
     * @return void
     */
    private function handle_response( $response, $headers, $temp_destination, &$stop_fetching, $file_handle ) {
        $processed_response = $this->helper->iwp_process_curl_response( 
            $response, 
            $this->curl_session, 
            $headers, 
            $this->errors_counter, 
            $this->slow_sleep, 
            'fetch-files' 
        );

        $processed_response_success = isset( $processed_response['success'] ) ? (bool) $processed_response['success'] : false;
        $processed_response_message = isset( $processed_response['message'] ) ? $processed_response['message'] : '';

        $header_status     = $headers['x-iwp-status'] ?? false;
        $header_message    = $headers['x-iwp-message'] ?? '';
        $transfer_complete = $headers['x-iwp-transfer-complete'] ?? false;
        $relative_path     = $headers['x-file-relative-path'] ?? '';
        $transfer_progress = $headers['x-iwp-progress'] ?? 0;
        $file_type        = $headers['x-file-type'] ?? 'single';
        $sent_filename    = $headers['x-iwp-sent-filename'] ?? '';
        $expected_checksum = $headers['x-iwp-checksum'] ?? '';
        $received_filename = $headers['x-iwp-filename'] ?? '';

        fclose( $file_handle );

        if ( ! $processed_response_success ) {
            WP_CLI::warning( $processed_response_message );
            $this->unmark_sent_files( $sent_filename, $expected_checksum );
            return;
        }

        if ( ! empty( $received_filename ) ) {
            $this->rename_temp_file( $temp_destination, $received_filename );
        }

        if ( 'false' === $header_status || 'true' === $transfer_complete ) {
            $stop_fetching = true;
            WP_CLI::warning( "Error: $header_message" );
            return;
        }

        switch ( $file_type ) {
            case 'single':
                $this->handle_single_file( $temp_destination, $relative_path, $expected_checksum, $sent_filename, $transfer_progress );
                break;
            case 'zip':
                $this->handle_zip_file( $temp_destination, $expected_checksum, $sent_filename, $transfer_progress );
                break;
            case 'inventory':
                $this->handle_inventory_file( $temp_destination, $transfer_progress );
                break;
            default:
                $this->errors_counter++;
                WP_CLI::warning( "Error: File type not set or invalid. Received file type: $file_type" );
                if ( ! empty( $sent_filename ) ) {
                    $this->unmark_sent_files( $sent_filename, $expected_checksum );
                }
        }

        if ( file_exists( $temp_destination ) ) {
            unlink( $temp_destination );
        } else {
            WP_CLI::warning( "File does not exist: $temp_destination" );
        }

        if ( $this->slow_sleep ) {
            sleep( $this->slow_sleep );
        }
    }

    /**
     * Handle single file transfer
     *
     * @param string $temp_destination Temporary file path.
     * @param string $relative_path    Relative path for the file.
     * @param string $expected_checksum Expected checksum.
     * @param string $sent_filename    Original filename.
     * @param float  $transfer_progress Transfer progress percentage.
     * @return void
     */
    private function handle_single_file( $temp_destination, $relative_path, $expected_checksum, $sent_filename, $transfer_progress ) {
        if ( empty( $relative_path ) ) {
            return;
        }

        $save_to_path      = $this->save_to_directory . $relative_path;
        $save_to_path_name = dirname( $save_to_path );

        if ( ! file_exists( $save_to_path_name ) ) {
            mkdir( $save_to_path_name, 0755, true );
        }

        if ( ! iwp_verify_checksum( $save_to_path, $expected_checksum ) ) {
            WP_CLI::warning( "Checksum verification failed for file: $save_to_path" );
            $this->unmark_sent_files( $sent_filename, $expected_checksum );
            return;
        }

        $this->update_progress( $transfer_progress );
        rename( $temp_destination, $save_to_path );
        $this->errors_counter = 0;
    }

    /**
     * Handle ZIP file transfer
     *
     * @param string $temp_destination Temporary file path.
     * @param string $expected_checksum Expected checksum.
     * @param string $sent_filename    Original filename.
     * @param float  $transfer_progress Transfer progress percentage.
     * @return void
     */
    private function handle_zip_file( $temp_destination, $expected_checksum, $sent_filename, $transfer_progress ) {
        if ( empty( $expected_checksum ) ) {
            WP_CLI::warning( "Checksum not received for zip file: $temp_destination" );
            $this->unmark_sent_files( $sent_filename, $expected_checksum );
            return;
        }

        if ( ! iwp_verify_checksum( $temp_destination, $expected_checksum ) ) {
            WP_CLI::warning( "Checksum verification failed for zip file: $temp_destination" );
            $this->unmark_sent_files( $sent_filename, $expected_checksum );
            return;
        }

        $zip = new ZipArchive();
        $res = $zip->open( $temp_destination );
        
        if ( true === $res && 0 === $zip->status ) {
            $this->update_progress( $transfer_progress );
            
            try {
                $zip->extractTo( $this->save_to_directory );
                $this->errors_counter = 0;
            } catch ( Exception $e ) {
                WP_CLI::warning( "Extraction failed: " . $e->getMessage() );
                $this->helper->iwp_send_migration_log( 'fetch-files: Extraction failed', $e->getMessage() );
            }
            
            $zip->close();
        } else {
            $this->errors_counter++;
            WP_CLI::warning( "Could not open $temp_destination. ZIP status: {$zip->status}" );
            $this->unmark_sent_files( $sent_filename, $expected_checksum );
        }
    }

    /**
     * Update progress
     *
     * @param float $transfer_progress Transfer progress percentage.
     * @return void
     */
    private function update_progress( $transfer_progress ) {
        if ( ( time() - $this->progress_sent_at > 5 ) && ( $transfer_progress - $this->previous_progress >= 0.1 ) ) {
            $this->helper->iwp_send_progress( $transfer_progress );
            WP_CLI::log( date( 'H:i:s' ) . ": File transfer progress: $transfer_progress%" );
            $this->progress_sent_at  = time();
            $this->previous_progress = $transfer_progress;
        }
    }

    /**
     * Unmark sent files
     *
     * @param string $sent_filename    Original filename.
     * @param string $expected_checksum Expected checksum.
     * @return void
     */
    private function unmark_sent_files( $sent_filename, $expected_checksum ) {
        $curl_fields = array(
            'serve_type'    => $this->serve_type,
            'api_signature' => $this->api_signature,
            'migrate_key'   => $this->migrate_key,
        );
        $this->helper->iwp_unmark_sent_files( $this->target_url, $sent_filename, $expected_checksum, $curl_fields );
    }

    /**
     * Rename temporary file
     *
     * @param string $temp_destination Temporary file path.
     * @param string $received_filename New filename.
     * @return void
     */
    private function rename_temp_file( $temp_destination, $received_filename ) {
        $new_temp_destination = dirname( $temp_destination ) . '/' . $received_filename;
        if ( rename( $temp_destination, $new_temp_destination ) ) {
            $temp_destination = $new_temp_destination;
        } else {
            WP_CLI::warning( "Failed to rename file to: $received_filename" );
        }
    }
}

/**
 * Class InstawpInventoryHandler
 * Handles inventory file processing
 */
class InstawpInventoryHandler {
    private $temp_destination;
    private $save_to_directory;
    private $target_url;
    private $curl_fields;
    private $failed_items = array();
	private $helper;

    /**
     * Constructor
     */
    public function __construct($temp_destination, $save_to_directory, $target_url, $curl_fields) {
        $this->temp_destination = $temp_destination;
        $this->save_to_directory = $save_to_directory;
        $this->target_url = $target_url;
        $this->curl_fields = $curl_fields;
        $this->helper = new InstawpHelper();
    }

    /**
     * Process inventory
     */
    public function process($transfer_progress) {
        try {
            $inventory_items = json_decode(file_get_contents($this->temp_destination), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                WP_CLI::warning("Error decoding inventory JSON: " . json_last_error_msg());
                return;
            }

            $inventory_items['staging'] = empty($inventory_items['staging']) ? 0 : 1;
            $inventory_data = $this->api_call($inventory_items['token'], 'checksum', $inventory_items['staging'], array(
                'items' => $inventory_items['items'],
                'with_download_url' => true,
                'max_download_size' => 100
            ));

            if (empty($inventory_data) || !is_array($inventory_data) || empty($inventory_data['data']) || empty($inventory_data['success'])) {
                WP_CLI::warning("Inventory API error found. Fall back to files migration.");
                $this->helper->iwp_inventory_sent_files($this->target_url, 'failed', 'plugin_theme', $this->curl_fields, $inventory_items['with_checksum']);
                return;
            }

            $inventory_data = $inventory_data['data'];
            $total_files_sent = 0;
            $installed_items_list = array();

            foreach ($inventory_items['with_checksum'] as $wp_item) {
                $item_type = 'plugin' === $wp_item['type'] ? 'plugin' : 'theme';

                if (empty($inventory_data[$item_type]) || empty($inventory_data[$item_type][$wp_item['slug']]) || empty($inventory_data[$item_type][$wp_item['slug']][$wp_item['version']]) || empty($inventory_data[$item_type][$wp_item['slug']][$wp_item['version']]['download_url']) || empty($inventory_data[$item_type][$wp_item['slug']][$wp_item['version']]['checksum']) || $wp_item['checksum'] !== $inventory_data[$item_type][$wp_item['slug']][$wp_item['version']]['checksum']) {
                    WP_CLI::warning("Failed to install $item_type: {$wp_item['slug']} (version {$wp_item['version']}) is not available or valid to install. Fall back to files migration.");
                    $this->add_failed_item($wp_item);
                    continue;
                }

                $download_url = $inventory_data[$item_type][$wp_item['slug']][$wp_item['version']]['download_url'];

                if (filter_var($download_url, FILTER_VALIDATE_URL) === false) {
                    WP_CLI::warning("Invalid download url $download_url for Item {$wp_item['slug']} with version {$wp_item['version']}");
                    $this->add_failed_item($wp_item);
                    continue;
                }

                $retries = 0;

                while ($retries < 5) {
                    exec('wp --path="' . $this->save_to_directory . '" ' . $item_type . ' install "' . $download_url . '" --force', $output, $return_var);

                    if ($return_var !== 0) {
                        $retries++;
                        if ($retries >= 5) {
                            WP_CLI::warning("Failed to install $item_type: {$wp_item['slug']} (version {$wp_item['version']}). Our migration script retried maximum times of installation. Fall back to files migration.");
                            $this->add_failed_item($wp_item);
                            break;
                        }
                        sleep(iwp_backoff_timer($retries));
                    } else {
                        $retries = 5;
                        WP_CLI::log("Successfully installed $item_type: {$wp_item['slug']} (version {$wp_item['version']})");

                        if (!empty($wp_item['path'])) {
                            $wp_item_path = $this->save_to_directory . $wp_item['path'];
                            if (!is_dir($wp_item_path)) {
                                WP_CLI::warning("Directory not found $wp_item_path. Fall back to files migration.");
                                $this->add_failed_item($wp_item);
                                break;
                            } elseif ($wp_item['checksum'] !== $this->calculate_checksum($wp_item_path)) {
                                WP_CLI::warning("Checksum mismatch $item_type: {$wp_item['slug']} (version {$wp_item['version']}). Fall back to files migration.");
                                $this->add_failed_item($wp_item);
                                break;
                            } else {
                                WP_CLI::log("Checksum matched $item_type: {$wp_item['slug']} (version {$wp_item['version']})");
                                iwp_inventory_sent_files($this->target_url, $wp_item['slug'], $item_type, $this->curl_fields);
                                if (!empty($wp_item['file_count']) && !empty($inventory_items['total_files_count'])) {
                                    $wp_item['file_count'] = intval($wp_item['file_count']);
                                    $inventory_items['total_files_count'] = intval($inventory_items['total_files_count']);

                                    if (0 < $wp_item['file_count'] && 0 < $inventory_items['total_files_count']) {
                                        $total_files_sent += $wp_item['file_count'];
                                        $transfer_progress = round(($total_files_sent / $inventory_items['total_files_count']) * 100, 2);
                                        WP_CLI::log("Inventory transfer progress $transfer_progress%");
                                        $this->helper->iwp_send_progress($transfer_progress);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (0 < count($this->failed_items)) {
                iwp_inventory_sent_files($this->target_url, 'failed', 'plugin_theme', $this->curl_fields, $this->failed_items);
            }

            WP_CLI::log("Inventory processing completed: $transfer_progress%");
        } catch (Exception $e) {
            WP_CLI::warning($e->getMessage());
        }

        return $transfer_progress;
    }

    /**
     * Add failed item
     */
    private function add_failed_item($item) {
        if (!empty($item['slug']) && !empty($item['type'])) {
            $key = $item['type'] . '_' . $item['slug'];
            if (empty($this->failed_items[$key])) {
                $this->failed_items[$key] = $item;
            }
        }
    }

    /**
     * API Call
     */
    public function api_call($api_key, $end_point, $is_staging, $body = array()) {
        $ch = curl_init("https://inventory.instawp.io/wp-json/instawp-checksum/v1/" . $end_point);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $api_key,
            'staging: ' . $is_staging
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $response = json_decode($response, true);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (200 !== $http_code) {
            WP_CLI::warning("Error: {$response['message']} HTTP Code: $http_code");
            return array();
        }

        curl_close($ch);

        return $response;
    }

    /**
     * Calculate checksum
     */
    private function calculate_checksum($folder) {
        if (!is_dir($folder)) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $totalHash = 0;
        $fileCount = 0;
        $totalSize = 0;

        foreach ($files as $file) {
            if ($file->isFile()) {
                $fileCount++;
                $filePath = $file->getPathname();
                $fileName = $file->getFilename();
                $fileSize = $file->getSize();
                $totalSize += $fileSize;
                $metadataHash = crc32($fileName . $fileSize);

                $handle = fopen($filePath, 'rb');
                if ($handle) {
                    $firstChunk = fread($handle, 4096);
                    $firstHash = crc32($firstChunk);

                    fseek($handle, -4096, SEEK_END);
                    $lastChunk = fread($handle, 4096);
                    $lastHash = crc32($lastChunk);

                    fclose($handle);
                }

                $fileHash = $metadataHash ^ $firstHash ^ $lastHash;
                $totalHash ^= $fileHash;
            }
        }

        $finalHash = $totalHash ^ crc32($fileCount . $totalSize);

        return sprintf('%u', $finalHash);
    }
}