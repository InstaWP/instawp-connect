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
				'message' => esc_html( 'Minimum 1 item is required!' ),
			];
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( $this->args );

		return [
			'success' => true,
			'message' => esc_html( 'Success!' )
		];
	}
}