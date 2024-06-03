<?php

defined( 'ABSPATH' ) || die;

class InstaWP_Rest_Api_WooCommerce extends InstaWP_Rest_Api {
	
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WooCommerce') ) {
			add_action( 'rest_api_init', array( $this, 'add_api_routes' ) );
		}
	}

	public function add_api_routes() {
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/woocommerce', '/summary', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_summary' ),
			'args'                => array(
				'limit'     => array(
					'default'           => -1,
					'validate_callback' => 'is_numeric',
				),
				'offset'    => array(
					'default'           => 0,
					'validate_callback' => 'is_numeric',
				),
				'compare'   => array(
					'default'           => false,
					'validate_callback' => 'is_boolean',
				),
				'from_date' => array(
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return strtotime( $param ) !== false;
					},
				),
				'to_date'   => array(
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return strtotime( $param ) !== false;
					},
				),
			),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/woocommerce', '/graph', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_graph' ),
			'args'                => array(
				'limit'       => array(
					'default'           => -1,
					'validate_callback' => 'is_numeric',
				),
				'offset'      => array(
					'default'           => 0,
					'validate_callback' => 'is_numeric',
				),
				'compare'     => array(
					'default'           => false,
					'validate_callback' => 'is_boolean',
				),
				'from_date'   => array(
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return strtotime( $param ) !== false;
					},
				),
				'to_date'     => array(
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return strtotime( $param ) !== false;
					},
				),
				'type'        => array(
					'default'           => 'orders',
					'validate_callback' => function( $param, $request, $key ) {
						return in_array( $param, array( 'orders', 'sales', 'customers' ), true );
					},
				),
				'granularity' => array(
					'default'           => 'daily',
					'validate_callback' => function( $param, $request, $key ) {
						return in_array( $param, array( 'hourly', 'daily', 'weekly', 'monthly', 'yearly' ), true );
					},
				),
			),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/woocommerce', '/products', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_products' ),
			'args'                => array(
				'limit'  => array(
					'default'           => 10,
					'validate_callback' => 'is_numeric',
				),
				'offset' => array(
					'default'           => 0,
					'validate_callback' => 'is_numeric',
				),
			),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/woocommerce', '/orders', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_orders' ),
			'args'                => array(
				'limit'  => array(
					'default'           => 10,
					'validate_callback' => 'is_numeric',
				),
				'offset' => array(
					'default'           => 0,
					'validate_callback' => 'is_numeric',
				),
			),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/woocommerce', '/users', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_customers' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function get_summary( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response  = array();
		$limit     = $request->get_param( 'limit' );
		$offset    = $request->get_param( 'offset' );
		$from_date = $request->get_param( 'from_date' );
		$to_date   = $request->get_param( 'to_date' );
		$compare   = $request->get_param( 'compare' );

		$response['current'] = $this->calculate_orders_summary( array(
			'limit'        => $limit,
			'offset'       => $offset,
			'date_created' => date( 'Y-m-d H:i:s', strtotime( $from_date ) ) . '...' . date( 'Y-m-d H:i:s', strtotime( $to_date ) ),
			'return'       => 'ids',
		) );
		$response['current']['customers'] = $this->calculate_customers_summary( $from_date, $to_date );

		if ( $compare ) {
			$date1 = new DateTime( $from_date );
			$date2 = new DateTime( $to_date );

			$interval = $date1->diff( $date2 );
			$days     = $interval->days;

			$from_date = strtotime( $from_date . " -$days days" );
			$to_date   = strtotime( $to_date . " -$days days" );

			$from_date = date( 'Y-m-d H:i:s', $from_date );
			$to_date   = date( 'Y-m-d H:i:s', $to_date );

			$response['previous'] = $this->calculate_orders_summary( array(
				'limit'        => $limit,
				'offset'       => $offset,
				'date_created' => date( 'Y-m-d H:i:s', strtotime( $from_date ) ) . '...' . date( 'Y-m-d H:i:s', strtotime( $to_date ) ),
				'return'       => 'ids',
			) );
			$response['previous']['customers'] = $this->calculate_customers_summary( $from_date, $to_date );
		}

		return $this->send_response( $response );
	}

	public function get_graph( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response    = array();
		$limit       = $request->get_param( 'limit' );
		$offset      = $request->get_param( 'offset' );
		$from_date   = $request->get_param( 'from_date' );
		$to_date     = $request->get_param( 'to_date' );
		$compare     = $request->get_param( 'compare' );
		$type        = $request->get_param( 'type' );
		$granularity = $request->get_param( 'granularity' );

		if ( in_array( $type, array( 'orders', 'sales' ), true ) ) {
			$orders = wc_get_orders( array(
				'limit'        => $limit,
				'offset'       => $offset,
				'date_created' => date( 'Y-m-d H:i:s', strtotime( $from_date ) ) . '...' . date( 'Y-m-d H:i:s', strtotime( $to_date ) ),
			) );

			$response['current'] = ( $type === 'orders' ) ? $this->get_orders_by_granularity( $orders, $granularity ) : $this->get_sales_by_granularity( $orders, $granularity );
		} else {
			$customers = $this->get_customers_registered( $from_date, $to_date );
			$response['current'] = $this->get_customers_by_granularity( $customers, $granularity );
		}

		if ( $compare ) {
			$date1 = new DateTime( $from_date );
			$date2 = new DateTime( $to_date );

			$interval = $date1->diff( $date2 );
			$days     = $interval->days;

			$from_date = strtotime( $from_date . " -$days days" );
			$to_date   = strtotime( $to_date . " -$days days" );

			$from_date = date( 'Y-m-d H:i:s', $from_date );
			$to_date   = date( 'Y-m-d H:i:s', $to_date );

			if ( in_array( $type, array( 'orders', 'sales' ), true ) ) {
				$orders = wc_get_orders( array(
					'limit'        => $limit,
					'offset'       => $offset,
					'date_created' => date( 'Y-m-d H:i:s', strtotime( $from_date ) ) . '...' . date( 'Y-m-d H:i:s', strtotime( $to_date ) ),
				) );

				$response['previous'] = ( $type === 'orders' ) ? $this->get_orders_by_granularity( $orders, $granularity ) : $this->get_sales_by_granularity( $orders, $granularity );
			} else {
				$customers = $this->get_customers_registered( $from_date, $to_date );
				$response['previous'] = $this->get_customers_by_granularity( $customers, $granularity );
			}
		}

		return $this->send_response( $response );
	}

	/**
	 * Handle response for WooCommerce Products API.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_products( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$limit    = $request->get_param( 'limit' );
		$offset   = $request->get_param( 'offset' );

		$products = $this->get_wc_products( array(
			'limit'  => $limit,
			'offset' => $offset,
		) );

		return $this->send_response( $products );
	}

	/**
	 * Handle response WooCommerce Orders API.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_orders( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$limit  = $request->get_param( 'limit' );
		$offset = $request->get_param( 'offset' );

		$orders = $this->get_wc_orders( array(
			'limit'  => $limit,
			'offset' => $offset,
		) );

		return $this->send_response( $orders );
	}

	/**
	 * Handle response for WooCommerce Customers API.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_customers( WP_REST_Request $request ) {

		$response = $this->validate_api_request( $request );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$response  = array();
		$customers = get_users( array( 'role' => array( 'customer' ) ) );
		foreach ( $customers as $customer ) {
			$customer   = new \WC_Customer( $customer->ID );
			$response[] = $this->get_formatted_item_data( $customer );
		}

		return $this->send_response( $response );
	}

	public function get_product_data( $product ) {
		$product = wc_get_product( $product );
		$data    = $this->get_product_data_core( $product );

		// Add variations to variable products.
		if ( $product->is_type( 'variable' ) && $product->has_child() ) {
			$data['variations'] = $this->get_variation_data( $product );
		}

		// Add grouped products data.
		if ( $product->is_type( 'grouped' ) && $product->has_child() ) {
			$data['grouped_products'] = $product->get_children();
		}

		return $data;
	}

	protected function get_product_data_core( $product ) {
		return array(
			'id'                 => $product->get_id(),
			'name'               => $product->get_name(),
			'slug'               => $product->get_slug(),
			'permalink'          => $product->get_permalink(),
			'date_created'       => wc_rest_prepare_date_response( $product->get_date_created(), false ),
			'date_created_gmt'   => wc_rest_prepare_date_response( $product->get_date_created() ),
			'date_modified'      => wc_rest_prepare_date_response( $product->get_date_modified(), false ),
			'date_modified_gmt'  => wc_rest_prepare_date_response( $product->get_date_modified() ),
			'type'               => $product->get_type(),
			'status'             => $product->get_status(),
			'featured'           => $product->is_featured(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'description'        => wpautop( do_shortcode( $product->get_description() ) ),
			'short_description'  => apply_filters( 'woocommerce_short_description', $product->get_short_description() ),
			'sku'                => $product->get_sku(),
			'price'              => $product->get_price(),
			'regular_price'      => $product->get_regular_price(),
			'sale_price'         => $product->get_sale_price() ? $product->get_sale_price() : '',
			'date_on_sale_from'  => $product->get_date_on_sale_from() ? date( 'Y-m-d', $product->get_date_on_sale_from()->getTimestamp() ) : '',
			'date_on_sale_to'    => $product->get_date_on_sale_to() ? date( 'Y-m-d', $product->get_date_on_sale_to()->getTimestamp() ) : '',
			'price_html'         => $product->get_price_html(),
			'on_sale'            => $product->is_on_sale(),
			'purchasable'        => $product->is_purchasable(),
			'total_sales'        => $product->get_total_sales(),
			'virtual'            => $product->is_virtual(),
			'downloadable'       => $product->is_downloadable(),
			'download_limit'     => $product->get_download_limit(),
			'download_expiry'    => $product->get_download_expiry(),
			'download_type'      => 'standard',
			'external_url'       => $product->is_type( 'external' ) ? $product->get_product_url() : '',
			'button_text'        => $product->is_type( 'external' ) ? $product->get_button_text() : '',
			'tax_status'         => $product->get_tax_status(),
			'tax_class'          => $product->get_tax_class(),
			'manage_stock'       => $product->managing_stock(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'in_stock'           => $product->is_in_stock(),
			'backorders'         => $product->get_backorders(),
			'backorders_allowed' => $product->backorders_allowed(),
			'backordered'        => $product->is_on_backorder(),
			'sold_individually'  => $product->is_sold_individually(),
			'weight'             => $product->get_weight(),
			'dimensions'         => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
			),
			'shipping_required'  => $product->needs_shipping(),
			'shipping_taxable'   => $product->is_shipping_taxable(),
			'shipping_class'     => $product->get_shipping_class(),
			'shipping_class_id'  => $product->get_shipping_class_id(),
			'reviews_allowed'    => $product->get_reviews_allowed(),
			'average_rating'     => wc_format_decimal( $product->get_average_rating(), 2 ),
			'rating_count'       => $product->get_rating_count(),
			'related_ids'        => array_map( 'absint', array_values( wc_get_related_products( $product->get_id() ) ) ),
			'upsell_ids'         => array_map( 'absint', $product->get_upsell_ids() ),
			'cross_sell_ids'     => array_map( 'absint', $product->get_cross_sell_ids() ),
			'parent_id'          => $product->get_parent_id(),
			'purchase_note'      => wpautop( do_shortcode( wp_kses_post( $product->get_purchase_note() ) ) ),
			'variations'         => array(),
			'grouped_products'   => array(),
			'menu_order'         => $product->get_menu_order(),
		);
	}

	protected function get_variation_data( $product ) {
		$variations = array();

		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || ! $variation->exists() ) {
				continue;
			}

			$variations[] = array(
				'id'                 => $variation->get_id(),
				'date_created'       => wc_rest_prepare_date_response( $variation->get_date_created(), false ),
				'date_created_gmt'   => wc_rest_prepare_date_response( $variation->get_date_created() ),
				'date_modified'      => wc_rest_prepare_date_response( $variation->get_date_modified(), false ),
				'date_modified_gmt'  => wc_rest_prepare_date_response( $variation->get_date_modified() ),
				'permalink'          => $variation->get_permalink(),
				'sku'                => $variation->get_sku(),
				'price'              => $variation->get_price(),
				'regular_price'      => $variation->get_regular_price(),
				'sale_price'         => $variation->get_sale_price(),
				'date_on_sale_from'  => $variation->get_date_on_sale_from() ? date( 'Y-m-d', $variation->get_date_on_sale_from()->getTimestamp() ) : '',
				'date_on_sale_to'    => $variation->get_date_on_sale_to() ? date( 'Y-m-d', $variation->get_date_on_sale_to()->getTimestamp() ) : '',
				'on_sale'            => $variation->is_on_sale(),
				'purchasable'        => $variation->is_purchasable(),
				'visible'            => $variation->is_visible(),
				'virtual'            => $variation->is_virtual(),
				'downloadable'       => $variation->is_downloadable(),
				'download_limit'     => '' !== $variation->get_download_limit() ? (int) $variation->get_download_limit() : -1,
				'download_expiry'    => '' !== $variation->get_download_expiry() ? (int) $variation->get_download_expiry() : -1,
				'tax_status'         => $variation->get_tax_status(),
				'tax_class'          => $variation->get_tax_class(),
				'manage_stock'       => $variation->managing_stock(),
				'stock_quantity'     => $variation->get_stock_quantity(),
				'in_stock'           => $variation->is_in_stock(),
				'backorders'         => $variation->get_backorders(),
				'backorders_allowed' => $variation->backorders_allowed(),
				'backordered'        => $variation->is_on_backorder(),
				'weight'             => $variation->get_weight(),
				'dimensions'         => array(
					'length' => $variation->get_length(),
					'width'  => $variation->get_width(),
					'height' => $variation->get_height(),
				),
				'shipping_class'     => $variation->get_shipping_class(),
				'shipping_class_id'  => $variation->get_shipping_class_id(),
			);
		}

		return $variations;
	}

	public function get_wc_orders( $args ) {
		$orders_data = array();
		$orders      = wc_get_orders( $args );

		foreach ( $orders as $order ) {
			$order_data = $order->get_data();
			$data       = $order_data;

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

			$data['line_items'] = array();
			foreach ( $order_data['line_items'] as $product ) {
				$data['line_items'][] = $product->get_data();
			}

			$orders_data[] = $data;
		}

		return $orders_data;
	}

	public function get_wc_products( $args ) {
		$products_data = array();
		$products    = wc_get_products( $args );

		foreach ( $products as $product ) {
			$products_data[] = $this->get_product_data( $product );
		}

		return $products_data;
	}

	public function calculate_orders_summary( $args ) {
		$orders   = wc_get_orders( $args );

		$total_sales    = 0;
		$net_sales      = 0;
		$total_tax      = 0;
		$total_refunded = 0;

		foreach ( $orders as $order_id ) {
			$order       = wc_get_order( $order_id );
			$total_sales += $order->get_total();
			$net_sales   += $order->get_total() - $order->get_total_tax() - $order->get_shipping_total();
			$total_tax   += $order->get_total_tax();

			$refunds = $order->get_refunds();
			foreach ( $refunds as $refund ) {
				$total_refunded += $refund->get_amount();
			}
		}

		return array(
			'net_sales'      => $net_sales,
			'total_sales'    => $total_sales,
			'total_tax'      => $total_tax,
			'total_refunded' => $total_refunded,
			'orders'         => count( $orders ),
		);
	}

	public function calculate_customers_summary( $from_date, $to_date ) {
		$args = array(
			'role'       => 'customer',
			'date_query' => array(
				'after'     => date( 'Y-m-d H:i:s', strtotime( $from_date ) ),
				'before'    => date( 'Y-m-d H:i:s', strtotime( $to_date ) ),
				'inclusive' => true,
			),
			'fields'     => 'ID',
		);
		$user_query = new WP_User_Query( $args );

		return $user_query->get_total();
	}

	public function get_customers_registered( $from_date, $to_date ) {
		$args = array(
			'role'       => 'customer',
			'date_query' => array(
				'after'     => date( 'Y-m-d H:i:s', strtotime( $from_date ) ),
				'before'    => date( 'Y-m-d H:i:s', strtotime( $to_date ) ),
				'inclusive' => true,
			),
			'fields'     => array( 'ID', 'user_registered' ),
		);
		$user_query = new WP_User_Query( $args );

		return $user_query->get_results();
	}

	public function get_orders_by_granularity( $orders, $granularity ) {
		$grouped = array();

		foreach ( $orders as $order ) {
			$date_created = $order->get_date_created();
			if ( ! $date_created ) continue;

			switch ( $granularity ) {
				case 'hourly':
					$key = $date_created->date( 'Y-m-d H:00:00' );
					break;
				case 'daily':
					$key = $date_created->date( 'Y-m-d' );
					break;
				case 'weekly':
					$key = $date_created->date( 'o-W' );
					break;
				case 'monthly':
					$key = $date_created->date( 'Y-m' );
					break;
				case 'yearly':
					$key = $date_created->date( 'Y' );
					break;
				default:
					$key = $date_created->date( 'Y-m-d' );
			}

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = 0;
			}

			++$grouped[ $key ];
		}

		return $grouped;
	}

	public function get_sales_by_granularity( $orders, $granularity ) {
		$grouped = array();

		foreach ( $orders as $order ) {
			$date_created = $order->get_date_created();
			if ( ! $date_created ) continue;

			$net_sales = $order->get_total() - $order->get_total_tax() - $order->get_shipping_total();

			switch ( $granularity ) {
				case 'hourly':
					$key = $date_created->date( 'Y-m-d H:00:00' );
					break;
				case 'daily':
					$key = $date_created->date( 'Y-m-d' );
					break;
				case 'weekly':
					$key = $date_created->date( 'o-W' );
					break;
				case 'monthly':
					$key = $date_created->date( 'Y-m' );
					break;
				case 'yearly':
					$key = $date_created->date( 'Y' );
					break;
				default:
					$key = $date_created->date( 'Y-m-d' );
			}

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = 0;
			}

			$grouped[ $key ] = $grouped[ $key ] + $net_sales;
		}

		return $grouped;
	}

	function get_customers_by_granularity( $users, $granularity ) {
		$grouped = array();

		foreach ( $users as $user ) {
			$date_registered = new DateTime( $user->user_registered );

			switch ( $granularity ) {
				case 'hourly':
					$key = $date_registered->format( 'Y-m-d H:00:00' );
					break;
				case 'daily':
					$key = $date_registered->format( 'Y-m-d' );
					break;
				case 'weekly':
					$key = $date_registered->format( 'o-W' );
					break;
				case 'monthly':
					$key = $date_registered->format( 'Y-m' );
					break;
				case 'yearly':
					$key = $date_registered->format( 'Y' );
					break;
				default:
					$key = $date_registered->format( 'Y-m-d' );
			}

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = 0;
			}

			++$grouped[ $key ];
		}

		return $grouped;
	}

	protected function get_formatted_item_data( $object_data ) {
		$formatted_data                 = $this->get_formatted_item_data_core( $object_data );
		$formatted_data['orders_count'] = $object_data->get_order_count();
		$formatted_data['total_spent']  = $object_data->get_total_spent();

		return $formatted_data;
	}

	protected function get_formatted_item_data_core( $object_data ) {
		$data        = $object_data->get_data();
		$format_date = array( 'date_created', 'date_modified' );

		// Format date values.
		foreach ( $format_date as $key ) {
			// Date created is stored UTC, date modified is stored WP local time.
			$datetime              = 'date_created' === $key && is_subclass_of( $data[ $key ], 'DateTime' ) ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $data[ $key ]->getTimestamp() ) ) : $data[ $key ];
			$data[ $key ]          = wc_rest_prepare_date_response( $datetime, false );
			$data[ $key . '_gmt' ] = wc_rest_prepare_date_response( $datetime );
		}

		$formatted_data = array(
			'id'                 => $object_data->get_id(),
			'date_created'       => $data['date_created'],
			'date_created_gmt'   => $data['date_created_gmt'],
			'date_modified'      => $data['date_modified'],
			'date_modified_gmt'  => $data['date_modified_gmt'],
			'email'              => $data['email'],
			'first_name'         => $data['first_name'],
			'last_name'          => $data['last_name'],
			'role'               => $data['role'],
			'username'           => $data['username'],
			'billing'            => $data['billing'],
			'shipping'           => $data['shipping'],
			'is_paying_customer' => $data['is_paying_customer'],
			'avatar_url'         => $object_data->get_avatar_url(),
		);

		if ( wc_current_user_has_role( 'administrator' ) ) {
			$formatted_data['meta_data'] = $data['meta_data'];
		}

		return $formatted_data;
	}
}

new InstaWP_Rest_Api_WooCommerce();