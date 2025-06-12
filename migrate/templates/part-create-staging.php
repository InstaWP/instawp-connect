<?php
/**
 * Staging creation Screen
 */

use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

global $instawp_settings;

$staging_screens       = array(
	esc_html__( 'Staging Type', 'instawp-connect' ),
	esc_html__( 'Customize Options', 'instawp-connect' ),
	esc_html__( 'Exclude Files & Tables', 'instawp-connect' ),
	esc_html__( 'Confirmation', 'instawp-connect' ),
	esc_html__( 'Creating Staging', 'instawp-connect' ),
);
$customize_options     = array(
	'general' => array(
		'label'   => esc_html__( 'General Options', 'instawp-connect' ),
		'options' => array(
			'active_plugins_only' => esc_html__( 'Active Plugins Only', 'instawp-connect' ),
			'active_themes_only'  => esc_html__( 'Active Themes Only', 'instawp-connect' ),
			'skip_media_folder'   => esc_html__( 'Skip Media Folder', 'instawp-connect' ),
			'skip_large_files'    => esc_html__( 'Skip Large Files', 'instawp-connect' ),
			'skip_log_tables'     => esc_html__( 'Skip Log Tables', 'instawp-connect' ),
		),
	),
	'sync'    => array(
		'label'   => esc_html__( 'Sync Options', 'instawp-connect' ),
		'options' => array(
			'enable_event_syncing' => esc_html__( 'Enable Sync Recording', 'instawp-connect' ),
		),
	),
);
$current_create_screen = isset( $_GET['screen'] ) ? intval( $_GET['screen'] ) : 1;
$tables                = instawp_get_database_details();
$log_tables_to_exclude = InstaWP_Tools::get_log_tables_to_exclude();
$list_data             = Option::get_option( 'instawp_large_files_list' );
$migration_details     = Helper::get_args_option( 'instawp_migration_details', $instawp_settings );
$tracking_url          = Helper::get_args_option( 'tracking_url', $migration_details );
$migrate_id            = Helper::get_args_option( 'migrate_id', $migration_details );
$serve_with_wp         = (bool) Helper::get_args_option( 'serve_with_wp', $migration_details );
$whitelist_ip          = instawp_whitelist_ip();
$site_plans            = instawp()->is_connected ? instawp_get_plans( 'sites' ) : array();

delete_option( 'instawp_files_offset' );
delete_option( 'instawp_db_offset' );
?>

<div class="bg-white text-center rounded-md py-20 flex items-center justify-center connected <?= empty( $migrate_id ) ? '' : 'hidden'; ?>">
    <div class="w-2/3">
        <div class="mb-4">
            <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/connected.svg' ) ); ?>" class="mx-auto" alt="">
        </div>
        <div class="text-sm font-medium text-grayCust-200 mb-1"><?php esc_html_e( 'Your account is now connected', 'instawp-connect' ); ?></div>
        <div class="text-center inline-block text-sm font-normal text-grayCust-50 mb-4"><?php esc_html_e( 'Start by creating a new staging site', 'instawp-connect' ); ?></div>
        <div class="flex gap-5 items-center justify-center mt-3">
            <button type="button" class="create-staging-btn flex items-center justify-center gap-3 shadow-sm rounded-md py-2 px-4 bg-secondary text-white hover:text-white text-sm font-medium">
                <svg width="15" height="14" viewBox="0 0 15 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7.5 0C8.05228 0 8.5 0.447715 8.5 1V6H13.5C14.0523 6 14.5 6.44772 14.5 7C14.5 7.55228 14.0523 8 13.5 8H8.5V13C8.5 13.5523 8.05228 14 7.5 14C6.94772 14 6.5 13.5523 6.5 13V8H1.5C0.947715 8 0.5 7.55228 0.5 7C0.5 6.44771 0.947715 6 1.5 6L6.5 6V1C6.5 0.447715 6.94772 0 7.5 0Z" fill="white"/>
                </svg>
                <span><?php esc_html_e( 'Create Staging Site', 'instawp-connect' ); ?></span>
            </button>
            <button type="button" class="browse-staging-btn flex items-center justify-center gap-3 shadow-sm border border-grayCust-350 rounded-md py-2 px-4 bg-white text-sm font-medium">
                <span><?php esc_html_e( 'Browse Staging Sites', 'instawp-connect' ); ?></span>
                <svg width="15" height="14" viewBox="0 0 15 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8.33329 1.16663L14.1666 6.99996L8.33329 12.8333M1.66663 1.16663L7.49996 6.99996L1.66663 12.8333" stroke="#101828" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<div class="flex p-8 items-start create-staging <?= empty( $migrate_id ) ? 'hidden' : ''; ?>">
    <div class="left-width">
        <ul role="list" class="screen-nav-items -mb-8">
			<?php foreach ( $staging_screens as $index => $screen ) : ?>
                <li>
                    <div class="screen-nav relative pb-8 <?php echo ( $index === 0 ) ? 'active' : ''; ?>">
						<?php if ( $index < 4 ) : ?>
                            <span class="screen-nav-line absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
						<?php endif; ?>
                        <div class="relative flex space-x-3">
                            <div>
                                <div class="screen-nav-icon h-8 w-8 rounded-full border-2 border-secondary flex items-center justify-center <?php echo ( $index === 0 ) ? 'bg-secondary' : 'bg-white'; ?>">
                                    <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/true-icon.svg' ) ); ?>" alt="True Icon">
                                    <span class="w-2 h-2 bg-secondary rounded"></span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="screen-nav-label text-xs font-medium uppercase <?php echo ( $index === 0 ) ? 'text-secondary' : 'text-grayCust-50'; ?>"><?php echo esc_html( $screen ); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
			<?php endforeach; ?>
        </ul>
    </div>

    <div class="w-full shadow-sm rounded-md border">
        <div class="p-6 bg-white min-h-[380px] rounded-md">
            <div class="screen screen-1 <?= $current_create_screen === 1 ? 'active' : ''; ?>">
                <div class="flex justify-between items-center">
                    <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '1. Select Staging', 'instawp-connect' ); ?></div>
                </div>
				<?php if ( $whitelist_ip['can_whitelist'] ) { ?>
                    <div class="wordfence-whitelist bg-yellow-50 border-2 border-r-0 border-y-0 border-l-orange-400 rounded-lg text-sm text-orange-700 mt-4 p-4 flex flex-col items-start gap-3">
                        <div class="flex items-center gap-3">
                            <div class="texdt-xs fonht-medium"><?php printf( esc_html__( 'We have detected %s in your website, which might block API calls from our server. Whitelisting our IP address solves this problem. Shall we add a whitelist entry?', 'instawp-connect' ), esc_html( $whitelist_ip['plugins'] ) ); ?></div>
                        </div>
                        <div class="flex flex-col items-start gap-3">
                            <div class="flex justify-between items-center text-xss">
                                <input type="checkbox" name="instawp_migrate[whitelist_ip]" id="whitelist-ip" value="yes" class="instawp-checkbox !mt-0 !mr-3 rounded border-gray-300 text-secondary focus:ring-secondary">
                                <label for="whitelist-ip" class="mr-2"><?php esc_html_e( 'Yes, Whitelist IP', 'instawp-connect' ); ?></label>(<a class="cursor-pointer focus:outline-none focus:ring-0 hover:text-secondary border-b border-transparent border-1 border-dashed hover:border-primary-700" href="https://silicondales.com/tutorials/wordpress/whitelist-ip-wordfence/" target="_blank">Documentation</a>)
                            </div>
                        </div>
                    </div>
				<?php } ?>
                <div class="panel mt-6 block">
                    <div for="quick_staging" class="instawp-staging-type cursor-pointer flex justify-between items-center border mb-4 p-4 rounded-xl">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/icon-quick.svg' ) ); ?>" alt=""></div>
                            <div class="ml-4">
                                <div class="text-graCust-300 text-lg font-medium staging-type-label"><?php esc_html_e( 'Quick Staging', 'instawp-connect' ); ?></div>
                                <div class="text-grayCust-50 text-sm font-normal"><?php esc_html_e( 'Create a staging environment without include media folder.', 'instawp-connect' ); ?></div>
                            </div>
                        </div>
                        <div>
                            <input id="quick_staging" name="migrate_settings[type]" value="quick" type="radio" class="instawp-option-selector !size-4 !m-0 border-grayCust-350 text-secondary foucs:ring-1 focus:ring-secondary">
                        </div>
                    </div>
                    <div for="full_staging" class="instawp-staging-type cursor-pointer flex justify-between items-center border mb-4 flex p-4 rounded-xl">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/icon-full.svg' ) ); ?>" alt=""></div>
                            <div class="ml-4">
                                <div class="text-graCust-300 text-lg font-medium staging-type-label"><?php esc_html_e( 'Full Staging', 'instawp-connect' ); ?></div>
                                <div class="text-grayCust-50 text-sm font-normal"><?php esc_html_e( 'Create an exact copy as a staging environment. Time may vary based on site size.', 'instawp-connect' ); ?></div>
                            </div>
                        </div>
                        <div>
                            <input id="full_staging" name="migrate_settings[type]" value="full" type="radio" class="instawp-option-selector !size-4 !m-0 border-grayCust-350 text-secondary foucs:ring-1 focus:ring-secondary">
                        </div>
                    </div>
                    <div for="custom_staging" class="instawp-staging-type cursor-pointer flex justify-between items-center border flex p-4 rounded-xl">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white rounded-lg flex justify-center items-center border custom-border"><img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/icon-custom.svg' ) ); ?>" alt=""></div>
                            <div class="ml-4">
                                <div class="text-graCust-300 text-lg font-medium staging-type-label"><?php esc_html_e( 'Custom Staging', 'instawp-connect' ); ?></div>
                                <div class="text-grayCust-50 text-sm font-normal"><?php esc_html_e( 'Choose the options that matches your requirements.', 'instawp-connect' ); ?></div>
                            </div>
                        </div>
                        <div>
                            <input id="custom_staging" name="migrate_settings[type]" value="custom" type="radio" class="instawp-option-selector !size-4 !m-0 border-grayCust-350 text-secondary foucs:ring-1 focus:ring-secondary">
                        </div>
                    </div>
                </div>
            </div>

            <div class="screen screen-2 <?= $current_create_screen === 2 ? 'active' : ''; ?>">
                <div class="flex justify-between items-center">
                    <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '2. Select Your Information', 'instawp-connect' ); ?></div>
                </div>
                <div class="panel mt-6 block">
					<?php foreach ( $customize_options as $customize_option ) : ?>
                        <div class="text-[16px] font-semibold mb-2"><?php echo esc_html( InstaWP_Setting::get_args_option( 'label', $customize_option ) ); ?></div>
                        <div class="grid grid-cols-3 gap-5 mb-4">
							<?php foreach ( InstaWP_Setting::get_args_option( 'options', $customize_option, array() ) as $option_id => $label ) : ?>
                                <!--relative flex items-start border border-secondary card-active p-3 px-4 rounded-lg-->
                                <label for="<?php echo esc_attr( $option_id ); ?>" class="relative flex items-start border border-grayCust-350 p-3 px-4 rounded-lg">
                                    <span>
                                        <input id="<?php echo esc_attr( $option_id ); ?>" name="migrate_settings[options][]" value="<?php echo esc_attr( $option_id ); ?>" type="checkbox" class="instawp-option-selector rounded border-gray-300 text-secondary focus:ring-secondary">
                                    </span>
                                    <span class="ml-2 text-sm">
                                        <span class="option-label font-medium text-sm"><?php echo esc_html( $label ); ?></span>
                                    </span>
                                </label>
							<?php endforeach; ?>
                        </div>
					<?php endforeach; ?>
                </div>
            </div>
            <div class="screen screen-3 <?= $current_create_screen === 3 ? 'active' : ''; ?>">
                <div class="flex justify-between items-center">
                    <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '3. Exclude', 'instawp-connect' ); ?></div>
                    <button type="button" class="instawp-refresh-exclude-screen">
                        <svg class="w-4 h-4" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" style="fill: #15B881;"
                                    d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"></path>
                        </svg>
                    </button>
                </div>
                <div class="panel mt-6 flex flex-col gap-6">
					<?php if ( ! empty( $list_data ) && is_array( $list_data ) ) { ?>
                        <div class="instawp-exclude-container">
                            <div class="bg-yellow-50 border border-2 border-r-0 border-y-0 border-l-orange-400 rounded-lg text-sm text-orange-700 p-4 flex flex-col items-start gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="text-sm font-medium"><?php esc_html_e( 'We have identified following large files in your installation:', 'instawp-connect' ); ?></div>
                                </div>
                                <div class="flex flex-col items-start gap-3 max-h-48 w-full overflow-auto">
									<?php foreach ( $list_data as $data ) {
										$element_id = wp_generate_uuid4(); ?>
                                        <div class="flex justify-between items-center text-xs">
                                            <input type="checkbox" name="migrate_settings[excluded_paths][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $data['relative_path'] ); ?>" class="instawp-checkbox exclude-file-item large-file !mt-0 !mr-3 rounded border-gray-300 text-secondary focus:ring-secondary" data-size="<?php echo esc_html( $data['size'] ); ?>" data-count="1">
                                            <label for="<?php echo esc_attr( $element_id ); ?>"><?php echo esc_html( $data['relative_path'] ); ?> (<?php echo esc_html( instawp()->get_file_size_with_unit( $data['size'] ) ); ?>)</label>
                                        </div>
									<?php } ?>
                                </div>
                            </div>
                        </div>
					<?php } else { ?>
                        <div class="instawp-exclude-container hidden"></div>
					<?php } ?>
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                        <div class="min-w-full divide-y divide-gray-300">
                            <div class="bg-gray-50 flex flex-row items-center justify-between p-4">
                                <div class="text-left text-sm font-medium text-grayCust-900">
                                    <span><?php esc_html_e( 'Files', 'instawp-connect' ); ?></span>
                                    <span class="instawp-files-details"></span>
                                </div>
                                <div class="flex flex-row items-center justify-between gap-5">
                                    <div class="text-left text-sm font-medium text-grayCust-900">
                                        <input type="checkbox" id="instawp-files-select-all" class="instawp-checkbox !mr-1 rounded border-gray-300 text-secondary focus:ring-secondary" disabled="disabled" style="margin-top: -2px;">
                                        <label for="instawp-files-select-all"><?php esc_html_e( 'Select All', 'instawp-connect' ); ?></label>
                                    </div>
                                    <div class="text-left text-sm font-medium text-secondary pointer-events-none flex flex-row items-center justify-between gap-1 cursor-pointer instawp-files-sort-by" data-sort="none">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M7 16V4M7 4L3 8M7 4L11 8M17 8V20M17 20L21 16M17 20L13 16" stroke="#15B881" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
										<?php esc_html_e( 'Size', 'instawp-connect' ); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="expand-files-list text-center cursor-pointer text-secondary p-4 hidden">
                                <svg width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline mr-1">
                                    <path d="M4.75504 4.09984L5.74004 3.11484L7.34504 1.50984C7.68004 1.16984 7.44004 0.589844 6.96004 0.589844L3.84504 0.589844L1.04004 0.589843C0.560037 0.589843 0.320036 1.16984 0.660037 1.50984L3.25004 4.09984C3.66004 4.51484 4.34004 4.51484 4.75504 4.09984Z" fill="#4F4F4F"></path>
                                </svg>
                                <span><?php esc_html_e( 'Expand', 'instawp-connect' ); ?></span>
                            </div>
                            <div class="overflow-auto exclude-files-container">
                                <div class="loading"></div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                        <div class="min-w-full divide-y divide-gray-300">
                            <div class="bg-gray-50 flex flex-row items-center justify-between p-4">
                                <div class="text-left text-sm font-medium text-grayCust-900">
                                    <span><?php esc_html_e( 'Tables', 'instawp-connect' ); ?></span>
                                    <span class="instawp-database-details">
                                        <?php if ( ! empty( $tables ) ) {
	                                        $table_count = count( $tables );
	                                        $table_size  = array_sum( wp_list_pluck( $tables, 'size' ) );
	                                        echo '(' . esc_html( $table_count ) . ') - ' . esc_html( instawp()->get_file_size_with_unit( $table_size ) );
                                        } ?>
                                    </span>
                                </div>
                                <div class="flex flex-row items-center justify-between gap-5">
                                    <div class="text-left text-sm font-medium text-grayCust-900">
                                        <input type="checkbox" id="instawp-database-select-all" class="instawp-checkbox !mr-1 rounded border-gray-300 text-secondary focus:ring-secondary" style="margin-top: -2px;">
                                        <label for="instawp-database-select-all"><?php esc_html_e( 'Select All', 'instawp-connect' ); ?></label>
                                    </div>
                                    <div class="text-left text-sm font-medium text-secondary flex flex-row items-center justify-between gap-1 cursor-pointer instawp-database-sort-by" data-sort="none">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M7 16V4M7 4L3 8M7 4L11 8M17 8V20M17 20L21 16M17 20L13 16" stroke="#15B881" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
										<?php esc_html_e( 'Size', 'instawp-connect' ); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="expand-database-list text-center cursor-pointer text-secondary p-4">
                                <svg width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline mr-1">
                                    <path d="M4.75504 4.09984L5.74004 3.11484L7.34504 1.50984C7.68004 1.16984 7.44004 0.589844 6.96004 0.589844L3.84504 0.589844L1.04004 0.589843C0.560037 0.589843 0.320036 1.16984 0.660037 1.50984L3.25004 4.09984C3.66004 4.51484 4.34004 4.51484 4.75504 4.09984Z" fill="#4F4F4F"></path>
                                </svg>
                                <span><?php esc_html_e( 'Expand', 'instawp-connect' ); ?></span>
                            </div>
                            <div class="overflow-auto exclude-database-container p-4 h-80 hidden">
								<?php if ( ! empty( $tables ) ) { ?>
                                    <div class="flex flex-col gap-5">
										<?php foreach ( $tables as $table ) {
											$element_id = wp_generate_uuid4(); ?>
                                            <div class="flex flex-col gap-5 item">
                                                <div class="flex justify-between items-center">
                                                    <div class="flex items-center cursor-pointer" style="transform: translate(0em);">
                                                        <input name="migrate_settings[excluded_tables][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $table['name'] ); ?>" type="checkbox" class="instawp-checkbox exclude-database-item !mt-0 !mr-3 rounded border-gray-300 text-secondary focus:ring-secondary <?= in_array( $table['name'], $log_tables_to_exclude ) ? 'log-table' : ''; ?>" data-size="<?php echo esc_html( $table['size'] ); ?>">
                                                        <label for="<?php echo esc_attr( $element_id ); ?>" class="text-sm font-medium text-grayCust-800 truncate" style="width: calc(400px - 1em);"><?php echo esc_html( $table['name'] ); ?> (<?php printf( esc_html__( '%s rows', 'instawp-connect' ), esc_html( $table['rows'] ) ); ?>)</label>
                                                    </div>
                                                    <div class="flex items-center" style="width: 105px;">
                                                        <svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M2.33333 6.49984H11.6667M2.33333 6.49984C1.59695 6.49984 1 5.90288 1 5.1665V2.49984C1 1.76346 1.59695 1.1665 2.33333 1.1665H11.6667C12.403 1.1665 13 1.76346 13 2.49984V5.1665C13 5.90288 12.403 6.49984 11.6667 6.49984M2.33333 6.49984C1.59695 6.49984 1 7.09679 1 7.83317V10.4998C1 11.2362 1.59695 11.8332 2.33333 11.8332H11.6667C12.403 11.8332 13 11.2362 13 10.4998V7.83317C13 7.09679 12.403 6.49984 11.6667 6.49984M10.3333 3.83317H10.34M10.3333 9.1665H10.34" stroke="#111827" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                        <div class="text-sm font-medium text-grayCust-800 ml-2"><?php echo esc_html( instawp()->get_file_size_with_unit( $table['size'] ) ); ?></div>
                                                    </div>
                                                </div>
                                            </div>
										<?php } ?>
                                    </div>
								<?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="screen screen-4 <?= $current_create_screen === 4 ? 'active' : ''; ?>">
                <div class="confirmation-preview">
                    <div class="flex justify-between items-center">
                        <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '4. Confirmation', 'instawp-connect' ); ?></div>
                    </div>
                    <div class="panel mt-6 flex flex-col gap-6">
                        <div class="flex items-start">
                            <div class="text-grayCust-900 text-base font-normal mr-4 basis-1/5"><?php esc_html_e( 'Staging Type', 'instawp-connect' ); ?></div>
                            <div class="text-grayCust-300 text-base font-medium items-center flex mr-6 selected-staging-type basis-4/5"><?php esc_html_e( 'Quick Staging', 'instawp-connect' ); ?></div>
                        </div>
                        <div class="flex items-start options-select hidden">
                            <div class="text-grayCust-900 text-base font-normal mr-4 basis-1/5"><?php esc_html_e( 'Options Selected', 'instawp-connect' ); ?></div>
                            <div class="grid grid-cols-3 gap-3 selected-staging-options basis-4/5"></div>
                        </div>
                        <div class="flex items-center files-select hidden">
                            <div class="text-grayCust-900 text-base font-normal mr-4 basis-1/5"><?php esc_html_e( 'Files Selected', 'instawp-connect' ); ?></div>
                            <div class="text-grayCust-300 text-base font-medium items-center flex mr-6 selected-files basis-4/5"></div>
                        </div>
                        <div class="flex items-center db-tables-select hidden">
                            <div class="text-grayCust-900 text-base font-normal mr-4 basis-1/5"><?php esc_html_e( 'Tables Selected', 'instawp-connect' ); ?></div>
                            <div class="text-grayCust-300 text-base font-medium items-center flex mr-6 selected-db-tables basis-4/5"></div>
                        </div>
                        <?php if ( ! empty( $site_plans ) ) { ?>
                            <div class="flex items-start">
                                <div class="text-grayCust-900 text-base font-normal mr-4 basis-1/5 flex items-center gap-1">
                                    <span><?php esc_html_e( 'Select Plan', 'instawp-connect' ); ?></span>
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="mt-0.5 hidden">
                                        <path d="M2.66699 2.66699V6.00033H3.05467M13.2924 7.33366C12.9643 4.70278 10.7201 2.66699 8.00033 2.66699C5.76207 2.66699 3.84585 4.04577 3.05467 6.00033M3.05467 6.00033H6.00033M13.3337 13.3337V10.0003H12.946M12.946 10.0003C12.1548 11.9549 10.2386 13.3337 8.00033 13.3337C5.28058 13.3337 3.03632 11.2979 2.70825 8.66699M12.946 10.0003H10.0003" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="flex flex-col gap-3 w-full basis-4/5">
                                    <?php foreach ( $site_plans as $key => $site_plan ) { ?>
                                        <label class="w-full cursor-pointer relative">
                                            <input type="radio" name="migrate_settings[plan_id]" id="staging-plan-<?php echo esc_attr( $key + 1 ); ?>" value="<?php echo esc_attr( $site_plan['id'] ); ?>" class="peer !hidden" <?php checked( $site_plan['name'], 'free' ); ?> />
                                            <div class="border pl-10 pr-4 font-medium rounded-lg flex items-center justify-between w-full cursor-pointer peer-disabled:opacity-50 peer-disabled:cursor-not-allowed peer-checked:border-secondary peer-checked:bg-teal-900 peer-checked:bg-opacity-5">
                                                <div class="flex items-center gap-2 w-full py-2 cursor-pointer">
                                                    <span><?php echo esc_html( $site_plan['display_name'] ); ?></span>
                                                    <?php if ( ! empty( $site_plan['description'] ) ) { ?>
                                                        <span class="text-blue-800 text-xs font-medium bg-blue-50 px-2 py-1 rounded-md truncate"><?php echo esc_html( $site_plan['description'] ); ?></span>
                                                    <?php } ?>
                                                </div>
                                                <div class="font-medium whitespace-nowrap">
                                                    <?php if ( $site_plan['name'] === 'free' ) { ?>
                                                        <?php echo esc_html( $site_plan['rate']['monthly'] ); ?><span class="text-xs text-gray-500 font-light">/mo</span>
                                                    <?php } else { ?>
                                                        <?php echo esc_html( $site_plan['rate']['monthly'] ); ?><span class="text-xs text-gray-500 font-light">/mo - <?php echo esc_html( $site_plan['rate']['daily'] ); ?>/day</span>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="absolute left-4 top-1/2 transform -translate-y-1/2 w-4 h-4 rounded-full peer-checked:border-secondary peer-checked:border-4 border flex items-center justify-center transition-colors bg-white"></div>
                                        </label>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="confirmation-warning hidden text-center px-24 py-8">
                    <div class="mb-2 flex justify-center text-center"><img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/warning.svg' ) ); ?>" alt="Warning"></div>
                    <div class="mb-2 text-graCust-300 text-lg font-medium warning-title staging-type-label"><?php esc_html_e( 'You have reached your limit', 'instawp-connect' ); ?></div>
                    <div class="mb-2 text-gray-500 text-sm font-normal leading-6 warning-subtitle"><?php esc_html_e( 'You have exceeded the maximum allowance of your plan.', 'instawp-connect' ); ?></div>
                    <div class="p-6 custom-bg rounded-lg warning-details border my-6 text-left">
                        <div class="flex items-center mb-6">
                            <div class="text-grayCust-900 text-base text-left font-normal w-48"><?php esc_html_e( 'Remaining Sites', 'instawp-connect' ); ?></div>
                            <div class="flex items-center text-secondary text-base">
                                <span class="remaining-site"></span>
                                <span>/</span>
                                <span class="user-allow-site"></span>
                            </div>
                        </div>
                        <div class="flex items-center mb-6">
                            <div class="text-grayCust-900 text-base text-left font-normal w-48"><?php esc_html_e( 'Available Disk Space', 'instawp-connect' ); ?></div>
                            <div class="flex items-center text-secondary text-base">
                                <span><span class="remaining-disk-space"></span>mb available out of <span class="user-allow-disk-space"></span>mb</span>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="text-grayCust-900 text-base text-left font-normal w-48"><?php esc_html_e( 'Require Disk Space', 'instawp-connect' ); ?></div>
                            <div class="flex items-center text-secondary text-base">
                                <span class="require-disk-space"></span>
                                <span class="ml-1"><?php esc_html_e( 'MB', 'instawp-connect' ); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex text-center gap-4 items-center justify-center">
                        <button type="button" class="instawp-migration-start-over text-gray-700 py-3 px-6 border border-grayCust-350 text-sm font-medium rounded-md"><?php esc_html_e( 'Start Over', 'instawp-connect' ); ?></button>
                        <a href="#" target="_blank" class="shadow-sm rounded-md w-fit text-center py-3 px-6 bg-secondary text-white hover:text-white text-sm font-medium" style="background: #11BF85;"><?php esc_html_e( 'Increase Limit', 'instawp-connect' ); ?></a>
                    </div>

                    <div class="p-4 mt-6 text-sm text-left w-full text-red-800 rounded-lg bg-red-50" role="alert">
						<?php printf( '<span>%s</span><a class="font-medium underline ml-2" href="%s">%s</a>',
							esc_html__( 'Provide more information to help you with this migration.', 'instawp-connect' ),
							esc_url( 'https://instawp.com/migration-help-query/' ),
							esc_html__( 'Get Help', 'instawp-connect' )
						); ?>
                    </div>

                </div>
            </div>
            <div class="screen screen-5 <?= $current_create_screen === 5 ? 'active' : ''; ?>">
                <div class="flex justify-between items-center">
                    <div class="text-grayCust-200 text-lg font-bold"><?php esc_html_e( '4. Creating Staging', 'instawp-connect' ); ?></div>
                    <span class="instawp-migration-loader text-secondary text-base font-normal"
                            data-in-progress-text="<?php esc_attr_e( 'In Progress...', 'instawp-connect' ); ?>"
                            data-error-text="<?php esc_attr_e( 'Migration Failed', 'instawp-connect' ); ?>"
                            data-complete-text="<?php esc_attr_e( 'Completed', 'instawp-connect' ); ?>">
                        <?php esc_html_e( 'In Progress...', 'instawp-connect' ); ?>
                    </span>
                </div>
                <div class="panel mt-6 block">
                    <div class="migration-running border border-grayCust-100 rounded-lg">

                        <div class="<?php echo esc_attr( $serve_with_wp ? '' : 'hidden' ); ?> notice-serve-with-wp p-5 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 gap-4" role="alert">
							<?php printf( '<span class="font-medium">%s</span><span>%s</span>',
								esc_html__( 'Using slow mode: ', 'instawp-connect' ),
								esc_html__( 'We’re currently experiencing access restrictions, so the migration process may take longer than usual.', 'instawp-connect' )
							); ?>
                        </div>

                        <div class="p-5 flex flex-col gap-4">
                            <div class="flex items-center">
                                <div class="w-24 text-grayCust-900 text-base font-normal"><?php esc_html_e( 'Files', 'instawp-connect' ); ?></div>
                                <div class="instawp-progress-files text-border rounded-xl w-full text-bg py-4 flex items-center px-4">
                                    <div class="w-full bg-gray-200 rounded-md mr-6">
                                        <div class="instawp-progress-bar h-2 bg-secondary rounded-md"></div>
                                    </div>
                                    <div class="progress-text text-grayCust-650 text-sm font-medium"><?php esc_html_e( '0%', 'instawp-connect' ); ?></div>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="w-24 text-grayCust-900 text-base font-normal"><?php esc_html_e( 'Database', 'instawp-connect' ); ?></div>
                                <div class="instawp-progress-db text-border rounded-xl w-full text-bg py-4 flex items-center px-4">
                                    <div class="w-full bg-gray-200 rounded-md mr-6">
                                        <div class="instawp-progress-bar h-2 bg-secondary rounded-md"></div>
                                    </div>
                                    <div class="progress-text text-grayCust-650 text-sm font-medium"><?php esc_html_e( '0%', 'instawp-connect' ); ?></div>
                                </div>
                            </div>
                            <div id="visibility-box" class="flex flex-col gap-2 rounded-xl w-full bg-zinc-800 border border-zinc-700 p-2">
                                <div class="flex sticky top-0 rounded-lg justify-between p-1">
                                    <div class="flex items-center text-gray-100 font-medium gap-2">
                                        <svg width="16" height="16" fill="none" class="animate-spin flex-shrink-0" xmlns="http://www.w3.org/2000/svg" aria-label="In progress">
                                            <path opacity=".5" d="M8 15A7 7 0 108 1a7 7 0 000 14v0z" stroke="#DBAB0A" stroke-width="2"></path>
                                            <path d="M15 8a7 7 0 01-7 7" stroke="#DBAB0A" stroke-width="2"></path>
                                            <path d="M8 12a4 4 0 100-8 4 4 0 000 8z" fill="#DBAB0A"></path>
                                        </svg>
                                        <span class="stage stage-processing"><?php printf( esc_html__( 'Processing (0/%s stages)', 'instawp-connect' ), count( InstaWP_Setting::get_stages() ) ); ?></span>
										<?php
										$index = 1;
										foreach ( InstaWP_Setting::get_stages() as $stage_key => $label ) { ?>
                                            <span class="stage stage-<?= esc_attr( $stage_key ) ?> hidden"><?= esc_html( $label ); ?><?php printf( esc_html__( ' (%1$s/%2$s stages)', 'instawp-connect' ), esc_html( $index ), count( InstaWP_Setting::get_stages() ) ); ?></span>
											<?php
											++ $index;
										} ?>
                                    </div>
                                    <div class="text-gray-100 font-medium flex gap-3">
                                        <span id="visibility-timer" class="text-gray-100 font-medium"></span>
                                        <span id="visibility-collapse" class="cursor-pointer hidden">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M4.16634 12.5L9.99967 6.66667L15.833 12.5" stroke="#FAFAFA" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <span id="visibility-expand" class="cursor-pointer">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M15.8332 7.5L9.99984 13.3333L4.1665 7.5" stroke="#FAFAFA" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                                <div id="visibility-box-area" class="flex flex-col relative rounded-lg bg-black max-h-[300px] overflow-hidden hidden">
                                    <div id="visibility-content-area" class="flex flex-col overflow-auto p-2">
                                        <div class="visibility-content-item flex gap-3 items-center hover:bg-zinc-800 hover:rounded-lg py-1.5 px-2.5 "><span class="text-gray-100 min-w-36"><?= esc_html( wp_date( 'Y-m-d H:i:s' ) ); ?></span><span class="text-gray-100 break-all font-medium"><?= esc_html__( 'Migration Initiated', 'instawp-connect' ); ?></span></div>
                                        <!--                                        --><?php //foreach( range( 0, 10 ) as $index ) { ?>
                                        <!--                                            <div class="visibility-content-item flex gap-3 items-center hover:bg-zinc-800 hover:rounded-lg py-1.5 px-2.5 group skipped">-->
                                        <!--                                                <span class="text-gray-100 min-w-36">--><?php //= wp_date( 'Y-m-d H:i:s' ); ?><!--</span>-->
                                        <!--                                                <span class="text-gray-100 break-all group-[.sent]:text-emerald-300 group-[.failed]:text-rose-500 group-[.skipped]:text-yellow-300">wp_config/matomo/app/plugins/file.php - 5 mb <span class="hidden group-hover:inline-block ml-2 px-2 py-1 text-xs rounded-lg border border-zinc-700 text-rose-500 cursor-pointer instawp-skip-item" data-type="file" data-item="5">Skip</span></span>-->
                                        <!--                                            </div>-->
                                        <!--                                        --><?php //} ?>
                                    </div>
                                    <div class="absolute bg-zinc-800 border border-zinc-700 p-1.5 bottom-3 right-3 rounded-sm cursor-pointer full-screen-btn">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M2 14V11.3333M2 14H4.66667M2 14L6 10M14 2H11.3333M14 2V4.66667M14 2L10 6M2 2V4.66667M2 2H4.66667M2 2L6 6M14 14H11.3333M14 14V11.3333M14 14L10 10" stroke="white" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="instawp-track-migration-area bg-grayCust-250 px-5 py-4 rounded-bl-lg rounded-br-lg flex content-center items-center <?= empty( $tracking_url ) ? 'justify-end' : 'justify-between' ?>">
                            <a class="instawp-track-migration text-secondary hover:text-secondary focus:ring-0 text-sm text-left flex items-center <?= empty( $tracking_url ) ? 'hidden' : '' ?>" href="<?php echo esc_url( $tracking_url ); ?>" target="_blank">
                                <span class="mr-2"><?php esc_html_e( 'Track Migration', 'instawp-connect' ); ?></span>
                                <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/share-icon.svg' ) ); ?>" class="inline ml-1" alt="">
                            </a>
                            <button type="button" class="instawp-migrate-abort shadow-sm border border-grayCust-350 rounded-md py-2 px-8 bg-white text-sm font-medium text-red-400"><?php esc_html_e( 'Abort', 'instawp-connect' ); ?></button>
                        </div>
                    </div>
                    <div class="migration-completed hidden border border-grayCust-100 rounded-lg">
                        <div class="p-6 border-b border-grayCust-10 flex items-center justify-center text-lg font-medium text-grayCust-800">
                            <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/check-icon.png' ) ); ?>" class="mr-2" alt=""><?php esc_html_e( 'Your Staging Site is ready!', 'instawp-connect' ); ?>
                        </div>
                        <div class="p-6 custom-bg">
                            <div class="flex items-center mb-6">
                                <div class="text-grayCust-900 text-base font-normal w-24"><?php esc_html_e( 'URL', 'instawp-connect' ); ?></div>
                                <div class="flex items-center cursor-pointer text-secondary border-b font-medium text-base border-dashed border-secondary ">
                                    <a target="_blank" id="instawp-site-url" class="focus:shadow-none focus:outline-0">
                                        <span></span>
                                        <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/share-icon.svg' ) ); ?>" class="inline ml-1" alt="">
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
                                    <div aria-label="<?php echo esc_attr__( 'This password is same as production, due to security reasons, we don\'t know or keep the plain-text password', 'instawp-connect' ); ?>" id="instawp-site-password" class="hint--top hint--medium text-grayCust-300 font-medium text-base"></div>
                                </div>
                                <a href="" target="_blank" id="instawp-site-magic-url" class="py-2 px-4 text-white active:text-white focus:text-white hover:text-white bg-primary-700 rounded-md text-sm font-medium focus:shadow-none flex items-center justify-center gap-2">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M13.3337 16.668L14.167 16.668C15.5477 16.668 16.667 15.5487 16.667 14.168L16.667 5.83464C16.667 4.45392 15.5477 3.33464 14.167 3.33464L13.3337 3.33464M10.0003 6.66797L13.3337 10.0013M13.3337 10.0013L10.0003 13.3346M13.3337 10.0013L3.33366 10.0013" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
									<?php esc_html_e( 'Magic Login', 'instawp-connect' ); ?>
                                </a>
                            </div>
                        </div>
                        <div class="instawp-rate-plugin m-4 p-4 text-sm text-blue-800 rounded-lg bg-blue-50 flex items-center" role="alert">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                                <path fill-rule="evenodd" d="M16.403 12.652a3 3 0 0 0 0-5.304 3 3 0 0 0-3.75-3.751 3 3 0 0 0-5.305 0 3 3 0 0 0-3.751 3.75 3 3 0 0 0 0 5.305 3 3 0 0 0 3.75 3.751 3 3 0 0 0 5.305 0 3 3 0 0 0 3.751-3.75Zm-2.546-4.46a.75.75 0 0 0-1.214-.883l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/>
                            </svg>
                            <span class="ml-1"><?php esc_html_e( 'We hope you are enjoying InstaWP.', 'instawp-connect' ); ?><a class="ml-2 underline focus:outline-none focus:shadow-none focus:text-blue-800" target="_blank" href="<?php echo esc_url_raw( 'https://wordpress.org/support/plugin/instawp-connect/reviews/?filter=5#new-post' ); ?>"><?php esc_html_e( 'Rate us on WordPress.org', 'instawp-connect' ); ?></a></span>
                        </div>
                    </div>
                    <div class="migration-error hidden">
                        <div class="relative bg-white rounded-lg border border-red-200 text-red-800 rounded-lg bg-red-50 py-4">
                            <div class="p-6 text-center">
                                <svg class="mx-auto mb-4 text-red-700 w-10 h-10" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 11V6m0 8h.01M19 10a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                                <p class="error-message text-[16px] font-normal text-red-700"></p>
                                <button data-migrate-id="" data-server-logs="" type="button" class="instawp-download-log mt-4 px-2 py-1 text-xs font-medium text-center inline-flex items-center text-orange-700 bg-orange-100 rounded-[3px] focus:outline-none focus:ring-0">Download Error Log</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex items-center justify-center hidden payment-method-warning border-t">
            <div class="bg-yellow-50 text-yellow-700 p-4 text-left w-full" role="alert">
                <div class="flex justify-between items-center transition-all duration-300">
                    <div class="flex gap-3 justify-center">
                        <svg width="16" height="14" viewBox="0 0 16 14" fill="none" xmlns="http://www.w3.org/2000/svg" class="mt-1">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.25706 1.09858C7.02167 -0.260724 8.97875 -0.260725 9.74336 1.09858L15.3237 11.0191C16.0736 12.3523 15.1102 13.9996 13.5805 13.9996H2.4199C0.890251 13.9996 -0.0731769 12.3523 0.676753 11.0191L6.25706 1.09858ZM9.00012 10.9998C9.00012 11.552 8.55241 11.9998 8.00012 11.9998C7.44784 11.9998 7.00012 11.552 7.00012 10.9998C7.00012 10.4475 7.44784 9.99976 8.00012 9.99976C8.55241 9.99976 9.00012 10.4475 9.00012 10.9998ZM8.00012 2.99976C7.44784 2.99976 7.00012 3.44747 7.00012 3.99976V6.99976C7.00012 7.55204 7.44784 7.99976 8.00012 7.99976C8.55241 7.99976 9.00012 7.55204 9.00012 6.99976V3.99976C9.00012 3.44747 8.55241 2.99976 8.00012 2.99976Z" fill="#FBBF24"/>
                        </svg>
                        <div class="flex flex-col gap-1 text-sm leading-5">
                            <div class="font-medium text-yellow-800">Add Credit Card First!</div>
                            <div class="font-normal text-yellow-700">You need to add your credit card first to create site.</div>
                        </div>
                    </div>
                    <div class="font-medium text-yellow-800 py-2 cursor-pointer instawp-add-credit-card">Add Credit Card</div>
                </div>
            </div>
        </div>

        <div class="screen-buttons-last hidden border-t bg-grayCust-250 px-5 py-4 rounded-bl-lg rounded-br-lg flex justify-between">
            <a href="<?php esc_url( admin_url( 'tools.php?page=instawp' ) ); ?>" class="text-secondary text-sm focus:outline-0 focus:shadow-none font-medium cursor-pointer flex items-center instawp-create-another-site">
                <span class="text-xl mr-1 -mt-1 self-center">+</span>
                <span><?php esc_html_e( 'Create another Staging Site', 'instawp-connect' ); ?></span>
            </a>
            <div class="text-grayCust-900 text-sm font-medium cursor-pointer flex items-center instawp-show-staging-sites">
                <span><?php esc_html_e( 'Show my staging sites', 'instawp-connect' ); ?></span>
                <div class="flex items-center ml-2">
                    <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/right-icon.svg' ) ); ?>" alt="">
                    <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/right-icon.svg' ) ); ?>" alt="">
                </div>
            </div>
        </div>

        <div class="screen-buttons border-t <?php echo esc_attr( ! empty( $migrate_id ) ? 'hidden' : '' ); ?> bg-grayCust-250 px-5 py-4 rounded-bl-lg rounded-br-lg flex justify-between">
            <div class="instawp-site-name flex items-center focus-visible:outline-none cursor-pointer hint--top hint--rounded" aria-label="<?= esc_attr__( 'Leave blank for Auto Generated name', 'instawp-connect' ) ?>" style="max-width: 350px;">
                <div class="focus-visible:outline-none">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" class="size-4">
                        <path d="M1 5H13M5 5L5 13M4.2 1H9.8C10.9201 1 11.4802 1 11.908 1.21799C12.2843 1.40973 12.5903 1.71569 12.782 2.09202C13 2.51984 13 3.0799 13 4.2V9.8C13 10.9201 13 11.4802 12.782 11.908C12.5903 12.2843 12.2843 12.5903 11.908 12.782C11.4802 13 10.9201 13 9.8 13H4.2C3.07989 13 2.51984 13 2.09202 12.782C1.71569 12.5903 1.40973 12.2843 1.21799 11.908C1 11.4802 1 10.9201 1 9.8V4.2C1 3.07989 1 2.51984 1.21799 2.09202C1.40973 1.71569 1.71569 1.40973 2.09202 1.21799C2.51984 1 3.0799 1 4.2 1Z" stroke="#374151" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="flex items-center ml-2 focus-visible:outline-none">
                    <div class="flex items-center focus-visible:outline-none placeholder-text">
                        <p class="truncate cursor-pointer text-sm hover:border-secondary border-b border-transparent focus-visible:outline-none" data-text="<?= esc_attr__( 'Enter Site Name', 'instawp-connect' ) ?>"><?= esc_html__( 'Enter Site Name', 'instawp-connect' ) ?></p>
                    </div>
                    <div class="focus-visible:outline-none site-name-input-wrap hidden">
                        <input id="site-prefix" name="migrate_settings[site_name]" data-postfix="" class="w-44 border-b-[1px] border-secondary focus-visible:outline-none bg-transparent" placeholder="<?= esc_attr__( 'Enter Site Name (max 16 characters)', 'instawp-connect' ) ?>" autocomplete="off" maxlength="16">
                    </div>
                </div>
            </div>
            <p class="doing-request"><span class="loader"></span><?php esc_html_e( 'Checking usages...', 'instawp-connect' ); ?></p>
            <input name="migrate_settings[screen]" type="hidden" id="instawp-screen" value="<?= esc_attr( $current_create_screen ); ?>">
            <div class="button-group">
                <button type="button" data-increment="-1" class="instawp-button-migrate back hidden shadow-sm border border-grayCust-350 mr-4 rounded-md py-2 px-8 bg-white text-sm font-medium"><?php esc_html_e( 'Back', 'instawp-connect' ); ?></button>
                <button type="button" data-increment="1" class="instawp-button-migrate continue shadow-sm border border-secondary rounded-md py-2 px-4 bg-secondary text-white hover:text-white text-sm font-medium"><?php esc_html_e( 'Next Step', 'instawp-connect' ); ?></button>
            </div>
        </div>
    </div>
</div>