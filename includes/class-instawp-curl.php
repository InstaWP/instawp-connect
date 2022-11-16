<?php

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
   die;
}
if ( ! defined('INSTAWP_REMOTE_S3COMPAT') ) {
   define('INSTAWP_REMOTE_S3COMPAT', 's3compat');
}
if ( ! defined('INSTAWP_UPLOAD_SUCCESS') ) {
   define('INSTAWP_UPLOAD_SUCCESS', 1);
}

if ( ! defined('INSTAWP_UPLOAD_FAILED') ) {
   define('INSTAWP_UPLOAD_FAILED', 2);
}

if ( ! defined('INSTAWP_UPLOAD_UNDO') ) {
   define('INSTAWP_UPLOAD_UNDO', 0);
}
class InstaWP_Curl
{
   private $api_key;
   private $task_id;
   public $response;
   public $fp;
   public function __construct() {
      add_action( "http_api_curl", array($this,"instawp_http_api_curl") , 10, 3);

   }

   

   public function curl( $url, $body, $header = array(), $api_key = '' ) {
      global $instawp_plugin,$InstaWP_Backup_Api;
      $this->set_api_key();
      $res = array();
      $headers = array();
      $headers = array(
		  'Authorization: Bearer ' . $this->api_key,
		  'Accept: application/json',
		  'Content-Type: application/json',
      );
      if ( ! empty($header) ) {
         array_push($headers, $header);
      }
      $instawp_plugin->instawp_log->WriteLog( 'Initiate Api Call API_URL: '. $url .' Headres: ' . json_encode( $headers ) . ' Body: '.$body,'notice');
      $InstaWP_Backup_Api->instawp_log->WriteLog( 'Initiate Api Call API_URL: '. $url .' Headres: ' . json_encode( $headers ) . ' Body: '.$body,'notice');
      $useragent =  isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ;

      $args = array( 
            'method' => 'POST',
            'body' => $body,
            'timeout'     => 0,
            'decompress'     => false,
            'stream'     => false,
            'filename'     => '',
            'user-agent'     => $useragent,
            'headers' => array(
                 'Authorization' => 'Bearer ' . $this->api_key,
                 'Content-Type' => 'application/json',
                 'Accept' => 'application/json'
             )

          );
         $WP_Http_Curl = new WP_Http_Curl();
         $this->response = $WP_Http_Curl->request( $url, $args );
         update_option('main_curl_1',$this->response);
    //   $curl = curl_init();
    //   curl_setopt_array($curl, array(
		  // CURLOPT_URL            => $url,
		  // CURLOPT_RETURNTRANSFER => true,
		  // CURLOPT_ENCODING       => '',
		  // CURLOPT_MAXREDIRS      => 10,
		  // CURLOPT_TIMEOUT        => 0,
		  // CURLOPT_FOLLOWLOCATION => true,
		  // CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
		  // CURLOPT_CUSTOMREQUEST  => 'POST',
		  // CURLOPT_POSTFIELDS     => $body,
		  // CURLOPT_HTTPHEADER     => $headers,
    //   ));

    //   $this->response = curl_exec($curl);
    //   $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    //   update_option('main_curl_1',$this->response);
      if ( !$this->response ) {

         $res['message'] = '';
         $res['error']   = 1;
         $res['curl_res'] = $this->response;
         $instawp_plugin->instawp_log->WriteLog( 'Response:' . $this->response, 'error');
         $InstaWP_Backup_Api->instawp_log->WriteLog( 'Response:' . $this->response, 'error');
      } else {
         error_log('type check 1 = '.gettype( $this->response['body']));
         error_log('type check 2 = '.gettype(json_decode($this->response['body'])));
         $respons_arr = (array) json_decode($this->response['body']);
         error_log('type check 3 = '.gettype($respons_arr));
         if ( $respons_arr['status'] == 1 ) {

            $res['error']    = 0;
            $res['curl_res'] = $this->response['body'];
            $res['message'] = $respons_arr['message'];
            $respons_arr = $this->response;
            //$instawp_plugin->instawp_log->WriteLog( 'Message:' . $respons_arr['message']. ' API_URL : '.$url, 'success');
            $instawp_plugin->instawp_log->WriteLog( 'Response:' . $this->response['body'], 'response');
            $InstaWP_Backup_Api->instawp_log->WriteLog( 'Response:' . $this->response['body']. ' API_URL : '.$url, 'success');            
         }
         else {
            $res['message'] = $respons_arr['message'];
            $res['error']   = 1;
            $res['curl_res'] = $this->response;
            $respons_arr = (array) json_decode($this->response['body']);
            $instawp_plugin->instawp_log->WriteLog( 'Response:' . json_encode($respons_arr) . ' API_URL : '.$url, 'error');
            //$InstaWP_Backup_Api->instawp_log->WriteLog( 'Message:' . $respons_arr['message']. ' API_URL : '.$url, 'error');
            $InstaWP_Backup_Api->instawp_log->WriteLog( 'Response:' . json_encode($respons_arr), 'error');
         }      
}

      return $res;
   }

   public function set_api_key() {
      global $instawp_plugin;
      $res             = array();
      $connect_options = get_option('instawp_api_options', '');

      if ( isset($connect_options['api_key']) && ! empty($connect_options['api_key']) ) {
         $this->api_key = $connect_options['api_key'];
         $instawp_plugin->instawp_log->WriteLog( 'Set Api Key:' .$this->api_key, 'success');
      } else {
         $res['error']   = true;
         $res['message'] = 'API Key Is Required';
         echo json_encode($res);
         $instawp_plugin->instawp_log->WriteLog( 'API Key is required', 'success');
         wp_die();
      }
   }

   public function pre_upload( $task_id, $presigned_urls ) {

      if ( ! empty($presigned_urls) ) {
         InstaWP_taskmanager::update_backup_main_task_progress($task_id, 'upload_initiate', 0, 0);
         InstaWP_taskmanager::update_backup_task_status($task_id, false, 'upload_initiate');
         return array( 'result' => INSTAWP_SUCCESS );
      } else {
         return array( 'result' => INSTAWP_FAILED );
      }

   }

   public function new_upload( $files, $task_id ) {
      global $instawp_plugin;
      $this->task_id = $task_id;

      $InstaWP_BackupUploader = new InstaWP_BackupUploader();
      $res                    = $InstaWP_BackupUploader->_rescan_local_folder_set_backup_api();

      $upload_job = InstaWP_taskmanager::get_backup_sub_task_progress($task_id, 'upload', INSTAWP_REMOTE_S3COMPAT);
      if ( empty($upload_job) ) {
         $job_data = array();
         foreach ( $files as $file ) {
            $file_data['size']         = filesize($file);
            $file_data['uploaded']     = 0;
            $job_data[ basename($file) ] = $file_data;
         }
         InstaWP_taskmanager::update_backup_sub_task_progress($task_id, 'upload', INSTAWP_REMOTE_S3COMPAT, INSTAWP_UPLOAD_UNDO, 'Start uploading', $job_data);
         $upload_job = InstaWP_taskmanager::get_backup_sub_task_progress($task_id, 'upload', INSTAWP_REMOTE_S3COMPAT);
      }

      $files_info       = array();
      $backup_info      = array();
      $backup_info_temp = array();
      foreach ( $files as $file ) {
         $filesize               = filesize($file);
         $size                   = round($filesize / 1024 / 1024);
         $files_info['filename'] = basename($file);
         $files_info['size']     = $size;
         array_push($backup_info_temp, $files_info);
      }
      $backup_info['task_id']   = $task_id;
      $backup_info['backup_id'] = $task_id;
      $backup_info['files']     = $backup_info_temp;
      update_option('instawp_init_upload', $backup_info);

      $presigned_urls = $this->get_presigned_url($backup_info);
      update_option('presigned_urls',$presigned_urls);
      
      if ( empty($presigned_urls) ) {

         $instawp_plugin->instawp_log->WriteLog( 'Presinged URLs are missing', 'error');
         // return array(
         // 'result' => INSTAWP_FAILED,
         // 'error'  => 'Upload Faild',
         // );
      }
      foreach ( $files as $file_key => $file ) {
         if ( is_array($upload_job['job_data']) && array_key_exists(basename($file), $upload_job['job_data']) ) {
            if ( $upload_job['job_data'][ basename($file) ]['uploaded'] == 1 ) {
               continue;
            }         
}
         //$instawp_plugin->set_time_limit($task_id);
         if ( ! file_exists($file) ) {
            $instawp_plugin->instawp_log->WriteLog('Uploading ' . basename($file) . ' failed.', 'notice');
            return array(
				'result' => INSTAWP_FAILED,
				'error'  => $file . ' not found. The file might has been moved, renamed or deleted. Please reload the list and verify the file exists.',
			);
         }
         if ( isset( $presigned_urls['data']['urls'][ $file_key ] ) && ! empty( $presigned_urls['data']['urls'][ $file_key ] ) ) {
            $presigned_url                                       = $presigned_urls['data']['urls'][ $file_key ];
            $instawp_plugin->instawp_log->WriteLog('Start uploading URL:' . $presigned_url, 'notice');
            $result                                              = $this->_upload_loop($file, $presigned_url, $file_key);
            $upload_job['job_data'][ basename($file) ]['uploaded'] = 1;
            $instawp_plugin->instawp_log->WriteLog('Finished uploading ' . basename($file), 'notice');
            InstaWP_taskmanager::update_backup_sub_task_progress($task_id, 'upload', INSTAWP_REMOTE_S3COMPAT, INSTAWP_UPLOAD_SUCCESS, 'Uploading ' . basename($file) . ' completed.', $upload_job['job_data']);
            update_option('instawp_job_data', $upload_job);   
         }
         else {
            $instawp_plugin->instawp_log->WriteLog('Failed uploading. Reason: Presigned URL is missing for ' . basename($file), 'error');
         }      
}
      return $result;
   }

   public function instawp_http_api_curl( $ch, $parsed_args, $url ) {

      if( isset($parsed_args['upload']) &&  $parsed_args['upload'] == true) {
         curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded ) {
            $this->upload_progress_callback($resource, $downloadSize, $downloaded, $uploadSize, $uploaded);
         });
         curl_setopt($ch, CURLOPT_NOPROGRESS, false);   
      }
      if( isset($parsed_args['download']) &&  $parsed_args['download'] == true) { 
         curl_setopt($ch, CURLOPT_VERBOSE, 1);
         curl_setopt($ch, CURLOPT_AUTOREFERER, false);
         curl_setopt($ch, CURLOPT_FILE, $this->fp);
         curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded ) {
                     $this->download_progress_callback($resource, $downloadSize, $downloaded, $uploadSize, $uploaded);
                  });
          curl_setopt($ch, CURLOPT_NOPROGRESS, false);
      }
      
      
   }

   public function _upload_loop( $files, $presigned_url, $file_key ) {
      global $instawp_plugin;
      $parts     = array();
      $backupdir = 'instawpbackups';
      $path      = $files;
      $success   = false;
      $filesize = strlen($path);
      
      $curl_error = '';
      $useragent =  isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ;
      for ( $i = 0; $i < INSTAWP_REMOTE_CONNECT_RETRY_TIMES; $i++ ) {
         
         $args = array( 
            'method' => 'PUT',
            'body' => file_get_contents($path),
            'timeout'     => 0,
            'decompress'     => false,
            'stream'     => false,
            'filename'     => '',
            'user-agent'     => $useragent,
            'headers' => array(
                 'Content-Type' => 'multipart/form-data'
             ),
            'upload' => true

          );
         $WP_Http_Curl = new WP_Http_Curl();
         $this->response = $WP_Http_Curl->request( $presigned_url, $args );
         // $ch = curl_init();

         // curl_setopt($ch, CURLOPT_URL, $presigned_url);
         // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

         // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
         
         // $headers   = array();
         // $headers[] = 'multipart/form-data';
         // curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

         

         // curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($path));

         // curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded ) use ( $files ) {
         //    $this->upload_progress_callback($resource, $downloadSize, $downloaded, $uploadSize, $uploaded, $files);
         // });
         // curl_setopt($ch, CURLOPT_NOPROGRESS, false);

         // $this->response = curl_exec($ch);
      
         if ( ! $this->response ) {
            //echo 'Error:' . curl_error($ch);
            //$curl_error = curl_error($ch);
            update_option('instawp_demo_parts_error', json_encode( $this->response ) );
            $instawp_plugin->instawp_log->WriteLog(json_encode( $this->response ), 'fail');
         } else {
            update_option('instawp_demo_parts', json_encode( $this->response ));
            return array(
				'result' => INSTAWP_SUCCESS,
            );
            //return array( 'result' => INSTAWP_SUCCESS );
         }

         //curl_close($ch);

      }
      $instawp_plugin->instawp_log->WriteLog('Failed uploading. Reason:' . json_encode( $this->response ), 'error');
      return array(
         
		  'result' => INSTAWP_FAILED,
		  'error'  => 'Multipart upload failed. File name: ' . basename($path),
      );

   }

   public function get_presigned_url( $backup_info ) {
      global $instawp_plugin;
      $backup_info['task_id'] = $this->task_id;
      $backup_info_json = json_encode($backup_info);
      $connect_ids      = get_option('instawp_connect_id_options', '');

      if ( ! empty($connect_ids) ) {
         if ( isset($connect_ids['data']['id']) && ! empty($connect_ids['data']['id']) ) {
            $id            = $connect_ids['data']['id'];
            $api_doamin = InstaWP_Setting::get_api_domain();
            $url           = $api_doamin . INSTAWP_API_URL . '/connects/' . $id . '/backup_upload';
            $curl_response = $this->curl($url, $backup_info_json);

            if ( $curl_response['error'] == 0 ) {

               $response = (array) json_decode($curl_response['curl_res'], true);

               if ( $response['status'] == 1 ) {

                  update_option('instawp_backup_upload_options', $response); // old 
                  //$backup_init[ 'presigned_urls' ] = $response;
                  //InstaWP_Setting::update_connect_option('instawp_connect_options',$response,$id,$this->task_id,'backup_upload');
                  $instawp_plugin->instawp_log->WriteLog('Presigned URLs Saved' . $url, 'success');
                  return $response;
                  // return array(
                  // 'result' => INSTAWP_SUCCESS,
                  // 'error'  => 'Multipart upload failed. File name: ' . $this->current_file_name,
                  // );

                  
               } else {
                  $instawp_plugin->instawp_log->WriteLog('Presigned URLs Missing API_URL: ' . $url, 'error');
                  return array(
					  'result' => INSTAWP_FAILED,
					  'error'  => 'Multipart upload failed. File name: ' . $this->current_file_name,
				  );
                  
                  update_option('instawp_backup_upload_err_options', $curl_reponse);
               }
            }
         }
      }
   }
   public function upload_progress_callback( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded, $files = array() ) {
      global $instawp_plugin;
      $progress = 0;
      $current_size = intval($uploadSize - $uploaded);
      $dummy        = array();
      //array_push($dummy,$resource);
      $dummy['downloadSize'] = $downloadSize;
      $dummy['downloaded']   = $downloaded;
      $dummy['uploadSize']   = $uploadSize;
      $dummy['uploaded']     = $uploaded;
      $dummy['current_size'] = $current_size;
      //$dummy['files']        = basename($files);
      $offset                = $uploaded;
      //$progress = intval( round( $uploaded * 100 / $uploadSize ) );
      if ( $uploadSize > 0 ) {
         $progress = round(($uploaded / $uploadSize) * 100);   
      }
      
      //$progress = intval(($offset / $uploadSize) * 100);
      $dummy['progress'] = $progress;
      InstaWP_taskmanager::update_backup_main_task_progress($this->task_id, 'upload', $progress, 0);
      //InstaWP_taskmanager::update_backup_main_current_upload($this->task_id, 'current_upload', basename($files));
      // InstaWP_taskmanager::update_file_desc($this->task_id, $files['filename']);
      update_option('instawp_curl_progress', $dummy);
      $instawp_plugin->instawp_log->WriteLog('Upload Progress' . json_encode($dummy), 'notice');

   }

   public function download( $task_id, $urls ) {
      global $instawp_plugin,$InstaWP_Backup_Api;
      $this->task_id = $task_id;
      // $instawp_plugin->end_shutdown_function = false;
      // register_shutdown_function(array($instawp_plugin, 'deal_shutdown_error'), $this->task_id);
      InstaWP_taskmanager::update_backup_main_task_progress($this->task_id,'download',0,0);
      $download_job = InstaWP_taskmanager::get_backup_sub_task_progress($task_id, 'download', INSTAWP_REMOTE_S3COMPAT);
      if ( empty($download_job) ) {
         $job_data = array();
         foreach ( $urls as $url ) {
            $basename = basename(parse_url($url, PHP_URL_PATH));
            $file_data['size']         = 0;
            $file_data['downloaded']   = 0;
            $job_data[ $basename ] = $file_data;
         }
         InstaWP_taskmanager::update_backup_sub_task_progress($task_id, 'download', INSTAWP_REMOTE_S3COMPAT, INSTAWP_UPLOAD_UNDO, 'Start downloading', $job_data);
         $download_job = InstaWP_taskmanager::get_backup_sub_task_progress($task_id, 'download', INSTAWP_REMOTE_S3COMPAT);
      }

      foreach ( $urls as $url ) {
         $basename = basename(parse_url($url, PHP_URL_PATH));
         if ( is_array($download_job['job_data']) && array_key_exists($basename, $download_job['job_data']) ) {
            if ( $download_job['job_data'][ $basename ]['downloaded'] == 1 ) {
               continue;
            }         
}
         $instawp_plugin->set_time_limit($task_id);
         $result = $this->download_loop($url);
         $download_job['job_data'][ $basename ]['downloaded'] = 1;
         $instawp_plugin->instawp_log->WriteLog('Finished Downloading ' . basename($basename), 'notice');
         $InstaWP_Backup_Api->instawp_log->WriteLog('Finished Downloading ' . basename($basename), 'notice');

         InstaWP_taskmanager::update_backup_sub_task_progress($task_id, 'upload', INSTAWP_REMOTE_S3COMPAT, INSTAWP_UPLOAD_SUCCESS, 'Uploading ' . basename($basename) . ' completed.', $download_job['job_data']);
         update_option('instawp_job_data', $download_job);
      }
      if ( $result['result'] == INSTAWP_SUCCESS ) {
        InstaWP_taskmanager::update_backup_main_task_progress($this->task_id,'download',100,1);
        InstaWP_taskmanager::update_backup_task_status($this->task_id,false,'completed');
       }
      return $result;
   }
   public function download_loop( $url ) {
      global $instawp_plugin,$InstaWP_Backup_Api;
      // $instawp_plugin->end_shutdown_function = false;
      // register_shutdown_function(array($instawp_plugin, 'deal_shutdown_error'), $this->task_id);
      $InstaWP_Backup_Api->instawp_log->WriteLog('File URL: ' .$url , 'notice');
      $basename = basename(parse_url($url, PHP_URL_PATH));

      // extracted basename
      
      $output_filename = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . InstaWP_Setting::get_backupdir() . DIRECTORY_SEPARATOR . basename($basename);
      $this->fp = fopen($output_filename, 'w+');
      $useragent =  isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ;
      for ( $i = 0; $i < INSTAWP_REMOTE_CONNECT_RETRY_TIMES; $i++ ) {
         $args = array(             
            'timeout'     => 0,            
            'download' => true,
            'decompress'     => false,
            'stream'     => false,
            'filename'     => '',
            'user-agent'     => $useragent,
          );
         $WP_Http_Curl = new WP_Http_Curl();
         $this->response = $WP_Http_Curl->request( $url, $args );   

         // $ch = curl_init();

         // curl_setopt($ch, CURLOPT_URL, $url);
         // curl_setopt($ch, CURLOPT_VERBOSE, 1);
         // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         // curl_setopt($ch, CURLOPT_AUTOREFERER, false);
         
         // curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
         // curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded ) {
         //    $this->download_progress_callback($resource, $downloadSize, $downloaded, $uploadSize, $uploaded);
         // });
         // curl_setopt($ch, CURLOPT_NOPROGRESS, false);
         // curl_setopt($ch, CURLOPT_HEADER, 0);
         // curl_setopt($ch, CURLOPT_FILE, $fp);

         // $this->response = curl_exec($ch);

         if ( !$this->response )  {
            
            $InstaWP_Backup_Api->instawp_log->WriteLog( json_encode( $this->response ) , 'error');
            update_option('instawp_demo_parts_error', json_encode( $this->response ) );
             return array(
				 'result' => INSTAWP_FAILED,
				 'error'  => curl_error($ch),
			 );
         } else {
            update_option('instawp_demo_parts', $this->response);
            //$this->_output( $output_filename,$this->response );
            // $fp = fopen($output_filename, 'w');
            // fwrite($fp, $this->response);
            // fclose($fp);
            return array(
				'result' => INSTAWP_SUCCESS,
            );
            //return array( 'result' => INSTAWP_SUCCESS );
         }
         curl_close($ch);
      }
      return array(
		  'result' => INSTAWP_FAILED,
		  'error'  => 'Multipart upload failed. File name:',
      );
      // the following lines write the contents to a file in the same directory (provided permissions etc)
      fclose($fp);
   }
   public function _output( $output_filename,$content ) {

      $fp = fopen($output_filename, 'w+');
      //fwrite($fp, $this->response);
      
       $pieces = str_split($content, 1024 * 4);
       foreach ( $pieces as $piece ) {
           fwrite($fp, $piece, strlen($piece));
       }
       fclose($fp);
   }
   public function download_progress_callback( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded, $files = array() ) {
      global $InstaWP_Backup_Api; 

      
      $current_size = intval($downloadSize - $downloaded);
      $dummy        = array();
      //array_push($dummy,$resource);
      $dummy['downloadSize'] = $downloadSize;
      $dummy['downloaded']   = $downloaded;
      $dummy['uploadSize']   = $uploadSize;
      $dummy['uploaded']     = $uploaded;
      $dummy['current_size'] = $current_size;
      //$dummy['files']        = basename($files);
      $offset                = $downloaded;
      //$progress = intval( round( $uploaded * 100 / $uploadSize ) );
      
      if ( $downloadSize > 0 ) {

         $progress = round( ($downloaded / $downloadSize) * 100 );  
      }
      else {
         $progress = 0;
      }
      
      //$progress = intval(($offset / $uploadSize) * 100);
      $dummy['progress'] = $progress;
      InstaWP_taskmanager::update_backup_main_task_progress($this->task_id, 'download', $progress, 0);
      update_option('instawp_curl_download_progress', $dummy);
      $InstaWP_Backup_Api->instawp_log->WriteLog('Download Progress : '.json_encode($dummy), 'notice');
      if ( $downloaded >= $downloadSize ) {
         //$InstaWP_Backup_Api->instawp_log->WriteLog('Finish download : '.json_encode($dummy), 'notice');
         update_option('instawp_finish_download', $dummy);
      }

   }
}
global $InstaWP_Curl;
$InstaWP_Curl = new InstaWP_Curl();
