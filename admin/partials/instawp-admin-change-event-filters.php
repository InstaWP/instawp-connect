<?php

/**
 * 
 * This file is used for change event traking 
 *
 * @link       https://instawp.com/
 * @since      1.0
 * @package    instaWP
 * @subpackage instaWP/admin
 */

 /**
 * This file is used for change event traking 
 * 
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/admin
 * @author     instawp team
 */

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}

require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-db.php';
require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-tb.php';

class InstaWP_Change_Event_Filters {
    
    private $table_name;

    public function __construct() {
        #post events
        add_filter( 'pre_trash_post', array( $this, 'trashPostFilter' ), 10, 2 );
        add_action( 'after_delete_post', array( $this,'deletePostFilter'), 10, 2 );
        add_action( 'untrashed_post', array( $this,'untrashPostFilter'),10, 3  );
        add_action( 'save_post', array( $this,'savePostFilter'), 10, 3 );

        #plugin events
        add_action( 'activated_plugin', array( $this,'activatePluginAction'),10, 2 );
        add_action( 'deactivated_plugin', array( $this,'deactivatePluginAction'),10, 2 );
        add_action( 'upgrader_process_complete', array( $this,'upgradePluginAction'),10, 2);
    }

    #update plugin
    function upgradePluginAction( $upgrader, $hook_extra ) {
        $event_name = 'upgrader Plugin';
        $event_slug = 'upgrader_plugin';
        $details = json_encode($hook_extra);
        $this->pluginEvents($event_name,$event_slug,$details);
    }

    #deactivate plugin
    public function deactivatePluginAction( $plugin, $network_wide ){
        $event_name = 'Deactivate Plugin';
        $event_slug = 'deactivate_plugin';
        $details = $plugin;
        $this->pluginEvents($event_name,$event_slug,$details);
    }

    #activate plugin
    public function activatePluginAction( $plugin, $network_wide ){
        $event_name = 'Activate Plugin';
        $event_slug = 'activate_plugin';
        $details = $plugin;
        $this->pluginEvents($event_name,$event_slug,$details);
    }

    public function pluginEvents($event_name,$event_slug,$details){
        $InstaWP_db = new InstaWP_DB();
        $InstaWP_tb = new InstaWP_TB();
        $tables = $InstaWP_tb->tb();
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');

        #Data Array
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => 'plugin',
            'source_id' => '',
            'title' => '',
            'details' => $details,
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
        ];
        
        $InstaWP_db->insert($tables['ch_table'],$data);
    }

    # New Post & Post Change
    public function savePostFilter( $post_ID, $post, $update){
        if(!$update && $post->post_status == 'auto-draft'){# new post
            $event_name = 'New Post';
            $event_slug = 'post_new';
            $this->eventDataAdded($event_name,$event_slug,$post,$post_ID);   
        }
        if($update && ($post->post_status != 'revision') ){ #update
            $event_name = 'Post Change';
            $event_slug = 'post_change';
            $this->eventDataAdded($event_name,$event_slug,$post,$post_ID);
        }
    }

    #Post delete
    function deletePostFilter($post_id,$post){
        if(isset($post->post_type) && $post->post_type != 'revision'){
            $event_name = 'Post Delete';
            $event_slug = 'post_delete';
            $this->eventDataAdded($event_name,$event_slug,$post,$post_id);
        }
    }

    #Post Trash
    public function trashPostFilter($trash, $post){
        $event_name = 'Post Trash';
        $event_slug = 'post_trash';
        $this->eventDataAdded($event_name,$event_slug,$post,null);
    }

    #Post Restore
    public function untrashPostFilter($post_id, $previous_status){
        $event_name = 'Post Restore';
        $event_slug = 'post_restore';
        $post = null;
        $this->eventDataAdded($event_name,$event_slug,$post,$post_id);
    }

    #Post data insert into database
    public function eventDataAdded($event_name = null, $event_slug = null, $post = null, $post_id = null){
        $InstaWP_db = new InstaWP_DB();
        $InstaWP_tb = new InstaWP_TB();
        $tables = $InstaWP_tb->tb();
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');
        $post_content = isset($post->post_content) ? $post->post_content : '';

        #Data Array
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => isset($post->post_type) ? $post->post_type : '',
            'source_id' => isset($post->ID) ? $post->ID : $post_id,
            'title' => isset($post->post_title) ? $post->post_title : '',
            'details' => json_encode(['content' => $post_content]),
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
        ];
        
        $InstaWP_db->insert($tables['ch_table'],$data);
    }
}
new InstaWP_Change_Event_Filters();