<?php
/**
 * Migrate template - Create Site
 */

if ( isset( $_GET['clear'] ) && $_GET['clear'] == 'all' ) {
	instawp_reset_running_migration();
}

$incomplete_task_ids = InstaWP_taskmanager::is_there_any_incomplete_task_ids();
$is_loading_class    = ! empty( $incomplete_task_ids ) ? 'loading' : '';


?>

<div class="<?php echo esc_attr( $is_loading_class ); ?> nav-item-content create bg-white box-shadow rounded-md active">
    <div class="screen screen-0 flex items-center justify-center text-center py-20">

        <div>

			<?php if ( empty( InstaWP_Setting::get_api_key() ) ) : ?>

                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-4 rounded-md relative mb-6" role="alert">
                    <strong class="font-bold mr-1"><?php echo esc_html__( 'Connection Error:', 'instawp-connect' ); ?></strong>
                    <span class="block sm:inline"><?php echo esc_html__( 'You need to connect the plugin with your InstaWP account first.', 'instawp-connect' ); ?></span>
                </div>

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