<?php
/**
 * Migrate template - Create Site
 */

global $wpdb;

$staging_sites       = $wpdb->get_results( "SELECT * FROM " . INSTAWP_DB_TABLE_STAGING_SITES, ARRAY_A );
$staging_sites_count = count( $staging_sites );
$pagination          = 10;
?>

<div class="nav-item-content sites bg-white rounded-md p-6" data-pagination="<?php echo esc_attr( $pagination ); ?>">
    <?php if ( empty( $staging_sites ) || $staging_sites_count < 1 ) { ?>
        <div class="mt-2">
            <div class="w-full">
                <div class="text-center ">
                    <div class="mb-4">
                        <svg width="38" class="mx-auto" height="30" viewBox="0 0 38 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 17H25H13ZM19 11V23V11ZM1 25V5C1 3.93913 1.42143 2.92172 2.17157 2.17157C2.92172 1.42143 3.93913 1 5 1H17L21 5H33C34.0609 5 35.0783 5.42143 35.8284 6.17157C36.5786 6.92172 37 7.93913 37 9V25C37 26.0609 36.5786 27.0783 35.8284 27.8284C35.0783 28.5786 34.0609 29 33 29H5C3.93913 29 2.92172 28.5786 2.17157 27.8284C1.42143 27.0783 1 26.0609 1 25Z" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="text-sm font-medium text-grayCust-200 mb-1"><?php echo esc_html__( 'No Staging Sites found!', 'instawp-connect' ); ?></div>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div>
            <div class="mb-6">
                <div class="text-grayCust-200 text-lg font-medium"><?php echo esc_html__( 'Staging Sites', 'instawp-connect' ); ?></div>
            </div>
            <div class="mt-6 flow-root">
                <div class="-my-2 -mx-6 overflow-x-auto lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-4  uppercase text-left text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'Site Name', 'instawp-connect' ); ?></th>
                                    <th scope="col" class="px-4 py-4 text-left uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'Username', 'instawp-connect' ); ?></th>
                                    <th scope="col" class="px-4 py-4 text-left uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'Password', 'instawp-connect' ); ?></th>
                                    <th scope="col" class="px-4 py-4 text-left uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'Created date', 'instawp-connect' ); ?></th>
                                    <th scope="col" class="px-4 py-4 text-center uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'Actions', 'instawp-connect' ); ?></th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <?php foreach ( array_reverse( $staging_sites ) as $index => $site ) :

                                        $site_name       = isset( $site['site_name'] ) ? $site['site_name'] : '';
                                        $site_url        = isset( $site['site_url'] ) ? $site['site_url'] : '';
                                        $username        = isset( $site['username'] ) ? $site['username'] : '';
                                        $password        = isset( $site['password'] ) ? $site['password'] : '';
                                        $datetime        = isset( $site['datetime'] ) ? $site['datetime'] : '';
                                        $datetime        = date( 'M j, Y', strtotime( $datetime ) );
                                        $auto_login_hash = isset( $site['auto_login_hash'] ) ? $site['auto_login_hash'] : '';
                                        $auto_login_url  = InstaWP_Setting::get_api_domain() . '/wordpress-auto-login?site=' . $auto_login_hash;

                                        ?>
                                        <tr class="staging-site-list">
                                            <td class="whitespace-nowrap py-8 px-4 text-xs font-medium flex items-center text-grayCust-300">
                                                <?php
                                                printf( '<img src="%s" class="mr-2" alt=""><a target="_blank" href="%s">%s</a>',
                                                    instawp()::get_asset_url( 'migrate/assets/images/glob.svg' ),
                                                    esc_url_raw( $site_url ), $site_name
                                                );
                                                ?>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-6 font-medium text-xs text-grayCust-300">
                                                <span><?php echo esc_html( $username ); ?></span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-8 font-medium text-xs text-grayCust-300 flex items-center">
                                                <span><?php echo esc_html( $password ); ?></span>
                                                <!-- <img src="img/off-eye.svg" class="ml-2 cursor-pointer" alt="">-->
                                            </td>
                                            <td class="whitespace-nowrap text-left px-4 py-6 font-medium text-xs text-grayCust-300">
                                                <span><?php echo esc_html( $datetime ); ?></span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-6 font-medium text-xs text-grayCust-300">
                                                <div class="flex items-center justify-center">
                                                    <a href="<?php echo esc_url( $auto_login_url ); ?>" target="_blank" type="button" class="relative flex items-center px-2.5 w-11 h-9 lg:px-3 rounded-md border border-grayCust-350 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-0 focus:border-grayCust-350">
                                                        <svg width="15" height="14" class="w-3 xl2:w-4" viewBox="0 0 15 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M6.52217 3.11111L5.54217 4.2L7.36217 6.22222H0.222168V7.77778H7.36217L5.54217 9.8L6.52217 10.8889L10.0222 7L6.52217 3.11111ZM12.8222 12.4444H7.22217V14H12.8222C13.5922 14 14.2222 13.3 14.2222 12.4444V1.55556C14.2222 0.7 13.5922 0 12.8222 0H7.22217V1.55556H12.8222V12.4444Z" fill="#1F2937"/>
                                                        </svg>
                                                    </a>
                                                    <button type="button" class="hidden -ml-px relative inline-flex items-center px-2 w-11 h-9 lg:px-3 border border-grayCust-350 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-0 focus:border-grayCust-350">
                                                        <svg width="18" height="19" viewBox="0 0 18 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M3.6001 10.3999V13.0999C3.6001 14.5912 6.21923 15.7999 9.4501 15.7999C12.681 15.7999 15.3001 14.5912 15.3001 13.0999V10.3999C15.3001 11.8912 12.681 13.0999 9.4501 13.0999C6.21923 13.0999 3.6001 11.8912 3.6001 10.3999Z" fill="#1F2937"/>
                                                            <path d="M3.6001 6.7998V9.0498C3.6001 10.2926 6.21923 11.2998 9.4501 11.2998C12.681 11.2998 15.3001 10.2926 15.3001 9.0498V6.7998C15.3001 8.04255 12.681 9.0498 9.4501 9.0498C6.21923 9.0498 3.6001 8.04255 3.6001 6.7998Z" fill="#1F2937"/>
                                                            <path d="M15.3001 4.9998C15.3001 6.4911 12.681 7.6998 9.4501 7.6998C6.21923 7.6998 3.6001 6.4911 3.6001 4.9998C3.6001 3.5085 6.21923 2.2998 9.4501 2.2998C12.681 2.2998 15.3001 3.5085 15.3001 4.9998Z" fill="#1F2937"/>
                                                        </svg>
                                                    </button>
                                                    <button type="button" class="hidden -ml-px relative inline-flex items-center px-2 w-11 h-9 lg:px-3 border border-grayCust-350 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-0 focus:border-grayCust-350">
                                                        <svg width="15" height="14" viewBox="0 0 15 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M7.07783 9.1H8.47783V10.5H7.07783V9.1ZM7.07783 3.5H8.47783V7.7H7.07783V3.5ZM7.77083 0C3.90683 0 0.777832 3.136 0.777832 7C0.777832 10.864 3.90683 14 7.77083 14C11.6418 14 14.7778 10.864 14.7778 7C14.7778 3.136 11.6418 0 7.77083 0ZM7.77783 12.6C4.68383 12.6 2.17783 10.094 2.17783 7C2.17783 3.906 4.68383 1.4 7.77783 1.4C10.8718 1.4 13.3778 3.906 13.3778 7C13.3778 10.094 10.8718 12.6 7.77783 12.6Z" fill="#1F2937"/>
                                                        </svg>
                                                    </button>
                                                    <button type="button" class="hidden -ml-px relative inline-flex items-center px-2 rounded-r-md w-10 h-9 lg:px-3 border border-grayCust-350 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-0 focus:border-grayCust-350">
                                                        <svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M0.541436 0.563653C0.892907 0.212181 1.46275 0.212181 1.81423 0.563653L4.77783 3.52726L7.74143 0.563653C8.0929 0.212181 8.66274 0.212181 9.01422 0.563653C9.36569 0.915125 9.36569 1.48497 9.01422 1.83644L5.41422 5.43644C5.06275 5.78792 4.4929 5.78792 4.14143 5.43644L0.541436 1.83644C0.189964 1.48497 0.189964 0.915125 0.541436 0.563653Z" fill="#1F2937"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ( $staging_sites_count > $pagination ) { ?>
                            <nav class="flex items-center justify-end mt-6">
                                <div class="pagination">
                                    <span class="prev-item p-2 pr-5 disabled"><?php esc_html_e( '« Previous', 'instawp-connect' ); ?></span>
                                    <span class="nav-item">
                                        <?php
                                        $page = 1;
                                        for ( $x = 1; $x <= $staging_sites_count; $x += $pagination ) {
                                            $css_class = ( $x == 1 ) ? 'page-item p-2 active' : 'page-item p-2';
                                            echo '<span class="' . $css_class . '" data-item="' . $page . '">' . $page . '</span>';
                                            $page++;
                                        }
                                        ?>
                                    </span>
                                    <span class="next-item p-2 pl-5 pr-0"><?php esc_html_e( 'Next »', 'instawp-connect' ); ?></span>
                                </div>
                            </nav>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>