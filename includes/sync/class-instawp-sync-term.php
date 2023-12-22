<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Term {

    public function __construct() {
	    // Term actions
	    add_action( 'created_term', [ $this, 'create_term' ], 10, 3 );
	    add_action( 'edited_term', [ $this, 'edit_term' ], 10, 3 );
	    add_action( 'pre_delete_term', [ $this, 'delete_term' ], 10, 2 );

	    // Process event
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
	public function create_term( $term_id, $tt_id, $taxonomy ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'term' ) ) {
			return;
		}

		$term_details = ( array ) get_term( $term_id, $taxonomy );
		$event_name   = sprintf( __('%s created', 'instawp-connect'), ucfirst( $taxonomy ) );

		if ( $term_details['parent'] ) {
			$term_details['parent_details'] = [
				'data'      => ( array ) get_term( $term_details['parent'], $taxonomy ),
				'source_id' => get_term_meta( $term_details['parent'], 'instawp_source_id', true ),
			];
		}

		$source_id = InstaWP_Sync_Helpers::set_term_reference_id( $term_id );
		InstaWP_Sync_DB::insert_update_event( $event_name, 'create_term', $taxonomy, $source_id, $term_details['name'], $term_details );
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
	public function edit_term( $term_id, $tt_id, $taxonomy ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'term' ) ) {
			return;
		}

		$term_details = ( array ) get_term( $term_id, $taxonomy );
		$event_name   = sprintf( __('%s modified', 'instawp-connect'), ucfirst( $taxonomy ) );

		if ( $term_details['parent'] ) {
			$term_details['parent_details'] = [
				'data'      => ( array ) get_term( $term_details['parent'], $taxonomy ),
				'source_id' => get_term_meta( $term_details['parent'], 'instawp_source_id', true ),
			];
		}

		$source_id = InstaWP_Sync_Helpers::set_term_reference_id( $term_id );
		InstaWP_Sync_DB::insert_update_event( $event_name, 'edit_term', $taxonomy, $source_id, $term_details['name'], $term_details );
	}

	/**
	 * Function for `delete_(taxonomy)` action-hook.
	 *
	 * @param int     $term_id         Term ID.
	 * @param int     $taxonomy        Term taxonomy ID.
	 *
	 * @return void
	 */
	public function delete_term( $term_id, $taxonomy ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'term' ) ) {
			return;
		}

		$term_details = ( array ) get_term( $term_id, $taxonomy );
		$source_id    = get_term_meta( $term_id, 'instawp_source_id', true );
		$event_name   = sprintf( __('%s deleted', 'instawp-connect' ), ucfirst( $taxonomy ) );

		InstaWP_Sync_DB::insert_update_event( $event_name, 'delete_term', $taxonomy, $source_id, $term_details['name'], $term_details );
	}

	public function parse_event( $response, $v ) {
		$source_id = $v->source_id;
		$logs      = [];

		// create and update term
		if ( in_array( $v->event_slug, [ 'create_term', 'edit_term' ], true ) && ! empty( $source_id ) ) {
			$term = ( array ) $v->details;

			$parent_term_id = $term['parent'];
			if ( $parent_term_id ) {
				$parent_details = $term['parent_details'];
				$parent_term_id = $this->get_term( $parent_details['source_id'], $parent_details['data'] );
			}

			$term_id = $this->get_term( $source_id, $term, [
				'parent' => $parent_term_id,
			] );

			if ( is_wp_error( $term_id ) ) {
				$logs[ $v->id ] = $term_id->get_error_message();

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, [
					'status'  => 'pending',
					'message' => $term_id->get_error_message()
				] );
			}

			if ( $v->event_slug === 'edit_term' && $term_id && ! is_wp_error( $term_id ) ) {
				$result = wp_update_term( $term_id, $term['taxonomy'], [
					'description' => $term['description'],
					'name'        => $term['name'],
					'slug'        => $term['slug'],
					'parent'      => $parent_term_id,
				] );

				if ( is_wp_error( $result ) ) {
					$logs[ $v->id ] = $result->get_error_message();

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, [
						'status'  => 'pending',
						'message' => $result->get_error_message()
					] );
				}
			}

			return InstaWP_Sync_Helpers::sync_response( $v, $logs );
		}

		// delete term
		if ( $v->event_slug === 'delete_term' && ! empty( $source_id ) ) {
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

			return InstaWP_Sync_Helpers::sync_response( $v, [], compact( 'status', 'message' ) );
		}

		return $response;
	}

	private function get_term( $source_id, $term, $args = [] ) {
		$term_id = 0;
		if ( ! taxonomy_exists( $term['taxonomy'] ) ) {
			return $term_id;
		}

		$terms = get_terms( [
			'hide_empty' => false,
			'meta_key'   => 'instawp_event_term_sync_reference_id',
			'meta_value' => $source_id,
			'fields'     => 'ids',
			'taxonomy'   => $term['taxonomy'],
		] );

		if ( empty( $terms ) ) {
			$get_term_by = ( array ) get_term_by( 'slug', $term['slug'], $term['taxonomy'] );

			if ( ! empty( $get_term_by['term_id'] ) ) {
				$term_id = $get_term_by['term_id'];
			} else {
				$inserted_term = wp_insert_term( $term['name'], $term['taxonomy'], wp_parse_args( $args, [
					'description' => $term['description'],
					'slug'        => $term['slug'],
					'parent'      => 0
				] ) );

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

new InstaWP_Sync_Term();