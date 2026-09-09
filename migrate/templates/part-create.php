<?php
/**
 * Migrate template - Create Site
 */

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

global $instawp_settings;

$nav_item_classes  = array( 'nav-item-content' );
$migration_details = Helper::get_args_option( 'instawp_migration_details', $instawp_settings );
$migrate_id        = Helper::get_args_option( 'migrate_id', $migration_details );

if ( ! empty( $migrate_id ) ) {
	$nav_item_classes[] = 'loading';
}

/*
 * V4 staging resume.
 *
 * Named instawp-v4-resume, NOT instawp-v4-running: the latter is already the in-progress NOTICE
 * element inside the screen, and this sits on the form that CONTAINS it. `.find()` would not
 * match a container against itself so the two would not actually clash, but one class name
 * meaning two things one nesting level apart is a trap for whoever edits it next.
 *
 * Deliberately its OWN class rather than reusing `loading`: that one makes scripts.js start the V3
 * progress poll (instawp_migrate_progress), which reads a migrates_v3 row a V4 run does not have.
 * The two resumes are mutually exclusive in practice — a V4 run writes no migrate_id — but they are
 * kept separate by construction rather than by that coincidence.
 *
 * started_at rides along so the resumed screen can show a truthful elapsed time instead of counting
 * from the moment the page happened to load.
 */
$v4_run = array();

/*
 * Gated on the SAME condition that includes part-create-staging.php below, because screen 5 only
 * exists in that template. Without this a resumable run on a site that has since gone local, lost
 * its connection, or picked up an unsupported plugin would stamp the class anyway, and scripts.js
 * would set a screen that is not rendered and start a watcher against nothing.
 */
if ( ! instawp()->has_unsupported_plugins && instawp()->can_bundle && instawp()->is_connected && ! instawp()->is_on_local ) {
	$v4_run = InstaWP_Staging_V4::resumable_run();
}

if ( ! empty( $v4_run ) ) {
	$nav_item_classes[] = 'instawp-v4-resume';
}
?>

<form action="" method="post" class="<?php echo esc_attr( implode( ' ', $nav_item_classes ) ); ?> create active"
	<?php if ( ! empty( $v4_run ) ) : ?>
		data-v4-started-at="<?php echo esc_attr( Helper::get_args_option( 'started_at', $v4_run, 0 ) ); ?>"
	<?php endif; ?>
>
	<?php
	if ( instawp()->has_unsupported_plugins || ! instawp()->can_bundle ) {
		include INSTAWP_PLUGIN_DIR . 'migrate/templates/part-create-error.php';
	} elseif ( instawp()->is_connected && instawp()->is_on_local ) {
		include INSTAWP_PLUGIN_DIR . 'migrate/templates/part-create-local.php';
	} elseif ( instawp()->is_connected ) {
		include INSTAWP_PLUGIN_DIR . 'migrate/templates/part-create-staging.php';
	} else {
		include INSTAWP_PLUGIN_DIR . 'migrate/templates/part-create-connect.php';
	}
	?>
</form>