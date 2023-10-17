<?php
/**
 * Connect to InstaWP Screen
 */

?>

<div class="bg-white text-center rounded-md py-20 flex items-center justify-center">
    <div>
        <div class="mb-4">
            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/createsite.svg' ) ); ?>" class="mx-auto" alt="">
        </div>
        <div class="text-sm font-medium text-grayCust-200 mb-1"><?php esc_html_e( 'We have removed support for local sites for now.', 'instawp-connect' ); ?></div>
        <div class="text-sm font-normal text-grayCust-50 mb-4"><?php esc_html_e( 'You may use a third party backup and restore plugin such as WP Vivid or Everest Backup', 'instawp-connect' ); ?></div>
    </div>
</div>

