<?php
/**
 * Migrate template - Settings
 */


?>

<div class="nav-item-content settings bg-white box-shadow rounded-md p-6">
    <div class="w-full mb-6">
        <div class="mb-6">
            <div class="text-grayCust-200 text-lg font-medium"><?php echo esc_html__( 'Settings', 'instawp-connect' ); ?></div>
            <div class="text-grayCust-50 text-sm font-normal"><?php echo esc_html__( 'Lorem ipsum demo text the default payment method will be used for any biling purposes.', 'instawp-connect' ); ?></div>
        </div>
        <div class="flex">
            <div class="w-1/2 mr-6">
                <label for="api_key" class="block text-sm font-medium text-gray-700 mb-1 sm:mt-px sm:pt-2"><?php echo esc_html__( 'API Key', 'instawp-connect' ); ?></label>
                <input type="text" name="api_key" id="api_key" autocomplete="off" placeholder="demosite.com" class="block w-full rounded-md border-grayCust-350 shadow-sm focus:border-primary-900 focus:ring-1 focus:ring-primary-900 sm:text-sm" />
            </div>
            <div class="w-1/2">
                <label for="Heartbeat_interval" class="block text-sm font-medium text-gray-700 mb-1 sm:mt-px sm:pt-2"><?php echo esc_html__( 'Heartbeat Interval', 'instawp-connect' ); ?></label>
                <input type="text" name="Heartbeat_interval" placeholder="84" id="Heartbeat_interval" autocomplete="off" class="block w-full rounded-md border-grayCust-350 shadow-sm focus:border-primary-900 focus:ring-1 focus:ring-primary-900 sm:text-sm" />
            </div>
        </div>
    </div>
    <div class="bg-grayCust-400 p-3 px-6 flex justify-end items-center">
        <button class="text-grayCust-500 py-3 mr-4 px-5 border border-grayCust-350 text-sm font-medium rounded-md"><?php echo esc_html__( 'Reset Plugin', 'instawp-connect' ); ?></button>
        <button class="bg-primary-900 text-white py-3 px-5  text-sm font-medium rounded-md"><?php echo esc_html__( 'Save Changes', 'instawp-connect' ); ?></button>
    </div>
</div>