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

    public function formatSuccessReponse($message, $data = []){
        return json_encode([
            "success" => true,
            "message" => $message,
            "data" => $data
        ]);
    }

    public function formatErrorReponse($message = "Something went wrong"){
        return json_encode([
            "success" => false,
            "message" => $message
        ]);
    }

    public function get_wp_events($sync_ids = null, $sync_type = null){
        try {
            $InstaWP_db = new InstaWP_DB();
            $tables = $InstaWP_db->tables;
                   
            if(isset($sync_type) && $sync_type == 'single_sync'){
                $rel = $InstaWP_db->getByTwoCondition($tables['ch_table'],'id',$sync_ids,'status','pending');
            }elseif(isset($sync_type) && $sync_type == 'selected_sync'){
                $rel = $InstaWP_db->getByInCondition($tables['ch_table'],'id',$sync_ids,'status','pending');
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

    public function get_data_from_db(){
        try {
            $InstaWP_db = new InstaWP_DB();
            $tables = $InstaWP_db->tables;
            $data['sync_type'] = $_POST['sync_type'];
            if(isset($_POST['sync_type']) && $_POST['sync_type'] == 'single_sync'){
                $rel = $InstaWP_db->get_with_condition($tables['ch_table'],'id',$_POST['sync_ids']);
                if(!empty($rel) && is_array($rel)){
                    $count = 0;
                    foreach($rel as $v){
                        $count = $count + 1;
                        $data['total_events'] = $count;
                        $data[$v->event_type] = $data[$v->event_type] + 1;
                    } 
                    $total_events = $count;           
                }
            }elseif(isset($_POST['sync_type']) && $_POST['sync_type'] == 'selected_sync'){
                if(isset($_POST['sync_ids']) && !empty($_POST['sync_ids'])){
                    $sync_ids = explode(',',$_POST['sync_ids']);
                    $count = 0;
                    if(!empty($sync_ids) && is_array($sync_ids)){
                        foreach($sync_ids as $sync_id){
                            $rel = $InstaWP_db->get_with_condition($tables['ch_table'],'id',$sync_id);
                            foreach($rel as $v){
                                $count = $count + 1;
                                $data['total_events'] = $count;
                                $data[$v->event_type] = $data[$v->event_type] + 1; 
                            }
                            $total_events = $count; 
                        }
                    }
                }
            }else{
                $type_counts = $InstaWP_db->get_event_type_counts($tables['ch_table'],'event_type');
                if(!empty($type_counts) && is_array($type_counts)){
                    $total_events = 0;
                    foreach($type_counts as $typeC){
                        $data[$typeC->event_type] = $typeC->type_count;
                        $total_events += intval($typeC->type_count);
                    }
                    $data['total_events'] = $total_events;
                }
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

    public function sync_changes(){
        $connect_id  = get_option('instawp_sync_connect_id');
        $parent_id = get_option('instawp_sync_parent_id');
        $message = isset($_POST['sync_message']) ? $_POST['sync_message']: '';
        $data = stripslashes($_POST['data']);
        $sync_ids = isset($_POST['sync_ids']) ? $_POST['sync_ids']: '';
        $sync_type = isset($_POST['sync_type']) ? $_POST['sync_type']: '';  
        $events = $this->get_wp_events($sync_ids,$sync_type);
        $eventsArr = json_decode($events);
        if(isset($eventsArr->success) && $eventsArr->success === true){
            $packed_data = json_encode([
                'encrypted_content' => json_encode($eventsArr->data),
                'dest_connect_id' => $parent_id, #live
                'changes' => $data,
                'upload_wp_user' => get_current_user_id(),
                'sync_message' => $message,
                'source_connect_id' => $connect_id, #staging id
                'source_url' => get_site_url() #staging url
            ]);
          
            $resp = $this->sync_upload($packed_data,null);
           
            $resp_decode = json_decode($resp); 
            
            if(isset($resp_decode->status) && $resp_decode->status === true){
                $sync_resp = '';
                if(isset($resp_decode->data->sync_id) && !empty($resp_decode->data->sync_id)){
                    $sync_resp = $this->get_Sync_Object($resp_decode->data->sync_id);

                    
                    $respD = json_decode($sync_resp);// WE WILL USE IT.
                    // echo "<pre here>";
                    // print_r($respD);
                    // die;
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

    public function single_sync(){
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
    public function sync_upload($data = null, $endpoint = null){
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

    public function get_api_key(){
        $instawp_api_options = get_option('instawp_api_options'); 
        return $instawp_api_options['api_key'];
    }
}
new InstaWP_Ajax_Fn();