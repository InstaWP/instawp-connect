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
        add_action("wp_ajax_pack_things", array($this, "get_data_from_db"));
        add_action("wp_ajax_sync_changes", array( $this,"sync_changes") );
        add_action("wp_ajax_single_sync", array( $this,"single_sync") );  
    }

    function formatSuccessReponse($message, $data = []){
        return json_encode([
            "success" => true,
            "message" => $message,
            "data" => $data
        ]);
    }

    function formatErrorReponse($message = "Something went wrong"){
        return json_encode([
            "success" => false,
            "message" => $message
        ]);
    }

    function get_wp_events($sync_ids = null){
        try {
            $InstaWP_db = new InstaWP_DB();
            $tables = $InstaWP_db->tables;
            if(isset($sync_ids) && !empty($sync_ids)){
                #we will use it in array and will send $sync_ids a object multiple ids  
                $rel = $InstaWP_db->getByTwoCondition($tables['ch_table'],'id',$sync_ids,'status','pending');
            }else{
                $rel = $InstaWP_db->get_with_condition($tables['ch_table'],'status','pending'); 
            }
            
            $encrypted_content = [];
            if(!empty($rel) && is_array($rel)){
                foreach($rel as $k => $v){
                        $encrypted_content[] = [
                            'id' => $v->id,
                            'details' => json_decode($v->details),
                            'event_name' => $v->event_name,
                            'event_slug' => $v->event_slug,
                            'event_type' => $v->event_type,
                            'source_id' => $v->source_id,
                            'user_id' => $v->user_id,
                        ];
                }
                return $this->formatSuccessReponse("The data has packed successfully as JSON from WP DB", $encrypted_content);
                #return json_encode($encrypted_content);
            }else{
                return $this->formatErrorReponse("Not pending event!");
            }
        } catch(Exception $e) {
            return $this->formatErrorReponse("Caught Exception: ",  $e->getMessage());
        }
    }

    function get_data_from_db(){
        try {
            $InstaWP_db = new InstaWP_DB();
            $tables = $InstaWP_db->tables;
            $data = [];
            if(isset($_POST['sync_type']) && $_POST['sync_type'] == 'single_sync'){
                $rel = $InstaWP_db->get_with_condition($tables['ch_table'],'id',$_POST['sync_ids']);
                if(!empty($rel) && is_array($rel)){
                    $count = 0;
                    $data['sync_type'] = $_POST['sync_type'];
                    foreach($rel as $v){
                        $count = $count + 1;
                        $data['total_events'] = $count;
                        $data[$v->event_type] = $data[$v->event_type] + 1;
                    } 
                    $total_events = $count;           
                }
            }else{
                //Pack Things: Form the array of events
                $total_posts = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],null,'post','pending');
                $total_pages = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],null,'page','pending');
                $total_plugins = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'plugin','pending');
                $total_themes = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'theme','pending');
                $total_events = $InstaWP_db->totalEvnets($tables['ch_table'],'pending');
                $data = [
                    'sync_type' => $_POST['sync_type'],
                    'total_events' => $total_events,
                    'post' => $total_posts,
                    'page' => $total_pages,
                    'plugin' => $total_plugins,
                    'theme' => $total_themes
                ];
            }

            if(!empty($total_events) && $total_events > 0){
                echo $this->formatSuccessReponse("The data has packed successfully as JSON from WP DB", json_encode($data));
            }else{
                echo $this->formatErrorReponse("The events are not available"); 
            }
            wp_die();
        } catch(Exception $e) {
            echo $this->formatErrorReponse("Caught Exception: ",  $e->getMessage());
        }
        wp_die();
    }

    function sync_changes(){
        $connect_id  = get_option('instawp_sync_connect_id');
        $parent_id = get_option('instawp_sync_parent_id');
        $message = isset($_POST['sync_message']) ? $_POST['sync_message']: '';
        $data = stripslashes($_POST['data']);
        $sync_ids = isset($_POST['sync_ids']) ? $_POST['sync_ids']: '';
        $events = $this->get_wp_events($sync_ids);
        $eventsArr = json_decode($events);
        if(isset($eventsArr->success) && $eventsArr->success === true){
            $packed_data = json_encode([
                'encrypted_content' => json_encode($eventsArr->data),
                'dest_connect_id' => $parent_id, #live
                'changes' => $data,
                'upload_wp_user' => get_current_user_id(),
                'sync_message' => $message,
                'source_connect_id' =>  $connect_id #staging 
            ]);
            
            $resp = $this->sync_upload($packed_data,null);
            $resp_decode = json_decode($resp); 
            if(isset($resp_decode->status) && $resp_decode->status === true){
                $sync_resp = '';
                if(isset($resp_decode->data->sync_id) && !empty($resp_decode->data->sync_id)){
                    $sync_resp = $this->get_Sync_Object($resp_decode->data->sync_id);
                    $respD = json_decode($sync_resp);// WE WILL USE IT.
                   
                    if($respD->status === 1 || $respD->status === true){
                        if(isset($respD->data->changes->changes->sync_response)){
                            $sync_response = $respD->data->changes->changes->sync_response;
                            $InstaWP_db = new InstaWP_DB();
                            $tables = $InstaWP_db->tables;
                            foreach($sync_response as $v){
                                $res_data = [
                                    'status' => $v->status,
                                    'synced_message' => $v->message
                                ];
                                $InstaWP_db->update($tables['ch_table'],$res_data,$v->id);   
                            }
                        }
                        $total = isset($respD->data->changes->total) ? json_decode($respD->data->changes->total) : '';
                        if($total->sync_type == 'single_sync'){
                            $repD = [
                                'sync_type' => $total->sync_type,
                                'res_data' => $res_data
                            ];
                        }else{
                            $repD = [
                                'sync_type' => $total->sync_type,
                                'res_data' => $res_data
                            ];
                        }
                       
                        echo $this->formatSuccessReponse($resp_decode->message,$repD);
                    }
                } 
            }else{
                echo $this->formatErrorReponse($resp_decode->message);  
            }
        }else{
            echo $this->formatErrorReponse($eventsArr->message);  
        }
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

    /*
    *  Endpoint - /api/v2/connects/$connect_id/syncs
    *  Example - https://s.instawp.io/api/v2/connects/1009/syncs
    *  Sync upload Api
    */
    function sync_upload($data = null, $endpoint = null){
        $api_doamin = InstaWP_Setting::get_api_domain();
        $connect_id = get_option('instawp_sync_connect_id');
        
        $endpoint = '/api/v2/connects/'.$connect_id.'/syncs';
        $url = $api_doamin.$endpoint; #https://stage.instawp.io/api/v2/connects/53/syncs
        $api_key = $this->get_api_key(); 
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer '.$api_key.'',
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

    #Get specific sync 
    public function get_Sync_Object($sync_id = null){
        global $InstaWP_Curl;
        $api_doamin = InstaWP_Setting::get_api_domain();
        $connect_id  = get_option('instawp_sync_connect_id');
        $endpoint = '/api/v2/connects/'.$connect_id.'/syncs/'.$sync_id;
        $url = $api_doamin.$endpoint; #https://stage.instawp.io/api/v2/connects/53/syncs/104
        $api_key = $this->get_api_key(); 
       
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer '.$api_key.''
            ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            return $response;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    function get_api_key(){
        $instawp_api_options = get_option('instawp_api_options'); 
        return $instawp_api_options['api_key'];
    }
}
new InstaWP_Ajax_Fn();