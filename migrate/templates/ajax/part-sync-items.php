<?php 
defined( 'ABSPATH' ) || exit;

if ( ! empty( $events ) ) : ?>
    <?php foreach ( $events as $event ) : ?>
        <?php
            $event_status     = $event->status;
            $label_classes    = array( $event_status, '[&.completed1]:text-red-500' );
            $label_attributes = '';
            $datetime         = wp_date( 'M j, Y H:i A', strtotime( $event->date ) );
            $user_id          = $event->user_id;
            $synced_by        = '--';

            if ( ! empty( $user_id ) ) {
                $user = get_user_by( 'id', $user_id );
                if ( ! empty( $user ) ) {
                    $synced_by = $user->display_name;
                }
            }

            if ( $event_status === 'completed' ) {
                $label_classes[]   = 'hint--left hint--success';
                $label_attributes .= sprintf(' aria-label="%s: %s"', __(  'Synced', 'instawp-connect' ), wp_date( 'M j, Y H:i A', strtotime( $event->synced_date ) ) );
            } else if ( ! empty( $event->synced_message ) ) {
                $label_classes[]   = 'hint--left';
                $label_attributes .= sprintf(' aria-label="%s"', $event->synced_message );
            }
        ?>
        <tr>
            <td class="whitespace-nowrap py-3 px-3 text-sm font-medium text-grayCust-300 text-center">
                <label>
                    <input type="checkbox" name="event[]" value="<?= esc_attr( $event->id ); ?>" class="single-event-cb" />
                </label>
            </td>
            <td class="whitespace-nowrap py-3 px-3 text-sm font-medium text-grayCust-300"><?php echo esc_html( $event->event_name ); ?></td>
            <td class="whitespace-nowrap px-3 py-3 font-medium text-sm text-grayCust-300 instawp-event-title-td"><?php echo esc_html( $event->title ); ?></td>
            <td class="whitespace-nowrap px-3 py-3 font-medium text-sm text-grayCust-300"><?php echo esc_html( $synced_by ); ?></td>
            <td class="whitespace-nowrap px-3 py-3 font-medium text-sm text-grayCust-300"><?php echo esc_html( $datetime ); ?></td>
            <td class="whitespace-nowrap px-3 py-3 text-center font-medium text-sm text-grayCust-300">
                <div class="flex flex-col items-center">
                    <div class="text-sm font-medium !px-3 !py-1 !m-0 !shadow-none !border-0 rounded-full synced_status <?php echo esc_attr( join( ' ', $label_classes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> [&.pending]:bg-sky-200 [&.pending]:text-sky-800 [&.error]:bg-red-200 [&.error]:text-red-800 [&.invalid]:bg-yellow-200 [&.invalid]:text-yellow-800 [&.completed]:bg-green-200 [&.completed]:text-green-900" <?php echo $label_attributes; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( ucfirst( $event_status ) ); ?></div>
                    <?php if ( isset( $event->log ) && $event->log !== '' ) : ?>
                        <div class="hint--top hint--low" aria-label="<?= esc_html( $event->log ); ?>">
                            <svg class="w-4 h-4 mr-2" width="14" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM11 7H13V9H11V7ZM11 11H13V17H11V11Z"></path></svg>
                        <div>
                    <?php endif ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php else : ?> 
        <tr>
            <td colspan="5" class="whitespace-nowrap py-6 px-6 text-sm font-medium text-grayCust-300 w-0.5 text-center">
                <?php echo esc_html__('No events found!', 'instawp-connect') ?>
            </td>
        </tr> 
<?php endif?>