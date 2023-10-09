<?php
declare( strict_types=1 );

namespace InstaWP\Connect\Helpers;

class Option {

	public function update( $args = [] ): array {
		$results = [];

		try {
			foreach( $args as $name => $value ) {
				$results[] = [
					'name'    => $name,
					'success' => update_option( $name, $value ),
				];
			}
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

        return $results;
    }

	public function delete(): array {
        $results = [ 'success' => true ];
        
		try {
			foreach( $args as $name ) {
				$results[] = [
					'name'    => $name,
					'success' => delete_option( $name ),
				];
			}
		} catch ( Exception $e ) {
			$results = [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

        return $results;
    }
}