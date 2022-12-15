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

    public $tables;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        #tables array
        $this->tables = [
            'ch_table' => $this->wpdb->prefix . 'change_event',
            'sh_table' => $this->wpdb->prefix . 'sync_history'
        ];
    }
    
    /**
     * Insert 
    */
    public function insert($table_name,$data){   
        if(!empty($data) && is_array($data)){
            $this->wpdb->insert($table_name, $data); 
        } 
    }

    /**
     * Delete 
    */
    public function delete($table_name, $id){
        $this->wpdb->delete( $table_name, array( 'id' => $id ) );
    }

    /**
     * Update 
    */
    public function update(){

    }

    /**
     * Select 
    */
    public function get($table_name){
        $results = $this->wpdb->get_results("SELECT * FROM $table_name"); 
        return $results;
    }
    
    /**
     * Bulk delete 
    */
    public function bulk($table_name, $ids = null){
        if(!empty($ids) && is_array($ids)){
            foreach($ids as $id){
               $this->delete($table_name,$id); 
            }
        }
    }

    /**
     * Fatch row via id
    */
    public function getRowById($table_name = null, $id = null){
        $results = $this->wpdb->get_results("SELECT * FROM $table_name WHERE `id` = $id"); 
        return $results;
    }

    /*
    * Count total traking events
    */
    public function totalEvnets($table_name){
        $results = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name"); 
        return $results;
    }

    /*
    * Get traking events via event slug 
    */
    public function trakingEventsBySlug($table_name = null,$event_slug = null,$event_type = null){
        if(isset($event_slug)){ //with slug
            $results = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE `event_slug`='".$event_slug."' AND `event_type`='".$event_type."'"); 
        }else{ //only with event_type
            $results = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE `event_type`='".$event_type."'"); 
        }
        return $results;
    }
}
