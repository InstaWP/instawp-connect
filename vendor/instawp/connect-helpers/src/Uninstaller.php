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
				'message' => esc_html( 'Minimum 1 and Maximum 5 items are allowed!' ),
			];
		}

		$results = [];
		foreach ( $this->args as $item ) {
			if ( ! isset( $item['type'], $item['asset'] ) ) {
				// Key by asset for consistency with Updater (keyed by slug)
				$results[ isset($item['asset']) ? $item['asset'] : 'unknown' ] = [
					'success' => false,
					'message' => esc_html( 'Required parameters are missing!' ),
				];
				continue;
			}

			// Key results by asset slug for consistency with Updater response format
			$results[ $item['asset'] ] = $this->uninstaller( $item['type'], $item['asset'] );
		}

		return $results;
	}

	private function uninstaller( $type, $item ) {
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Handle unknown type — avoid undefined $deleted variable
		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			return array(
				'success' => false,
				'message' => esc_html( 'Unknown type: ' . $type ),
			);
		}

		if ( 'plugin' === $type ) {
			if ( ! function_exists( 'delete_plugins' ) || ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( is_plugin_active( $item ) ) {
				deactivate_plugins( array( $item ) );
			}

			$deleted = delete_plugins( array( $item ) );

			// Verify plugin files are actually gone
			if ( ! is_wp_error( $deleted ) && file_exists( WP_PLUGIN_DIR . '/' . $item ) ) {
				return array(
					'success' => false,
					'message' => esc_html( 'Delete reported success but plugin files still exist.' ),
				);
			}
		} elseif ( 'theme' === $type ) {

			if ( ! function_exists( 'delete_theme' ) ) {
				require_once ABSPATH . 'wp-admin/includes/theme.php';
			}

			if ( ! function_exists( 'get_stylesheet' ) ) {
				require_once ABSPATH . 'wp-includes/theme.php';
			}

			$deleted = delete_theme( $item );

			// Verify theme is actually gone
			if ( ! is_wp_error( $deleted ) && wp_get_theme( $item )->exists() ) {
				return array(
					'success' => false,
					'message' => esc_html( 'Delete reported success but theme still exists.' ),
				);
			}
		}

		// Check for WP_Error or false return from delete functions
		if ( is_wp_error( $deleted ) ) {
			return array(
				'success' => false,
				'message' => $deleted->get_error_message(),
			);
		}

		if ( $deleted === false ) {
			return array(
				'success' => false,
				'message' => esc_html( 'Deletion failed.' ),
			);
		}

		return array(
			'success' => true,
			'message' => esc_html( 'Success!' ),
		);
	}
}