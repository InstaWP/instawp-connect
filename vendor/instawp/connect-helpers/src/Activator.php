<?php

namespace InstaWP\Connect\Helpers;

class Activator {

	public $args;

	public function __construct( array $args = [] ) {
		$this->args = $args;
	}

	public function activate() {
		if ( count( $this->args ) < 1 ) {
			return [
				'success' => false,
				'message' => esc_html( 'Minimum 1 item is required!' ),
			];
		}

		$results = [];

		foreach ( $this->args as $item ) {
			if ( ! isset( $item['type'], $item['asset'] ) ) {
				$results[] = [
					'success' => false,
					'message' => esc_html( 'Required parameters are missing!' ),
				];
				continue;
			}

			$results[] = $this->activator( $item['type'], $item['asset'] );
		}

		return $results;
	}

	private function activator( $type, $item ) {
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$response = [
			'success' => true,
			'message' => esc_html( 'Success!' )
		];

		if ( 'plugin' === $type ) {
			if ( ! function_exists( 'activate_plugin' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$activate = activate_plugin( $item );
			$response = array(
				'success' => ! is_wp_error( $activate ),
				'message' => is_wp_error( $activate ) ? $activate->get_error_message() : '',
			);

		} elseif ( 'theme' === $type ) {

			if ( ! function_exists( 'switch_theme' ) ) {
				require_once ABSPATH . 'wp-includes/theme.php';
			}

			switch_theme( $item );
			if ( current_action() === 'wp_die_handler' ) {
				$response = [
					'success' => false,
					'message' => esc_html( 'Theme Activation Failed!' ),
				];
			}
		}

		return $response;
	}
}