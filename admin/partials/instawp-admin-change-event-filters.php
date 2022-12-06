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
        add_filter( 'pre_trash_post', array( $this, 'trashPostFilter' ), 10, 2 );
    }

    #Post Trash
    public function trashPostFilter($trash, $post){
        $event_name = 'Post Trash';
        $event_slug = 'post_trash';
        $InstaWP_db = new InstaWP_DB();
        $InstaWP_tb = new InstaWP_TB();
        $tables = $InstaWP_tb->tb();
       
        $data = [
            'event_name' => $event_name,
            'event_slug' => $event_slug,
            'event_type' => $post->post_type,
            'source_id' => $post->ID
        ];
       
        $InstaWP_db->insert($tables['ch_table'],$data);
    }
}
new InstaWP_Change_Event_Filters();