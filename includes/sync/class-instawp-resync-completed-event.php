<?php

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

class InstaWP_Resync_Completed_Event {

	/**
	 * Post Events
	 * @var array
	 * @since 0.1.0.58
	 */
	private $post_events = array();

	public function __construct() {
		// Post Actions.
		add_action( 'admin_init', array( $this, 'resync_completed_event' ), 10, 3 );
	}

	public function resync_completed_event() {
		if ( empty( $_GET['resync_completed_event'] ) || empty( $_GET['connect_id'] ) || 0 >= intval( $_POST['connect_id'] ) || ! InstaWP_Sync_Helpers::can_sync( 'post' ) ) {
			return;
		}

		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connect_id = intval( $_POST['connect_id'] );

		$event_id = InstaWP_Sync_DB::get_event_id_by_reference( $reference_id );
	}

	/**
	 * Process batch of post events.
	 *
	 * @param int $batch_size Optional. Number of events to process. Default 20.
	 * @return array {
	 *     Array of process results
	 *     @type int    $processed Number of events processed
	 *     @type array  $errors   Array of errors if any
	 * }
	 */
	public static function process_post_events_batch( $batch_size = 20 ) {
		global $wpdb;

		$result = array(
			'processed' => 0,
			'errors'    => array(),
		);

		// Get the table name
		$table = $wpdb->prefix . INSTAWP_DB_TABLE_EVENTS;

		// Get events
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE event_slug IN ('post_new', 'post_change') 
				AND status = %s 
				ORDER BY id ASC 
				LIMIT %d",
				'pending',
				absint( $batch_size )
			)
		);

		if ( empty( $events ) ) {
			return $result;
		}

		// Store event IDs for batch update
		$processed_ids = array();

		foreach ( $events as $event ) {
			try {
				// Process event
				$success = self::process_single_event( $event );

				if ( $success ) {
					$processed_ids[] = $event->id;
					$result['processed']++;
				} else {
					$result['errors'][] = sprintf(
						'Failed to process event ID: %d, Slug: %s',
						$event->id,
						$event->event_slug
					);
				}
			} catch ( Exception $e ) {
				$result['errors'][] = sprintf(
					'Error processing event ID: %d, Error: %s',
					$event->id,
					$e->getMessage()
				);
			}
		}

		// Batch update processed events
		if ( ! empty( $processed_ids ) ) {
			$ids = implode( ',', array_map( 'absint', $processed_ids ) );
			$wpdb->query(
				"UPDATE {$table} 
				SET status = 'completed', 
					processed_at = '" . current_time( 'mysql' ) . "' 
				WHERE id IN ({$ids})"
			);
		}

		return $result;
	}

	/**
	 * Process single event.
	 *
	 * @param object $event Event object from database.
	 * @return bool True on success, false on failure.
	 */
	private static function process_single_event( $event ) {
		if ( empty( $event->event_data ) ) {
			return false;
		}

		$event_data = maybe_unserialize( $event->event_data );
		if ( empty( $event_data ) || ! is_array( $event_data ) ) {
			return false;
		}

		switch ( $event->event_slug ) {
			case 'post_new':
				return self::process_new_post( $event_data );

			case 'post_change':
				return self::process_post_change( $event_data );

			default:
				return false;
		}
	}

}

new InstaWP_Resync_Completed_Event();
