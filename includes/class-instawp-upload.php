<?php

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}

class InstaWP_Upload
{
    public $task_id;

    public function upload( $task_id,$remote_option=null ) {
        global $instawp_plugin;
        $this->task_id = $task_id;
        $task = new InstaWP_Backup_Task($task_id);
        $files = $task->get_backup_files();
        InstaWP_taskmanager::update_backup_main_task_progress($this->task_id,'upload',0,0);

        if ( is_null($remote_option) ) {
            $remote_options = InstaWP_taskmanager::get_task_options($this->task_id,'remote_options');

            if ( sizeof($remote_options) > 1 ) {
                $result = array(
					'result' => INSTAWP_FAILED,
					'error'  => 'not support multi remote storage',
				);
                $result = apply_filters('instawp_upload_files_to_multi_remote',$result,$task_id);

                if ( $result['result'] == INSTAWP_SUCCESS ) {
                    InstaWP_taskmanager::update_backup_main_task_progress($this->task_id,'upload',100,1);
                    InstaWP_taskmanager::update_backup_task_status($task_id,false,'completed');
                    return array( 'result' => INSTAWP_SUCCESS );
                }
                else {
                    InstaWP_taskmanager::update_backup_task_status($this->task_id,false,'error',false,false,$result['error']);
                    return array(
						'result' => INSTAWP_FAILED,
						'error'  => $result['error'],
					);
                }
            }
            else {
                $remote_option = array_shift($remote_options);

                if ( is_null($remote_option) ) {
                    return array(
						'result' => INSTAWP_FAILED,
						'error'  => 'not select remote storage',
					);
                }

                $remote = $instawp_plugin->remote_collection->get_remote($remote_option);

                $result = $remote->upload($this->task_id,$files,array( $this, 'upload_callback' ));

                if ( $result['result'] == INSTAWP_SUCCESS ) {
                    InstaWP_taskmanager::update_backup_main_task_progress($this->task_id,'upload',100,1);
                    InstaWP_taskmanager::update_backup_task_status($task_id,false,'completed');
                    return array( 'result' => INSTAWP_SUCCESS );
                }
                else {
                    $remote ->cleanup($files);

                    InstaWP_taskmanager::update_backup_task_status($this->task_id,false,'error',false,false,$result['error']);
                    return array(
						'result' => INSTAWP_FAILED,
						'error'  => $result['error'],
					);
                }
            }
        }
        else {
            $remote = $instawp_plugin->remote_collection->get_remote($remote_option);

            $result = $remote->upload($this->task_id,$files,array( $this, 'upload_callback' ));

            if ( $result['result'] == INSTAWP_SUCCESS ) {
                InstaWP_taskmanager::update_backup_main_task_progress($this->task_id,'upload',100,1);
                InstaWP_taskmanager::update_backup_task_status($task_id,false,'completed');
                return array( 'result' => INSTAWP_SUCCESS );
            }
            else {
                $remote ->cleanup($files);

                InstaWP_taskmanager::update_backup_task_status($this->task_id,false,'error',false,false,$result['error']);
                return array(
					'result' => INSTAWP_FAILED,
					'error'  => $result['error'],
				);
            }
        }
    }
    public function upload_api( $task_id,$remote_option=null ) {
        error_log("Upload API");
        global $instawp_plugin,$InstaWP_Curl;
        
        $this->task_id = $task_id;
        $task = new InstaWP_Backup_Task($task_id);
        $files = $task->get_backup_files();
        InstaWP_taskmanager::update_backup_main_task_progress($this->task_id,'upload',0,0);
        $result = $InstaWP_Curl->new_upload($files,$task_id); 
        $response = array();
        if ( $result['result'] == INSTAWP_SUCCESS ) {
                InstaWP_taskmanager::update_backup_main_task_progress($this->task_id,'upload',100,1);
                InstaWP_taskmanager::update_backup_task_status($task_id,false,'completed');
                

         $connect_ids = get_option('instawp_connect_id_options', '');
         if ( ! empty($connect_ids) ) {
            if ( isset($connect_ids['data']['id']) && ! empty($connect_ids['data']['id']) ) {
               $id  = $connect_ids['data']['id'];
               $api_doamin = InstaWP_Setting::get_api_domain();
               $url = $api_doamin . INSTAWP_API_URL . '/connects/' . $id . '/backup_status';

               $body = array(
				   "task_id"   => $this->task_id,
				   "type"      => 'upload',
				   "progress"  => 100,
				   "message"   => "Upload To Cloud",
				   "completed" => true,
               );
               $body_json     = json_encode($body);
               $curl_response = $InstaWP_Curl->curl($url, $body_json);
               
               if ( $curl_response['error'] == false ) {

                  $response              = (array) json_decode($curl_response['curl_res'], true);
                  $response['task_info'] = $body;
                  
                  update_option('instawp_backup_status_options', $response);
                  
                 // InstaWP_Setting::update_connect_option('instawp_connect_options',$response,$id,$this->task_id,'backup_status');
               }
               else {
                    update_option('instawp_backup_status_options_err', $curl_response);
               }
            }
         }
        error_log('instawp upload  \n '.print_r(get_option( 'instawp_backup_status_options'),true));
        update_option('instawp_finish_upload', $response);
      
                return array( 'result' => INSTAWP_SUCCESS );
            }
            else {   
                //$remote ->cleanup($files);
                
                InstaWP_taskmanager::update_backup_task_status($this->task_id,false,'error',false,false,$result['error']);
                return array(
                    'result' => INSTAWP_FAILED,
                    'error'  => $result['error'],
                );
            }

        // $bkp_init_opt = get_option('instawp_backup_init_options','');
        
        //        if ( ! empty($bkp_init_opt) ) {
                 
        //           $files_info = array();
        //           $backup_info = array();
        //           $backup_info_temp = array();
        //           //$task_id = $bkp_init_opt['task_info']['task_id'];
        //           $backups = InstaWP_Backuplist::get_backup_by_id($task_id);
        //           //update_option('del_upload_api',$backups);
        //           if ( isset( $backups['backup']['files'] ) && is_array( $backups['backup']['files'] ) && ! empty( $backups['backup']['files'] ) ) {

        //              foreach ( $backups['backup']['files'] as $backup ) {
        //                 $size = round($backup['size'] / 1024 / 1024);
        //                 $files_info['filename'] = $backup['file_name'];
        //                 $files_info['size'] = $size;
        //                 array_push($backup_info_temp, $files_info);
        //              }
        //              $backup_info['task_id'] = $task_id;
        //              $backup_info['backup_id'] = $task_id;
        //              $backup_info['files'] = $backup_info_temp;
        //              update_option('instawp_backup_finish_options',$backup_info);
        //              $result = $InstaWP_Curl->upload($backup_info,$this->task_id);   
        //           }
        //    }

            //$result = $remote->upload($this->task_id,$files,array( $this, 'upload_callback' ));

            
    }

    public function upload_callback( $offset,$current_name,$current_size,$last_time,$last_size ) {
        $job_data = array();
        $upload_data = array();
        $upload_data['offset'] = $offset;
        $upload_data['current_name'] = $current_name;
        $upload_data['current_size'] = $current_size;
        $upload_data['last_time'] = $last_time;
        $upload_data['last_size'] = $last_size;
        $upload_data['descript'] = 'Uploading '.$current_name;
        $v = ( $offset - $last_size ) / (time() - $last_time);
        $v /= 1000;
        $v = round($v,2);

        global $instawp_plugin;
        $instawp_plugin->check_cancel_backup($this->task_id);

        $message = 'Uploading '.$current_name.' Total size: '.size_format($current_size,2).' Uploaded: '.size_format($offset,2).' speed:'.$v.'kb/s';
        $instawp_plugin->instawp_log->WriteLog($message,'notice');
        $progress = intval(($offset / $current_size) * 100);
        InstaWP_taskmanager::update_backup_main_task_progress($this->task_id,'upload',$progress,0);
        InstaWP_taskmanager::update_backup_sub_task_progress($this->task_id,'upload','',INSTAWP_UPLOAD_UNDO,$message, $job_data, $upload_data);
    }

    public function get_backup_files( $backup ) {
        $backup_item = new InstaWP_Backup_Item($backup);

        return $backup_item->get_files();
    }

    public function clean_remote_backup( $remotes,$files ) {
        $remote_option = array_shift($remotes);

        if ( ! is_null($remote_option) ) {
            global $instawp_plugin;

            $remote = $instawp_plugin->remote_collection->get_remote($remote_option);
            $remote ->cleanup($files);
        }
    }
}