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
    public function update($table_name = null, $data = null , $id = null){
        $results = $this->wpdb->update($table_name,$data,array( 'id' => $id ));
        return $results;
    }

    public function _update($table_name = null, $data = null , $key = null, $val = null){
        $results = $this->wpdb->update($table_name,$data,array( $key => $val ));
        return $results;
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
    public function totalEvnets($table_name = null, $status = null){
        $results = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE `status`='".$status."'"); 
        return $results;
    }

    /*
    * To get unique or distinct values of a column in MySQL Table
    */
    public function get_with_distinct($table_name = null, $key = null){ 
        $results = $this->wpdb->get_results("SELECT DISTINCT($key) FROM $table_name");
        return $results; 
    }

    /*
    * Single key - value query
    */
    public function get_with_condition($table_name = null, $key = null, $val = null){
        $results = $this->wpdb->get_results("SELECT * FROM $table_name WHERE $key='".$val."'"); 
        return $results; 
    }

    public function getByInCondition($table_name = null, $key1 = null, $val1 = null, $key2 = null, $val2 = null){
        $str = $val1; 
        $rel = $this->wpdb->get_results("SELECT * FROM $table_name WHERE $key1 IN ($str) AND $key2='".$val2."'");
        return $rel;
    }

    public function getByTwoCondition($table_name = null, $key1 = null, $val1 = null, $key2 = null, $val2 = null){
        $rel = $this->wpdb->get_results("SELECT * FROM $table_name WHERE $key1='".$val1."' AND $key2='".$val2."'");
        return $rel;
    }

    public function get_double_condition($table_name = null, $val1 = null, $val2 = null){
        $rel = $this->wpdb->get_results("SELECT * FROM $table_name WHERE `event_type`='".$val1."' AND `status`='".$val2."'");
        return $rel;
    }
    
    public function get_with_count($table_name = null, $key = null, $val = null){
        $results = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $key='".$val."'"); 
        return $results; 
    }
    
    public function get_all_count($table_name = null){
        $results = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name"); 
        return $results; 
    }

    /*
    * Get traking events via event slug 
    */
    public function trakingEventsBySlug($table_name = null,$event_slug = null,$event_type = null, $status = null){      
        if(isset($event_slug)){ //with slug
            $results = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE `event_slug`='".$event_slug."' AND `event_type`='".$event_type."' AND `status`='".$status."'"); 
        }else{ //only with event_type
            $results = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE `event_type`='".$event_type."' AND `status`='".$status."'"); 
        }
        return $results > 0 ? $results : 0;
    }

    /*
    * Existing update event for source id
    */
    public function existing_update_events($table_name = null, $event_slug = null, $source_id = null){
        $results = $this->wpdb->get_var("SELECT id FROM $table_name WHERE `event_slug`='".$event_slug."' AND `source_id`='".$source_id."'"); 
        return $results;
    }

    public function get_event_type_counts($table_name = null, $event_type = null){
        $results = $this->wpdb->get_results("SELECT `event_type` ,COUNT(event_type) as type_count FROM $table_name GROUP BY `event_type` HAVING COUNT(event_type) >= 1 ORDER BY COUNT(event_type)"); 
        return $results; 
    }

    public function checkCustomizerChanges($table_name = null){
        $results = $this->wpdb->get_results("SELECT `id` FROM $table_name WHERE `event_slug`='customizer_changes'");  
        return $results; 
    }

    public function getDistinictCol($table_name,$column){
        $results = $this->wpdb->get_results("SELECT DISTINCT $column FROM $table_name");  
        return $results; 
    }
}
