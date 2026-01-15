<?php

use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Option {

	public $options_meta_name = 'instawp_2waysync_options_metadata';

	/**
	 * Flag to prevent recursive tracking during sync
	 *
	 * @var bool
	 */
	private $is_syncing = false;

	public function __construct() {
		// Update option
		add_action( 'added_option', array( $this, 'added_option' ), 10, 2 );
		add_action( 'updated_option', array( $this, 'updated_option' ), 10, 3 );
		add_action( 'deleted_option', array( $this, 'deleted_option' ) );
		add_action( 'init', array( $this, 'purge_instawp_option_cache' ) );

		// Track sidebars_widgets changes with special handling
		add_action( 'update_option_sidebars_widgets', array( $this, 'track_sidebars_widgets' ), 10, 2 );

		// Process event
		add_filter( 'instawp/filters/2waysync/process_event', array( $this, 'parse_event' ), 10, 2 );
	}

	public function added_option( $option, $value ) {
		if ( $this->is_syncing ) {
			return;
		}

		$sync_type = $this->is_widget_option( $option ) ? 'widget' : 'option';
		if ( ! InstaWP_Sync_Helpers::can_sync( $sync_type ) ) {
			return;
		}

		if ( ! $this->is_protected_option( $option ) ) {
			if ( $this->is_widget_option( $option ) ) {
				$this->track_widget_option_add( $option, $value );
			} else {
				$data = array(
					'name'  => $option,
					'value' => maybe_serialize( $value ),
				);
				InstaWP_Sync_DB::insert_update_event( __( 'Option added', 'instawp-connect' ), 'add_option', 'option', $option, ucfirst( str_replace( array( '-', '_' ), ' ', $option ) ), $data );
			}
		}
	}

	public function updated_option( $option, $old_value, $value ) {
		if ( $this->is_syncing ) {
			return;
		}

		$sync_type = $this->is_widget_option( $option ) ? 'widget' : 'option';
		if ( ! InstaWP_Sync_Helpers::can_sync( $sync_type ) ) {
			return;
		}

		if ( ! $this->is_protected_option( $option ) && $this->has_update( $option ) ) {
			if ( $this->is_widget_option( $option ) ) {
				$this->track_widget_option_update( $option, $old_value, $value );
			} else {
				$data = array(
					'name'  => $option,
					'value' => maybe_serialize( $value ),
				);
				InstaWP_Sync_DB::insert_update_event( __( 'Option updated', 'instawp-connect' ), 'update_option', 'option', $option, ucfirst( str_replace( array( '-', '_' ), ' ', $option ) ), $data );
			}
		}
	}

	public function purge_instawp_option_cache() {
		if ( ! isset( $_GET['instawp-cache-cleared'] ) ) {
			return;
		}

		$cache_cleared = get_transient( 'instawp_cache_purged' );
		if ( $cache_cleared ) {
			update_option( $this->options_meta_name, array() );
		}
	}

	/**
	 * Check if some specific option like user roles has been updated in last 24 hours
	 *
	 * @param string $option Option name
	 *
	 * @return bool
	 */
	public function has_update( $option ) {
		global $wpdb;
		$user_role_option = $wpdb->prefix . 'user_roles';
		if ( $option === $user_role_option ) {
			$option_data = get_option( $this->options_meta_name );
			$option_data = empty( $option_data ) ? array() : $option_data;
			$time        = time();
			// Check if user roles has been updated in last 24 hours
			if ( ! empty( $option_data[ $option ] ) && ( $time - intval( $option_data[ $option ]['last_update'] ) ) < 86400 ) {
				return false;
			}
			$option_data[ $option ] = array(
				'last_update' => $time,
			);
			update_option( $this->options_meta_name, $option_data );
		}

		return true;
	}

	public function deleted_option( $option ) {
		if ( $this->is_syncing ) {
			return;
		}

		$sync_type = $this->is_widget_option( $option ) ? 'widget' : 'option';
		if ( ! InstaWP_Sync_Helpers::can_sync( $sync_type ) ) {
			return;
		}

		if ( ! $this->is_protected_option( $option ) ) {
			if ( $this->is_widget_option( $option ) ) {
				$this->track_widget_option_delete( $option );
			} else {
				InstaWP_Sync_DB::insert_update_event( __( 'Option deleted', 'instawp-connect' ), 'delete_option', 'option', $option, ucfirst( str_replace( array( '-', '_' ), ' ', $option ) ), $option );
			}
		}
	}

	public function parse_event( $response, $v ) {
		if ( ! in_array( $v->event_type, array( 'option', 'widget' ), true ) ) {
			return $response;
		}

		$data = InstaWP_Sync_Helpers::object_to_array( $v->details );

		// Set syncing flag to prevent recursive tracking
		$this->is_syncing = true;

		$logs = array();

		try {
			// Handle widget events
			if ( $v->event_type === 'widget' ) {
				$option_name = isset( $data['option_name'] ) ? $data['option_name'] : '';
				$new_value   = isset( $data['new_value'] ) ? $data['new_value'] : null;

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
			} else {
				// Handle regular option events
				// add or update option
				if ( in_array( $v->event_slug, array( 'add_option', 'update_option' ), true ) ) {
					Option::update_option( $data['name'], maybe_unserialize( $data['value'] ) );
				}

				// delete option
				if ( $v->event_slug === 'delete_option' ) {
					delete_option( $data );
				}
			}
		} catch ( Exception $e ) {
			$logs[ $v->id ] = sprintf( '%s sync error: %s', ucfirst( $v->event_type ), $e->getMessage() );
		}

		// Reset syncing flag
		$this->is_syncing = false;

		return InstaWP_Sync_Helpers::sync_response( $v, $logs );
	}

	private function is_protected_option( $option ) {
		$excluded_options = array( 'cron', 'instawp_api_options', 'siteurl', 'home', 'blog_public', 'permalink_structure', 'rewrite_rules', 'recently_activated', 'active_plugins', 'theme_switched', 'theme_switch_menu_locations', 'recovery_mode_email_last_sent', 'recovery_keys', 'auto_updater.lock', 'elementor_version', 'elementor_log', 'iwp_connect_helper_error_log', 'iwp_failed_direct_process_media_events', 'iwp_sync_processed_media_ids', 'iwp_sync_config_data', 'iwp_mig_helper_error_log', 'instawp_last_heartbeat_data', $this->options_meta_name );

		if ( in_array( $option, $excluded_options, true )
			|| strpos( $option, '_transient' ) !== false
			|| strpos( $option, 'instawp' ) !== false
			|| strpos( $option, 'action_scheduler' ) !== false
		) {
			return true;
		}

		return false;
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
		if ( $this->is_syncing ) {
			return;
		}

		if ( ! InstaWP_Sync_Helpers::can_sync( 'widget' ) ) {
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
	private function track_widget_option_update( $option_name, $old_value, $new_value ) {
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
	private function track_widget_option_add( $option_name, $value ) {
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
	private function track_widget_option_delete( $option_name ) {
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
}

new InstaWP_Sync_Option();
