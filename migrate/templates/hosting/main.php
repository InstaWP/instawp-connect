<?php
/**
 * Migrate template - Main
 */

if ( isset( $_GET['clear'] ) && $_GET['clear'] == 'all' ) {
	instawp_reset_running_migration();
}

$migrate_hosting = array(
	'mode'             => 'migrate',
	'domain_search'    => true,
	'email_collection' => true,
	'providers'        => array(
		array(
			'id'       => 'mijndomein',
			'name'     => 'Mijndomein Hosting B.V',
			'logo_url' => 'https://www.mijndomein.nl/data-assets/images/logo-mijndomein.svg',
			'default'  => true
		),
	)
);
//$website_domain_confirm = 'https://sty-magpie-gibo.a.instawpsites.com/wp-admin/authorize-application.php?app_name=InstaWP&app_id=33ff0627-fdd3-5266-a7b3-9eba4e2d07e3&success_url=https%3A%2F%2Fstage.instawp.io%2Fdesign20%2Fwp-connect-callback%3Fsid%3DMjEzMjQ%3D';
$website_domain_confirm = '';


?>

<div class="wrap instawp-migrate-wrap box-width rounded-2xl">

    <input type="number" id="migrate-step-controller" value="1">

    <div class="w-full">
        <div class="text-center py-6">
            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/logo-mijndomein.svg' ) ); ?>" class="mx-auto w-60" alt="">
        </div>
        <div class="p-8 bg-grayCust-250 rounded-b-2xl">

            <!-- Choose a domain -->
            <div class="migrate-step step-1 relative flex mb-8">
                <div class="w-14">
                    <div class="step-progress-line absolute top-4 left-4 -ml-px mt-0.5 h-full w-0.5 bg-grayCust-350" style="top: 40px;"></div>
                    <div class="group relative flex items-start">
                        <div class="flex h-9 items-center">
                            <div class="absolute top-4 back-line left-4  h-full w-0.5 bg-secondary-900" aria-hidden="true">
                                <div class="step-progress-box group relative flex items-start position-relative -left-4 -top-2">
                                    <div class="flex h-9 items-center">
                                         <span class="step-progress-icon relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-purpleCust-700 bg-white">
                                             <span class="h-2.5 w-2.5 rounded-full bg-purpleCust-700"></span>
                                             <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/correct-icon.svg' ) ); ?>" class="hidden h-4 w-4" alt="" srcset="">
                                         </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="accordion w-full">
                    <div class="accordion-item ">
                        <div class="accordion-item-header">
                            <div class="flex justify-between items-center">
                                <div class="accordion-item-header-title text-lg text-grayCust-150 font-bold">1. Choose a domain</div>
                                <div class="flex items-center">
                                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/down-icon.svg' ) ); ?>" alt="" class="rotate-180">
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item-body">
                            <div class="accordion-item-body-content">
                                <div class="accordion-padding">
                                    <div class="mb-4">
                                        <label for="website_domain" class="block text-sm font-medium text-grayCust-700 mb-2">Choose a domain name for your website</label>
                                        <div class="relative">
                                            <input value="" type="text" name="website_domain" placeholder="Enter Domain Name" id="website_domain" autocomplete="off" class="block w-full py-2.5 pr-3 pl-10 rounded-md border-grayCust-350 shadow-sm focus:border-primary-900 focus:ring-1 focus:ring-primary-900 sm:text-sm"/>
                                            <div class="absolute top-2.5 left-2.5">
                                                <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/globe-icon.png' ) ); ?>" alt="">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="migrate-domain-search-response text-sm inline-flex items-center font-sm hidden">
                                        <img alt="Response Icon">
                                        <span></span>
                                    </div>
                                </div>
                                <div class="migrate-step-response bg-grayCust-250 py-4 rounded-b-2xl px-6 flex justify-between items-center">
                                    <div class="loading-controller opacity-0 text-primary-800 inline-flex items-center text-sm font-normal">
                                        <span style="margin-left: 26px;">Checking availability...</span>
                                    </div>
                                    <button type="button" data-proceed="no" class="migrate-step-proceed bg-purpleCust-700 py-2 px-4 text-white text-sm font-medium shadow rounded-md focus:ring-2 focus:ring-offset-2 focus:ring-purpleCust-700 hover:text-white">Check Availability</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Set up an account -->
            <div class="migrate-step step-2 relative flex mb-8">
                <div class="w-14">
                    <div class="step-progress-line absolute top-4 left-4 -ml-px mt-0.5 h-full w-0.5 bg-grayCust-350" style="top: 40px;"></div>
                    <div class="group relative flex items-start">
                        <div class="flex h-9 items-center">
                            <div class="absolute top-4 back-line left-4  h-full w-0.5 bg-secondary-900">
                                <div class="step-progress-box group relative flex items-start top-3 position-relative -left-4" style="top:-8px">
                                    <div class="flex h-9 items-center">
                                        <span class="step-progress-icon relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-gray-300 bg-white">
                                             <span class="h-2.5 w-2.5 rounded-full bg-purpleCust-700 hidden"></span>
                                             <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/correct-icon.svg' ) ); ?>" class="hidden h-4 w-4" alt="" srcset="">
                                         </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="accordion w-full">
                    <div class="accordion-item ">
                        <div class="accordion-item-header">
                            <div class="flex justify-between items-center">
                                <div class="accordion-item-header-title text-sm text-grayCust-900 font-medium">2. Setup an account</div>
                                <div class="flex items-center">
                                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/down-icon.svg' ) ); ?>" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item-body">
                            <div class="accordion-item-body-content accordion-height">
                                <div class="accordion-padding">
                                    <div class="mb-2">
                                        <label for="email_address" class="block text-sm font-medium text-grayCust-700 mb-2">Email ID</label>
                                        <div class="relative">
                                            <input value="" type="text" name="email_address" placeholder="Enter Email ID" id="email_address" autocomplete="off" class="block w-full py-2.5 pr-3 pl-10 rounded-md border-grayCust-350 shadow-sm focus:border-primary-900 focus:ring-1 focus:ring-primary-900 sm:text-sm"/>
                                            <div class="absolute top-2.5 left-2.5">
                                                <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/email-iocn.svg' ) ); ?>" alt="">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="migrate-step-response bg-grayCust-250 py-4 rounded-b-2xl px-6 flex justify-between items-center">
                                    <div class="loading-controller text-purpleCust-50 inline-flex items-center text-sm font-normal">
                                        <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/information-icon.svg' ) ); ?>" alt="" class="mr-2">
                                        <span>Create a new account in new window</span>
                                    </div>
                                    <button type="button" class="migrate-step-proceed bg-purpleCust-700 py-2 px-4 text-white text-sm font-medium shadow rounded-md focus:ring-2 focus:ring-offset-2 focus:ring-purpleCust-700 ">Setup Account</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ready to go live! -->
            <div class="migrate-step step-3 relative flex mb-8">
                <div class="w-14">
                    <div class="step-progress-line absolute top-4 left-4 -ml-px mt-0.5 h-full w-0.5 bg-grayCust-350" style="top: 40px;">
                    </div>
                    <div class="group relative flex items-start">
                        <div class="flex h-9 items-center">
                            <div class="absolute top-4 back-line left-4  h-full w-0.5 bg-secondary-900">
                                <div class="step-progress-box group relative flex items-start top-3 position-relative -left-4" style="top:-8px">
                                    <div class="flex h-9 items-center">
                                        <span class="step-progress-icon relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-gray-300 bg-white">
                                             <span class="h-2.5 w-2.5 rounded-full bg-purpleCust-700 hidden"></span>
                                             <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/correct-icon.svg' ) ); ?>" class="hidden h-4 w-4" alt="" srcset="">
                                         </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="accordion w-full">
                    <div class="accordion-item ">
                        <div class="accordion-item-header">
                            <div class="flex justify-between items-center">
                                <div class="accordion-item-header-title text-sm text-grayCust-900 font-medium">3. Ready to go live!</div>
                                <div class="flex items-center">
                                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/down-icon.svg' ) ); ?>" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item-body">
                            <div class="accordion-item-body-content accordion-height">
                                <div class="accordion-padding">
                                    <div class="mb-2">
                                        <label for="website_domain_confirm" class="block text-sm font-medium text-grayCust-700 mb-2">Confirm your final domain again</label>
                                        <div class="relative">
                                            <input value="<?= $website_domain_confirm; ?>" type="text" name="website_domain_confirm" placeholder="Enter Domain Name" id="website_domain_confirm" readonly autocomplete="off" class="block w-full py-2.5 pr-3 pl-10 rounded-md border-grayCust-350 shadow-sm focus:border-primary-900 focus:ring-1 focus:ring-primary-900 sm:text-sm"/>
                                            <div class="absolute top-2.5 left-2.5">
                                                <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/globe-icon.png' ) ); ?>" alt="">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="migrate-step-response bg-grayCust-250 py-4 rounded-b-2xl px-6 flex justify-between items-center">
                                    <div class="loading-controller text-purpleCust-50 inline-flex items-center text-sm font-normal">
                                        <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/information-icon.svg' ) ); ?>" alt="" class="mr-2">
                                        <span>Not Connected</span>
                                    </div>
                                    <div class="flex items-center ">
                                        <div class="migration-progress-wrap opacity-0 inline-flex items-center mr-4 text-purpleCust-100 text-sm font-medium">
                                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/loading-icon.svg' ) ); ?>" alt="" class="animate-spin mr-2">
                                            <span class="migration-progress">Migrating <span class="progress">50</span>%</span>
                                        </div>
                                        <button type="button" class="migrate-step-proceed bg-purpleCust-700 py-2 px-4 text-white text-sm font-medium shadow rounded-md focus:ring-2 focus:ring-offset-2 focus:ring-purpleCust-700 ">Connect Website</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- We are live -->
            <div class="migrate-step step-4 relative flex mb-8">
                <div class="w-14">
                    <div class="group relative flex items-start">
                        <div class="flex h-9 items-center">
                            <div class="absolute top-4 back-line left-4  h-full w-0.5 bg-secondary-900">
                                <div class="step-progress-box group relative flex items-start top-3 position-relative -left-4" style="top:-8px">
                                    <div class="flex h-9 items-center">
                                        <span class="step-progress-icon relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-gray-300 bg-white">
                                             <span class="h-2.5 w-2.5 rounded-full bg-purpleCust-700 hidden"></span>
                                             <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/correct-icon.svg' ) ); ?>" class="hidden h-4 w-4" alt="" srcset="">
                                         </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="accordion w-full">
                    <div class="accordion-item ">
                        <div class="accordion-item-header">
                            <div class="flex justify-between items-center">
                                <div class="accordion-item-header-title text-sm text-grayCust-900 font-medium">We are live</div>
                                <div class="flex items-center">
                                    <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/down-icon.svg' ) ); ?>" alt="">
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item-body">
                            <div class="accordion-item-body-content accordion-height">
                                <div class="accordion-padding">
                                    <div class="py-4 px-4 border flex justify-left items-center border-primary-50" style="border-radius: 12px;">
                                        <div class="inline-flex items-center text-grayCust-300 font-normal text-base">
                                            <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/check-icon.png' ) ); ?>" class="mr-2" alt="">
                                            <span class="mx-1">Your domain <span class="text-purpleCust-700 website-domain-name"></span> is now live.</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-grayCust-250 py-4 rounded-b-2xl px-6 flex justify-end items-center">
                                    <a href="" target="_blank" class="migrate-visit-site bg-purpleCust-700 py-2 px-4 text-white text-sm font-medium shadow rounded-md focus:ring-2 focus:ring-offset-0 focus:ring-white focus:text-white hover:text-white">Login To Your Site</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

