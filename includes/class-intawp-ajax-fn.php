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

require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-db.php';

class InstaWP_Ajax_Fn{
    
    public function __construct(){
        #The wp_ajax_ hook only fires for logged-in users
        add_action( "wp_ajax_sync_changes", array( $this,"sync_changes") ); 
    }
 
    function sync_changes(){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $rel = $InstaWP_db->get($tables['ch_table']);
        echo json_encode($rel);
        wp_die();
    }
}
new InstaWP_Ajax_Fn();
