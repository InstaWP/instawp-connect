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
				// Key by asset for consistency with Updater response format
				$results[ isset($item['asset']) ? $item['asset'] : 'unknown' ] = [
					'success' => false,
					'message' => esc_html( 'Required parameters are missing!' ),
				];
				continue;
			}

			$results[ $item['asset'] ] = $this->activator( $item['type'], $item['asset'] );
		}

		return $results;
	}

	private function activator( $type, $item ) {
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Handle unknown type instead of returning blind success
		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			return array(
				'success' => false,
				'message' => esc_html( 'Unknown type: ' . $type ),
			);
		}

		if ( 'plugin' === $type ) {
			if ( ! function_exists( 'activate_plugin' ) || ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$activate = activate_plugin( $item );

			if ( is_wp_error( $activate ) ) {
				return array(
					'success' => false,
					'message' => $activate->get_error_message(),
				);
			}

			// Verify the plugin is actually active after activation
			if ( ! is_plugin_active( $item ) ) {
				return array(
					'success' => false,
					'message' => esc_html( 'Activation completed but plugin is not active.' ),
				);
			}

			return array(
				'success' => true,
				'message' => esc_html( 'Success!' ),
			);

		} elseif ( 'theme' === $type ) {

			if ( ! function_exists( 'switch_theme' ) || ! function_exists( 'get_stylesheet' ) ) {
				require_once ABSPATH . 'wp-includes/theme.php';
			}

			switch_theme( $item );

			// Verify the active theme actually changed
			if ( get_stylesheet() !== $item ) {
				return array(
					'success' => false,
					'message' => esc_html( 'Theme activation failed — active theme did not change.' ),
				);
			}

			return array(
				'success' => true,
				'message' => esc_html( 'Success!' ),
			);
		}
	}
}