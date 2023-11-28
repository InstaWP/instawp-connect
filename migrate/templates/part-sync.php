<?php
/**
 * Migrate template - Sync
 */

$changeEvent         = new InstaWP_Sync_Admin();
$events              = $changeEvent->listEvents();
$syncing_status      = InstaWP_Setting::get_option( 'instawp_is_event_syncing' );
$syncing_status_val  = ( $syncing_status == 1 ) ? 'checked' : '';
$parent_connect_data = InstaWP_Setting::get_option( 'instawp_sync_parent_connect_data' );
$staging_sites       = [];

if ( ! empty( $parent_connect_data ) ) {
    if ( ! array_key_exists( 'url', $parent_connect_data ) ) {
	    $parent_connect_data['url'] = InstaWP_Setting::get_args_option( 'domain', $parent_connect_data, '' );
    }
    if ( ! array_key_exists( 'connect_id', $parent_connect_data ) ) {
	    $parent_connect_data['connect_id'] = InstaWP_Setting::get_args_option( 'id', $parent_connect_data, '' );
    }

	$staging_sites[] = $parent_connect_data;
} else {
	$staging_sites = instawp_get_staging_sites_list();
}

?>
<div class="nav-item-content sync bg-white rounded-md p-6">
	<?php if ( empty( $events ) ) : ?>
        <div class="data-listening">
            <!--        <div class="bg-white  box-shadow rounded-md data-padding flex items-center justify-center">-->
            <div class="w-full">
                <div class="text-center ">
                    <div class="mb-4">
                        <svg width="38" class="mx-auto" height="30" viewBox="0 0 38 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 17H25H13ZM19 11V23V11ZM1 25V5C1 3.93913 1.42143 2.92172 2.17157 2.17157C2.92172 1.42143 3.93913 1 5 1H17L21 5H33C34.0609 5 35.0783 5.42143 35.8284 6.17157C36.5786 6.92172 37 7.93913 37 9V25C37 26.0609 36.5786 27.0783 35.8284 27.8284C35.0783 28.5786 34.0609 29 33 29H5C3.93913 29 2.92172 28.5786 2.17157 27.8284C1.42143 27.0783 1 26.0609 1 25Z" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="text-sm font-medium text-grayCust-200 mb-1"><?php echo esc_html__( 'No Data found!', 'instawp-connect' ); ?></div>
                    <div class="text-sm font-normal text-grayCust-50"><?php echo esc_html__( 'Start Listening for Changes', 'instawp-connect' ); ?></div>
                    <div class="instawp_is_event_syncing">
                        <label class="toggle-control">
                            <input type="checkbox" <?php echo $syncing_status_val; ?> name="instwawp_is_event_syncing" id="instwawp_is_event_syncing" class="toggle-checkbox">
                            <div class="toggle-switch"></div>
                            <span class="toggle-label" data-on="1" data-off="0"></span>
                        </label>
                    </div>
                </div>
            </div>
            <!--        </div>-->
        </div>
	<?php else : ?>
        <div class="sync-listining1">
            <!--        <div class="bg-white  box-shadow rounded-md p-6 flex items-center justify-center">-->
            <div class="w-full">
                <div class="events-head">
                    <div class="events-head-left flex items-baseline">
                        <div class="text-grayCust-200 text-lg font-medium"><?php echo esc_html__( 'Listening for Changes', 'instawp-connect' ); ?></div>
                        <label class="switch-toggle instawp_is_event_syncing">
                            <input type="checkbox" <?php echo $syncing_status_val; ?>>
                            <span class="slider-toggle round-toggle"></span>
                        </label>
                    </div>
                    <div class="events-head-right">
                        <div class="button-ct flex ml-2.5">
                            <button type="button" class="bulk-sync-popup-btn text-white bg-[#005e54] font-medium py-2 px-3 rounded text-center inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" width="14" height="16" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" style="fill: #fff;"
                                          d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"/>
                                </svg>
                                <span><?php echo esc_html__( 'Sync', 'instawp-connect' ); ?></span>
                            </button>
                            <button type="button" class="bg-white py-2 px-2 border rounded shadow instawp-refresh-events">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="14" height="16" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" clip-rule="evenodd" style="fill: #005e54;" d="M13.5 2c-5.629 0-10.212 4.436-10.475 10h-3.025l4.537 5.917 4.463-5.917h-2.975c.26-3.902 3.508-7 7.475-7 4.136 0 7.5 3.364 7.5 7.5s-3.364 7.5-7.5 7.5c-2.381 0-4.502-1.119-5.876-2.854l-1.847 2.449c1.919 2.088 4.664 3.405 7.723 3.405 5.798 0 10.5-4.702 10.5-10.5s-4.702-10.5-10.5-10.5z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="select-ct <?php echo empty( $staging_sites ) ? 'hidden' : '' ?>">
                            <select id="staging-site-sync" data-page="instawp">
								<?php foreach ( $staging_sites as $site ): ?>
									<?php if ( isset( $site['url'] ) && isset( $site['connect_id'] ) ): ?>
                                        <option value="<?php echo esc_attr( $site['connect_id'] ) ?>"><?php echo esc_html( $site['url'] ); ?></option>
									<?php endif ?>
								<?php endforeach ?>
                            </select>
                        </div>
                        <button type="button" id="instawp-delete-events" class="bg-white hover:bg-red-100 text-red-400 py-2 px-2 border border-red-400 rounded shadow mr-3 hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="mt-8 flow-root">
                    <div class="-my-2 -mx-6 overflow-x-auto lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                                <form id="event-form" method="POST">
                                    <table class="min-w-full divide-y divide-gray-300">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-4  uppercase text-left text-sm font-medium text-grayCust-900 w-0.5"><input type="checkbox" name="select_all_event" id="select-all-event"></th>
                                            <th scope="col" class="px-6 py-4  uppercase text-left text-sm font-medium text-grayCust-900"><?php echo esc_html__( 'event', 'instawp-connect' ); ?></th>
                                            <th scope="col" class="px-6 py-4 text-left uppercase text-sm font-medium text-grayCust-900"><?php echo esc_html__( 'event details', 'instawp-connect' ); ?></th>
                                            <th scope="col" class="px-6 py-4 text-left uppercase text-sm font-medium text-grayCust-900"><?php echo esc_html__( 'Date', 'instawp-connect' ); ?></th>
                                            <th scope="col" class="px-6 py-4 text-center uppercase text-sm font-medium text-grayCust-900"><?php echo esc_html__( 'Status', 'instawp-connect' ); ?></th>
                                            <!-- <th scope="col" class="px-6 py-4 text-center uppercase text-sm font-medium text-grayCust-900"><?php echo esc_html__( 'Actions', 'instawp-connect' ); ?></th> -->
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white" id="part-sync-results">
                                        <tr>
                                            <td colspan="4" class="event-sync-cell loading"></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </form>
                            </div>
                        </div>
                        <!-- pagination -->
                        <nav class="flex items-center justify-between border-t border-gray-200 mx-9 my-5">
                            <div id="event-sync-pagination">

                            </div>
                            <!-- <div class="-mt-px flex w-0 flex-1">
                                    <a href="#" class="inline-flex items-center border-t-2 border-transparent pt-3 pr-1 text-sm font-medium text-grayCust-300 hover:border-gray-300 hover:text-gray-700">
                                       
                                        <svg class="mr-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#1F2937" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M18 10a.75.75 0 01-.75.75H4.66l2.1 1.95a.75.75 0 11-1.02 1.1l-3.5-3.25a.75.75 0 010-1.1l3.5-3.25a.75.75 0 111.02 1.1l-2.1 1.95h12.59A.75.75 0 0118 10z" clip-rule="evenodd" />
                                        </svg>
                                        <?php echo esc_html__( 'Previous', 'instawp-connect' ); ?>
                                    </a>
                                </div>
                                <div class="-mt-px flex w-0 flex-1 justify-end">
                                    <a href="#" class="inline-flex items-center border-t-2 border-transparent pt-3 pl-1 text-sm font-medium text-grayCust-300 hover:border-gray-300 hover:text-gray-700">
                                        <?php echo esc_html__( 'Next', 'instawp-connect' ); ?>
                                        
                                        <svg class="ml-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#1F2937" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M2 10a.75.75 0 01.75-.75h12.59l-2.1-1.95a.75.75 0 111.02-1.1l3.5 3.25a.75.75 0 010 1.1l-3.5 3.25a.75.75 0 11-1.02-1.1l2.1-1.95H2.75A.75.75 0 012 10z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                </div> -->
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <div class="bulk-sync-popup" data-sync-type="">
            <div class="instawp-popup-main">
                <div class="instawppopwrap">
                    <div class="topinstawppopwrap">
                        <h3><?php echo esc_html__( 'Preparing Events for Sync', 'instawp-connect' ); ?></h3>
                        <div class="destination_form">
                            <label for="destination-site"><?php echo esc_html__( 'Destination site', 'instawp-connect' ); ?></label>
                            <select id="destination-site">
								<?php foreach ( $staging_sites as $site ): ?>
									<?php if ( isset( $site['url'] ) && isset( $site['connect_id'] ) ): ?>
                                        <option value="<?php echo esc_attr( $site['connect_id'] ) ?>"><?php echo esc_html( $site['url'] ); ?></option>
									<?php endif ?>
								<?php endforeach ?>
                            </select>
                        </div>
                        <div class="rounded-xl w-full text-bg py-4 px-4 border mt-5 bg-green-50">
                            <div class="progress-wrapper">
                                <div class="w-100 text-left font-medium event-progress-text">
                                    Sync not initiated ( 0 out of 0 events )
                                </div>
                                <div class="w-full text-bg py-1 flex items-center mb-2 border-b-[1px] mb-6 pb-3">
                                    <div class="w-full bg-gray-200 rounded-md event-progress-bar">
                                        <div class="instawp-progress-bar h-2 bg-primary-900 rounded-md"></div>
                                    </div>
                                    <div class="progress-text text-grayCust-650 text-sm font-medium"></div>
                                </div>
                            </div>
                            <div class="instawp_category rounded-xl">
                                <div id="event-type-list" class="instawpcatlftcol bulk-events-info bg-[#fff] relative instawp-box-loading">

                                </div>
                                <div class="instawpcatlftcol selected-events-info">
                                    <ul class="list">
                                        <li><span class="post-change">0</span><?php echo esc_html__( 'post change events', 'instawp-connect' ); ?></li>
                                        <li><span class="post-delete">0</span><?php echo esc_html__( 'post delete events', 'instawp-connect' ); ?></li>
                                        <li><span class="post-trash">0</span><?php echo esc_html__( 'post trash events', 'instawp-connect' ); ?></li>
                                        <li><span class="others">0</span><?php echo esc_html__( 'other events', 'instawp-connect' ); ?></li>
                                    </ul>
                                </div>
                                <div class="instawpcatrgtcol sync_process bg-[#fff]">
                                    <ul>
                                        <li class="step-1 process_pending"><?php echo esc_html__( 'Packing things', 'instawp-connect' ); ?></li>
                                        <li class="step-2 process_pending"><?php echo esc_html__( 'Pushing', 'instawp-connect' ); ?></li>
                                        <li class="step-3 process_pending"><?php echo esc_html__( 'Merging to destination', 'instawp-connect' ); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="sync_error_success_msg"></div>
                        <div class="sync_message_main textarea_json destination_form mt-6">
                            <label for="sync_message"><?php echo esc_html__( 'Message:', 'instawp-connect' ); ?></label>
                            <input type="hidden" id="id_syncIds" value=""/>
                            <textarea id="sync_message" name="sync_message" rows="4"></textarea>
                        </div>
                        <div class="instawp_buttons mt-6">
                            <div class="bulk-close-btn"><a class="cancel-btn close" href="javascript:void(0);"><?php echo esc_html__( 'Cancel', 'instawp-connect' ); ?></a></div>
                            <div class="bulk-sync-btn"><a class="changes-btn sync-changes-btn disabled" href="javascript:void(0);"><span><?php echo esc_html__( 'Sync', 'instawp-connect' ); ?></span></a></div>
                        </div>
                    </div>
                    <div><input type="hidden" id="selected_events" name="selected_events" value=""></div>
                </div>
            </div>
        </div>
        <!--    </div>-->
	<?php endif ?>
</div>