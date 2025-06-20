<?php

/**
 * Migrate template - Main
 */

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

$api_response   = Helper::get_response();
$email          = Helper::get_args_option( 'email', $api_response );
$team_name      = Helper::get_args_option( 'team_name', $api_response );
$current_tab    = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
$is_app_connect = strpos( $connect_domain, 'app' ) !== false;

if ( ! in_array( $current_tab, array_keys( $plugin_nav_items ) ) ) {
    $current_tab = '';
} ?>

<div class="flex border-b justify-between shadow-md rounded-tl-lg rounded-tr-lg border-grayCust-100 instawp-current-tab" current-tab="<?php echo esc_attr($current_tab); ?>">
    <div class="flex items-center nav-items">
        <?php foreach ( $plugin_nav_items as $item_key => $item ) {
            $icon  = isset( $item['icon'] ) ? $item['icon'] : '';
            $label = isset( $item['label'] ) ? $item['label'] : '';

            printf(
                '<div id="%s" class="nav-item"><a class="flex items-center px-4 py-5 border-b-2 border-transparent hover:text-primary-900 text-sm font-medium">%s<span>%s</span></a></div>',
                esc_html( $item_key ),
                $icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                esc_html( $label )
            );
        } ?>
    </div>

    <?php if ( empty( $connect_api_key ) || empty( $connect_id ) ) : ?>
        <div class="flex items-center text-sm font-medium">
            <div class="flex items-center gap-2 pr-4">
                <span class="w-1 h-1 bg-red-600 rounded-full"></span>
                <span class="text-red-600"><?php esc_html_e( 'Your account is not connected', 'instawp-connect' ); ?></span>
            </div>
        </div>
    <?php else : ?>
        <div class="flex gap-1 flex-col items-end justify-center text-sm font-medium px-4">
            <div class="flex items-center gap-2">
                <span class="w-1 h-1 <?= $is_app_connect ? 'bg-secondary' : 'bg-amber-600'; ?> rounded-full"></span>
                <a href="<?php echo esc_url( sprintf( '%s/connects/%s/dashboard', $connect_domain, instawp()->connect_id ) ); ?>" target="_blank" class="focus:ring-0 hover:ring-0 focus:outline-0 hover:outline-0 <?= $is_app_connect ? 'text-secondary hover:text-secondary focus:text-secondary' : 'text-amber-600 hover:text-amber-600 focus:text-amber-600'; ?>">
                    <?php if ( instawp_is_connect_whitelabelled() ) {
                        esc_html_e( 'Managed Website', 'instawp-connect' );
                    } else {
                        esc_html_e( 'Your account is connected', 'instawp-connect' );
                    } ?>
                </a>
            </div>
            <?php if ( ! instawp_is_connect_whitelabelled() && ! empty( $email ) && ! empty( $team_name ) ) : ?>
                <div class="text-grayCust-50 text-xs">
                    <span><?= esc_html( $email ); ?></span> | <span><?= esc_html( $team_name ); ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>