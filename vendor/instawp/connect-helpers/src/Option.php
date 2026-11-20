<?php
namespace InstaWP\Connect\Helpers;

class Option {

	public function update( $args = [] ) {
		$results = [];

		try {
			foreach( $args as $name => $value ) {
				$results[] = [
					'name'    => $name,
					'success' => self::update_option( $name, $value ),
				];
			}
		} catch ( \Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

        return $results;
    }

	public function delete( $args = [] ) {
        $results = [ 'success' => true ];
        
		try {
			foreach( $args as $name ) {
				$results[] = [
					'name'    => $name,
					'success' => self::delete_option( $name ),
				];
			}
		} catch ( \Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

        return $results;
    }

	public static function get_option( $option_name, $default = [] ) {
		return get_option( $option_name, $default );
	}

	public static function update_option( $option_name, $option_value, $autoload = false ) {
		return update_option( $option_name, $option_value, $autoload );
	}

	public static function delete_option( $option_name ) {
		return delete_option( $option_name );
	}
}