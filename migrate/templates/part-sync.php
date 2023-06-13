<?php
/**
 * Migrate template - Sync
 */

?>
<?php
$changeEvent = new InstaWP_Change_event();
$events = $changeEvent->listEvents();
$syncing_status = get_option('syncing_enabled_disabled');
$syncing_status_val = ($syncing_status == 1) ? 'checked' : '';
$InstaWP_db = new InstaWP_DB();
$tables = $InstaWP_db->tables;
#Total events
$total_events = $InstaWP_db->totalEvnets($tables['ch_table'],'pending');
$post_new = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'post_new','post','pending');
$post_delete = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'post_delete','post','pending');
$post_trash = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'post_trash','post','pending');
#others
$destination_url = get_option('instawp_sync_parent_url', '') ;
$others = (abs($total_events) - abs($post_new+$post_delete+$post_trash));
?>
<div class="nav-item-content sync bg-white box-shadow rounded-md p-6 data-padding">
    <?php if(empty($events)) : ?>
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
                    <label class="switch syncing_enabled_disabled">
					   <input type="checkbox" id="switch-id" <?php echo $syncing_status_val; ?>>
					   <span class="slider round"></span>
					</label>
                </div>
            </div>
<!--        </div>-->
    </div>
     <?php else : ?>
     <div class="sync-listining1">
<!--        <div class="bg-white  box-shadow rounded-md p-6 flex items-center justify-center">-->
            <div class="w-full">
                    <div class="events-head">
                        <div class="events-head-left">
                            <div class="text-grayCust-200 text-lg font-medium"><?php echo esc_html__( 'Listening for Changes', 'instawp-connect' ); ?></div>
                            <!-- <div class="text-grayCust-50 text-sm font-normal"><?php echo esc_html__( 'Lorem ipsum demo text the default payment method will be used for any biling purposes.', 'instawp-connect' ); ?></div> -->
                            <label class="switch-toggle syncing_enabled_disabled">
                                <input type="checkbox" <?php echo $syncing_status_val; ?>>
                                <span class="slider-toggle round-toggle"></span>
                            </label>
                        </div>
                        <div class="events-head-right">
                        <button type="button" class="instawp-green-btn bulk-sync-popup-btn">Sync all changes</button>
                        <!-- <button type="button" class="instawp-green-btn selected-sync-popup-btn">Selcted sync</button> -->
                        </div>
                    </div>
                    <div class="mt-8 flow-root">
                        <div class="-my-2 -mx-6 overflow-x-auto lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-300">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-4  uppercase text-left text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'event', 'instawp-connect' ); ?></th>
                                            <th scope="col" class="px-6 py-4 text-left uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'event details', 'instawp-connect' ); ?></th>
                                            <th scope="col" class="px-6 py-4 text-center uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'Status', 'instawp-connect' ); ?></th>
                                            <th scope="col" class="px-6 py-4 text-center uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'Actions', 'instawp-connect' ); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white ">
                                        <?php foreach ( $events as $event ) : ?>
                                            <?php $colors = $changeEvent->getStatusColor($event['status']); ?>
                                            <tr>
                                                <td class="whitespace-nowrap py-6 px-6 text-xs font-medium text-grayCust-300"><?php echo esc_html( $event['event_name'] ); ?></td>
                                                <td class="whitespace-nowrap px-6 py-6 font-medium text-xs text-grayCust-300"><?php echo esc_html( $event['title'] ); ?></td>
                                                <td class="whitespace-nowrap px-6 py-6 text-center font-medium text-xs text-grayCust-300"><div class="py-1 px-4 inline-block rounded-full text-primary-900 font-medium " style="background-color: <?php echo $colors['bg']; ?>;color:<?php echo $colors['color']; ?>"><?php echo esc_html( $event['status'] ); ?></div>
                                                </td>
                                                <td class="whitespace-nowrap cursor-pointer  text-center px-6 py-6 font-medium text-xs text-primary-900">
                                                    <?php echo   ($event['status'] != 'completed') ? '<button type="button" id="btn-sync-'.$event['ID'].'" data-id="'.$event['ID'].'" class="two-way-sync-btn">Sync changes</button> <span class="sync-loader"></span><span class="sync-success"></span>' : '<p class="sync_completed">Synced</p>';  ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- pagination -->
                            <nav class="flex items-center justify-between border-t border-gray-200 mx-9 my-5">
                                <div class="-mt-px flex w-0 flex-1">
                                    <a href="#" class="inline-flex items-center border-t-2 border-transparent pt-3 pr-1 text-sm font-medium text-grayCust-300 hover:border-gray-300 hover:text-gray-700">
                                        <!-- Heroicon name: mini/arrow-long-left -->
                                        <svg class="mr-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#1F2937" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M18 10a.75.75 0 01-.75.75H4.66l2.1 1.95a.75.75 0 11-1.02 1.1l-3.5-3.25a.75.75 0 010-1.1l3.5-3.25a.75.75 0 111.02 1.1l-2.1 1.95h12.59A.75.75 0 0118 10z" clip-rule="evenodd" />
                                        </svg>
                                        <?php echo esc_html__( 'Previous', 'instawp-connect' ); ?>
                                    </a>
                                </div>
                                <div class="-mt-px flex w-0 flex-1 justify-end">
                                    <a href="#" class="inline-flex items-center border-t-2 border-transparent pt-3 pl-1 text-sm font-medium text-grayCust-300 hover:border-gray-300 hover:text-gray-700">
                                        <?php echo esc_html__( 'Next', 'instawp-connect' ); ?>
                                        <!-- Heroicon name: mini/arrow-long-right -->
                                        <svg class="ml-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#1F2937" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M2 10a.75.75 0 01.75-.75h12.59l-2.1-1.95a.75.75 0 111.02-1.1l3.5 3.25a.75.75 0 010 1.1l-3.5 3.25a.75.75 0 11-1.02-1.1l2.1-1.95H2.75A.75.75 0 012 10z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                </div>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bulk-sync-popup" data-sync-type=""> 
                <div class="instawp-popup-main">
                    <div class="instawppopwrap">
                        <div class="topinstawppopwrap">
                            <h3>Preparing changes for Sync</h3>
                            <div class="destination_form">
                                <label for="instawp-destination">Destination</label>
                                <select id="destination-site">
                                    <?php  foreach($staging_sites as $site): ?>
                                        <?php $site_name = isset( $site['site_name'] ) ? $site['site_name'] : ''; ?>
                                    <option value="<?php echo $site['connect_id'] ?>"><?php echo esc_html($site_name); ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="instawp_category">
                                <div class="instawpcatlftcol bulk-events-info">
                                    <ul class="list">
                                        <li><?php printf('%d post change events', $post_new) ?></li>
                                        <li><?php printf('%d post delete events', $post_delete) ?></li>
                                        <li><?php printf('%d post trash eventss', $post_trash) ?></li>
                                        <li><?php printf('%d other events', $others) ?></li>
                                    </ul>
                                </div>
                                <div class="instawpcatlftcol selected-events-info">
                                    <ul class="list">
                                        <li><span class="post-change">0</span> post change events</li>
                                        <li><span class="post-delete">0</span> post delete events</li>
                                        <li><span class="post-trash">0</span> post trash events</li>
                                        <li><span class="others">0</span> other events</li>
                                    </ul>
                                </div>
                                <div class="instawpcatrgtcol sync_process">
                                    <ul>
                                        <li class="step-1 process_pending">Packing things</li>
                                        <li class="step-2 process_pending">Pusing to cloud</li>
                                        <li class="step-3 process_pending">Merging to destination</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="sync_error_success_msg"></div>
                            <div class="sync_message_main textarea_json destination_form">
                                <label for="sync_message">Message:</label>
                                <textarea id="sync_message" name="sync_message" rows="4"></textarea>
                            </div>
                        </div>
                        <div class="instawp_buttons">                            
                            <div class="bulk-close-btn"><a class="cancel-btn close" href="javascript:void(0);">Cancel</a></div>
                            <div class="bulk-sync-btn"><a class="changes-btn sync-changes-btn" href="javascript:void(0);">Sync Changes</a></div>
                        </div>
                        <div><input type="hidden" id="selected_events" name="selected_events" value=""></div>
                    </div>
                </div>
             </div>
<!--    </div>-->
    <?php endif ?>
</div>