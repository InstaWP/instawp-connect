<?php
/**
 * Migrate template - Main
 */

use InstaWP\Connect\Helpers;

if ( ! class_exists( 'WP_Debug_Data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
}

$api_data       = Helpers\Option::get_option( 'instawp_api_options', array() );
$sizes_data     = WP_Debug_Data::get_sizes();
$active_plugins = ( array ) get_option( 'active_plugins', array() );
$details        = array(
    'PHP Version'       => phpversion(),
    'WordPress Version' => get_bloginfo( 'version' ),
    'Site URL'          => get_bloginfo( 'url' ),
    'Plugin Version'    => INSTAWP_PLUGIN_VERSION,
    'Connected Name'    => ! empty( $api_data['response']['name'] ) ? $api_data['response']['name'] : '',
    'Connect ID'        => instawp_get_connect_id(),
    'API Domain'        => Helpers\Helper::get_api_domain(),
    'API Key'           => Helpers\Helper::get_api_key(),
    'Hashed API Key'    => Helpers\Helper::get_api_key( true ),
    'Root Path'         => $sizes_data['wordpress_size']['path'],
    'WordPress Size'    => $sizes_data['wordpress_size']['debug'],
    'Themes Size'       => $sizes_data['themes_size']['debug'],
    'Plugins Size'      => $sizes_data['plugins_size']['debug'],
    'Uploads Size'      => $sizes_data['uploads_size']['debug'],
    'Database Size'     => $sizes_data['database_size']['debug'],
    'Total Site Size'   => $sizes_data['total_size']['debug'],
    'Active Theme'      => wp_get_theme()->get( 'Name' ),
    'Active Plugin(s)'  => join( ",\n", $active_plugins ),
)
?>

<div class="wrap instawp-wrap box-width pt-4">
    <div class="w-full">
        <div class="bg-white shadow-md rounded-lg p-6">
            <table class="w-full border">
                <?php foreach ( array_filter( $details ) as $key => $value ) { ?>
                    <tr>
                        <td class="border p-2"><?= esc_html( $key ); ?></td>
                        <td class="border p-2"><?= esc_html( $value ); ?></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
</div>