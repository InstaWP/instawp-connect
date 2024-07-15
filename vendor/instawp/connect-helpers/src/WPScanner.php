<?php

namespace InstaWP\Connect\Helpers;

use Exception;

class WPScanner {

	public function scan_slow_items() {

		if ( ! in_array( 'code-profiler/index.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			return new \WP_Error( 'code_profiler_not_found', 'Code profiler plugin not found.' );
		}

		if ( ! $post_data_setup = $this->set_post_data() ) {
			return $post_data_setup;
		}

		$cp_file = $this->get_cp_file_path();

		if ( is_wp_error( $cp_file ) ) {
			return $cp_file;
		}

		$profile     = str_replace( '.slugs.profile', '', $cp_file );
		$profile_res = code_profiler_get_profile_data( $profile );

		usort( $profile_res, function ( $a, $b ) {
			if ( $a[1] == $b[1] ) {
				return 0;
			}

			return ( $a[1] < $b[1] ) ? 1 : - 1;
		} );

		return $profile_res;
	}

	public function scan_summary() {

		if ( ! in_array( 'code-profiler/index.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			return new \WP_Error( 'code_profiler_not_found', 'Code profiler plugin not found.' );
		}

		if ( ! $post_data_setup = $this->set_post_data() ) {
			return $post_data_setup;
		}

		$cp_file = $this->get_cp_file_path();

		if ( is_wp_error( $cp_file ) ) {
			return $cp_file;
		}

		$summary_file = str_replace( '.slugs.profile', '', $cp_file ) . '.summary.profile';

		if ( ! is_readable( $summary_file ) ) {
			return new \WP_Error( 'cp_summary_file_not_found', 'Could not find code profiler summary file.' );
		}

		return json_decode( file_get_contents( $summary_file ), true );
	}

	protected function get_cp_file_path() {

		try {
			$prepare_report_res = codeprofiler_prepare_report();
		} catch ( Exception $e ) {
			return new \WP_Error( 'cp_prepare_report_failed', 'Could not prepare report.' );
		}

		$prepare_report = json_decode( $prepare_report_res, true );
		$profile        = isset( $prepare_report['cp_profile'] ) ? $prepare_report['cp_profile'] : '';
		$cp_file_path   = code_profiler_get_profile_path( $profile );

		if ( ! $cp_file_path ) {
			return new \WP_Error( 'cp_file_path_failed', 'Could not find code profiler file path.' );
		}

		return $cp_file_path . '.slugs.profile';
	}

	protected function set_post_data() {

		$_POST['cp_nonce'] = wp_create_nonce( 'start_profiler_nonce' );
		$_POST['profile']  = 'WP-CLI_' . time();
		$_POST['ua']       = 'Firefox';
		$_POST['where']    = 'frontend';
		$_POST['post']     = home_url( '/' );
		$_POST['user']     = 'unauthenticated';
		$start_profiler    = [];

		try {
			$start_profiler_response = codeprofiler_start_profiler();
		} catch ( Exception $e ) {
			return new \WP_Error( 'cp_start_failed', 'Could not start code profiler' );
		}

		$response           = json_decode( $start_profiler_response, true );
		$_POST['microtime'] = $response['microtime'];

		return true;
	}
}