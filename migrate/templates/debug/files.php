<?php
/**
 * Migrate template - Main
 */

global $wpdb;

$offset  = ! empty( $_GET['offset'] ) ? intval( $_GET['offset'] ) : 0;
$count   = ! empty( $_GET['count'] ) ? intval( $_GET['count'] ) : 10;
$results = $wpdb->get_results( "SHOW TABLES LIKE 'iwp_files_sent'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

if ( ! empty( $results ) ) {
	$results = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM `iwp_files_sent` WHERE `sent`=%d LIMIT {$offset}, {$count}", 0 ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}
?>

<div class="wrap instawp-wrap pt-4">
    <div class="w-full">
        <div class="bg-white shadow-md rounded-lg p-6">
            <table class="w-full border text-center">
                <thead>
                    <tr>
                        <th class="border p-2">ID</th>
                        <th class="border p-2">File Path</th>
                        <th class="border p-2">Size</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! empty( $results ) ) {
                    foreach ( $results as $result ) { ?>
                        <tr>
                            <td class="border p-2"><?= esc_html( $result->id ); ?></td>
                            <td class="border p-2"><?= esc_html( $result->filepath ); ?></td>
                            <td class="border p-2"><?= esc_html( instawp()->get_file_size_with_unit( $result->size ) ); ?></td>
                        </tr>
                    <?php }
                } else { ?>
                    <tr>
                        <td class="border p-2" colspan="3">No results found!</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>