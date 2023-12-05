<?php
/**
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Ajax {

	private $wpdb;

	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;

		#The wp_ajax_ hook only fires for logged-in users
		add_action( 'wp_ajax_instawp_is_event_syncing', array( $this, 'is_event_syncing' ) );
		add_action( 'wp_ajax_instawp_get_site_events', array( $this, 'get_site_events' ) );
		add_action( 'wp_ajax_instawp_handle_select2', array( $this, 'handle_select2' ) );
		add_action( 'wp_ajax_instawp_pack_events', array( $this, 'pack_events' ) );
		add_action( 'wp_ajax_instawp_sync_changes', array( $this, 'sync_changes' ) );
		add_action( 'wp_ajax_instawp_get_events_summary', array( $this, 'get_events_summary' ) );
		add_action( 'wp_ajax_instawp_delete_events', array( $this, 'delete_events' ) );
		add_action( 'wp_ajax_instawp_calculate_events', array( $this, 'calculate_events' ) );
	}

	public function is_event_syncing() {
		check_ajax_referer( 'instawp-tws', 'nonce' );

		$sync_status = $_POST['sync_status'];
		update_option( 'instawp_is_event_syncing', $sync_status );

		$message = ( $sync_status == 1 ) ? 'Syncing enabled!' : 'Syncing disabled!';
		wp_send_json( [ 'sync_status' => $sync_status, 'message' => $message ] );
	}

	public function get_site_events() {
		check_ajax_referer( 'instawp-tws', 'nonce' );

		$connect_id     = ! empty( $_POST['connect_id'] ) ? intval( $_POST['connect_id'] ) : 0;
		$where          = '1=1';
		$items_per_page = INSTAWP_EVENTS_PER_PAGE;

		if ( $connect_id > 0 ) {
			$staging_site = get_connect_detail_by_connect_id( $connect_id );

			if ( ! empty( $staging_site ) && isset( $staging_site['created_at'] ) ) {
				$staging_site_created = date( 'Y-m-d h:i:s', strtotime( $staging_site['created_at'] ) );
				$where                .= " AND date >= '" . $staging_site_created . "'";
			}
		}

		$query       = "SELECT * FROM " . INSTAWP_DB_TABLE_EVENTS . "  WHERE $where";
		$total_query = "SELECT COUNT(1) FROM ({$query}) AS combined_table ";
		$total       = $this->wpdb->get_var( $total_query );

		$page   = isset( $_POST['epage'] ) ? abs( (int) $_POST['epage'] ) : 1;
		$offset = ( $page * $items_per_page ) - $items_per_page;

		$events = $this->wpdb->get_results( $query . " ORDER BY id DESC LIMIT {$offset}, {$items_per_page}" );

		$totalPage = ceil( $total / $items_per_page );

		ob_start();
		include INSTAWP_PLUGIN_DIR . '/migrate/templates/ajax/part-sync-items.php';
		$data = ob_get_contents();
		ob_end_clean();

		$this->send_success( 'Event fetched.', [
			'results'    => $data,
			'pagination' => $this->get_events_sync_list_pagination( $total, $items_per_page, $page )
		] );
	}

	public function handle_select2() {
		if ( isset( $_GET['event'] ) ) {
			if ( $_GET['event'] == 'instawp_get_users' ) {
				$keyword = $_GET['term'];
				$args    = array(
					'search'         => $keyword,
					'paged'          => 1,
					'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
					'fields'         => array( 'id', 'user_login' )
				);
				$users   = get_users( $args );
				$this->send_success( "Users loaded", [ 'results' => $users, 'opt_col' => [ 'text' => 'user_login', 'id' => 'ID' ] ] );
			} else if ( $_GET['event'] == 'instawp_sync_tab_roles' ) {

				$results   = [];
				$all_roles = wp_roles()->roles;
				foreach ( $all_roles as $slug => $role ) {
					$results[] = [ 'id' => $slug, 'name' => $role['name'] ];
				}
				$this->send_success( "Users loaded", [ 'results' => $results, 'opt_col' => [ 'text' => 'name', 'id' => 'id' ] ] );
			}
		}
	}

	public function pack_events() {
		check_ajax_referer( 'instawp-tws', 'nonce' );

		try {
			$events = $this->pack_pending_sync_events();
			if ( ! empty( $events ) ) {
				$data = [];
				foreach ( $events as $row ) {
					$data[ $row->event_type ] = $data[ $row->event_type ] + 1;
				}
				$data['total_events'] = count( $events );
				$this->send_success( 'The data has packed successfully as JSON from WP DB', $data );
			} else {
				$this->send_error( 'The events are not available' );
			}
		} catch ( Exception $e ) {
			$this->send_error( 'Caught Exception: ' . $e->getMessage() );
		}
	}

	public function sync_changes() {
		check_ajax_referer( 'instawp-tws', 'nonce' );

		$dest_connect_id = ! empty( $_POST['dest_connect_id'] ) ? intval( $_POST['dest_connect_id'] ) : '';
		if ( empty( $dest_connect_id ) ) {
			$this->send_error( 'Invalid destination.' );
		}

		$message = isset( $_POST['sync_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sync_message'] ) ) : '';
		$data    = wp_unslash( $_POST['data'] );
		$events  = $this->get_wp_events();

		if ( isset( $events['success'] ) && $events['success'] === true ) {
			$packed_data = [
				'encrypted_content' => wp_json_encode( $events['data']['contents'] ),
				'dest_connect_id'   => $dest_connect_id,
				'changes'           => $data,
				'upload_wp_user'    => get_current_user_id(),
				'sync_message'      => $message,
				'source_connect_id' => instawp()->connect_id,
				'source_url'        => get_site_url()
			];

			$response = $this->sync_upload( $packed_data );
			if ( ! isset( $response['success'] ) || $response['success'] !== true ) {
				$this->send_error( $response['message'] );
			}

			$sync_id = ! empty( $response['data']['sync_id'] ) ? $response['data']['sync_id'] : '';
			if ( empty( $sync_id ) ) {
				$this->send_error( 'Sync ID missing!' );
			}

			$this->update_sync_events_status( $dest_connect_id, $sync_id );

			$batch_data      = InstaWP_Setting::get_option( 'instawp_event_batch_data' );
			$total_completed = $batch_data['total_completed'] + count( $events['data']['contents'] );
			$percentage      = round( ( $batch_data['current_batch'] * 100 ) / intval( $batch_data['total_batch'] ) );
			$next_batch      = $batch_data['current_batch'] + 1;

			$result = [
				'count'             => $batch_data['total_events'],
				'current_batch'     => $batch_data['current_batch'],
				'next_batch'        => $next_batch,
				'total_completed'   => $total_completed,
				'percent_completed' => $percentage,
				'per_batch'         => INSTAWP_EVENTS_SYNC_PER_PAGE,
				'total_batch'       => intval( $batch_data['total_batch'] ),
				'progress_text'     => $percentage . '%' . sprintf( " Completed ( %u out of %s events)", $total_completed, intval( $batch_data['total_events'] ) )
			];

			$batch_data['current_batch']   = $next_batch;
			$batch_data['total_completed'] = $total_completed;

			update_option( 'instawp_event_batch_data', $batch_data );

			$this->send_success( $response['message'], $result );
		} else {
			$this->send_error( 'No pending events found!' );
		}
	}

	public function get_events_summary() {
		check_ajax_referer( 'instawp-tws', 'nonce' );

		$where = $where2 = "1=1";
		$connect_id = ! empty( $_POST['connect_id'] ) ? intval( $_POST['connect_id'] ) : 0;

		if ( $connect_id > 0 ) {
			$where        .= " AND connect_id=" . $connect_id;
			$staging_site = get_connect_detail_by_connect_id( $connect_id );

			if ( ! empty( $staging_site ) && isset( $staging_site['created_at'] ) && ! instawp()->is_staging ) {
				$staging_site_created = date( 'Y-m-d h:i:s', strtotime( $staging_site['created_at'] ) );
				$where2               .= " AND date >= '" . $staging_site_created . "'";
			}
		}

		$query   = "SELECT event_name, COUNT(*) as event_count FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE $where2 AND `id` NOT IN (SELECT event_id AS id FROM " . INSTAWP_DB_TABLE_EVENT_SITES . " WHERE $where) GROUP BY event_name HAVING event_count > 0";
		$results = $this->wpdb->get_results( $query );

		$html = '<ul class="list">';
		if ( ! empty( $results ) ) {
			foreach ( $results as $i => $row ) {
				$html .= '<li class="event-type-count ' . ( $i > 2 ? 'hidden' : '' ) . '">';
				$html .= sprintf( __( '%u %s', 'instawp-connect' ), $row->event_count, ucfirst( str_replace( "_", " ", $row->event_name ) ) );
				$html .= '</li>';
			}

			$html .= '<li class="event-type-count-show-more" style="display:none">';
			$html .= '<a href="javascript:void(0)" class="load-more-event-type">' . esc_html( __( 'Show more', 'instawp-connect' ) ) . '</a>';
			$html .= '</li>';

		} else {
			$results = [ 'Post', 'Page', 'Theme', 'Plugin' ];
			foreach ( $results as $row ) {
				$html .= '<li class="event-type-count">';
				$html .= sprintf( __( '%u %s %s', 'instawp-connect' ), 0, $row, in_array( $row, [ 'Page', 'Post' ] ) ? 'modified' : 'installed' );
				$html .= '</li>';
			}
		}
		$html .= '</ul>';

		delete_option( 'instawp_event_batch_data' );

		$total_events = $this->get_total_pending_events_count();

		$this->send_success( 'Summery fetched', [
			'html'          => $html,
			'count'         => $total_events,
			'progress_text' => sprintf( __( 'Sync not initiated ( 0 out of %d events )', 'instawp-connect' ), $total_events ),
			'message'       => $total_events > 0 ? __( 'Events loaded', 'instawp-connect' ) : __( 'No pending events found!', 'instawp-connect' )
		] );
	}

	public function delete_events() {
		check_ajax_referer( 'instawp-tws', 'nonce' );

		if ( isset( $_POST['ids'] ) && ! empty( $_POST['ids'] ) ) {
			global $wpdb;
			$ids = sanitize_text_field( $_POST['ids'] );
			$wpdb->query( "DELETE FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE id IN($ids)" );
			if ( isset( $_POST['connect_id'] ) && intval( $_POST['connect_id'] ) > 0 ) {
				$wpdb->query( "DELETE FROM " . INSTAWP_DB_TABLE_EVENT_SITES . " WHERE event_id IN($ids)" );
			}
			$this->send_success( 'Data deleted' );
		}
	}

	public function calculate_events() {
		check_ajax_referer( 'instawp-tws', 'nonce' );

		$total_events = $this->get_total_pending_events_count();

		if ( $total_events > 0 ) {
			delete_option( 'instawp_event_batch_data' );

			$sync_quota_response = $this->get_connect_quota_remaining_limit();
			if ( ! empty( $sync_quota_response ) ) {
				if ( $sync_quota_response['remaining'] >= $total_events ) {
					$batch_data = [
						'per_batch'         => INSTAWP_EVENTS_SYNC_PER_PAGE,
						'total_batch'       => ceil( $total_events / INSTAWP_EVENTS_SYNC_PER_PAGE ),
						'total_events'      => $total_events,
						'current_batch'     => 1,
						'percent_completed' => 0,
						'total_completed'   => 0,
					];
					update_option( 'instawp_event_batch_data', $batch_data );

					$this->send_success( 'Event fetched.', [
						'count'         => $total_events,
						'page'          => 1,
						'per_page'      => INSTAWP_EVENTS_SYNC_PER_PAGE,
						'progress_text' => '0%' . sprintf( __( ' Completed ( 0 out of %d events )', 'instawp-connect' ), $total_events )
					] );
				} else {
					$this->send_error( sprintf( __( 'You have reached your sync limit. Current usage %u out of %u.', 'instawp-connect' ), $sync_quota_response['remaining'], $sync_quota_response['sync_quota_limit'] ) );
				}
			}
		} else {
			$this->send_error( __( 'No pending events found!', 'instawp-connect' ) );
		}
	}

	private function send_success( $message, $data = [] ) {
		wp_send_json( [
			'success' => true,
			'message' => $message,
			'data'    => $data
		] );
	}

	private function send_error( $message = 'Something went wrong' ) {
		wp_send_json( [
			'success' => false,
			'message' => $message
		] );
	}

	private function update_sync_events_status( $connect_id, $sync_id ): array {
		try {
			$response = $this->get_sync_object( $sync_id );

			if ( $response['success'] === true ) {
				$sync_response = $response['data']['changes']['changes']['sync_response'] ?? [];
				foreach ( $sync_response as $data ) {
					InstaWP_Sync_DB::insert( INSTAWP_DB_TABLE_EVENT_SITES, [
						'event_id'       => $data['id'],
						'connect_id'     => $connect_id,
						'status'         => $data['status'],
						'synced_message' => $data['message'],
						'date'           => date( 'Y-m-d h:i:s' )
					] );
				}
			}

			return $response;
		} catch ( Exception $e ) {
			return [
				'success' => false,
				'message' => 'Caught Exception: ' . $e->getMessage(),
			];
		}
	}

	private function get_events_sync_list_pagination( $total, $items_per_page, $page ) {
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

	private function get_total_pending_events_count() {
		$where = $where2 = "1=1";
		if ( isset( $_POST['connect_id'] ) && intval( $_POST['connect_id'] ) > 0 ) {
			$connect_id   = sanitize_text_field( $_POST['connect_id'] );
			$staging_site = get_connect_detail_by_connect_id( $connect_id );
			$where        .= " AND connect_id=" . sanitize_text_field( $connect_id );
		}

		if ( ! empty( $staging_site ) && isset( $staging_site['created_at'] ) ) {
			$staging_site_created = date( 'Y-m-d h:i:s', strtotime( $staging_site['created_at'] ) );
			$where2               .= " AND date >= '" . $staging_site_created . "'";
		}

		$query = "SELECT COUNT(1) FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE $where2 AND `id` NOT IN (SELECT event_id AS id FROM " . INSTAWP_DB_TABLE_EVENT_SITES . " WHERE $where)";

		return $this->wpdb->get_var( $query );
	}

	private function sync_upload( $data = null ): array {
		$connect_id = instawp_get_connect_id();

		// connects/<connect_id>/syncs
		return InstaWP_Curl::do_curl( "connects/{$connect_id}/syncs", $data );
	}

	private function get_sync_object( $sync_id = null ): array {
		$connect_id = instawp_get_connect_id();

		// connects/<connect_id>/syncs
		return InstaWP_Curl::do_curl( "connects/{$connect_id}/syncs/{$sync_id}", [], [], false );
	}

	private function get_connect_quota_remaining_limit() {
		$connect_id   = instawp_get_connect_id();

		// connects/<connect_id>/staging-sites
		$api_response = InstaWP_Curl::do_curl( "connects/{$connect_id}/get-sync-quota", [], [], false );

		if ( $api_response['success'] && ! empty( $api_response['data'] ) ) {
			return $api_response['data'];
		}

		return false;
	}

	private function pack_pending_sync_events() {
		$where = "1=1";
		if ( isset( $_POST['dest_connect_id'] ) && intval( $_POST['dest_connect_id'] ) > 0 ) {
			$where .= " AND connect_id=" . sanitize_text_field( $_POST['dest_connect_id'] );
		}
		$query = "SELECT * FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE `id` NOT IN (SELECT event_id AS id FROM " . INSTAWP_DB_TABLE_EVENT_SITES . " WHERE $where) ORDER BY id ASC LIMIT " . INSTAWP_EVENTS_SYNC_PER_PAGE;

		return $this->wpdb->get_results( $query );
	}

	private function get_wp_events(): array {
		try {
			$encrypted_content = [];
			$events            = $this->pack_pending_sync_events();
			$output            = [
				'success' => false,
				'message' => '',
			];

			if ( ! empty( $events ) && is_array( $events ) ) {
				foreach ( $events as $k => $v ) {
					$event_hash = $v->event_hash;

					if ( empty( $event_hash ) ) {
						$event_hash = InstaWP_Tools::get_random_string();
						$this->wpdb->update( INSTAWP_DB_TABLE_EVENT_SYNC_LOGS, [ 'event_hash' => $event_hash ], [ 'id' => $v->id ] );
					}

					$encrypted_content[] = [
						'id'         => $v->id,
						'event_hash' => $event_hash,
						'details'    => json_decode( $v->details ),
						'event_name' => $v->event_name,
						'event_slug' => $v->event_slug,
						'event_type' => $v->event_type,
						'source_id'  => $v->source_id,
						'user_id'    => $v->user_id,
					];
				}

				if ( count( $encrypted_content ) > 0 ) {
					$output['success']          = true;
					$output['message']          = 'The data has packed successfully as JSON from WP DB';
					$output['data']['contents'] = $encrypted_content;
				} else {
					$output['message'] = 'No pending events found!';
				}
			}
		} catch ( Exception $e ) {
			$output['message'] = "Caught Exception: " . $e->getMessage();
		}

		return $output;
	}
}

new InstaWP_Sync_Ajax();