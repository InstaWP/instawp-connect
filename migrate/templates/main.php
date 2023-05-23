<?php
/**
 * Migrate template - Main
 */

$connect_classes = array();
$access_token    = isset( $_REQUEST['access_token'] ) ? sanitize_text_field( $_REQUEST['access_token'] ) : '';
$status_status   = isset( $_REQUEST['success'] ) ? sanitize_text_field( $_REQUEST['success'] ) : '';

if ( 'true' == $status_status && InstaWP_Setting::get_option( 'instawp_api_key' ) != $access_token ) {
	InstaWP_Setting::instawp_generate_api_key( $access_token, $status_status );
}

if ( ! instawp()->is_connected ) {
	$connect_classes[] = 'p-8';
}

?>

<div class="wrap instawp-wrap box-width pt-10">

    <div class="w-full">
        <div class="bg-white shadow-md rounded-lg">

			<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/navbar.php'; ?>

            <div class="nav-content bg-grayCust-400 shadow-md rounded-bl-lg rounded-br-lg <?php echo esc_attr( implode( ' ', $connect_classes ) ); ?>">
				<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-create.php'; ?>
				<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-sites.php'; ?>
				<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-sync.php'; ?>
				<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-settings.php'; ?>
            </div>

        </div>
    </div>

</div>
