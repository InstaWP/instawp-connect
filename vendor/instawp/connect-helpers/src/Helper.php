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

	public static function get_option( $option_name, $default = [] ) {
		$option = get_option( $option_name, $default );

		return $option;
	}

	public static function get_args_option( $key = '', $args = [], $default = '' ) {
		$default = is_array( $default ) && empty( $default ) ? [] : $default;
		$value   = ! is_array( $default ) && ! is_bool( $default ) && empty( $default ) ? '' : $default;
		$key     = empty( $key ) ? '' : $key;

		if ( isset( $args[ $key ] ) && ! empty( $args[ $key ] ) ) {
			$value = $args[ $key ];
		}

		if ( isset( $args[ $key ] ) && is_bool( $default ) ) {
			$value = ! ( 0 == $args[ $key ] || '' == $args[ $key ] );
		}

		return $value;
	}

	public static function get_directory_info( $path ) {
		$bytes_total = 0;
		$files_total = 0;
		$path        = realpath( $path );
		try {
			if ( $path !== false && $path != '' && file_exists( $path ) ) {
				foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) ) as $object ) {
					$bytes_total += $object->getSize();
					$files_total ++;
				}
			}
		} catch ( Exception $e ) {
		}

		return [
			'size'  => $bytes_total,
			'count' => $files_total
		];
	}
}