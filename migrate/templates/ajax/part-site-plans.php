<?php

defined( 'ABSPATH' ) || exit;

?>

<div class="flex items-start staging-plans">
    <div class="text-grayCust-900 text-base font-normal mr-4 basis-1/5 flex items-center gap-1">
        <span><?php esc_html_e( 'Select Plan', 'instawp-connect' ); ?></span>
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="mt-0.5 hidden">
            <path d="M2.66699 2.66699V6.00033H3.05467M13.2924 7.33366C12.9643 4.70278 10.7201 2.66699 8.00033 2.66699C5.76207 2.66699 3.84585 4.04577 3.05467 6.00033M3.05467 6.00033H6.00033M13.3337 13.3337V10.0003H12.946M12.946 10.0003C12.1548 11.9549 10.2386 13.3337 8.00033 13.3337C5.28058 13.3337 3.03632 11.2979 2.70825 8.66699M12.946 10.0003H10.0003" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="flex flex-col gap-3 w-full basis-4/5">
        <?php foreach ( $site_plans['plans']['sites'] as $key => $site_plan ) {
            // $features = array_filter( $site_plan['features'], function( $feature ) {
            //     return $feature['is_visible'] === true;
            // } );

            // $features_to_show = [];
            // foreach ( $features as $feature ) {
            //     $features_to_show[ $feature['feature'] ] = $feature;
            // }

            // $feature_items = [];
            // if ( ! empty( $features_to_show['disk_quota']['value'] ) ) {
            //     $feature_items[] = ( $features_to_show['disk_quota']['value'] / 1000 ) . ' GB';
            // }

            // if ( ! empty( $features_to_show['cdn']['value'] ) ) {
            //     $feature_items[] = 'CDN';
            // }

            // if ( ! empty( $features_to_show['backup_ui']['value'] ) ) {
            //     $feature_items[] = 'Backups';
            // }

            $is_free_plan_disabled = $site_plan['name'] === 'free' && $site_plans['free_site_count'] >= 3; ?>
            <label class="w-full cursor-pointer relative">
                <input type="radio" name="migrate_settings[plan_id]" id="staging-plan-<?php echo esc_attr( $key + 1 ); ?>" value="<?php echo esc_attr( $site_plan['id'] ); ?>" class="peer !hidden" <?php disabled( $is_free_plan_disabled, true ); ?> />
                <div class="border pl-10 pr-4 font-medium rounded-lg flex items-center justify-between w-full cursor-pointer peer-disabled:opacity-50 peer-disabled:cursor-not-allowed peer-checked:border-primary-900 peer-checked:bg-teal-900 peer-checked:bg-opacity-5">
                    <div class="flex items-center gap-2 w-full py-2 cursor-pointer">
                        <span><?php echo esc_html( $site_plan['display_name'] ); ?></span>
                        <?php if ( ! empty( $site_plan['description'] ) ) { ?>
                            <span class="text-blue-800 text-xs font-medium bg-blue-50 px-2 py-1 rounded-md truncate"><?php echo esc_html( $site_plan['description'] ); ?></span>
                        <?php } ?>
                        <?php if ( $is_free_plan_disabled ) { ?>
                            <span class="text-xs text-gray-500 font-light"><?php esc_html_e( '3 sites exhausted', 'instawp-connect' ); ?></span>
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
                <div class="absolute left-4 top-1/2 transform -translate-y-1/2 w-4 h-4 rounded-full peer-checked:border-primary-900 peer-checked:border-4 border flex items-center justify-center transition-colors bg-white"></div>
            </label>
        <?php } ?>
    </div>
</div>