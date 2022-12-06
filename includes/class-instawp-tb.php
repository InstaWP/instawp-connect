<?php

/**
 * (G.K)
 * Define database tables
 * 
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

/**
 * Define database tables
 *
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/includes
 * @author     instawp team
 */

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}

class InstaWP_TB{
    
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    public function tb(){
        return [
            'ch_table' => $this->wpdb->prefix . 'change_event'
        ];
    }
}