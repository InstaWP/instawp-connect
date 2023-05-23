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
	esc_html__( 'Overall Status Text', 'instawp-connect' ),
);
$nav_item_classes    = array( 'nav-item-content' );
$incomplete_task_ids = InstaWP_taskmanager::is_there_any_incomplete_task_ids();

if ( ! empty( $incomplete_task_ids ) ) {
	$nav_item_classes[] = 'loading';
}


?>

<div class="<?php echo esc_attr( implode( ' ', $nav_item_classes ) ); ?> create active">

	<?php if ( empty( InstaWP_Setting::get_api_key() ) ) : ?>

        <div class="flex items-center justify-center text-center py-20">
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

	<?php else: ?>

        <div class="flex p-8 flex items-start">
            <div class="left-width">
                <ul role="list" class="-mb-8">

					<?php foreach ( $staging_screens as $index => $screen ) : ?>
                        <li>
                            <div class="screen-nav relative pb-8 <?php echo ( $index == 0 ) ? 'active' : ''; ?>">

                                <?php if ( $index < 3 ) : ?>
                                    <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
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
                                            <p class="screen-nav-label text-xs font-semibold uppercase <?php echo ( $index == 0 ) ? 'text-primary-900' : 'text-grayCust-50'; ?>"><?php echo $screen; ?></p>
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

                    <div class="screen screen-1 active">
                        <div class="flex justify-between items-center accordion cursor-pointer active">
                            <div class="text-grayCust-200 text-lg font-bold">Select Staging</div>
                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/down-img.svg' ) ); ?>" alt="" class="down-img">
                        </div>
                        <div class="panel mt-6 block">
                            <div class="flex justify-between items-center card-active border mb-4 border-primary-900 flex p-4 rounded-xl">
                                <label for="Staging" class="flex items-center">
                                    <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/flag-icon.svg' ) ); ?>" alt=""></div>
                                    <div class="ml-4">
                                        <div class="text-graCust-300 text-lg font-medium">Quick Staging</div>
                                        <div class="text-grayCust-50 text-sm font-normal">Without Media</div>
                                    </div>
                                </label>
                                <div>
                                    <input id="Staging" name="Staging" type="radio" checked="" class="h-4 w-4 border-grayCust-350 text-primary-900 focus:border-0 foucs:ring-1 focus:ring-primary-900">
                                </div>
                            </div>
                            <div class="flex justify-between items-center  border mb-4 border-primary-600 flex p-4 rounded-xl">
                                <label for="full_staging" class="flex items-center">
                                    <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/flag-2.svg' ) ); ?>" alt=""></div>
                                    <div class="ml-4">
                                        <div class="text-graCust-300 text-lg font-medium">Full Staging</div>
                                        <div class="text-grayCust-50 text-sm font-normal">With Media</div>
                                    </div>
                                </label>
                                <div>
                                    <input id="full_staging" name="Staging" type="radio" class="h-4 w-4 border-grayCust-350 text-primary-900 focus:border-0 foucs:ring-1 focus:ring-primary-900">
                                </div>
                            </div>
                            <div class="flex justify-between items-center  border mb-4 border-primary-600 flex p-4 rounded-xl">
                                <label for="custom_staging" class="flex items-center">
                                    <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/flag-3.svg' ) ); ?>" alt=""></div>
                                    <div class="ml-4">
                                        <div class="text-graCust-300 text-lg font-medium">Custom Staging</div>
                                        <div class="text-grayCust-50 text-sm font-normal">Choose Options</div>
                                    </div>
                                </label>
                                <div>
                                    <input id="custom_staging" name="Staging" type="radio" class="h-4 w-4 border-grayCust-350 text-primary-900 focus:border-0 foucs:ring-1 focus:ring-primary-900">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="screen screen-2">
                        <div class="flex justify-between items-center accordion cursor-pointer active">
                            <div class="text-grayCust-200 text-lg font-bold">Select Your Information</div>
                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/down-img.svg' ) ); ?>" alt="" class="down-img">
                        </div>
                        <div class="panel mt-6 block" style="">
                            <div class="grid grid-cols-3 gap-5">
                                <div class="relative flex items-start border border-primary-900 card-active p-3 px-4 rounded-lg">
                                    <div class="flex h-6 items-center">
                                        <input id="wordpress" checked="" name="wordpress" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                    </div>
                                    <div class="ml-3 text-sm leading-6">
                                        <label for="wordpress" class="font-medium text-sm text-grayCust-700">Wordpress</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                    <div class="flex h-6 items-center">
                                        <input id="wordpress1" name="wordpress1" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                    </div>
                                    <div class="ml-3 text-sm leading-6">
                                        <label for="wordpress1" class="font-medium text-sm text-grayCust-700">Wordpress</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                    <div class="flex h-6 items-center">
                                        <input id="wordpress3" name="wordpress3" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                    </div>
                                    <div class="ml-3 text-sm leading-6">
                                        <label for="wordpress3" class="font-medium text-sm text-grayCust-700">Wordpress</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                    <div class="flex h-6 items-center">
                                        <input id="wordpress4" name="wordpress4" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                    </div>
                                    <div class="ml-3 text-sm leading-6">
                                        <label for="wordpress4" class="font-medium text-sm text-grayCust-700">Wordpress</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                    <div class="flex h-6 items-center">
                                        <input id="wordpress5" name="wordpress5" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                    </div>
                                    <div class="ml-3 text-sm leading-6">
                                        <label for="wordpress5" class="font-medium text-sm text-grayCust-700">Wordpress</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                    <div class="flex h-6 items-center">
                                        <input id="wordpress6" name="wordpress6" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                    </div>
                                    <div class="ml-3 text-sm leading-6">
                                        <label for="wordpress6" class="font-medium text-sm text-grayCust-700">Wordpress</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                    <div class="flex h-6 items-center">
                                        <input id="wordpress7" name="wordpress7" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                    </div>
                                    <div class="ml-3 text-sm leading-6">
                                        <label for="wordpress7" class="font-medium text-sm text-grayCust-700">Wordpress</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                    <div class="flex h-6 items-center">
                                        <input id="wordpress8" name="8" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                    </div>
                                    <div class="ml-3 text-sm leading-6">
                                        <label for="wordpress8" class="font-medium text-sm text-grayCust-700">Wordpress</label>
                                    </div>
                                </div>
                                <div class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                    <div class="flex h-6 items-center">
                                        <input id="wordpress9" name="wordpress9" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-900 focus:ring-primary-900">
                                    </div>
                                    <div class="ml-3 text-sm leading-6">
                                        <label for="wordpress9" class="font-medium text-sm text-grayCust-700">Wordpress</label>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="screen screen-3">
                        <div class="flex justify-between items-center accordion cursor-pointer active">
                            <div class="text-grayCust-200 text-lg font-bold">3.Confirmation</div>
                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/down-img.svg' ) ); ?>" alt="" class="down-img">
                        </div>
                        <div class="panel mt-6 block">
                            <div class="flex items-center mb-6">
                                <div class="text-grayCust-900 text-base font-normal mr-6">Staging Type</div>
                                <div class="text-grayCust-300 text-base font-medium items-center flex mr-6">
                                    <svg class="mr-2" width="12" height="13" viewBox="0 0 12 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M0.399902 2.7999C0.399902 1.47442 1.47442 0.399902 2.7999 0.399902H10.7999C11.1029 0.399902 11.3799 0.571104 11.5154 0.842131C11.651 1.11316 11.6217 1.43749 11.4399 1.6799L9.3999 4.3999L11.4399 7.1199C11.6217 7.36232 11.651 7.68665 11.5154 7.95767C11.3799 8.2287 11.1029 8.3999 10.7999 8.3999H2.7999C2.35807 8.3999 1.9999 8.75807 1.9999 9.1999V11.5999C1.9999 12.0417 1.64173 12.3999 1.1999 12.3999C0.758075 12.3999 0.399902 12.0417 0.399902 11.5999V2.7999Z" fill="#9CA3AF"></path>
                                    </svg>
                                    Quick Staging
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="text-grayCust-900 text-base font-normal mr-4">Options Selected</div>
                            <div class="flex items-center">
                                <div class="border-primary-900 border card-active py-2 px-4 text-grayCust-700 text-xs font-medium rounded-lg mr-3">Wordpress</div>
                                <div class="border-primary-900 border card-active py-2 px-4 text-grayCust-700 text-xs font-medium rounded-lg mr-3">Wordpress</div>
                                <div class="border-primary-900 border card-active py-2 px-4 text-grayCust-700 text-xs font-medium rounded-lg">Wordpress</div>
                            </div>
                        </div>
                    </div>

                    <div class="screen screen-4">
                        <div class="flex justify-between items-center accordion cursor-pointer active">
                            <div class="text-grayCust-200 text-lg font-bold">Overall Status Text</div>
                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/down-img.svg' ) ); ?>" alt="" class="down-img">
                        </div>
                        <div class="panel mt-6 block">
                            <div class="border border-grayCust-100 rounded-lg mb-6">
                                <div class="p-5">
                                    <div class="flex items-center">
                                        <div class="w-24 text-grayCust-900 text-base font-normal">Backup</div>
                                        <div class="text-border rounded-xl w-full text-bg py-4 flex items-center mb-4 px-4">
                                            <div class="w-full bg-grayCust-450 rounded-md mr-6">
                                                <div class="h-2 bg-primary-900 rounded-md" style="width: 70%;"></div>
                                            </div>
                                            <div class="text-grayCust-650 text-sm font-medium">70%</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-24 text-grayCust-900 text-base font-normal">Upload</div>
                                        <div class="text-border rounded-xl w-full text-bg py-4 flex items-center mb-4 px-4">
                                            <div class="w-full bg-grayCust-450 rounded-md mr-6">
                                                <div class="h-2 bg-primary-900 rounded-md" style="width: 30%;"></div>
                                            </div>
                                            <div class="text-grayCust-650 text-sm font-medium">30%</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-24 text-grayCust-900 text-base font-normal">Staging</div>
                                        <div class="text-border rounded-xl w-full text-bg py-4 flex items-center mb-4 px-4">
                                            <div class="w-full bg-grayCust-450 rounded-md mr-6">
                                                <div class="h-2 bg-primary-900 rounded-md" style="width: 90%;"></div>
                                            </div>
                                            <div class="text-grayCust-650 text-sm font-medium">90%</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-grayCust-250 px-6 py-3 rounded-bl-lg rounded-br-lg flex justify-end">
                                    <button class="btn-shadow border border-grayCust-350 mr-4 rounded-md py-2 px-8 bg-white text-redCust-50 text-sm font-medium">Abort</button>
                                </div>
                            </div>
                            <div class="border border-grayCust-100 rounded-lg">
                                <div class="p-6 border-b border-grayCust-10 flex items-center justify-center text-lg font-medium text-grayCust-800">
                                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/check-icon.png' ) ); ?>" class="mr-2" alt="">Your new Wordpress website is ready!
                                </div>
                                <div class="p-6 custom-bg">
                                    <div class="flex items-center mb-6">
                                        <div class="text-grayCust-900 text-base font-normal w-24">URL</div>
                                        <div class="flex items-center cursor-pointer text-primary-900 border-b font-medium text-base border-dashed border-primary-900 ">https://wpsite.instawp.xyz<img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/share-icon.svg' ) ); ?>" class="ml-2" alt=""></div>
                                    </div>
                                    <div class="flex items-center mb-6">
                                        <div class="text-grayCust-900 text-base font-normal w-24">Username</div>
                                        <div class="text-grayCust-300 font-medium text-base  ">gucifi</div>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center">
                                            <div class="text-grayCust-900 text-base font-normal w-24">Password</div>
                                            <div class="text-grayCust-300 font-medium text-base">fL7cMdPY</div>
                                        </div>
                                        <button class="py-2 px-4 text-white bg-primary-700 rounded-md text-sm font-medium">Auto Login</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="bg-grayCust-250 px-6 py-3 rounded-bl-lg rounded-br-lg flex justify-end">
                    <button type="button" data-screen="1" class="instawp-button-connect btn-shadow rounded-md py-2 px-4 bg-primary-900 text-white hover:text-white text-sm font-medium">Continue</button>
                </div>
            </div>
        </div>

	<?php endif; ?>

</div>