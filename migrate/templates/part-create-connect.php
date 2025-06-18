<?php
/**
 * Connect to InstaWP Screen
 */
defined( 'ABSPATH' ) || exit;

$features = array(
    'Plugin & Theme Updates',
    'Database Manager',
    'Manage Website',
    'Manage Users',
    'Magic Login',
    'Purge Cache',
    'Staging Creation',
);

$pro_features = array(
    'Scheduled Updates',
    'Automated Vulnerability Scanning',
    'Activity Logs',
    'Report Generation',
    'Automated Core Web Vitals Scanning',
    'Visual Regression (soon)',
    'Uptime Monitoring',
    'WP Config Manager',
    'Backups (soon)',
);
?>

<div class="bg-white text-center rounded-md py-20 flex items-center justify-center">
    <div class="flex flex-col items-center justify-center">
        <div class="mb-4 flex items-center justify-center bg-gray-200 rounded-full border-8 border-gray-100 p-3">
            <svg width="25" height="24" viewBox="0 0 25 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11.5 4.5H18.8C19.9201 4.5 20.4802 4.5 20.908 4.71799C21.2843 4.90973 21.5903 5.21569 21.782 5.59202C22 6.01984 22 6.57989 22 7.7V9C22 9.93188 22 10.3978 21.8478 10.7654C21.6448 11.2554 21.2554 11.6448 20.7654 11.8478C20.3978 12 19.9319 12 19 12M13.5 19.5H6.2C5.0799 19.5 4.51984 19.5 4.09202 19.282C3.71569 19.0903 3.40973 18.7843 3.21799 18.408C3 17.9802 3 17.4201 3 16.3V15C3 14.0681 3 13.6022 3.15224 13.2346C3.35523 12.7446 3.74458 12.3552 4.23463 12.1522C4.60218 12 5.06812 12 6 12M10.8 14.5H14.2C14.48 14.5 14.62 14.5 14.727 14.4455C14.8211 14.3976 14.8976 14.3211 14.9455 14.227C15 14.12 15 13.98 15 13.7V10.3C15 10.02 15 9.87996 14.9455 9.773C14.8976 9.67892 14.8211 9.60243 14.727 9.5545C14.62 9.5 14.48 9.5 14.2 9.5H10.8C10.52 9.5 10.38 9.5 10.273 9.5545C10.1789 9.60243 10.1024 9.67892 10.0545 9.773C10 9.87996 10 10.02 10 10.3V13.7C10 13.98 10 14.12 10.0545 14.227C10.1024 14.3211 10.1789 14.3976 10.273 14.4455C10.38 14.5 10.52 14.5 10.8 14.5ZM18.3 22H21.7C21.98 22 22.12 22 22.227 21.9455C22.3211 21.8976 22.3976 21.8211 22.4455 21.727C22.5 21.62 22.5 21.48 22.5 21.2V17.8C22.5 17.52 22.5 17.38 22.4455 17.273C22.3976 17.1789 22.3211 17.1024 22.227 17.0545C22.12 17 21.98 17 21.7 17H18.3C18.02 17 17.88 17 17.773 17.0545C17.6789 17.1024 17.6024 17.1789 17.5545 17.273C17.5 17.38 17.5 17.52 17.5 17.8V21.2C17.5 21.48 17.5 21.62 17.5545 21.727C17.6024 21.8211 17.6789 21.8976 17.773 21.9455C17.88 22 18.02 22 18.3 22ZM3.3 7H6.7C6.98003 7 7.12004 7 7.227 6.9455C7.32108 6.89757 7.39757 6.82108 7.4455 6.727C7.5 6.62004 7.5 6.48003 7.5 6.2V2.8C7.5 2.51997 7.5 2.37996 7.4455 2.273C7.39757 2.17892 7.32108 2.10243 7.227 2.0545C7.12004 2 6.98003 2 6.7 2H3.3C3.01997 2 2.87996 2 2.773 2.0545C2.67892 2.10243 2.60243 2.17892 2.5545 2.273C2.5 2.37996 2.5 2.51997 2.5 2.8V6.2C2.5 6.48003 2.5 6.62004 2.5545 6.727C2.60243 6.82108 2.67892 6.89757 2.773 6.9455C2.87996 7 3.01997 7 3.3 7Z" stroke="#374151" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>

        </div>
        <div class="text-lg font-medium text-grayCust-200 mb-1"><?php esc_html_e( 'Connect this site to your InstaWP Account', 'instawp-connect' ); ?></div>
        <div class="text-sm font-normal text-grayCust-50 mb-5"><?php esc_html_e( 'Authorize the InstaWP Connect Plugin for staging creation, site management, and more.', 'instawp-connect' ); ?></div>
        
        <div class="flex items-center mb-5 bg-gray-50 py-5 rounded w-[950px]"> 
            <div class="flex flex-col items-center justify-center gap-1 px-8 w-28">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.8116 3.2538C19.2552 3.3716 19.6268 3.67418 19.832 4.08466C20.6057 5.63206 21.1831 6.57121 21.7641 7.33267C22.3521 8.10344 22.9742 8.73442 23.9204 9.68064C26.107 11.8673 27.2008 14.7364 27.2008 17.6003C27.2008 20.4641 26.107 23.3332 23.9204 25.5198C19.5465 29.8937 12.4551 29.8937 8.08118 25.5198C5.89457 23.3332 4.80079 20.4641 4.80078 17.6003C4.80078 14.7364 5.89456 11.8673 8.08119 9.68064C8.53878 9.22304 9.22697 9.08615 9.82485 9.3338C10.4227 9.58145 10.8126 10.1649 10.8126 10.812C10.8126 12.6037 10.9244 13.9689 11.4485 15.0579C11.7366 15.6566 12.1861 16.242 12.9609 16.7644C13.1459 15.0672 13.4848 12.9994 13.9428 11.0366C14.3034 9.49132 14.7518 7.94856 15.2856 6.66205C15.5526 6.01857 15.8545 5.40586 16.1973 4.87685C16.5311 4.36192 16.9599 3.83792 17.5134 3.46892C17.8953 3.21435 18.368 3.13601 18.8116 3.2538ZM19.3949 24.1943C17.5204 26.0688 14.4812 26.0688 12.6067 24.1943C11.6694 23.2571 11.2008 22.0286 11.2008 20.8002C11.2008 20.8002 12.6067 21.6002 15.2009 21.6002C15.2009 20.0002 16.0009 15.2002 17.2009 14.4002C18.0009 16.0002 18.4576 16.4688 19.3949 17.4061C20.3322 18.3434 20.8008 19.5718 20.8008 20.8002C20.8008 22.0286 20.3322 23.2571 19.3949 24.1943Z" fill="#9CA3AF"/>
                </svg>
                <span class="font-medium"><?php esc_html_e( 'Free', 'instawp-connect' ); ?></span>
            </div>
            <div class="grid grid-cols-[auto_auto_auto_auto] items-center gap-x-8 gap-y-4 border-l border-dashed pl-6">
                <?php foreach ( $features as $feature ) :?>
                    <div class="flex items-center gap-2">
                        <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="0.5" width="16" height="16" rx="8" fill="#D1FAE5"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M11.8986 4.92661L7.12531 9.53328L5.85865 8.17994C5.62531 7.95994 5.25865 7.94661 4.99198 8.13328C4.73198 8.32661 4.65865 8.66661 4.81865 8.93994L6.31865 11.3799C6.46531 11.6066 6.71865 11.7466 7.00531 11.7466C7.27865 11.7466 7.53865 11.6066 7.68531 11.3799C7.92531 11.0666 12.5053 5.60661 12.5053 5.60661C13.1053 4.99328 12.3786 4.45328 11.8986 4.91994V4.92661Z" fill="#005E54"/>
                        </svg>
                        <span class="text-sm font-normal text-grayCust-50"><?php echo esc_html( $feature );?></span>
                    </div>
                <?php endforeach;?>
            </div>
        </div>

        <div class="flex items-center mb-5 bg-amber-50 py-5 rounded w-[950px]">
            <div class="flex flex-col items-center justify-center gap-1 px-8 w-28">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M18.8116 3.2538C19.2552 3.3716 19.6268 3.67418 19.832 4.08466C20.6057 5.63206 21.1831 6.57121 21.7641 7.33267C22.3521 8.10344 22.9742 8.73442 23.9204 9.68064C26.107 11.8673 27.2008 14.7364 27.2008 17.6003C27.2008 20.4641 26.107 23.3332 23.9204 25.5198C19.5465 29.8937 12.4551 29.8937 8.08118 25.5198C5.89457 23.3332 4.80079 20.4641 4.80078 17.6003C4.80078 14.7364 5.89456 11.8673 8.08119 9.68064C8.53878 9.22304 9.22697 9.08615 9.82485 9.3338C10.4227 9.58145 10.8126 10.1649 10.8126 10.812C10.8126 12.6037 10.9244 13.9689 11.4485 15.0579C11.7366 15.6566 12.1861 16.242 12.9609 16.7644C13.1459 15.0672 13.4848 12.9994 13.9428 11.0366C14.3034 9.49132 14.7518 7.94856 15.2856 6.66205C15.5526 6.01857 15.8545 5.40586 16.1973 4.87685C16.5311 4.36192 16.9599 3.83792 17.5134 3.46892C17.8953 3.21435 18.368 3.13601 18.8116 3.2538ZM19.3949 24.1943C17.5204 26.0688 14.4812 26.0688 12.6067 24.1943C11.6694 23.2571 11.2008 22.0286 11.2008 20.8002C11.2008 20.8002 12.6067 21.6002 15.2009 21.6002C15.2009 20.0002 16.0009 15.2002 17.2009 14.4002C18.0009 16.0002 18.4576 16.4688 19.3949 17.4061C20.3322 18.3434 20.8008 19.5718 20.8008 20.8002C20.8008 22.0286 20.3322 23.2571 19.3949 24.1943Z" fill="#FBBF24"/>
                </svg>
                <span class="font-medium"><?php esc_html_e( 'Advanced', 'instawp-connect' ); ?></span>
            </div>
            <div class="grid grid-cols-[auto_auto_auto] items-center gap-x-8 gap-y-4 border-l border-dashed pl-6">
                <?php foreach ( $pro_features as $feature ) :?>
                    <div class="flex items-center gap-2">
                        <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="0.5" width="16" height="16" rx="8" fill="#FEF3C7"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M11.8986 4.92661L7.12531 9.53328L5.85865 8.17994C5.62531 7.95994 5.25865 7.94661 4.99198 8.13328C4.73198 8.32661 4.65865 8.66661 4.81865 8.93994L6.31865 11.3799C6.46531 11.6066 6.71865 11.7466 7.00531 11.7466C7.27865 11.7466 7.53865 11.6066 7.68531 11.3799C7.92531 11.0666 12.5053 5.60661 12.5053 5.60661C13.1053 4.99328 12.3786 4.45328 11.8986 4.91994V4.92661Z" fill="#FBBF24"/>
                        </svg>
                        <span class="text-sm font-normal text-grayCust-50"><?php echo esc_html( $feature );?></span>
                    </div>
                <?php endforeach;?>
            </div>
        </div>
        <a class="instawp-button-connect cursor-pointer	px-7 py-3 inline-flex items-center mx-auto rounded-md shadow-sm bg-secondary text-white hover:text-white active:text-white focus:text-white focus:shadow-none font-medium text-sm">
            <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/icon-plus.svg' ) ); ?>" class="mr-2" alt="">
            <span><?php esc_html_e( 'Connect with InstaWP', 'instawp-connect' ); ?></span>
        </a>
    </div>
</div>
