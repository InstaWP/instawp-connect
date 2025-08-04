<?php
/**
 * Migrate template - Settings
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="nav-item-content settings bg-white rounded-md p-6">
    <form class="instawp-form w-full">
        <div class="instawp-form-fields">
			<?php foreach ( array_values( InstaWP_Setting::get_plugin_settings() ) as $index => $section ) : ?>
				<?php InstaWP_Setting::generate_section( $section, $index ); ?>
			<?php endforeach; ?>
        </div>
        <div class="instawp-form-footer rounded-md bg-grayCust-400 p-3 mt-6 flex justify-between items-center">
            <div class="instawp-form-buttons flex gap-4">
				<?php if ( ! empty( $connect_api_key ) ) { ?>
                    <button type="button" class="text-grayCust-500 py-3 px-5 border border-grayCust-350 text-sm font-medium rounded-md instawp-disconnect-plugin"><?php esc_html_e( 'Disconnect', 'instawp-connect' ); ?></button>
				<?php } ?>
                <p class="instawp-form-response loading flex items-center text-sm font-medium"></p>
            </div>
            <div class="instawp-form-buttons flex gap-4 items-center">
				<?php wp_nonce_field( 'instawp_settings_nonce_action', 'instawp_settings_nonce' ) ?>
                <span aria-label="<?= esc_html__( 'Plugin Version', 'instawp-connect' ); ?>" class="hint--top cursor-pointer text-sm text-primary-900 font-medium"><?= esc_html( INSTAWP_PLUGIN_VERSION ); ?></span>
                <button type="button" class="text-grayCust-500 py-3 px-5 border border-grayCust-350 text-sm font-medium rounded-md instawp-reset-plugin"><?php esc_html_e( 'Reset Plugin', 'instawp-connect' ); ?></button>
                <button type="submit" class="bg-secondary text-white py-3 px-5 text-sm font-medium rounded-md"><?php esc_html_e( 'Save Changes', 'instawp-connect' ); ?></button>
            </div>
        </div>
    </form>
    
    <!-- Settings Confirmation Modal -->
    <div id="settings-confirmation-modal" class="deactivate-modal">
        <div class="deactivate-modal-content">
            <h3 id="settings-modal-title"><?php esc_html_e( 'Confirm Changes', 'instawp-connect' ); ?></h3>
            <p id="settings-modal-message"><?php esc_html_e( 'Are you sure you want to make this change?', 'instawp-connect' ); ?></p>
            <div class="deactivate-modal-actions">
                <button id="confirm-settings-change" class="deactivate-modal-cancel"><?php esc_html_e( 'Yes, Continue', 'instawp-connect' ); ?></button>
                <button id="cancel-settings-change" class="deactivate-modal-confirm"><?php esc_html_e( 'Cancel', 'instawp-connect' ); ?></button>
            </div>
        </div>
    </div>
</div>