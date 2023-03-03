<?php
/**
 * Migrate template - Create Site
 */


?>

<div class="nav-item-content create bg-white text-center box-shadow rounded-md py-20 flex items-center justify-center active">
    <div>
        <div class="mb-10">
            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/createsite.png' ) ) ?>" class="mx-auto" alt="">
        </div>

        <div class="text-lg text-lg font-bold text-grayCust-150"><?php echo esc_html__( 'Create a Staging Site', 'instawp-connect' ); ?></div>

        <button class="btn-width py-3 rounded-md shadow-md bg-primary-900 text-white mt-8 font-semibold text-sm"><?php echo esc_html__( 'Create a Site', 'instawp-connect' ); ?></button>
    </div>
</div>