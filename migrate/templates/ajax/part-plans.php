<?php

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

$current_plan = Helper::get_connect_plan();
$plans        = CONNECT_WHITELABEL_PLAN_DETAILS;

foreach ( $plans as $plan ) {
    $plan_id = (int) $plan['plan_id'];
    if ( ! $plan_id ) {
        continue;
    }

    if ( ! empty( $current_plan['plan_timestamp'] ) ) {
        $plan_activated_date = new DateTime( $current_plan['plan_timestamp'] ); 
        $today_date          = new DateTime( current_time( 'mysql' ) );
        $diff                = $today_date->diff( $plan_activated_date );
        $duration            = $diff->days;
    }

    $trial           = isset( $plan['trial'] ) ? (int) $plan['trial'] : 0;
    $remaining_days  = isset( $duration ) ? $trial - $duration : $trial;
    $is_current_plan = ! empty( $current_plan['plan_id'] ) && $current_plan['plan_id'] === $plan_id && ! empty( $connect_id );

    $tick_class = 'absolute -top-8 -right-2 h-5 w-5 bg-blueCust-200 rounded-full';
    $box_class = 'md:w-1/2 w-full p-6 rounded-lg space-y-6 relative';
    if ( $is_current_plan ) {
        $box_class .= ' border-2 border-blueCust-200 active';
    } else {
        $tick_class .= ' hidden';
        $box_class .= ' border border-grayCust-650';
    }
    if ( $plan['free'] ) {
        $feature_icon = instaWP::get_asset_url( 'migrate/assets/images/check-icon-gray.svg' );
    } else {
        $box_class .= ' bg-blueCust-50';
        $feature_icon = instaWP::get_asset_url( 'migrate/assets/images/check-icon-blue.svg' );
    }
    ?>
    <div class="<?= esc_attr( $box_class ) ?>">
        <div>
            <div class="flex items-center justify-between">
                <h2 class="text-3xl text-grayCust-750"><?= esc_html( $plan['name'] ); ?></h2>
                <?php if ( $plan['free'] ) { ?>
                    <p class="text-grayCust-550 text-3xl"><?= esc_html__( 'Free', 'instawp-connect' ); ?></p>
                <?php } else {  ?>
                    <div class="flex items-end">
                        <p class="text-grayCust-800 text-3xl"><?= esc_html( $plan['price'] ); ?></p>
                        <p class="text-grayCust-550 text-2xl">/<?= esc_html( $plan['frequency'] ); ?></p>
                    </div>
                <?php } ?>
            </div>
            <p class="mt-1.5 text-base text-grayCust-550"><?= esc_html( $plan['description'] ); ?></p>
        </div>
        <?php
            $btn_class = 'mt-4 w-full border border-grayCust-650 py-2.5 rounded-lg text-base font-semibold';
            if ( $is_current_plan ) {
                $btn_text = __( 'Current Plan', 'instawp-connect' );
                $btn_class .= ' pointer-events-none';
                
                if ( $plan['free'] ) {
                    $btn_class .= ' text-grayCust-450';
                } else {
                    $btn_class .= ' bg-blueCust-100 text-white';
                }
            } elseif ( $plan['free'] ) {
                $btn_text = __( 'Select Plan', 'instawp-connect' );
                if ( $remaining_days < 1 ) {
                    $btn_class .= ' text-grayCust-450 pointer-events-none';
                }
            } else {
                $btn_text = __( 'Get Complete Protection', 'instawp-connect' );
                $btn_class .= ' bg-blueCust-200 text-white';
            }
        ?>
        <button class="instawp-connect-plan-btn <?= esc_attr( $btn_class ) ?>" data-plan-id="<?= esc_attr( $plan['plan_id'] ) ?>"><?= esc_html( $btn_text ); ?></button>
        <div class="border-t border-grayCust-650"></div>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="font-semibold text-base text-grayCust-800 uppercase"><?= esc_html__( 'Plan Info', 'instawp-connect' ); ?></span>
                <?php if ( $remaining_days > 0 ) { ?>
                    <span class="inline-flex items-center rounded-full bg-yellowCust-150 px-3 py-1 text-sm font-medium text-yellowCust-200"><?= esc_html( $remaining_days ); ?> <?= esc_html__( 'days remaining', 'instawp-connect' ); ?></span>
                <?php } elseif ( $trial > 0 ) { ?>
                    <span class="inline-flex items-center rounded-full bg-redCust-150 px-3 py-1 text-sm font-medium text-redCust-200"><?= esc_html__( 'Plan expired', 'instawp-connect' ); ?></span>
                <?php } ?>
            </div>
            <div class="space-y-4">
                <?php foreach ( $plan['features'] as $feature ) { ?>
                    <div class="flex items-center gap-3">
                        <img src="<?php echo esc_url( $feature_icon ); ?>" alt="check-icon" />
                        <span class="text-base text-grayCust-550"><?= esc_html( $feature ); ?></span>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="tick-icon <?= esc_attr( $tick_class ) ?>">
            <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/check-icon-dark-blue.svg' ) ); ?>" alt="check-icon" />
        </div>
    </div>
<?php } ?>