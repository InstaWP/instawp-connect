<?php
/**
 * InstaWP Database Fetching Class
 *
 * This class handles the fetching of database from source to staging site
 * using WP-CLI commands and proper error handling.
 *
 * @package InstaWP
 * @since   1.0.0
 */

/**
 * Class InstaWP_Fetch_DB
 */
class InstaWP_Fetch_DB {

    /**
     * Target URL to fetch database from
     *
     * @var string
     */
    private $target_url;

    /**
     * Directory to save the database file
     *
     * @var string
     */
    private $save_to_directory;

    /**
     * Type of service being used
     *
     * @var string
     */
    private $serve_type;

    /**
     * API signature for authentication
     *
     * @var string
     */
    private $api_signature;

    /**
     * Migration key for tracking
     *
     * @var string
     */
    private $migrate_key;

    /**
     * Migration ID for tracking
     *
     * @var string
     */
    private $migrate_id;

    /**
     * Bearer token for authentication
     *
     * @var string
     */
    private $bearer_token;

    /**
     * Output file path for the database
     *
     * @var string
     */
    private $output_file;

    /**
     * File pointer for writing database
     *
     * @var resource|false
     */
    private $file_pointer;

    /**
     * Counter for tracking errors
     *
     * @var int
     */
    private $errors_counter = 0;

    /**
     * Maximum number of retry attempts
     *
     * @var int
     */
    private const MAX_RETRY_ATTEMPTS = 20;

    /**
     * Constructor.
     *
     * @param array $args {
     *     Required. Array of arguments for database fetching.
     *
     *     @type string $target_url        The URL to fetch database from.
     *     @type string $save_to_directory Directory to save the database file.
     *     @type string $serve_type        Type of service being used.
     *     @type string $api_signature     API signature for authentication.
     *     @type string $migrate_key       Migration key for tracking.
     *     @type string $migrate_id        Migration ID for tracking.
     *     @type string $bearer_token      Bearer token for authentication.
     * }
     */
    public function __construct( array $args ) {
        if ( ! isset( $args['target_url'], $args['save_to_directory'], $args['serve_type'],
                     $args['api_signature'], $args['migrate_key'], $args['migrate_id'],
                     $args['bearer_token'] ) ) {
            WP_CLI::error( 'Missing required parameters for database fetching.' );
        }

        $this->target_url        = $args['target_url'];
        $this->save_to_directory = $args['save_to_directory'];
        $this->serve_type        = $args['serve_type'];
        $this->api_signature     = $args['api_signature'];
        $this->migrate_key       = $args['migrate_key'];
        $this->migrate_id        = $args['migrate_id'];
        $this->bearer_token      = $args['bearer_token'];
        $this->output_file       = $this->save_to_directory . 'db.sql';

        $this->initialize_file_pointer();
    }

    /**
     * Initialize file pointer for writing database
     *
     * @return void
     */
    private function initialize_file_pointer() {
        $this->file_pointer = fopen( $this->output_file, 'w' );

        if ( ! $this->file_pointer ) {
            WP_CLI::error( 'Unable to open output file for writing' );
        }
    }

    /**
     * Initialize cURL session with required options
     *
     * @return resource cURL session handle
     */
    private function initialize_curl_session() {
        $curl_session = curl_init();
        $curl_fields  = array(
            'serve_type'    => $this->serve_type,
            'api_signature' => $this->api_signature,
            'migrate_key'   => $this->migrate_key,
        );

        curl_setopt( $curl_session, CURLOPT_URL, $this->target_url );
        curl_setopt( $curl_session, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $curl_session, CURLOPT_TIMEOUT, 50 );
        curl_setopt( $curl_session, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $curl_session, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $curl_session, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $curl_session, CURLOPT_CUSTOMREQUEST, 'POST' );
        curl_setopt( $curl_session, CURLOPT_POSTFIELDS, $curl_fields );
        curl_setopt( $curl_session, CURLOPT_USERAGENT, 'InstaWP Migration Service - Pull DB' );
        curl_setopt( $curl_session, CURLOPT_COOKIE, 'instawp_skip_splash=true' );

        return $curl_session;
    }

    /**
     * Process response and handle errors
     *
     * @param string     $response    cURL response.
     * @param resource   $curl_session cURL session handle.
     * @param array      $headers     Response headers.
     * @param string|int $slow_sleep  Sleep duration for rate limiting.
     *
     * @return array Processed response data
     */
    private function process_response( $response, $curl_session, &$headers, &$slow_sleep ) {
        $processed_response = iwp_process_curl_response(
            $response,
            $curl_session,
            $headers,
            $this->errors_counter,
            $slow_sleep,
            'fetch-db'
        );

        if ( ! isset( $processed_response['success'] ) || ! $processed_response['success'] ) {
            WP_CLI::warning( $processed_response['message'] ?? 'Unknown error occurred' );
            $this->errors_counter++;
            return $processed_response;
        }

        return $processed_response;
    }

    /**
     * Update progress and send notifications
     *
     * @param float $progress Progress percentage.
     * @param float $previous_progress Previous progress percentage.
     *
     * @return void
     */
    private function update_progress( $progress, &$previous_progress ) {
        if ( is_numeric( $progress ) && false === strpos( $progress, '.' ) ) {
            if ( $progress - $previous_progress >= 0.1 ) {
                iwp_send_progress( 0, $progress );
                WP_CLI::log( sprintf( '%s: Progress: %s%%.', date( 'H:i:s' ), $progress ) );
                $previous_progress = $progress;
            }
        }
    }

    /**
     * Write response to file after cleaning
     *
     * @param string $response Response data to write.
     *
     * @return void
     */
    private function write_response( $response ) {
        // Clean response for backward compatibility
        $response = preg_replace( '/.*Cannot modify header information.*/', '', $response );
        $response = preg_replace( '/^\s*<br \/>\s*$/m', '', $response );

        fwrite( $this->file_pointer, $response );
    }

    /**
     * Main method to fetch database
     *
     * @return void
     */
    public function fetch() {
        $is_completed      = false;
        $previous_progress = 0;
        $slow_sleep       = 0;
        $headers          = array();

        // Notify that database pulling is starting
        iwp_send_progress( 0, 0, array( 'pull-db-in-progress' => true ) );

        $curl_session = $this->initialize_curl_session();

        while ( ! $is_completed ) {
            if ( $this->errors_counter >= self::MAX_RETRY_ATTEMPTS ) {
                iwp_send_migration_log(
                    'fetch-db: Tried maximum times',
                    'Our migration script retried maximum times of fetching db. Now exiting the migration'
                );
                WP_CLI::error( 'Maximum retry attempts reached' );
            }

            curl_setopt(
                $curl_session,
                CURLOPT_HEADERFUNCTION,
                function ( $curl, $header ) use ( &$headers ) {
                    return iwp_process_curl_headers( $curl, $header, $headers );
                }
            );

            $response = curl_exec( $curl_session );
            $this->process_response( $response, $curl_session, $headers, $slow_sleep );

            $header_status     = $headers['x-iwp-status'] ?? false;
            $header_message    = $headers['x-iwp-message'] ?? '';
            $transfer_complete = $headers['x-iwp-transfer-complete'] ?? false;
            $transfer_progress = $headers['x-iwp-progress'] ?? 0;

            if ( 'false' === $header_status || 'true' === $transfer_complete ) {
                $is_completed = true;
                WP_CLI::log( "Response: $header_message" );
                break;
            }

            $this->update_progress( $transfer_progress, $previous_progress );
            $this->write_response( $response );
        }

        curl_close( $curl_session );
        fclose( $this->file_pointer );

        // Notify that database pulling is complete
        iwp_send_progress( 0, 100, array( 'pull-db-finished' => true ) );
        WP_CLI::success( 'The whole database is transferred successfully from the source website to the staging website.' );
    }
}

// Execute if running from command line
if ( defined( 'WP_CLI' ) && WP_CLI && isset( $argv ) ) {
    if ( count( $argv ) < 8 ) {
        WP_CLI::error( 'Parameters not enough: USAGE: wp instawp fetch-db <target_url> <save_dir> <serve_type> <api_sig> <migrate_key> <migrate_id> <bearer_token>' );
    }

    include_once 'connect-inc/v-instawp-connect-functions';

    $db_fetcher = new InstaWP_Fetch_DB(
        array(
            'target_url'        => $argv[1],
            'save_to_directory' => $argv[2],
            'serve_type'        => $argv[3],
            'api_signature'     => $argv[4],
            'migrate_key'       => $argv[5],
            'migrate_id'        => $argv[6],
            'bearer_token'      => $argv[7],
        )
    );

    $db_fetcher->fetch();
}
