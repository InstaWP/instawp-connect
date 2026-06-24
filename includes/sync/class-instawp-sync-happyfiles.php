<?php
/**
 * HappyFiles Pro 2-Way Sync Module
 *
 * Handles synchronization of HappyFiles folder operations including
 * folder CRUD, file-to-folder assignments, and folder metadata
 * (position, color) between connected WordPress sites.
 *
 * @link       https://instawp.com/
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/includes/sync
 */

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_HappyFiles {

	public function __construct() {
		// Outbound: capture folder events.
		add_action( 'created_term', array( $this, 'folder_created' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'folder_updated' ), 10, 3 );
		add_action( 'pre_delete_term', array( $this, 'folder_deleted' ), 10, 2 );
		add_action( 'set_object_terms', array( $this, 'assignment_changed' ), 10, 6 );
		add_action( 'updated_term_meta', array( $this, 'folder_meta_updated' ), 10, 4 );
		add_action( 'added_term_meta', array( $this, 'folder_meta_updated' ), 10, 4 );
		add_action( 'deleted_term_meta', array( $this, 'folder_meta_deleted' ), 10, 4 );

		// Inbound: process incoming sync events.
		add_filter( 'instawp/filters/2waysync/process_event', array( $this, 'parse_event' ), 10, 2 );

		// Prevent term sync from double-capturing HappyFiles taxonomies.
		add_filter( 'instawp/filters/2waysync/restricted_taxonomies', array( $this, 'restrict_taxonomies' ) );
	}

	/**
	 * Check if a taxonomy belongs to HappyFiles.
	 *
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return bool
	 */
	private function is_happyfiles_taxonomy( $taxonomy ) {
		return 'happyfiles_category' === $taxonomy || 0 === strpos( $taxonomy, 'hf_cat_' );
	}

	/**
	 * Check if HappyFiles sync can proceed.
	 *
	 * @return bool
	 */
	private function can_sync() {
		$syncing_status = get_option( 'instawp_is_event_syncing', 0 );

		return ( intval( $syncing_status ) === 1 ) && taxonomy_exists( 'happyfiles_category' );
	}

	/**
	 * Get full folder details including term data, meta, and parent info.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return array
	 */
	private function get_folder_details( $term_id, $taxonomy ) {
		$term_details = (array) get_term( $term_id, $taxonomy );
		$term_meta    = get_term_meta( $term_id );

		$term_details['term_meta'] = array(
			'data'  => $term_meta,
			'media' => array(),
		);

		if ( ! empty( $term_details['parent'] ) ) {
			$term_details['parent_details'] = array(
				'data'      => (array) get_term( $term_details['parent'], $taxonomy ),
				'source_id' => InstaWP_Sync_Helpers::get_term_reference_id( $term_details['parent'] ),
			);
		}

		return $term_details;
	}

	/**
	 * Handle folder creation event.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id   Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return void
	 */
	public function folder_created( $term_id, $tt_id, $taxonomy ) {
		if ( ! $this->is_happyfiles_taxonomy( $taxonomy ) || ! $this->can_sync() ) {
			return;
		}

		$source_id    = InstaWP_Sync_Helpers::get_term_reference_id( $term_id );
		$term_details = $this->get_folder_details( $term_id, $taxonomy );

		InstaWP_Sync_DB::insert_update_event(
			__( 'HappyFiles folder created', 'instawp-connect' ),
			'happyfiles_folder_created',
			'happyfiles',
			$source_id,
			$term_details['name'],
			$term_details
		);
	}

	/**
	 * Handle folder update event.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id   Term taxonomy ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return void
	 */
	public function folder_updated( $term_id, $tt_id, $taxonomy ) {
		if ( ! $this->is_happyfiles_taxonomy( $taxonomy ) || ! $this->can_sync() ) {
			return;
		}

		$source_id    = InstaWP_Sync_Helpers::get_term_reference_id( $term_id );
		$term_details = $this->get_folder_details( $term_id, $taxonomy );

		InstaWP_Sync_DB::insert_update_event(
			__( 'HappyFiles folder updated', 'instawp-connect' ),
			'happyfiles_folder_updated',
			'happyfiles',
			$source_id,
			$term_details['name'],
			$term_details
		);
	}

	/**
	 * Handle folder deletion event.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return void
	 */
	public function folder_deleted( $term_id, $taxonomy ) {
		if ( ! $this->is_happyfiles_taxonomy( $taxonomy ) || ! $this->can_sync() ) {
			return;
		}

		$term_details = (array) get_term( $term_id, $taxonomy );
		$source_id    = InstaWP_Sync_Helpers::get_term_reference_id( $term_id );

		InstaWP_Sync_DB::insert_update_event(
			__( 'HappyFiles folder deleted', 'instawp-connect' ),
			'happyfiles_folder_deleted',
			'happyfiles',
			$source_id,
			$term_details['name'],
			$term_details
		);
	}

	/**
	 * Handle file-to-folder assignment change.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Array of term IDs.
	 * @param array  $tt_ids     Array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy name.
	 * @param bool   $append     Whether to append terms.
	 * @param array  $old_tt_ids Old term taxonomy IDs.
	 *
	 * @return void
	 */
	public function assignment_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( ! $this->is_happyfiles_taxonomy( $taxonomy ) || ! $this->can_sync() ) {
			return;
		}

		$post = get_post( $object_id );
		if ( empty( $post ) ) {
			return;
		}

		$post_reference_id  = InstaWP_Sync_Helpers::get_post_reference_id( $object_id );
		$term_reference_ids = array();

		foreach ( $terms as $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
				$term_reference_ids[] = array(
					'term_id'      => $term_id,
					'reference_id' => InstaWP_Sync_Helpers::get_term_reference_id( $term_id ),
					'slug'         => $term->slug,
				);
			}
		}

		$details = array(
			'post_id'            => $object_id,
			'post_type'          => $post->post_type,
			'post_name'          => $post->post_name,
			'taxonomy'           => $taxonomy,
			'term_reference_ids' => $term_reference_ids,
			'post_reference_id'  => $post_reference_id,
		);

		InstaWP_Sync_DB::insert_update_event(
			__( 'HappyFiles item assignment changed', 'instawp-connect' ),
			'happyfiles_assignment_changed',
			'happyfiles',
			$post_reference_id,
			$post->post_name,
			$details
		);
	}

	/**
	 * Handle folder meta update (position, color).
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $term_id    Term ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @return void
	 */
	public function folder_meta_updated( $meta_id, $term_id, $meta_key, $meta_value ) {
		if ( ! in_array( $meta_key, array( 'happyfiles_position', 'happyfiles_folder_color' ), true ) ) {
			return;
		}

		$term = get_term( $term_id );
		if ( empty( $term ) || is_wp_error( $term ) || ! $this->is_happyfiles_taxonomy( $term->taxonomy ) || ! $this->can_sync() ) {
			return;
		}

		$term_reference_id = InstaWP_Sync_Helpers::get_term_reference_id( $term_id );

		$details = array(
			'term_id'           => $term_id,
			'taxonomy'          => $term->taxonomy,
			'meta_key'          => $meta_key,
			'meta_value'        => $meta_value,
			'term_reference_id' => $term_reference_id,
		);

		InstaWP_Sync_DB::insert_update_event(
			__( 'HappyFiles folder meta updated', 'instawp-connect' ),
			'happyfiles_folder_meta_updated',
			'happyfiles',
			$term_reference_id,
			$term->name,
			$details
		);
	}

	/**
	 * Handle folder meta deletion (color cleared).
	 *
	 * @param array  $meta_ids   Meta IDs.
	 * @param int    $term_id    Term ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @return void
	 */
	public function folder_meta_deleted( $meta_ids, $term_id, $meta_key, $meta_value ) {
		if ( ! in_array( $meta_key, array( 'happyfiles_position', 'happyfiles_folder_color' ), true ) ) {
			return;
		}

		$term = get_term( $term_id );
		if ( empty( $term ) || is_wp_error( $term ) || ! $this->is_happyfiles_taxonomy( $term->taxonomy ) || ! $this->can_sync() ) {
			return;
		}

		$term_reference_id = InstaWP_Sync_Helpers::get_term_reference_id( $term_id );

		$details = array(
			'term_id'           => $term_id,
			'taxonomy'          => $term->taxonomy,
			'meta_key'          => $meta_key,
			'term_reference_id' => $term_reference_id,
		);

		InstaWP_Sync_DB::insert_update_event(
			__( 'HappyFiles folder meta deleted', 'instawp-connect' ),
			'happyfiles_folder_meta_deleted',
			'happyfiles',
			$term_reference_id,
			$term->name,
			$details
		);
	}

	/**
	 * Prevent term sync from double-capturing HappyFiles taxonomies.
	 *
	 * @param array $taxonomies Restricted taxonomy list.
	 *
	 * @return array
	 */
	public function restrict_taxonomies( $taxonomies ) {
		$taxonomies[] = 'happyfiles_category';

		$all_taxonomies = get_taxonomies( array(), 'names' );
		foreach ( $all_taxonomies as $tax_name ) {
			if ( 0 === strpos( $tax_name, 'hf_cat_' ) && ! in_array( $tax_name, $taxonomies, true ) ) {
				$taxonomies[] = $tax_name;
			}
		}

		return $taxonomies;
	}

	/**
	 * Process incoming HappyFiles sync events.
	 *
	 * @param array  $response Current response.
	 * @param object $v        Event data object.
	 *
	 * @return array
	 */
	public function parse_event( $response, $v ) {
		if ( 'happyfiles' !== $v->event_type ) {
			return $response;
		}

		$source_id = $v->reference_id;
		$details   = InstaWP_Sync_Helpers::object_to_array( $v->details );
		$logs      = array();

		switch ( $v->event_slug ) {
			case 'happyfiles_folder_created':
				return $this->process_folder_created( $v, $source_id, $details, $logs );

			case 'happyfiles_folder_updated':
				return $this->process_folder_updated( $v, $source_id, $details, $logs );

			case 'happyfiles_folder_deleted':
				return $this->process_folder_deleted( $v, $source_id, $details, $logs );

			case 'happyfiles_assignment_changed':
				return $this->process_assignment_changed( $v, $details, $logs );

			case 'happyfiles_folder_meta_updated':
				return $this->process_folder_meta_updated( $v, $details, $logs );

			case 'happyfiles_folder_meta_deleted':
				return $this->process_folder_meta_deleted( $v, $details, $logs );

			default:
				return $response;
		}
	}

	/**
	 * Process folder created event.
	 *
	 * @param object $v         Event data.
	 * @param string $source_id Source reference ID.
	 * @param array  $details   Event details.
	 * @param array  $logs      Log entries.
	 *
	 * @return array
	 */
	private function process_folder_created( $v, $source_id, $details, $logs ) {
		if ( ! taxonomy_exists( $details['taxonomy'] ) ) {
			return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
				'status'  => 'pending',
				'message' => 'HappyFiles taxonomy does not exist on destination.',
			) );
		}

		$parent_term_id = 0;
		if ( ! empty( $details['parent'] ) && ! empty( $details['parent_details'] ) ) {
			$parent_details = $details['parent_details'];
			$parent_term_id = $this->get_term( $parent_details['source_id'], $parent_details['data'] );
		}

		$term_meta = isset( $details['term_meta'] ) ? $details['term_meta'] : array();
		unset( $details['term_meta'], $details['parent_details'] );

		$term_id = $this->get_term( $source_id, $details, array(
			'parent' => $parent_term_id,
		) );

		if ( is_wp_error( $term_id ) ) {
			$logs[ $v->id ] = $term_id->get_error_message();

			return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
				'status'  => 'pending',
				'message' => $term_id->get_error_message(),
			) );
		}

		if ( ! empty( $term_meta['data'] ) && is_array( $term_meta['data'] ) ) {
			foreach ( $term_meta['data'] as $key => $value ) {
				if ( 'instawp_event_term_sync_reference_id' === $key ) {
					continue;
				}
				update_term_meta( $term_id, $key, maybe_unserialize( reset( $value ) ) );
			}
		}

		return InstaWP_Sync_Helpers::sync_response( $v, $logs );
	}

	/**
	 * Process folder updated event.
	 *
	 * @param object $v         Event data.
	 * @param string $source_id Source reference ID.
	 * @param array  $details   Event details.
	 * @param array  $logs      Log entries.
	 *
	 * @return array
	 */
	private function process_folder_updated( $v, $source_id, $details, $logs ) {
		if ( ! taxonomy_exists( $details['taxonomy'] ) ) {
			return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
				'status'  => 'pending',
				'message' => 'HappyFiles taxonomy does not exist on destination.',
			) );
		}

		$parent_term_id = 0;
		if ( ! empty( $details['parent'] ) && ! empty( $details['parent_details'] ) ) {
			$parent_details = $details['parent_details'];
			$parent_term_id = $this->get_term( $parent_details['source_id'], $parent_details['data'] );
		}

		$term_meta = isset( $details['term_meta'] ) ? $details['term_meta'] : array();
		unset( $details['term_meta'], $details['parent_details'] );

		$term_id = $this->get_term( $source_id, $details, array(
			'parent' => $parent_term_id,
		) );

		if ( is_wp_error( $term_id ) ) {
			$logs[ $v->id ] = $term_id->get_error_message();

			return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
				'status'  => 'pending',
				'message' => $term_id->get_error_message(),
			) );
		}

		if ( $term_id && ! is_wp_error( $term_id ) ) {
			$result = wp_update_term( $term_id, $details['taxonomy'], array(
				'description' => $details['description'],
				'name'        => $details['name'],
				'slug'        => $details['slug'],
				'parent'      => $parent_term_id,
			) );

			if ( is_wp_error( $result ) ) {
				$logs[ $v->id ] = $result->get_error_message();

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'pending',
					'message' => $result->get_error_message(),
				) );
			}
		}

		if ( ! empty( $term_meta['data'] ) && is_array( $term_meta['data'] ) ) {
			foreach ( $term_meta['data'] as $key => $value ) {
				if ( 'instawp_event_term_sync_reference_id' === $key ) {
					continue;
				}
				update_term_meta( $term_id, $key, maybe_unserialize( reset( $value ) ) );
			}
		}

		return InstaWP_Sync_Helpers::sync_response( $v, $logs );
	}

	/**
	 * Process folder deleted event.
	 *
	 * @param object $v         Event data.
	 * @param string $source_id Source reference ID.
	 * @param array  $details   Event details.
	 * @param array  $logs      Log entries.
	 *
	 * @return array
	 */
	private function process_folder_deleted( $v, $source_id, $details, $logs ) {
		$term_id = $this->get_term( $source_id, $details, array(), false );
		$status  = 'pending';
		$message = '';

		if ( ! $term_id ) {
			$status  = 'completed';
			$message = 'Term not found, may have already been deleted.';
		} else {
			$deleted = wp_delete_term( $term_id, $details['taxonomy'] );

			if ( is_wp_error( $deleted ) ) {
				$message = $deleted->get_error_message();
			} elseif ( false === $deleted ) {
				$message = 'Term not found for delete operation.';
			} elseif ( 0 === $deleted ) {
				$message = 'Default term cannot be deleted.';
			} elseif ( $deleted ) {
				$status  = 'completed';
				$message = 'Sync successfully.';
			}
		}

		return InstaWP_Sync_Helpers::sync_response( $v, $logs, compact( 'status', 'message' ) );
	}

	/**
	 * Process assignment changed event.
	 *
	 * @param object $v       Event data.
	 * @param array  $details Event details.
	 * @param array  $logs    Log entries.
	 *
	 * @return array
	 */
	private function process_assignment_changed( $v, $details, $logs ) {
		$post = InstaWP_Sync_Helpers::get_post_by_reference(
			$details['post_type'],
			$details['post_reference_id'],
			$details['post_name']
		);

		if ( empty( $post ) ) {
			return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
				'status'  => 'pending',
				'message' => 'Post not found on destination.',
			) );
		}

		if ( ! taxonomy_exists( $details['taxonomy'] ) ) {
			return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
				'status'  => 'pending',
				'message' => 'HappyFiles taxonomy does not exist on destination.',
			) );
		}

		$term_ids = array();
		if ( ! empty( $details['term_reference_ids'] ) && is_array( $details['term_reference_ids'] ) ) {
			foreach ( $details['term_reference_ids'] as $term_ref ) {
				$term = InstaWP_Sync_Helpers::get_term_by_reference(
					$details['taxonomy'],
					$term_ref['reference_id'],
					$term_ref['slug']
				);
				if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
					$term_ids[] = (int) $term->term_id;
				}
			}
		}

		wp_set_object_terms( $post->ID, $term_ids, $details['taxonomy'] );

		return InstaWP_Sync_Helpers::sync_response( $v, $logs );
	}

	/**
	 * Process folder meta updated event.
	 *
	 * @param object $v       Event data.
	 * @param array  $details Event details.
	 * @param array  $logs    Log entries.
	 *
	 * @return array
	 */
	private function process_folder_meta_updated( $v, $details, $logs ) {
		$term = InstaWP_Sync_Helpers::get_term_by_reference(
			$details['taxonomy'],
			$details['term_reference_id'],
			''
		);

		if ( empty( $term ) || is_wp_error( $term ) ) {
			return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
				'status'  => 'pending',
				'message' => 'Term not found on destination.',
			) );
		}

		update_term_meta( $term->term_id, $details['meta_key'], $details['meta_value'] );

		return InstaWP_Sync_Helpers::sync_response( $v, $logs );
	}

	/**
	 * Process folder meta deleted event.
	 *
	 * @param object $v       Event data.
	 * @param array  $details Event details.
	 * @param array  $logs    Log entries.
	 *
	 * @return array
	 */
	private function process_folder_meta_deleted( $v, $details, $logs ) {
		$term = InstaWP_Sync_Helpers::get_term_by_reference(
			$details['taxonomy'],
			$details['term_reference_id'],
			''
		);

		if ( empty( $term ) || is_wp_error( $term ) ) {
			return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
				'status'  => 'pending',
				'message' => 'Term not found on destination.',
			) );
		}

		delete_term_meta( $term->term_id, $details['meta_key'] );

		return InstaWP_Sync_Helpers::sync_response( $v, $logs );
	}

	/**
	 * Get or create a term by reference ID (same pattern as InstaWP_Sync_Term::get_term).
	 *
	 * @param string $source_id Reference ID.
	 * @param array  $term      Term data.
	 * @param array  $args      Additional arguments for wp_insert_term.
	 * @param bool   $insert    Whether to insert if not found.
	 *
	 * @return int|WP_Error Term ID or error.
	 */
	private function get_term( $source_id, $term, $args = array(), $insert = true ) {
		$term_id = 0;
		if ( ! taxonomy_exists( $term['taxonomy'] ) ) {
			return $term_id;
		}

		$terms = get_terms( array(
			'hide_empty' => false,
			'meta_key'   => 'instawp_event_term_sync_reference_id',
			'meta_value' => $source_id,
			'fields'     => 'ids',
			'taxonomy'   => $term['taxonomy'],
		) );

		if ( empty( $terms ) ) {
			$get_term_by = (array) get_term_by( 'slug', $term['slug'], $term['taxonomy'] );

			if ( ! empty( $get_term_by['term_id'] ) ) {
				$term_id = $get_term_by['term_id'];
			} elseif ( true === $insert ) {
				$inserted_term = wp_insert_term( $term['name'], $term['taxonomy'], wp_parse_args( $args, array(
					'description' => $term['description'],
					'slug'        => $term['slug'],
					'parent'      => 0,
				) ) );

				$term_id = is_wp_error( $inserted_term ) ? $inserted_term : $inserted_term['term_id'];
			}

			if ( $term_id && ! is_wp_error( $term_id ) ) {
				update_term_meta( $term_id, 'instawp_event_term_sync_reference_id', $source_id );
			}
		} else {
			$term_id = reset( $terms );
		}

		return $term_id;
	}
}

new InstaWP_Sync_HappyFiles();
