<?php

/**
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

/**
 *
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/includes
 * @author     instawp team
 */

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
   die;
}

require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-db.php';

class InstaWP_Ajax_Fn{
    public function __construct(){
        #The wp_ajax_ hook only fires for logged-in users
        add_action( "wp_ajax_sync_changes", array( $this,"sync_changes") );
        add_action( "wp_ajax_single_sync", array( $this,"single_sync") );  
    }

    function sync_changes(){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $rel = $InstaWP_db->get($tables['ch_table']);
        $TotalPosts = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],null,'post');
        $TotalPages = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],null,'page');
        $TotalPlugins = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'plugin');
        $TotalThemes = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'theme');
        $totalEvents = [];
        $user_id = get_current_user_id();
        if(!empty($TotalPosts)){
            $totalEvents['posts'] = $TotalPosts;
        }
        if(!empty($TotalPages)){
            $totalEvents['pages'] = $TotalPages;
        }

        if(!empty($TotalPlugins)){
            $totalEvents['plugins'] = $TotalPlugins;
        }

        if(!empty($TotalThemes)){
            $totalEvents['themes'] = $TotalThemes;
        }
	
    
    	$encrypted_content = array(
            array(
                'event_slug' => 'post_trash',
                'source_id' => 132
            ),
            array(
                'event_slug' => 'post_trash',
                'source_id' => 129
            )
        );
        $data = json_encode([
            'encrypted_content' => json_encode($encrypted_content),
            'dest_connect_id' => '935',
            'changes' => json_encode($totalEvents),
            'upload_wp_user' => $user_id
        ]);
      
        $resp = $this->sync_upload($data,null);
        echo json_encode($resp);
        wp_die();
    }

    function single_sync(){
        if(isset($_POST['sync_id'])){
            $sync_id = $_POST['sync_id'];
            $InstaWP_db = new InstaWP_DB();
            $tables = $InstaWP_db->tables;
            $rel = $InstaWP_db->getRowById($tables['ch_table'],$sync_id);
            echo json_encode($rel);
        }
        wp_die();
    }



    function sync_upload($data = null, $endpoint = null){
  
        global $InstaWP_Curl;
        $api_doamin = InstaWP_Setting::get_api_domain();
        $connect_ids  = get_option('instawp_connect_id_options', '');
        $connect_id = $connect_ids['data']['id'];
        $url = $api_doamin.'/connects/'.$connect_id.'/syncs';
        
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://s.instawp.io/api/v2/connects/1009/syncs',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer P3VgWApUGRIdhB9xyPI55ZCLLrte3rIvIEDywRjQ',
                    'Content-Type: application/json'
                ),
            ));
            $response = curl_exec($curl);
            $result = json_decode($response);
            return $response;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    function get_syncs($data = null, $end_point = null){
        global $InstaWP_Curl;
        $api_doamin = InstaWP_Setting::get_api_domain();
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => $api_doamin.'/connects/:connect_id/syncs',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            return $response;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}

new InstaWP_Ajax_Fn();