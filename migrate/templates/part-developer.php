<?php
/**
 * Migrate template - Management
 */

defined( 'ABSPATH' ) || exit;

// Settings/Developer tabs (and the API key the Settings tab renders) are for administrators
// only. Defence-in-depth: even if this template were ever included for a lower-privileged
// user, never emit its contents without the manage_options capability.
if ( ! InstaWP_Setting::current_user_can_manage() ) {
	return;
}
?>

<div class="nav-item-content developer bg-white rounded-md p-6">
    <form class="instawp-form w-full">
        <div class="instawp-form-fields">
			<?php foreach ( array_values( InstaWP_Setting::get_developer_settings() ) as $index => $section ) : ?>
				<?php InstaWP_Setting::generate_section( $section, $index ); ?>
			<?php endforeach; ?>
        </div>
        <?php wp_nonce_field( 'instawp_settings_nonce_action', 'instawp_settings_nonce' ) ?>
    </form>
</div>