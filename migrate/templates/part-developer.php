<?php
/**
 * Migrate template - Management
 */

defined( 'ABSPATH' ) || exit;
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