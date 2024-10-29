<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_WC {

	/**
	 * Order ID meta key. Use to match staging order id with production order id
	 * @var string
	 * @since 0.1.0.58
	 */
	private $order_id_meta_key = '_iwp_wc_sync_order_id';
	
	/**
	 * Order created date key
	 * @var string
	 * @since 0.1.0.58
	 */
	private $order_created_date_key = 'iwp_sync_date_created';
	
	/**
	 * Order modified date key
	 * @var string
	 * @since 0.1.0.58
	 */
	private $order_modified_date_key = 'iwp_sync_date_modified';

    public function __construct() {
	    // Order Actions.
	    add_action( 'woocommerce_new_order', array( $this, 'create_order' ) );
	    add_action( 'woocommerce_update_order', array( $this, 'update_order' ) );
	    add_action( 'woocommerce_before_trash_order', array( $this, 'trash_order' ) );
	    add_action( 'woocommerce_before_delete_order', array( $this, 'delete_order' ) );

		// Attributes Actions.
	    add_action( 'woocommerce_attribute_added', array( $this, 'attribute_added' ), 10, 2 );
	    add_action( 'woocommerce_attribute_updated', array( $this, 'attribute_updated' ), 10, 3 );
	    add_action( 'woocommerce_attribute_deleted', array( $this, 'attribute_deleted' ), 10, 2 );

	    // Hooks.
	    add_filter( 'instawp/filters/2waysync/can_sync_post', array( $this, 'can_sync_post' ), 10, 2 );
	    add_filter( 'instawp/filters/2waysync/can_sync_taxonomy', array( $this, 'can_sync_taxonomy' ), 10, 2 );
	    add_filter( 'instawp/filters/2waysync/post_data', array( $this, 'add_post_data' ), 10, 3 );
	    add_action( 'instawp/actions/2waysync/process_event_post', array( $this, 'process_gallery' ), 10, 2 );

	    // Process Events.
	    add_filter( 'instawp/filters/2waysync/process_event', array( $this, 'parse_event_wrapper' ), 10, 2 );

		// Display order number
		add_filter( 'woocommerce_order_number', array( $this, 'display_order_number_from_meta' ), 10, 2 );
    }

	public function create_order( $order_id ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		// Add order ID as order meta
		$this->add_order_number_meta( $order_id, $order );

		$event_name  = __('Order created', 'instawp-connect' );
		$this->add_event( $event_name, 'woocommerce_order_created', $order->get_id(), $order->get_order_key() );
	}

	/**
	 * Production only : Add order ID as order meta to WC order object in order to display this ID. 
	 * After sync, this meta data will be saved as it is in staging site. It will help to match order 
	 * number with production site.
	 *
	 * @since  0.1.0.58
	 * @param int    $order_id The ID of the order to add the custom ID to.
	 * @param object $order    The WC order object to add the custom ID to.
	 *
	 * @return void
	 */
	private function add_order_number_meta( $order_id, $order ) {
		// Meta data for order id will not be created in staging
		if ( instawp()->is_staging || empty( $order_id ) || empty( $order ) ) {
			return;
		}
		// Check if custom order ID already exists
		$custom_id = $order->get_meta( $this->order_id_meta_key );
		if ( ! empty( $custom_id ) ) {
			return;
		}

		// Set custom order ID as order meta
		$order->update_meta_data( $this->order_id_meta_key, $order_id );
		$order->save();
	}

	/**
	 * Staging only: Display order ID from meta, if it exists. Here custom ID will be same as 
	 * source site order id
	 *
	 * @since  0.1.0.58
	 * @param int    $order_id The ID of the order to get the custom ID from.
	 * @param object $order    The WC order object to get the custom ID from.
	 *
	 * @return int The custom order ID if it exists, otherwise the original ID.
	 */
	public function display_order_number_from_meta( $order_id, $order ) {
		// Production site order id will be same as custom order id
		if ( ! instawp()->is_staging || empty( $order_id ) || empty( $order ) ) {
			return $order_id;
		}

		$custom_id = $order->get_meta( $this->order_id_meta_key );
		if ( empty( $custom_id ) ) {
			return $order_id;
		}
		return $custom_id;
	}


	/**
	 * Sync order on update.
	 *
	 * Triggered when an order is updated. This will sync the order to the staging site.
	 *
	 * @since 0.1.0.58
	 *
	 * @param int $order_id The order ID to sync.
	 */
	public function update_order( $order_id ) {
		if ( ! $this->can_sync() ) {
			return;
		}
		$this->add_update_order_event( $order_id );
	}

	/**
	 * Add event for when an order is updated. This event is used to sync
	 * the order with the staging site.
	 *
	 * @since  0.1.0.58
	 *
	 * @param int    $order_id The ID of the order to add the event for.
	 *
	 * @return void
	 */
	private function add_update_order_event( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Add order ID as order meta
		$this->add_order_number_meta( $order_id, $order );

		$event_name = __('Order updated', 'instawp-connect' );
		$this->add_event( $event_name, 'woocommerce_order_updated', $order->get_id(), $order->get_order_key() );
	}

	public function trash_order( $order_id ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$event_name = __('Order trashed', 'instawp-connect' );
		$this->add_event( $event_name, 'woocommerce_order_trashed', $order->get_id(), $order->get_order_key() );
	}

	public function delete_order( $order_id ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$event_name = __('Order trashed', 'instawp-connect' );
		$this->add_event( $event_name, 'woocommerce_order_deleted', $order->get_id(), $order->get_order_key() );
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

		$data['attribute_id'] = $id;
		$this->add_event( $event_name, 'woocommerce_attribute_added', $data, $data['attribute_name'] );
	}

	/**
	 * Attribute Updated (hook).
	 *
	 * @param int    $id       Added attribute ID.
	 * @param array  $data     Attribute data.
	 * @param string $old_slug Attribute old name.
	 */
	public function attribute_updated( $id, $data, $old_slug ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		$event_name = __('Woocommerce attribute updated', 'instawp-connect' );
		$data['attribute_id'] = $id;
		$this->add_event( $event_name, 'woocommerce_attribute_updated', $data, $old_slug );
	}

	/**
	 * Attribute Deleted (hook).
	 *
	 * @param int $id Attribute ID.
	 * @param string $name Attribute name.
	 */
	public function attribute_deleted( $id, $name ) {
		if ( ! $this->can_sync() ) {
			return;
		}

		$event_name = __( 'Woocommerce attribute deleted', 'instawp-connect' );
		$this->add_event( $event_name, 'woocommerce_attribute_deleted', array( 'attribute_id' => $id ), $name );
	}

	public function can_sync_post( $can_sync, $post ) {
		if ( $this->can_sync() && in_array( $post->post_type, array( 'product', 'shop_coupon' ), true ) ) {
			$can_sync = true;
		}

		return $can_sync;
	}

    public function can_sync_taxonomy( $can_sync, $taxonomy ) {
        $taxonomies    = array();
        $wc_taxonomies = get_object_taxonomies( 'product', 'objects' );
        foreach ( $wc_taxonomies as $wc_taxonomy ) {
            if ( in_array( $wc_taxonomy->name, array( 'product_type', 'product_visibility', 'product_shipping_class' ), true ) ) {
                continue;
            }
            $taxonomies[] = $wc_taxonomy->name;
        }

        if ( $this->can_sync() && in_array( $taxonomy, $taxonomies, true ) ) {
            $can_sync = true;
        }

        return $can_sync;
    }

	public function add_post_data( $data, $type, $post ) {
		if ( $this->can_sync() && $type === 'product' ) {
			$data['product_gallery'] = $this->get_product_gallery( $post->ID );
		}

		return $data;
	}

	public function process_gallery( $post, $data ) {
		if ( $post['post_type'] === 'product' ) {
			$product_gallery = isset( $data['product_gallery'] ) ? $data['product_gallery'] : array();
			$gallery_ids     = array();

			foreach ( $product_gallery as $gallery_item ) {
				$gallery_ids[] = InstaWP_Sync_Parser::process_attachment_data( $gallery_item );
			}

			$this->set_product_gallery( $post['ID'], $gallery_ids );
		}
	}

	/**
	 * Adds an order item with specified properties to a WooCommerce order.
	 *
	 * This function sets the properties of the given item, updates its metadata
	 * if available, and then saves the item. Finally, it adds the item to the order.
	 *
	 * @param WC_Order $order The WooCommerce order to add the item to.
	 * @param WC_Order_Item $item The order item to be added.
	 * @param array $props The properties to set on the order item.
	 */
	private function add_order_item( &$order, &$item, $props ) {
		if ( empty( $props ) ) {
			return;
		}
		/**
		 * Remove id, order_id and instance_id from item as they are related to source site
		 */
		foreach ( array( 'id', 'order_id', 'instance_id' ) as $remove_key ) {
			if ( ! empty( $props[ $remove_key ] ) ) {
				unset( $props[ $remove_key ] );
			}
		}
		
		$item->set_props( $props );
		// Set meta if available
		if ( ! empty( $props['meta_data'] ) ) {
			foreach ( $props['meta_data'] as $meta ) {
				$item->update_meta_data( $meta['key'], $meta['value'] );
			}
		}
		$item->save();
		$order->add_item( $item );
	}

	public function disable_woocommerce_emails( $mailer ) {
		return function(  $to, $subject, $message, $headers = '', $attachments = array() ) {
			// Do nothing and return true to mimic successful sending
			return true;
		};
	}

	/**
	 * Wraps the WooCommerce event parsing process with email disabling functionality.
	 *
	 * This function temporarily disables WooCommerce emails during the parsing of a WooCommerce
	 * event to prevent emails from being sent during synchronization. It restores the email
	 * functionality after the parsing process is completed or if an exception occurs.
	 *
	 * @param mixed $response The response object to be modified during the event parsing.
	 * @param object $v The event data containing the event type and reference ID.
	 *
	 * @return mixed The modified response object after processing the WooCommerce event.
	 */
	public function parse_event_wrapper( $response, $v ) {
		if ( $v->event_type !== 'woocommerce' || empty( $v->reference_id ) || ! class_exists( 'WooCommerce' ) ) {
			return $response;
		}

		$wc_mail_ids = array( 'new_order', 'cancelled_order', 'failed_order', 'customer_on_hold_order', 'customer_processing_order', 'customer_completed_order', 'customer_refunded_order', 'customer_invoice', 'customer_note', 'customer_reset_password', 'customer_new_account', 'failed_renewal_authentication', 'failed_preorder_sca_authentication', 'failed_authentication_requested' );

		try {
			// Disable WooCommerce emails
			foreach ( $wc_mail_ids as $wc_mail_id ) {
				add_filter( 'woocommerce_email_enabled_' . $wc_mail_id, '__return_false', PHP_INT_MAX );
			}
			
			// Disable WooCommerce emails by replacing the mailer with the noop function
			add_filter( 'woocommerce_mail_callback', array( $this, 'disable_woocommerce_emails' ), PHP_INT_MAX );
			// parse order
			$response = $this->parse_event( $response, $v );

			// Restore WooCommerce emails
			remove_filter( 'woocommerce_mail_callback', array( $this, 'disable_woocommerce_emails' ), PHP_INT_MAX );
			foreach ( $wc_mail_ids as $wc_mail_id ) {
				remove_filter( 'woocommerce_email_enabled_' . $wc_mail_id, '__return_false', PHP_INT_MAX );
			}

			return $response;
		} catch ( \Exception $e ) {
			// Restore WooCommerce emails
			remove_filter( 'woocommerce_mail_callback', array( $this, 'disable_woocommerce_emails' ), PHP_INT_MAX );
			foreach ( $wc_mail_ids as $wc_mail_id ) {
				remove_filter( 'woocommerce_email_enabled_' . $wc_mail_id, '__return_false', PHP_INT_MAX );
			}

			return InstaWP_Sync_Helpers::sync_response( $v, array(), array(
				'status'  => 'pending',
				'message' => $e->getMessage(),
			) );
		}
	}

	public function parse_event( $response, $v ) {

		$reference_id = $v->reference_id;
		$details      = InstaWP_Sync_Helpers::object_to_array( $v->details );
		$log_data     = array();

		// add or update order
		if ( in_array( $v->event_slug, array( 'woocommerce_order_created', 'woocommerce_order_updated' ), true ) ) {
			$order_id = wc_get_order_id_by_order_key( $reference_id );

			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				$types = array( 'line_item', 'fee', 'shipping', 'coupon', 'tax' );

				foreach ( $order->get_items( $types ) as $item_id => $item ) {
					wc_delete_order_item( $item_id );
				}
			} else {
				$order = wc_create_order();
				$order_id = $order->get_id();
				try {
					$order->set_order_key( $reference_id );
				} catch ( Exception $e ) {
					return InstaWP_Sync_Helpers::sync_response( $v, array(), array(
						'status'  => 'pending',
						'message' => $e->getMessage(),
					) );
				}
			}

			// set order created date
			if ( ! empty( $details[ $this->order_created_date_key ] ) ) {
				$order->set_date_created( $details[ $this->order_created_date_key ] );
			}
			$previous_modified_date = $order->get_meta( $this->order_modified_date_key );
			// set order meta
			if ( ! empty( $details['meta_data'] ) && is_array( $details['meta_data'] ) ) {
				foreach ( $details['meta_data'] as $meta_key => $meta_value ) {
					if ( empty( $meta_key ) ) {
						continue;
					}
					$order->update_meta_data( $meta_key, $meta_value );
				}
			}

			kses_remove_filters();
			foreach ( $details['line_items'] as $line_item ) {
				if ( empty( $line_item ) || empty( $line_item['reference_id'] ) || empty( $line_item['data'] ) ) {
					continue;
				}

				$product_id = InstaWP_Sync_Helpers::get_post_by_reference( $line_item['post_data']['post_type'], $line_item['reference_id'], $line_item['post_data']['post_name'] );
				if ( ! $product_id ) {
					$product_id = InstaWP_Sync_Parser::create_or_update_post( $line_item['post_data'], $line_item['meta_data'], $line_item['reference_id'] );
				}

				// Product Variation 
				if ( ! empty( $line_item['variation_data'] ) ) {
					$variation_id = InstaWP_Sync_Helpers::get_post_by_reference( $line_item['variation_data']['post_data']['post_type'], $line_item['variation_data']['reference_id'], $line_item['variation_data']['post_data']['post_name'] );
					if ( ! $variation_id ) {
						$variation_id = InstaWP_Sync_Parser::create_or_update_post( $line_item['variation_data']['post_data'], $line_item['variation_data']['meta_data'], $line_item['variation_data']['reference_id'] );
					}
					$product = wc_get_product( $variation_id );
				} else {
					$product = wc_get_product( $product_id );
				}

				
				if ( empty( $product ) ) {
					continue;
				}

				$args = array();
				
				// Add line item arguments
				foreach ( array(
					'name',
					'subtotal',
					'total',
					'taxes',
					'tax_class',
				) as $line_item_key ) {
					if ( ! empty( $line_item['data'][ $line_item_key ] ) ) {
						$args[ $line_item_key ] = $line_item['data'][ $line_item_key ];
					}
				}
				
				// Add product to order
				$item_id = $order->add_product( $product, $line_item['quantity'], $args );

				// Set meta if available
				if ( ! empty( $item_id ) && ! empty( $line_item['data']['meta_data'] ) ) {
					// Get the WC_Order_Item_Product object by item ID
					$wc_item = $order->get_item( $item_id );
					if ( ! empty( $wc_item ) ) {
						foreach ( $line_item['data']['meta_data'] as $product_meta ) {
							$wc_item->update_meta_data( $product_meta['key'], $product_meta['value'] );
						}
						$wc_item->save();
					}
				}           
}
			kses_init_filters();

			foreach ( $details['shipping_lines'] as $shipping_item ) {
				$wc_item = new \WC_Order_Item_Shipping();
				$this->add_order_item(
					$order,
					$wc_item,
					$shipping_item
				);
			}

			foreach ( $details['fee_lines'] as $fee_item ) {
				$wc_item = new \WC_Order_Item_Fee();
				$this->add_order_item(
					$order,
					$wc_item,
					$fee_item
				);
			}

			foreach ( $details['tax_lines'] as $tax_item ) {
				$wc_item = new \WC_Order_Item_Tax();
				$this->add_order_item(
					$order,
					$wc_item,
					$tax_item
				);
			}

			foreach ( $details['coupon_lines'] as $coupon_item ) {
				if ( empty( $coupon_item ) ) {
					continue;
				}

				kses_remove_filters();
				InstaWP_Sync_Parser::create_or_update_post( $coupon_item['post_data'], $coupon_item['meta_data'], $coupon_item['reference_id'] );
				kses_init_filters();

				$coupon_code    = $coupon_item['data']['code'];
				$coupon         = new \WC_Coupon( $coupon_code );
				$discount_total = $coupon->get_amount();
				
				$coupon_item['data']['discount'] = isset( $coupon_item['data']['discount'] ) ? $coupon_item['data']['discount'] : $discount_total;
				$wc_item = new \WC_Order_Item_Coupon();
				$this->add_order_item(
					$order,
					$wc_item,
					$coupon_item['data']
				);
			}

			$order->set_address( $details['billing'], 'billing' );
			$order->set_address( $details['shipping'], 'shipping' );

			try {
				$order->set_payment_method( $details['payment_method'] );
				$order->set_payment_method_title( $details['payment_method_title'] );
			} catch ( Exception $e ) {
				return InstaWP_Sync_Helpers::sync_response( $v, array(), array(
					'status'  => 'pending',
					'message' => $e->getMessage(),
				) );
			}

			// set order status only if it has changed
			if ( empty( $previous_modified_date ) || empty( $details[ $this->order_modified_date_key ] ) || absint( $previous_modified_date ) < absint( $details[ $this->order_modified_date_key ] ) ) {
				$order->set_status( $details['status'] );
			}

			// set order modified date
			if ( ! empty( $details[ $this->order_modified_date_key ] ) ) {
				$order->set_date_modified( $details[ $this->order_modified_date_key ] );
			}
			
			$order->set_customer_ip_address( $details['customer_ip_address'] );
			$order->set_customer_user_agent( $details['customer_user_agent'] );
			$order->set_transaction_id( $details['transaction_id'] );
			$order->set_customer_note( $details['customer_note'] );
			$order->set_customer_id( $details['customer_id'] );
			$order->save();

			// Grab the order and recalculate
			$order = wc_get_order( $order_id );

			// Set order notes
			if ( ! empty( $details['order_notes'] ) && is_array( $details['order_notes'] ) && function_exists( 'wc_get_order_notes' ) && function_exists( 'wc_delete_order_note' ) ) {
				$order_notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
				
				// Delete order notes
				foreach ( $order_notes as $note ) {
					if ( ! empty( $note->id ) ) {
						wc_delete_order_note( $note->id );
					}
				}
				
				$details['order_notes'] = array_reverse( $details['order_notes'] );
				foreach ( $details['order_notes'] as $note ) {
					if ( empty( $note ) ) {
						continue;
					}
					// Add note
					$note_id = $order->add_order_note(
						$note['content'],
						$note['customer_note'],
						$note['added_by']
					);
	
					if ( ! empty( $note_id ) && is_numeric( $note_id ) && 0 < $note_id ) {
						// Update the comment date to match original
						$date_created = is_array($note['date_created']) ? $note['date_created']['date'] : $note['date_created'];
						
						global $wpdb;
						$wpdb->update(
							$wpdb->comments,
							array(
								'comment_date'     => $date_created,
								'comment_date_gmt' => get_gmt_from_date($date_created),
							),
							array( 'comment_ID' => $note_id ),
							array(
								'%s',
								'%s',
							),
							array(
								'%d',
							)
						);
					}
				}
			}

			$order->calculate_totals();
			$order->save();

			
			/**
			 * Add update order event at production site with order ID as order meta, 
			 * if it doesn't exist. It will be used to match staging order id with production 
			 * order id.
			 * 
			 */
			if ( ! instawp()->is_staging && ( empty( $details['meta_data'] ) || empty( $details['meta_data'][ $this->order_id_meta_key ] ) ) ) {
				// Add order ID as order meta
				$this->add_update_order_event( $order_id );
			}
		}

		// delete order
		if ( in_array( $v->event_slug, array( 'woocommerce_order_trashed', 'woocommerce_order_deleted' ), true ) ) {
			$order_id = wc_get_order_id_by_order_key( $reference_id );

			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				$order->delete( $v->event_slug === 'woocommerce_order_deleted' );
			} else {
				return InstaWP_Sync_Helpers::sync_response( $v, array(), array(
					'status'  => 'pending',
					'message' => 'Order not found',
				) );
			}
		}

		// add or update attribute
		if ( in_array( $v->event_slug, array( 'woocommerce_attribute_added', 'woocommerce_attribute_updated' ), true ) ) {
			$attribute_id   = wc_attribute_taxonomy_id_by_name( $v->reference_id );
			$attribute_data = array(
				'name'         => $details['attribute_label'],
				'slug'         => $details['attribute_name'],
				'type'         => $details['attribute_type'],
				'order_by'     => $details['attribute_orderby'],
				'has_archives' => isset( $details['attribute_public'] ) ? (int) $details['attribute_public'] : 0,
			);

			if ( $attribute_id ) {
				$attribute = wc_update_attribute( $attribute_id, $attribute_data );
			} else {
				$attribute = wc_create_attribute( $attribute_data );
			}

			if ( is_wp_error( $attribute ) ) {
				$log_data[ $v->id ] = $attribute->get_error_message();

				return InstaWP_Sync_Helpers::sync_response( $v, $log_data, array(
					'status'  => 'pending',
					'message' => $attribute->get_error_message(),
				) );
			}
		}

		if ( $v->event_slug === 'woocommerce_attribute_deleted' ) {
			$attribute_id = wc_attribute_taxonomy_id_by_name( $v->reference_id );

			if ( $attribute_id ) {
				$response = wc_delete_attribute( $attribute_id );

				if ( ! $response ) {
					return InstaWP_Sync_Helpers::sync_response( $v, array(), array(
						'status'  => 'pending',
						'message' => 'Failed',
					) );
				}
			} else {
				return InstaWP_Sync_Helpers::sync_response( $v, array(), array(
					'status'  => 'pending',
					'message' => 'Attribute not found',
				) );
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
	private function add_event( $event_name, $event_slug, $details, $source_id ) {
		switch ( $event_slug ) {
			case 'woocommerce_attribute_added':
			case 'woocommerce_attribute_updated':
				$title = $details['attribute_label'];
				break;
			case 'woocommerce_attribute_deleted':
				$title = ucfirst( str_replace( array( '-', '_' ), ' ', $source_id ) );
				break;
			case 'woocommerce_order_created':
			case 'woocommerce_order_updated':
				$title   = sprintf( __('Order %s', 'instawp-connect' ), '#' . $details );
				$details = $this->order_data( $details );
				break;
			case 'woocommerce_order_trashed':
			case 'woocommerce_order_deleted':
				$title = sprintf( __('Order %s', 'instawp-connect' ), '#' . $details );
				break;
			default:
				$title = $details;
		}
		InstaWP_Sync_DB::insert_update_event( $event_name, $event_slug, 'woocommerce', $source_id, $title, $details );
	}

	private function can_sync() {
		return InstaWP_Sync_Helpers::can_sync( 'wc' ) && class_exists( 'WooCommerce' );
	}

	/*
     * Get product gallery images
     */
	private function get_product_gallery( $product_id ) {
		$gallery = array();
		$product = $this->get_product( $product_id );

		if ( $product ) {
			$attachment_ids = ! empty( $product->get_gallery_image_ids() ) ? $product->get_gallery_image_ids() : array();

			foreach ( $attachment_ids as $attachment_id ) {
				$gallery[] = InstaWP_Sync_Parser::generate_attachment_data( $attachment_id, 'full' );
			}
		}

		return $gallery;
	}

	/**
	 * Set product gallery
	 */
	private function set_product_gallery( $product_id, $gallery_ids ) {
		$product = $this->get_product( $product_id );
		if ( $product && $gallery_ids ) {
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

	private function order_data( $order_id ) {
		$order      = wc_get_order( $order_id );
		$order_data = $order->get_data();
		$data       = $order_data;
		$data['order_notes'] = wc_get_order_notes( array( 'order_id' => $order_id ) );
		// Get order created date
		if ( ! empty( $order_data['date_created'] ) && is_a( $order_data['date_created'], 'WC_DateTime' ) ) {
			$data[ $this->order_created_date_key ] = $order_data['date_created']->getTimestamp();
		}

		// Get order created date
		$date_modified = time();
		if ( ! empty( $order_data['date_modified'] ) && is_a( $order_data['date_modified'], 'WC_DateTime' ) ) {
			$date_modified = $order_data['date_modified']->getTimestamp();
			$data[ $this->order_modified_date_key ] = $date_modified;
		}
		

		$data['meta_data'] = array();
		$data['meta_data'][ $this->order_modified_date_key ] = $date_modified;
		foreach ( $order_data['meta_data'] as $meta ) {
			if ( in_array( $meta->key, array( '_edit_lock' ) ) ) {
				continue;
			}

			$data['meta_data'][ $meta->key ] = $meta->value;
		}

		$data['fee_lines'] = array();
		foreach ( $order_data['fee_lines'] as $fee ) {
			$data['fee_lines'][] = $fee->get_data();
		}

		$data['shipping_lines'] = array();
		foreach ( $order_data['shipping_lines'] as $shipping ) {
			$data['shipping_lines'][] = $shipping->get_data();
		}

		$data['tax_lines'] = array();
		foreach ( $order_data['tax_lines'] as $tax ) {
			$data['tax_lines'][] = $tax->get_data();
		}

		$data['coupon_lines'] = array();
		foreach ( $order_data['coupon_lines'] as $coupon ) {
			$post_id = wc_get_coupon_id_by_code( $coupon['code'] );
			// Retrieve the coupon post data, post meta and its associated reference ID.
			$coupon_post   = InstaWP_Sync_Helpers::get_post_meta_reference_id( $post_id );

			if ( false === $coupon_post ) {
				continue;
			}
            // post_id, post_data, meta_data, reference_id key will be added by $coupon_post
			$data['coupon_lines'][] = array_merge( $coupon_post, array(
				'data' => $coupon->get_data(),
			) );
		}
		// Get line items
		$data['line_items'] = array();
		foreach ( $order_data['line_items'] as $product ) {
			$product_data   = $product->get_data();
			// Retrieve the product post data, post meta and its associated reference ID.
			$product_post   = InstaWP_Sync_Helpers::get_post_meta_reference_id( $product_data['product_id'] );

			if ( false === $product_post ) {
				continue;
			}

			// post_id, post_data, meta_data, reference_id key will be added by $product_post
			$data['line_items'][] = array_merge( $product_post, array(
				'quantity'       => $product_data['quantity'],
				'data'           => $product_data,
				'variation_data' => empty( $product_data['variation_id'] ) ? null : InstaWP_Sync_Helpers::get_post_meta_reference_id( $product_data['variation_id'] ),
			) );
		}

		return $data;
	}
}

new InstaWP_Sync_WC();