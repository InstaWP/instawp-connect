<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to Change Event.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instaWP
 * @subpackage instaWP/admin/instawp-change-event
 */

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class InstaWP_Change_Event_Table extends WP_List_Table {
    
    public function InstaWP_get_change_events(){

    }

    #data for table (NOTE: it will come dynamic soon)
    private $event_data = array(
        array('ID' => 1,'event_name' => 'New Post', 'event_slug' => 'post_new',
        'event_type' => 'post'),
        array('ID' => 2,'event_name' => 'Post Change', 'event_slug' => 'post_change',
        'event_type' => 'post'),
        array('ID' => 3,'event_name' => 'Post Trash', 'event_slug' => 'post_trash',
        'event_type' => 'post'),
        array('ID' => 4,'event_name' => 'Post Restore', 'event_slug' => 'post_restore',
        'event_type' => 'post'),
        array('ID' => 5,'event_name' => 'Post Delete', 'event_slug' => 'post_delete',
        'event_type' => 'post'),
    );

    #get columns
    public function get_columns() {
        $columns = array(
          'event_name' => 'Event name',
          'event_slug' => 'Event slug',
          'event_type' => 'Event Type'
        );
        return $columns;
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $this->event_data;
    }

    public function column_default( $item, $column_name ) {
        switch( $column_name ) { 
          case 'event_name':
          case 'event_slug':
          case 'event_type':
          return $item[ $column_name ];
          default:
          break;
        }
    }

    #show data table
    public function display_instawp_change_event_table(){
        echo '<div class="wrap"><h2>Event change</h2>'; 
        $this->prepare_items(); 
        $this->display(); 
        echo '</div>'; 
    }
}
$obj_change_event = new InstaWP_Change_Event_Table();
echo $obj_change_event->display_instawp_change_event_table();

