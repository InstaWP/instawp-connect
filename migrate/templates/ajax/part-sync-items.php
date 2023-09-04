<?php if(!empty($events)):?>
    <?php foreach ( $events as $event ) : ?>
        <?php 
            $event_row = $InstaWP_db->getSiteEventStatus($connect_id, $event->id);    
            $status = $event_row && $event_row->status !='' ? $event_row->status : 'pending';
            $datetime = date( 'M j, Y H:i A', strtotime( $event->date ) );
        ?>
        <tr>
            <td class="whitespace-nowrap py-6 px-6 text-sm font-medium text-grayCust-300 w-0.5">
                <input type="checkbox" name="event[]"  value="<?php echo $event->id; ?>" class="single-event-cb" />
            </td>
            <td class="whitespace-nowrap py-6 px-6 text-sm font-medium text-grayCust-300"><?php echo esc_html( $event->event_name ); ?></td>
            <td class="whitespace-nowrap px-6 py-6 font-medium text-sm text-grayCust-300"><?php echo esc_html( $event->title ); ?></td>
            <td class="whitespace-nowrap px-6 py-6 font-medium text-sm text-grayCust-300"><?php echo esc_html( $datetime ); ?></td>
            <td class="whitespace-nowrap px-6 py-6 text-center font-medium text-sm text-grayCust-300">
            <div class="flex flex-col items-center">
                <div class="py-1 px-4 inline-block rounded-full text-primary-900 font-medium synced_status <?php echo $status; ?>"><?php echo esc_html( ucfirst($status) ); ?></div>
                    <?php if( $event_row && $event_row->synced_message !='' && $status == 'error' ) { ?>
                        <div class="py-1 px-4 inline-block rounded-full text-primary-900 font-medium text-red-500"><?php echo esc_html( $event_row->synced_message ); ?></div>
                    <?php } ?>
                </div>
            </td>
            <!-- <td class="whitespace-nowrap cursor-pointer  text-center px-6 py-6 font-medium text-sm text-primary-900">
                <?php echo ($status != 'completed') ? '<button type="button" id="btn-sync-'.$event->id.'" data-id="'.$event->id.'" class="two-way-sync-btn btn-single-sync"><span>Sync changes </span></button><span class="sync-success"></span>' : '<p class="sync_completed">Synced</p>';  ?>
            </td> -->
        </tr>
    <?php endforeach; ?>
<?php endif?>