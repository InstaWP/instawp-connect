<?php

namespace InstaWP\Connect\Helpers;

class Uninstaller {

	public $args;

	public function __construct( array $args = [] ) {
		$this->args = $args;
	}

	public function uninstall() {
		if ( count( $this->args ) < 1 || count( $this->args ) > 5 ) {
			return [
				'success' => false,
				'message' => esc_html( 'Minimum 1 and Maximum 5 updates are allowed!' ),
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

			$results[] = $this->uninstaller( $item['type'], $item['asset'] );
		}

		return $results;
	}

	private function uninstaller( $type, $item ) {
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$response = [
			'success' => true,
			'message' => esc_html( 'Success!' )
		];

		if ( 'plugin' === $type ) {
			if ( ! function_exists( 'delete_plugins' ) || ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( is_plugin_active( $item ) ) {
				deactivate_plugins( array( $item ) );
			}

			$deleted = delete_plugins( array( $item ) );

		} elseif ( 'theme' === $type ) {

			if ( ! function_exists( 'delete_theme' ) ) {
				require_once ABSPATH . 'wp-admin/includes/theme.php';
			}

			if ( ! function_exists( 'get_stylesheet' ) ) {
				require_once ABSPATH . 'wp-includes/theme.php';
			}

			$deleted = delete_theme( $item );
		}

		$response = array(
			'success' => ! is_wp_error( $deleted ),
			'message' => is_wp_error( $deleted ) ? $deleted->get_error_message() : '',
		);

		return $response;
	}
}