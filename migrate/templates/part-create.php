<?php
/**
 * Migrate template - Create Site
 */

//echo "<pre>";
//print_r( InstaWP_taskmanager::get_tasks() );
//echo "</pre>";

InstaWP_taskmanager::delete_all_task();
$task = new InstaWP_Backup();
$task->clean_backup();


//$migrate_task_id  = 'instawp-641dc76875716';
//$migrate_task_obj = new InstaWP_Backup_Task( $migrate_task_id );
//$migrate_task     = InstaWP_taskmanager::get_task( $migrate_task_id );
//$migrate_id       = InstaWP_Setting::get_args_option( 'migrate_id', $migrate_task );
//$cloud_urls       = InstaWP_taskmanager::get_cloud_uploaded_files( $migrate_task_id );
//$cloud_urls       = array_map( function ( $url ) {
//	return '"' . $url . '"';
//}, $cloud_urls );
//$cloud_urls       = implode( ',', $cloud_urls );
//
//echo "<pre>";
//print_r( $cloud_urls );
//echo "</pre>";

//$local_file  = $migrate_task['options']['backup_options']['backup']['backup_db']['zip_files_path'][0];
//$useragent   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

//$upload_args = array(
//	'method'     => 'PUT',
//	'body'       => file_get_contents( $local_file ),
//	'timeout'    => 0,
//	'decompress' => false,
//	'stream'     => false,
//	'filename'   => '',
//	'user-agent' => $useragent,
//	'upload'     => true,
//	'headers'    => array(
//		'Content-Type' => 'multipart/form-data'
//	),
//);

//$backup_task     = new InstaWP_Backup_Task();
//$backup_task_ret = $backup_task->new_download_task();


//
//for ( $i = 0; $i < INSTAWP_REMOTE_CONNECT_RETRY_TIMES; $i ++ ) {
//
////	$WP_Http_Curl = new WP_Http_Curl();
////	$response     = $WP_Http_Curl->request( $cloud_urls[0], $upload_args );
//
//	$basename     = basename( parse_url( $cloud_urls[0], PHP_URL_PATH ) );
//	$args         = array(
//		'timeout'    => 300,
//		'download'   => true,
//		'decompress' => false,
//		'stream'     => false,
//		'filename'   => '',
//		'user-agent' => $useragent,
//	);
//	$WP_Http_Curl = new WP_Http_Curl();
//	$response     = $WP_Http_Curl->request( $cloud_urls[0], $args );
//
//	echo "<pre>";
//	print_r( $response );
//	echo "</pre>";
//}

//return;

$incomplete_task_ids = InstaWP_taskmanager::is_there_any_incomplete_task_ids();
$is_loading_class    = ! empty( $incomplete_task_ids ) ? 'loading' : '';
?>

<div class="<?php echo esc_attr( $is_loading_class ); ?> nav-item-content create bg-white box-shadow rounded-md active">
    <div class="screen-0 flex items-center justify-center text-center py-20">

        <div>
            <div class="mb-6">
                <img src="<?php echo esc_url( instawp()::get_asset_url( 'migrate/assets/images/createsite.png' ) ) ?>" class="mx-auto" alt="">
            </div>

            <div class="text-lg text-lg font-bold text-grayCust-150"><?php echo esc_html__( 'Create a Staging Site', 'instawp-connect' ); ?></div>

            <button class="instawp-button-create btn-width py-3 rounded-md shadow-md bg-primary-900 text-white mt-8 font-semibold text-sm"><?php echo esc_html__( 'Create a Site', 'instawp-connect' ); ?></button>
        </div>

    </div>

    <div class="screen-1 p-8">

        <div class="mb-6 flex items-center">
            <span class="loader mr-2"></span>
            <span class="loader-text">Migration is processing...</span>
        </div>

        <div class="mb-6 flex items-center w-full">
            <div class="w-1/2 mr-6 mb-6">
                <span class="block text-sm font-medium text-gray-700 mb-3 sm:mt-px sm:pt-2">Backup</span>
                <div class="instawp-bar instawp-bar-backup w-full" style="--progress: 0%;"></div>
            </div>
            <div class="w-1/2 mr-6 mb-6">
                <span class="block text-sm font-medium text-gray-700 mb-3 sm:mt-px sm:pt-2">Upload</span>
                <div class="instawp-bar instawp-bar-upload w-full" style="--progress: 0%;"></div>
            </div>
        </div>

        <div class="mb-6 flex items-center w-full">
            <div class="w-full mr-6 mb-6">
                <span class="block text-sm font-medium text-gray-700 mb-3 sm:mt-px sm:pt-2">Migration</span>
                <div class="instawp-bar instawp-bar-migrate w-full" style="--progress: 0%;"></div>
            </div>
        </div>

    </div>
</div>