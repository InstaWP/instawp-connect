<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class InstaWP_Activity_Log_Core {
	
	public function __construct() {
		add_action( '_core_updated_successfully', array( $this, 'hook_update' ) );
	}

	public function hook_update( $version ) {
		global $pagenow, $wp_version;

		// Auto updated
		if ( 'update-core.php' !== $pagenow ) {
			$object_name = 'WordPress Auto Updated';
		} else {
			$object_name = 'WordPress Updated';
		}

		$update_type = $this->detect_wp_update_type($version, $wp_version);
		InstaWP_Activity_Log::insert_log(
			array(
				'action'         => 'core_updated_' . $update_type,
				'object_type'    => 'Core',
				'object_id'      => 0,
				'object_name'    => $object_name,
				'object_subtype' => $version,
			)
		);
	}

	private function detect_wp_update_type($wp_version, $old_version) {
		// Remove any potential 'alpha', 'beta', 'RC' suffixes
		$wp_version = preg_replace('/[-+].*$/', '', $wp_version);
		$old_version = preg_replace('/[-+].*$/', '', $old_version);

		// Split version strings into components and pad with zeros if needed
		$new_parts = array_pad(array_map('intval', explode('.', $wp_version)), 3, 0);
		$old_parts = array_pad(array_map('intval', explode('.', $old_version)), 3, 0);

		// Compare major version (first number)
		if ( $new_parts[0] !== $old_parts[0] ) {
			return 'major'; // e.g., 5.9 to 6.0
		}

		// Compare minor version (second number)
		if ( $new_parts[1] !== $old_parts[1] ) {
			return 'major'; // e.g., 6.0 to 6.1
		}

		// If only the patch version (third number) changed, it's a minor update
		return 'minor'; // e.g., 6.0.0 to 6.0.3
	}
}

new InstaWP_Activity_Log_Core();