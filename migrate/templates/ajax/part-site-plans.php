<?php

defined( 'ABSPATH' ) || exit;

/*
 * Size the plan against files AND the database.
 *
 * get_site_plans() already computes $total_size = $total_files_size + $total_db_size, but this
 * threshold only ever read $total_files_size — so a site with a small filesystem and a large
 * database could select a plan that cannot hold it, and then block at Preparation partway through
 * the migration. Both variables are in scope here because the template is include()d from inside
 * get_site_plans().
 */
$total_size_mb = $total_size / (1000 * 1000);
?>

<div class="flex items-start staging-plans">
    <div class="text-grayCust-900 text-base font-normal mr-4 basis-1/5 flex items-center gap-1">
        <span><?php esc_html_e( 'Select Plan', 'instawp-connect' ); ?></span>
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="mt-0.5 hidden">
            <path d="M2.66699 2.66699V6.00033H3.05467M13.2924 7.33366C12.9643 4.70278 10.7201 2.66699 8.00033 2.66699C5.76207 2.66699 3.84585 4.04577 3.05467 6.00033M3.05467 6.00033H6.00033M13.3337 13.3337V10.0003H12.946M12.946 10.0003C12.1548 11.9549 10.2386 13.3337 8.00033 13.3337C5.28058 13.3337 3.03632 11.2979 2.70825 8.66699M12.946 10.0003H10.0003" stroke="#9CA3AF" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="flex flex-col gap-3 w-full basis-4/5">
        <?php foreach ( $site_plans as $key => $site_plan ) {
            // Filter visible features once
            $visible_features = array_filter( $site_plan['features'], function( $feature ) {
                return $feature['is_visible'] === true;
            } );

            // Build features array more efficiently
            $features_to_show = array_column( $visible_features, null, 'feature' );
            
            // Build feature items array
            $feature_items = array();
            
            // Add worker count if available
            if ( isset( $features_to_show['worker_count']['value'] ) && $features_to_show['worker_count']['value'] ) {
                $feature_items[] = sprintf( 
                    _n( '%s Worker', '%s Workers', $features_to_show['worker_count']['value'], 'instawp-connect' ), 
                    number_format_i18n( $features_to_show['worker_count']['value'] ) 
                );
            }

            // Add disk quota if available
            if ( isset( $features_to_show['disk_quota']['value'] ) && $features_to_show['disk_quota']['value'] ) {
                $feature_items[] = sprintf( __( '%s GB Storage', 'instawp-connect' ), $features_to_show['disk_quota']['value'] / 1000 );
            }

            /*
             * Disabled on SIZE, and only on size.
             *
             * The free-plan handling that used to live here is gone: new free sites are withdrawn
             * upstream, so no free plan reaches this list. client-app's getSiteCreationPlans()
             * (app/Traits/PayPerUse.php) rejects every is_free plan unless
             * const.free_site_creation_enabled is true, and that defaults to false.
             *
             * ⚠ That flag is the coupling. Setting FREE_SITE_CREATION_ENABLED=true on client-app
             * puts free plans back in this list, and this template no longer has the 3-site cap
             * label or the free price format — so re-enabling free site creation is now a change in
             * BOTH repositories, not just an env var. Removed deliberately (review of PR #541).
             */
            $disk_quota_exceeded = isset( $features_to_show['disk_quota']['value'] ) && $total_size_mb > $features_to_show['disk_quota']['value'];
            $is_plan_disabled = $disk_quota_exceeded;
            ?>
            <label class="w-full cursor-pointer relative">
                <input type="radio" 
                        name="migrate_settings[plan_id]" 
                        id="staging-plan-<?php echo esc_attr( $key + 1 ); ?>" 
                        value="<?php echo esc_attr( $site_plan['id'] ); ?>" 
                        class="plan-selector peer !hidden" 
                        <?php disabled( $is_plan_disabled, true ); ?> />
                <div class="border pl-10 pr-4 font-medium rounded-lg flex items-center justify-between w-full cursor-pointer peer-disabled:opacity-50 peer-disabled:cursor-not-allowed peer-checked:border-primary-900 peer-checked:bg-teal-900 peer-checked:bg-opacity-5">
                    <div class="flex items-center gap-2 w-full py-2 cursor-pointer">
                        <span><?php echo esc_html( $site_plan['display_name'] ); ?></span>
                        <?php if ( ! empty( $feature_items ) ) { ?>
                            <span class="text-blue-800 text-xs font-medium bg-blue-50 px-2 py-1 rounded-md truncate"><?php echo esc_html( implode( ', ', $feature_items ) ); ?></span>
                        <?php } ?>
                        <?php
                        /*
                         * Say WHY, with both numbers. A plan disabled on size used to render no
                         * label at all — just a greyed row — and sizing now counts the database as
                         * well as the files, which routes many more rows down here.
                         */
                        if ( $disk_quota_exceeded ) {
                            ?>
                            <span class="text-xs text-gray-500 font-light"><?php
                                /* translators: 1: this site's total size in GB, 2: the plan's storage in GB. */
                                echo esc_html(
                                    sprintf(
                                        __( 'Too small — this site is %1$s GB, plan holds %2$s GB', 'instawp-connect' ),
                                        number_format_i18n( $total_size_mb / 1000, 2 ),
                                        number_format_i18n( $features_to_show['disk_quota']['value'] / 1000, 2 )
                                    )
                                );
                            ?></span>
                            <?php
                        }
                        ?>
                    </div>
                    <div class="font-medium whitespace-nowrap">
                        <?php echo esc_html( $site_plan['rate']['monthly'] ); ?><span class="text-xs text-gray-500 font-light">/mo - <?php echo esc_html( $site_plan['rate']['daily'] ); ?>/day</span>
                    </div>
                </div>
                <div class="absolute left-4 top-1/2 transform -translate-y-1/2 w-4 h-4 rounded-full peer-checked:border-primary-900 peer-checked:border-4 border flex items-center justify-center transition-colors bg-white"></div>
            </label>
        <?php } ?>
    </div>
</div>