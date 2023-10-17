<?php
/**
 * Migrate template - Create Site
 */

$nav_item_classes  = array( 'nav-item-content' );
$migration_details = InstaWP_Setting::get_option( 'instawp_migration_details', [] );
$migrate_id        = InstaWP_Setting::get_args_option( 'migrate_id', $migration_details );

if ( ! empty( $migrate_id ) ) {
	$nav_item_classes[] = 'loading';
}

?>

<form action="" method="post" class="<?php echo esc_attr( implode( ' ', $nav_item_classes ) ); ?> create active">

	<?php
	if ( instawp()->is_on_local ) {
		include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-create-local.php';
	} else if ( instawp()->is_connected ) {
		include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-create-staging.php';
	} else {
		include INSTAWP_PLUGIN_DIR . '/migrate/templates/part-create-connect.php';
	}
	?>

</form>