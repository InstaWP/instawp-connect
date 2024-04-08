<?php
/**
 * Migrate template - Main
 */

global $wpdb;

$offset     = ! empty( $_GET['offset'] ) ? intval( $_GET['offset'] ) : 0;
$count      = ! empty( $_GET['count'] ) ? intval( $_GET['count'] ) : 10;
$table_name = ! empty( $_GET['table_name'] ) ? sanitize_text_field( wp_unslash( $_GET['table_name'] ) ) : '';
$results    = $wpdb->get_results( "SELECT * FROM {$table_name} LIMIT {$offset}, {$count}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
?>

<div class="wrap instawp-wrap pt-4">
    <div class="w-full">
        <div class="bg-white shadow-md rounded-lg p-6">
            <?php if ( ! empty( $results ) ) {
                $head_items = array_keys( ( array ) $results[0] ); ?>
                    <table class="w-full border text-center">
                        <thead>
                            <tr>
                                <?php foreach ( $head_items as $head_item ) { ?>
                                    <th class="border p-2"><?= esc_html( $head_item ); ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $results as $values ) { ?>
                            <tr>
                                <?php foreach ( ( array ) $values as $value ) { ?>
                                    <td class="border p-2 break-all whitespace-normal"><?= esc_html( $value ); ?></td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
            <?php } else {
                echo esc_html__( 'No results found!', 'instawp-connect' );
            } ?>
        </div>
    </div>
</div>