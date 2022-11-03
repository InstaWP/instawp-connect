<?php

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function instawp_clear_free_dir( $directory ) {
    if ( file_exists($directory) ) {
        if ( $dir_handle = @opendir($directory) ) {
            while ( $filename = readdir($dir_handle) ) {
                if ( $filename != '.' && $filename != '..' ) {
                    $subFile = $directory."/".$filename;
                    if ( is_dir($subFile) ) {
                        instawp_clear_free_dir($subFile);
                    }
                    if ( is_file($subFile) ) {
                        unlink($subFile);
                    }
                }
            }
            closedir($dir_handle);
            rmdir($directory);
        }
    }
}

$instawp_common_setting = get_option('instawp_common_setting', array());
if ( ! empty($instawp_common_setting) ) {
    if ( isset($instawp_common_setting['uninstall_clear_folder']) && $instawp_common_setting['uninstall_clear_folder'] ) {
        $instawp_local_setting = get_option('instawp_local_setting', array());
        if ( isset($instawp_local_setting['path']) ) {
            if ( $instawp_local_setting['path'] !== 'instawpbackups' ) {
                instawp_clear_free_dir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.'instawpbackups');
            }
            instawp_clear_free_dir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$instawp_local_setting['path']);
        }
        else {
            instawp_clear_free_dir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.'instawpbackups');
        }
    }
}

delete_option('instawp_schedule_setting');
delete_option('instawp_email_setting');
delete_option('instawp_compress_setting');
delete_option('instawp_local_setting');
delete_option('instawp_upload_setting');
delete_option('instawp_common_setting');
delete_option('instawp_backup_list');
delete_option('instawp_task_list');
delete_option('instawp_init');
delete_option('instawp_remote_init');
delete_option('instawp_last_msg');
delete_option('instawp_download_cache');
delete_option('instawp_download_task');
delete_option('instawp_user_history');
delete_option('instawp_saved_api_token');
delete_option('instawp_import_list_cache');
delete_option('instawp_importer_task_list');
delete_option('instawp_list_cache');
delete_option('instawp_exporter_task_list');
delete_option('instawp_need_review');
delete_option('instawp_review_msg');
delete_option('instawp_migrate_status');
delete_option('clean_task');
delete_option('cron_backup_count');
delete_option('instawp_backup_success_count');
delete_option('instawp_backup_error_array');
delete_option('instawp_amazons3_notice');
delete_option('instawp_hide_mwp_tab_page_v1');
delete_option('instawp_hide_wp_cron_notice');
delete_option('instawp_transfer_error_array');
delete_option('instawp_transfer_success_count');
delete_option('instawp_api_token');
delete_option('instawp_download_task_v2');
delete_option('instawp_export_list');
delete_option('instawp_backup_report');

$options = get_option('instawp_staging_options',array());
$staging_keep_setting = isset($options['staging_keep_setting']) ? $options['staging_keep_setting'] : true;
if ( $staging_keep_setting ) {

}
else {
    delete_option('instawp_staging_task_list');
    delete_option('instawp_staging_task_cancel');
    delete_option('instawp_staging_options');
    delete_option('instawp_staging_history');
    delete_option('instawp_staging_list');
}

define('INSTAWP_MAIN_SCHEDULE_EVENT','instawp_main_schedule_event');

if ( wp_get_schedule(INSTAWP_MAIN_SCHEDULE_EVENT) ) {
    wp_clear_scheduled_hook(INSTAWP_MAIN_SCHEDULE_EVENT);
    $timestamp = wp_next_scheduled(INSTAWP_MAIN_SCHEDULE_EVENT);
    wp_unschedule_event($timestamp,INSTAWP_MAIN_SCHEDULE_EVENT);
}
