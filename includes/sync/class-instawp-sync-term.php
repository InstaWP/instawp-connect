<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Term {

    public function __construct() {
	    if ( InstaWP_Sync_Helpers::can_sync() ) {
		    // Term actions
		    add_action( 'created_term', [ $this, 'create_taxonomy' ], 10, 3 );
		    add_action( 'edited_term', [ $this, 'edit_taxonomy' ], 10, 3 );
		    add_action( 'delete_term', [ $this, 'delete_taxonomy' ], 10, 4 );
	    }

	    // process event
	    add_filter( 'INSTAWP_CONNECT/Filters/process_two_way_sync', [ $this, 'parse_event' ], 10, 2 );
    }

	/**
	 * Function for `created_(taxonomy)` action-hook.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_insert_term().
	 *
	 * @return void
	 */
	public function create_taxonomy( $term_id, $tt_id, $taxonomy ) {
		$term_details = ( array ) get_term( $term_id, $taxonomy );
		$event_name   = sprintf( __('%s created', 'instawp-connect'), ucfirst( $taxonomy ) );

		InstaWP_Sync_DB::insert_update_event( $event_name, 'create_taxonomy', $taxonomy, $term_id, $term_details['name'], $term_details );
	}

	/**
	 * Function for `created_(taxonomy)` action-hook.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_insert_term().
	 *
	 * @return void
	 */
	public function edit_taxonomy( $term_id, $tt_id, $taxonomy ) {
		$term_details = ( array ) get_term( $term_id, $taxonomy );
		$event_name   = sprintf( __('%s modified', 'instawp-connect'), ucfirst( $taxonomy ) );

		InstaWP_Sync_DB::insert_update_event( $event_name, 'edit_taxonomy', $taxonomy, $term_id, $term_details['name'], $term_details );
	}

	/**
	 * Function for `delete_(taxonomy)` action-hook.
	 *
	 * @param int     $term         Term ID.
	 * @param int     $tt_id        Term taxonomy ID.
	 * @param WP_Term $deleted_term Copy of the already-deleted term.
	 * @param array   $object_ids   List of term object IDs.
	 *
	 * @return void
	 */
	public function delete_taxonomy( $term, $tt_id, $taxonomy, $deleted_term ) {
		$event_name = sprintf( __('%s deleted', 'instawp-connect' ), ucfirst( $taxonomy ) );

		InstaWP_Sync_DB::insert_update_event( $event_name, 'delete_taxonomy', $taxonomy, $term, $deleted_term->name, $deleted_term );
	}

	public function parse_event( $response, $v ) {
		$source_id = $v->source_id;

		// create and update term
		if ( in_array( $v->event_slug, [ 'create_taxonomy', 'edit_taxonomy' ], true ) && ! empty( $source_id ) ) {
			$details          = ( array ) $v->details;
			$wp_terms         = $this->wp_terms_data( $source_id, $details );
			$wp_term_taxonomy = $this->wp_term_taxonomy_data( $source_id, $details );

			if ( $v->event_slug === 'create_taxonomy' && ! term_exists( $source_id, $v->event_type ) ) {
				$this->insert_taxonomy( $source_id, $wp_terms, $wp_term_taxonomy );
				clean_term_cache( $source_id );
			}

			if ( $v->event_slug === 'edit_taxonomy' && term_exists( $source_id, $v->event_type ) ) {
				$this->update_taxonomy( $source_id, $wp_terms, $wp_term_taxonomy );
			}

			return InstaWP_Sync_Helpers::sync_response( $v );
		}

		// delete term
		if ( $v->event_slug === 'delete_taxonomy' && ! empty( $source_id ) ) {
			$deleted = wp_delete_term( $source_id, $v->event_type );
			$status  = 'pending';

			if ( is_wp_error( $deleted ) ) {
				$message = $deleted->get_error_message();
			} else if ( $deleted === false ) {
				$message = 'Term not found for delete operation.';
			} else if ( $deleted === 0 ) {
				$message = 'Default Term can not be deleted.';
			} else if ( $deleted ) {
				$status  = 'completed';
				$message = 'Sync successfully.';
			}

			return InstaWP_Sync_Helpers::sync_response( $v, compact( 'status', 'message' ) );
		}

		return $response;
	}

	/**
	 * wp_terms_data
	 *
	 * @param $term_id
	 * @param $arr
	 *
	 * @return array
	 */
	public function wp_terms_data( $term_id = null, $arr = [] ): array {
		return [
			'term_id' => $term_id,
			'name'    => $arr['name'],
			'slug'    => $arr['slug']
		];
	}

	/**
	 * wp_term_taxonomy_data
	 *
	 * @param $term_id
	 * @param $arr
	 *
	 * @return array
	 */
	public function wp_term_taxonomy_data( $term_id = null, $arr = [] ): array {
		return [
			'term_taxonomy_id' => $term_id,
			'term_id'          => $term_id,
			'taxonomy'         => $arr['taxonomy'],
			'description'      => $arr['description'],
			'parent'           => $arr['parent']
		];
	}

	public function insert_taxonomy( $term_id = null, $wp_terms = null, $wp_term_taxonomy = null ) {
		InstaWP_Sync_DB::insert( InstaWP_Sync_DB::prefix() . 'terms', $wp_terms );
		InstaWP_Sync_DB::insert( InstaWP_Sync_DB::prefix() . 'term_taxonomy', $wp_term_taxonomy );
	}

	public function update_taxonomy( $term_id = null, $wp_terms = null, $wp_term_taxonomy = null ) {
		InstaWP_Sync_DB::update( InstaWP_Sync_DB::prefix() . 'terms', $wp_terms, array( 'term_id' => $term_id ) );
		InstaWP_Sync_DB::update( InstaWP_Sync_DB::prefix() . 'term_taxonomy', $wp_term_taxonomy, array( 'term_id' => $term_id ) );
	}
}

new InstaWP_Sync_Term();