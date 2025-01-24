<?php 
/**
 * InstaWP Pull database
 * 
 */
if ( ! function_exists( 'instawp_fetch_db' ) ) {
    function instawp_fetch_db( $settings ) {
        global $tracking_db, $table_prefix, $migrate_key, $migrate_id, $migrate_mode, $migrate_curl_title, $bearer_token, $search_replace, $target_url;

        $start = time();
		$migrate_curl_title = $migrate_curl_title . ' Database';

        $save_to_directory = $settings['save_to_directory'];
        $serve_type        = 'db';
        $api_signature     = $settings['api_signature'];
        $output_file       = $save_to_directory . 'db.sql';
        $file_pointer      = fopen( $output_file, 'w' );
        $errors_counter    = 0;
        $migrate_mode      = empty( $migrate_mode ) ? 'pull': $migrate_mode;
        $is_iterative_pull = 'iterative_pull' === $migrate_mode;

        if ( ! $file_pointer ) {
            echo "Unable to open output file for writing \n";
            return false;
        }

        $is_completed = false;
        $curl_session = curl_init();
        $curl_fields  = array(
            'serve_type'    => $serve_type,
            'api_signature' => $api_signature,
            'migrate_key'   => $migrate_key,
            'migrate_mode'  => $migrate_mode,
        );
        $headers      = [];

        curl_setopt( $curl_session, CURLOPT_URL, $target_url );
        curl_setopt( $curl_session, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $curl_session, CURLOPT_TIMEOUT, 50 );
        curl_setopt( $curl_session, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $curl_session, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $curl_session, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $curl_session, CURLOPT_CUSTOMREQUEST, 'POST' );
        curl_setopt( $curl_session, CURLOPT_POSTFIELDS, $curl_fields );
        curl_setopt( $curl_session, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36 InstaWP-Migration-Pull-DB/1.0' );
        curl_setopt( $curl_session, CURLOPT_COOKIE, "instawp_skip_splash=true" );

        // Database pulling is running
        iwp_send_progress( 0, 0, [ 'pull-db-in-progress' => true ] );
        iwp_progress_bar( 0, 100, 50, $migrate_mode );

        $slow_sleep        = 0;
        $previous_progress = 0;

        while ( ! $is_completed ) {

            if ( $errors_counter >= 20 ) {
                iwp_send_migration_log( 'fetch-db: Tried maximum times', 'Our migration script retried maximum times of fetching db. Now exiting the migration' );
                exit( 1 );
            }

            curl_setopt( $curl_session, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
                return iwp_process_curl_headers( $curl, $header, $headers );
            } );

            $response                   = curl_exec( $curl_session );
            $processed_response         = iwp_process_curl_response( $response, $curl_session, $headers, $errors_counter, $slow_sleep, 'fetch-db' );
            $processed_response_success = isset( $processed_response['success'] ) ? (bool) $processed_response['success'] : false;
            $processed_response_message = isset( $processed_response['message'] ) ? $processed_response['message'] : '';

            if ( ! $processed_response_success ) {
                echo $processed_response_message;

                continue;
            }

            // Reset errors counter on successful response
            if ( $errors_counter > 0 ) {
                $errors_counter = 0;
                echo "Errors counter reset to 0. Previous counter value: $errors_counter\n";
            }

            $header_status     = $headers['x-iwp-status'] ?? false;
            $header_message    = $headers['x-iwp-message'] ?? '';
            $transfer_complete = $headers['x-iwp-transfer-complete'] ?? false;
            $transfer_progress = $headers['x-iwp-progress'] ?? 0;

            if ( $header_status == 'false' || $transfer_complete == 'true' ) {
                $is_completed = true;
                if ( ! $is_iterative_pull || $transfer_complete != 'true' ) {
                    echo "Response: $header_message\n";
                }
                break;
            }

            if ( is_numeric( $transfer_progress ) && strpos( $transfer_progress, '.' ) === false ) {
                if ( $transfer_progress - $previous_progress >= 0.1 ) {

                    iwp_send_progress( 0, $transfer_progress );
                    iwp_progress_bar( $transfer_progress, 100, 50, $migrate_mode );
                    echo date( "H:i:s" ) . ": Progress: $transfer_progress%.\n";

                    $previous_progress = $transfer_progress;
                }
            }

            //remove this code after 0.0.9.47 release. 
            //this is for backward compatibility with old versions of instawp
            $response = preg_replace( "/.*Cannot modify header information.*/", "", $response );
            $response = preg_replace( "/^\s*<br \/>\s*$/m", "", $response );

            fwrite( $file_pointer, $response );
        }

        curl_close( $curl_session );
        fclose( $file_pointer );

        // Database pulling is completed
        iwp_send_progress( 0, 100, [ 'pull-db-finished' => true ] );
        iwp_progress_bar( 100, 100, 50, $migrate_mode );

        if ( $is_iterative_pull ) {
            echo "Database pull completed in " . ( time() - $start ) . " seconds. \n";
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                $result = WP_CLI::runcommand( 'db import ' . $output_file . ' --skip-plugins --skip-themes', [
                    'return'     => true,   // Return output instead of printing
                    'parse'      => 'json', // Parse output as JSON
                    'launch'     => false,  // Run in same process
                    'exit_error' => true    // Throw exception on error
                ]);

            }
        } else {
            echo "The whole database is transferred successfully from the source website to the staging website. \n";
        }
        return true;

    }
}