<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Option {

    public function __construct() {
	    if ( ! InstaWP_Setting::get_option( 'instawp_is_event_syncing' ) ) {
		    // Update option
		    add_action( 'added_option', [ $this, 'added_option' ], 10, 2 );
		    add_action( 'updated_option', [ $this, 'updated_option' ], 10, 3 );
		    add_action( 'deleted_option', [ $this, 'deleted_option' ] );
	    }

	    // process event
	    add_filter( 'INSTAWP_CONNECT/Filters/process_two_way_sync', [ $this, 'parse_event' ], 10, 2 );
    }

	public function added_option( $option, $value ) {
		if ( ! $this->is_protected_option( $option ) ) {
			InstaWP_Sync_DB::insert_update_event( __( 'Option added', 'instawp-connect' ), 'add_option', 'option', '', ucfirst( str_replace( [ '-', '_' ], ' ', $option ) ), [ $option => $value ] );
		}
	}

	public function updated_option( $option, $old_value, $value ) {
		if ( ! $this->is_protected_option( $option ) ) {
			InstaWP_Sync_DB::insert_update_event( __( 'Option updated', 'instawp-connect' ), 'update_option', 'option', '', ucfirst( str_replace( [ '-', '_' ], ' ', $option ) ), [ $option => $value ] );
		}
	}

	public function deleted_option( $option ) {
		if ( ! $this->is_protected_option( $option ) ) {
			InstaWP_Sync_DB::insert_update_event( __( 'Option deleted', 'instawp-connect' ), 'delete_option', 'option', '', ucfirst( str_replace( [ '-', '_' ], ' ', $option ) ), $option );
		}
	}

	public function parse_event( $response, $v ) {
		if ( $v->event_type !== 'option' ) {
			return $response;
		}

		// add or update option
		if ( in_array( $v->event_slug, [ 'add_option', 'update_option' ], true ) ) {
			foreach ( ( array ) $v->details as $name => $value ) {
				update_option( $name, $value );
			}
		}

		// delete option
		if ( $v->event_slug === 'delete_option' ) {
			foreach ( ( array ) $v->details as $name ) {
				delete_option( $name );
			}
		}

		return InstaWP_Sync_Helpers::sync_response( $v );
	}

	private function is_protected_option( $option ): bool {
		$excluded_options = [ 'cron', 'instawp_api_options', 'siteurl', 'home', 'permalink_structure' ];

		if ( in_array( $option, $excluded_options ) || strpos( $option, '_transient' ) !== false || strpos( $option, 'instawp' ) !== false ) {
			return true;
		}

		return false;
	}
}

new InstaWP_Sync_Option();