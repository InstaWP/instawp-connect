<?php
/** 
 * 
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

require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-db.php';

class InstaWP_Change_Event_Table extends WP_List_Table {

    public function dataChangeEvents(){
        $InstaWP_db = new InstaWP_DB();
        $InstaWP_tb = new InstaWP_TB();
        $tables = $InstaWP_tb->tb();
        $rel = $InstaWP_db->get($tables['ch_table']);
        $data = [];
        if(!empty($rel) && is_array($rel)){
            foreach($rel as $v){
                $data[] = [
                            'ID' => $v->id,
                            'event_name' => $v->event_name,
                            'event_slug' => $v->event_slug,
                            'event_type' => $v->event_type,
                            'source_id' => $v->source_id,
                            'title' => $v->title,
                            'details' => $v->details,
                            'user_id' => $v->user_id,
                            'date' => $v->date,
                            'prod' => $v->prod,
                            'sync' => '<button type="button" id="btn-sync-'.$v->id.'" data-id="'.$v->id.'" class="button button-primary two-way-sync-btn">Sync</button>',
                        ];
            }
        }  
        return $data;
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="change_event_ck[]" value="%s" />', $item['ID']
        );    
    }
    
    public function get_bulk_actions() {
        $actions = array(
          'delete'    => 'Delete'
        );
        return $actions;
    }

    #get columns
    public function get_columns() {
        $columns = array(
          'cb'        => '<input type="checkbox" />',
          'event_name' => 'Event name',
          'event_slug' => 'Event slug',
          'event_type' => 'Event Type',
          'source_id' => 'Source ID',
          'title' => 'Title',
          'details' => 'Details',
          'user_id' => 'User ID',
          'date' => 'Date',
          'prod' => 'Prod',
          'sync' => 'Sync',
        );
        return $columns;
    }

    public function get_sortable_columns(){
      $sortable_columns = array(
            'event_name'  => array('event_name', false),
            'event_slug' => array('event_slug', false),
            'event_type'   => array('event_type', true),
            'source_id'   => array('source_id', true),
            'title'   => array('title', true),
            'details'   => array('details', false),
            'user_id'   => array('user_id', true),
            'date'   => array('date', true),
            'prod'   => array('prod', true),
      );
      return $sortable_columns;
    }

    public function prepare_items() {
        $per_page = 5;
        $current_page = isset($_GET['paged']) ? $_GET['paged'] : 1;
        $items = $this->dataChangeEvents();
        $total_items = count($items);
        $found_data = array_slice($items,(($current_page-1)*$per_page),$per_page);
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $primary  = 'event_name';
        $this->_column_headers = array($columns, $hidden, $sortable,$primary);
        usort($found_data, array(&$this, 'usort_reorder'));
        $this->items = $this->dataChangeEvents();
        $this->set_pagination_args( array(
            'total_items' => $total_items, #WE have to calculate the total number of items
            'per_page'    => $per_page     #WE have to determine how many items to show on a page
          ) );
        $this->items = $found_data;
    }

    # Sorting function
    public function usort_reorder($a, $b){
        # If no sort, default to user_login
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'user_login';
        # If no order, default to asc
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
        # Determine sort order
        $result = strcmp($a[$orderby], $b[$orderby]);
        # Send final sort direction to usort
        return ($order === 'asc') ? $result : -$result;
    }

    public function column_default( $item, $column_name ) {
        switch( $column_name ) { 
            case 'event_name':
            case 'event_slug':
            case 'event_type':
            case 'source_id':
            case 'title':
            case 'details':
            case 'user_id':
            case 'date': 
            case 'prod': 
            case 'sync':      
            return $item[ $column_name ];
            default:
            break;
        }
    }

    #Show data table
    public function displayChangeEventTable(){
        if(isset($_POST['change_event_ck'])){
            $this->bulkOprations($_POST['change_event_ck']);
        }
        echo '<div class="wrap"><div class="message-change-events"></div><h2>Change event</h2><form method="post" action="">'; 
        $this->prepare_items(); 
        $this->display(); 
        echo '</div></form>'; 
    }

    #Bulk opration 
    public function bulkOprations($ids = null){
        $InstaWP_db = new InstaWP_DB();
        $InstaWP_tb = new InstaWP_TB();
        $tables = $InstaWP_tb->tb();
        #Bulk Delete
        $InstaWP_db->bulk($tables['ch_table'],$ids);
    }
}
$obj_change_event = new InstaWP_Change_Event_Table();
echo $obj_change_event->displayChangeEventTable();
