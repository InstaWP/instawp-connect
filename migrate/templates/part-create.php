<?php
/**
 * Migrate template - Create Site
 */

if ( isset( $_GET['clear'] ) && $_GET['clear'] == 'all' ) {
	instawp_reset_running_migration();
}

$incomplete_task_ids = InstaWP_taskmanager::is_there_any_incomplete_task_ids();
$is_loading_class    = ! empty( $incomplete_task_ids ) ? 'loading' : '';

//echo "<pre>"; print_r( $incomplete_task_ids ); echo "</pre>";

?>

<div class="<?php echo esc_attr( $is_loading_class ); ?> nav-item-content create bg-white box-shadow rounded-md active">
    <div class="screen screen-0 flex items-center justify-center text-center py-20">

	    <?php if ( empty( InstaWP_Setting::get_api_key() ) ) : ?>

            <div class="mb-4">
                <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/createsite.svg' ) ); ?>" class="mx-auto" alt="">
            </div>
            <div class="text-sm font-medium text-grayCust-200 mb-1"><?php echo esc_html__( 'InstaWP account is not connected', 'instawp-connect' ); ?></div>
            <div class="text-sm font-normal text-grayCust-50 mb-4"><?php echo esc_html__( 'Please authorize your account in order to connect this site and enable staging site creation.', 'instawp-connect' ); ?></div>
            <a class="instawp-button-connect cursor-pointer	px-7 py-3 inline-flex items-center mx-auto rounded-md shadow-sm bg-primary-900 text-white hover:text-white active:text-white focus:text-white focus:shadow-none font-medium text-sm">
                <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-plus.svg' ) ); ?>" class="mr-2" alt="">
                <span><?php echo esc_html__( 'Connect', 'instawp-connect' ); ?></span>
            </a>

	    <?php else: ?>


	    <?php endif; ?>




        <div>

			<?php if ( empty( InstaWP_Setting::get_api_key() ) ) : ?>

                <div class="mb-4">
                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/createsite.svg' ) ); ?>" class="mx-auto" alt="">
                </div>
                <div class="text-sm font-medium text-grayCust-200 mb-1"><?php echo esc_html__( 'InstaWP account is not connected', 'instawp-connect' ); ?></div>
                <div class="text-sm font-normal text-grayCust-50 mb-4"><?php echo esc_html__( 'Please authorize your account in order to connect this site and enable staging site creation.', 'instawp-connect' ); ?></div>
                <a class="instawp-button-connect cursor-pointer	px-7 py-3 inline-flex items-center mx-auto rounded-md shadow-sm bg-primary-900 text-white hover:text-white active:text-white focus:text-white focus:shadow-none font-medium text-sm">
                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-plus.svg' ) ); ?>" class="mr-2" alt="">
                    <span><?php echo esc_html__( 'Connect', 'instawp-connect' ); ?></span>
                </a>

			<?php else: ?>

                <div class="mb-6">
                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/createsite.png' ) ) ?>" class="mx-auto" alt="">
                </div>

                <div class="text-lg text-lg font-bold text-grayCust-150"><?php echo esc_html__( 'Create a Staging Site', 'instawp-connect' ); ?></div>

                <button class="instawp-button-create btn-width py-3 rounded-md shadow-md bg-primary-900 text-white mt-8 font-semibold text-sm"><?php echo esc_html__( 'Create a Site', 'instawp-connect' ); ?></button>

			<?php endif; ?>

        </div>

    </div>

    <div class="screen screen-1 p-12">

        <div class="mb-6 flex items-center">
            <span class="loader mr-2"></span>
            <span class="loader-text">Migration is processing...</span>
        </div>

        <div class="mb-6 flex items-center w-full">
            <div class="w-1/2 mr-6 mb-6">
                <span class="block text-sm font-medium text-gray-700 mb-3 sm:mt-px sm:pt-2">Backup</span>
                <div class="instawp-bar instawp-bar-backup w-full" style="--progress: 0%;"></div>
            </div>
            <div class="w-1/2 mr-6 mb-6">
                <span class="block text-sm font-medium text-gray-700 mb-3 sm:mt-px sm:pt-2">Upload</span>
                <div class="instawp-bar instawp-bar-upload w-full" style="--progress: 0%;"></div>
            </div>
        </div>

        <div class="mb-6 flex items-center w-full">
            <div class="w-full mr-6 mb-6">
                <span class="block text-sm font-medium text-gray-700 mb-3 sm:mt-px sm:pt-2">Migration</span>
                <div class="instawp-bar instawp-bar-migrate w-full" style="--progress: 0%;"></div>
            </div>
        </div>

    </div>

    <div class="screen screen-2 p-12">

        <div class="migration-message">
            <p class="text-lg mb-8 text-primary-900"><?php echo esc_html__( 'Congratulations! Staging creation successful.', 'instawp-connect' ); ?></p>
        </div>

        <div class="site-detail-wrap">
            <div class="w-3/4">
                <p class="text-sm font-medium mb-4"><?php echo esc_html__( 'WP Login Credentials', 'instawp-connect' ); ?></p>
                <p class="mb-2 flex items-center justify-start">
                    <span class="text-sm w-36"><?php echo esc_html__( 'URL', 'instawp-connect' ); ?></span>
                    <span class="text-sm"><a id="instawp-site-url" target="_blank"></a></span>
                </p>
                <p class="mb-2 flex items-center justify-start">
                    <span class="text-sm w-36"><?php echo esc_html__( 'Admin Username', 'instawp-connect' ); ?></span>
                    <span class="text-sm" id="instawp-site-username"></span>
                </p>
                <p class="mb-2 flex items-center justify-start">
                    <span class="text-sm w-36"><?php echo esc_html__( 'Admin Password', 'instawp-connect' ); ?></span>
                    <span class="text-sm" id="instawp-site-password"></span>
                </p>
            </div>

            <div class="w-1/4 flex justify-end">
                <a id="instawp-site-magic-url" class="bg-primary-900 text-white hover:text-white focus:text-white focus:shadow-none focus:outline-none active:text-white active:shadow-none active:outline-none py-3 px-5 cursor-pointer text-sm font-medium rounded-md" target="_blank"><?php echo esc_html__( 'Magic login', 'instawp-connect' ); ?></a>
            </div>
        </div>

    </div>
</div>