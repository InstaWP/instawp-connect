<?php
/**
 * Migrate template - Sync
 */

?>

<div class="nav-item-content sync bg-white box-shadow rounded-md p-6 data-padding">
    <div class="data-listening">
<!--        <div class="bg-white  box-shadow rounded-md data-padding flex items-center justify-center">-->
            <div class="w-full">
                <div class="text-center ">
                    <div class="mb-4">
                        <svg width="38" class="mx-auto" height="30" viewBox="0 0 38 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13 17H25H13ZM19 11V23V11ZM1 25V5C1 3.93913 1.42143 2.92172 2.17157 2.17157C2.92172 1.42143 3.93913 1 5 1H17L21 5H33C34.0609 5 35.0783 5.42143 35.8284 6.17157C36.5786 6.92172 37 7.93913 37 9V25C37 26.0609 36.5786 27.0783 35.8284 27.8284C35.0783 28.5786 34.0609 29 33 29H5C3.93913 29 2.92172 28.5786 2.17157 27.8284C1.42143 27.0783 1 26.0609 1 25Z" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="text-sm font-medium text-grayCust-200 mb-1"><?php echo esc_html__( 'No Data found!', 'instawp-connect' ); ?></div>
                    <div class="text-sm font-normal text-grayCust-50"><?php echo esc_html__( 'Start Listening for Changes', 'instawp-connect' ); ?></div>
                    <!-- <label class="switch">
					   <input type="checkbox" id="switch-id" >
					   <span class="slider round"></span>
					</label> -->
                </div>
            </div>
<!--        </div>-->
    </div>

    <div class="sync-listining">
<!--        <div class="bg-white  box-shadow rounded-md p-6 flex items-center justify-center">-->
            <div class="w-full">
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <div >
                            <div class="text-grayCust-200 text-lg font-medium"><?php echo esc_html__( 'Listening for Changes', 'instawp-connect' ); ?></div>
                            <div class="text-grayCust-50 text-sm font-normal"><?php echo esc_html__( 'Lorem ipsum demo text the default payment method will be used for any biling purposes.', 'instawp-connect' ); ?></div>
                        </div>
                        <label class="switch-toggle">
                            <input type="checkbox" checked>
                            <span class="slider-toggle round-toggle"></span>
                        </label>
                    </div>
                    <div class="mt-8 flow-root">
                        <div class="-my-2 -mx-6 overflow-x-auto lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-300">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-4  uppercase text-left text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'event', 'instawp-connect' ); ?></th>
                                            <th scope="col" class="px-6 py-4 text-left uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'event details', 'instawp-connect' ); ?></th>
                                            <th scope="col" class="px-6 py-4 text-center uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'Status', 'instawp-connect' ); ?></th>
                                            <th scope="col" class="px-6 py-4 text-center uppercase text-xs font-medium text-grayCust-900"><?php echo esc_html__( 'Actions', 'instawp-connect' ); ?></th>
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white ">
                                        <tr>
                                            <td class="whitespace-nowrap py-6 px-6 text-xs font-medium text-grayCust-300"><?php echo esc_html__( 'Lorem ipsum demo', 'instawp-connect' ); ?></td>
                                            <td class="whitespace-nowrap px-6 py-6 font-medium text-xs text-grayCust-300"><?php echo esc_html__( 'Lorem ipsum demo text the default payment.', 'instawp-connect' ); ?></td>
                                            <td class="whitespace-nowrap px-6 py-6 text-center font-medium text-xs text-grayCust-300"><div class="py-1 px-4 inline-block rounded-full text-primary-900 font-medium " style="background-color: #D1FAE5;"><?php echo esc_html__( 'Synced', 'instawp-connect' ); ?></div>
                                            </td>
                                            <td class="whitespace-nowrap cursor-pointer  text-center px-6 py-6 font-medium text-xs text-primary-900"><?php echo esc_html__( 'Sync', 'instawp-connect' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="whitespace-nowrap py-6 px-6 text-xs font-medium text-grayCust-300"><?php echo esc_html__( 'Lorem ipsum demo', 'instawp-connect' ); ?></td>
                                            <td class="whitespace-nowrap px-6 py-6 font-medium text-xs text-grayCust-300"><?php echo esc_html__( 'Lorem ipsum demo text the default payment.', 'instawp-connect' ); ?></td>
                                            <td class="whitespace-nowrap px-6 py-6 text-center font-medium text-xs text-grayCust-300"><div class="py-1 px-4 inline-block rounded-full font-medium " style="background-color: #FEE2E2;color: #991B1B;"><?php echo esc_html__( 'Failed', 'instawp-connect' ); ?></div>
                                            </td>
                                            <td class="whitespace-nowrap cursor-pointer  text-center px-6 py-6 font-medium text-xs text-primary-900"><?php echo esc_html__( 'Sync', 'instawp-connect' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="whitespace-nowrap py-6 px-6 text-xs font-medium text-grayCust-300"><?php echo esc_html__( 'Lorem ipsum demo', 'instawp-connect' ); ?></td>
                                            <td class="whitespace-nowrap px-6 py-6 font-medium text-xs text-grayCust-300"><?php echo esc_html__( 'Lorem ipsum demo text the default payment.', 'instawp-connect' ); ?></td>
                                            <td class="whitespace-nowrap px-6 py-6 text-center font-medium text-xs text-grayCust-300"><div class="py-1 px-4 inline-block rounded-full font-medium " style="background-color: #DBEAFE;color: #1E40AF;"><?php echo esc_html__( 'Pending', 'instawp-connect' ); ?></div>
                                            </td>
                                            <td class="whitespace-nowrap cursor-pointer  text-center px-6 py-6 font-medium text-xs text-primary-900"><?php echo esc_html__( 'Sync', 'instawp-connect' ); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="whitespace-nowrap py-6 px-6 text-xs font-medium text-grayCust-300"><?php echo esc_html__( 'Lorem ipsum demo', 'instawp-connect' ); ?></td>
                                            <td class="whitespace-nowrap px-6 py-6 font-medium text-xs text-grayCust-300"><?php echo esc_html__( 'Lorem ipsum demo text the default payment.', 'instawp-connect' ); ?></td>
                                            <td class="whitespace-nowrap px-6 py-6 text-center font-medium text-xs text-grayCust-300"><div class="py-1 px-4 inline-block rounded-full font-medium " style="background-color: #FEF3C7;color: #92400E;">Pending </div>
                                            </td>
                                            <td class="whitespace-nowrap cursor-pointer  text-center px-6 py-6 font-medium text-xs text-primary-900"><?php echo esc_html__( 'Sync', 'instawp-connect' ); ?></td>
                                        </tr>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- pagination -->
                            <nav class="flex items-center justify-between border-t border-gray-200 mx-9 my-5">
                                <div class="-mt-px flex w-0 flex-1">
                                    <a href="#" class="inline-flex items-center border-t-2 border-transparent pt-3 pr-1 text-sm font-medium text-grayCust-300 hover:border-gray-300 hover:text-gray-700">
                                        <!-- Heroicon name: mini/arrow-long-left -->
                                        <svg class="mr-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#1F2937" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M18 10a.75.75 0 01-.75.75H4.66l2.1 1.95a.75.75 0 11-1.02 1.1l-3.5-3.25a.75.75 0 010-1.1l3.5-3.25a.75.75 0 111.02 1.1l-2.1 1.95h12.59A.75.75 0 0118 10z" clip-rule="evenodd" />
                                        </svg>
                                        <?php echo esc_html__( 'Previous', 'instawp-connect' ); ?>
                                    </a>
                                </div>
                                <div class="-mt-px flex w-0 flex-1 justify-end">
                                    <a href="#" class="inline-flex items-center border-t-2 border-transparent pt-3 pl-1 text-sm font-medium text-grayCust-300 hover:border-gray-300 hover:text-gray-700">
                                        <?php echo esc_html__( 'Next', 'instawp-connect' ); ?>
                                        <!-- Heroicon name: mini/arrow-long-right -->
                                        <svg class="ml-3 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#1F2937" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M2 10a.75.75 0 01.75-.75h12.59l-2.1-1.95a.75.75 0 111.02-1.1l3.5 3.25a.75.75 0 010 1.1l-3.5 3.25a.75.75 0 11-1.02-1.1l2.1-1.95H2.75A.75.75 0 012 10z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                </div>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<!--    </div>-->
</div>