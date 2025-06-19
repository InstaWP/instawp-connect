<?php
/**
 * Migrate template - Main
 */

use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

global $instawp_settings;

$current             = get_site_transient( 'update_plugins' );
$plugin_file         = plugin_basename( INSTAWP_PLUGIN_FILE );
$update_available    = false;
$curr_version_number = "";
$new_version_number  = "";

if ( isset( $current->response[ $plugin_file ] ) ) {
	$curr_version_number = INSTAWP_PLUGIN_VERSION;
	$new_version_number  = isset( $current->response[ $plugin_file ]->new_version ) ? $current->response[ $plugin_file ]->new_version : '';
	$update_available    = true;
}

/**
 * Jaed and Sayan discussed and made the decision to remove this functionality.
 *
 * If there is no requirement comes in the future, we will permanently delete this with the associates files.
 */

//if ( ! empty( $_GET['debug'] ) && current_user_can( 'manage_options' ) ) {
//  $file_path = INSTAWP_PLUGIN_DIR . '/migrate/templates/debug/' . sanitize_file_name( wp_unslash( $_GET['debug'] ) ) . '.php';
//
//  if ( file_exists( $file_path ) ) {
//      include $file_path;
//
//      return;
//  }
//}
$connect_id      = Helper::get_connect_id();
$connect_api_key = Helper::get_api_key();
$connect_domain  = Helper::get_api_domain();
$connect_classes = array( 'nav-content' );
$staging_sites   = instawp_get_connected_sites_list();

$syncing_status    = Option::get_option( 'instawp_is_event_syncing' );
$migration_details = Option::get_option( 'instawp_migration_details', array() );
$plugin_nav_items  = InstaWP_Setting::get_plugin_nav_items();

$instawp_settings['instawp_is_event_syncing']  = $syncing_status;
$instawp_settings['instawp_migration_details'] = $migration_details;

instawp()->is_connected = ! empty( $connect_id );

if ( ! instawp()->is_connected ) {
	$connect_classes[] = 'p-0';
}
?>

<div class="wrap instawp-wrap box-width pt-10">
	<?php if ( $update_available ) : ?>
        <div class="pb-4 flex items-center justify-center">
            <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4 text-left w-full shadow rounded" role="alert">
                <div class="flex justify-between items-center transition-all duration-300">
                    <p class="max-w-[85%]">
                        <span class="inline-block"><?php printf( wp_kses_post( __( 'A new version of InstaWP Connect (%1$s) is available. You are using an older version (%2$s), it is recommended to update.', 'instawp-connect' ) ), $new_version_number, INSTAWP_PLUGIN_VERSION ); ?></span>
                        <span class="instawp-update-notice hidden bg-red-700 text-gray-200 px-1.5 py-[2px] mt-2 rounded-sm transition-all duration-300"><?php esc_html_e( 'Could not update the plugin!', 'instawp-connect' ); ?></span>
                    </p>
					<?php printf( wp_kses_post( __( '<p class="cursor-pointer px-4 py-2 text-xs font-medium text-center inline-flex items-center text-white bg-secondary rounded-3xl hover:bg-primary-900 hover:text-white ease-linear duration-300 instawp-update-plugin" data-plugin="%s">Update Now</p>', 'instawp-connect' ) ), esc_attr( $plugin_file ) ); ?>
                </div>
            </div>
        </div>
	<?php endif; ?>
    <div class="w-full">
        <div class="bg-white shadow-md rounded-lg">
			<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/navbar.php'; ?>
            <div class="bg-grayCust-400 shadow-md rounded-bl-lg rounded-br-lg <?php echo esc_attr( implode( ' ', $connect_classes ) ); ?>">
                <?php foreach ( $plugin_nav_items as $item_key => $item ) {
                    if ( instawp()->is_connected || $item_key === 'developer' ) {
                        include INSTAWP_PLUGIN_DIR . 'migrate/templates/part-' . sanitize_file_name( $item_key ) . '.php';
                    } else { ?>
                        <div class="nav-item-content <?= esc_attr( $item_key ); ?> bg-white rounded-md">
                            <?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-create-connect.php'; ?>
                        </div>
                    <?php }
                } ?>
            </div>
        </div>
    </div>
</div>
