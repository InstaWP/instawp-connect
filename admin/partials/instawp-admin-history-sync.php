<?php
/** 
 * 
 * Provide a admin area view for the plugin
 *
 * This file is used to Sync history.
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

class InstaWP_Sync_History_Table extends WP_List_Table {

    public function dataSyncHistory(){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        $rel = $InstaWP_db->get($tables['sh_table']);
        $data = [];
        if(!empty($rel) && is_array($rel)){
            foreach($rel as $v){
                $dir = '';
                if(isset($v->direction)){
                    if($v->direction == 'dev-to-live'){
                        $dir = '<img src="'.INSTAWP_PLUGIN_IMAGES_URL.'/upward.png">';
                    }elseif($v->direction == 'live-to-dev'){
                        $dir = '<img src="'.INSTAWP_PLUGIN_IMAGES_URL.'/downward.png">';
                    }
                }

                $total_events = 0;
                if(isset($v->changes) && !empty($v->changes)){
                    $changes = json_decode($v->changes);
                    $ch_list = '<ul>';
                    foreach($changes as $k => $ch){
                        $ch_list .= '<li><strong>'.$k.' changes : </strong>'.$ch.'</li>';
                        $total_events = ($total_events + $ch);
                    }
                    $ch_list .= '</ul>';
                }
                
                $data[] = [
                    'ID' => $v->id,
                    'sync_message' => $v->sync_message,
                    'changes' => $ch_list,
                    'direction' => $dir,
                    'total_events' => $total_events,
                    'changes_sync_id' => $v->changes_sync_id,
                    'status' => $v->status,
                    'source_url' => $v->source_url,
                ];
            }
        }  
        return $data;
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="sync_history_ck[]" value="%s" />', $item['ID']
        );    
    }
    
    public function get_bulk_actions() {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    #get columns
    public function get_columns() {
        $columns = array(
          'cb'        => '<input type="checkbox" />',
          'sync_message' => 'Sync message',
          'changes' => 'Changes',
          'direction' => 'Direction',
          'total_events' => 'Total events',
          'changes_sync_id' => 'Sync id',
          'status' => 'Status',
          'source_url' => 'Source url',
        );
        return $columns;
    }

    public function get_sortable_columns(){
      $sortable_columns = array(
            'sync_message'  => array('sync_message', false),
            'changes' => array('event_slug', false),
            'direction'   => array('event_type', false),
            'total_events'   => array('total_events', false),
            'changes_sync_id'   => array('changes_sync_id', false),
            'status'   => array('source_id', true),
            'source_url'   => array('source_id', false),
      );
      return $sortable_columns;
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? $_GET['paged'] : 1;
        $items = $this->dataSyncHistory();
        $total_items = count($items);
        $found_data = array_slice($items,(($current_page-1)*$per_page),$per_page);
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $primary  = 'status';
        $this->_column_headers = array($columns, $hidden, $sortable,$primary);
        usort($found_data, array(&$this, 'usort_reorder'));
        $this->items = $this->dataSyncHistory();
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
            case 'sync_message':
            case 'changes':
            case 'direction':
            case 'total_events':
            case 'changes_sync_id':
            case 'status':
            case 'source_url':      
            return $item[ $column_name ];
            default:
            break;
        }
    }

    #Show data table
    public function displaySyncHistoryTable(){
        if(isset($_POST['sync_history_ck'])){
            $this->bulkOprations($_POST['sync_history_ck']);
        }

        echo '<div class="wrap sync-history-main">
                <div class="message-sync-history"></div>
                <div class="top-title">
                    <h2>Sync History</h2>
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
        $InstaWP_db->bulk($tables['sh_table'],$ids);      
    }
}
$obj_sync_history = new InstaWP_Sync_History_Table();
echo $obj_sync_history->displaySyncHistoryTable();
