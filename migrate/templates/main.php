<?php
/**
 * Migrate template - Main
 */

use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

// Add WordPress update script
wp_enqueue_script( 'updates' );

global $staging_sites, $instawp_settings;

// Add this code to check for plugin updates
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

	<?php if ( $update_available ) : ?>
        <div class="pb-4 flex items-center justify-center">
            <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4 text-left w-full" role="alert">
                <div class="flex justify-between items-center">
                    <span><?php printf( wp_kses_post( __( 'A new version of InstaWP Connect (%s) is available. You are using an older version (%s), it is recommended to update.', 'instawp-connect' ) ), $new_version_number, INSTAWP_PLUGIN_VERSION ); ?></span>
                    <span><?php printf( wp_kses_post( __( '<a href="#" class="px-4 py-2 text-xs font-medium text-center inline-flex items-center text-white bg-primary-700 rounded-3xl hover:bg-primary-800 hover:text-white ease-linear duration-300 instawp-update-plugin" data-plugin="%s">Update now</a>', 'instawp-connect' ) ), esc_attr( $plugin_file ) ); ?></span>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('.instawp-update-plugin').on('click', function (e) {
                    e.preventDefault();

                    const $updateLink = $(this);
                    const $spinner = $('.instawp-update-spinner');
                    const pluginSlug = $updateLink.data('plugin');

                    $updateLink.hide();
                    $spinner.show();

                    wp.updates.maybeRequestFilesystemCredentials(e);

                    wp.updates.updatePlugin({
                        plugin: pluginSlug,
                        success: function (response) {
                            $spinner.hide();
                            // Refresh the page after successful update
                            location.reload();
                        },
                        error: function (response) {
                            $spinner.hide();
                            $updateLink.show();
                            alert(response.errorMessage);
                        }
                    });
                });
            });
        </script>
	<?php endif; ?>


    <div class="w-full">
        <div class="bg-white shadow-md rounded-lg">
			<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/navbar.php'; ?>
            <div class="nav-content bg-grayCust-400 shadow-md rounded-bl-lg rounded-br-lg <?php echo esc_attr( implode( ' ', $connect_classes ) ); ?>">
				<?php foreach ( InstaWP_Setting::get_plugin_nav_items() as $item_key => $item ) {
					include INSTAWP_PLUGIN_DIR . 'migrate/templates/part-' . $item_key . '.php';
				} ?>
            </div>
        </div>
    </div>
</div>
