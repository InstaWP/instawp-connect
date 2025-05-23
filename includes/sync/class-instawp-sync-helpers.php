<?php
/**
 *
 * This file is used for change event traking
 *
 * @link       https://instawp.com/
 * @since      1.0
 * @package    instaWP
 * @subpackage instaWP/admin
 */
/**
 * This file is used for change event tracking
 *
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/admin
 * @author     instawp team
 */

use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || die;

class InstaWP_Sync_Helpers {

	/**
	 * Sync parser log option name
	 */
	public static $iwp_sync_parser_log = 'iwp_sync_parser_log';

	/**
	 * get post type singular name
	 *
	 * @param $post_type
	 *
	 * @return string
	 */
	public static function get_post_type_name( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		if ( ! empty( $post_type_object ) ) {
			return $post_type_object->labels->singular_name;
		}

		return '';
	}

	/**
	 * Get or set failed direct download attachment events
	 * 
	 * @param array $event_ids
	 * 
	 * @return array
	 */
	public static function failed_direct_process_media_events( $event_ids = array() ) {
		// Get failed direct download attachment events
		$failed_events = get_option( 'iwp_failed_direct_process_media_events' );
		$failed_events = empty( $failed_events ) ? array() : $failed_events;

		if ( ! empty( $event_ids['add'] ) || ( ! empty( $failed_events ) && ! empty( $event_ids['remove'] ) ) ) {
			// Add event ids in failed direct download attachment events
			if ( ! empty( $event_ids['add'] ) && is_array( $event_ids['add'] ) ) {
				$event_ids['add'] = array_map( 'intval', $event_ids['add'] );
				$failed_events = array_merge( $failed_events, $event_ids['add'] );
			}

			// Remove event ids from failed direct download attachment events
			if ( ! empty( $event_ids['remove'] ) && is_array( $event_ids['remove'] ) ) {
				$event_ids['remove'] = array_map( 'intval', $event_ids['remove'] );
				$failed_events = array_diff( $failed_events, $event_ids['remove'] );
			}
			$failed_events = array_unique( $failed_events );
			update_option( 'iwp_failed_direct_process_media_events', $failed_events );
		}
		
		return $failed_events;
	}

	/**
	 * Get or set current sync parser log
	 *
	 * @param string $message current sync parser message
	 * @param boolean $error has error
	 *
	 * @return array sync parser log
	 */
	public static function get_set_sync_parser_log( $message = '', $error = false ) {
		// Current event ID
		global $iwp_sync_process_event_id;
		if ( empty( $iwp_sync_process_event_id ) ) {
			return array();
		}

		// Get sync parser log
		$log = get_option( self::$iwp_sync_parser_log );
		$log = empty( $log ) ? array() : $log;

		if ( empty( $log[ $iwp_sync_process_event_id ] ) ) {
			$log[ $iwp_sync_process_event_id ] = array(
				'message' => array(),
			);
		}

		if ( ! empty( $message ) ) {
			$log[ $iwp_sync_process_event_id ]['message'][] = $message;
			if ( true === $error ) {
				$log[ $iwp_sync_process_event_id ]['error'] = true;
				$log[ $iwp_sync_process_event_id ]['error_message'] = $message;
				error_log( $message );
			}

			update_option( self::$iwp_sync_parser_log, $log );
		}

		return $log;
	} 

	/*
     * Update post metas
     */
	public static function get_post_reference_id( $post_id ) {
		$reference_id = get_post_meta( $post_id, 'instawp_event_sync_reference_id', true );

		return ! empty( $reference_id ) ? $reference_id : self::set_post_reference_id( $post_id );
	}

	/**
	 * Retrieve the post type name, post name and its associated reference ID.
	 *
	 * @param int $post_id The ID of the post to retrieve.
	 *
	 * @return array An associative array containing the post type name, post name and its reference ID.
	 */
	public static function get_post_type_name_reference_id( $post_id ) {
		if ( empty( $post_id ) || ! is_numeric( $post_id ) || 0 >= intval( $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return false;
		}

		return array(
			'post_type'    => $post->post_type,
			'post_name'    => $post->post_name,
			'reference_id' => self::get_post_reference_id( $post->ID ),
		);
	}

	/**
	 * Retrieve the post data, post meta and its associated reference ID.
	 *
	 * @param int $post_id The ID of the post to retrieve.
	 *
	 * @return array An associative array containing the post object and its reference ID.
	 */
	public static function get_post_meta_reference_id( $post_id ) {
		if ( empty( $post_id ) || ! is_numeric( $post_id ) || 0 >= intval( $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return false;
		}

		return array(
			'post_id'      => $post->ID,
			'post_data'    => $post,
			'meta_data'    => get_post_meta( $post->ID ),
			'reference_id' => self::get_post_reference_id( $post->ID ),
		);
	}

	/*
	 * Update post metas
	 */
	public static function set_post_reference_id( $post_id, $reference_id = '' ) {
		$reference_id = ! empty( $reference_id ) ? $reference_id : Helper::get_random_string( 8 );
		update_post_meta( $post_id, 'instawp_event_sync_reference_id', $reference_id );

		return $reference_id;
	}

	/*
	 * Get user metas
	 */
	public static function get_term_reference_id( $term_id ) {
		$reference_id = get_term_meta( $term_id, 'instawp_event_term_sync_reference_id', true );

		return ! empty( $reference_id ) ? $reference_id : self::set_term_reference_id( $term_id );
	}

	/**
	 * Retrieve the term taxonomy, slug and its associated reference ID.
	 *
	 * @param int $term_id The ID of the term to retrieve.
	 *
	 * @return array An associative array containing the term taxonomy, slug and its reference ID.
	 */
	public static function get_term_taxonomy_slug_reference_id( $term_id, $taxonomy = '' ) {
		if ( empty( $term_id ) || ! is_numeric( $term_id ) || 0 >= intval( $term_id ) ) {
			return false;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( empty( $term ) || is_wp_error( $term ) ) {
			return false;
		}

		return array(
			'taxonomy'     => $term->taxonomy,
			'slug'         => $term->slug,
			'reference_id' => self::get_term_reference_id( $term->term_id ),
		);
	}

	/*
	 * Update user metas
	 */
	public static function set_term_reference_id( $term_id, $reference_id = '' ) {
		$reference_id = ! empty( $reference_id ) ? $reference_id : Helper::get_random_string( 8 );
		update_term_meta( $term_id, 'instawp_event_term_sync_reference_id', $reference_id );

		return $reference_id;
	}

	/*
     * Get user metas
     */
	public static function get_user_reference_id( $user_id ) {
		$reference_id = get_user_meta( $user_id, 'instawp_event_user_sync_reference_id', true );

		return ! empty( $reference_id ) ? $reference_id : self::set_user_reference_id( $user_id );
	}

	/*
	 * Update user metas
	 */
	public static function set_user_reference_id( $user_id, $reference_id = '' ) {
		$reference_id = ! empty( $reference_id ) ? $reference_id : Helper::get_random_string( 8 );
		update_user_meta( $user_id, 'instawp_event_user_sync_reference_id', $reference_id );

		return $reference_id;
	}

	public static function can_sync( $module ) {
		$syncing_status = get_option( 'instawp_is_event_syncing', 0 );
		$can_sync       = ( intval( $syncing_status ) === 1 ) && self::is_enabled( $module );

		return (bool) apply_filters( 'instawp/filters/can_two_way_sync', $can_sync, $module );
	}

	public static function sync_response( $data, $log_data = array(), $args = array() ) {
		// Get global log
		$log  = self::get_set_sync_parser_log();
		$log = empty( $log[ $data->id ] ) ? array() : $log[ $data->id ];
		// Check error
		$error = ( isset( $log['error'] ) && true === $log['error'] ) ? true : false;
		// Check message
		if ( ! empty( $log['message'] ) ) {
			// Flush log data
			update_option( self::$iwp_sync_parser_log, array() );
			// Encode message
			$log['message'] = json_encode( $log['message'] );
			if ( ! empty( $log_data[ $data->id ] ) ) {
				if ( is_string( $log_data[ $data->id ] ) ) {
					$log_data[ $data->id ] .= "\n" . $log['message'];
				} elseif ( is_array( $log_data[ $data->id ] ) ) {
					$log_data[ $data->id ][] = $log['message'];
				}
			} else {
				$log_data[ $data->id ] = $log['message'];
			} 
		}

		$data = array(
			'data' => wp_parse_args( $args, array(
				'id'      => $data->id,
				'hash'    => $data->event_hash,
				'status'  => $error ? 'pending' : 'completed',
				'message' => $error ? $log['error_message'] : 'Sync successfully.',
			) ),
		);

		if ( ! empty( $log_data ) ) {
			$data['log_data'] = $log_data;
		}

		return $data;
	}

	/**
	 * Verify the sync feature is enabled or not.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public static function is_enabled( $key ) {
        $field         = 'instawp_sync_' . $key;
        $fields        = InstaWP_Setting::get_plugin_settings();
        $sync_settings = wp_list_pluck( $fields['sync_events']['fields'], 'default', 'id' );
		$default       = isset( $sync_settings[ $field ] ) ? $sync_settings[ $field ] : 'off';
		$value         = Option::get_option( $field, $default );
		$value         = empty( $value ) ? $default : $value;

		return 'on' === $value;
	}

	public static function object_to_array( $object_or_array ) {
		if ( is_object( $object_or_array ) || is_array( $object_or_array ) ) {
			$result = array();
			foreach ( $object_or_array as $key => $value ) {
				$result[ $key ] = self::object_to_array( $value );
			}

			return $result;
		}

		return $object_or_array;
	}

	/**
	 * get post type singular name
	 *
	 * @param $post_name
	 * @param $post_type
	 */
	public static function get_post_by_name( $post_name, $post_type ) {
		global $wpdb;

		$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type= %s ", $post_name, $post_type ) );
		if ( $post ) {
			return get_post( $post );
		}

		return null;
	}

	/**
	 * Flattens the post meta array by replacing single-element arrays with their sole element.
	 *
	 * Iterates through the provided meta array and for each key, if the value is an array with
	 * exactly one element, it replaces the array with that single element.
	 *
	 * @param array $meta The meta data array where keys are meta keys and values are meta values,
	 *                    which can be single-element arrays.
	 *                    
	 * @return array The flattened meta array with single-element arrays replaced by their element.
	 */
	public static function flat_post_meta( $meta = array() ) {
		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return array();
		}
		foreach ( $meta as $key => $value ) {
			if ( is_array( $value ) && 1 === count( $value ) && isset( $value[0] ) ) {
				$meta[ $key ] = $value[0];
			}
		}
		return $meta;
	}

	public static function get_post_by_reference( $post_type, $reference_id, $post_name ) {
		$post = get_posts( array(
			'post_type'   => $post_type,
			'meta_key'    => 'instawp_event_sync_reference_id',
			'meta_value'  => $reference_id,
			'post_status' => 'any',
			'nopaging'    => true,
		) );

		return ! empty( $post ) ? reset( $post ) : self::get_post_by_name( $post_name, $post_type );
	}

	public static function get_term_by_reference( $taxonomy, $reference_id, $slug ) {
		$terms = get_terms( array(
			'hide_empty' => false,
			'meta_key'   => 'instawp_event_term_sync_reference_id',
			'meta_value' => $reference_id,
			'taxonomy'   => $taxonomy,
		) );

		return ! empty( $terms ) ? reset( $terms ) : get_term_by( 'slug', $slug, $taxonomy );
	}

	/**
	 * Get taxonomies items
	 */
	public static function get_taxonomies_items( $post_id ) {
		$taxonomies = get_post_taxonomies( $post_id );
		$items      = array();

		if ( ! empty ( $taxonomies ) && is_array( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy_items = get_the_terms( $post_id, $taxonomy );

				if ( ! empty( $taxonomy_items ) && is_array( $taxonomy_items ) ) {
					foreach ( $taxonomy_items as $k => $item ) {
						$items[ $item->taxonomy ][ $k ] = ( array ) $item;

						if ( $item->parent > 0 ) {
							$items[ $item->taxonomy ][ $k ]['cat_parent'] = ( array ) get_term( $item->parent, $taxonomy );
						}
					}
				}
			}
		}

		return $items;
	}

	public static function reset_post_terms( $post_id ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id = %d",
				$post_id
			)
		);
	}

	public static function is_built_with_elementor( $post_id ) {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return false;
		}

        $document = \Elementor\Plugin::$instance->documents->get( $post_id );
        if ( ! $document || ! method_exists( $document, 'is_built_with_elementor' ) || ! $document->is_built_with_elementor() ) {
            return false;
        }

		return true;
	}

	/**
	 * Prepare post, term and user ids
	 *
	 * @param array $data
	 * @return array
	 */
	public static function prepare_post_term_user_ids( $data = array() ) {
		foreach ( $data as $item_type => $item_ids ) {
			if ( ! in_array( $item_type, array( 'post_ids', 'term_ids', 'user_ids' ) ) ) {
				continue;
			}
			foreach ( $item_ids as $item_id => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( empty( $item['reference_id'] ) ) {
					unset( $data[ $item_type ][ $item_id ] );
					continue;
				}
				$is_set_id = false; // flag to check if id is set
				if ( $item_type === 'post_ids' ) {
					if ( is_array( $item ) ) {
						if ( ! empty( $item['post_type'] ) && isset( $item['post_name'] ) ) {
							$post = self::get_post_by_reference( $item['post_type'], $item['reference_id'], $item['post_name'] );
							if ( ! empty( $post ) ) {
								$data[ $item_type ][ $item_id ] = $post->ID;
								$is_set_id = true;
							}
						} 
					}
				} elseif ( $item_type === 'term_ids' ) {
					if ( ! empty( $item['taxonomy'] ) && isset( $item['slug'] ) ) {
						$term = self::get_term_by_reference( $item['taxonomy'], $item['reference_id'], $item['slug'] );
						if ( ! empty( $term ) ) {
							$data[ $item_type ][ $item_id ] = $term->term_id;
							$is_set_id = true;
						}
					}
				} elseif ( $item_type === 'user_ids' ) {
					if ( ! empty( $item['user_email'] ) ) {
						$user = get_user_by( 'email', $item['user_email'] );
						if ( ! empty( $user ) ) {
							$data[ $item_type ][ $item_id ] = $user->ID;
							$is_set_id = true;
						}
					}
				}

				if ( ! $is_set_id ) {
					// unset if id is not set
					unset( $data[ $item_type ][ $item_id ] );
				}
			}
		}

		return $data;
	}
}