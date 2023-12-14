<?php
/**
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'InstaWP_Backup_Api' ) ) {
	require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-rest-api.php';
}

class InstaWP_Sync_Apis extends InstaWP_Backup_Api {

	private $wpdb;

	private $tables;

	private $logs = [];

	public function __construct() {
		parent::__construct();

		global $wpdb;

		$this->wpdb   = $wpdb;
		$this->tables = InstaWP_Sync_DB::$tables;

		add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
	}

	public function add_api_routes() {
		register_rest_route( $this->namespace . '/' . $this->version, '/mark-staging', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'mark_staging' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $this->namespace . '/' . $this->version, '/sync', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'events_receiver' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Handle events receiver api
	 *
	 * @param WP_REST_Request $req
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function mark_staging( WP_REST_Request $req ) {
		$response = $this->validate_api_request( $req );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$body    = $req->get_body();
		$request = json_decode( $body );

		if ( ! isset( $request->parent_connect_id ) ) {
			return new WP_Error( 400, esc_html__( 'Invalid connect ID', 'instawp-connect' ) );
		}

		delete_option( 'instawp_sync_parent_connect_data' );
		update_option( 'instawp_sync_connect_id', intval( $request->parent_connect_id ) );
		update_option( 'instawp_is_staging', true );
		instawp_get_source_site_detail();

		return $this->send_response( [
			'status'  => true,
			'message' => __( 'Site has been marked as staging', 'instawp-connect' ),
		] );
	}

	/**
	 * Handle events receiver api
	 *
	 * @param WP_REST_Request $req
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function events_receiver( WP_REST_Request $req ) {

		$response = $this->validate_api_request( $req );
		if ( is_wp_error( $response ) ) {
			return $this->throw_error( $response );
		}

		$body    = $req->get_body();
		$bodyArr = json_decode( $body );

		if ( ! isset( $bodyArr->encrypted_contents ) ) {
			return new WP_Error( 400, esc_html__( 'Invalid data', 'instawp-connect' ) );
		}

		$encrypted_contents = json_decode( $bodyArr->encrypted_contents );
		$sync_id            = $bodyArr->sync_id;
		$source_connect_id  = $bodyArr->source_connect_id;
		$source_url         = $bodyArr->source_url;
		$is_enabled         = false;
		$changes            = [];

		if ( get_option( 'instawp_is_event_syncing' ) ) {
			$is_enabled = true;
		}

		delete_option( 'instawp_is_event_syncing' );

		if ( ! empty( $encrypted_contents ) && is_array( $encrypted_contents ) ) {
			$sync_response   = [];
			$count           = 1;
			$progress_status = 'pending';
			$total_op        = count( $encrypted_contents );
			$progress        = intval( $count / $total_op * 100 );
			$sync_message    = $bodyArr->sync_message ?? '';
			$progress_status = ( $progress > 100 ) ? 'in_progress' : 'completed';

			foreach ( $encrypted_contents as $v ) {
				$isResult = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT ID FROM " . INSTAWP_DB_TABLE_EVENT_SYNC_LOGS . " WHERE event_hash = %s ", $v->event_hash ) );

				if ( $isResult ) {
					$response_data   = InstaWP_Sync_Helpers::sync_response( $v );
					$sync_response[] = $response_data['data'];
				} else {
					if ( empty( $v->event_slug ) || empty( $v->details ) ) {
						continue;
					}

					$source_id    = ( ! empty( $v->source_id ) ) ? intval( $v->source_id ) : null;
					$v->source_id = $source_id;

					$response_data = apply_filters( 'INSTAWP_CONNECT/Filters/process_two_way_sync', [], $v );
					if ( ! empty( $response_data['data'] ) ) {
						$sync_response[] = $response_data['data'];
					}

					if ( ! empty( $response_data['log_data'] ) ) {
						$this->logs = array_merge( $this->logs, $response_data['log_data'] );
					}

					/**
					 * Customizer settings update
					 */

					if ( isset( $v->event_slug ) && $v->event_slug == 'customizer_changes' ) {
						$details = isset( $v->details ) ? $v->details : '';

						#custom logo
						$this->customizer_custom_logo( $details->custom_logo );

						#background image
						$this->customizer_background_image( $details->background_image );

						#site icon
						$this->customizer_site_icon( $details->site_icon );

						#background color
						if ( isset( $details->background_color ) && ! empty( $details->background_color ) ) {
							set_theme_mod( 'background_color', $details->background_color );
						}

						#Site Title
						update_option( 'blogname', $details->name );

						#Tagline
						$this->blogDescription( $details->description );

						#Homepage Settings
						if ( isset( $details->show_on_front ) && ! empty( $details->show_on_front ) ) {
							update_option( 'show_on_front', $details->show_on_front );
						}

						#for 'Astra' theme
						if ( isset( $details->astra_settings ) && ! empty( $details->astra_settings ) ) {
							$astra_settings = $this->object_to_array( $details->astra_settings );
							update_option( 'astra-settings', $astra_settings );
						}

						#nav menu locations
						if ( ! empty( $details->nav_menu_locations ) ) {
							$menu_array = (array) $details->nav_menu_locations;
							set_theme_mod( 'nav_menu_locations', $menu_array );
						}

						#Custom css post id
						$custom_css_post = (array) $details->custom_css_post;
						if ( ! empty( $details->custom_css_post ) ) {
							if ( get_post_status( $custom_css_post['ID'] ) ) {
								#The post exists,Then update
								$postData = $this->postData( $custom_css_post, 'update' );
								wp_update_post( $postData );

							} else {
								$postData = $this->postData( $custom_css_post, 'insert' );
								#The post does not exist,Then insert
								wp_insert_post( $postData );
							}
							set_theme_mod( 'custom_css_post_id', $custom_css_post['ID'] );
						}
						$current_theme = wp_get_theme();
						if ( $current_theme->Name == 'Astra' ) { #for 'Astra' theme
							$astra_theme_setting = isset( $details->astra_theme_customizer_settings ) ? (array) $details->astra_theme_customizer_settings : '';
							$this->setAstraCostmizerSetings( $astra_theme_setting );
						} else if ( $current_theme->Name == 'Divi' ) {  #for 'Divi' theme
							$divi_settings = isset( $details->divi_settings ) ? (array) $details->divi_settings : '';
							if ( ! empty( $divi_settings ) && is_array( $divi_settings ) ) {
								update_option( 'et_divi', $divi_settings );
							}
						}

						#message
						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
						#changes

					}

					/**
					 * Woocommerce attributes
					 */

					#create&upadte woocommerce attribute
					if ( isset( $v->event_slug ) && ( $v->event_slug == 'woocommerce_attribute_added' || $v->event_slug == 'woocommerce_attribute_updated' ) ) {
						$details = isset( $v->details ) ? (array) $v->details : '';
						if ( ! empty( $details ) ) {
							$attribute = wc_get_attribute( 208 );
							if ( ! empty( $attribute ) ) {
								unset( $details['id'] );
								wc_update_attribute( $v->source_id, $attribute );

								#message
								$message         = 'Sync successfully.';
								$status          = 'completed';
								$sync_response[] = $this->sync_opration_response( $status, $message, $v );
								#changes

							} else {
								$this->woocommerce_create_attribute( $v->source_id, $details );

								#message
								$message         = 'Sync successfully.';
								$status          = 'completed';
								$sync_response[] = $this->sync_opration_response( $status, $message, $v );
								#changes


							}
						}
					}

					if ( isset( $v->event_slug ) && $v->event_slug == 'woocommerce_attribute_deleted' ) {
						wc_delete_attribute( $v->source_id );
						#message
						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
						#changes

					}

					/**
					 * Users actions
					 */


					/*
					* widget
					*/
					if ( isset( $v->event_type ) && $v->event_type == 'widget' ) {
						$widget_block = (array) $v->details->widget_block;
						$appp         = (array) $v->details;
						$dataIns      = [
							'data' => json_encode( $appp )
						];
						InstaWP_Sync_DB::insert( 'wp_testing', $dataIns );

						$widget_block_arr = [];
						foreach ( $widget_block as $widget_key => $widget_val ) {
							if ( $widget_key == '_multiwidget' ) {
								$widget_block_arr[ $widget_key ] = $widget_val;
							} else {
								$widget_val_arr                  = (array) $widget_val;
								$widget_block_arr[ $widget_key ] = [ 'content' => $widget_val_arr['content'] ];
							}
						}
						update_option( 'widget_block', $widget_block_arr );
						#message
						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
						#changes

					}

					//record logs
					$this->event_sync_logs( $v, $source_url );
				}

				/*
				* Update api for cloud
				*/
				#Sync update
				$syncUpdate = [
					'progress' => $progress,
					'status'   => $progress_status,
					'message'  => $sync_message,
					'changes'  => [ 'changes' => $changes, 'sync_response' => $sync_response, 'logs' => $this->logs ],
				];
				$this->sync_update( $sync_id, $syncUpdate );
				$count ++;
			}
		}

		#Sync history save
		$this->sync_history_save( $body, $changes, 'Complete' );

		#enable is back if syncing already enabled at the destination
		if ( $is_enabled ) {
			update_option( 'instawp_is_event_syncing', 1 );
		}

		return $this->send_response( [
			'sync_id'            => $sync_id,
			'encrypted_contents' => $encrypted_contents,
			'source_connect_id'  => $source_connect_id,
			'changes'            => [
				'changes'       => $changes,
				'sync_response' => $sync_response
			],
		] );
	}

	public function event_sync_logs( $data, $source_url ) {
		$data = [
			'event_id'   => $data->id,
			'event_hash' => $data->event_hash,
			'source_url' => $source_url,
			'data'       => json_encode( $data->details ),
			'logs'       => $this->logs[ $data->id ] ?? '',
			'date'       => date( 'Y-m-d H:i:s' ),
		];
		InstaWP_Sync_DB::insert( INSTAWP_DB_TABLE_EVENT_SYNC_LOGS, $data );
	}

	/**
	 * This function is for upload media which are coming form widgets.
	 */
	public function upload_widgets_media( $media = null, $content = null ) {
		$media      = json_decode( reset( $media ) );
		$new        = $old = [];
		$newContent = '';
		if ( ! empty( $media ) ) {
			foreach ( $media as $v ) {
				$v = (array) $v;
				if ( isset( $v['attachment_id'] ) && isset( $v['attachment_url'] ) ) {
					$attachment_id = $this->insert_attachment( $v['attachment_id'], $v['attachment_url'] );
					$new[]         = wp_get_attachment_url( $attachment_id );
					$old[]         = $v['attachment_url'];
				}
			}
			$newContent = str_replace( $old, $new, $content ); #str_replace(old,new,str)
		}

		return $newContent;
	}

	public function user_id_exists( $user_id ) {
		$table_name = $this->wpdb->prefix . 'users';
		$count      = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE ID = %d", $user_id ) );

		return intval( $count ) === 1;
	}

	public function blogDescription( $v = null ) {
		$this->wpdb->update( $this->wpdb->prefix . 'options', [ 'option_value' => $v ], array( 'option_name' => 'blogdescription' ) );
	}

	/**
	 * object to array conversation
	 */
	public function object_to_array( $data ) {
		if ( ( ! is_array( $data ) ) and ( ! is_object( $data ) ) ) {
			return;
		}
		$result = array();
		$data   = (array) $data;
		foreach ( $data as $key => $value ) {
			if ( is_object( $value ) ) {
				$value = (array) $value;
			}
			if ( is_array( $value ) ) {
				$result[ $key ] = $this->object_to_array( $value );
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Create woocommerce attribute
	 */
	public function woocommerce_create_attribute( $source_id, $data = null ) {
		$format               = array( '%s', '%s', '%s', '%s', '%d' );
		$data['attribute_id'] = intval( $source_id );
		$results              = $this->wpdb->insert(
			$this->wpdb->prefix . 'woocommerce_attribute_taxonomies',
			$data,
			$format
		);

		if ( is_wp_error( $results ) ) {
			return new WP_Error( 'cannot_create_attribute', 'Can not create attribute!', array( 'status' => 400 ) );
		}
		$id = $this->wpdb->insert_id;
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
		WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
	}

	public function customizer_site_icon( $data = null ) {
		$attachment_id = $data->id;
		$url           = $data->url;
		if ( isset( $attachment_id ) && ! empty( $attachment_id ) ) {
			$attUrl = wp_get_attachment_url( intval( $attachment_id ) );
			if ( ! empty( $attUrl ) ) {
				update_option( 'site_icon', $attachment_id );
			} else {
				$attachment_id = $this->insert_attachment( $attachment_id, $url );
				update_option( 'site_icon', $attachment_id );
			}
		} else {
			update_option( 'site_icon', $attachment_id );
		}
	}

	public function customizer_custom_logo( $data ) {
		$attachment_id = $data->id;
		$url           = $data->url;
		if ( isset( $attachment_id ) && ! empty( $attachment_id ) ) {
			$attUrl = wp_get_attachment_url( intval( $attachment_id ) );
			if ( ! empty( $attUrl ) ) {
				set_theme_mod( 'custom_logo', $attachment_id );
			} else {
				$attachment_id = $this->insert_attachment( $attachment_id, $url );
				set_theme_mod( 'custom_logo', $attachment_id );
			}
		} else {
			set_theme_mod( 'custom_logo', $attachment_id );
		}
	}

	public function customizer_background_image( $data = null ) {
		$attachment_id = $data->id;
		$url           = $data->url;
		if ( isset( $attachment_id ) && ! empty( $attachment_id ) ) {
			$attUrl = wp_get_attachment_url( intval( $attachment_id ) );
			if ( ! empty( $attUrl ) ) {
				set_theme_mod( 'background_image', $attUrl );
			} else {
				$attachment_id  = $this->insert_attachment( $attachment_id, $url );
				$attachment_url = wp_get_attachment_url( intval( $attachment_id ) );
				set_theme_mod( 'background_image', $attachment_url );
			}
		} else {
			set_theme_mod( 'background_image', $url );
		}

		if ( isset( $data->background_preset ) && ! empty( $data->background_preset ) ) {
			set_theme_mod( 'background_preset', $data->background_preset );
		}

		if ( isset( $data->background_size ) && ! empty( $data->background_size ) ) {
			set_theme_mod( 'background_size', $data->background_size );
		}

		if ( isset( $data->background_repeat ) && ! empty( $data->background_repeat ) ) {
			set_theme_mod( 'background_repeat', $data->background_repeat );
		}

		if ( isset( $data->background_attachment ) && ! empty( $data->background_attachment ) ) {
			set_theme_mod( 'background_attachment', $data->background_attachment );
		}

		if ( isset( $data->background_position_x ) && ! empty( $data->background_position_x ) ) {
			set_theme_mod( 'background_position_x', $data->background_position_x );
		}

		if ( isset( $data->background_position_y ) && ! empty( $data->background_position_y ) ) {
			set_theme_mod( 'background_position_y', $data->background_position_y );
		}
	}

	/**
	 * Insert an attachment from a URL address.
	 *
	 * @param string $url The URL address.
	 * @param int|null $parent_post_id The parent post ID (Optional).
	 *
	 * @return int|false                The attachment ID on success. False on failure.
	 */
	function instawp_insert_attachment_from_url( $url, $parent_post_id = null ) {

		if ( ! class_exists( 'WP_Http' ) ) {
			require_once ABSPATH . WPINC . '/class-http.php';
		}

		$http     = new WP_Http();
		$response = $http->request( $url );
		if ( 200 !== $response['response']['code'] ) {
			return false;
		}

		$upload = wp_upload_bits( basename( $url ), null, $response['body'] );
		if ( ! empty( $upload['error'] ) ) {
			return false;
		}

		$file_path        = $upload['file'];
		$file_name        = basename( $file_path );
		$file_type        = wp_check_filetype( $file_name, null );
		$attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
		$wp_upload_dir    = wp_upload_dir();

		$post_info = array(
			'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
			'post_mime_type' => $file_type['type'],
			'post_title'     => $attachment_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Create the attachment.
		$attach_id = wp_insert_attachment( $post_info, $file_path, $parent_post_id );

		// Include image.php.
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Generate the attachment metadata.
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );

		// Assign metadata to attachment.
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return $attach_id;

	}

	#Insert history
	public function sync_history_save( $body = null, $changes = null, $status = null ) {
		$dir     = 'dev-to-live';
		$date    = date( 'Y-m-d H:i:s' );
		$bodyArr = json_decode( $body );
		$message = $bodyArr->sync_message ?? '';
		$data    = [
			'encrypted_contents' => $bodyArr->encrypted_contents,
			'changes'            => json_encode( $changes ),
			'sync_response'      => '',
			'direction'          => $dir,
			'status'             => $status,
			'user_id'            => isset( $bodyArr->upload_wp_user ) ? $bodyArr->upload_wp_user : '',
			'changes_sync_id'    => isset( $bodyArr->sync_id ) ? $bodyArr->sync_id : '',
			'sync_message'       => $message,
			'source_connect_id'  => '',
			'source_url'         => isset( $bodyArr->source_url ) ? $bodyArr->source_url : '',
			'date'               => $date,
		];

		InstaWP_Sync_DB::insert( $this->tables['sh_table'], $data );
	}



	/** sync operation response
	 *
	 * @param $status
	 * @param $message
	 * @param $v
	 *
	 * @return array
	 */
	public function sync_opration_response( $status, $message, $v ) {
		return [
			'id'      => $v->id,
			'status'  => $status,
			'message' => $message
		];
	}

	/** sync update
	 *
	 * @param $sync_id
	 * @param $data
	 * @param $source_connect_id
	 *
	 * @return array
	 */
	public function sync_update( $sync_id, $data ) {
		$connect_id = instawp_get_connect_id();

		// connects/<connect_id>/syncs/<sync_id>
		return InstaWP_Curl::do_curl( "connects/{$connect_id}/syncs/{$sync_id}", $data, [], 'patch' );
	}

	/**
	 * Set Astra Costmizer Setings
	 */
	function setAstraCostmizerSetings( $arr = null ) {
		#Checkout
		update_option( 'woocommerce_checkout_company_field', $arr['woocommerce_checkout_company_field'] );
		update_option( 'woocommerce_checkout_address_2_field', $arr['woocommerce_checkout_address_2_field'] );
		update_option( 'woocommerce_checkout_phone_field', $arr['woocommerce_checkout_phone_field'] );
		update_option( 'woocommerce_checkout_highlight_required_fields', $arr['woocommerce_checkout_highlight_required_fields'] );
		update_option( 'wp_page_for_privacy_policy', $arr['wp_page_for_privacy_policy'] );
		update_option( 'woocommerce_terms_page_id', $arr['woocommerce_terms_page_id'] );
		update_option( 'woocommerce_checkout_privacy_policy_text', $arr['woocommerce_checkout_privacy_policy_text'] );
		update_option( 'woocommerce_checkout_terms_and_conditions_checkbox_text', $arr['woocommerce_checkout_terms_and_conditions_checkbox_text'] );

		#product catalog
		update_option( 'woocommerce_shop_page_display', $arr['woocommerce_shop_page_display'] );
		update_option( 'woocommerce_default_catalog_orderby', $arr['woocommerce_default_catalog_orderby'] );
		update_option( 'woocommerce_category_archive_display', $arr['woocommerce_category_archive_display'] );

		#Product Images
		update_option( 'woocommerce_single_image_width', $arr['woocommerce_single_image_width'] );
		update_option( 'woocommerce_thumbnail_image_width', $arr['woocommerce_thumbnail_image_width'] );
		update_option( 'woocommerce_thumbnail_cropping', $arr['woocommerce_thumbnail_cropping'] );
		update_option( 'woocommerce_thumbnail_cropping_custom_width', $arr['woocommerce_thumbnail_cropping_custom_width'] );
		update_option( 'woocommerce_thumbnail_cropping_custom_height', $arr['woocommerce_thumbnail_cropping_custom_height'] );

		#Store Notice
		update_option( 'woocommerce_demo_store', $arr['woocommerce_demo_store'] );
		update_option( 'woocommerce_demo_store_notice', $arr['woocommerce_demo_store_notice'] );
	}
}

new InstaWP_Sync_Apis();