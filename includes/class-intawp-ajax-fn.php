<?php
/**
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-db.php';

class InstaWP_Ajax_Fn {

	private $wpdb;

	private $InstaWP_db;

	private $tables;

	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;

		$this->InstaWP_db = new InstaWP_DB();

		$this->tables = $this->InstaWP_db->tables;

		#The wp_ajax_ hook only fires for logged-in users
		add_action( "wp_ajax_pack_things", 				array( $this, "get_data_from_db" ) );
		add_action( "wp_ajax_sync_changes", 			array( $this, "sync_changes" ) );
		add_action( "wp_ajax_single_sync", 				array( $this, "single_sync" ) );
		add_action( "wp_ajax_syncing_enabled_disabled", array( $this, "syncing_enabled_disabled" ) );
		add_action( 'wp_ajax_get_site_events', 			array( $this, 'get_site_events' ) );
		add_action( 'wp_ajax_get_events_summary', 		array( $this, 'get_events_summary' ) );
		add_action( 'wp_ajax_instawp_handle_select2', 		array( $this, 'instawp_handle_select2' ) );
		add_action( 'wp_ajax_instawp_delete_events', 	array( $this, 'instawp_delete_events' ) );
		add_action( 'wp_ajax_instawp_calculate_events', array( $this, 'instawp_calculate_events' ) );
		add_action( 'wp_ajax_get_site_events_ajax', 	array( $this, 'get_site_events_ajax' ) );
		add_action( 'wp_ajax_update_sync_status', 		array( $this, 'instawp_update_sync_events_status' ) );
	}

	public function syncing_enabled_disabled() {
		$sync_status = $_POST['sync_status'];
		if ( ! get_option( 'syncing_enabled_disabled' ) ) {
			add_option( 'syncing_enabled_disabled', $sync_status );
		}
		update_option( 'syncing_enabled_disabled', $sync_status );
		$message = ( $sync_status == 1 ) ? 'Syncing enabled!' : 'Syncing disabled!';
		echo json_encode( [ 'sync_status' => $sync_status, 'message' => $message ] );
		wp_die();
	}

	public function formatSuccessReponse( $message, $data = [] ) {
		return json_encode( [
			"success" => true,
			"message" => $message,
			"data"    => $data
		] );
	}

	public function formatErrorReponse( $message = "Something went wrong" ) {
		return json_encode( [
			"success" => false,
			"message" => $message
		] );
	}

	public function instawp_calculate_events() {
		global $wpdb;
		$where = "1=1";
		if( isset($_POST['connect_id']) && intval( $_POST['connect_id'] ) > 0 ){
			$where .= " AND connect_id=".sanitize_text_field( $_POST['connect_id'] );
		}
		$query 			= "SELECT COUNT(1) FROM ".INSTAWP_DB_TABLE_EVENTS." WHERE `id` NOT IN (SELECT event_id AS id FROM ".INSTAWP_DB_TABLE_EVENT_SITES." WHERE $where)";
		$total_events   = $wpdb->get_var( $query );
		if( $total_events > 0 ){

			$sync_quota_limit = $this->instawp_get_connect_quota_remaining_limit();

			if( $sync_quota_limit >= $total_events ) {
				echo $this->formatSuccessReponse( "Event fetched.", [
					'count'    	=> $total_events, 
					'page'		=> 1,
					'per_page'	=>	INSTAWP_EVENTS_SYNC_PER_PAGE,
					'progress_text'	=> '0%'. sprintf(__(' Completed ( 0 out of %d events )', 'instawp-connect'), $total_events)
				] );
			}else{
				echo $this->formatErrorReponse( __('You have reached maximum sync quota limit.', 'instawp-connect') );
			}
		}else{
			echo $this->formatErrorReponse( __('No pending events found!', 'instawp-connect') );
		}
		wp_die();
	}

	public function instawp_pack_pending_sync_events() {
		global $wpdb;
		$where = "1=1";
		if( isset($_POST['dest_connect_id']) && intval( $_POST['dest_connect_id'] ) > 0 ){
			$where .= " AND connect_id=".sanitize_text_field( $_POST['dest_connect_id'] );
		}
		$items_per_page = INSTAWP_EVENTS_SYNC_PER_PAGE;
		$query          = "SELECT * FROM ".INSTAWP_DB_TABLE_EVENTS." WHERE `id` NOT IN (SELECT event_id AS id FROM ".INSTAWP_DB_TABLE_EVENT_SITES." WHERE $where)";
		$total_query    = "SELECT COUNT(1) FROM ({$query}) AS combined_table";
		$total          = $wpdb->get_var( $total_query );
		$page           = isset( $_POST['page'] ) ? abs( (int) $_POST['page'] ) : 1;
		$offset         = ( $page * $items_per_page ) - $items_per_page;
		$events         = $wpdb->get_results( $query . " LIMIT ".  $offset .", ". $items_per_page ."" );
		$total_page     = ceil( $total / $items_per_page );
		return [
			'count'    		=> 	$total, 
			'page'			=> 	$page,
			'total_page'	=>	$total_page,
			'events'		=>	$events,
			'per_page'		=>	INSTAWP_EVENTS_SYNC_PER_PAGE,
		];
	}

	public function get_site_events() {
		global $wpdb;
		$InstaWP_db     = new InstaWP_DB();
		$tables         = $InstaWP_db->tables;
		$items_per_page = INSTAWP_EVENTS_PER_PAGE;
		$connect_id     = isset( $_POST['connect_id'] ) ? sanitize_text_field( $_POST['connect_id'] ) : 0;
		$query          = "SELECT * FROM ".INSTAWP_DB_TABLE_EVENTS;
		$total_query    = "SELECT COUNT(1) FROM ({$query}) AS combined_table";
		$total          = $wpdb->get_var( $total_query );
		$page           = isset( $_POST['epage'] ) ? abs( (int) $_POST['epage'] ) : 1;
		$offset         = ( $page * $items_per_page ) - $items_per_page;
		$events         = $wpdb->get_results( $query . " GROUP BY `source_id`,`date` ORDER BY id DESC LIMIT {$offset}, {$items_per_page}" );
		$totalPage      = ceil( $total / $items_per_page );

		ob_start();
		include INSTAWP_PLUGIN_DIR . '/migrate/templates/ajax/part-sync-items.php';
		$data = ob_get_contents();
		ob_end_clean();

		echo $this->formatSuccessReponse( "Event fetched.", [
			'results'    => $data,
			'pagination' => $this->get_events_sync_list_pagination( $total, $items_per_page, $page )
		] );
		wp_die();
	}

	public function instawp_handle_select2(){
		if( isset($_GET['event'])){
			if( $_GET['event'] == 'instawp_get_users' ){
				$keyword = $_GET['term'];
				$args = array(
					'search'              => $keyword,
					'paged'               => 1,
					'search_columns'      => array('user_login','user_nicename','user_email'),
					'fields'			  => array('id','user_login')
				);
				$users = get_users( $args );
				echo $this->formatSuccessReponse( "Users loaded", ['results'=> $users, 'opt_col'=> ['text'=>'user_login','id'=>'ID']] );
			}else if( $_GET['event'] == 'instawp_sync_tab_roles' ){ 
				
				$results = [];
				$all_roles = wp_roles()->roles;
				foreach ($all_roles as $slug => $role) {
					$results[] = ['id'=> $slug, 'name'=> $role['name'] ];
				}
				echo $this->formatSuccessReponse( "Users loaded", ['results'=> $results, 'opt_col'=> ['text'=>'name','id'=>'id']] );
			}
		}
		wp_die();
	}
	

	public function instawp_delete_events(){
		if( isset( $_POST['ids'] ) && !empty( $_POST['ids'] )){
			global $wpdb;
			$ids = sanitize_text_field ($_POST['ids'] );		
			$wpdb->query( "DELETE FROM ".INSTAWP_DB_TABLE_EVENTS." WHERE id IN($ids)" );
			if( isset( $_POST['connect_id'] ) && intval( $_POST['connect_id'] ) > 0 ){
				$wpdb->query( "DELETE FROM ".INSTAWP_DB_TABLE_EVENT_SITES." WHERE event_id IN($ids)" );
			}
			echo $this->formatSuccessReponse( "Data deleted", [] );
			wp_die();
		}
	}

	public function get_events_summary() {
	
		$where ="1=1";
		if( isset($_POST['connect_id']) && intval( $_POST['connect_id'] ) > 0 ){
			$where .= " AND connect_id=".sanitize_text_field( $_POST['connect_id'] );
		}

		$query		= "SELECT event_type, COUNT(*) as event_count FROM ".INSTAWP_DB_TABLE_EVENTS." WHERE `id` NOT IN (SELECT event_id AS id FROM ".INSTAWP_DB_TABLE_EVENT_SITES." WHERE $where) GROUP BY event_type HAVING event_count > 0";
		$results    = $this->wpdb->get_results( $query );
		
		$html = '';
		if( !empty( $results ) ){
			foreach ($results as $row) {
				$html .= '<li class="event-type-count">';
				$html .= sprintf( __( '%u %s change events', 'instawp-connect' ), $row->event_count, ucfirst( $row->event_type ) );
				$html .= '</li>';
			}
		}else{
			$results = ['Post','Page','Theme', 'Plugin'];
			foreach ($results as $row) {
				$html .= '<li class="event-type-count">';
				$html .= sprintf( __( '%u %s change event', 'instawp-connect' ), 0, $row );
				$html .= '</li>';
			}
		}


		echo $this->formatSuccessReponse( "Summery fetched", $html );
		wp_die();
	}

	public function get_events_sync_list_pagination( $total, $items_per_page, $page ) {
		return paginate_links( array(
			'base'      => '%_%',
			'format'    => '?page=instawp&epage=%#%',
			'prev_text' => __( '« Previous' ),
			'next_text' => __( 'Next »' ),
			'show_all'  => false,
			'total'     => ceil( $total / $items_per_page ),
			'current'   => $page,
			'type'      => 'plain',
			'prev_next' => true,
			'class'     => 'instawp_sync_event_pagination',
		) );
	}

	public function get_wp_events() {
		try {

			$encrypted_content = [];
			$events_results = $this->instawp_pack_pending_sync_events();

			if ( ! empty( $events_results['events'] ) && is_array( $events_results['events'] ) ) {
				foreach ( $events_results['events'] as $k => $v ) {
					$encrypted_content[] = [
						'id'         => $v->id,
						'details'    => json_decode( $v->details ),
						'event_name' => $v->event_name,
						'event_slug' => $v->event_slug,
						'event_type' => $v->event_type,
						'source_id'  => $v->source_id,
						'user_id'    => $v->user_id,
					];
				}

				if ( count( $encrypted_content ) > 0 ) {
					return $this->formatSuccessReponse( "The data has packed successfully as JSON from WP DB", [
						'contents'	=> $encrypted_content,
						'results'	=> $events_results 
					] );
				} else {
					return $this->formatErrorReponse( "No pending events found!" );
				}
			}
		} catch ( Exception $e ) {
			return $this->formatErrorReponse( "Caught Exception: ", $e->getMessage() );
		}
	}

	public function get_data_from_db() {
		try {
			$data['sync_type'] = $_POST['sync_type'];
			if ( isset( $_POST['sync_type'] ) && $_POST['sync_type'] == 'single_sync' ) {
				$rel = $this->InstaWP_db->get_with_condition( $this->tables['ch_table'], 'id', $_POST['sync_ids'] );
				if ( ! empty( $rel ) && is_array( $rel ) ) {
					$count = 0;
					foreach ( $rel as $v ) {
						$count                  = $count + 1;
						$data['total_events']   = $count;
						$data[ $v->event_type ] = $data[ $v->event_type ] + 1;
					}
					$total_events = $count;
				}
			} elseif ( isset( $_POST['sync_type'] ) && $_POST['sync_type'] == 'selected_sync' ) {
				if ( isset( $_POST['sync_ids'] ) && ! empty( $_POST['sync_ids'] ) ) {
					$sync_ids = explode( ',', $_POST['sync_ids'] );
					$count    = 0;
					if ( ! empty( $sync_ids ) && is_array( $sync_ids ) ) {
						foreach ( $sync_ids as $sync_id ) {
							$rel = $this->InstaWP_db->get_with_condition( $this->tables['ch_table'], 'id', $sync_id );
							foreach ( $rel as $v ) {
								$count                  = $count + 1;
								$data['total_events']   = $count;
								$data[ $v->event_type ] = $data[ $v->event_type ] + 1;
							}
							$total_events = $count;
						}
					}
				}
			} else {
				$type_counts = $this->InstaWP_db->get_event_type_counts( $this->tables['ch_table'], 'event_type' );
				if ( ! empty( $type_counts ) && is_array( $type_counts ) ) {
					$total_events = 0;
					foreach ( $type_counts as $typeC ) {
						$data[ $typeC->event_type ] = $typeC->type_count;
						$total_events               += intval( $typeC->type_count );
					}
					$data['total_events'] = $total_events;
				}
			}

			if ( ! empty( $total_events ) && $total_events > 0 ) {
				echo $this->formatSuccessReponse( "The data has packed successfully as JSON from WP DB", json_encode( $data ) );
			} else {
				echo $this->formatErrorReponse( "The events are not available" );
			}
			wp_die();
		} catch ( Exception $e ) {
			echo $this->formatErrorReponse( "Caught Exception: ", $e->getMessage() );
		}
		wp_die();
	}

	public function sync_changes() {
		if ( isset( $_POST['dest_connect_id'] ) && $_POST['dest_connect_id'] != '' ) {
			$dest_connect_id = sanitize_text_field( $_POST['dest_connect_id'] );

			$message         = isset( $_POST['sync_message'] ) ? $_POST['sync_message'] : '';
			$data            = stripslashes( $_POST['data'] );

			$events          = $this->get_wp_events();

			$eventsArr       = json_decode( $events );
			if ( isset( $eventsArr->success ) && $eventsArr->success === true ) {
				$packed_data = json_encode( [
					'encrypted_content' => json_encode( $eventsArr->data->contents ),
					'dest_connect_id'   => $dest_connect_id, #live
					'changes'           => $data,
					'upload_wp_user'    => get_current_user_id(),
					'sync_message'      => $message,
					'source_connect_id' => instawp()->connect_id, #staging id
					'source_url'        => get_site_url() #staging url
				] );

				//    echo $packed_data;
				//    exit();
				$resp        = $this->sync_upload( $packed_data, null );
				$resp_decode = json_decode( $resp );
 
				if ( isset( $resp_decode->status ) && $resp_decode->status === true ) {
					if ( isset( $resp_decode->data->sync_id ) && ! empty( $resp_decode->data->sync_id ) ) {
						
						$paging_data 		= $eventsArr->data->results;
						$event_counts 		= count( $paging_data->events );
						$percentage 		= round( ( $paging_data->page*100 )/$paging_data->total_page );
						$total_completed 	= ( $paging_data->page - 1 ) * INSTAWP_EVENTS_SYNC_PER_PAGE + $event_counts;

						$repD = [
							'paging_data'	=> [
								'count'    			=> 	$paging_data->count, 
								'page'				=> 	$paging_data->page,
								'total_page'		=>	$paging_data->total_page,
								'next_page'			=>	$paging_data->page + 1,
								'total_completed'	=>  $total_completed,
								'percent_completed'	=>  $percentage,
								'event'				=>  $event_counts,
								'sync_id'			=>	$resp_decode->data->sync_id,
								'per_page'			=>	INSTAWP_EVENTS_SYNC_PER_PAGE,
								'progress_text'		=>  $percentage.'%'.sprintf(" Completed ( %u out of %s events)", $total_completed, $paging_data->count)
							]
						];
						echo $this->formatSuccessReponse( $resp_decode->message, $repD );
					}
				} else {
					echo $this->formatErrorReponse( $resp_decode->message );
				}
			} else {
				echo $this->formatErrorReponse( $eventsArr->message );
			}
		} else {
			echo $this->formatErrorReponse( 'Destination is required.' );
		}
		wp_die();
	}

	public function instawp_update_sync_events_status(){
 
		if( isset($_POST['sync_ids']) && isset($_POST['connect_id']) && intval( $_POST['connect_id'] ) > 0 ){
			
			$ids 		= sanitize_text_field( $_POST['sync_ids'] );
			$connect_id = sanitize_text_field( $_POST['connect_id'] );
			if( $ids ){
				$ids = array_filter ( explode(',', $ids ) );
				if( !empty( $ids ) && is_array( $ids ) ){
					foreach ($ids as $key => $sync_id) {

						$sync_resp = $this->get_Sync_Object( $sync_id );
						$response  = json_decode( $sync_resp );
 
						if ( $response->status === 1 || $response->status === true ) {
							
							$site_sync_row = $this->wpdb->get_row("SELECT * FROM ".INSTAWP_DB_TABLE_EVENT_SITES."", ARRAY_A);
							$site_sync_row = $site_sync_row ?? [];

							if( ( !array_key_exists('synced_message', $site_sync_row ) ) ){
								$this->wpdb->query("ALTER TABLE ".INSTAWP_DB_TABLE_EVENT_SITES." ADD `synced_message` TEXT NULL DEFAULT NULL AFTER `status`");
							}
							
							if ( isset( $response->data->changes->changes->sync_response ) ) {
								$sync_response = $response->data->changes->changes->sync_response;
								foreach ( $sync_response as $v ) {
									$this->InstaWP_db->insert( INSTAWP_DB_TABLE_EVENT_SITES, [
										'event_id'   		=> $v->id,
										'connect_id' 		=> $connect_id,
										'status'     		=> $v->status,
										'synced_message' 	=> $v->message,
										'date'       		=> date( "Y-m-d h:i:s" )
									] );
								}
							}
						}
					}
				}
			}
		}
		wp_die(1);
	}

	public function single_sync() {
		if ( isset( $_POST['sync_id'] ) ) {
			$sync_id = $_POST['sync_id'];
			$rel     = $this->InstaWP_db->getRowById( $this->tables['ch_table'], $sync_id );
			echo json_encode( $rel );
		}
		wp_die();
	}

	/*
	*  Endpoint - /api/v2/connects/$connect_id/syncs
	*  Example - https://s.instawp.io/api/v2/connects/1009/syncs
	*  Sync upload Api
	*/
	public function sync_upload( $data = null, $endpoint = null ) {
		$api_doamin = InstaWP_Setting::get_api_domain();
		$connect_id = instawp_get_connect_id();

		$endpoint = '/api/v2/connects/' . $connect_id . '/syncs';
		$url      = $api_doamin . $endpoint; #https://stage.instawp.io/api/v2/connects/53/syncs
		$api_key  = $this->get_api_key();
		try {
			$curl = curl_init();
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => $data,
				CURLOPT_HTTPHEADER     => array(
					'Authorization: Bearer ' . $api_key . '',
					'Content-Type: application/json'
				),
			) );
			$response = curl_exec( $curl );
			$result   = json_decode( $response );

			return $response;
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
	}

	#Get specific sync
	public function get_Sync_Object( $sync_id = null ) {
		global $InstaWP_Curl;
		$api_doamin = InstaWP_Setting::get_api_domain();
		$connect_id = instawp_get_connect_id();
		$endpoint   = '/api/v2/connects/' . $connect_id . '/syncs/' . $sync_id;
		$url        = $api_doamin . $endpoint; #https://stage.instawp.io/api/v2/connects/53/syncs/104
		$api_key    = $this->get_api_key();

		try {
			$curl = curl_init();
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'GET',
				CURLOPT_HTTPHEADER     => array(
					'Accept: application/json',
					'Authorization: Bearer ' . $api_key . ''
				),
			) );
			$response = curl_exec( $curl );
			curl_close( $curl );

			return $response;
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
	}

	public function get_api_key() {
		$instawp_api_options = get_option( 'instawp_api_options' );
		return $instawp_api_options['api_key'];
	}


	public function instawp_get_connect_quota_remaining_limit() {
		$api_response = InstaWP_Curl::do_curl( 'connects/' . instawp_get_connect_id() . '/get-sync-quota', [], [], false );
		if ( $api_response['success'] && ! empty( $api_response['data'] ) ) {
			$data =  $api_response['data'];
			if( isset( $data['sync_quota_limit'] ) && isset( $data['sync_quota_used'] ) ){
				$remaining = $data['sync_quota_limit'] - $data['sync_quota_used'];
				return $remaining;
			}
		}
		return 0;
	}
}

new InstaWP_Ajax_Fn();