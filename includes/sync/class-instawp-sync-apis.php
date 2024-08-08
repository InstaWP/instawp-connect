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
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Apis extends InstaWP_Rest_Api {

	private $tables;

	private $sync;

	private $logs = array();

	public function __construct() {
		parent::__construct();
		$this->tables = InstaWP_Sync_DB::$tables;
        $this->sync   = new InstaWP_Sync_Ajax();

		add_action( 'rest_api_init', array( $this, 'add_api_routes' ) );
		add_action( 'instawp/actions/2waysync/record_event', array( $this, 'register_event' ), 10, 3 );
	}

	public function add_api_routes() {
		register_rest_route( $this->namespace . '/' . $this->version, '/sync', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'events_receiver' ),
			'permission_callback' => '__return_true',
		) );

        register_rest_route( $this->namespace . '/' . $this->version, '/sync/events', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_events' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'process_events' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( $this, 'delete_events' ),
                'permission_callback' => '__return_true',
            )
        ) );

        register_rest_route( $this->namespace . '/' . $this->version, '/sync/summary', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'events_summary' ),
            'permission_callback' => '__return_true',
        ) );
	}

    /**
     * Handle response for 2 ways sync prepare events
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function get_events( WP_REST_Request $request ) {

        $response = $this->validate_api_request( $request );
        if ( is_wp_error( $response ) ) {
            return $this->throw_error( $response );
        }

        instawp_create_db_tables();

        global $wpdb;

        $params         = $request->get_params();
        $num_page       = ! empty( $params['num_page'] ) ? intval( $params['num_page'] ) : 1;
        $items_per_page = ! empty( $params['items_per_page'] ) ? intval( $params['items_per_page'] ) : 10;
        $connect_id     = ! empty( $params['connect_id'] ) ? intval( $params['connect_id'] ) : 0;
        $filter_status  = ! empty( $params['type'] ) ? $params['type'] : 'pending';
        $offset         = ( $num_page * $items_per_page ) - $items_per_page;

        $staging_site = instawp_get_site_detail_by_connect_id( $connect_id, 'data' );
        $site_created = '1970-01-01 00:00:00';

        if ( ! empty( $staging_site ) && isset( $staging_site['created_at'] ) ) {
            $site_created = date( 'Y-m-d h:i:s', strtotime( $staging_site['created_at'] ) );
        }

        $events = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE `date` >= %s ORDER BY `date` ASC, `id` ASC", $site_created ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        $events = array_map( function( $event ) use ( $connect_id ) {
            $event_row = InstaWP_Sync_DB::get_sync_event_by_id( $connect_id, $event->event_hash );

            if ( $event_row ) {
                $event->status         = ! empty( $event_row->status ) ? $event_row->status : 'pending';
                $event->synced_date    = ! empty( $event_row->date ) ? $event_row->date : $event->date;
                $event->synced_message = ! empty( $event_row->synced_message ) ? $event_row->synced_message : $event->synced_message;

                if ( $event->status === 'completed' ) {
                    $event->log = ! empty( $event_row->log ) ? $event_row->log : '';
                }
            }

            return $event;
        }, $events );
        $events = $this->sync->filter_events( $events );

        if ( $filter_status !== 'all' ) {
            $events = array_filter( $events, function ( $event ) use ( $filter_status ) {
                return $filter_status === $event->status;
            } );
        }
        $total = count( $events );
        $meta  = array(
            'total'          => $total,
            'num_page'       => $num_page,
            'items_per_page' => $items_per_page,
            'total_page'     => ceil( $total / $items_per_page ),
        );

        if ( $num_page > 1 ) {
            $prev_page    = $num_page - 1;
            $meta['prev'] = "/{$this->namespace}/{$this->version}/sync/events?type={$filter_status}&connect_id={$connect_id}&num_page={$prev_page}&items_per_page={$items_per_page}";
        }

        if ( $num_page < $meta['total_page'] ) {
            $next_page    = $num_page + 1;
            $meta['next'] = "/{$this->namespace}/{$this->version}/sync/events?type={$filter_status}&connect_id={$connect_id}&num_page={$next_page}&items_per_page={$items_per_page}";
        }

        return $this->send_response( array(
            'meta' => $meta,
            'data' => array_slice( $events, $offset, $items_per_page ),
        ) );
    }

    /**
     * Handle response for 2 ways sync process events
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function process_events( WP_REST_Request $request ) {

        $response = $this->validate_api_request( $request );
        if ( is_wp_error( $response ) ) {
            return $this->throw_error( $response );
        }

        instawp_create_db_tables();

        global $wpdb;

        $params     = $request->get_params();
        $connect_id = ! empty( $params['connect_id'] ) ? intval( $params['connect_id'] ) : 0;
        $event_ids  = ! empty( $params['event_ids'] ) ? array_map( 'intval', $params['event_ids'] ) : [];
        $message    = ! empty( $params['message'] ) ? $params['message'] : '';

        if ( count( $event_ids ) > 5 ) {
            return $this->send_response( [
                'success' => false,
                'message' => 'More than 5 events are not allowed at a time.',
            ] );
        }

        $events = $this->sync->generate_pending_sync_events( $connect_id, $event_ids );

        if ( empty( $events ) ) {
            return $this->send_response( [
                'success' => false,
                'message' => 'No events found',
            ] );
        }

        $events       = array_slice( $events, 0, INSTAWP_EVENTS_SYNC_PER_PAGE );
        $total_events = count( $events );

        $sync_quota_response = $this->sync->get_connect_quota_remaining_limit();
        $sync_quota_full     = true;

        if ( ! empty( $sync_quota_response ) ) {
            if ( $sync_quota_response['remaining'] >= $total_events ) {
                $sync_quota_full = false;
            }
        }

        if ( $sync_quota_full ) {
            return $this->send_response( [
                'success' => false,
                'message' => 'Sync Quota Full',
            ] );
        }

        $data     = array( 'total_events' => INSTAWP_EVENTS_SYNC_PER_PAGE );
        $contents = array();

        foreach ( $events as $event ) {
            $event_hash = $event->event_hash;
            $content    = json_decode( $event->details, true );

            if ( empty( $event_hash ) ) {
                $event_hash = Helper::get_random_string( 8 );
                $wpdb->update( INSTAWP_DB_TABLE_EVENT_SYNC_LOGS, array( 'event_hash' => $event_hash ), array( 'id' => $event->id ) );
            }

            $contents[] = array(
                'id'         => $event->id,
                'event_hash' => $event_hash,
                'details'    => InstaWP_Sync_Parser::process_attachments( $content ),
                'event_name' => $event->event_name,
                'event_slug' => $event->event_slug,
                'event_type' => $event->event_type,
                'source_id'  => $event->source_id,
                'user_id'    => $event->user_id,
            );

            if ( ! empty( $event->event_type ) ) {
                $count                    = isset( $data[ $event->event_type ] ) ? $data[ $event->event_type ] : 0;
                $data[ $event->event_type ] = $count + 1;
            }
        }

        $admin_users = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
        $user_id     = is_array( $admin_users ) && isset( $admin_users[0] ) ? (int) $admin_users[0] : 1;

        $packed_data = array(
            'encrypted_content' => wp_json_encode( $contents ),
            'dest_connect_id'   => $connect_id,
            'changes'           => wp_json_encode( $data ),
            'upload_wp_user'    => $user_id,
            'sync_message'      => $message,
            'source_connect_id' => instawp()->connect_id,
            'source_url'        => get_site_url(),
        );

        $response = $this->sync->sync_upload( $packed_data );

        if ( ! isset( $response['success'] ) || $response['success'] !== true ) {
            return $this->send_response( [
                'success' => false,
                'message' => $response['message'],
            ] );
        }

        $sync_id = ! empty( $response['data']['sync_id'] ) ? $response['data']['sync_id'] : '';

        if ( empty( $sync_id ) ) {
            return $this->send_response( [
                'success' => false,
                'message' => 'Sync ID missing!',
            ] );
        }

        $response = $this->sync->update_sync_events_status( $connect_id, $sync_id );
        if ( $response['success'] === true ) {
            $events = [];
            $sync_response = isset( $response['data']['changes']['changes']['sync_response'] ) ? $response['data']['changes']['changes']['sync_response'] : array();

            foreach ( $sync_response as $data ) {
                $count                     = isset( $events[ $data['status'] ] ) ? $events[ $data['status'] ] : 0;
                $events[ $data['status'] ] = $count + 1;
            }

            return $this->send_response( array(
                'success'  => true,
                'events'   => $events,
                'response' => $sync_response,
            ) );
        }

        return $this->send_response( array(
            'success' => false,
            'message' => 'Failed to update sync events status',
            'error'   => $response,
        ) );
    }

    /**
     * Handle response for 2 ways sync delete events
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function delete_events( WP_REST_Request $request ) {

        $response = $this->validate_api_request( $request );
        if ( is_wp_error( $response ) ) {
            return $this->throw_error( $response );
        }

        global $wpdb;

        $params    = $request->get_params();
        $event_ids = ! empty( $params['event_ids'] ) ? array_map( 'intval', $params['event_ids'] ) : [];

        if ( count( $event_ids ) < 1 ) {
            return $this->send_response( [
                'success' => false,
                'message' => 'No event ids provided',
            ] );
        }

        $placeholders = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );

        $wpdb->query(
            $wpdb->prepare( "DELETE FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE id IN ($placeholders)", $event_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        $wpdb->query(
            $wpdb->prepare( "DELETE FROM " . INSTAWP_DB_TABLE_EVENT_SITES . " WHERE event_id IN ($placeholders)", $event_ids ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        );

        return $this->send_response( [
            'success' => true,
            'message' => 'Events deleted successfully',
        ] );
    }

    /**
     * Handle response for 2 way sync events summary
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function events_summary( WP_REST_Request $request ) {

        $response = $this->validate_api_request( $request );
        if ( is_wp_error( $response ) ) {
            return $this->throw_error( $response );
        }

        global $wpdb;

        $params     = $request->get_params();
        $connect_id = ! empty( $params['connect_id'] ) ? intval( $params['connect_id'] ) : 0;

        $staging_site = instawp_get_site_detail_by_connect_id( $connect_id, 'data' );
        $site_created = '1970-01-01 00:00:00';

        if ( ! empty( $staging_site ) && isset( $staging_site['created_at'] ) ) {
            $site_created = date( 'Y-m-d h:i:s', strtotime( $staging_site['created_at'] ) );
        }

        $events = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE `date` >= %s ORDER BY `date` ASC, `id` ASC", $site_created ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        $events = array_map( function( $event ) use ( $connect_id ) {
            $event_row = InstaWP_Sync_DB::get_sync_event_by_id( $connect_id, $event->event_hash );

            if ( $event_row ) {
                $event->status         = ! empty( $event_row->status ) ? $event_row->status : 'pending';
                $event->synced_date    = ! empty( $event_row->date ) ? $event_row->date : $event->date;
                $event->synced_message = ! empty( $event_row->synced_message ) ? $event_row->synced_message : $event->synced_message;

                if ( $event->status === 'completed' ) {
                    $event->log = ! empty( $event_row->log ) ? $event_row->log : '';
                }
            }

            return $event;
        }, $events );
        $events = $this->sync->filter_events( $events );
        $events = array_filter( $events, function ( $event ) {
            return 'pending' === $event->status;
        } );

        return $this->send_response( array(
            'status' => get_option( 'instawp_is_event_syncing', 0 ) ? 'on' : 'off',
            'pending_events' => count( $events ),
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