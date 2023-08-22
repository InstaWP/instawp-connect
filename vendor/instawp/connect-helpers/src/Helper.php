<?php
declare( strict_types=1 );

namespace InstaWP\Connect\Helpers;

class Helper {

    public static function get_random_string( int $length ): string {
        try {
			$length        = ( int ) round( ceil( absint( $length ) / 2 ) );
			$bytes         = function_exists( 'random_bytes' ) ? random_bytes( $length ) : openssl_random_pseudo_bytes( $length );
			$random_string = bin2hex( $bytes );
		} catch ( Exception $e ) {
			$random_string = substr( hash( 'sha256', wp_generate_uuid4() ), 0, absint( $length ) );
		}

		return $random_string;
	}
}