<?php
/**
 * Migrate template - Main
 */

global $wpdb;

$offset  = ! empty( $_GET['offset'] ) ? intval( $_GET['offset'] ) : 0;
$count   = ! empty( $_GET['count'] ) ? intval( $_GET['count'] ) : 10;
$results = $wpdb->get_results( "SHOW TABLES LIKE 'iwp_db_sent'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

if ( ! empty( $results ) ) {
	$results = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM `iwp_db_sent` WHERE `completed`=%d LIMIT {$offset}, {$count}", 0 ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}
$db_info = wp_list_pluck( instawp_get_database_details(), 'size', 'name' );
?>

<div class="wrap instawp-wrap pt-4">
    <div class="w-full">
        <div class="bg-white shadow-md rounded-lg p-6">
            <table class="w-full border text-center">
                <thead>
                    <tr>
                        <th class="border p-2">ID</th>
                        <th class="border p-2">Table Name</th>
                        <th class="border p-2">Total Rows Sent</th>
                        <th class="border p-2">Total Rows</th>
                        <th class="border p-2">Total Size</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! empty( $results ) ) {
                    foreach ( $results as $key => $result ) { ?>
                        <tr>
                            <td class="border p-2"><?= esc_html( $result->id ); ?></td>
                            <td class="border p-2"><a href="<?= esc_url( admin_url( "tools.php?page=instawp&debug=db-table&table_name={$result->table_name}&offset={$result->offset}") ); ?>" target="_blank"><?= esc_html( $result->table_name ); ?></a></td>
                            <td class="border p-2"><?= esc_html( $result->offset ); ?></td>
                            <td class="border p-2"><?= esc_html( $result->rows_total ); ?></td>
                            <td class="border p-2"><?= esc_html( instawp()->get_file_size_with_unit( $db_info[ $result->table_name ] ) ); ?></td>
                        </tr>
	                <?php }
                } else { ?>
                    <tr>
                        <td class="border p-2" colspan="5">No results found!</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>