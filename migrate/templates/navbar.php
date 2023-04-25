<?php
/**
 * Migrate template - Main
 */

$instawp_nav_items = array(
	'create'   => array(
		'label' => esc_html__( 'Create New', 'instawp-connect' ),
		'icon'  => '<svg width="14" height="14" class="mr-2" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.99995 0.699951C7.49701 0.699951 7.89995 1.10289 7.89995 1.59995V6.09995H12.4C12.897 6.09995 13.3 6.5029 13.3 6.99995C13.3 7.49701 12.897 7.89995 12.4 7.89995H7.89995V12.4C7.89995 12.897 7.49701 13.3 6.99995 13.3C6.5029 13.3 6.09995 12.897 6.09995 12.4V7.89995H1.59995C1.10289 7.89995 0.699951 7.49701 0.699951 6.99995C0.699951 6.50289 1.10289 6.09995 1.59995 6.09995L6.09995 6.09995V1.59995C6.09995 1.10289 6.5029 0.699951 6.99995 0.699951Z"/></svg>',
	),
	'sites'    => array(
		'label' => esc_html__( 'Staging Sites', 'instawp-connect' ),
		'icon'  => '<svg width="18" class="mr-2" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd"d="M3.67447 8.10005H5.42557C5.50586 6.7083 5.77085 5.42621 6.1796 4.3941C4.87557 5.19426 3.93742 6.53268 3.67447 8.10005ZM8.9998 1.80005C5.02335 1.80005 1.7998 5.0236 1.7998 9.00005C1.7998 12.9765 5.02335 16.2 8.9998 16.2C12.9763 16.2 16.1998 12.9765 16.1998 9.00005C16.1998 5.0236 12.9763 1.80005 8.9998 1.80005ZM8.9998 3.60005C8.93136 3.60005 8.79089 3.6286 8.58103 3.83571C8.36732 4.04663 8.13356 4.39646 7.91785 4.8998C7.56803 5.71604 7.31222 6.82708 7.22894 8.10005H10.7707C10.6874 6.82708 10.4316 5.71604 10.0818 4.8998C9.86604 4.39646 9.63229 4.04663 9.41857 3.83571C9.20872 3.6286 9.06825 3.60005 8.9998 3.60005ZM12.574 8.10005C12.4938 6.7083 12.2288 5.42621 11.82 4.3941C13.124 5.19426 14.0622 6.53268 14.3251 8.10005H12.574ZM10.7707 9.90005H7.22894C7.31222 11.173 7.56803 12.2841 7.91785 13.1003C8.13356 13.6036 8.36732 13.9535 8.58103 14.1644C8.79089 14.3715 8.93136 14.4 8.9998 14.4C9.06825 14.4 9.20872 14.3715 9.41857 14.1644C9.63229 13.9535 9.86604 13.6036 10.0818 13.1003C10.4316 12.2841 10.6874 11.173 10.7707 9.90005ZM11.82 13.606C12.2288 12.5739 12.4938 11.2918 12.574 9.90005H14.3251C14.0622 11.4674 13.124 12.8058 11.82 13.606ZM6.1796 13.606C5.77086 12.5739 5.50586 11.2918 5.42557 9.90005H3.67447C3.93742 11.4674 4.87557 12.8058 6.1796 13.606Z"/></svg>',
	),
//	'sync'     => array(
//		'label' => esc_html__( 'Sync', 'instawp-connect' ),
//		'icon'  => '<svg width="14" class="mr-2" height="16" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"/> </svg>',
//	),
	'settings' => array(
		'label' => esc_html__( 'Settings', 'instawp-connect' ),
		'icon'  => '<svg width="16" class="mr-2" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.34035 1.8539C8.99923 0.448767 7.00087 0.448766 6.65975 1.8539C6.43939 2.76159 5.39945 3.19235 4.6018 2.70633C3.36701 1.95396 1.95396 3.36701 2.70633 4.6018C3.19235 5.39945 2.76159 6.43939 1.8539 6.65975C0.448766 7.00087 0.448767 8.99923 1.8539 9.34035C2.76159 9.56071 3.19235 10.6006 2.70633 11.3983C1.95396 12.6331 3.36701 14.0461 4.6018 13.2938C5.39945 12.8077 6.43939 13.2385 6.65975 14.1462C7.00087 15.5513 8.99923 15.5513 9.34035 14.1462C9.56071 13.2385 10.6006 12.8077 11.3983 13.2938C12.6331 14.0461 14.0461 12.6331 13.2938 11.3983C12.8077 10.6006 13.2385 9.56071 14.1462 9.34035C15.5513 8.99923 15.5513 7.00087 14.1462 6.65975C13.2385 6.43939 12.8077 5.39945 13.2938 4.6018C14.0461 3.36701 12.6331 1.95396 11.3983 2.70633C10.6006 3.19235 9.56071 2.76159 9.34035 1.8539ZM8.00005 10.7C9.49122 10.7 10.7 9.49122 10.7 8.00005C10.7 6.50888 9.49122 5.30005 8.00005 5.30005C6.50888 5.30005 5.30005 6.50888 5.30005 8.00005C5.30005 9.49122 6.50888 10.7 8.00005 10.7Z"/> </svg>',
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

<div class="flex border-b justify-between mb-4 border-grayCust-100">
    <div class="flex items-center nav-items">

		<?php foreach ( $instawp_nav_items as $item_key => $item ) {

			$icon  = isset( $item['icon'] ) ? $item['icon'] : '';
			$label = isset( $item['label'] ) ? $item['label'] : '';

			printf( '<div id="%s" class="mr-8 nav-item"><a class="flex items-center px-2 py-4 text-grayCust-50 border-b-2 border-transparent text-sm font-medium hover:text-primary-900">%s<span>%s</span></a></div>', $item_key, $icon, $label );
		} ?>

    </div>

    <div class="flex items-center text-sm font-medium">

		<?php if ( empty( InstaWP_Setting::get_api_key() ) ) : ?>
            <div class="flex items-center text-grayCust-1300"><?php echo esc_html__( 'Please connect InstaWP account', 'instawp-connect' ); ?></div>
            <button type="button" class="instawp-button-connect px-4 rounded-lg py-2 border border-primary-900 text-primary-900 text-sm font-medium ml-3">
                <span><?php echo esc_html__( 'Connect', 'instawp-connect' ); ?></span>
            </button>
		<?php else: ?>
            <span class="w-1 h-1 bg-primary-700 rounded-full mr-2"></span>
            <span class="text-primary-700"><?php echo esc_html__( 'Your account is connected', 'instawp-connect' ); ?></span>
		<?php endif; ?>

    </div>
</div>

