<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Menu {

    public function __construct() {
	    // Term actions
	    add_action( 'wp_create_nav_menu', array( $this, 'create_or_update_nav_menu' ), 10, 2 );
	    add_action( 'wp_update_nav_menu', array( $this, 'create_or_update_nav_menu' ), 10, 2 );
	    add_action( 'pre_delete_term', array( $this, 'delete_nav_menu' ), 10, 2 );

	    // Process event
	    add_filter( 'INSTAWP_CONNECT/Filters/process_two_way_sync', array( $this, 'parse_event' ), 10, 3 );
    }

	/**
	 * Function for nav menu created or updated.
	 *
	 * @param int   $nav_menu_id ID of the updated menu.
	 * @param array $menu_data   An array of menu data.
	 *
	 * @return void
	 */
	public function create_or_update_nav_menu( $nav_menu_id, $menu_data = array() ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'menu' ) ) {
			return;
		}

		$source_id    = InstaWP_Sync_Helpers::get_term_reference_id( $nav_menu_id );
		$menu_details = ( array ) wp_get_nav_menu_object( $nav_menu_id );

		if ( 'wp_create_nav_menu' === current_filter() ) {
			$event_name = __('Nav Menu created', 'instawp-connect' );
			$event_slug = 'nav_menu_created';
		} else {
			$event_name = __('Nav Menu updated', 'instawp-connect' );
			$event_slug = 'nav_menu_updated';
			$menu_items = wp_get_nav_menu_items( $nav_menu_id );
			$menu_items = array_map( function( $item ) {
				if ( $item->type === 'post_type' ) {
					$item->object_name  = get_post( $item->object_id )->post_name;
					$item->reference_id = InstaWP_Sync_Helpers::get_post_reference_id( $item->object_id );
				} else if ( $item->type === 'taxonomy' ) {
					$item->object_name  = get_term( $item->object_id )->slug;
					$item->reference_id = InstaWP_Sync_Helpers::get_term_reference_id( $item->object_id );
				}

				return $item;
			}, $menu_items );

			$menu_details['items'] = $menu_items;
		}

		$menus = get_nav_menu_locations();
		foreach ( $menus as $menu_name => $menu_id ) {
			if ( intval( $nav_menu_id ) === $menu_id ) {
				$menu_details['locations'][] = $menu_name;
			}
		}

		$options = get_option( 'nav_menu_options' );
		if ( ! empty( $options['auto_add'] ) && in_array( $nav_menu_id, $options['auto_add'] ) ) {
			$menu_details['auto_add'] = true;
		}

		InstaWP_Sync_DB::insert_update_event( $event_name, $event_slug, 'nav_menu', $source_id, $menu_details['name'], $menu_details );
	}

	/**
	 * Function for `delete_(taxonomy)` action-hook.
	 *
	 * @param int     $term_id         Term ID.
	 * @param int     $taxonomy        Term taxonomy ID.
	 *
	 * @return void
	 */
	public function delete_nav_menu( $term_id, $taxonomy ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'menu' ) ) {
			return;
		}

		if ( $taxonomy !== 'nav_menu' ) {
			return;
		}

		$menu_details = ( array ) get_term( $term_id, $taxonomy );
		$source_id    = InstaWP_Sync_Helpers::get_term_reference_id( $term_id );;
		$event_name   = __('Nav Menu deleted', 'instawp-connect' );

		InstaWP_Sync_DB::insert_update_event( $event_name, 'nav_menu_deleted', $taxonomy, $source_id, $menu_details['name'], $menu_details );
	}

	public function parse_event( $response, $v, $source_url ) {
		$source_id = $v->source_id;
		$term      = InstaWP_Sync_Helpers::object_to_array( $v->details );
		$logs      = array();

		// delete nav menu
		if ( $v->event_slug === 'nav_menu_created' ) {
			$menu_id = wp_create_nav_menu( $term['name'] );

			if ( is_wp_error( $menu_id ) ) {
				$logs[ $v->id ] = $menu_id->get_error_message();

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'error',
					'message' => $logs[ $v->id ],
				) );
			}

			InstaWP_Sync_Helpers::set_term_reference_id( $menu_id, $source_id );

			$this->set_locations( $term, $menu_id );
			$this->setup_auto_add( $term, $menu_id );

			return InstaWP_Sync_Helpers::sync_response( $v );
		}

		// update nav menu
		if ( $v->event_slug === 'nav_menu_updated' ) {
			$menu_id = $this->get_nav_menu( $source_id, $term );

			if ( $menu_id ) {
				$menu_objects = get_objects_in_term( $menu_id, 'nav_menu' );

				if ( ! empty( $menu_objects ) ) {
					foreach ( $menu_objects as $item ) {
						wp_delete_post( $item );
					}
				}
			} else {
				$menu_id = wp_create_nav_menu( $term['name'] );

				if ( is_wp_error( $menu_id ) ) {
					$logs[ $v->id ] = $menu_id->get_error_message();

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}
			}

			if ( ! empty( $term['items'] ) ) {
				foreach ( $term['items'] as $value ) {
					$value = ( object ) $value;

					// Create new menu item to get the id.
					$menu_item_id = wp_update_nav_menu_item( $menu_id, 0, null );

					// Store all parent child relationships in an array.
					$parent_child[ $value->db_id ] = $menu_item_id;

					if ( isset( $parent_child[ $value->menu_item_parent ] ) ) {
						$menu_item_parent_id = $parent_child[ $value->menu_item_parent ];
					} else {
						$menu_item_parent_id = 0;
					}

					if ( $value->type === 'post_type' ) {
						$post             = InstaWP_Sync_Helpers::get_post_by_reference( $value->object, $value->reference_id, $value->object_name );
						$value->object_id = $post->ID;
					} else if ( $value->type === 'taxonomy' ) {
						$term             = InstaWP_Sync_Helpers::get_term_by_reference( $value->object, $value->reference_id, $value->object_name );
						$value->object_id = $term->term_id;
					}

					$args = array(
						//'menu-item-db-id'       => $value->db_id,
						'menu-item-object-id'   => $value->object_id,
						'menu-item-object'      => $value->object,
						'menu-item-parent-id'   => intval( $menu_item_parent_id ),
						'menu-item-position'    => $value->menu_order,
						'menu-item-title'       => $value->title,
						'menu-item-type'        => $value->type,
						'menu-item-url'         => str_replace( $source_url, site_url(), $value->url ),
						'menu-item-description' => $value->description,
						'menu-item-attr-title'  => $value->attr_title,
						'menu-item-target'      => $value->target,
						'menu-item-classes'     => implode( ' ', $value->classes ),
						'menu-item-xfn'         => $value->xfn,
						'menu-item-status'      => $value->post_status,
					);

					// Update the menu nav item with all information.
					wp_update_nav_menu_item( $menu_id, $menu_item_id, $args );
				}
			}

			$this->set_locations( $term, $menu_id );
			$this->setup_auto_add( $term, $menu_id );

			return InstaWP_Sync_Helpers::sync_response( $v );
		}

		// delete nav menu
		if ( $v->event_slug === 'nav_menu_deleted' ) {
			$menu_id = $this->get_nav_menu( $source_id, $term );

			if ( ! $menu_id ) {
				$logs[ $v->id ] = __( 'Can not find nav menu', 'instawp-connect' );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'error',
					'message' => $logs[ $v->id ],
				) );
			}

			$deleted = wp_delete_nav_menu( $menu_id );

			if ( is_wp_error( $deleted ) || ! $deleted ) {
				$logs[ $v->id ] = is_wp_error( $deleted ) ? $deleted->get_error_message() : __( 'Can not delete Nav Menu.', 'instawp-connect' );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'error',
					'message' => $logs[ $v->id ],
				) );
			}

			return InstaWP_Sync_Helpers::sync_response( $v );
		}

		return $response;
	}

	private function get_nav_menu( $source_id, $term ) {
		$term_id = 0;

		$terms = wp_get_nav_menus( array(
			'meta_key'   => 'instawp_event_term_sync_reference_id',
			'meta_value' => $source_id,
			'fields'     => 'ids',
		) );

		if ( empty( $terms ) ) {
			$get_term_by = ( array ) get_term_by( 'slug', $term['slug'], 'nav_menu' );

			if ( ! empty( $get_term_by['term_id'] ) ) {
				$term_id = $get_term_by['term_id'];
				InstaWP_Sync_Helpers::set_term_reference_id( $term_id, $source_id );
			}
		} else {
			$term_id = current( $terms );
		}

		return $term_id;
	}

	private function set_locations( $term, $menu_id ) {
		if ( ! empty( $term['locations'] ) ) {
			$locations        = [];
			$registered_menus = get_registered_nav_menus();

			foreach ( $term['locations'] as $location ) {
				if ( in_array( $location, array_keys( $registered_menus ) ) ) {
					$locations[ $location ] = $menu_id;
				}
			}

			if ( ! empty( $locations ) ) {
				set_theme_mod( 'nav_menu_locations', $locations );
			}
		}
	}

	private function setup_auto_add( $term, $menu_id ) {
		if ( ! empty( $term['auto_add'] ) ) {
			$options               = get_option( 'nav_menu_options' );
			$options['auto_add'][] = $menu_id;

			update_option( 'nav_menu_options', $options );
		}
	}
}

new InstaWP_Sync_Menu();