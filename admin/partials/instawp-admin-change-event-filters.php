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
        #add_action( 'save_post', array( $this,'savePostFilter'), 10, 3 );
        add_action( 'wp_after_insert_post', array( $this,'savePostFilter'), 10, 4 );

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

        #taxonomy actions 
        $taxonomies = get_taxonomies();;
        foreach($taxonomies as $taxonomy){
            add_action( 'created_'.$taxonomy, array( $this,'createTaxonomyAction'), 10, 3 );
            add_action( 'delete_'.$taxonomy, array( $this,'deleteTaxonomyAction'), 10, 4 );
            add_action( 'edit_'.$taxonomy, array( $this,'editTaxonomyAction'), 10, 3 );
        }
    }
    /**
     * Function for `edit_(taxonomy)` action-hook.
     * 
     * @param int   $term_id Term ID.
     * @param int   $tt_id   Term taxonomy ID.
     * @param array $args    Arguments passed to wp_update_term().
     *
     * @return void
     */
    function editTaxonomyAction( $term_id, $tt_id, $args ){   
        $event_name = 'edit taxonomy';
        $event_slug = 'edit_taxonomy';
        $taxonomy = $args['taxonomy'];
        $title = $args['name'];
        $details = json_encode($args);
        $this->eventDataAdded($event_name,$event_slug,$taxonomy,$term_id,$title,$details);
    }

    /**
     * Function for `delete_(taxonomy)` action-hook.
     * 
     * @param int     $term         Term ID.
     * @param int     $tt_id        Term taxonomy ID.
     * @param WP_Term $deleted_term Copy of the already-deleted term.
     * @param array   $object_ids   List of term object IDs.
     *
     * @return void
     */
    function deleteTaxonomyAction( $term, $tt_id, $deleted_term, $object_ids ){
        $event_name = 'delete taxonomy';
        $event_slug = 'delete_taxonomy';
        $taxonomy = $deleted_term->taxonomy;
        $title = $deleted_term->name;
        $details = json_encode($deleted_term);
        $this->eventDataAdded($event_name,$event_slug,$taxonomy,$term,$title,$details);
    }

    /**
     * Function for `created_(taxonomy)` action-hook.
     * 
     * @param int   $term_id Term ID.
     * @param int   $tt_id   Term taxonomy ID.
     * @param array $args    Arguments passed to wp_insert_term().
     *
     * @return void
     */
    public function createTaxonomyAction($term_id, $tt_id, $args){
        $term = (array) get_term( $term_id , $args['category'] );
        $event_name = 'Create taxonomy';
        $event_slug = 'create_taxonomy';
        $taxonomy = $args['taxonomy'];
        $this->addTaxonomyData($event_name,$event_slug,$term_id,$tt_id,$taxonomy,$term);
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
            'status' => 'pending',
            'synced_message' => ''
        ];
        
        $InstaWP_db->insert($tables['ch_table'],$data);
    }

    /**
     * Function for `wp_after_insert_post` action-hook.
     * 
     * @param int          $post_id     Post ID.
     * @param WP_Post      $post        Post object.
     * @param bool         $update      Whether this is an existing post being updated.
     * @param null|WP_Post $post_before Null for new posts, the WP_Post object prior to the update for updated posts.
     *
     * @return void
     */
    public function savePostFilter( $post_ID, $post, $update, $post_before){
        if(!$update && $post->post_status == 'auto-draft'){# new post
            $event_name = 'New Post';
            $event_slug = 'post_new';
            $this->addPostData($event_name,$event_slug,$post,$post_ID);   
        }
        if($update && ($post->post_status != 'revision') ){ #update
            $event_name = 'Post Change';
            $event_slug = 'post_change';
            $InstaWP_db = new InstaWP_DB();
            $tables = $InstaWP_db->tables;
            $table_name = $tables['ch_table'];
            $existing_update_events = $InstaWP_db->existing_update_events($table_name,'post_change',$post_ID);
            # need to add update traking data once in db
            if($existing_update_events){
                $this->eventDataUpdated($event_name,$event_slug,$post,$post_ID,$existing_update_events);
            }else{
                $this->addPostData($event_name,$event_slug,$post,$post_ID);
            }
        }
    }

    public function eventDataUpdated($event_name = null, $event_slug = null, $post = null, $post_id = null, $id = null){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');
        $post_id = isset($post_id) ? $post_id : $post->ID;
        $postData = get_post($post_id);
        $post_content = isset($postData->post_content) ? $postData->post_content : '';
        $featured_image_id = get_post_thumbnail_id($post_id); 
        $featured_image_url = get_the_post_thumbnail_url($post_id);
        $taxonomies = $this->get_taxonomies_items($post_id);
        #Data Array
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => isset($postData->post_type) ? $postData->post_type : '',
            'source_id' => isset($post_id) ? $post_id : '',
            'title' => isset($postData->post_title) ? $postData->post_title : '',
            'details' => json_encode(['content' => $post_content,'posts' => $postData,'postmeta' => get_post_meta($post_id),'featured_image' => ['featured_image_id'=>$featured_image_id,'featured_image_url' => $featured_image_url],'taxonomies' => $taxonomies]),
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
            'status' => 'pending',
            'synced_message' => ''
        ];
        
        global $wpdb;
        $wpdb->update( 
            $tables['ch_table'], 
            $data, 
            array( 'id' => $id )
        );
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
            $this->addPostData($event_name,$event_slug,$post,$post_id);
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
        $this->addPostData($event_name,$event_slug,$post,null);
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
        $event_slug = 'untrashed_post';
        $post = null;
        $this->addPostData($event_name,$event_slug,$post,$post_id);
    }

    #post data add
    public function addPostData($event_name = null, $event_slug = null, $post = null, $post_id = null){
        $post_id = isset($post_id) ? $post_id : $post->ID;
        $postData = get_post($post_id);
        $post_content = isset($postData->post_content) ? $postData->post_content : '';
        $featured_image_id = get_post_thumbnail_id($post_id); 
        $featured_image_url = get_the_post_thumbnail_url($post_id);
        $event_type = isset($postData->post_type) ? $postData->post_type : '';
        $source_id = isset($post_id) ? $post_id : '';
        $title = isset($postData->post_title) ? $postData->post_title : '';
        $taxonomies = $this->get_taxonomies_items($post_id);
        $details = json_encode(['content' => $post_content,'posts' => $postData,'postmeta' => get_post_meta($post_id),'featured_image' => ['featured_image_id'=>$featured_image_id,'featured_image_url' => $featured_image_url],'taxonomies' => $taxonomies]);
        $this->eventDataAdded($event_name,$event_slug,$event_type,$source_id,$title,$details);
    }

    #Taxonomy
    public function addTaxonomyData($event_name = null, $event_slug = null, $term_id = null, $tt_id = null, $taxonomy = null, $args= null){
        $title = $args['name'];
        $details = json_encode($args);
        $this->eventDataAdded($event_name,$event_slug,$taxonomy,$term_id,$title,$details);
    }

    public function eventDataAdded($event_name = null, $event_slug = null, $event_type = null, $source_id = null, $title = null, $details = null){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $uid = get_current_user_id();
        $date = date('Y-m-d H:i:s');
        #Data Array
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => $event_type,
            'source_id' => $source_id,
            'title' => $title,
            'details' => $details,
            'user_id' => $uid,
            'date' => $date,
            'prod' => '',
            'status' => 'pending',
            'synced_message' => ''
        ];
        $InstaWP_db->insert($tables['ch_table'],$data);
    }

    #Get taxonomies items
    public function get_taxonomies_items($post_id = null){        
        $taxonomies = get_post_taxonomies($post_id);
        $items = [];
        if( !empty($taxonomies) && is_array($taxonomies) ){
            foreach($taxonomies as $taxonomy){
                $taxonomy_items = get_the_terms($post_id, $taxonomy);
                if( !empty($taxonomy_items) && is_array($taxonomy_items) ){
                    foreach($taxonomy_items as $item){
                        $items[$item->taxonomy][] = (array) $item;
                    }
                }
            }
        }
        return $items;
    }
}
new InstaWP_Change_Event_Filters();   