<?php
/**
 * Migrate template - Main
 */

use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

global $staging_sites, $instawp_settings;

if ( ! empty( $_GET['debug'] ) && current_user_can( 'manage_options' ) ) {
	$file_path = INSTAWP_PLUGIN_DIR . '/migrate/templates/debug/' . sanitize_text_field( wp_unslash( $_GET['debug'] ) ) . '.php';

	if ( file_exists( $file_path ) ) {
		include $file_path;

		return;
	}
}

$connect_classes = array();
$staging_sites   = instawp_get_connected_sites_list();

$syncing_status    = Option::get_option( 'instawp_is_event_syncing' );
$migration_details = Option::get_option( 'instawp_migration_details', array() );

$instawp_settings['instawp_is_event_syncing']  = $syncing_status;
$instawp_settings['instawp_migration_details'] = $migration_details;

instawp()->is_connected = ! empty( Helper::get_api_key() );

if ( ! instawp()->is_connected ) {
	$connect_classes[] = 'p-0';
}

?>

<div class="wrap instawp-wrap box-width pt-10">
    <div class="w-full">
        <div class="bg-white shadow-md rounded-lg">
			<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/navbar.php'; ?>
            <div class="nav-content bg-grayCust-400 shadow-md rounded-bl-lg rounded-br-lg <?php echo esc_attr( implode( ' ', $connect_classes ) ); ?>">
				<?php foreach ( InstaWP_Setting::get_plugin_nav_items() as $item_key => $item ) {
					include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-' . $item_key . '.php';
				} ?>
            </div>
        </div>
    </div>
</div>
