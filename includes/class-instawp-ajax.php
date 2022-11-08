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
      // add_action('wp_ajax_instawp_heartbeat_check', array( $this, 'instawp_heartbeat_check' ));
      add_action('wp_ajax_instawp_connect', array( $this, 'connect' ));
      add_action('wp_ajax_instawp_check_staging', array( $this, 'instawp_check_staging' ));
     
   }

   /*public static function instawp_heartbeat_data_encrypt( $arg ){
      $connect_options = get_option('instawp_api_options', '');
      $api_key = $connect_options['api_key'];

      $cipher = "aes-128-gcm";
      $ivlen = openssl_cipher_iv_length($cipher);
      $iv = openssl_random_pseudo_bytes($ivlen);
      $tag = 'GCM';
      return openssl_encrypt( $arg, $cipher, $api_key, $options=0, $iv, $tag );       
   }*/

   // public function instawp_heartbeat_check(){
      
   //    if ( ! class_exists( 'WP_Debug_Data' ) ) {
   //       require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
   //    }
   //    $sizes_data = WP_Debug_Data::get_sizes();

   //    $wp_version = get_bloginfo('version');
   //    $php_version = phpversion();
   //    $total_size = $sizes_data['total_size']['size'];
   //    $active_theme = wp_get_theme()->get('Name');

   //    $count_posts = wp_count_posts();
   //    $posts = $count_posts->publish;

   //    $count_pages = wp_count_posts('page');
   //    $pages = $count_pages->publish;

   //    $count_users = count_users();
   //    $users = $count_users['total_users'];

   //    $connect_ids = get_option('instawp_connect_id_options', '');

   //    if ( ! empty($connect_ids) ) {
   //       if ( isset($connect_ids['data']['id']) && ! empty($connect_ids['data']['id']) ) {
   //          $id= $connect_ids['data']['id'];
   //       }
   //    }
      
   //    global $InstaWP_Curl;
   //    $body = array(
   //       "wp_version" => self::instawp_heartbeat_data_encrypt($wp_version),
   //       "php_version" => self::instawp_heartbeat_data_encrypt($php_version),
   //       "total_size" => self::instawp_heartbeat_data_encrypt($total_size),
   //       "theme" => self::instawp_heartbeat_data_encrypt($active_theme),
   //       "posts" => self::instawp_heartbeat_data_encrypt($posts),
   //       "pages" => self::instawp_heartbeat_data_encrypt($pages),
   //       "users" => self::instawp_heartbeat_data_encrypt($users),
   //      // "connect_id" => $id,//self::instawp_heartbeat_data_encrypt($id),
   //    );     
   //    error_log( print_r($body, true) );      
   //    $api_doamin = InstaWP_Setting::get_api_domain();
   //    $url = $api_doamin . INSTAWP_API_URL . '/connects/'.$id.'/heartbeat';
   //    $body_json     = json_encode($body);
   //    $curl_response = $InstaWP_Curl->curl($url, $body_json);            
     
   //    error_log( "Heartbeat API Curl URL ".$url);
   //    error_log( "Print Heartbeat API Curl Response Start" );      
   //    error_log( print_r($curl_response, true) );
   //    error_log( "Print Heartbeat API Curl Response End" );
   //    wp_send_json($curl_response);
   //    wp_die();
   // }

   public function instawp_settings_call() {
      
      $message=''; $resType = false;      
      if( isset( $_REQUEST['api_heartbeat'] ) && !empty( $_REQUEST['api_heartbeat'] ) ){

         if( isset( $_REQUEST['instawp_api_url_internal'] ) ){
            $instawp_api_url_internal = $_REQUEST['instawp_api_url_internal'];
            InstaWP_Setting::set_api_domain($instawp_api_url_internal);         
         }

         $api_heartbeat = trim( $_REQUEST['api_heartbeat'] );        
         $resType = true;
         $message='Settings saved successfully';
         update_option( 'instawp_heartbeat_option', $api_heartbeat );

         if ( intval( get_option("instawp_heartbeat_option") ) !== intval( $api_heartbeat ) ) {
            $timestamp = wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' );
            wp_unschedule_event( $timestamp, 'instwp_handle_heartbeat_cron_action' );
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

               $curl_response = (array) json_decode($curl_response_data['curl_res'], true);
               if ( $curl_response['status'] == 1 ) {
                     $staging_sites        = get_option('instawp_staging_list', array());
                     $staging_sites_items  = get_option('instawp_staging_list_items', array());
                     $staging_sites[ $id ] = $curl_response;
                     update_option('instawp_staging_list', $staging_sites);

                     //option to add task id and items
                     $staging_sites_items[ $id ] [ $task_id ] = $curl_response;
                     error_log("Stagin Site List". $staging_sites_items );
                     update_option('instawp_staging_list_items', $staging_sites_items);
               }
            }         
}
      }
      update_option('restore_status_options', $curl_response_data);
      $this->instawp_log->WriteLog('url: '. $url . ' Body:'.$backup_info_json. 'Response: ' . json_encode($curl_response_data), 'notice');
      //$this->instawp_log->CloseFile();
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
