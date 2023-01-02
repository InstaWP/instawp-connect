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
        $tables = $InstaWP_db->tables;
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
                            #'details' => $v->details,
                            'user_id' => $v->user_id,
                            'date' => $v->date,
                            'status' => $v->status,
                            'synced_message' => $v->synced_message,
                            'sync' => '<button type="button" id="btn-sync-'.$v->id.'" data-id="'.$v->id.'" class="two-way-sync-btn">Sync</button> <span class="sync-loader"></span><span class="sync-success" style="display:none;">Done</span>',
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
            'sync' => 'Sync',
            'delete' => 'Delete'
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
          #'details' => 'Details',
          'user_id' => 'User ID',
          'date' => 'Date',
          'status' => 'Status',
          'synced_message' => 'Synced message',
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
            #'details'   => array('details', false),
            'user_id'   => array('user_id', true),
            'date'   => array('date', true),
            'status'   => array('status', true),
            'synced_message' => array('synced_message', true),
      );
      return $sortable_columns;
    }

    public function prepare_items() {
        $per_page = 20;
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
            #case 'details':
            case 'user_id':
            case 'date': 
            case 'status':
            case 'synced_message': 
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
        
        echo $this->bulkSyncPopup();
        echo '<div class="wrap change-event-main">
                <div class="message-change-events"></div>
                <div class="top-title">
                    <h2>Change event</h2>
                    <div class="bulk-sync"><button type="button" class="instawp-green-btn bulk-sync-popup-btn">Bulk Sync</button></div>
                </div>
                <form method="post" action="">'; 
                    $this->prepare_items(); 
                    $this->display(); 
            echo '</form>
            </div>'; 
    }

    #Bulk opration 
    public function bulkOprations($ids = null){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;

        #Bulk Delete
        $InstaWP_db->bulk($tables['ch_table'],$ids);

        #Bulk selected sync
        $this->bulkSync($tables['ch_table'],$ids); 
    }

    public function bulkSync($ids = null){
        if(!empty($ids) && is_array($ids)){
            foreach($ids as $id){
            }
        }
    } 
    
    public function bulkSyncPopup(){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        
        #Total events
        $total_events = $InstaWP_db->totalEvnets($tables['ch_table']);
        $post_new = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'post_new','post');
        $post_delete = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'post_delete','post');
        $post_trash = $InstaWP_db->trakingEventsBySlug($tables['ch_table'],'post_trash','post');
        
        #others
        $destination_url = get_option('instawp_sync_parent_url', '') ;
        $others = (abs($total_events) - abs($post_new+$post_delete+$post_trash));
        $html = '<div class="bulk-sync-popup"> 
                <div class="instawp-popup-main">
                    <div class="instawppopwrap">
                        <div class="topinstawppopwrap">
                            <h3>Preparing changes for Sync</h3>
                            <div class="destination_form">
                                <label for="instawp-destination">Destination</label>
                                <input type="url" id="destination-url" placeholder="mywebsite.com" value="'.$destination_url.'" name="Destination" disabled>
                            </div>
                            <div class="instawp_category">
                                <div class="instawpcatlftcol">
                                    <ul class="list">
                                        <li>'.$post_new.' post create events</li>
                                        <li>'.$post_delete.' post delete events</li>
                                        <li>'.$post_trash.' post trash events</li>
                                        <li>'.$others.' other events</li>
                                    </ul>
                                </div>
                                <div class="instawpcatrgtcol sync_process">
                                    <ul>
                                        <li class="step-1 process_pending">Packing things</li>
                                        <li class="step-2 process_pending">Pusing to cloud</li>
                                        <li class="step-3 process_pending">Merging to destination</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="sync_error_success_msg"></div>
                            <div class="sync_message_main textarea_json destination_form">
                                <label for="sync_message">Message:</label>
                                <textarea id="sync_message" name="sync_message" rows="4"></textarea>
                            </div>
                        </div>
                        <div class="instawp_buttons">                            
                            <a class="cancel-btn close" href="javascript:void(0);">Cancel</a>
                            <a class="changes-btn sync-changes-btn" href="javascript:void(0);">Sync Changes</a>
                        </div>
                    </div>
                </div>
             </div>';
        return $html;
    }
}
$obj_change_event = new InstaWP_Change_Event_Table();
echo $obj_change_event->displayChangeEventTable();