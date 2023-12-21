<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_WC extends InstaWP_Sync_Post {

    public function __construct() {
	    parent::__construct();

		// Hooks
        add_filter( 'INSTAWP_CONNECT/Filters/two_way_sync_post_data', [ $this, 'add_post_data' ], 10, 3 );
        add_action( 'INSTAWP_CONNECT/Actions/process_two_way_sync_post', [ $this, 'process_gallery' ], 10, 2 );

		// Attributes actions
	    add_action( 'woocommerce_attribute_added', [ $this,'attribute_added' ], 10, 2 );
	    add_action( 'woocommerce_attribute_updated', [ $this, 'attribute_updated' ], 10, 2 );
	    add_action( 'woocommerce_attribute_deleted', [ $this, 'attribute_deleted' ], 10, 2 );

	    // Process event
	    add_filter( 'INSTAWP_CONNECT/Filters/process_two_way_sync', [ $this, 'parse_event' ], 10, 2 );
    }

	public function add_post_data( $data, $type, $post ) {
		if ( $this->can_sync() && $type === 'product' ) {
			$data['product_gallery'] = $this->get_product_gallery( $post->ID );
		}

		return $data;
	}
	
	public function process_gallery( $post, $data ) {
		if ( $this->can_sync() && $post['post_type'] === 'product' ) {
			if ( isset( $data->details->product_gallery ) && ! empty( $data->details->product_gallery ) ) {
				$product_gallery = $data->details->product_gallery;
				$gallery_ids     = [];
				foreach ( $product_gallery as $gallery ) {
					if ( ! empty( $gallery->media ) && ! empty( $gallery->url ) ) {
						$gallery_ids[] = $this->handle_attachments( ( array ) $gallery->media, ( array ) $gallery->media_meta, $gallery->url );
					}
				}
				$this->set_product_gallery( $post['ID'], $gallery_ids );
			}
		}
	}

	/**
	 * Attribute added (hook).
	 *
	 * @param int   $id   Added attribute ID.
	 * @param array $data Attribute data.
	 */
	public function attribute_added( $id, $data ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		$event_name = __( 'Woocommerce attribute added', 'instawp-connect' );
		$this->add_event( $event_name, 'woocommerce_attribute_added', $data, $id );
	}

	/**
	 * Attribute Updated (hook).
	 *
	 * @param int   $id   Updated attribute ID.
	 * @param array $data Attribute data.
	 */
	public function attribute_updated( $id, $data ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		$event_name = __('Woocommerce attribute updated', 'instawp-connect' );
		$event_id   = InstaWP_Sync_DB::existing_update_events(INSTAWP_DB_TABLE_EVENTS, 'woocommerce_attribute_updated', $id );

		$this->add_event( $event_name, 'woocommerce_attribute_updated', $data, $id, $event_id );
	}

	/**
	 * Attribute Deleted (hook).
	 *
	 * @param int   $id   Deleted attribute ID.
	 * @param array $data Attribute data.
	 */
	public function attribute_deleted( $id, $data ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		$event_name = __( 'Woocommerce attribute deleted', 'instawp-connect' );
		$this->add_event( $event_name, 'woocommerce_attribute_deleted', $data, $id );
	}

	public function parse_event( $response, $v ) {
		if ( $v->event_type !== 'woocommerce' || empty( $v->source_id ) || ! class_exists( 'WooCommerce' ) ) {
			return $response;
		}

		$details  = ( array ) $v->details;
		$log_data = [];

		// add or update attribute
		if ( in_array( $v->event_slug, [ 'woocommerce_attribute_added', 'woocommerce_attribute_updated' ], true ) ) {
			$attribute = wc_get_attribute( $v->source_id );

			if ( ! empty( $attribute ) ) {
				unset( $details['id'] );
				$attribute = wc_update_attribute( $v->source_id, $details );
			} else {
				$attribute = $this->create_attribute( $v->source_id, $details );
			}

			if ( is_wp_error( $attribute ) ) {
				$log_data[ $v->id ] = $attribute->get_error_message();

				return InstaWP_Sync_Helpers::sync_response( $v, $log_data, [
					'status'  => 'pending',
					'message' => $attribute->get_error_message()
				] );
			}
		}

		if ( $v->event_slug === 'woocommerce_attribute_deleted' ) {
			$response = wc_delete_attribute( $v->source_id );

			if ( ! $response ) {
				return InstaWP_Sync_Helpers::sync_response( $v, [], [
					'status'  => 'pending',
					'message' => 'Failed'
				] );
			}
		}

		return InstaWP_Sync_Helpers::sync_response( $v, $log_data );
	}

	/*
	 * Function add_event
	 * @param $event_name
	 * @param $event_slug
	 * @param $details
	 * @param $type
	 * @param $source_id
	 * @return void
	 */
	private function add_event( $event_name, $event_slug, $details, $source_id, $event_id = null ) {
		switch ( $event_slug ) {
			case 'woocommerce_attribute_added':
			case 'woocommerce_attribute_updated':
				$title   = $details['attribute_label'];
				$details = ( array ) wc_get_attribute( $source_id );
				break;
			default:
				$title = $details;
		}
		InstaWP_Sync_DB::insert_update_event( $event_name, $event_slug, 'woocommerce', $source_id, $title, $details, $event_id );
	}

	private function can_sync(): bool {
		return InstaWP_Sync_Helpers::can_sync( 'wc' ) && class_exists( 'WooCommerce' );
	}

	/**
	 * Create woocommerce attribute
	 */
	public function create_attribute( $source_id, $args ) {
		global $wpdb;

		$args   = wp_unslash( $args );
		$format = [ '%s', '%s', '%s', '%s', '%d' ];

		// Validate type.
		if ( empty( $args['type'] ) || ! array_key_exists( $args['type'], wc_get_attribute_types() ) ) {
			$args['type'] = 'select';
		}

		// Validate order by.
		if ( empty( $args['order_by'] ) || ! in_array( $args['order_by'], [ 'menu_order', 'name', 'name_num', 'id' ], true ) ) {
			$args['order_by'] = 'menu_order';
		}

		$data = [
			'attribute_id'      => intval( $source_id ),
			'attribute_label'   => $args['name'],
			'attribute_name'    => $args['slug'],
			'attribute_type'    => $args['type'],
			'attribute_orderby' => $args['order_by'],
			'attribute_public'  => isset( $args['has_archives'] ) ? (int) $args['has_archives'] : 0,
		];

		$results = $wpdb->insert(
			$wpdb->prefix . 'woocommerce_attribute_taxonomies',
			$data,
			$format
		);

		if ( is_wp_error( $results ) ) {
			return new WP_Error( 'cannot_create_attribute', 'Can not create attribute!', [ 'status' => 400 ] );
		}

		$id = $wpdb->insert_id;

		/**
		 * Attribute added.
		 *
		 * @param int $id Added attribute ID.
		 * @param array $data Attribute data.
		 */
		do_action( 'woocommerce_attribute_added', $id, $data );

		// Clear cache and flush rewrite rules.
		wp_schedule_single_event( time(), 'woocommerce_flush_rewrite_rules' );
		delete_transient( 'wc_attribute_taxonomies' );
		\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );

		return $id;
	}

	/*
     * Get product gallery images
     */
	private function get_product_gallery( $product_id ): array {
		$gallery = [];
		$product = $this->get_product( $product_id );

		if ( $product ) {
			$attachment_ids = $product->get_gallery_image_ids();

			if ( ! empty( $attachment_ids ) && is_array( $attachment_ids ) ) {
				foreach ( $attachment_ids as $attachment_id ) {
					$url       = wp_get_attachment_url( intval( $attachment_id ) );
					$gallery[] = [
						'id'         => $attachment_id,
						'url'        => $url,
						'media'      => get_post( $attachment_id ),
						'media_meta' => get_post_meta( $attachment_id ),
					];
				}
			}
		}

		return $gallery;
	}

	/**
	 * Set product gallery
	 */
	private function set_product_gallery( $product_id, $gallery_ids ) {
		$product = $this->get_product( $product_id );
		if ( $product ) {
			$product->set_gallery_image_ids( $gallery_ids );
			$product->save();
		}
	}

	/**
	 * Set product gallery
	 */
	private function get_product( $product_id ) {
		return function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
	}
}

new InstaWP_Sync_WC();