<?php
/**
 * Migrate template - Main
 */

$instawp_nav_items = array(
	'create'   => array(
		'label' => esc_html__( 'Create Staging', 'instawp-connect' ),
		'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.36804 0.883699C9.04694 0.111684 7.95329 0.111684 7.63219 0.883699L5.8014 5.28547L1.04932 5.66644C0.215863 5.73326 -0.122092 6.77337 0.512913 7.31732L4.13349 10.4187L3.02735 15.056C2.83334 15.8693 3.71812 16.5121 4.43167 16.0763L8.50011 13.5913L12.5686 16.0763C13.2821 16.5121 14.1669 15.8693 13.9729 15.056L12.8667 10.4187L16.4873 7.31732C17.1223 6.77337 16.7844 5.73326 15.9509 5.66644L11.1988 5.28547L9.36804 0.883699Z" fill="#9CA3AF"/> </svg>',
	),
	'sites'    => array(
		'label' => esc_html__( 'Staging Sites', 'instawp-connect' ),
		'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M10.0713 3.78565L9.98153 3.74076C8.3802 2.9401 6.54529 2.73868 4.80841 3.1729L2.5 3.75V12.5L4.80841 11.9229C6.54529 11.4887 8.3802 11.6901 9.98153 12.4908L10.0713 12.5356C11.6406 13.3203 13.4353 13.5299 15.1432 13.1281L17.7384 12.5174C17.5809 11.075 17.5 9.60942 17.5 8.125C17.5 6.65285 17.5795 5.19928 17.7345 3.76835L15.1432 4.37807C13.4353 4.77993 11.6406 4.5703 10.0713 3.78565Z" fill="#9CA3AF"/> <path d="M2.5 2.5V3.75M2.5 17.5V12.5M2.5 12.5L4.80841 11.9229C6.54529 11.4887 8.3802 11.6901 9.98153 12.4908L10.0713 12.5356C11.6406 13.3203 13.4353 13.5299 15.1432 13.1281L17.7384 12.5174C17.5809 11.075 17.5 9.60942 17.5 8.125C17.5 6.65285 17.5795 5.19928 17.7345 3.76835L15.1432 4.37807C13.4353 4.77993 11.6406 4.5703 10.0713 3.78565L9.98153 3.74076C8.3802 2.9401 6.54529 2.73868 4.80841 3.1729L2.5 3.75M2.5 12.5V3.75" stroke="#9CA3AF" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/> </svg>',
	),
//	'sync'     => array(
//		'label' => esc_html__( 'Sync', 'instawp-connect' ),
//		'icon'  => '<svg width="14" class="mr-2" height="16" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"/> </svg>',
//	),
	'settings' => array(
		'label' => esc_html__( 'Settings', 'instawp-connect' ),
		'icon'  => '<svg width="20" class="mr-2" height="20" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.34035 1.8539C8.99923 0.448767 7.00087 0.448766 6.65975 1.8539C6.43939 2.76159 5.39945 3.19235 4.6018 2.70633C3.36701 1.95396 1.95396 3.36701 2.70633 4.6018C3.19235 5.39945 2.76159 6.43939 1.8539 6.65975C0.448766 7.00087 0.448767 8.99923 1.8539 9.34035C2.76159 9.56071 3.19235 10.6006 2.70633 11.3983C1.95396 12.6331 3.36701 14.0461 4.6018 13.2938C5.39945 12.8077 6.43939 13.2385 6.65975 14.1462C7.00087 15.5513 8.99923 15.5513 9.34035 14.1462C9.56071 13.2385 10.6006 12.8077 11.3983 13.2938C12.6331 14.0461 14.0461 12.6331 13.2938 11.3983C12.8077 10.6006 13.2385 9.56071 14.1462 9.34035C15.5513 8.99923 15.5513 7.00087 14.1462 6.65975C13.2385 6.43939 12.8077 5.39945 13.2938 4.6018C14.0461 3.36701 12.6331 1.95396 11.3983 2.70633C10.6006 3.19235 9.56071 2.76159 9.34035 1.8539ZM8.00005 10.7C9.49122 10.7 10.7 9.49122 10.7 8.00005C10.7 6.50888 9.49122 5.30005 8.00005 5.30005C6.50888 5.30005 5.30005 6.50888 5.30005 8.00005C5.30005 9.49122 6.50888 10.7 8.00005 10.7Z"/> </svg>',
	),
);
$return_url        = urlencode( admin_url( 'tools.php?page=instawp' ) );
$connect_api_url   = InstaWP_Setting::get_api_domain() . '/authorize?source=InstaWP Connect&return_url=' . $return_url;

$current_page = isset( $_REQUEST['page'] ) ? sanitize_text_field( $_REQUEST['page'] ) : '';
$access_token = isset( $_REQUEST['access_token'] ) ? sanitize_text_field( $_REQUEST['access_token'] ) : '';
$success      = isset( $_REQUEST['success'] ) ? sanitize_text_field( $_REQUEST['success'] ) : '';

if ( empty( InstaWP_Setting::get_api_key() ) && 'instawp' == $current_page && 'true' == $success && ! empty( $access_token ) ) {
	InstaWP_Setting::instawp_generate_api_key( $access_token, $success );
}

?>

<!--<div class="flex border-b justify-between mb-4 border-grayCust-100">-->
<div class="flex border-b justify-between rounded-tl-lg rounded-tr-lg border-grayCust-100">
    <div class="flex items-center nav-items">

		<?php foreach ( $instawp_nav_items as $item_key => $item ) {

			$icon  = isset( $item['icon'] ) ? $item['icon'] : '';
			$label = isset( $item['label'] ) ? $item['label'] : '';

			printf( '<div id="%s" class="mr-4 nav-item"><a class="flex items-center px-5 py-5 border-b-2 border-transparent hover:text-primary-900 text-sm font-medium">%s<span>%s</span></a></div>', $item_key, $icon, $label );
		} ?>

    </div>

    <div class="flex items-center text-sm font-medium">

		<?php if ( empty( InstaWP_Setting::get_api_key() ) ) : ?>
            <div class="flex items-center text-grayCust-1300"><?php echo esc_html__( 'Please connect InstaWP account', 'instawp-connect' ); ?></div>
            <button type="button" class="instawp-button-connect px-4 rounded-lg py-2 border border-primary-900 text-primary-900 text-sm font-medium ml-3 mr-3">
                <span><?php echo esc_html__( 'Connect', 'instawp-connect' ); ?></span>
            </button>
		<?php else: ?>
            <span class="w-1 h-1 bg-primary-700 rounded-full mr-2"></span>
            <span class="text-primary-700 mr-3"><?php echo esc_html__( 'Your account is connected', 'instawp-connect' ); ?></span>
		<?php endif; ?>

    </div>
</div>

