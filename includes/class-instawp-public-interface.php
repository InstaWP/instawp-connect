<?php

class InstaWP_Public_Interface
{
    public function __construct() {

    }

    public function mainwp_data( $data ) {
        $action = sanitize_text_field($data['mwp_action']);
        if ( has_filter($action) ) {
            $ret = apply_filters($action, $data);
        } else {
            $ret['result'] = INSTAWP_FAILED;
            $ret['error'] = 'Unknown function';
        }
        return $ret;
    }

    public function prepare_backup( $backup_options ) {
        global $instawp_plugin;
        if ( isset($backup_options) && ! empty($backup_options) ) {
            if ( is_null($backup_options) ) {
                $ret['error'] = 'Invalid parameter param:'.$backup_options;
                return $ret;
            }
            $backup_options = apply_filters('instawp_custom_backup_options', $backup_options);

            if ( ! isset($backup_options['type']) ) {
                $backup_options['type'] = __('Manual', 'instawp-connect');
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

    public function backup_now( $task_id ) {
        global $instawp_plugin;
        if ( ! isset($task_id) || empty($task_id) || ! is_string($task_id) ) {
            $ret['error'] = __('Error occurred while parsing the request data. Please try to run backup again.', 'instawp-connect');
            return $ret;
        }
        $task_id = sanitize_key($task_id);
        $ret['result'] = 'success';
        $txt = '<mainwp>' . base64_encode( serialize( $ret ) ) . '</mainwp>';
        // Close browser connection so that it can resume AJAX polling
        header( 'Content-Length: ' . ( ( ! empty( $txt ) ) ? strlen( $txt ) : '0' ) );
        header( 'Connection: close' );
        header( 'Content-Encoding: none' );
        if ( session_id() ) {
            session_write_close();
        }
        echo wp_kses_post( $txt );
        // These two added - 19-Feb-15 - started being required on local dev machine, for unknown reason (probably some plugin that started an output buffer).
        if ( ob_get_level() ) {
            ob_end_flush();
        }
        flush();

        $instawp_plugin->flush($task_id);
        //Start backup site
        $instawp_plugin->backup($task_id);
        $ret['result'] = 'success';
    }

    public function get_status(){
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

    

    public function get_backup_list(){
        $backuplist = InstaWP_Backuplist::get_backuplist();
        $ret['result'] = 'success';
        $ret['instawp']['backup_list'] = $backuplist;
        return $ret;
    }

    public function get_default_remote(){
        global $instawp_plugin;
        $ret['result'] = 'success';
        $ret['remote_storage_type'] = $instawp_plugin->function_realize->_get_default_remote_storage();
        return $ret;
    }

    public function delete_backup( $backup_id, $force_del ) {
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

    public function delete_backup_array( $backup_id_array ) {
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

    public function set_security_lock( $backup_id, $lock ) {
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

    public function view_log( $backup_id ) {
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

    public function read_last_backup_log( $log_file_name ) {
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

    public function view_backup_task_log( $backup_task_id ) {
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

    public function backup_cancel( $task_id = '' ) {
        global $instawp_plugin;
        $ret = $instawp_plugin->function_realize->_backup_cancel();
        return $ret;
    }

    public function init_download_page( $backup_id ) {
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

    public function prepare_download_backup( $backup_id, $file_name ) {
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

    public function get_download_task( $backup_id ) {
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

    public function download_backup( $backup_id, $file_name ) {
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

    public function set_general_setting( $setting ) {
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

    public function set_schedule( $schedule ) {
        $ret = array();
        try {
            if ( isset($schedule) && ! empty($schedule) ) {
                $json = $schedule;
                $json = stripslashes($json);
                $schedule = json_decode($json, true);
                if ( is_null($schedule) ) {
                    $ret['error'] = 'bad parameter';
                    return $ret;
                }
                $ret = InstaWP_Schedule::set_schedule_ex($schedule);
                if ( $ret['result'] != 'success' ) {
                    return $ret;
                }
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

    public function set_remote( $remote ) {
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