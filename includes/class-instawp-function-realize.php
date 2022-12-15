<?php

class InstaWP_Function_Realize
{
    public function __construct() {

    }

    public function _backup_cancel( $task_id = '' ) {
        global $instawp_plugin;
        try {
            $tasks = InstaWP_taskmanager::get_tasks();
            foreach ( $tasks as $task ) {
                $task_id = $task['id'];
                $status = InstaWP_taskmanager::get_backup_task_status($task_id);
                $time_spend = $status['run_time'] - $status['start_time'];
                $options = InstaWP_Setting::get_option('instawp_common_setting');
                if ( isset($options['max_execution_time']) ) {
                    $limit = $options['max_execution_time'];
                }
                else {
                    $limit = INSTAWP_MAX_EXECUTION_TIME;
                }
                if ( $time_spend > $limit * 2 ) {
                    $file_name = InstaWP_taskmanager::get_task_options($task_id, 'file_prefix');
                    $backup_options = InstaWP_taskmanager::get_task_options($task_id, 'backup_options');
                    $file = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backup_options['dir'] . DIRECTORY_SEPARATOR . $file_name . '_cancel';
                    touch($file);

                    if ( $instawp_plugin->instawp_log->log_file_handle == false ) {
                        $instawp_plugin->instawp_log->OpenLogFile(InstaWP_taskmanager::get_task_options($task_id,'log_file_name'));
                    }
                    $instawp_plugin->instawp_log->WriteLog('Backup cancelled. Twice the setting time.','notice');
                    $task = new InstaWP_Backup_Task($task_id);
                    $task->update_status('cancel');
                    $instawp_plugin->clean_backing_up_data_event($task_id);
                    
                    InstaWP_taskmanager::delete_task($task_id);
                }
                else {
                    $file_name = InstaWP_taskmanager::get_task_options($task_id, 'file_prefix');
                    $backup_options = InstaWP_taskmanager::get_task_options($task_id, 'backup_options');
                    $file = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backup_options['dir'] . DIRECTORY_SEPARATOR . $file_name . '_cancel';
                    touch($file);

                    $timestamp = wp_next_scheduled(INSTAWP_TASK_MONITOR_EVENT, array( $task_id ));

                    if ( $timestamp === false ) {
                        $instawp_plugin->add_monitor_event($task_id, 10);
                    }
                }
            }

            /*if (InstaWP_taskmanager::get_task($task_id) !== false) {
                $file_name = InstaWP_taskmanager::get_task_options($task_id, 'file_prefix');
                $backup_options = InstaWP_taskmanager::get_task_options($task_id, 'backup_options');
                $file = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backup_options['dir'] . DIRECTORY_SEPARATOR . $file_name . '_cancel';
                touch($file);
            }

            $timestamp = wp_next_scheduled(INSTAWP_TASK_MONITOR_EVENT, array($task_id));

            if ($timestamp === false) {
                $instawp_plugin->add_monitor_event($task_id, 10);
            }*/
            $ret['result'] = 'success';
            $ret['msg'] = __('The backup will be canceled after backing up the current chunk ends.', 'instawp-connect');
        }
        catch ( Exception $error ) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            return array(
				'result' => 'failed', 
				'error'  => $message,
			);
        }
        catch ( Error $error ) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            return array(
				'result' => 'failed', 
				'error'  => $message,
			);
        }
        return $ret;
    }

    public function _get_log_file( $read_type, $param ) {
        global $instawp_plugin;
        $ret['result'] = 'failed';
        if ( $read_type == 'backuplist' ) {
            $backup_id = $param;
            $backup = InstaWP_Backuplist::get_backup_by_id($backup_id);
            if ( ! $backup ) {
                $ret['result'] = 'failed';
                $ret['error'] = __('Retrieving the backup information failed while showing log. Please try again later.', 'instawp-connect');
                return $ret;
            }
            if ( ! file_exists($backup['log']) ) {
                $ret['result'] = 'failed';
                $ret['error'] = __('The log not found.', 'instawp-connect');
                return $ret;
            }
            $ret['result'] = 'success';
            $ret['log_file'] = $backup['log'];
        }
        elseif ( $read_type == 'lastlog' ) {
            $option = $param;
            $log_file_name = $instawp_plugin->instawp_log->GetSaveLogFolder().$option.'_log.txt';
            if ( ! file_exists($log_file_name) ) {
                $information['result'] = 'failed';
                $information['error'] = __('The log not found.', 'instawp-connect');
                return $information;
            }
            $ret['result'] = 'success';
            $ret['log_file'] = $log_file_name;
        }
        elseif ( $read_type == 'tasklog' ) {
            $backup_task_id = $param;
            $option = InstaWP_taskmanager::get_task_options($backup_task_id,'log_file_name');
            if ( ! $option ) {
                $information['result'] = 'failed';
                $information['error'] = __('Retrieving the backup information failed while showing log. Please try again later.', 'instawp-connect');
                return $information;
            }
            $log_file_name = $instawp_plugin->instawp_log->GetSaveLogFolder().$option.'_log.txt';
            if ( ! file_exists($log_file_name) ) {
                $information['result'] = 'failed';
                $information['error'] = __('The log not found.', 'instawp-connect');
                return $information;
            }
            $ret['result'] = 'success';
            $ret['log_file'] = $log_file_name;
        }
        return $ret;
    }

    public function _set_remote( $remote ) {
        InstaWP_Setting::update_option('instawp_upload_setting',$remote['upload']);
        $history = InstaWP_Setting::get_option('instawp_user_history');
        $history['remote_selected'] = $remote['history']['remote_selected'];
        InstaWP_Setting::update_option('instawp_user_history',$history);
    }

    public function _get_default_remote_storage(){
        $remote_storage_type = '';
        $remoteslist = InstaWP_Setting::get_all_remote_options();
        $default_remote_storage = '';
        foreach ( $remoteslist['remote_selected'] as $value ) {
            $default_remote_storage = $value;
        }
        foreach ( $remoteslist as $key => $value ) {
            if ( $key === $default_remote_storage ) {
                $remote_storage_type = $value['type'];
            }
        }
        return $remote_storage_type;
    }
}