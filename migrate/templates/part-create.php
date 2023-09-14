<?php
/**
 * Migrate template - Create Site
 */

$staging_screens     = array(
	esc_html__( 'Staging Type', 'instawp-connect' ),
	esc_html__( 'Customize Options', 'instawp-connect' ),
	esc_html__( 'Exclude Files', 'instawp-connect' ),
	esc_html__( 'Confirmation', 'instawp-connect' ),
	esc_html__( 'Creating Staging', 'instawp-connect' ),
);
$nav_item_classes    = array( 'nav-item-content' );
$incomplete_task_ids = InstaWP_taskmanager::is_there_any_incomplete_task_ids();
$incomplete_task_id  = reset( $incomplete_task_ids );
$migration_nonce     = InstaWP_taskmanager::get_nonce( $incomplete_task_id );

if ( ! empty( $incomplete_task_id ) && ! empty( InstaWP_Setting::get_option( 'instawp_migration_running', '' ) ) ) {
	$nav_item_classes[] = 'loading';
}

$current_create_screen = isset( $_GET['screen'] ) ? sanitize_text_field( $_GET['screen'] ) : 1;
$customize_options     = array(
	'general' => array(
		'label'   => esc_html__( 'General', 'instawp-connect' ),
		'options' => array(
			'active_plugins_only' => esc_html__( 'Active Plugins Only', 'instawp-connect' ),
			'active_themes_only'  => esc_html__( 'Active Themes Only', 'instawp-connect' ),
//			'skip_post_revisions' => esc_html__( 'Skip Post Revisions', 'instawp-connect' ),
			'skip_media_folder'   => esc_html__( 'Skip Media Folder', 'instawp-connect' ),
		),
	),
);

$list_data = get_option( 'instawp_large_files_list', [] ) ?? [];
if ( ! empty( $list_data ) ) {
    $customize_options['general']['options']['skip_large_files'] = esc_html__( 'Skip Large Files', 'instawp-connect' );
} ?>

<form action="" method="post" class="<?php echo esc_attr( implode( ' ', $nav_item_classes ) ); ?> create active">

	<?php if ( empty( InstaWP_Setting::get_api_key() ) ) : ?>

        <div class="bg-white text-center rounded-md py-20 flex items-center justify-center">
            <div>
                <div class="mb-4">
                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/createsite.svg' ) ); ?>" class="mx-auto" alt="">
                </div>
                <div class="text-sm font-medium text-grayCust-200 mb-1"><?php esc_html_e( 'InstaWP account is not connected', 'instawp-connect' ); ?></div>
                <div class="text-sm font-normal text-grayCust-50 mb-4"><?php esc_html_e( 'Please authorize your account in order to connect this site and enable staging site creation.', 'instawp-connect' ); ?></div>
                <a class="instawp-button-connect cursor-pointer	px-7 py-3 inline-flex items-center mx-auto rounded-md shadow-sm bg-primary-900 text-white hover:text-white active:text-white focus:text-white focus:shadow-none font-medium text-sm">
                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-plus.svg' ) ); ?>" class="mr-2" alt="">
                    <span><?php esc_html_e( 'Connect', 'instawp-connect' ); ?></span>
                </a>
            </div>
        </div>

	<?php else: ?>

        <div class="flex p-8 flex items-start">
            <div class="left-width">
                <ul role="list" class="screen-nav-items -mb-8">
					<?php foreach ( $staging_screens as $index => $screen ) : ?>
                        <li>
                            <div class="screen-nav relative pb-8 <?php echo ( $index == 0 ) ? 'active' : ''; ?>">
								<?php if ( $index < 4 ) : ?>
                                    <span class="screen-nav-line absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
								<?php endif; ?>
                                <div class="relative flex space-x-3">
                                    <div>
                                        <div class="screen-nav-icon h-8 w-8 rounded-full border-2 border-primary-900 flex items-center justify-center <?php echo ( $index == 0 ) ? 'bg-primary-900' : 'bg-white'; ?>">
                                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/true-icon.svg' ) ); ?>" alt="True Icon">
                                            <span class="w-2 h-2 bg-primary-900 rounded"></span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="screen-nav-label text-xs font-medium uppercase <?php echo ( $index == 0 ) ? 'text-primary-900' : 'text-grayCust-50'; ?>"><?php echo esc_html( $screen ); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
					<?php endforeach; ?>
                </ul>
            </div>

            <div class="w-full">
                <div class="p-6 bg-white rounded-md">
                    <div class="screen screen-1 <?= $current_create_screen == 1 ? 'active' : ''; ?>">
                        <div class="flex justify-between items-center">
                            <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '1. Select Staging', 'instawp-connect' ); ?></div>
                        </div>
                        <div class="panel mt-6 block">
                            <div for="quick_staging" class="instawp-staging-type cursor-pointer flex justify-between items-center border mb-4 border-primary-600 flex p-4 rounded-xl">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-quick.svg' ) ); ?>" alt=""></div>
                                    <div class="ml-4">
                                        <div class="text-graCust-300 text-lg font-medium staging-type-label"><?php esc_html_e( 'Quick Staging', 'instawp-connect' ); ?></div>
                                        <div class="text-grayCust-50 text-sm font-normal"><?php esc_html_e( 'Create a staging environment without include media folder.', 'instawp-connect' ); ?></div>
                                    </div>
                                </div>
                                <div>
                                    <input id="quick_staging" name="instawp_migrate[type]" value="quick" type="radio" class="instawp-option-selector h-4 w-4 border-grayCust-350 text-primary-900 focus:border-0 foucs:ring-1 focus:ring-primary-900">
                                </div>
                            </div>
                            <div for="full_staging" class="instawp-staging-type cursor-pointer flex justify-between items-center border mb-4 border-primary-600 flex p-4 rounded-xl">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-full.svg' ) ); ?>" alt=""></div>
                                    <div class="ml-4">
                                        <div class="text-graCust-300 text-lg font-medium staging-type-label"><?php esc_html_e( 'Full Staging', 'instawp-connect' ); ?></div>
                                        <div class="text-grayCust-50 text-sm font-normal"><?php esc_html_e( 'Create an exact copy as a staging environment. Time may vary based on site size.', 'instawp-connect' ); ?></div>
                                    </div>
                                </div>
                                <div>
                                    <input id="full_staging" name="instawp_migrate[type]" value="full" type="radio" class="instawp-option-selector h-4 w-4 border-grayCust-350 text-primary-900 focus:border-0 foucs:ring-1 focus:ring-primary-900">
                                </div>
                            </div>
                            <div for="custom_staging" class="instawp-staging-type cursor-pointer flex justify-between items-center border border-primary-600 flex p-4 rounded-xl">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-custom.svg' ) ); ?>" alt=""></div>
                                    <div class="ml-4">
                                        <div class="text-graCust-300 text-lg font-medium staging-type-label"><?php esc_html_e( 'Custom Staging', 'instawp-connect' ); ?></div>
                                        <div class="text-grayCust-50 text-sm font-normal"><?php esc_html_e( 'Choose the options that matches your requirements.', 'instawp-connect' ); ?></div>
                                    </div>
                                </div>
                                <div>
                                    <input id="custom_staging" name="instawp_migrate[type]" value="custom" type="radio" class="instawp-option-selector h-4 w-4 border-grayCust-350 text-primary-900 focus:border-0 foucs:ring-1 focus:ring-primary-900">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="screen screen-2 <?= $current_create_screen == 2 ? 'active' : ''; ?>">
                        <div class="flex justify-between items-center">
                            <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '2. Select Your Information', 'instawp-connect' ); ?></div>
                        </div>
                        <div class="panel mt-6 block">
							<?php foreach ( $customize_options as $customize_option ) : ?>
                                <div class="text-lg font-normal mb-2"><?php echo esc_html( InstaWP_Setting::get_args_option( 'label', $customize_option ) ); ?></div>
                                <div class="grid grid-cols-3 gap-5">
									<?php foreach ( InstaWP_Setting::get_args_option( 'options', $customize_option, array() ) as $id => $label ) : ?>
                                        <!--relative flex items-start border border-primary-900 card-active p-3 px-4 rounded-lg-->
                                        <label for="<?php echo esc_attr( $id ); ?>" class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg items-center">
                                            <span>
                                                <input id="<?php echo esc_attr( $id ); ?>" name="instawp_migrate[options][]" value="<?php echo esc_attr( $id ); ?>" type="checkbox" class="instawp-option-selector rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                            </span>
                                            <span class="ml-2 text-sm">
                                                <span class="option-label font-medium text-sm text-grayCust-700"><?php echo esc_html( $label ); ?></span>
                                            </span>
                                        </label>
									<?php endforeach; ?>
                                </div>
							<?php endforeach; ?>
                        </div>
                    </div>

                    <div class="screen screen-3 <?= $current_create_screen == 3 ? 'active' : ''; ?>">
                        <div class="flex justify-between items-center">
                            <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '3. Exclude', 'instawp-connect' ); ?></div>
                        </div>
                        <?php if ( ! empty( $list_data ) ) { ?>
                            <div class="bg-yellow-50 border border-2 border-r-0 border-y-0 border-l-orange-400 rounded-lg text-sm text-orange-700 p-4 mt-4 flex flex-col items-start gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="text-sm font-medium"><?php esc_html_e( 'We have identified following large files in your installation:', 'instawp-connect' ); ?></div>
                                    <button type="button" class="instawp-refresh-large-files">
                                        <svg class="w-4 h-4" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" style="fill: #005e54;" d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"></path> </svg>
                                    </button>
                                </div>
                                <div class="flex flex-col items-start gap-3 instawp-large-file-container">
                                    <?php foreach ( $list_data as $data ) {
                                        $element_id = wp_generate_uuid4(); ?>
                                        <div class="flex justify-between items-center text-xs">
                                            <input type="checkbox" name="instawp_migrate[excluded_paths][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $data['relative_path'] ); ?>" class="instawp-checkbox exclude-item large-file !mt-0 !mr-3 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                            <label for="<?php echo esc_attr( $element_id ); ?>"><?php echo esc_html( $data['relative_path'] ); ?> (<?php echo esc_html( instawp()->get_file_size_with_unit( $data['size'] ) ); ?>)</label>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="panel mt-6 block">
                            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                                <div class="min-w-full divide-y divide-gray-300">
                                    <div class="bg-gray-50 flex flex-row items-center justify-between p-4">
                                        <div>
                                            <div class="text-left text-sm font-medium text-grayCust-900 font-bold"><?php esc_html_e( 'Files', 'instawp-connect' ); ?></div>
                                        </div>
                                        <div class="flex flex-row items-center justify-between gap-5">
                                            <div class="text-left text-sm font-medium text-grayCust-900">
                                                <input type="checkbox" id="instawp-files-select-all" class="instawp-checkbox !mr-1 rounded border-gray-300 text-primary-900 focus:ring-primary-900" disabled="disabled" style="margin-top: -2px;">
                                                <label for="instawp-files-select-all"><?php esc_html_e( 'Select All', 'instawp-connect' ); ?></label>
                                            </div>
                                            <div class="text-left text-sm font-medium text-grayCust-900">
                                                <label for="instawp-sort-by">Sort by:</label>
                                                <select id="instawp-sort-by" disabled="disabled">
                                                    <option value="descending-size">Size ↑</option>
                                                    <option value="ascending-size">Size ↓</option>
                                                    <option value="descending-size">Name ↑</option>
                                                    <option value="ascending-name">Name ↓</option>
                                                </select>
                                            </div>
                                            <button type="button" class="instawp-refresh-file-explorer animate-spin" disabled="disabled">
                                                <svg class="w-4 h-4" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" style="fill: #005e54;" d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"></path> </svg>
                                            </button>
                                            <!-- <div class="text-left text-sm font-medium text-grayCust-900">Select All</div>
                                            <div class="text-left upercase text-sm font-medium text-grayCust-900">Filter</div> -->
                                        </div>
                                    </div>
                                    <div class="overflow-auto exclude-container">
                                        <div class="loading"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="screen screen-4 <?= $current_create_screen == 4 ? 'active' : ''; ?>">
                        <div class="confirmation-preview">
                            <div class="flex justify-between items-center">
                                <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '3. Confirmation', 'instawp-connect' ); ?></div>
                            </div>
                            <div class="panel mt-6 block">
                                <div class="flex items-center mb-6">
                                    <div class="text-grayCust-900 text-base font-normal mr-4 w-[140px]"><?php esc_html_e( 'Staging Type', 'instawp-connect' ); ?></div>
                                    <div class="text-grayCust-300 text-base font-medium items-center flex mr-6 selected-staging-type"><?php esc_html_e( 'Quick Staging', 'instawp-connect' ); ?></div>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="text-grayCust-900 text-base font-normal mr-4 w-[140px]"><?php esc_html_e( 'Options Selected', 'instawp-connect' ); ?></div>
                                <div class="grid grid-cols-3 gap-3 selected-staging-options"></div>
                            </div>
                        </div>

                        <div class="confirmation-warning hidden text-center px-24 py-8">
                            <div class="mb-2 flex justify-center text-center"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/warning.svg' ) ); ?>" alt="Warning"></div>
                            <div class="mb-2 text-graCust-300 text-lg font-medium staging-type-label"><?php esc_html_e( 'You have reached your limit', 'instawp-connect' ); ?></div>
                            <div class="mb-2 text-gray-500 text-sm font-normal leading-6"><?php esc_html_e( 'You have exceeded the maximum allowance of your plan.', 'instawp-connect' ); ?></div>

                            <div class="p-6 custom-bg rounded-lg border my-6">
                                <div class="flex items-center mb-6">
                                    <div class="text-grayCust-900 text-base text-left font-normal w-48"><?php esc_html_e( 'Remaining Sites', 'instawp-connect' ); ?></div>
                                    <div class="flex items-center text-primary-900 text-base">
                                        <span class="remaining-site"></span>
                                        <span>/</span>
                                        <span class="user-allow-site"></span>
                                    </div>
                                </div>
                                <div class="flex items-center mb-6">
                                    <div class="text-grayCust-900 text-base text-left font-normal w-48"><?php esc_html_e( 'Available Disk Space', 'instawp-connect' ); ?></div>
                                    <div class="flex items-center text-primary-900 text-base">
                                        <span class="remaining-disk-space"></span>
                                        <span>/</span>
                                        <span class="user-allow-disk-space"></span>
                                        <span class="ml-1"><?php esc_html_e( 'MB', 'instawp-connect' ); ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <div class="text-grayCust-900 text-base text-left font-normal w-48"><?php esc_html_e( 'Require Disk Space', 'instawp-connect' ); ?></div>
                                    <div class="flex items-center text-primary-900 text-base">
                                        <span class="require-disk-space"></span>
                                        <span class="ml-1"><?php esc_html_e( 'MB', 'instawp-connect' ); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex text-center gap-4 items-center justify-center">
                                <button type="button" class="instawp-migration-start-over text-gray-700 py-3 px-6 border border-grayCust-350 text-sm font-medium rounded-md"><?php esc_html_e( 'Start Over', 'instawp-connect' ); ?></button>
                                <a href="#" target="_blank" class="btn-shadow rounded-md w-fit text-center py-3 px-6 bg-primary-900 text-white hover:text-white text-sm font-medium" style="background: #11BF85;"><?php esc_html_e( 'Increase Limit', 'instawp-connect' ); ?></a>
                            </div>
                        </div>

                    </div>

                    <div class="screen screen-5 <?= $current_create_screen == 5 ? 'active' : ''; ?>">
                        <div class="flex justify-between items-center">
                            <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '4. Creating Staging', 'instawp-connect' ); ?></div>
                            <span class="instawp-migration-loader text-primary-900 text-base font-normal" data-complete-text="Completed"><?php esc_html_e( 'In Progress...', 'instawp-connect' ); ?></span>
                        </div>
                        <div class="panel mt-6 block">
                            <div class="migration-running border border-grayCust-100 rounded-lg">
                                <div class="p-5 flex flex-col gap-4">
                                    <div class="flex items-center">
                                        <div class="w-24 text-grayCust-900 text-base font-normal"><?php esc_html_e( 'Backup', 'instawp-connect' ); ?></div>
                                        <div class="instawp-progress-backup text-border rounded-xl w-full text-bg py-4 flex items-center px-4">
                                            <div class="w-full bg-gray-200 rounded-md mr-6">
                                                <div class="progress-bar h-2 bg-primary-900 rounded-md"></div>
                                            </div>
                                            <div class="progress-text text-grayCust-650 text-sm font-medium"><?php esc_html_e( '0%', 'instawp-connect' ); ?></div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-24 text-grayCust-900 text-base font-normal"><?php esc_html_e( 'Upload', 'instawp-connect' ); ?></div>
                                        <div class="instawp-progress-upload text-border rounded-xl w-full text-bg py-4 flex items-center px-4">
                                            <div class="w-full bg-gray-200 rounded-md mr-6">
                                                <div class="progress-bar h-2 bg-primary-900 rounded-md"></div>
                                            </div>
                                            <div class="progress-text text-grayCust-650 text-sm font-medium"><?php esc_html_e( '0%', 'instawp-connect' ); ?></div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-24 text-grayCust-900 text-base font-normal"><?php esc_html_e( 'Migration', 'instawp-connect' ); ?></div>
                                        <div class="instawp-progress-staging text-border rounded-xl w-full text-bg py-4 flex items-center px-4">
                                            <div class="w-full bg-gray-200 rounded-md mr-6">
                                                <div class="progress-bar h-2 bg-primary-900 rounded-md"></div>
                                            </div>
                                            <div class="progress-text text-grayCust-650 text-sm font-medium"><?php esc_html_e( '0%', 'instawp-connect' ); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="instawp-track-migration-area bg-grayCust-250 px-5 py-4 rounded-bl-lg rounded-br-lg flex justify-end content-center items-center">
                                    <a class="instawp-track-migration hidden text-primary-900 hover:text-primary-900 text-sm text-left flex items-center" href="" target="_blank">
                                        <span class="mr-2"><?php esc_html_e( 'Track Migration', 'instawp-connect' ); ?></span>
                                        <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/share-icon.svg' ) ); ?>" class="inline ml-1" alt="">
                                    </a>
                                    <button type="button" class="instawp-migrate-abort btn-shadow border border-grayCust-350 rounded-md py-2 px-8 bg-white text-redCust-50 text-sm font-medium text-red-400"><?php esc_html_e( 'Abort', 'instawp-connect' ); ?></button>
                                </div>
                            </div>
                            <div class="migration-completed hidden border border-grayCust-100 rounded-lg">
                                <div class="p-6 border-b border-grayCust-10 flex items-center justify-center text-lg font-medium text-grayCust-800">
                                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/check-icon.png' ) ); ?>" class="mr-2" alt=""><?php esc_html_e( 'Your new WordPress website is ready!', 'instawp-connect' ); ?>
                                </div>
                                <div class="p-6 custom-bg">
                                    <div class="flex items-center mb-6">
                                        <div class="text-grayCust-900 text-base font-normal w-24"><?php esc_html_e( 'URL', 'instawp-connect' ); ?></div>
                                        <div class="flex items-center cursor-pointer text-primary-900 border-b font-medium text-base border-dashed border-primary-900 ">
                                            <a target="_blank" id="instawp-site-url">
                                                <span></span>
                                                <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/share-icon.svg' ) ); ?>" class="inline ml-1" alt="">
                                            </a>
                                        </div>
                                    </div>
                                    <div class="flex items-center mb-6">
                                        <div class="text-grayCust-900 text-base font-normal w-24"><?php esc_html_e( 'Username', 'instawp-connect' ); ?></div>
                                        <div id="instawp-site-username" class="text-grayCust-300 font-medium text-base"></div>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center">
                                            <div class="text-grayCust-900 text-base font-normal w-24"><?php esc_html_e( 'Password', 'instawp-connect' ); ?></div>
                                            <div id="instawp-site-password" class="text-grayCust-300 font-medium text-base"></div>
                                        </div>
                                        <a href="" target="_blank" id="instawp-site-magic-url" class="py-2 px-4 text-white active:text-white focus:text-white hover:text-white bg-primary-700 rounded-md text-sm font-medium focus:shadow-none"><?php esc_html_e( 'Auto Login', 'instawp-connect' ); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="screen-buttons-last hidden bg-grayCust-250 px-5 py-4 rounded-bl-lg rounded-br-lg flex justify-between">
                    <div class="text-primary-900 text-sm font-medium cursor-pointer flex items-center instawp-create-another-site">
                        <span class="text-xl mr-1 -mt-1 self-center">+</span><?php esc_html_e( 'Create another Staging Site', 'instawp-connect' ); ?>
                    </div>
                    <div class="text-grayCust-900 text-sm font-medium cursor-pointer flex items-center instawp-show-staging-sites">
                        <span><?php esc_html_e( 'Show my staging sites', 'instawp-connect' ); ?></span>
                        <div class="flex items-center ml-2">
                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/right-icon.svg' ) ); ?>" alt="">
                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/right-icon.svg' ) ); ?>" alt="">
                        </div>
                    </div>
                </div>

                <div class="screen-buttons bg-grayCust-250 px-5 py-4 rounded-bl-lg rounded-br-lg flex justify-end">
                    <p class="doing-request"><span class="loader"></span><?php esc_html_e( 'Checking usages...', 'instawp-connect' ); ?></p>
                    <input name="instawp_migrate[screen]" type="hidden" id="instawp-screen" value="<?= $current_create_screen; ?>">
                    <input name="instawp_migrate[nonce]" type="hidden" id="instawp-nonce" value="<?= $migration_nonce; ?>">
                    <button type="button" data-increment="-1" class="instawp-button-migrate back hidden btn-shadow border border-grayCust-350 mr-4 rounded-md py-2 px-8 bg-white text-grayCust-700 text-sm font-medium"><?php esc_html_e( 'Back', 'instawp-connect' ); ?></button>
                    <button type="button" data-increment="1" class="instawp-button-migrate continue btn-shadow rounded-md py-2 px-4 bg-primary-900 text-white hover:text-white text-sm font-medium"><?php esc_html_e( 'Next Step', 'instawp-connect' ); ?></button>
                </div>
            </div>
        </div>

	<?php endif; ?>

</form>