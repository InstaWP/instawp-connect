<?php
/**
 * Migrate template - Management
 */

defined( 'ABSPATH' ) || exit;

$disconnected = get_option( 'instawp_connect_plan_disconnected' );
?>

<div class="nav-item-content site-management bg-white rounded-md p-6">
    <div class="p-8 space-y-6">
        <div class="flex items-center gap-2 flex-col">
            <?php if ( defined( 'CONNECT_WHITELABEL_TEAM_ICON' ) && CONNECT_WHITELABEL_TEAM_ICON ) { ?>
                <img src="<?php echo esc_url( CONNECT_WHITELABEL_TEAM_ICON ); ?>" alt="logo" class="h-10" />
            <?php } ?>
            <h2 class="text-sm text-grayCust-550">
                <?= esc_html_e( 'Upgrade your site management plan for better site security and more', 'instawp-connect' ); ?>
            </h2>
        </div>
        <?php if ( $disconnected ) { ?>
            <div class="flex items-center flex-wrap justify-between px-4 py-3 border border-grayCust-650 rounded-lg" style="background: linear-gradient(90deg, rgba(240,242,236,1) 0%, rgba(17,191,133,0.1) 76%, rgba(238,223,92,0.1) 100%);">
                <div class="flex items-center gap-3 flex-wrap">
                    <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/sad.png' ) ); ?>" alt="sad-icon" class="grayscale" />
                    <span class="text-base text-grayCust-800"><?= esc_html_e( 'Your website is no longer protected. Subscribe today to Pro Plan!', 'instawp-connect' ); ?></span>
                </div>
                <button class="bg-blueCust-200 text-white px-4 py-2 rounded-lg" id="instawp-protect-site-btn"><?= esc_html_e( 'Protect My Site', 'instawp-connect' ); ?></button>
            </div>
        <?php } ?>
        <div class="flex justify-between gap-6 md:flex-nowrap flex-wrap" id="instawp-plans-container">
            <?php include INSTAWP_PLUGIN_DIR . 'migrate/templates/ajax/part-plans.php'; ?>
        </div>
    </div>
</div>