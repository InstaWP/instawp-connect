<?php

/**
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

class InstaWP_change_event_filters {
    public function __construct() {
        add_filter( 'pre_trash_post', array( $this, 'InstaWP_pre_trash_post_filter' ), 10, 2 );
    }

    #Post Trash
    public function InstaWP_pre_trash_post_filter($trash, $post){
        $event_name = 'Post Trash';
        $event_slug = 'post_trash';
        $this->InstaWP_event_change_insert($event_name,$event_slug,$post);
    }

    #Event changes insert into database
    public function InstaWP_event_change_insert($event_name,$event_slug,$post){
        global $wpdb;    
        $table_name = $wpdb->prefix . 'event_change';     
        if(isset($post->ID)){
            $wpdb->insert($table_name, array('event_name' => $event_name,'event_slug' => $event_slug, 'event_type' => $post->post_type,'source_id' => $post->ID)); 
        } 
    }
}

new InstaWP_change_event_filters();