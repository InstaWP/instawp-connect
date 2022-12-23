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
                    // if(isset($v->event_slug) && $v->event_slug == 'deactivate_plugin'){
                    //     if(isset($v->source_id)){
                    //         $this->plugin_deactivation($v->details);
                    //         $status = 'completed';
                    //     }
                    // }

                    if(isset($v->event_slug) && $v->event_slug == 'post_change'){
                        if(isset($v->source_id)){
                            $posts = (array) $v->details->posts;
                            $postmeta = (array) $v->details->postmeta;
                            
                            #Post array
                            if (get_post_status($posts['ID']) ) {
                                #The post exists
                                #Then update
                                $postData = $this->postData($posts,'update');
                                wp_update_post($postData);
                                
                                #post meta
                                $this->add_update_postmeta($postmeta,$posts['ID']);
                            } else {
                                $postData = $this->postData($posts,'insert');
                                #The post does not exist
                                #Then insert
                                wp_insert_post($postData); 
                                
                                #post meta
                                $this->add_update_postmeta($postmeta,$posts['ID']); 
                            }  
                        }
                    }
            }
        }
        
        #Sync history save
        $this->sync_history_save($body,$response,'Complete');

        #Sync update
        $syncUpdate = [
            'progress' => 100,
            'status' => 'completed',
            'message' => '',
            'changes' => $bodyArr->changes
        ];
        $sync_id = $bodyArr->sync_id;
        $this->sync_update($sync_id,$syncUpdate,'null');
        
        return new WP_REST_Response( 
            array(
                'encrypted_contents' => $body,
                'status' => 'Complete', #we will also check error then according to that we will change status
                'sync_response' => json_encode($response),
                'changes' => $bodyArr->changes
            ) 
        );
    }

    public function add_update_postmeta($meta_data = null, $post_id = null){
        $post_type = get_post_type($post_id);
        if(!empty($meta_data) && is_array($meta_data)){
            foreach($meta_data as $k => $v){
                if ( metadata_exists('post',$post_id,$k) ) {
                    update_post_meta($post_id,$k,$v[0]);   
                }else{
                    add_post_meta($post_id,$k,$v[0]);
                }
            }
        }
    }

    public function postData($posts = null, $op = null){
        $data = [];
        if($op == 'insert'){
            $data['import_id'] = $posts['ID'];
        }else{
            $data['ID'] = $posts['ID'];
        }
        $args = array(
            'post_author' => $posts['post_author'],
            'post_date' => $posts['post_date'],
            'post_date_gmt' => $posts['post_date_gmt'],
            'post_content' => $posts['post_content'],
            'post_title' => $posts['post_title'],
            'post_excerpt' => $posts['post_excerpt'],
            'post_status' => $posts['post_status'],
            'comment_status' => $posts['comment_status'],
            'ping_status' => $posts['ping_status'],
            'post_password' => $posts['post_password'],
            'post_name' => $posts['post_name'],
            'to_ping' => $posts['to_ping'],
            'pinged' => $posts['pinged'],
            'post_modified' => $posts['post_modified'],
            'post_modified_gmt' => $posts['post_modified_gmt'],
            'post_content_filtered' => $posts['post_content_filtered'],
            'post_parent' => $posts['post_parent'],
            'guid' => $posts['guid'],
            'menu_order' => $posts['menu_order'],
            'post_type' => $posts['post_type'],
            'post_mime_type' => $posts['post_mime_type'],
            'comment_count' => $posts['comment_count'],
            'filter' => $posts['filter'],
        );
        $Arr_merge = array_merge($data,$args);
        return $Arr_merge;
    }
    
    #Insert history  
    public function sync_history_save($body = null, $response = null, $status = null){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $dir = 'dev-to-live';
        $date = date('Y-m-d H:i:s');
        $bodyArr = json_decode($body);
        $message = isset($bodyArr->sync_message) ? $bodyArr->sync_message : '';
        $data = [
            'encrypted_contents' => $bodyArr->encrypted_contents,
            'changes' => $bodyArr->changes,
            'sync_response' => json_encode($response),
            'direction' => $dir,
            'status' => $status,
            'user_id' => $bodyArr->upload_wp_user,
            'changes_sync_id' => $bodyArr->sync_id,
            'sync_message' => $message,
            'source_connect_id' => '',
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
    public function plugindeactivation( $plugin ) {
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
                #'id' => $v->id,
                'event_name' => $v->event_name,
                'event_slug' => $v->event_slug,
                'event_type' => $v->event_type,
                'status' => $status,
                'source_id' => $v->source_id,
                'user_id' => $v->user_id
            ];
    }

    public function sync_update($sync_id = null, $data = null, $endpoint = null){
        global $InstaWP_Curl;
        $api_doamin = InstaWP_Setting::get_api_domain();
        $connect_ids  = get_option('instawp_connect_id_options', '');
        $connect_id = 53;
        $endpoint = '/api/v2/connects/'.$connect_id.'/syncs/'.$sync_id;
        $url = $api_doamin.$endpoint; #https://stage.instawp.io/api/v2/connects/53/syncs/78
        $api_key = $this->get_api_key(); 

        try{
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '.$api_key.'',
                'Content-Type: application/json'
            ),
            ));

            $response = curl_exec($curl);
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
new InstaWP_Rest_Apis();

