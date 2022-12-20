<?php

/**
 *
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

class InstaWP_Rest_Apis{
    public function __construct(){
        /*
        * Initiate Sync
        * Endpoint : /wp-json/instawp-connect/v1/sync
        * HOOK - rest_api_init
        */
        
        add_action( 'rest_api_init', function () {
            register_rest_route( 'instawp-connect/v1', '/sync', 
            array(
            'methods' => 'POST',
            'callback' => [$this, 'events_receiver'],
            'permission_callback' => [$this, 'check_permission'],
            ) );
        } );
    }

    function check_permission(){
        return true;
    }

    /**
     * Reciver 
     * @param array $data Options for the function.
     * @return string|null 
     */
    public function events_receiver($req) { 
        $body = $req->get_body();
        $bodyArr = json_decode($body);
        $encrypted_contents = json_decode($bodyArr->encrypted_contents);
        if(!empty($encrypted_contents) && is_array($encrypted_contents)){
            foreach($encrypted_contents as $v){
                $status = '';
                    #Post trash
                    if(isset($v->event_slug) && $v->event_slug == 'post_trash'){
                        if(isset($v->source_id)){
                        $rel = wp_trash_post($v->source_id);  //Post data on success, false or null on failure.
                        $status = $this->sync_post_status($rel);
                        $response[] = $this->sync_post_response($status,$v);
                        }
                    }

                    #Post permanently delete 
                    if(isset($v->event_slug) && $v->event_slug == 'post_delete'){
                        if(isset($v->source_id)){
                            $rel = wp_delete_post($v->source_id,true);  // Set to False if you want to send them to Trash.
                            $status = $this->sync_post_status($rel);
                            $response[] = $this->sync_post_response($status,$v);
                        }
                    }

                    #Post restored 
                    if(isset($v->event_slug) && $v->event_slug == 'untrashed_post'){
                        if(isset($v->source_id)){
                            $rel = wp_untrash_post($v->source_id,true);  //Post data on success, false or null on failure.
                            $status = $this->sync_post_status($rel);
                            $response[] = $this->sync_post_response($status,$v);
                        }
                    }

                    #Plugin actiavte 
                    if(isset($v->event_slug) && $v->event_slug == 'activate_plugin'){
                        if(isset($v->source_id) && isset($v->details)){
                            $this->plugin_activation($v->details);
                            $status = 'completed';
                        }
                    }

                    #Plugin deactiavte 
                    if(isset($v->event_slug) && $v->event_slug == 'deactivate_plugin'){
                        if(isset($v->source_id)){
                            $this->plugin_deactivation($v->details);
                            $status = 'completed';
                        }
                    }

                    #Sync history save
                    $this->sync_history_save($v,$status);
            }
        }
        
        return new WP_REST_Response( 
            array(
                'encrypted_contents' => $body,
                'status' => $status
            ) 
        );
    }

    #Insert history  
    public function sync_history_save($v = null, $status = null){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $dir = 'dev-to-live';
        $date = date('Y-m-d H:i:s');
        $data = [
            'encrypted_contents' => json_encode($v),
            'changes' => $v->event_name,
            'direction' => $dir,
            'status' => $status,
            'user_id' => $v->user_id,
            'date' => $date,
        ];
        $InstaWP_db->insert($tables['sh_table'],$data);
    }

   

    #Plugin activate.
    public function plugin_activation( $plugin ) {
        if( ! function_exists('activate_plugin') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if( ! is_plugin_active( $plugin ) ) {
             activate_plugin( $plugin );
        }
    }  

    #Plugin deactivate.
    public function plugin_deactivation( $plugin ) {
        if( ! function_exists('deactivate_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if( is_plugin_active( $plugin ) ) {
            deactivate_plugins( $plugin );
        }
    }

    public function sync_post_status($rel = null){
        $status = 'in_progress';
        if(isset($rel->ID)){

            $status = 'completed';
        }else{
            $status = 'pending';
        }
        return $status;
    }

    public function sync_post_response($status = null, $v = null){
       return [
                'id' => $v->id,
                'event_name' => $v->event_name,
                'event_slug' => $v->event_slug,
                'event_type' => $v->event_type,
                'status' => $status,
                'source_id' => $v->source_id,
                'user_id' => $v->user_id
            ];
    }

    // Sync Update  
    function sync_update(){
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => '/connects/:connect_id/syncs/:sync_id',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS =>'{
                "progress": 80,
                "status": "pending/in_progress/completed/error",
                "message": "Error ABC for post 41",
                "changes" : {
                    "posts": 40
                }
            }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            return $response;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}

new InstaWP_Rest_Apis();

