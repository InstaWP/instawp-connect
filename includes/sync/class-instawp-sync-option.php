<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Option {

    public function __construct() {
	    // Update option
	    add_action( 'added_option', [ $this,'added_option' ], 10, 2 );
	    add_action( 'updated_option', [ $this,'updated_option' ], 10, 3 );
	    add_action( 'deleted_option', [ $this,'deleted_option' ] );
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

	private function is_protected_option( $option ): bool {
		$excluded_options = [ 'cron', 'instawp_api_options' ];

		if ( in_array( $option, $excluded_options ) || strpos( $option, '_transient' ) !== false || strpos( $option, 'instawp' ) !== false ) {
			return true;
		}

		return false;
	}
}

new InstaWP_Sync_Option();