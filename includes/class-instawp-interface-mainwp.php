<?php

class InstaWP_Interface_MainWP
{
    public function __construct(){
        $this->load_instawp_mainwp_backup_filter();
        $this->load_instawp_mainwp_side_bar_filter();
        $this->load_instawp_mainwp_backup_list_filter();
        
        $this->load_instawp_mainwp_setting_filter();
        $this->load_instawp_mainwp_remote_filter();
    }

    public function load_instawp_mainwp_backup_filter(){
        add_filter('instawp_get_status_mainwp', array( $this, 'instawp_get_status_mainwp' ));
        add_filter('instawp_get_backup_list_mainwp', array( $this, 'instawp_get_backup_list_mainwp' ));
       
        add_filter('instawp_get_default_remote_mainwp', array( $this, 'instawp_get_default_remote_mainwp' ));
        add_filter('instawp_prepare_backup_mainwp', array( $this, 'instawp_prepare_backup_mainwp' ));
        add_filter('instawp_backup_now_mainwp', array( $this, 'instawp_backup_now_mainwp' ));
        add_filter('instawp_view_backup_task_log_mainwp', array( $this, 'instawp_view_backup_task_log_mainwp' ));
        add_filter('instawp_backup_cancel_mainwp', array( $this, 'instawp_backup_cancel_mainwp' ));
        add_filter('instawp_set_backup_report_addon_mainwp', array( $this, 'instawp_set_backup_report_addon_mainwp' ));
    }

    public function load_instawp_mainwp_side_bar_filter(){
        add_filter('instawp_read_last_backup_log_mainwp', array( $this, 'instawp_read_last_backup_log_mainwp' ));
    }

    public function load_instawp_mainwp_backup_list_filter(){
        add_filter('instawp_set_security_lock_mainwp', array( $this, 'instawp_set_security_lock_mainwp' ));
        add_filter('instawp_view_log_mainwp', array( $this, 'instawp_view_log_mainwp' ));
        add_filter('instawp_init_download_page_mainwp', array( $this, 'instawp_init_download_page_mainwp' ));
        add_filter('instawp_prepare_download_backup_mainwp', array( $this, 'instawp_prepare_download_backup_mainwp' ));
        add_filter('instawp_get_download_task_mainwp', array( $this, 'instawp_get_download_task_mainwp' ));
        add_filter('instawp_download_backup_mainwp', array( $this, 'instawp_download_backup_mainwp' ));
        add_filter('instawp_delete_backup_mainwp', array( $this, 'instawp_delete_backup_mainwp' ));
        add_filter('instawp_delete_backup_array_mainwp', array( $this, 'instawp_delete_backup_array_mainwp' ));
    }

    

    public function load_instawp_mainwp_setting_filter(){
        add_filter('instawp_set_general_setting_mainwp', array( $this, 'instawp_set_general_setting_mainwp' ));
    }

    public function load_instawp_mainwp_remote_filter(){
        add_filter('instawp_set_remote_mainwp', array( $this, 'instawp_set_remote_mainwp' ));
    }

    public function instawp_get_status_mainwp( $data ) {
        $ret['result'] = 'success';
        $list_tasks = array();
        $tasks = InstaWP_Setting::get_tasks();
        foreach ( $tasks as $task ) {
            $backup = new InstaWP_Backup_Task($task['id']);
            $list_tasks[ $task['id'] ] = $backup->get_backup_task_info($task['id']);
            if ( $list_tasks[ $task['id'] ]['task_info']['need_update_last_task'] === true ) {
                $task_msg = InstaWP_taskmanager::get_task($task['id']);
                InstaWP_Setting::update_option('instawp_last_msg',$task_msg);
                apply_filters('instawp_set_backup_report_addon_mainwp', $task_msg);
            }
        }
        $ret['instawp']['task'] = $list_tasks;
        $backuplist = InstaWP_Backuplist::get_backuplist();
        
        $ret['instawp']['backup_list'] = $backuplist;
        $ret['instawp']['schedule'] = $schedule;
        $ret['instawp']['schedule']['last_message'] = InstaWP_Setting::get_last_backup_message('instawp_last_msg');
        InstaWP_taskmanager::delete_marked_task();
        return $ret;
    }

    public function instawp_get_backup_list_mainwp( $data ) {
        $backuplist = InstaWP_Backuplist::get_backuplist();
        $ret['result'] = 'success';
        $ret['instawp']['backup_list'] = $backuplist;
        return $ret;
    }

   
    public function instawp_get_default_remote_mainwp( $data ) {
        global $instawp_plugin;
        $ret['result'] = 'success';
        $ret['remote_storage_type'] = $instawp_plugin->function_realize->_get_default_remote_storage();
        return $ret;
    }

    public function instawp_prepare_backup_mainwp( $data ) {
        $backup_options = $data['backup'];
        global $instawp_plugin;
        if ( isset($backup_options) && ! empty($backup_options) ) {
            if ( is_null($backup_options) ) {
                $ret['error'] = 'Invalid parameter param:'.$backup_options;
                return $ret;
            }
            $backup_options = apply_filters('instawp_custom_backup_options', $backup_options);

            if ( ! isset($backup_options['type']) ) {
                $backup_options['type'] = 'Manual';
                $backup_options['action'] = 'backup';
            }
            $ret = $instawp_plugin->check_backup_option($backup_options, $backup_options['type']);
            if ( $ret['result'] != 'success' ) {
                return $ret;
            }
            $ret = $instawp_plugin->pre_backup($backup_options);
            if ( $ret['result'] == 'success' ) {
                //Check the website data to be backed up
                $ret['check'] = $instawp_plugin->check_backup($ret['task_id'],$backup_options);
                if ( isset($ret['check']['result']) && $ret['check']['result'] == 'failed' ) {
                    $ret['error'] = $ret['check']['error'];
                    return $ret;
                }
            }
        }
        else {
            $ret['error'] = 'Error occurred while parsing the request data. Please try to run backup again.';
            return $ret;
        }
        return $ret;
    }

    public function instawp_backup_now_mainwp( $data ) {
        $task_id = $data['task_id'];
        global $instawp_plugin;
        if ( ! isset($task_id) || empty($task_id) || ! is_string($task_id) ) {
            $ret['error'] = __('Error occurred while parsing the request data. Please try to run backup again.', 'instawp-connect');
            return $ret;
        }
        $task_id = sanitize_key($task_id);
        /*$ret['result']='success';
        $txt = '<mainwp>' . base64_encode( serialize( $ret ) ) . '</mainwp>';
        // Close browser connection so that it can resume AJAX polling
        if(!headers_sent()) {
            header('Content-Length: ' . ((!empty($txt)) ? strlen($txt) : '0'));
            header('Connection: close');
            header('Content-Encoding: none');
        }
        if ( session_id() ) {
            session_write_close();
        }
        echo $txt;
        // These two added - 19-Feb-15 - started being required on local dev machine, for unknown reason (probably some plugin that started an output buffer).
        $ob_level = ob_get_level();
        while ($ob_level > 0) {
            ob_end_flush();
            $ob_level--;
        }
        flush();
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();*/

        $instawp_plugin->flush($task_id, true);
        //Start backup site
        $instawp_plugin->backup($task_id);
        $ret['result'] = 'success';
    }

    public function instawp_view_backup_task_log_mainwp( $data ) {
        $backup_task_id = $data['id'];
        global $instawp_plugin;
        if ( ! isset($backup_task_id) || empty($backup_task_id) || ! is_string($backup_task_id) ) {
            $ret['error'] = 'Reading the log failed. Please try again.';
            return $ret;
        }
        $backup_task_id = sanitize_key($backup_task_id);
        $ret = $instawp_plugin->function_realize->_get_log_file('tasklog', $backup_task_id);
        if ( $ret['result'] == 'success' ) {
            $file = fopen($ret['log_file'], 'r');
            if ( ! $file ) {
                $ret['result'] = 'failed';
                $ret['error'] = __('Unable to open the log file.', 'instawp-connect');
                return $ret;
            }
            $buffer = '';
            while ( ! feof($file) ) {
                $buffer .= fread($file, 1024);
            }
            fclose($file);
            $ret['result'] = 'success';
            $ret['data'] = $buffer;
        }
        else {
            $ret['error'] = 'Unknown error';
        }
        return $ret;
    }

    public function instawp_backup_cancel_mainwp( $data ) {
        global $instawp_plugin;
        $ret = $instawp_plugin->function_realize->_backup_cancel();
        return $ret;
    }

    public function instawp_set_backup_report_addon_mainwp( $data ) {
        if ( isset($data['id']) ) {
            $task_id = $data['id'];
            $option = array();
            $option[ $task_id ]['task_id'] = $task_id;
            $option[ $task_id ]['backup_time'] = $data['status']['start_time'];
            if ( $data['status']['str'] == 'completed' ) {
                $option[ $task_id ]['status'] = 'Succeeded';
            }
            elseif ( $data['status']['str'] == 'error' ) {
                $option[ $task_id ]['status'] = 'Failed, '.$data['status']['error'];
            }
            elseif ( $data['status']['str'] == 'cancel' ) {
                $option[ $task_id ]['status'] = 'Canceled';
            }
            else {
                $option[ $task_id ]['status'] = 'The last backup message not found.';
            }

            $backup_reports = get_option('instawp_backup_reports', array());
            if ( ! empty($backup_reports) ) {
                foreach ( $option as $key => $value ) {
                    $backup_reports[ $key ] = $value;
                    update_option('instawp_backup_reports', $backup_reports);
                }
            }
            else {
                update_option('instawp_backup_reports', $option);
            }
        }
    }

    public function instawp_read_last_backup_log_mainwp( $data ) {
        $log_file_name = $data['log_file_name'];
        global $instawp_plugin;
        if ( ! isset($log_file_name) || empty($log_file_name) || ! is_string($log_file_name) ) {
            $ret['result'] = 'failed';
            $ret['error'] = __('Reading the log failed. Please try again.', 'instawp-connect');
            return $ret;
        }
        $log_file_name = sanitize_text_field($log_file_name);
        $ret = $instawp_plugin->function_realize->_get_log_file('lastlog', $log_file_name);
        if ( $ret['result'] == 'success' ) {
            $file = fopen($ret['log_file'], 'r');
            if ( ! $file ) {
                $ret['result'] = 'failed';
                $ret['error'] = __('Unable to open the log file.', 'instawp-connect');
                return $ret;
            }
            $buffer = '';
            while ( ! feof($file) ) {
                $buffer .= fread($file, 1024);
            }
            fclose($file);
            $ret['result'] = 'success';
            $ret['data'] = $buffer;
        }
        else {
            $ret['error'] = 'Unknown error';
        }
        return $ret;
    }

    public function instawp_set_security_lock_mainwp( $data ) {
        $backup_id = $data['backup_id'];
        $lock = $data['lock'];
        if ( ! isset($backup_id) || empty($backup_id) || ! is_string($backup_id) ) {
            $ret['error'] = 'Backup id not found';
            return $ret;
        }
        if ( ! isset($lock) ) {
            $ret['error'] = 'Invalid parameter param: lock';
            return $ret;
        }
        $backup_id = sanitize_key($backup_id);
        if ( $lock == 0 || $lock == 1 ) {
        }
        else {
            $lock = 0;
        }
        InstaWP_Backuplist::set_security_lock($backup_id,$lock);
        $backuplist = InstaWP_Backuplist::get_backuplist();
        $ret['instawp']['backup_list'] = $backuplist;
        return $ret;
    }

    public function instawp_view_log_mainwp( $data ) {
        $backup_id = $data['id'];
        global $instawp_plugin;
        if ( ! isset($backup_id) || empty($backup_id) || ! is_string($backup_id) ) {
            $ret['error'] = 'Backup id not found';
            return $ret;
        }
        $backup_id = sanitize_key($backup_id);
        $ret = $instawp_plugin->function_realize->_get_log_file('backuplist', $backup_id);
        if ( $ret['result'] == 'success' ) {
            $file = fopen($ret['log_file'], 'r');
            if ( ! $file ) {
                $ret['result'] = 'failed';
                $ret['error'] = __('Unable to open the log file.', 'instawp-connect');
                return $ret;
            }
            $buffer = '';
            while ( ! feof($file) ) {
                $buffer .= fread($file, 1024);
            }
            fclose($file);
            $ret['data'] = $buffer;
        }
        else {
            $ret['error'] = 'Unknown error';
        }
        return $ret;
    }

    public function instawp_init_download_page_mainwp( $data ) {
        $backup_id = $data['backup_id'];
        global $instawp_plugin;
        if ( ! isset($backup_id) || empty($backup_id) || ! is_string($backup_id) ) {
            $ret['error'] = 'Invalid parameter param:'.$backup_id;
            return $ret;
        }
        else {
            $backup_id = sanitize_key($backup_id);
            return $instawp_plugin->init_download($backup_id);
        }
    }

    public function instawp_prepare_download_backup_mainwp( $data ) {
        $backup_id = $data['backup_id'];
        $file_name = $data['file_name'];
        if ( ! isset($backup_id) || empty($backup_id) || ! is_string($backup_id) ) {
            $ret['error'] = 'Invalid parameter param:'.$backup_id;
            return $ret;
        }
        if ( ! isset($file_name) || empty($file_name) || ! is_string($file_name) ) {
            $ret['error'] = 'Invalid parameter param:'.$file_name;
            return $ret;
        }
        $download_info = array();
        $download_info['backup_id'] = sanitize_key($backup_id);
        $download_info['file_name'] = $file_name;

        @set_time_limit(600);
        if (session_id())
            session_write_close();
        try {
            $downloader = new InstaWP_downloader();
            $downloader->ready_download($download_info);
        }
        catch ( Exception $e ) {
            $message = 'A exception ('.get_class($e).') occurred '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
            error_log($message);
            return array( 'error' => $message );
        }
        catch ( Error $e ) {
            $message = 'A error ('.get_class($e).') has occurred: '.$e->getMessage().' (Code: '.$e->getCode().', line '.$e->getLine().' in '.$e->getFile().')';
            error_log($message);
            return array( 'error' => $message );
        }

        $ret['result'] = 'success';
        return $ret;
    }

    public function instawp_get_download_task_mainwp( $data ) {
        $backup_id = $data['backup_id'];
        if ( ! isset($backup_id) || empty($backup_id) || ! is_string($backup_id) ) {
            $ret['error'] = 'Invalid parameter param:'.$backup_id;
            return $ret;
        }
        else {
            $backup = InstaWP_Backuplist::get_backup_by_id($backup_id);
            if ( $backup === false ) {
                $ret['result'] = INSTAWP_FAILED;
                $ret['error'] = 'backup id not found';
                return $ret;
            }
            $backup_item = new InstaWP_Backup_Item($backup);
            $ret = $backup_item->update_download_page($backup_id);
            return $ret;
        }
    }

    public function instawp_download_backup_mainwp( $data ) {
        $backup_id = $data['backup_id'];
        $file_name = $data['file_name'];
        global $instawp_plugin;
        if ( ! isset($backup_id) || empty($backup_id) || ! is_string($backup_id) ) {
            $ret['error'] = 'Invalid parameter param: backup_id';
            return $ret;
        }
        if ( ! isset($file_name) || empty($file_name) || ! is_string($file_name) ) {
            $ret['error'] = 'Invalid parameter param: file_name';
            return $ret;
        }
        $backup_id = sanitize_key($backup_id);
        $cache = InstaWP_taskmanager::get_download_cache($backup_id);
        if ( $cache === false ) {
            $instawp_plugin->init_download($backup_id);
            $cache = InstaWP_taskmanager::get_download_cache($backup_id);
        }
        $path = false;
        if ( array_key_exists($file_name,$cache['files']) ) {
            if ( $cache['files'][ $file_name ]['status'] == 'completed' ) {
                $path = $cache['files'][ $file_name ]['download_path'];
                $download_url = $cache['files'][ $file_name ]['download_url'];
            }
        }
        if ( $path !== false ) {
            if ( file_exists($path) ) {
                $ret['download_url'] = $download_url;
                $ret['size'] = filesize($path);
            }
        }
        return $ret;
    }

    public function instawp_delete_backup_mainwp( $data ) {
        $backup_id = $data['backup_id'];
        $force_del = $data['force'];
        global $instawp_plugin;
        if ( ! isset($backup_id) || empty($backup_id) || ! is_string($backup_id) ) {
            $ret['error'] = 'Invalid parameter param: backup_id.';
            return $ret;
        }
        if ( ! isset($force_del) ) {
            $ret['error'] = 'Invalid parameter param: force.';
            return $ret;
        }
        if ( $force_del == 0 || $force_del == 1 ) {
        }
        else {
            $force_del = 0;
        }
        $backup_id = sanitize_key($backup_id);
        $ret = $instawp_plugin->delete_backup_by_id($backup_id, $force_del);
        $backuplist = InstaWP_Backuplist::get_backuplist();
        $ret['instawp']['backup_list'] = $backuplist;
        return $ret;
    }

    public function instawp_delete_backup_array_mainwp( $data ) {
        $backup_id_array = $data['backup_id'];
        global $instawp_plugin;
        if ( ! isset($backup_id_array) || empty($backup_id_array) || ! is_array($backup_id_array) ) {
            $ret['error'] = 'Invalid parameter param: backup_id';
            return $ret;
        }
        $ret = array();
        foreach ( $backup_id_array as $backup_id ) {
            $backup_id = sanitize_key($backup_id);
            $ret = $instawp_plugin->delete_backup_by_id($backup_id);
        }
        $backuplist = InstaWP_Backuplist::get_backuplist();
        $ret['instawp']['backup_list'] = $backuplist;
        return $ret;
    }

  

    public function instawp_set_general_setting_mainwp( $data ) {
        $setting = $data['setting'];
        $ret = array();
        try {
            if ( isset($setting) && ! empty($setting) ) {
                $json_setting = $setting;
                $json_setting = stripslashes($json_setting);
                $setting = json_decode($json_setting, true);
                if ( is_null($setting) ) {
                    $ret['error'] = 'bad parameter';
                    return $ret;
                }
                InstaWP_Setting::update_setting($setting);
            }

            $ret['result'] = 'success';
        }
        catch ( Exception $error ) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            return array( 'error' => $message );
        }
        return $ret;
    }

    public function instawp_set_remote_mainwp( $data ) {
        $remote = $data['remote'];
        global $instawp_plugin;
        $ret = array();
        try {
            if ( isset($remote) && ! empty($remote) ) {
                $json = $remote;
                $json = stripslashes($json);
                $remote = json_decode($json, true);
                if ( is_null($remote) ) {
                    $ret['error'] = 'bad parameter';
                    return $ret;
                }
                $instawp_plugin->function_realize->_set_remote($remote);
            }
            $ret['result'] = 'success';
        }
        catch ( Exception $error ) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            return array( 'error' => $message );
        }
        return $ret;
    }
}