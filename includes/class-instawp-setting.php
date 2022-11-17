<?php

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}
class InstaWP_Setting
{   
        public static function init_option() {
        $ret = self::get_option('instawp_email_setting');
        if ( empty($ret) ) {
            self::set_default_email_option();
        }

        $ret = self::get_option('instawp_compress_setting');
        if ( empty($ret) ) {
            self::set_default_compress_option();
        }

        $ret = self::get_option('instawp_local_setting');
        if ( empty($ret) ) {
            self::set_default_local_option();
        }

        $ret = self::get_option('instawp_upload_setting');
        if ( empty($ret) ) {
            self::set_default_upload_option();
        }

        $ret = self::get_option('instawp_common_setting');
        if ( empty($ret) ) {
            self::set_default_common_option();
        }

        // $ret = get_option('instawp_api_url','');
        // if ( empty($ret) ) {
        //     self::set_api_domain();
        // }
       
    }

    public static function get_default_option( $option_name ) {
        $options = array();

        switch ( $option_name ) {
            case 'instawp_compress_setting':
                $options = self::set_default_compress_option();
                break;
            case 'instawp_local_setting':
                $options = self::set_default_local_option();
                break;
            case 'instawp_upload_setting':
                $options = self::set_default_upload_option();
                break;
            case 'instawp_common_setting':
                $options = self::set_default_common_option();
                break;
        }
        return $options;
    }

    public static function set_default_option() {
        self::set_default_compress_option();
        self::set_default_local_option();
        self::set_default_upload_option();
        self::set_default_common_option();
    }
    
    public static function set_api_domain( $instawp_api_url = 'https://app.instawp.io' ) {     
        update_option( 'instawp_api_url', $instawp_api_url );  
    }

    public static function get_api_domain( ) {
        // if( !empty( get_option('instawp_api_url_internal'))){
        //     return get_option( 'instawp_api_url_internal' );
        // }
        return get_option( 'instawp_api_url' );
    }

    public static function instawp_generate_api_key( $access_token, $status ) {
        
        global $InstaWP_Curl;
       
        if( !empty( $access_token ) && ( !empty( $status ) && $status == true ) )
        {   
            $api_doamin = InstaWP_Setting::get_api_domain();            

            /* API KEY Store Code Stat */
            $url = $api_doamin . INSTAWP_API_URL . '/check-key';
            $api_key = $_REQUEST['access_token'];
            $response = wp_remote_get( $url, array(
                'body'    => '',
                'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
                ),
            ));
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ( ! is_wp_error($response) && $response_code == 200 ) {
                $body = (array) json_decode(wp_remote_retrieve_body($response), true);     
                
                $connect_options = array();
                if ( $body['status'] == true ) {        
                    $connect_options['api_key']  = $api_key;
                    $connect_options['response'] = $body;
                   
                    update_option('instawp_api_options', $connect_options);                   
                } 
                error_log("instawp_api_options \n ".print_r(get_option('instawp_api_options'),true));
            }
            /* API KEY Store Code End */

            /* Connect ID Store Code Stat */
            $url = $api_doamin . INSTAWP_API_URL . '/connects';    
            $php_version  = substr( phpversion(), 0, 3);
            $body = json_encode(array( "url" => get_site_url(), 'php_version' => $php_version));
                  
            $curl_response = $InstaWP_Curl->curl($url, $body );
            //die;
            if ( $curl_response['error'] == false ) {
                $response = (array) json_decode($curl_response['curl_res'], true);

                if ( $response['status'] == true ) {
                    $connect_options = InstaWP_Setting::get_option('instawp_connect_options',array() );
                    $connect_id = $response['data']['id'];
                    $connect_options[ $connect_id ] = $response;
                    update_option('instawp_connect_id_options', $response);
                }
            }

            /* RUN CRON ON CONNECT START */
            $timestamp = wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' );
            wp_unschedule_event( $timestamp, 'instwp_handle_heartbeat_cron_action' );

            if ( !wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' ) ) {         
                error_log("RUN CRON ON GENERATE ACTION");
                wp_schedule_event( time(), 'instawp_heartbeat_interval', 'instwp_handle_heartbeat_cron_action');
             }
            /* RUN CRON ON CONNECT END */

            /* Connect ID Store Code End */

            $url = admin_url('admin.php?page=instawp-connect'); 
            error_log("redirect url : ".$url);
            wp_redirect( $url, 301 );
            exit();
        } 
    }

    public static function set_default_compress_option() {
        $compress_option['compress_type'] = INSTAWP_DEFAULT_COMPRESS_TYPE;
        $compress_option['max_file_size'] = INSTAWP_DEFAULT_MAX_FILE_SIZE;
        $compress_option['no_compress'] = INSTAWP_DEFAULT_NO_COMPRESS;
        $compress_option['use_temp_file'] = INSTAWP_DEFAULT_USE_TEMP;
        $compress_option['use_temp_size'] = INSTAWP_DEFAULT_USE_TEMP_SIZE;
        $compress_option['exclude_file_size'] = INSTAWP_DEFAULT_EXCLUDE_FILE_SIZE;
        $compress_option['subpackage_plugin_upload'] = INSTAWP_DEFAULT_SUBPACKAGE_PLUGIN_UPLOAD;
        self::update_option('instawp_compress_setting',$compress_option);
        return $compress_option;
    }

    public static function set_default_local_option() {
        $local_option['path'] = INSTAWP_DEFAULT_BACKUP_DIR;
        $local_option['save_local'] = 1;
        self::update_option('instawp_local_setting',$local_option);
        return $local_option;
    }

    public static function set_default_upload_option() {
        $upload_option = array();
        self::update_option('instawp_upload_setting',$upload_option);
        return $upload_option;
    }

    public static function set_default_email_option() {
        $email_option['send_to'] = array();
        $email_option['always'] = true;
        $email_option['email_enable'] = false;
        self::update_option('instawp_email_setting',$email_option);
        return $email_option;
    }

    public static function set_default_common_option() {
        $sapi_type = php_sapi_name();

        if ( $sapi_type == 'cgi-fcgi' || $sapi_type == ' fpm-fcgi' ) {
            $common_option['max_execution_time'] = INSTAWP_MAX_EXECUTION_TIME_FCGI;
        }
        else {
            $common_option['max_execution_time'] = INSTAWP_MAX_EXECUTION_TIME;
        }

        $common_option['log_save_location'] = INSTAWP_DEFAULT_LOG_DIR;
        $common_option['max_backup_count'] = INSTAWP_DEFAULT_BACKUP_COUNT;
        $common_option['show_admin_bar'] = INSTAWP_DEFAULT_ADMIN_BAR;
        //$common_option['show_tab_menu']=INSTAWP_DEFAULT_TAB_MENU;
        $common_option['domain_include'] = INSTAWP_DEFAULT_DOMAIN_INCLUDE;
        $common_option['estimate_backup'] = INSTAWP_DEFAULT_ESTIMATE_BACKUP;
        $common_option['max_resume_count'] = INSTAWP_RESUME_RETRY_TIMES;
        $common_option['memory_limit'] = INSTAWP_MEMORY_LIMIT;
        $common_option['restore_memory_limit'] = INSTAWP_RESTORE_MEMORY_LIMIT;
        $common_option['migrate_size'] = INSTAWP_MIGRATE_SIZE;
        self::update_option('instawp_common_setting',$common_option);
        return $common_option;
    }

    public static function get_option( $option_name, $default = array() ) {
        $ret = get_option($option_name, $default);
        if ( empty($ret) ) {
            self::get_default_option($option_name);
        }
        return $ret;
    }

    public static function get_last_backup_message( $option_name, $default = array() ) {
        $message = self::get_option($option_name, $default);
        $ret = array();
        if ( ! empty($message['id']) ) {
            $ret['id'] = $message['id'];
            $ret['status'] = $message['status'];
            $ret['status']['start_time'] = date("M d, Y H:i", $ret['status']['start_time']);
            $ret['status']['run_time'] = date("M d, Y H:i", $ret['status']['run_time']);
            $ret['status']['timeout'] = date("M d, Y H:i", $ret['status']['timeout']);
            if (isset($message['options']['log_file_name']))
                $ret['log_file_name'] = $message['options']['log_file_name'];
            else
                $ret['log_file_name'] = '';
        }
        return $ret;
    }

    public static function get_backupdir() {
        $dir = self::get_option('instawp_local_setting');

        if ( ! isset($dir['path']) ) {
            $dir = self::set_default_local_option();
        }
        if ( ! is_dir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir['path']) ) {
            @mkdir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir['path'],0777,true);
            @fopen(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir['path'].DIRECTORY_SEPARATOR.'index.html', 'x');
            $tempfile = @fopen(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir['path'].DIRECTORY_SEPARATOR.'.htaccess', 'x');
            if ( $tempfile ) {
                // Prevent access, #Commented temporarily#
                // $text = "deny from all";
                $text = "";
                fwrite($tempfile,$text );
                fclose($tempfile);
            }
            else {
                return false;
            }        
}

        return $dir['path'];
    }

    public static function set_backupdir( $dir ) {
        if ( ! isset($dir['path']) ) {
            $dir = self::set_default_local_option();
        }
        else {
            self::update_option('instawp_local_setting',$dir);
        }

        if ( ! is_dir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir['path']) ) {
            @mkdir(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir['path'],0777,true);
        }

        @fopen(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir['path'].'/index.html', 'x');
        $tempfile = @fopen(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$dir['path'].'/.htaccess', 'x');
        if ( $tempfile ) {
            $text = "deny from all";
            fwrite($tempfile,$text );
            fclose($tempfile);
        }
    }

    public static function get_save_local() {
        $local = self::get_option('instawp_local_setting');

        if ( ! isset($local['save_local']) ) {
            $local = self::set_default_local_option();
        }

        return $local['save_local'];
    }

    public static function update_option( $option_name,$options ) {
        update_option($option_name,$options,'no');
    }


    public static function update_connect_option( $option_name,$options,$connect_id,$task_id='',$key = '' ) {

        $connect_options = InstaWP_Setting::get_option('instawp_connect_options',array() );
        if ( isset( $connect_options[ $connect_id ] ) ) {

            if ( isset($connect_options[ $connect_id ][ $task_id ][ $key ]) && ! empty($key) ) {

                $connect_options[ $connect_id ][ $task_id ][ $key ] = $options[ $task_id ][ $key ];               
            }
            else {
                $connect_options[ $connect_id ][ $task_id ][ $key ] = $options;
            }   
        }
        else {
            $connect_options[ $connect_id ] = $options;               
        }
        update_option($option_name,$connect_options,'no');    
    }

    public static function delete_option( $option_name ) {
        delete_option($option_name);
    }

    public static function get_tasks() {
        $default = array();
        return $options = get_option('instawp_task_list', $default);
    }

    public static function update_task( $id,$task ) {
        $default = array();
        $options = get_option('instawp_task_list', $default);
        $options[ $id ] = $task;
        self::update_option('instawp_task_list',$options);
    }

    public static function delete_task( $id ) {
        $default = array();
        $options = get_option('instawp_task_list', $default);
        unset($options[ $id ]);
        self::update_option('instawp_task_list',$options);
    }

    public static function check_compress_options() {
        $options = self::get_option('instawp_compress_setting');

        if ( ! isset($options['compress_type']) || ! isset($options['max_file_size']) ||
            ! isset($options['no_compress']) || ! isset($options['exclude_file_size']) ||
            ! isset($options['use_temp_file']) || ! isset($options['use_temp_size']) ) {
            self::set_default_compress_option();
        }
    }

    public static function check_local_options() {
        $options = self::get_option('instawp_local_setting');

        if ( ! isset($options['path']) || ! isset($options['save_local']) ) {
            self::set_default_local_option();
        }

        return true;
    }

    /*public static function get_backup_options($post)
    {
        self::check_compress_options();
        self::check_local_options();

        if($post=='files+db')
        {
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_DB]=0;
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_THEMES]=0;
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_PLUGIN]=0;
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_UPLOADS]=0;
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_CONTENT]=0;
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_CORE]=0;
        }
        else if($post=='files')
        {
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_THEMES]=0;
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_PLUGIN]=0;
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_UPLOADS]=0;
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_CONTENT]=0;
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_CORE]=0;
        }
        else if($post=='db')
        {
            $backup_options['backup']['backup_type'][INSTAWP_BACKUP_TYPE_DB]=0;
        }
        else
        {
            //return false;
        }

        $backup_options['compress']=self::get_option('instawp_compress_setting');
        $backup_options['dir']=self::get_backupdir();
        return $backup_options;
    }*/

    public static function get_remote_option( $id ) {
        $upload_options = self::get_option('instawp_upload_setting');
        if ( array_key_exists($id,$upload_options) ) {
            return $upload_options[ $id ];
        }
        else {
            return false;
        }
    }

    public static function get_remote_options( $remote_ids=array() ) {
        if ( empty($remote_ids) ) {
            $remote_ids = InstaWP_Setting::get_user_history('remote_selected');
        }

        if ( empty($remote_ids) ) {
            return false;
        }

        $options = array();
        $upload_options = InstaWP_Setting::get_option('instawp_upload_setting');
        foreach ( $remote_ids as $id ) {
            if ( array_key_exists($id,$upload_options) ) {
                $options[ $id ] = $upload_options[ $id ];
            }
        }
        if (empty($options))
            return false;
        else
            return $options;
    }

    public static function get_all_remote_options() {
        $upload_options = self::get_option('instawp_upload_setting');
        $upload_options['remote_selected'] = InstaWP_Setting::get_user_history('remote_selected');
        return $upload_options;
    }

    public static function add_remote_options( $remote ) {
        $upload_options = self::get_option('instawp_upload_setting');
        $id = uniqid('instawp-remote-');

        $remote = apply_filters('instawp_pre_add_remote',$remote,$id);

        $upload_options[ $id ] = $remote;
        self::update_option('instawp_upload_setting',$upload_options);
        return $id;
    }

    public static function delete_remote_option( $id ) {
        do_action('instawp_delete_remote_token',$id);

        $upload_options = self::get_option('instawp_upload_setting');

        if ( array_key_exists($id,$upload_options) ) {
            unset( $upload_options[ $id ]);

            self::update_option('instawp_upload_setting',$upload_options);
            return true;
        }
        else {
            return false;
        }
    }

    public static function update_remote_option( $remote_id,$remote ) {
        $upload_options = self::get_option('instawp_upload_setting');

        if ( array_key_exists($remote_id,$upload_options) ) {
            $remote = apply_filters('instawp_pre_add_remote',$remote,$remote_id);
            $upload_options[ $remote_id ] = $remote;
            self::update_option('instawp_upload_setting',$upload_options);
            return true;
        }
        else {
            return false;
        }
    }

    public static function get_setting( $all,$options_name ) {
        $get_options = array();
        if ( $all == true ) {
            $get_options[] = 'instawp_email_setting';
            $get_options[] = 'instawp_compress_setting';
            $get_options[] = 'instawp_local_setting';
            $get_options[] = 'instawp_common_setting';
            $get_options = apply_filters('instawp_get_setting_addon', $get_options);
        }
        else {
            $get_options[] = $options_name;
        }

        $ret['result'] = 'success';
        $ret['options'] = array();

        foreach ( $get_options as $option_name ) {
            $ret['options'][ $option_name ] = self::get_option($option_name);
        }

        return $ret;
    }

    public static function update_setting( $options ) {
        foreach ( $options as $option_name => $option ) {
            self::update_option($option_name,$option);
        }
        $ret['result'] = 'success';
        return $ret;
    }

    public static function export_setting_to_json( $setting=true,$history=true,$review=true,$backup_list=true ) {
        global $instawp_plugin;
        $json['plugin'] = $instawp_plugin->get_plugin_name();
        $json['version'] = INSTAWP_PLUGIN_VERSION;
        $json['setting'] = $setting;
        $json['history'] = $history;
        $json['data']['instawp_init'] = self::get_option('instawp_init');

        if ( $setting ) {
            $json['data']['instawp_schedule_setting'] = self::get_option('instawp_schedule_setting');
            if ( ! empty( $json['data']['instawp_schedule_setting']) ) {
                if (isset($json['data']['instawp_schedule_setting']['backup']['backup_files']))
                    $json['data']['instawp_schedule_setting']['backup_type'] = $json['data']['instawp_schedule_setting']['backup']['backup_files'];
                if ( isset($json['data']['instawp_schedule_setting']['backup']['local']) ) {
                    if ( $json['data']['instawp_schedule_setting']['backup']['local'] == 1 ) {
                        $json['data']['instawp_schedule_setting']['save_local_remote'] = 'local';
                    }
                    else {
                        $json['data']['instawp_schedule_setting']['save_local_remote'] = 'remote';
                    }
                }

                $json['data']['instawp_schedule_setting']['lock'] = 0;
                if ( wp_get_schedule(INSTAWP_MAIN_SCHEDULE_EVENT) ) {
                    $recurrence = wp_get_schedule(INSTAWP_MAIN_SCHEDULE_EVENT);
                    $timestamp = wp_next_scheduled(INSTAWP_MAIN_SCHEDULE_EVENT);
                    $json['data']['instawp_schedule_setting']['recurrence'] = $recurrence;
                    $json['data']['instawp_schedule_setting']['next_start'] = $timestamp;
                }
            }
            else {
                $json['data']['instawp_schedule_setting'] = array();
            }
            $json['data']['instawp_compress_setting'] = self::get_option('instawp_compress_setting');
            $json['data']['instawp_local_setting'] = self::get_option('instawp_local_setting');
            $json['data']['instawp_upload_setting'] = self::get_option('instawp_upload_setting');
            $json['data']['instawp_common_setting'] = self::get_option('instawp_common_setting');
            $json['data']['instawp_email_setting'] = self::get_option('instawp_email_setting');
            $json['data']['instawp_saved_api_token'] = self::get_option('instawp_saved_api_token');
            $json = apply_filters('instawp_export_setting_addon', $json);
            /*if(isset($json['data']['instawp_local_setting']['path'])){
                unset($json['data']['instawp_local_setting']['path']);
            }*/
            if ( isset($json['data']['instawp_common_setting']['log_save_location']) ) {
                unset($json['data']['instawp_common_setting']['log_save_location']);
            }
            if ( isset($json['data']['instawp_common_setting']['backup_prefix']) ) {
                unset($json['data']['instawp_common_setting']['backup_prefix']);
            }
        }

        if ( $history ) {
            $json['data']['instawp_task_list'] = self::get_option('instawp_task_list');
            $json['data']['instawp_last_msg'] = self::get_option('instawp_last_msg');
            $json['data']['instawp_user_history'] = self::get_option('instawp_user_history');
            $json = apply_filters('instawp_history_addon', $json);
        }

        if ( $backup_list ) {
            $json['data']['instawp_backup_list'] = self::get_option('instawp_backup_list');
            $json = apply_filters('instawp_backup_list_addon', $json);
        }

        if ( $review ) {
            $json['data']['instawp_need_review'] = self::get_option('instawp_need_review');
            $json['data']['cron_backup_count'] = self::get_option('cron_backup_count');
            $json['data']['instawp_review_msg'] = self::get_option('instawp_review_msg');
            $json = apply_filters('instawp_review_addon', $json);
        }
        return $json;
    }

    public static function import_json_to_setting( $json ) {
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete('alloptions', 'options');
        foreach ( $json['data'] as $option_name => $option ) {
            wp_cache_delete($option_name, 'options');
            delete_option($option_name);
            self::update_option($option_name,$option);
        }
    }

    public static function set_max_backup_count( $count ) {
        $options = self::get_option('instawp_common_setting');
        $options['max_backup_count'] = $count;
        self::update_option('instawp_common_setting',$options);
    }

    public static function get_max_backup_count() {
        $options = self::get_option('instawp_common_setting');
        if ( isset($options['max_backup_count']) ) {
            return $options['max_backup_count'];
        }
        else {
            return INSTAWP_MAX_BACKUP_COUNT;
        }
    }

    public static function get_mail_setting() {
        return self::get_option('instawp_email_setting');
    }

    public static function get_admin_bar_setting(){
        $options = self::get_option('instawp_common_setting');
        if ( isset($options['show_admin_bar']) ) {
            if ( $options['show_admin_bar'] ) {
                return true;
            }
            else {
                return false;
            }
        }
        else {
            return true;
        }
    }

    public static function update_user_history( $action,$value ) {
        $options = self::get_option('instawp_user_history');
        $options[ $action ] = $value;
        self::update_option('instawp_user_history',$options);
    }

    public static function get_user_history( $action ) {
        $options = self::get_option('instawp_user_history');
        if ( array_key_exists($action,$options) ) {
            return $options[ $action ];
        }
        else {
            return array();
        }
    }

    public static function get_retain_local_status() {
        $options = self::get_option('instawp_common_setting');
        if ( isset($options['retain_local']) ) {
            if ( $options['retain_local'] ) {
                return true;
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }
    }

    public static function get_sync_data() {
        $data['setting']['instawp_compress_setting'] = self::get_option('instawp_compress_setting');
        $data['setting']['instawp_local_setting'] = self::get_option('instawp_local_setting');
        $data['setting']['instawp_common_setting'] = self::get_option('instawp_common_setting');
        $data['setting']['instawp_email_setting'] = self::get_option('instawp_email_setting');
        $data['setting']['cron_backup_count'] = self::get_option('cron_backup_count');
        $data['schedule'] = self::get_option('instawp_schedule_setting');
        $data['remote']['upload'] = self::get_option('instawp_upload_setting');
        $data['remote']['history'] = self::get_option('instawp_user_history');
        $data['last_backup_report'] = get_option('instawp_backup_reports');

        $data['setting_addon'] = $data['setting'];
        $data['setting_addon']['instawp_staging_options'] = array();
        $data['backup_custom_setting'] = array();
        $data['menu_capability'] = array();
        $data['white_label_setting'] = array();
        $data['incremental_backup_setting'] = array();
        $data['schedule_addon'] = array();
        $data['time_zone'] = false;
        $data['is_pro'] = false;
        $data['is_install'] = false;
        $data['is_login'] = false;
        $data['latest_version'] = '';
        $data['current_version'] = '';
        $data['dashboard_version'] = '';
        $data['addons_info'] = array();
        $data = apply_filters('instawp_get_instawp_info_addon_mainwp_ex', $data);
        return $data;
    }
}
