<?php
/**
 * Migrate template - Create Site
 */

if ( isset( $_GET['clear'] ) && $_GET['clear'] == 'all' ) {
	instawp_reset_running_migration();
}

$staging_screens     = array(
	esc_html__( 'Staging Type', 'instawp-connect' ),
	esc_html__( 'Customize Options', 'instawp-connect' ),
	esc_html__( 'Confirmation', 'instawp-connect' ),
	esc_html__( 'Staging Creation Status', 'instawp-connect' ),
);
$nav_item_classes    = array( 'nav-item-content' );
$incomplete_task_ids = InstaWP_taskmanager::is_there_any_incomplete_task_ids();

if ( ! empty( $incomplete_task_ids ) ) {
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

echo "<pre>";
print_r( get_option( 'instawp_connect_id_options' ) );
echo "</pre>";

?>

<form action="" method="post" class="<?php echo esc_attr( implode( ' ', $nav_item_classes ) ); ?> create active">

	<?php if ( empty( InstaWP_Setting::get_api_key() ) ) : ?>

        <div class="bg-white text-center box-shadow rounded-md py-20 flex items-center justify-center">
            <div>
                <div class="mb-4">
                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/createsite.svg' ) ); ?>" class="mx-auto" alt="">
                </div>
                <div class="text-sm font-medium text-grayCust-200 mb-1"><?php echo esc_html__( 'InstaWP account is not connected', 'instawp-connect' ); ?></div>
                <div class="text-sm font-normal text-grayCust-50 mb-4"><?php echo esc_html__( 'Please authorize your account in order to connect this site and enable staging site creation.', 'instawp-connect' ); ?></div>
                <a class="instawp-button-connect cursor-pointer	px-7 py-3 inline-flex items-center mx-auto rounded-md shadow-sm bg-primary-900 text-white hover:text-white active:text-white focus:text-white focus:shadow-none font-medium text-sm">
                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-plus.svg' ) ); ?>" class="mr-2" alt="">
                    <span><?php echo esc_html__( 'Connect', 'instawp-connect' ); ?></span>
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

								<?php if ( $index < 3 ) : ?>
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
                                            <p class="screen-nav-label text-xs font-medium uppercase <?php echo ( $index == 0 ) ? 'text-primary-900' : 'text-grayCust-50'; ?>"><?php echo $screen; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
					<?php endforeach; ?>
                </ul>
            </div>

            <div class="bg-white w-full box-shadow rounded-md">

                <div class="p-6">
                    <div class="screen screen-1 <?= $current_create_screen == 1 ? 'active' : ''; ?>">
                        <div class="flex justify-between items-center">
                            <div class="text-grayCust-200 text-lg font-bold">1. Select Staging</div>
                        </div>
                        <div class="panel mt-6 block">
                            <div for="quick_staging" class="instawp-staging-type cursor-pointer flex justify-between items-center border mb-4 border-primary-600 flex p-4 rounded-xl">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-quick.svg' ) ); ?>" alt=""></div>
                                    <div class="ml-4">
                                        <div class="text-graCust-300 text-lg font-medium staging-type-label">Quick Staging</div>
                                        <div class="text-grayCust-50 text-sm font-normal">Create a staging environment without include media folder.</div>
                                    </div>
                                </div>
                                <div>
                                    <input id="quick_staging" name="instawp_migrate[type]" value="quick" type="radio" class="h-4 w-4 border-grayCust-350 text-primary-900 focus:border-0 foucs:ring-1 focus:ring-primary-900">
                                </div>
                            </div>
                            <div for="full_staging" class="instawp-staging-type cursor-pointer flex justify-between items-center border mb-4 border-primary-600 flex p-4 rounded-xl">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-full.svg' ) ); ?>" alt=""></div>
                                    <div class="ml-4">
                                        <div class="text-graCust-300 text-lg font-medium staging-type-label">Full Staging</div>
                                        <div class="text-grayCust-50 text-sm font-normal">Create an exact copy as a staging environment. Time may vary based on site size.</div>
                                    </div>
                                </div>
                                <div>
                                    <input id="full_staging" name="instawp_migrate[type]" value="full" type="radio" class="h-4 w-4 border-grayCust-350 text-primary-900 focus:border-0 foucs:ring-1 focus:ring-primary-900">
                                </div>
                            </div>
                            <div for="custom_staging" class="instawp-staging-type cursor-pointer flex justify-between items-center border mb-4 border-primary-600 flex p-4 rounded-xl">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/icon-custom.svg' ) ); ?>" alt=""></div>
                                    <div class="ml-4">
                                        <div class="text-graCust-300 text-lg font-medium staging-type-label">Custom Staging</div>
                                        <div class="text-grayCust-50 text-sm font-normal">Choose the options that matches your requirements.</div>
                                    </div>
                                </div>
                                <div>
                                    <input id="custom_staging" name="instawp_migrate[type]" value="custom" type="radio" class="h-4 w-4 border-grayCust-350 text-primary-900 focus:border-0 foucs:ring-1 focus:ring-primary-900">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="screen screen-2 <?= $current_create_screen == 2 ? 'active' : ''; ?>">
                        <div class="flex justify-between items-center">
                            <div class="text-grayCust-200 text-lg font-bold">2. Select Your Information</div>
                        </div>
                        <div class="panel mt-6 block" style="">
							<?php foreach ( $customize_options as $customize_option ) : ?>

                                <div class="text-lg font-normal mb-2"><?php echo InstaWP_Setting::get_args_option( 'label', $customize_option ); ?></div>

                                <div class="grid grid-cols-3 gap-5">
									<?php foreach ( InstaWP_Setting::get_args_option( 'options', $customize_option, array() ) as $id => $label ) : ?>

                                        <!--relative flex items-start border border-primary-900 card-active p-3 px-4 rounded-lg-->

                                        <label for="<?php echo $id; ?>" class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                            <span class="flex h-7 items-center">
                                                <input id="<?php echo $id; ?>" name="instawp_migrate[options][]" value="<?php echo $id; ?>" type="checkbox" class="instawp-option-selector h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                            </span>
                                            <span class="ml-2 text-sm leading-6">
                                                <span class="option-label font-medium text-sm text-grayCust-700"><?php echo $label; ?></span>
                                            </span>
                                        </label>

									<?php endforeach; ?>
                                </div>

							<?php endforeach; ?>
                        </div>
                    </div>

                    <div class="screen screen-3 <?= $current_create_screen == 3 ? 'active' : ''; ?>">

                        <div class="confirmation-preview">
                            <div class="flex justify-between items-center">
                                <div class="text-grayCust-200 text-lg font-bold">3. Confirmation</div>
                            </div>
                            <div class="panel mt-6 block">
                                <div class="flex items-center mb-6">
                                    <div class="text-grayCust-900 text-base font-normal mr-4 w-[140px]">Staging Type</div>
                                    <div class="text-grayCust-300 text-base font-medium items-center flex mr-6 selected-staging-type">Quick Staging</div>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="text-grayCust-900 text-base font-normal mr-4 w-[140px]">Options Selected</div>
                                <div class="flex items-center selected-staging-options"></div>
                            </div>
                        </div>

                        <div class="confirmation-warning hidden text-center p-24">
                            <div class="mb-2 flex justify-center text-center"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/warning.svg' ) ); ?>" alt="Warning"></div>
                            <div class="mb-2 text-graCust-300 text-lg font-medium staging-type-label">You have reached your limit</div>
                            <div class="text-gray-500 text-sm font-normal leading-6">You have exceeded the maximum allowance of disk space for your plan.</div>
                            <div class="flex text-center gap-4 items-center justify-center">
                                <button type="button" class="instawp-migration-start-over text-gray-700 mt-4 py-3 px-6 border border-grayCust-350 text-sm font-medium rounded-md">Start Over</button>
                                <a href="#" target="_blank" class="btn-shadow rounded-md w-fit text-center mt-4 py-3 px-6 bg-primary-900 text-white hover:text-white text-sm font-medium" style="background: #11BF85;">Increase Limit</a>
                            </div>
                        </div>

                    </div>

                    <div class="screen screen-4 <?= $current_create_screen == 4 ? 'active' : ''; ?>">
                        <div class="flex justify-between items-center">
                            <div class="text-grayCust-200 text-lg font-bold">4. Staging Creation Status</div>
                            <span class="instawp-migration-loader text-primary-900 text-base font-normal" data-complete-text="Completed">In Progress...</span>
                        </div>
                        <div class="panel mt-6 block">
                            <div class="migration-running border border-grayCust-100 rounded-lg mb-6">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="w-24 text-grayCust-900 text-base font-normal">Backup</div>
                                        <div class="instawp-progress-backup text-border rounded-xl w-full text-bg py-4 flex items-center mb-4 px-4">
                                            <div class="w-full bg-gray-200 rounded-md mr-6">
                                                <div class="progress-bar h-2 bg-primary-900 rounded-md"></div>
                                            </div>
                                            <div class="progress-text text-grayCust-650 text-sm font-medium">0%</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-24 text-grayCust-900 text-base font-normal">Upload</div>
                                        <div class="instawp-progress-upload text-border rounded-xl w-full text-bg py-4 flex items-center mb-4 px-4">
                                            <div class="w-full bg-gray-200 rounded-md mr-6">
                                                <div class="progress-bar h-2 bg-primary-900 rounded-md"></div>
                                            </div>
                                            <div class="progress-text text-grayCust-650 text-sm font-medium">0%</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-24 text-grayCust-900 text-base font-normal">Staging</div>
                                        <div class="instawp-progress-staging text-border rounded-xl w-full text-bg py-4 flex items-center mb-4 px-4">
                                            <div class="w-full bg-gray-200 rounded-md mr-6">
                                                <div class="progress-bar h-2 bg-primary-900 rounded-md"></div>
                                            </div>
                                            <div class="progress-text text-grayCust-650 text-sm font-medium">0%</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-grayCust-250 px-6 py-3 rounded-bl-lg rounded-br-lg flex justify-end">
                                    <button class="instawp-migrate-abort btn-shadow border border-grayCust-350 rounded-md py-2 px-8 bg-white text-redCust-50 text-sm font-medium">Abort</button>
                                </div>
                            </div>
                            <div class="migration-completed hidden border border-grayCust-100 rounded-lg">
                                <div class="p-6 border-b border-grayCust-10 flex items-center justify-center text-lg font-medium text-grayCust-800">
                                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/check-icon.png' ) ); ?>" class="mr-2" alt="">Your new WordPress website is ready!
                                </div>
                                <div class="p-6 custom-bg">
                                    <div class="flex items-center mb-6">
                                        <div class="text-grayCust-900 text-base font-normal w-24">URL</div>
                                        <div class="flex items-center cursor-pointer text-primary-900 border-b font-medium text-base border-dashed border-primary-900 ">
                                            <a target="_blank" id="instawp-site-url">
                                                <span></span>
                                                <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/share-icon.svg' ) ); ?>" class="inline ml-1" alt="">
                                            </a>
                                        </div>
                                    </div>
                                    <div class="flex items-center mb-6">
                                        <div class="text-grayCust-900 text-base font-normal w-24">Username</div>
                                        <div id="instawp-site-username" class="text-grayCust-300 font-medium text-base"></div>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center">
                                            <div class="text-grayCust-900 text-base font-normal w-24">Password</div>
                                            <div id="instawp-site-password" class="text-grayCust-300 font-medium text-base"></div>
                                        </div>
                                        <a href="" target="_blank" id="instawp-site-magic-url" class="py-2 px-4 text-white active:text-white focus:text-white hover:text-white bg-primary-700 rounded-md text-sm font-medium focus:shadow-none">Auto Login</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="screen-buttons-last hidden bg-grayCust-250 px-6 py-3 rounded-bl-lg rounded-br-lg flex justify-between">
                    <div class="text-primary-900 text-sm font-medium cursor-pointer instawp-create-another-site"><span class="text-xl mr-1">+</span>Create another Staging Site</div>
                    <div class="text-grayCust-900 text-sm font-medium cursor-pointer flex items-center instawp-show-staging-sites">
                        <span>Show my staging sites</span>
                        <div class="flex items-center ml-2">
                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/right-icon.svg' ) ); ?>" alt="">
                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/right-icon.svg' ) ); ?>" alt="">
                        </div>
                    </div>
                </div>

                <div class="screen-buttons bg-grayCust-250 px-6 py-3 rounded-bl-lg rounded-br-lg flex justify-end">
                    <p class="doing-request"><span class="loader"></span>Checking usages...</p>
                    <input name="instawp_migrate[screen]" type="hidden" id="instawp-screen" value="<?= $current_create_screen; ?>">
                    <button type="button" data-increment="-1" class="instawp-button-migrate back hidden btn-shadow border border-grayCust-350 mr-4 rounded-md py-2 px-8 bg-white text-grayCust-700 text-sm font-medium">Back</button>
                    <button type="button" data-increment="1" class="instawp-button-migrate continue btn-shadow rounded-md py-2 px-4 bg-primary-900 text-white hover:text-white text-sm font-medium">Next Step</button>
                </div>
            </div>
        </div>

	<?php endif; ?>

</form>