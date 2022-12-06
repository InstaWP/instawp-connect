<?php

/**
 * 
 * Define the database methods
 * 
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

/**
 * Define the database methods
 *
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/includes
 * @author     instawp team
 */

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}

class InstaWP_DB{ 

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    public function insert($table_name,$data){    
        if(!empty($data) && is_array($data)){
            $this->wpdb->insert($table_name, $data); 
        } 
    }

    public function delete($table_name, $id){
        $this->wpdb->delete( $table_name, array( 'id' => $id ) );
    }

    public function update(){

    }

    public function get($table_name){
        $results = $this->wpdb->get_results("SELECT * FROM $table_name"); 
        return $results;
    }
    
    public function bulk($table_name, $ids = null){
        if(!empty($ids) && is_array($ids)){
            foreach($ids as $id){
               $this->delete($table_name,$id); 
            }
        }
    }
}
