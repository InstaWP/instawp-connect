<?php

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
   die;
}

class InstaWP_AJAX
{
   public $instawp_log;
   public function __construct() {
      $this->instawp_log          = new InstaWP_Log();
      add_action('wp_ajax_instawp_check_key', array( $this, 'check_key' ));
      add_action('wp_ajax_instawp_settings_call', array( $this, 'instawp_settings_call' ));
      add_action('wp_ajax_instawp_connect', array( $this, 'connect' ));
      add_action('wp_ajax_instawp_check_staging', array( $this, 'instawp_check_staging' ));
   }

   public function instawp_settings_call() {

      $message=''; $resType = false;      
      if( isset( $_REQUEST['api_heartbeat'] ) && !empty( $_REQUEST['api_heartbeat'] ) ){

         if( isset( $_REQUEST['instawp_api_url_internal'] ) ){
            $instawp_api_url_internal = $_REQUEST['instawp_api_url_internal'];
            InstaWP_Setting::set_api_domain($instawp_api_url_internal);         
         }

         $api_heartbeat = intval(trim( $_REQUEST['api_heartbeat'] ));       

         $resType = true;
         $message='Settings saved successfully';
         update_option( 'instawp_heartbeat_option', $api_heartbeat );

         error_log('heartbeat settings call');
         error_log( gettype(get_option("instawp_heartbeat_option")). " <===> ". gettype( $api_heartbeat ) );
         error_log( "db option : ".get_option("instawp_heartbeat_option") );
         error_log( "convert option : ".gettype((int)get_option("instawp_heartbeat_option")) );
        
         $heartbeat_option_val = (int)get_option("instawp_heartbeat_option");

         error_log( "Updated option : ".gettype( $heartbeat_option_val ) );
         error_log("=================================");

         if ( (int)$heartbeat_option_val !== intval( $api_heartbeat ) ) {
            date_default_timezone_set("Asia/Kolkata");
            error_log("New Schedule AT : " . date('d-m-Y, H:i:s, h:i:s'));
            $timestamp = wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' );
            wp_unschedule_event( $timestamp, 'instwp_handle_heartbeat_cron_action' );
         }else{
            date_default_timezone_set("Asia/Kolkata");
            error_log("Next Schedule AT : " . date('d-m-Y, H:i:s, h:i:s'));
            //instaWP::instawp_handle_cron_time_intervals( $api_heartbeat );
            
            // $schedules['instawp_heartbeat_interval'] = array(
            //    'interval' => $api_heartbeat * 60,
            //    'display' => 'Once '.$api_heartbeat.' minutes'
            // );
            // return $schedules;

            if ( ! wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' ) ) {         
               wp_schedule_event( $api_heartbeat * 60, 'instawp_heartbeat_interval', 'instwp_handle_heartbeat_cron_action');
            }
         }
         
      }else{
         $resType = false;
         $message='Something Wrong';         
      }
      $res_array = array();
      $res_array['message']  = $message;
      $res_array['resType']  = $resType;
      echo json_encode( $res_array );
      wp_die();
   }

   public function instawp_check_staging() {
      //$this->ajax_check_security();
      global $InstaWP_Curl;
      
      $this->instawp_log->CreateLogFile('restore_status', 'no_folder', 'Remote Config');
      $api_doamin = InstaWP_Setting::get_api_domain();
      
      $instawp_finish_upload = get_option('instawp_finish_upload', array());
      $url                   = $api_doamin . INSTAWP_API_URL . '/connects/get_restore_status';
      $body                  = array();
      $curl_response                  = array();
      $staging_sites                  = array();

      $connect_ids  = get_option('instawp_connect_id_options', '');
      $bkp_init_opt = get_option('instawp_backup_init_options', '');
      $backup_status_opt = get_option('instawp_backup_status_options', '');
      if ( empty($backup_status_opt) ) {
         //$this->instawp_log->CloseFile();
         echo json_encode($curl_response);
         error_log("empty backup status: " . print_r($curl_response, true));
         wp_die();
      }
      // if (!empty($connect_ids)) {
      //    $id                 = $connect_ids['data']['id'];
      //    $staging_list      = get_option('instawp_staging_list', array());
      //    if(!empty($staging_list) && isset($staging_list[$id]) ) {
      //       $staging_sites = $staging_list[$id];
      //       $this->instawp_log->CloseFile();
      //       echo json_encode($staging_sites);
      //       wp_die();
      //    }

      // }

      $task_id = $bkp_init_opt['task_info']['task_id'];
      $id      = 0;
      if ( ! empty($connect_ids) && ! empty($bkp_init_opt) && ! empty($backup_status_opt) ) {
         if ( isset($connect_ids['data']['id']) && ! empty($connect_ids['data']['id']) ) {
            $id                 = $connect_ids['data']['id'];
            $site_id            = $backup_status_opt['data']['site_id'];
            $body['connect_id'] = $id;
            $body['task_id']    = $task_id;
            $body['site_id']    = $site_id;
            $backup_info_json   = json_encode($body);
            $curl_response_data      = $InstaWP_Curl->curl($url, $backup_info_json);

            $this->instawp_log->WriteLog('url: '. $url . ' Body:'.$backup_info_json. 'Response: ' . json_encode($curl_response_data), 'notice');

            if ( isset($curl_response_data['curl_res']) ) {
               error_log("ON LINE 97");
               if (gettype($curl_response_data['curl_res']) == "string") {
                  $curl_response = json_decode($curl_response_data['curl_res'],true);
                  error_log("ON LINE 100");
               }else{
                  $curl_response = $curl_response_data['curl_res'];
                  error_log("ON LINE 103");
               }

               error_log("ON LINE 106 curl_response type starts: " . gettype($curl_response));
               error_log(print_r($curl_response, true));
               error_log("ON LINE 106 curl_response type ends: " . gettype($curl_response));
               if ( isset($curl_response['status']) && $curl_response['status'] == 1 ) {
                  error_log("ON LINE 107");

                  $staging_sites        = get_option('instawp_staging_list', array());
                  $staging_sites[ $id ] = $curl_response;
                  update_option('instawp_staging_list', $staging_sites);

                  //option to add task id and items
                  $staging_sites_items  = get_option('instawp_staging_list_items', array());

                  $api_doamin = InstaWP_Setting::get_api_domain();
                  $auto_login_url = $api_doamin . '/wordpress-auto-login';

                  $site_name = $curl_response['data']['wp'][0]['site_name']; 
                  $wp_admin_url = $curl_response['data']['wp'][0]['wp_admin_url']; 
                  $wp_username = $curl_response['data']['wp'][0]['wp_username']; 
                  $wp_password = $curl_response['data']['wp'][0]['wp_password'];  
                  $auto_login_hash = $curl_response['data']['wp'][0]['auto_login_hash']; 
                  $auto_login_url = add_query_arg( array( 'site' => $auto_login_hash ), $auto_login_url );

                  $staging_sites_items[ $id ][ $task_id ] = array(
                     "stage_site_task_id" => $task_id,
                     "stage_site_url" => array(
                        "site_name" => $site_name, 
                        "wp_admin_url" => $wp_admin_url
                     ),
                     "stage_site_user" => $wp_username,
                     "stage_site_pass" => $wp_password,
                     "stage_site_login_button" => $auto_login_url,
                  );
                  error_log("staging_sites_items Task ID After: " . $task_id);

                  update_option('instawp_staging_list_items', $staging_sites_items);
               }
            }         
         }
      }
      error_log("ON LINE 143");
      update_option('restore_status_options', $curl_response_data);
      $this->instawp_log->WriteLog('url: '. $url . ' Body:'.$backup_info_json. 'Response: ' . json_encode($curl_response_data), 'notice');

      // error_log("curl_response 142 LINE : " . print_r($curl_response, true));
      echo json_encode($curl_response);
      wp_die();
   }

   

   public function check_key() {

      $this->ajax_check_security();
      $res = array(
        'error'   => true,
        'message' => '',
     );
      $api_doamin = InstaWP_Setting::get_api_domain();
      $url = $api_doamin . INSTAWP_API_URL . '/check-key';
      error_log("Check key available ?". $_REQUEST['api_key'] );
      if ( isset($_REQUEST['api_key']) && empty($_REQUEST['api_key']) ) {
         $res['message'] = 'API Key is required';
         echo json_encode($res);
         wp_die();
      }
      $api_key = sanitize_text_field( wp_unslash( $_REQUEST['api_key'] ) );
      //$api_heartbeat = trim( $_REQUEST['api_heartbeat'] );
      //102|SouBdaa121zb1U2DDlsWK8tXaoV8L31WsXnqMyOy';
      $response = wp_remote_get($url, array(
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
            //$connect_options['api_heartbeat']  = $api_heartbeat;
            $connect_options['response'] = $body;
            
            //InstaWP_Setting::update_connect_option('instawp_connect_options',$connect_options,'api_key_opt');
            update_option('instawp_api_options', $connect_options);
            $res = array(
               'error'   => false,
               'message' => $body['message'],
            );
         } else {
            $res = array(
               'error'   => true,
               'message' => 'Key Not Valid',
            );
         }
      } else {
         $res = array(
            'error'   => true,
            'message' => 'Key Not Valid',
         );
      }
      echo json_encode($res);
      wp_die();
   }

   public function connect() {

      global $InstaWP_Curl;

      $this->ajax_check_security();
      $res = array(
        'error'   => true,
        'message' => '',
     );
      $api_doamin = InstaWP_Setting::get_api_domain();
      $url = $api_doamin . INSTAWP_API_URL . '/connects';

      // $connect_options = get_option('instawp_api_options', '');
      // if (!isset($connect_options['api_key']) && empty($connect_options['api_key'])) {
      //    $res['message'] = 'API Key is required';
      //    echo json_encode($res);
      //    wp_die();
      // }
      // $api_key = $connect_options['api_key'];
      $php_version  = substr( phpversion(), 0, 3);
      $body         = json_encode(array( "url" => get_site_url(), 'php_version' => $php_version));
      
      $curl_response = $InstaWP_Curl->curl($url, $body);
      update_option('instawp_connect_id_options_err', $curl_response);
      if ( $curl_response['error'] == false ) {

         $response = (array) json_decode($curl_response['curl_res'], true);

         if ( $response['status'] == true ) {
            $connect_options = InstaWP_Setting::get_option('instawp_connect_options',array() );
            $connect_id = $response['data']['id'];
            $connect_options[ $connect_id ] = $response;
            update_option('instawp_connect_id_options', $response); // old
            //InstaWP_Setting::update_connect_option('instawp_connect_options',$connect_options,$connect_id);
            $res['message'] = $response['message'];
            $res['error']   = false;
         } else {
            update_option('instawp_connect_id_options_err', $response);
            $res['message'] = 'Something Went Wrong. Please try again';
            $res['error']   = true;
         }
      }
      else {
         $res['message'] = 'Something Went Wrong. Please try again';
         $res['error']   = true;
      }

      echo json_encode($res);
      wp_die();
   }

   public function test_connect() {

      $this->ajax_check_security();
      $res = array(
        'error'   => true,
        'message' => '',
     );
      $url = 'https://s.instawp.io/api/v1/connects';

      $connect_options = get_option('instawp_api_options', '');
      if ( ! isset($connect_options['api_key']) && empty($connect_options['api_key']) ) {
         $res['message'] = 'API Key is required';
         echo json_encode($res);
         wp_die();
      }
      $api_key = $connect_options['api_key'];
      $header  = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json;charset=UTF-8',

     );
      $body = json_encode(array( 'url' => get_site_url() ));

      print_r($body);

      $response = wp_remote_post($url, array(
        'headers' => $header,
        'body'    => json_encode($body),

     ));
      $response_code = wp_remote_retrieve_response_code($response);
      print_r($response);
      if ( ! is_wp_error($response) && $response_code == 200 ) {
         $body = (array) json_decode(wp_remote_retrieve_body($response), true);

         print_r($body);
         // $connect_options = array();
         // if ($body['status'] == true) {
         //    $connect_options['api_key']   = $connect_options['api_key'];
         //    $connect_options['connected'] = true;
         //    $connect_options['response']  = $body;
         //    update_option('instawp_connect_id_options', $connect_options);
         //    $res = array(
         //       'error'   => false,
         //       'message' => 'Connected',

         //    );
         // }
         // else {
         //    $res = array(
         //       'error'   => true,
         //       'message' => 'Api Key Not Valid'

         //    );
         // }

      }
      echo json_encode($res);
      wp_die();
   }

   public function ajax_check_security( $role = 'administrator' ) {
      check_ajax_referer('instawp_ajax', 'nonce');
      $check = is_admin() && current_user_can($role);
      $check = apply_filters('instawp_ajax_check_security', $check);
      if ( ! $check ) {
         wp_die();
      }
   }
}

new InstaWP_AJAX();
