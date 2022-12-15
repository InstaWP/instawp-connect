<?php

class InstaWP_Backup_Api
{

   private $namespace;
   private $version;
   public $instawp_log;
   public $config_log_file_name = 'config';
   public $download_log_file_name = 'backup_download';
   public function __construct() {
      $this->version   = 'v1';
      $this->version_2   = 'v2';
      $this->namespace = 'instawp-connect';

      add_action('rest_api_init', array( $this, 'add_api_routes' ));
       $this->instawp_log          = new InstaWP_Log();

   }
   public function add_api_routes() {


      register_rest_route($this->namespace . '/' . $this->version, 'backup', array(
		  'methods'             => 'POST',
		  'callback'            => array( $this, 'backup' ),
		  'permission_callback' => '__return_true',
      ));

      register_rest_route($this->namespace . '/' . $this->version, 'test-backup', array(
		  'methods'             => 'POST',
		  'callback'            => array( $this, 'test_backup' ),
		  'permission_callback' => '__return_true',
      ));

      register_rest_route($this->namespace . '/' . $this->version, 'restore', array(
		  'methods'             => 'POST',
		  'callback'            => array( $this, 'restore' ),
		  'permission_callback' => '__return_true',
      ));

      register_rest_route($this->namespace . '/' . $this->version, 'test', array(
		  'methods'             => 'POST',
		  'callback'            => array( $this, 'test' ),
		  'permission_callback' => '__return_true',
      ));

      register_rest_route($this->namespace . '/' . $this->version, 'config', array(
		  'methods'             => 'POST',
		  'callback'            => array( $this, 'config' ),
		  'permission_callback' => '__return_true',
      ));

      register_rest_route($this->namespace . '/' . $this->version, 'task_status/(?P<task_id>\w+)', array(
		  'methods'             => 'GET',
		  'callback'            => array( $this, 'task_status' ),
		  'permission_callback' => '__return_true',

      ));
      register_rest_route($this->namespace . '/' . $this->version, 'upload_status/(?P<task_id>\w+)', array(
		  'methods'             => 'GET',
		  'callback'            => array( $this, 'upload_status' ),
		  'permission_callback' => '__return_true',

      ));

      //autologin code call endpoint
      register_rest_route($this->namespace . '/' . $this->version_2, '/auto-login-code', array(
         'methods'             => 'POST',
         'callback'            => array( $this, 'instawp_handle_auto_login_code' ),
         'permission_callback' => '__return_true',
      ));

      //autologin endpoint
      register_rest_route($this->namespace . '/' . $this->version_2, '/auto-login', array(
         'methods'             => 'POST',
         'callback'            => array( $this, 'instawp_handle_auto_login' ),
         'permission_callback' => '__return_true',
      ));


	   // clear cache
	   register_rest_route($this->namespace . '/' . $this->version_2, '/clear-cache', array(
		   'methods'             => 'POST',
		   'callback'            => array( $this, 'instawp_handle_clear_cache' ),
		   'permission_callback' => '__return_true',
	   ));
   }


	/**
	 * Handle response for clear cache endpoint
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
   function instawp_handle_clear_cache( WP_REST_Request $request ) {

	    $param_api_key   = sanitize_text_field( $request->get_param('api_key') );
	    $connect_options = get_option( 'instawp_api_options', '' );
	    $current_api_key = $connect_options['api_key'] ?? '';

		// check if the api key is empty
	    if( empty( $param_api_key ) ) {
			return new WP_REST_Response( array( 'error' => true, 'message' => esc_html('Key parameter missing' ) ) );
	    }

		// check is the api key mismatched
	    if( $current_api_key !== $param_api_key ) {
		    return new WP_REST_Response( array( 'error' => true, 'message' => esc_html('API key mismatch' ) ) );
	    }

		if( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

	    // Clear cache for - WP Rocket
	    if( is_plugin_active('wp-rocket/wp-rocket.php' ) ) {
		    rocket_clean_minify();
			rocket_clean_domain();
	    }

	    // Clear cache for - W3 Total Cache
	    if( is_plugin_active('w3-total-cache/w3-total-cache.php' ) ) {
		    w3tc_flush_all();
	    }

	    // Clear cache for - Autoptimize
		if( is_plugin_active('autoptimize/autoptimize.php' ) ) {
			autoptimizeCache::clearall();
	    }

	    // Clear cache for - Lite Speed Cache
	    if( is_plugin_active('litespeed-cache/litespeed-cache.php' ) ) {
			\LiteSpeed\Purge::purge_all();
	    }

	    // Clear cache for - WP Fastest Cache
	    if( is_plugin_active('wp-fastest-cache/wpFastestCache.php' ) ) {
		    wpfc_clear_all_site_cache();
		    wpfc_clear_all_cache( true );
	    }

	    return new WP_REST_Response( array( 'error' => false, 'message' => esc_html('Cache clear success' ) ) );
    }


  /**
    * Handle repsonse for login code generate
    * */
   public function instawp_handle_auto_login_code ( $request ){
      $response_array = array();

      // Hashed string
      $param_api_key = $request->get_param('api_key');

      $connect_options = get_option('instawp_api_options', '');

      // Non hashed
      $current_api_key = !empty($connect_options) ? $connect_options['api_key'] : "";

      $current_api_key_hash = "";

      // check for pipe
      if (!empty($current_api_key) && strpos($current_api_key, '|') !== false) {
         $exploded = explode('|', $current_api_key);
         $current_api_key_hash = hash('sha256', $exploded[1]);
      }else{
         $current_api_key_hash = !empty($current_api_key) ? hash('sha256', $current_api_key) : "";
      }

      if (
         !empty($param_api_key) &&
         $param_api_key === $current_api_key_hash
      ) {
         $uuid_code = wp_generate_uuid4();
         $uuid_code_256 = str_shuffle( $uuid_code . $uuid_code );

         $auto_login_api = get_rest_url(null, '/' . $this->namespace . '/' . $this->version_2 . "/auto-login");

         $message = "success";
         $response_array = array(
            'code' => $uuid_code_256,
            'message' => $message
         );
         set_transient('instawp_auto_login_code', $uuid_code_256, 8 * HOUR_IN_SECONDS);
      }else{
         $message = "request invalid - ";

         if ( empty($param_api_key) ) { // api key parameter is empty
            $message .= "key parameter missing";
         }
         elseif ($param_api_key !== $current_api_key_hash) { // local and param, api key hash not matched
            $message .= "api key mismatch";
         }
         else{ // default response
            $message = "invalid request";
         }

         $response_array = array(
            'error'   => true,
            'message' => $message,
         );
      }

      $response = new WP_REST_Response($response_array);
      $response->set_status(200);
      return $response;
   }

   /**
    * Auto login url generate
    * */
   public function instawp_handle_auto_login( $request )
   {
      $response_array = array();

      $param_api_key = $request->get_param('api_key');
      $param_code = $request->get_param('c');
      $param_user = $request->get_param('s');

      $connect_options = get_option('instawp_api_options', '');

      // Non hashed
      $current_api_key = !empty($connect_options) ? $connect_options['api_key'] : '';

      $current_login_code = get_transient( 'instawp_auto_login_code' );

      $current_api_key_hash = "";

      // check for pipe
      if (!empty($current_api_key) && strpos($current_api_key, '|') !== false) {
         $exploded = explode('|', $current_api_key);
         $current_api_key_hash = hash('sha256', $exploded[1]);
      }else{
         $current_api_key_hash = !empty($current_api_key) ? hash('sha256', $current_api_key) : "";
      }

      if (
         !empty( $param_api_key ) &&
         !empty( $param_code ) &&
         !empty( $param_user ) &&
         $param_api_key === $current_api_key_hash &&
         false !== $current_login_code &&
         $param_code === $current_login_code
      ) {
         // Decoded user
         $site_user = base64_decode( $param_user );

         // Make url
         $auto_login_url = add_query_arg(
            array(
               'c' => $param_code,
               's' => base64_encode( $site_user )
            ),
            wp_login_url('', true)
         );
         // Auto Login Logic to be written
         $message = "success";
         $response_array = array(
            'error'   => false,
            'message' => $message,
            'login_url' => $auto_login_url,
         );
      }else{
         $message = "request invalid - ";

         if ( empty($param_api_key) ) { // api key parameter is empty
            $message .= "key parameter missing";
         }
         elseif ( empty($param_code) ) { // code parameter is empty
            $message .= "code parameter missing";
         }
         elseif ( empty($param_user) ) { // user parameter is empty
            $message .= "user parameter missing";
         }
         elseif ($param_api_key !== $current_api_key_hash) { // local and param, api key hash not matched
            $message .= "api key mismatch";
         }
         elseif ($param_code !== $current_login_code) { // local and param, code not matched
            $message .= "code mismatch";
         }
         elseif (false === $current_login_code) { // local code parameter option not set
            $message .= "code expired";
         }
         else{ // default response
            $message = "invalid request";
         }

         $response_array = array(
            'error'   => true,
            'message' => $message,
         );
      }

      $response = new WP_REST_Response( $response_array );
      $response->set_status(200);
      return $response;
   }

   public function test_backup( $request ) {
      $parameters = $request->get_params();
      $backups    = InstaWP_Backuplist::get_backup_by_id('6305fbad810e0');
      $backupdir  = InstaWP_Setting::get_backupdir();
      $files      = array( $backup['backup']['files'][0]['file_name'] );

      foreach ( $backups['backup']['files'] as $backup ) {
         print_r($backup);
      }

      $filePath = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backupdir . DIRECTORY_SEPARATOR . $backup['backup']['files'][0]['file_name'];

      $response = new WP_REST_Response($backups);
      $response->set_status(200);
      return $response;
   }

   public function config( $request ) {
      $parameters = $request->get_params();
      $results    = array(
		  'status'     => false,
		  'connect_id' => 0,
		  'message'    => '',
      );
      $this->instawp_log->CreateLogFile($this->config_log_file_name, 'no_folder', 'Remote Config');
      $this->instawp_log->WriteLog('Inti Api Config', 'notice');

      //$this->instawp_log->CloseFile();
      $connect_ids = get_option('instawp_connect_id_options', '');
      if ( ! empty($connect_ids) ) {
         if ( isset($connect_ids['data']['id']) && ! empty($connect_ids['data']['id']) ) {
            $id = $connect_ids['data']['id'];
         }
         $this->instawp_log->WriteLog('Already Connected : ' . json_encode($connect_ids), 'notice');
         $results['status']     = true;
         $results['message']    = 'Connected';
         $results['connect_id'] = $id;
         $response              = new WP_REST_Response($results);
         $response->set_status(200);
         return $response;

      }
      if ( ! isset($parameters['api_key']) || empty($parameters['api_key']) ) {
         $this->instawp_log->WriteLog('Api key is required', 'error');
         $results['message'] = 'api key is required';
         $response           = new WP_REST_Response($results);
         $response->set_status(200);
         return $response;
      }
      if(isset($parameters['api_domain'])) {
	 InstaWP_Setting::set_api_domain($parameters['api_domain']);
      }
      $res = $this->_config_check_key($parameters['api_key']);

      $this->instawp_log->CloseFile();
      if ( $res['error'] == false ) {
         $connect_ids = get_option('instawp_connect_id_options', '');

         if ( ! empty($connect_ids) ) {
            if ( isset($connect_ids['data']['id']) && ! empty($connect_ids['data']['id']) ) {
               $id = $connect_ids['data']['id'];
            }
            $results['status']     = true;
            $results['message']    = 'Connected';
            $results['connect_id'] = $id;

         }
      } else {
         $results['status']     = true;
         $results['message']    = $res['message'];
         $results['connect_id'] = 0;
      }

      $response = new WP_REST_Response($results);
      $response->set_status(200);
      return $response;
   }

   public function restore( $request ) {
      global $InstaWP_Curl, $instawp_plugin;
     //

      $response = array();
      $parameters = $request->get_params();

      update_option('instawp_restore_urls',$parameters);
      $this->instawp_log->CreateLogFile($this->download_log_file_name, 'no_folder', 'Download Backup');
      $this->instawp_log->WriteLog('Restore Parameters: '.json_encode($parameters), 'notice');

      $backup = new InstaWP_Backup_Task();
      $ret    = $backup->new_download_task();
      $task_id = $ret['task_id'];
      update_option('instawp_init_restore',$task_id);
      $this->instawp_log->WriteLog('New Task Created: '.$task_id, 'notice');
      //$instawp_plugin->end_shutdown_function = false;
      // register_shutdown_function(array($instawp_plugin, 'deal_shutdown_error'), $ret['task_id']);
      // $instawp_plugin->set_time_limit( $ret['task_id'] );
      // @ignore_user_abort(true);
      if ( $ret['result'] == 'success' ) {
         $this->instawp_log->WriteLog('Init Download', 'notice');
         $curl_result = $InstaWP_Curl->download($ret['task_id'], $parameters['urls']);
      }
      // if ( $curl_result['result'] != INSTAWP_SUCCESS ) {

      //    $curl_result['task_id'] = $task_id;
      //    $curl_result['completed'] = $task_id;
      //    $REST_Response = new WP_REST_Response($curl_result);
      //    $REST_Response->set_status(200);
      //    return $REST_Response;
      // }

      $res = $instawp_plugin->delete_last_restore_data_api();

      //$backuplist = InstaWP_Backuplist::get_backuplist();

      //$task = InstaWP_taskmanager::new_download_task_api();

      delete_option('instawp_backup_list');
      $InstaWP_BackupUploader = new InstaWP_BackupUploader();
      $res                    = $InstaWP_BackupUploader->_rescan_local_folder_set_backup_api();

      $backuplist = InstaWP_Backuplist::get_backuplist();
      if ( empty($backuplist ) ) {

        $this->instawp_log->WriteLog('Backup List is empty', 'error');
      }
      $restore_options = array(
		  'skip_backup_old_site'     => '1',
		  'skip_backup_old_database' => '1',
		  'is_migrate'               => '1',
		  'backup_db',
		  'backup_themes',
		  'backup_plugin',
		  'backup_uploads',
		  'backup_content',
		  'backup_core',
      );
      $restore_options_json = json_encode($restore_options);
      // global $instawp_plugin;
      // $this->restore_log = new InstaWP_Log();
      $index = 1;
      foreach ( $backuplist as $key => $backup ) {

         // $next_task = $instawp_plugin->restore_data->get_next_restore_task();
   // array($wait_running, $total) = $instawp_plugin->restore_data->get_next_restore_task_count();

   // $progress = intval($wait_running / $total);
         //calculating progress
         $progress = intval(($index / count($backuplist))*100);

         //updating restore status
         $this->restore_status('in_progress', $progress);

         do {

            $results_2 = $instawp_plugin->restore_api($key, $restore_options_json);
            //$instawp_plugin->restore_data->write_log('REST API RESULTS 2 '.print_r($results_2,true),'notice');

            $results = $instawp_plugin->get_restore_progress_api($key);
            //$instawp_plugin->restore_data->write_log('REST API RESULTS '.print_r($results,true),'notice');

            $ret     = (array) json_decode($results);
         } while ( $ret['status'] != 'completed' || $ret['status'] == 'error' );
         $index ++;
      }
      if ( $ret['status'] == 'completed' ) {
          $this->instawp_log->WriteLog('Restore Status: '.json_encode($ret), 'success');
         if ( isset($parameters['wp']) ) {
            $this->create_user($parameters['wp']['users']);
         }
         InstaWP_AJAX::instawp_folder_remover_handle();
         $response['status'] = true;
         $response['message'] = 'Restore task completed.';
      }
      else {
         $this->instawp_log->WriteLog('Restore Status: '.json_encode($ret), 'error');
         $response['status'] = false;
         $response['message'] = 'Something Went Wrong';
      }

      $res_result = $this->restore_status($response['message'], 100);
      //$this->_disable_maintenance_mode();
      $res      = $instawp_plugin->delete_last_restore_data_api();
      $REST_Response = new WP_REST_Response($res_result);
      $REST_Response->set_status(200);
      return $REST_Response;
   }

   public function restore_status($message, $progress = 100) {
      // error_log("Restore Status");

      $task_id =       get_option('instawp_init_restore', false);
      if(!$task_id)
         return;

      global $InstaWP_Curl;

      $connect_ids = get_option('instawp_connect_id_options', '');
         if ( ! empty($connect_ids) ) {
            if ( isset($connect_ids['data']['id']) && ! empty($connect_ids['data']['id']) ) {
               $id  = $connect_ids['data']['id'];
               $api_doamin = InstaWP_Setting::get_api_domain();
               $url = $api_doamin . INSTAWP_API_URL . '/connects/' . $id . '/restore_status';

               // restore preogress precetage
               // $restore_progress_option = get_option('instawp_restore_progress_percents', "0");
               // error_log('Restore Status percentage is : '. $restore_progress_option);

               $domain = str_replace("https://", "", get_site_url());
               $domain = str_replace("http://", "", $domain);

               $body = array(
                  "task_id"         => $task_id,
                  // "type"     => 'restore',
                  "progress"        => $progress,
                  "message"         => $message,
                  "connect_id"      => $id,
                  "completed"       => $progress == 100 ? true : false,
                  "destination_url" => $domain,
               );
               $body_json     = json_encode($body);


               // error_log('Update Restore Status call has made the url is : '. $url);

               $this->instawp_log->CreateLogFile('update_restore_status_call', 'no_folder', 'Update restore status call');

               $this->instawp_log->WriteLog('Restore Status percentage is : '. $restore_progress_option, 'notice');
               $this->instawp_log->WriteLog('Update Restore Status call has made the body is : '. $body_json, 'notice');
               $this->instawp_log->WriteLog('Update Restore Status call has made the url is : '. $url, 'notice');

               $curl_response = $InstaWP_Curl->curl($url, $body_json);

               // error_log("API Error: ==> ".$curl_response['error']);
               // error_log('After Update Restore Status call made the response : '. print_r($curl_response, true));

               if ( $curl_response['error'] == false ) {

                  $this->instawp_log->WriteLog('After Update Restore Status call made the response : '. $curl_response['curl_res'], 'notice');
                  $response              = (array) json_decode($curl_response['curl_res'], true);
                  $response['task_info'] = $body;
                  update_option('instawp_backup_status_options', $response);
               }

               $this->instawp_log->CloseFile();
            }
         }
         // error_log('instawp rest api \n '.print_r(get_option( 'instawp_backup_status_options'),true));
         update_option('instawp_finish_restore', $message);
         return $body;
   }
   public function upload_status( $request ) {
      $parameters = $request->get_params();

      $task_id  = $parameters['task_id'];
      $res      = get_option('instawp_upload_data_' . $task_id, '');
      $response = new WP_REST_Response($res);
      $response->set_status(200);
      return $response;
   }

   public function test( $request ) {

      $InstaWP_S3Compat = new InstaWP_S3Compat();
      $backup           = InstaWP_Backuplist::get_backup_by_id('62fb88296fe49');
      $backupdir        = InstaWP_Setting::get_backupdir();
      $files            = array( $backup['backup']['files'][0]['file_name'] );
      $res              = $InstaWP_S3Compat->upload_api('62fb88296fe49', $backup['backup']['files'][0]['file_name']);

      $filePath = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backupdir . DIRECTORY_SEPARATOR . $backup['backup']['files'][0]['file_name'];

      //print_r( get_option( 'instawp_upload_data_62fb88296fe49', '' ) );
      // if (isset($backup['backup']['files'][0]['file_name'])) {
      //    $filePath = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backupdir . DIRECTORY_SEPARATOR . $backup['backup']['files'][0]['file_name'];

      // }

      $response = new WP_REST_Response($res);
      $response->set_status(200);
      return $response;
   }

   public function backup( $request ) {
      $instawp_plugin = new instaWP();
      $args           = array(
		  "ismerge"      => "1",
		  "backup_files" => "files+db",
		  "local"        => "1",
      );

      $pre_backup_json = $instawp_plugin->prepare_backup_rest_api(json_encode($args));
      $pre_backup      = (array) json_decode($pre_backup_json, true);

      //print_r($data);
      if ( $pre_backup['result'] == 'success' ) {

         // Unique connection id / restore_id
         $restore_id = $request->get_param( 'restore_id' );
         $backup_now = $instawp_plugin->backup_now_api($pre_backup['task_id'], $restore_id);

         $data = array(
			 'task_id' => $pre_backup['task_id'],
			 'status'  => true,
			 'message' => 'Backup Initiated',
         );
         $response = new WP_REST_Response($data);
         $response->set_status(200);
      } else {

         $data = array(
			 'task_id' => '',
			 'status'  => false,
			 'message' => 'Failed To Initiated Backup',
         );
         $response = new WP_REST_Response($data);
         $response->set_status(403);
      }

      return $response;

   }
   public function task_status( $request ) {

      $data = array(
		  'task_id'  => '',
		  'progress' => '',
		  'status'   => true,
		  'message'  => '',
      );
      $InstaWP_Backup_Task = new InstaWP_Backup_Task();
      $tasks               = InstaWP_Setting::get_tasks();

      $parameters = $request->get_params();

      $task_id = $parameters['task_id'];
      $backup  = new InstaWP_Backup_Task($task_id);

      $list_tasks[ $task_id ] = $backup->get_backup_task_info($task_id);
      //$backuplist=InstaWP_Backuplist::get_backuplist();

      if ( $list_tasks[ $task_id ]['status'] == '' ) {

         $data['task_id'] = '';
         $data['status']  = 'faild';
         $data['message'] = 'No Task Found';
         $response        = new WP_REST_Response($data);
         $response->set_status(404);
         return $response;
      }

      $backup_percent = '0';
      if ( $list_tasks[ $task_id ]['status']['str'] == 'completed' ) {
         $backup_percent  = '100';
         $data['message'] = 'Finished Backup';
         $backup_percent  = str_replace('%', '', $list_tasks[ $task_id ]['task_info']['backup_percent']);
         $data['message'] = $list_tasks[ $task_id ]['task_info']['api_descript'];

      }
      $data['task_id']  = $task_id;
      $data['progress'] = $backup_percent;
      $data['status']   = $list_tasks[ $task_id ]['status']['str'];

      $response = new WP_REST_Response($data);
      $response->set_status(200);
      return $response;

   }

   public function _config_check_key( $api_key ) {
      global $InstaWP_Curl;
      $res = array(
		  'error'   => true,
		  'message' => '',
      );
      $api_doamin = InstaWP_Setting::get_api_domain();
      $url = $api_doamin . INSTAWP_API_URL . '/check-key';
      $log = array(
		  "url"     => $url,
		  "api_key" => $api_key,
      );
      $this->instawp_log->WriteLog('Init Check Key: '. json_encode($log), 'notice');


      $api_key = sanitize_text_field($api_key);
      //102|SouBdaa121zb1U2DDlsWK8tXaoV8L31WsXnqMyOy';
      $response = wp_remote_get($url, array(
		  'body'    => '',
		  'headers' => array(
			  'Authorization' => 'Bearer ' . $api_key,
			  'Accept'        => 'application/json',

		  ),
      ));
      $response_code = wp_remote_retrieve_response_code($response);

      $log = array(
		  "response_code" => $response_code,
		  "response"      => $response,
      );
       $this->instawp_log->WriteLog('Check Key Response : '. json_encode($response), 'notice');

      if ( ! is_wp_error($response) && $response_code == 200 ) {

         $body            = (array) json_decode(wp_remote_retrieve_body($response), true);
         $this->instawp_log->WriteLog('Check Key Response Body: '. json_encode($body), 'notice');

         $connect_options = array();
         if ( $body['status'] == true ) {
            $connect_options['api_key']  = $api_key;
            $connect_options['response'] = $body;
            update_option('instawp_api_options', $connect_options);
            $this->instawp_log->WriteLog('Save instawp_api_options: '. json_encode($connect_options), 'success');
            $res = $this->_config_connect($api_key);
         } else {
            $res = array(
				'error'   => true,
				'message' => 'Key Not Valid',

            );
            $this->instawp_log->WriteLog('Something Wrong: '. json_encode($body), 'error');
         }
      }

      return $res;
   }

   public function _config_connect( $api_key ) {
      global $InstaWP_Curl;
      $res = array(
		  'error'   => true,
		  'message' => '',
      );
      $api_doamin = InstaWP_Setting::get_api_domain();
      $url = $api_doamin . INSTAWP_API_URL . '/connects';

      $body          = json_encode(array( "url" => get_site_url() ));
      $log = array(
		  'url'  => $url,
		  'body' => $body,
	  );
      $this->instawp_log->WriteLog('_config_connect_init '. json_encode($log), 'notice');

      $curl_response = $InstaWP_Curl->curl($url, $body);

      $this->instawp_log->WriteLog('_config_connect_response '. json_encode($curl_response), 'notice');

      if ( $curl_response['error'] == false ) {
         $response = (array) json_decode( $curl_response['curl_res'],true);
         update_option('_config_connect_response',$response);
         $response = (array) json_decode($curl_response['curl_res'], true);
         if ( $response['status'] == true ) {

            update_option('instawp_connect_id_options', $response);
            $this->instawp_log->WriteLog('Save instawp_connect_id_options '. json_encode($response), 'success');
            $res['message'] = $response['message'];
            $res['error']   = false;
         } else {
            $res['message'] = 'Something Went Wrong. Please try again';
            $res['error']   = true;
            $this->instawp_log->WriteLog('Something Went Wrong. Please try again '. json_encode($curl_response), 'error');
         }
      }
      return $res;
   }

   public function create_user( $user_details ) {
      global $wpdb;

      // $username = $user_details['username'];
      // $password = $user_details['password'];
      // $email    = $user_details['email'];
      foreach ( $user_details as $user_detail ) {
          //print_r($user_details);
         if ( ! isset( $user_detail['username'] ) || ! isset( $user_detail['email'] ) || ! isset( $user_detail['password'] ) ) {
            continue;
         }
         if ( username_exists($user_detail['username']) == null && email_exists($user_detail['email']) == false && ! empty($user_detail['password']) ) {

            // Create the new user
            $user_id = wp_create_user($user_detail['username'], $user_detail['password'], $user_detail['email']);

            // Get current user object
            $user = get_user_by('id', $user_id);

            // Remove role
            $user->remove_role('subscriber');

            // Add role
            $user->add_role('administrator');
         }
         elseif ( email_exists( $user_detail['email'] ) || username_exists($user_detail['username']) ) {
            $user = get_user_by('email', $user_detail['email']);

            $wpdb->update(
                $wpdb->users,
                [
					'user_login' => $user_detail['username'],
					'user_pass'  => md5( $user_detail['password'] ),
					'user_email' => $user_detail['email'],
				],
                [ 'ID' => $user->ID ]
            );

            $user->remove_role('subscriber');

            // Add role
            $user->add_role('administrator');

         }
      }

   }
}
global $InstaWP_Backup_Api;
$InstaWP_Backup_Api = new InstaWP_Backup_Api();
