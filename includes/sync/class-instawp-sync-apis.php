<?php
/**
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Apis extends InstaWP_Rest_Api {

	private $tables;

	private $logs = array();

	public function __construct() {
		parent::__construct();
		$this->tables = InstaWP_Sync_DB::$tables;

		add_action( 'rest_api_init', array( $this, 'add_api_routes' ) );
		add_action( 'instawp/actions/2waysync/record_event', array( $this, 'register_event' ), 10, 3 );
	}

	public function add_api_routes() {
		register_rest_route( $this->namespace . '/' . $this->version, '/sync', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'events_receiver' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Handle events receiver api
	 *
	 * @param WP_REST_Request $req
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 * @throws Exception
	 */
	public function events_receiver( WP_REST_Request $req ) {

		$response = $this->validate_api_request( $req );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		global $wpdb;

		$body    = $req->get_body();
		$bodyArr = json_decode( $body );

		if ( ! isset( $bodyArr->encrypted_contents ) ) {
			return new WP_Error( 400, esc_html__( 'Invalid data', 'instawp-connect' ) );
		}

		instawp_create_db_tables();

		$encrypted_contents = json_decode( $bodyArr->encrypted_contents );
		$sync_id            = $bodyArr->sync_id;
		$source_connect_id  = $bodyArr->source_connect_id;
		$source_url         = $bodyArr->source_url;
		$is_enabled         = false;
		$changes            = array();
		$sync_response      = array();

		if ( get_option( 'instawp_is_event_syncing' ) ) {
			$is_enabled = true;
		}

		delete_option( 'instawp_is_event_syncing' );

		if ( ! empty( $encrypted_contents ) && is_array( $encrypted_contents ) ) {
			$count        = 1;
			$total_op     = count( $encrypted_contents );
			$sync_message = isset( $bodyArr->sync_message ) ? $bodyArr->sync_message : '';

			foreach ( $encrypted_contents as $event ) {
				$has_log = $wpdb->get_var(
					$wpdb->prepare( "SELECT id FROM " . INSTAWP_DB_TABLE_EVENT_SYNC_LOGS . " WHERE `event_hash`=%s AND `status`=%s", $event->event_hash, 'completed' ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

				if ( $has_log ) {
					$response_data               = InstaWP_Sync_Helpers::sync_response( $event );
					$sync_response[ $event->id ] = $response_data['data'];
				} else {
					if ( empty( $event->event_slug ) || empty( $event->details ) ) {
						continue;
					}

					$reference_id        = ( ! empty( $event->source_id ) ) ? sanitize_text_field( $event->source_id ) : null;
					$event->reference_id = $reference_id;

					$response_data = ( array ) apply_filters( 'instawp/filters/2waysync/process_event', array(), $event, $source_url );
					if ( ! empty( $response_data['data'] ) ) {
						$sync_response[ $event->id ] = $response_data['data'];
					}

					if ( ! empty( $response_data['log_data'] ) ) {
						$this->logs = array_merge( $this->logs, $response_data['log_data'] );
					}

					// record logs
					$this->event_sync_logs( $event, $source_url, $sync_response );
				}

				$progress        = intval( ( $count / $total_op ) * 100 );
				$progress_status = ( $progress < 100 ) ? 'in_progress' : 'completed';

				/**
				 * Update api for cloud
				 */
				$sync_update = array(
					'progress' => $progress,
					'status'   => $progress_status,
					'message'  => $sync_message,
					'changes'  => array(
						'changes'       => $changes,
						'sync_response' => array_values( $sync_response ),
						'logs'          => $this->logs,
					),
				);
				$this->sync_update( $sync_id, $sync_update );
				++$count ;
			}
		}

		if ( ! empty( $source_url ) && class_exists( '\Elementor\Utils' ) && method_exists('\Elementor\Utils', 'replace_urls' ) ) {
			try {
				\Elementor\Utils::replace_urls( $source_url, site_url() );
			} catch ( \Exception $e ) {}
		}

		#Sync history save
		$this->sync_history_save( $body, $changes, 'Complete' );

		#enable is back if syncing already enabled at the destination
		if ( $is_enabled ) {
			Option::update_option( 'instawp_is_event_syncing', 1 );
		}

		return $this->send_response( array(
			'sync_id'            => $sync_id,
			'encrypted_contents' => $encrypted_contents,
			'source_connect_id'  => $source_connect_id,
			'changes'            => array(
				'changes'       => $changes,
				'sync_response' => array_values( $sync_response ),
			),
		) );
	}

	public function register_event( $event, $reference_id, $source ) {
		$event = wp_parse_args( ( array ) $event, array(
			'name'  => '', // Event name.
			'slug'  => '', // Event slug i.e. post_meta_added/post_meta_deleted.
			'type'  => '', // Event type i.e. Object type i.e. Post/User/Term.
			'title' => '', // Event title.
			'data'  => array(), // Event data.
		) );

		if ( ! empty( $reference_id ) ) { // Reference ID should be unique for each event.
            $event_source = 'source_' . $source;
			InstaWP_Sync_DB::insert_update_event( $event['name'], $event['slug'], $event['type'], $reference_id, $event['title'], $event['data'], $event_source );
		}
	}

	public function event_sync_logs( $data, $source_url, $response ) {
		$status = ! empty( $this->logs[ $data->id ] ) ? 'failed' : ( isset( $response[ $data->id ]['status'] ) ? $response[ $data->id ]['status'] : 'error' );
		$data   = array(
			'event_id'   => $data->id,
			'event_hash' => $data->event_hash,
			'source_url' => $source_url,
			'status'     => $status,
			'data'       => wp_json_encode( $data->details ),
			'logs'       => isset( $this->logs[ $data->id ] ) ? $this->logs[ $data->id ] : '',
			'date'       => current_time( 'mysql', 1 ),
		);
		InstaWP_Sync_DB::insert( INSTAWP_DB_TABLE_EVENT_SYNC_LOGS, $data );
	}

	#Insert history
	public function sync_history_save( $body = null, $changes = null, $status = null ) {
		$dir     = 'dev-to-live';
		$date    = current_time( 'mysql', 1 );
		$bodyArr = json_decode( $body );
		$message = isset( $bodyArr->sync_message ) ? $bodyArr->sync_message : '';
		$data    = array(
			'encrypted_contents' => $bodyArr->encrypted_contents,
			'changes'            => wp_json_encode( $changes ),
			'sync_response'      => '',
			'direction'          => $dir,
			'status'             => $status,
			'user_id'            => isset( $bodyArr->upload_wp_user ) ? $bodyArr->upload_wp_user : '',
			'changes_sync_id'    => isset( $bodyArr->sync_id ) ? $bodyArr->sync_id : '',
			'sync_message'       => $message,
			'source_connect_id'  => '',
			'source_url'         => isset( $bodyArr->source_url ) ? $bodyArr->source_url : '',
			'date'               => $date,
		);

		InstaWP_Sync_DB::insert( $this->tables['sh_table'], $data );
	}

	/** sync update
	 *
	 * @param $sync_id
	 * @param $data
	 * @param $source_connect_id
	 *
	 * @return array
	 */
	public function sync_update( $sync_id, $data ) {
		$connect_id = instawp_get_connect_id();

		// connects/<connect_id>/syncs/<sync_id>
		return Curl::do_curl( "connects/{$connect_id}/syncs/{$sync_id}", $data, array(), 'PATCH' );
	}
}

new InstaWP_Sync_Apis();