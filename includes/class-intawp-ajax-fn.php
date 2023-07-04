<?php
/**
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

/**
 *
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/includes
 * @author     instawp team
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
		add_action( "wp_ajax_pack_things", array( $this, "get_data_from_db" ) );
		add_action( "wp_ajax_sync_changes", array( $this, "sync_changes" ) );
		add_action( "wp_ajax_single_sync", array( $this, "single_sync" ) );
		add_action( "wp_ajax_syncing_enabled_disabled", array( $this, "syncing_enabled_disabled" ) );
		add_action( 'wp_ajax_get_site_events', array( $this, 'get_site_events' ) );
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

	public function get_site_events() {
		global $wpdb;

		$connect_id = isset( $_POST['connect_id'] ) ? sanitize_text_field( $_POST['connect_id'] ) : 0;
		$InstaWP_db = new InstaWP_DB();
		$tables     = $InstaWP_db->tables;

		$query          = "SELECT * FROM {$tables['ch_table']}";
		$total_query    = "SELECT COUNT(1) FROM (${query}) AS combined_table";
		$total          = $wpdb->get_var( $total_query );
		$items_per_page = 20;

		// if(isset($_POST['filter_action']) && !empty($_POST['event_type'])){
		//     $events = $InstaWP_db->get_with_condition($tables['ch_table'],'event_type',$_POST['event_type']);
		// }elseif(isset($_GET['change_event_status']) && $_GET['change_event_status'] != 'all'){
		//     $events = $InstaWP_db->get_with_condition($tables['ch_table'],'status',$_GET['change_event_status']);
		// }
		// else{
		//     $events = $InstaWP_db->getAllEvents();
		// }
		$page      = isset( $_POST['epage'] ) ? abs( (int) $_POST['epage'] ) : 1;
		$offset    = ( $page * $items_per_page ) - $items_per_page;
		$events    = $wpdb->get_results( $query . " ORDER BY id DESC LIMIT ${offset}, ${items_per_page}" );
		$totalPage = ceil( $total / $items_per_page );

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

	public function get_wp_events( $sync_ids = null, $sync_type = null, $dest_connect_id = null ) {
		try {
			if ( isset( $sync_type ) && $sync_type == 'single_sync' ) {
				$rel = $this->InstaWP_db->getByOneCondition( $this->tables['ch_table'], 'id', $sync_ids );
			} elseif ( isset( $sync_type ) && $sync_type == 'selected_sync' ) {
				$rel = $this->InstaWP_db->getByInCondition( $this->tables['ch_table'], 'id', $sync_ids, 'status', 'pending' );
			} else {
				$rel = $this->InstaWP_db->getAllEvents();
			}
			$encrypted_content = [];
			if ( ! empty( $rel ) && is_array( $rel ) ) {
				foreach ( $rel as $k => $v ) {
					$eventpvoit = $this->InstaWP_db->getSiteEventStatus( $dest_connect_id, $v->id );
					if ( ! $eventpvoit ) {
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
				}

				if ( count( $encrypted_content ) > 0 ) {
					return $this->formatSuccessReponse( "The data has packed successfully as JSON from WP DB", $encrypted_content );
				} else {
					return $this->formatErrorReponse( "No pending events found!" );
				}
				#return json_encode($encrypted_content);
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
		$connect_id = get_connect_id();
		if ( isset( $_POST['dest_connect_id'] ) && $_POST['dest_connect_id'] != '' ) {
			$dest_connect_id = $_POST['dest_connect_id'];
			$message         = isset( $_POST['sync_message'] ) ? $_POST['sync_message'] : '';
			$data            = stripslashes( $_POST['data'] );
			$sync_ids        = isset( $_POST['sync_ids'] ) ? $_POST['sync_ids'] : '';
			$sync_type       = isset( $_POST['sync_type'] ) ? $_POST['sync_type'] : '';
			$events          = $this->get_wp_events( $sync_ids, $sync_type, $dest_connect_id );
			$eventsArr       = json_decode( $events );
			if ( isset( $eventsArr->success ) && $eventsArr->success === true ) {
				$packed_data = json_encode( [
					'encrypted_content' => json_encode( $eventsArr->data ),
					'dest_connect_id'   => $dest_connect_id, #live
					'changes'           => $data,
					'upload_wp_user'    => get_current_user_id(),
					'sync_message'      => $message,
					'source_connect_id' => $connect_id, #staging id
					'source_url'        => get_site_url() #staging url
				] );

				//    echo $packed_data;
				//    exit();
				$resp        = $this->sync_upload( $packed_data, null );
				$resp_decode = json_decode( $resp );
				if ( isset( $resp_decode->status ) && $resp_decode->status === true ) {
					$sync_resp = '';
					if ( isset( $resp_decode->data->sync_id ) && ! empty( $resp_decode->data->sync_id ) ) {
						$sync_resp = $this->get_Sync_Object( $resp_decode->data->sync_id );
						$respD     = json_decode( $sync_resp );// WE WILL USE IT.
						if ( $respD->status === 1 || $respD->status === true ) {
							if ( isset( $respD->data->changes->changes->sync_response ) ) {
								$sync_response = $respD->data->changes->changes->sync_response;
								foreach ( $sync_response as $v ) {
									$res_data = [
										'status'         => $v->status,
										'synced_message' => $v->message
									];
									$this->InstaWP_db->update( $this->tables['ch_table'], $res_data, $v->id );
									$this->InstaWP_db->insert( $this->tables['se_table'], [
										'event_id'   => $v->id,
										'connect_id' => $dest_connect_id,
										'status'     => $v->status,
										'date'       => date( "Y-m-d h:i:s" )
									] );
								}
							}
							$total = isset( $respD->data->changes->total ) ? json_decode( $respD->data->changes->total ) : '';
							if ( $total->sync_type == 'single_sync' ) {
								$repD = [
									'sync_type' => $total->sync_type,
									'res_data'  => $res_data
								];
							} else {
								$repD = [
									'sync_type' => $total->sync_type,
									'res_data'  => $res_data
								];
							}
							echo $this->formatSuccessReponse( $resp_decode->message, $repD );
						}
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
		$connect_id = get_connect_id();

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
		$connect_id = get_connect_id();
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
}

new InstaWP_Ajax_Fn();