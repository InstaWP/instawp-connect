<?php
/**
 * Migrate template - Create Site
 */

global $wpdb;

$staging_sites = $wpdb->get_results( "SELECT * FROM " . INSTAWP_DB_TABLE_STAGING_SITES, ARRAY_A );

?>

<div class="nav-item-content sites bg-white box-shadow rounded-md p-6">
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
                            <tbody class="divide-y divide-gray-200 bg-white ">

							<?php foreach ( $staging_sites as $site ) :

								$site_name = isset( $site['site_name'] ) ? $site['site_name'] : '';
								$site_url = isset( $site['site_url'] ) ? $site['site_url'] : '';
								$username = isset( $site['username'] ) ? $site['username'] : '';
								$password = isset( $site['password'] ) ? $site['password'] : '';
								$datetime = isset( $site['datetime'] ) ? $site['datetime'] : '';
								$datetime = date( 'M j, Y', strtotime( $datetime ) );
								$auto_login_hash = isset( $site['auto_login_hash'] ) ? $site['auto_login_hash'] : '';
								$auto_login_url = InstaWP_Setting::get_api_domain() . '/wordpress-auto-login?site=' . $auto_login_hash;

								?>
                                <tr>
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
                </div>
                <!-- pagination -->
                <!--                <nav class="flex items-center justify-between border-t border-gray-200 mx-9 my-5">-->
                <!--                    <div class="-mt-px flex w-0 flex-1 justify-end">-->
                <!--                        <a href="#" class="inline-flex items-center border-t-2 border-transparent pt-3 pl-1 text-sm font-medium text-grayCust-300 hover:border-gray-300 hover:text-gray-700">-->
                <!--							--><?php //echo esc_html__( 'Next', 'instawp-connect' ); ?>
                <!--                            <svg class="ml-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#1F2937" aria-hidden="true">-->
                <!--                                <path fill-rule="evenodd" d="M2 10a.75.75 0 01.75-.75h12.59l-2.1-1.95a.75.75 0 111.02-1.1l3.5 3.25a.75.75 0 010 1.1l-3.5 3.25a.75.75 0 11-1.02-1.1l2.1-1.95H2.75A.75.75 0 012 10z" clip-rule="evenodd"/>-->
                <!--                            </svg>-->
                <!--                        </a>-->
                <!--                    </div>-->
                <!--                </nav>-->
            </div>
        </div>
    </div>
</div>