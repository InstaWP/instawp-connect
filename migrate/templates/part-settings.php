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
</div>

<!-- Settings Confirmation Modal -->
<div id="instawp-settings-modal" class="instawp-modal hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:w-full sm:max-w-lg sm:p-6 sm:align-middle">
            <div class="sm:flex sm:items-start">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-yellow-100 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">
                        <?php esc_html_e( 'Confirm Settings Change', 'instawp-connect' ); ?>
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500" id="modal-message">
                            <?php esc_html_e( 'Are you sure you want to change this setting?', 'instawp-connect' ); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                <button type="button" class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm" id="modal-confirm">
                    <?php esc_html_e( 'Confirm', 'instawp-connect' ); ?>
                </button>
                <button type="button" class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm" id="modal-cancel">
                    <?php esc_html_e( 'Cancel', 'instawp-connect' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>