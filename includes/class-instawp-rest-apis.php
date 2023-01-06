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
        * In0000i0t0iate Sync
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
        $InstaWP_db = new InstaWP_DB();
        $body = $req->get_body();
        $dataIns = [
            'data' => $body
        ];
        $InstaWP_db->insert('wp_testing',$dataIns);
        
        $body = $req->get_body();
        $bodyArr = json_decode($body);
        $encrypted_contents = json_decode($bodyArr->encrypted_contents);
        $sync_id = $bodyArr->sync_id;
        $source_connect_id = $bodyArr->source_connect_id;
        if(!empty($encrypted_contents) && is_array($encrypted_contents)){
            $total_op = count($encrypted_contents);
            $count = 1;
            $progress_status = 'pending';
            $changes = $sync_response = [];
            foreach($encrypted_contents as $v){
                $source_id = (isset($v->source_id) && !empty($v->source_id)) ? intval($v->source_id) : null;
                
                /*
                *Post Oprations
                */
                //create and update
                if(isset($v->event_slug) && ($v->event_slug == 'post_change' ||$v->event_slug == 'post_new') ){
                    if(isset($source_id)){
                        $posts = (array) $v->details->posts;
                        $postmeta = (array) $v->details->postmeta;
                        $featured_image = (array) $v->details->featured_image;
                        
                        if (get_post_status($posts['ID']) ) {
                            #The post exists,Then update
                            $postData = $this->postData($posts,'update');
                            wp_update_post($postData);
                            #post meta
                            $this->add_update_postmeta($postmeta,$posts['ID']);
                        } else {
                            $postData = $this->postData($posts,'insert');
                            #The post does not exist,Then insert
                            wp_insert_post($postData); 
                            #post meta
                            $this->add_update_postmeta($postmeta,$posts['ID']); 
                        }

                        #feature image import 
                        $attachment_id = $featured_image['featured_image_id'];
                        $file = $featured_image['featured_image_url'];
                        wp_delete_attachment($attachment_id,true);
                        if(!empty($attachment_id) && !empty($file)){
                            $att_id = $this->insert_attachment($attachment_id,$file);
                            if(isset($att_id) && !empty($att_id)){
                                set_post_thumbnail($posts['ID'],$att_id);
                            }
                        }

                        #terms in post
                        $taxonomies = (array) $v->details->taxonomies;
                        if(!empty($taxonomies) && is_array($taxonomies)){
                            foreach($taxonomies as $taxonomy => $terms){
                                $terms = (array) $terms;
                                # if term not exist then create first
                                 if(!empty($terms) && is_array($terms)){
                                     foreach($terms as $term){
                                         $term = (array) $term;
                                         if(!term_exists($term['term_id'],$taxonomy)){
                                            $wp_terms = $this->wp_terms_data($term['term_id'],$term);
                                            $wp_term_taxonomy = $this->wp_term_taxonomy_data($term['term_id'],$term);
                                            $this->insert_taxonomy($term['term_id'],$wp_terms,$wp_term_taxonomy);
                                            wp_set_post_terms( $posts['ID'], [$term['term_id']], $taxonomy );
                                        }
                                     }
                                 }
                                
                                #set terms in post
                                $term_ids = array_column($terms, 'term_id');
                                wp_set_post_terms( $posts['ID'], $term_ids, $taxonomy );
                            }
                        }

                        #message 
                        $message = 'Sync successfully.';
                        $status = 'completed';
                        $sync_response[] = $this->sync_opration_response($status,$message,$v);
                        #changes
                        $changes[$v->event_type] = $changes[$v->event_type] + 1;
                    }
                }

                //Post trash
                if(isset($v->event_slug) && $v->event_slug == 'post_trash'){
                    if(isset($source_id)){
                        if(get_post_status($source_id)){
                            $rel = wp_trash_post($source_id);  //Post data on success, false or null on failure.
                            $status = $this->sync_post_status($rel);
                            $message = $this->sync_message($rel);
                        }else{
                            $status = 'pending';
                            $message = $this->notExistMsg();  
                        }
                        $sync_response[] = $this->sync_opration_response($status,$message,$v);
                        #changes
                        $changes[$v->event_type] = $changes[$v->event_type] + 1; 
                    }
                }

                //Post permanently delete 
                if(isset($v->event_slug) && $v->event_slug == 'post_delete'){
                    if(isset($source_id)){
                        if(get_post_status($source_id)){
                            $rel = wp_delete_post($source_id,true);  // Set to False if you want to send them to Trash.
                            $status = $this->sync_post_status($rel);
                            $message = $this->sync_message($rel);
                        }else{
                            $status = 'pending';
                            $message = $this->notExistMsg(); 
                        }
                        $sync_response[] = $this->sync_opration_response($status,$message,$v);
                        #changes
                        $changes[$v->event_type] = $changes[$v->event_type] + 1;
                    }
                }

                //Post restored 
                if(isset($v->event_slug) && $v->event_slug == 'untrashed_post'){
                    if(isset($source_id)){
                        if(get_post_status($source_id)){
                            $rel = wp_untrash_post($source_id,true);  //Post data on success, false or null on failure.
                            $status = $this->sync_post_status($rel);
                            $message = $this->sync_message($rel);
                        }else{
                            $status = 'pending';
                            $message = $this->notExistMsg(); 
                        }
                        $sync_response[] = $this->sync_opration_response($status,$message,$v);
                        #changes
                        $changes[$v->event_type] = $changes[$v->event_type] + 1;
                    }
                }
               
                /*
                *Plugin Oprations
                */

                //Plugin actiavte 
                if(isset($v->event_slug) && $v->event_slug == 'activate_plugin'){
                    if(isset($source_id) && isset($v->details)){
                        $this->plugin_activation($v->details);
                        $status = 'completed';
                        #changes
                        $changes[$v->event_type] = $changes[$v->event_type] + 1;
                        #message 
                        $message = $this->sync_message($rel);
                    }
                }

                //Plugin deactiavte 
                // if(isset($v->event_slug) && $v->event_slug == 'deactivate_plugin'){
                //     if(isset($source_id)){
                //         $this->plugin_deactivation($v->details);
                //         $status = 'completed';
                //     }
                // }
  
                /*
                * Taxonomy Oprations
                */
      
                //create and update
                if(isset($v->event_slug) && ($v->event_slug == 'create_taxonomy' || $v->event_slug == 'edit_taxonomy')){
                    if(isset($source_id)){
                        $details = (array) $v->details;
                        $wp_terms = $this->wp_terms_data($source_id,$details);
                        $wp_term_taxonomy = $this->wp_term_taxonomy_data($source_id,$details);
                        if(!term_exists($source_id,$v->event_type)){
                            if($v->event_slug == 'create_taxonomy'){
                                $this->insert_taxonomy($source_id,$wp_terms,$wp_term_taxonomy);
                            }   
                        }
                        if(term_exists($source_id,$v->event_type)){
                            if($v->event_slug == 'edit_taxonomy'){
                                $this->update_taxonomy($source_id,$wp_terms,$wp_term_taxonomy);
                            }
                        }

                        #message 
                        $message = 'Sync successfully.';
                        $status = 'completed';
                        $sync_response[] = $this->sync_opration_response($status,$message,$v);
                        #changes
                        $changes[$v->event_type] = $changes[$v->event_type] + 1;
                    }
                }

                //Delete 
                if(isset($v->event_slug) && $v->event_slug == 'delete_taxonomy'){
                    if(isset($source_id)){
                        if(term_exists($source_id,$v->event_type)){
                            $rel = wp_delete_term($source_id,$v->event_type);
                            $status = $this->sync_post_status($rel);
	                        $message = $this->sync_message($rel);
                        }
                    }else{
                        $status = 'pending';
                        $message = $this->notExistMsg();  
                    }
                    $sync_response[] = $this->sync_opration_response($status,$message,$v);
                    #changes
                    $changes[$v->event_type] = $changes[$v->event_type] + 1; 
                }
               
                /*
                * Update api for cloud
                */
                $progress = intval($count/$total_op * 100);
                $progress_status = ($progress > 100 ) ?  'in_progress': 'completed';
                #Sync update
                $syncUpdate = [
                    'progress' => $progress,
                    'status' => $progress_status,
                    'message' => $message,
                    'changes' => ['changes' => $changes,'sync_response' => $sync_response],
                ];
                $this->sync_update($sync_id,$syncUpdate,$source_connect_id);
                $count++; 
            }
        }
        
        #Sync history save
        $this->sync_history_save($body,$changes,'Complete');
     
        return new WP_REST_Response( 
            array(
                'encrypted_contents' => $encrypted_contents,
                'source_connect_id' => $source_connect_id,
                'changes' => ['changes' => $changes,'sync_response' => $sync_response],
                'sync_id' => $sync_id
            ) 
        );
    }

    public function notExistMsg(){
        return "ID is not exists.";
    }

    public function wp_terms_data($term_id = null, $arr = []){
        return [
            'term_id' => $term_id,
            'name' => $arr['name'],
            'slug' => $arr['slug']
        ];
    }
    public function wp_term_taxonomy_data($term_id = null, $arr = []){
        return [
            'term_taxonomy_id' => $term_id,
            'term_id' => $term_id,
            'taxonomy' => $arr['taxonomy'],
            'description' => $arr['description'],
            'parent' => $arr['parent']
        ];
    }

    public function insert_taxonomy($term_id = null, $wp_terms = null, $wp_term_taxonomy = null){
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'terms',$wp_terms);
        $wpdb->insert($wpdb->prefix.'term_taxonomy',$wp_term_taxonomy);
    }

    public function update_taxonomy($term_id = null, $wp_terms = null, $wp_term_taxonomy = null){
        global $wpdb;
        $wpdb->update($wpdb->prefix.'terms',$wp_terms,array( 'term_id' => $term_id ));
        $wpdb->update($wpdb->prefix.'term_taxonomy',$wp_term_taxonomy,array( 'term_id' => $term_id ));
    }

    public function add_update_postmeta($meta_data = null, $post_id = null){
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

    # import attechments form source to destination.
    public function insert_attachment($attachment_id = null, $file = null){
        $filename = basename($file);
        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );
        $parent_post_id = 0;
        $upload_file = wp_upload_bits($filename, null, file_get_contents($file,false, stream_context_create($arrContextOptions)));
        if (!$upload_file['error']) {
            $wp_filetype = wp_check_filetype($filename, null );
            $attachment = array(
                'import_id' => $attachment_id,
                'post_mime_type' => $wp_filetype['type'],
                'post_parent' => $parent_post_id,
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            $attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $parent_post_id );
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
                wp_update_attachment_metadata( $attachment_id,  $attachment_data );
            }
        }
        return $attachment_id;  
    }

    #Insert history  
    public function sync_history_save($body = null, $changes = null,$status = null){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $dir = 'dev-to-live';
        $date = date('Y-m-d H:i:s');
        $bodyArr = json_decode($body);
        $message = isset($bodyArr->sync_message) ? $bodyArr->sync_message : '';
        $data = [
            'encrypted_contents' => $bodyArr->encrypted_contents,
            'changes' => json_encode($changes),
            'sync_response' => '',
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

    public function sync_message($rel = null){
        if(isset($rel->ID)){
            $message = 'Sync successfully.';     
        }else{
            $message = 'Something went wrong.';    
        }
        return $message;
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

    public function sync_opration_response($status = null, $message = null, $v = null){
       return [ 
                'id' => $v->id,
                'status' => $status,
                'message' => $message
            ];
    }

    public function sync_update($sync_id = null, $data = null, $source_connect_id = null){
        global $InstaWP_Curl;
        $api_doamin = InstaWP_Setting::get_api_domain();
        $connect_id = intval($source_connect_id);
        $endpoint = '/api/v2/connects/'.$connect_id.'/syncs/'.$sync_id;
        $url = $api_doamin.$endpoint; #https://stage.instawp.io/api/v2/connects/241/syncs/450
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
            CURLOPT_POSTFIELDS => json_encode($data),
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