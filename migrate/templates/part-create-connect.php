<?php
/**
 * Connect to InstaWP Screen
 */

$features = [
    'Plugin & Theme Updates',
    'Magic Login',
    'Database Manager',
    'Purge Cache',
    'Manage Website',
    'Manage Users',
    'Staging Creation',
];
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
        
        <div class="flex flex-wrap items-center justify-center gap-4 mb-5 w-[760px] border border-dashed border-gray-300 p-5 border-x-0">
            <?php foreach ( $features as $feature ) :?>
                <div class="flex items-center gap-2">
                    <svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="0.5" width="16" height="16" rx="8" fill="#D1FAE5"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M11.8986 4.92661L7.12531 9.53328L5.85865 8.17994C5.62531 7.95994 5.25865 7.94661 4.99198 8.13328C4.73198 8.32661 4.65865 8.66661 4.81865 8.93994L6.31865 11.3799C6.46531 11.6066 6.71865 11.7466 7.00531 11.7466C7.27865 11.7466 7.53865 11.6066 7.68531 11.3799C7.92531 11.0666 12.5053 5.60661 12.5053 5.60661C13.1053 4.99328 12.3786 4.45328 11.8986 4.91994V4.92661Z" fill="#15B881"/>
                    </svg>
                    <span class="text-sm font-normal text-grayCust-50"><?php echo esc_html( $feature );?></span>
                </div>
            <?php endforeach;?>
        </div>
        <a class="instawp-button-connect cursor-pointer	px-7 py-3 inline-flex items-center mx-auto rounded-md shadow-sm bg-secondary text-white hover:text-white active:text-white focus:text-white focus:shadow-none font-medium text-sm">
            <img src="<?php echo esc_url( instaWP::get_asset_url( 'migrate/assets/images/icon-plus.svg' ) ); ?>" class="mr-2" alt="">
            <span><?php esc_html_e( 'Connect with InstaWP', 'instawp-connect' ); ?></span>
        </a>
    </div>
</div>
