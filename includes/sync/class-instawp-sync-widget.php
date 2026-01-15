<?php
/**
 * Widget Sync Class
 *
 * Handles synchronization of widgets and sidebars between connected sites.
 *
 * @link       https://instawp.com/
 * @since      0.1.2.3
 * @package    instawp
 * @subpackage instawp/includes/sync
 */

use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Widget {

	/**
	 * Flag to prevent recursive tracking during sync
	 *
	 * @var bool
	 */
	private $is_syncing = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Track sidebars_widgets changes
		add_action( 'update_option_sidebars_widgets', array( $this, 'track_sidebars_widgets' ), 10, 2 );

		// Track individual widget option changes
		add_action( 'update_option', array( $this, 'track_widget_option_update' ), 10, 3 );
		add_action( 'add_option', array( $this, 'track_widget_option_add' ), 10, 2 );
		add_action( 'delete_option', array( $this, 'track_widget_option_delete' ), 10, 1 );

		// Process sync events
		add_filter( 'instawp/filters/2waysync/process_event', array( $this, 'parse_event' ), 10, 2 );
	}

	/**
	 * Check if option is a widget option
	 *
	 * @param string $option_name Option name.
	 * @return bool
	 */
	private function is_widget_option( $option_name ) {
		return strpos( $option_name, 'widget_' ) === 0;
	}

	/**
	 * Check if widget sync can proceed
	 *
	 * @return bool
	 */
	private function can_sync() {
		if ( $this->is_syncing ) {
			return false;
		}

		return InstaWP_Sync_Helpers::can_sync( 'widget' );
	}

	/**
	 * Generate a reference ID for widget options
	 *
	 * @param string $option_name Option name.
	 * @return string
	 */
	private function get_widget_reference_id( $option_name ) {
		return 'widget_' . md5( $option_name );
	}

	/**
	 * Track sidebars_widgets option changes
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 */
	public function track_sidebars_widgets( $old_value, $new_value ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		// Don't track if values are the same
		if ( $old_value === $new_value ) {
			return;
		}

		// Skip if this is just array_version update
		if ( is_array( $old_value ) && is_array( $new_value ) ) {
			$old_copy = $old_value;
			$new_copy = $new_value;
			unset( $old_copy['array_version'], $new_copy['array_version'] );
			if ( $old_copy === $new_copy ) {
				return;
			}
		}

		$reference_id = $this->get_widget_reference_id( 'sidebars_widgets' );
		$event_name   = __( 'Sidebars widgets updated', 'instawp-connect' );

		$data = array(
			'option_name' => 'sidebars_widgets',
			'old_value'   => $old_value,
			'new_value'   => $new_value,
		);

		InstaWP_Sync_DB::insert_update_event(
			$event_name,
			'sidebars_update',
			'widget',
			$reference_id,
			__( 'Sidebar Widgets', 'instawp-connect' ),
			$data
		);
	}

	/**
	 * Track widget option updates
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $old_value   Old value.
	 * @param mixed  $new_value   New value.
	 */
	public function track_widget_option_update( $option_name, $old_value, $new_value ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		// Only track widget_* options
		if ( ! $this->is_widget_option( $option_name ) ) {
			return;
		}

		// Don't track if values are the same
		if ( $old_value === $new_value ) {
			return;
		}

		$reference_id = $this->get_widget_reference_id( $option_name );
		$widget_name  = str_replace( 'widget_', '', $option_name );
		$event_name   = sprintf( __( 'Widget "%s" updated', 'instawp-connect' ), $widget_name );

		$data = array(
			'option_name' => $option_name,
			'old_value'   => $old_value,
			'new_value'   => $new_value,
		);

		InstaWP_Sync_DB::insert_update_event(
			$event_name,
			'widget_update',
			'widget',
			$reference_id,
			sprintf( __( 'Widget: %s', 'instawp-connect' ), ucfirst( $widget_name ) ),
			$data
		);
	}

	/**
	 * Track new widget options
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Option value.
	 */
	public function track_widget_option_add( $option_name, $value ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		// Only track widget_* options
		if ( ! $this->is_widget_option( $option_name ) ) {
			return;
		}

		$reference_id = $this->get_widget_reference_id( $option_name );
		$widget_name  = str_replace( 'widget_', '', $option_name );
		$event_name   = sprintf( __( 'Widget "%s" added', 'instawp-connect' ), $widget_name );

		$data = array(
			'option_name' => $option_name,
			'old_value'   => null,
			'new_value'   => $value,
		);

		InstaWP_Sync_DB::insert_update_event(
			$event_name,
			'widget_add',
			'widget',
			$reference_id,
			sprintf( __( 'Widget: %s', 'instawp-connect' ), ucfirst( $widget_name ) ),
			$data
		);
	}

	/**
	 * Track deleted widget options
	 *
	 * @param string $option_name Option name.
	 */
	public function track_widget_option_delete( $option_name ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		// Only track widget_* options
		if ( ! $this->is_widget_option( $option_name ) ) {
			return;
		}

		$old_value    = get_option( $option_name );
		$reference_id = $this->get_widget_reference_id( $option_name );
		$widget_name  = str_replace( 'widget_', '', $option_name );
		$event_name   = sprintf( __( 'Widget "%s" deleted', 'instawp-connect' ), $widget_name );

		$data = array(
			'option_name' => $option_name,
			'old_value'   => $old_value,
			'new_value'   => null,
		);

		InstaWP_Sync_DB::insert_update_event(
			$event_name,
			'widget_delete',
			'widget',
			$reference_id,
			sprintf( __( 'Widget: %s', 'instawp-connect' ), ucfirst( $widget_name ) ),
			$data
		);
	}

	/**
	 * Parse and process sync events
	 *
	 * @param array  $response Current response.
	 * @param object $v        Event data.
	 * @return array
	 */
	public function parse_event( $response, $v ) {
		if ( $v->event_type !== 'widget' ) {
			return $response;
		}

		$data = InstaWP_Sync_Helpers::object_to_array( $v->details );

		// Set syncing flag to prevent recursive tracking
		$this->is_syncing = true;

		$option_name = isset( $data['option_name'] ) ? $data['option_name'] : '';
		$new_value   = isset( $data['new_value'] ) ? $data['new_value'] : null;
		$logs        = array();

		try {
			switch ( $v->event_slug ) {
				case 'widget_add':
				case 'widget_update':
				case 'sidebars_update':
					if ( ! empty( $option_name ) ) {
						Option::update_option( $option_name, $new_value );
					}
					break;

				case 'widget_delete':
					if ( ! empty( $option_name ) ) {
						delete_option( $option_name );
					}
					break;

				default:
					$logs[ $v->id ] = sprintf( 'Unknown widget event slug: %s', $v->event_slug );
					break;
			}
		} catch ( Exception $e ) {
			$logs[ $v->id ] = sprintf( 'Widget sync error: %s', $e->getMessage() );
		}

		// Reset syncing flag
		$this->is_syncing = false;

		return InstaWP_Sync_Helpers::sync_response( $v, $logs );
	}
}

new InstaWP_Sync_Widget();
