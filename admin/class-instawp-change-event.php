<?php
/**
 * This is for go live integration.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instaWP
 * @subpackage instaWP/admin
 */

defined( 'INSTAWP_PLUGIN_DIR' ) || die;


class InstaWP_Change_event {

	protected static $_instance = null;

	public function __construct() {

		add_action( 'admin_menu', array( $this, 'add_change_event_menu' ) );
	}


	function render_change_event_page() {
		include_once( 'partials/instawp-admin-change-event.php' );
	}


	function add_change_event_menu() {
		add_management_page(
			esc_html__( 'Change Event', 'instawp-connect' ),
			esc_html__( 'Change Event', 'instawp-connect' ),
			'administrator', 'instawp-change-event',
			array( $this, 'render_change_event_page' ),
			2
		);
	}
	function listEvents(){
        $InstaWP_db = new InstaWP_DB();
        $tables = $InstaWP_db->tables;
        if(isset($_POST['filter_action']) && !empty($_POST['event_type'])){
            $rel = $InstaWP_db->get_with_condition($tables['ch_table'],'event_type',$_POST['event_type']);
        }elseif(isset($_GET['change_event_status']) && $_GET['change_event_status'] != 'all'){
            $rel = $InstaWP_db->get_with_condition($tables['ch_table'],'status',$_GET['change_event_status']);
        }
        // elseif( (isset($_POST['event_type']) && !empty($_POST['event_type'])) && (isset($_GET['change_event_status']) && $_GET['change_event_status'] != 'all')){
        //     $rel = $InstaWP_db->get($tables['ch_table'],$_POST['event_type'],$_GET['change_event_status']); 
        // }
        else{
            $rel = $InstaWP_db->get($tables['ch_table']);
        }
        $data = [];
        if(!empty($rel) && is_array($rel)){
            foreach($rel as $v){
                $btn = ($v->status != 'completed') ? '<button type="button" id="btn-sync-'.$v->id.'" data-id="'.$v->id.'" class="two-way-sync-btn">Sync changes</button> <span class="sync-loader"></span><span class="sync-success"></span>' : '<p class="sync_completed">Synced</p>'; 
                $data[] = [
                    'ID' => $v->id,
                    'event_name' => $v->event_name,
                    'event_slug' => $v->event_slug,
                    'event_type' => $v->event_type,
                    'source_id' => $v->source_id,
                    'title' => $v->title,
                    'status' => $v->status,
                    'user_id' => $v->user_id,
                    'synced_message' => $v->synced_message,
                    'date' => '<span class="synced_status">'.$v->status.'</span><br/><span>'.$v->date.'</span>',
                    'sync' => $btn,
                ];
            }
        }  
        return $data;
    }

	public function getStatusColor($status){
		switch ($status) {
			case 'failed':
				$colors = ['bg'=>'#FEE2E2','color'=>'#991B1B'];
				break;
			case 'pending':
				$colors = ['bg'=>'#DBEAFE','color'=>'#1E40AF'];
				break;
			case 'completed':
				$colors =  ['bg'=>'#D1FAE5','color'=>''];
				break;
			default:
				$colors = ['bg'=>'#FEF3C7','color'=>'#92400E'];
				break;
		}
		return $colors;
	}

	/**
	 * @return InstaWP_Change_event
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}

InstaWP_Change_event::instance();