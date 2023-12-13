<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Term {

    public function __construct() {
	    // Term actions
	    add_action( 'created_term', [ $this, 'create_taxonomy' ], 10, 3 );
	    add_action( 'edited_term', [ $this, 'edit_taxonomy' ], 10, 3 );
	    add_action( 'delete_term', [ $this, 'delete_taxonomy' ], 10, 4 );
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
}

new InstaWP_Sync_Term();