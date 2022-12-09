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

class InstaWP_Change_Event_Filters {
    
    public function __construct() {
        #post actions
        add_filter( 'pre_trash_post', array( $this, 'trashPostFilter' ), 10, 2 );
        add_action( 'after_delete_post', array( $this,'deletePostFilter'), 10, 2 );
        add_action( 'untrashed_post', array( $this,'untrashPostFilter'),10, 3  );
        add_action( 'save_post', array( $this,'savePostFilter'), 10, 3 );
        
        #plugin actions
        add_action( 'activated_plugin', array( $this,'activatePluginAction'),10, 2 );
        add_action( 'deactivated_plugin', array( $this,'deactivatePluginAction'),10, 2 );
        #add_action( 'upgrader_process_complete', array( $this,'upgradePluginAction'),10, 2);
        
        #theme actions
        add_action( 'switch_theme', array( $this,'switchThemeAction'), 10, 3 );
        add_action( 'deleted_theme', array( $this,'deletedThemeAction'), 10, 2 );
        add_action( 'install_themes_new', array( $this,'installThemesNewAction') );
        add_action( 'install_themes_upload', array( $this,'installThemesUploadAction') );
        add_action( 'install_themes_updated', array( $this,'installThemesUpdatedAction') );
    }

    /**
     * Function for `install_themes_updated` action-hook.
     * 
     * @param int $paged Number of the current page of results being viewed.
     *
     * @return void
     */
    function installThemesUpdatedAction( $paged ){
        $event_name = 'install themes updated';
        $event_slug = 'install_themes_updated';
        $details = $paged;
        $this->pluginThemeEvents($event_name,$event_slug,$details,'theme');
    }
    /**
     * Function for `install_themes_upload` action-hook.
     * 
     * @param int $paged Number of the current page of results being viewed.
     *
     * @return void
     */
    function installThemesUploadAction( $paged ){
        $event_name = 'install themes upload';
        $event_slug = 'install_themes_upload';
        $details = $paged;
        $this->pluginThemeEvents($event_name,$event_slug,$details,'theme');
    }
    /**
     * Function for `install_themes_new` action-hook.
     * 
     * @param int $paged Number of the current page of results being viewed.
     *
     * @return void
     */
    function installThemesNewAction( $paged ){
        $event_name = 'install themes new';
        $event_slug = 'install_themes_new';
        $details = $paged;
        $this->pluginThemeEvents($event_name,$event_slug,$details,'theme');
    }

    /**
     * Function for `deleted_theme` action-hook.
     * 
     * @param string $stylesheet Stylesheet of the theme to delete.
     * @param bool   $deleted    Whether the theme deletion was successful.
     *
     * @return void
     */
    function deletedThemeAction( $stylesheet, $deleted ){
        $event_name = 'Deleted Theme';
        $event_slug = 'deleted_theme';
        $details = $stylesheet;
        $this->pluginThemeEvents($event_name,$event_slug,$details,'theme');
    }

    /**
     * Function for `switch_theme` action-hook.
     * 
     * @param string   $new_name  Name of the new theme.
     * @param WP_Theme $new_theme WP_Theme instance of the new theme.
     * @param WP_Theme $old_theme WP_Theme instance of the old theme.
     *
     * @return void
     */
    function switchThemeAction( $new_name, $new_theme, $old_theme ){
        $event_name = 'Switch Theme';
        $event_slug = 'switch_theme';
        $details = '';
        $this->pluginThemeEvents($event_name,$event_slug,$details,'theme');
    }

    /**
     * Function for `upgrader_process_complete` action-hook.
     * 
     * @param WP_Upgrader $upgrader   WP_Upgrader instance. In other contexts this might be a Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
     * @param array       $hook_extra Array of bulk item update data.
     *
     * @return void
     */
    function upgradePluginAction( $upgrader, $hook_extra ) {
        $event_name = $hook_extra['type'].'_'.$hook_extra['action'];
        $event_slug = $hook_extra['type'].'_'.$hook_extra['action'];
        $details = json_encode($hook_extra);
        $this->pluginThemeEvents($event_name,$event_slug,$details,'plugin');
    }

    /**
     * Function for `deactivated_plugin` action-hook.
     * 
     * @param string $plugin               Path to the plugin file relative to the plugins directory.
     * @param bool   $network_deactivating Whether the plugin is deactivated for all sites in the network or just the current site. Multisite only.
     *
     * @return void
     */
    public function deactivatePluginAction( $plugin, $network_wide ){
        $event_name = 'Deactivate Plugin';
        $event_slug = 'deactivate_plugin';
        $details = $plugin;
        $this->pluginThemeEvents($event_name,$event_slug,$details,'plugin');
    }

    /**
     * Function for `activated_plugin` action-hook.
     * 
     * @param string $plugin       Path to the plugin file relative to the plugins directory.
     * @param bool   $network_wide Whether to enable the plugin for all sites in the network or just the current site. Multisite only.
     *
     * @return void
     */
    public function activatePluginAction( $plugin, $network_wide ){
        $event_name = 'Activate Plugin';
        $event_slug = 'activate_plugin';
        $details = $plugin;
        $this->pluginThemeEvents($event_name,$event_slug,$details,'plugin');
    }

    public function pluginThemeEvents($event_name,$event_slug,$details,$type){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');

        #Data Array
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => $type,
            'source_id' => '',
            'title' => '',
            'details' => $details,
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
        ];
        
        $InstaWP_db->insert($tables['ch_table'],$data);
    }

    /**
     * Function for `save_post` action-hook.
     * 
     * @param int     $post_ID Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     *
     * @return void
     */
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

    /**
     * Function for `after_delete_post` action-hook.
     * 
     * @param int     $postid Post ID.
     * @param WP_Post $post   Post object.
     *
     * @return void
     */
    function deletePostFilter($post_id,$post){
        if(isset($post->post_type) && $post->post_type != 'revision'){
            $event_name = 'Post Delete';
            $event_slug = 'post_delete';
            $this->eventDataAdded($event_name,$event_slug,$post,$post_id);
        }
    }

    /**
     * Function for `pre_trash_post` filter-hook.
     * 
     * @param bool|null $trash Whether to go forward with trashing.
     * @param WP_Post   $post  Post object.
     *
     * @return bool|null
     */
    public function trashPostFilter($trash, $post){
        $event_name = 'Post Trash';
        $event_slug = 'post_trash';
        $this->eventDataAdded($event_name,$event_slug,$post,null);
    }

    /**
     * Function for `untrashed_post` action-hook.
     * 
     * @param int    $post_id         Post ID.
     * @param string $previous_status The status of the post at the point where it was trashed.
     *
     * @return void
     */ 
    public function untrashPostFilter($post_id, $previous_status){
        $event_name = 'Post Restore';
        $event_slug = 'post_restore';
        $post = null;
        $this->eventDataAdded($event_name,$event_slug,$post,$post_id);
    }

    public function eventDataAdded($event_name = null, $event_slug = null, $post = null, $post_id = null){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');
        $post_content = isset($post->post_content) ? $post->post_content : '';
        $featured_image_id = get_post_thumbnail_id($post->ID); 
        $featured_image_url = get_the_post_thumbnail_url($post->ID);
     
        #Data Array
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => isset($post->post_type) ? $post->post_type : '',
            'source_id' => isset($post->ID) ? $post->ID : $post_id,
            'title' => isset($post->post_title) ? $post->post_title : '',
            'details' => json_encode(['content' => $post_content,'posts' => $post,'postmeta' => get_post_meta($post->ID),'featured_image' => ['featured_image_id'=>$featured_image_id,'featured_image_url' => $featured_image_url]]),
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
        ];
        
        $InstaWP_db->insert($tables['ch_table'],$data);
    }
}
new InstaWP_Change_Event_Filters();   