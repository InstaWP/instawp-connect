<?php
/**
 * Migrate template - Main
 */

$access_token  = isset( $_REQUEST['access_token'] ) ? sanitize_text_field( $_REQUEST['access_token'] ) : '';
$status_status = isset( $_REQUEST['success'] ) ? sanitize_text_field( $_REQUEST['success'] ) : '';

if ( 'true' == $status_status && InstaWP_Setting::get_option( 'instawp_api_key' ) != $access_token ) {
	InstaWP_Setting::instawp_generate_api_key( $access_token, $status_status );
}

?>


<div class="wrap instawp-wrap box-width pt-10">

    <div class="w-full">
        <div class="bg-white shadow-md rounded-lg">

			<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/navbar.php'; ?>

            <div class="nav-content bg-grayCust-400 p-8 shadow-md rounded-bl-lg rounded-br-lg">
				<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-create.php'; ?>
				<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-sites.php'; ?>
				<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-sync.php'; ?>
				<?php include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-settings.php'; ?>
            </div>

        </div>
    </div>

</div>
