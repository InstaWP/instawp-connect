<?php
/**
 * Migrate template - Settings
 */

?>

<div class="nav-item-content settings bg-white box-shadow rounded-md p-6">

    <form class="instawp-form w-full">

        <div class="instawp-form-fields">
			<?php foreach ( InstaWP_Setting::get_migrate_settings() as $section ) : ?>

				<?php InstaWP_Setting::generate_section( $section ); ?>

			<?php endforeach; ?>

        </div>

        <div class="instawp-form-footer bg-grayCust-400 p-3 flex justify-between items-center">

            <p class="instawp-form-response loading flex items-center text-sm font-medium"></p>

            <div class="instawp-form-buttons">
				<?php wp_nonce_field( 'instawp_settings_nonce_action', 'instawp_settings_nonce' ) ?>
                <button type="button" class="text-grayCust-500 py-3 mr-4 px-5 border border-grayCust-350 text-sm font-medium rounded-md instawp-reset-plugin"><?php echo esc_html__( 'Reset Plugin', 'instawp-connect' ); ?></button>
                <button type="submit" class="bg-primary-900 text-white py-3 px-5  text-sm font-medium rounded-md"><?php echo esc_html__( 'Save Changes', 'instawp-connect' ); ?></button>
            </div>
        </div>

    </form>

</div>