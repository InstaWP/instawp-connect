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

class instaWP_Enqueue_Scripts{
    
    public function __construct(){
        add_action( 'admin_enqueue_scripts', array( $this,'enqueueScriptsAssets') );  
    }

    function enqueueScriptsAssets( $hook ) {
        
    }    
}
new instaWP_Enqueue_Scripts();
