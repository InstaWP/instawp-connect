<?php

namespace InstaWP\Connect\Helpers;

class Deactivator {

	public $args;

	public function __construct( array $args = [] ) {
		$this->args = $args;
	}

	public function deactivate() {
		if ( count( $this->args ) < 1 ) {
			return [
				'success' => false,
				'message' => esc_html__( 'Minimum 1 item is required!', 'connect-helpers' ),
			];
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$results = [];

		foreach ( $this->args as $item ) {
			if ( ! isset( $item['type'], $item['asset'] ) ) {
				$results[] = [
					'success' => false,
					'message' => esc_html__( 'Required parameters are missing!', 'connect-helpers' ),
					'item' 	  => $item,
				];
				continue;
			}

			if ( 'plugin' === $item['type'] ) {
				deactivate_plugins( $item['asset'] );
				$results[] = [
					'success' => true,
					'message' => esc_html__( 'Success!', 'connect-helpers' ),
					'item' 	  => $item,
				];
			} else {
				$results[] = [
					'success' => false,
					'message' => esc_html__( 'Only plugins can be deactivated!', 'connect-helpers' ),
					'item' 	  => $item,
				];
			}
		}

		return $results;
	}
}