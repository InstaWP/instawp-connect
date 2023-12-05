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
		$logs               = [];

		if ( get_option( 'instawp_is_event_syncing' ) ) {
			$is_enabled = true;
		}

		#forcely disable the syncing at the destination
		update_option( 'instawp_is_event_syncing', 0 );

		if ( ! empty( $encrypted_contents ) && is_array( $encrypted_contents ) ) {
			$changes         = [];
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
					$message         = 'Sync successfully.';
					$status          = 'completed';
					$sync_response[] = $this->sync_opration_response( $status, $message, $v );
				} else {
					if ( empty( $v->event_slug ) || empty( $v->details ) ) {
						continue;
					}

					$source_id = ( ! empty( $v->source_id ) ) ? intval( $v->source_id ) : null;

					// create and update
					if ( in_array( $v->event_slug, [ 'post_change', 'post_new' ], true ) ) {
						$posts          = isset( $v->details->posts ) ? ( array ) $v->details->posts : '';
						$postmeta       = isset( $v->details->postmeta ) ? ( array ) $v->details->postmeta : '';
						$featured_image = isset( $v->details->featured_image ) ? ( array ) $v->details->featured_image : '';
						$media          = isset( $v->details->media ) ? ( array ) $v->details->media : '';
						$parent_post    = isset( $v->details->parent->post ) ? ( array ) $v->details->parent->post : [];

						#check for the post parent
						if ( ! empty( $parent_post ) ) {
							$parent_post_meta = isset( $v->details->parent->post_meta ) ? ( array ) $v->details->parent->post_meta : [];
							$reference_id     = $parent_post_meta['instawp_event_sync_reference_id'][0] ?? '';
							$destination_post = $this->get_post_by_reference( $parent_post['post_type'], $reference_id, $parent_post['post_name'] );

							if ( ! empty( $destination_post ) ) {
								$posts['post_parent'] = $destination_post->ID;
							} else {
								if ( in_array( $posts['post_type'], [ 'acf-field' ] ) ) {
									$posts['post_parent'] = $this->create_or_update_post( $parent_post, $parent_post_meta );
								}
							}
						}

						if ( $posts['post_type'] === 'attachment' ) {
							$posts['ID'] = $this->handle_attachments( $posts, $postmeta, $posts['guid'] );
							$this->process_post_meta( $postmeta, $posts['ID'] );
						} else {
							$posts['ID'] = $this->create_or_update_post( $posts, $postmeta );
						}

						if ( ! empty( $featured_image['media'] ) ) {
							$att_id = $this->handle_attachments( ( array ) $featured_image['media'], ( array ) $featured_image['media_meta'], $featured_image['featured_image_url'] );
							if ( ! empty( $att_id ) ) {
								set_post_thumbnail( $posts['ID'], $att_id );
							}
						}

						if ( $posts['post_type'] === 'product' ) {
							if ( isset( $v->details->product_gallery ) && ! empty( $v->details->product_gallery ) ) {
								$product_gallery = $v->details->product_gallery;
								$gallery_ids     = [];
								foreach ( $product_gallery as $gallery ) {
									if ( ! empty( $gallery->media ) && ! empty( $gallery->url ) ) {
										$gallery_ids[] = $this->handle_attachments( ( array ) $gallery->media, ( array ) $gallery->media_meta, $gallery->url );
									}
								}
								$this->set_product_gallery( $posts['ID'], $gallery_ids );
							}
						}

						$this->reset_post_terms( $posts['ID'] );

						$taxonomies = ( array ) $v->details->taxonomies;
						foreach ( $taxonomies as $taxonomy => $terms ) {
							$terms    = ( array ) $terms;
							$term_ids = [];
							foreach ( $terms as $term ) {
								$term = ( array ) $term;
								if ( ! term_exists( $term['slug'], $taxonomy ) ) {
									$inserted_term = wp_insert_term( $term['name'], $taxonomy, [
										'description' => $term['description'],
										'slug'        => $term['slug'],
										'parent'      => 0
									] );
									$term_ids[]    = $inserted_term['term_id'];
								} else {
									$get_term_by = ( array ) get_term_by( 'slug', $term['slug'], $taxonomy );
									$term_ids[]  = $get_term_by['term_id'];
								}
							}
							wp_set_post_terms( $posts['ID'], $term_ids, $taxonomy );
						}

						$this->upload_content_media( $media, $posts['ID'] );

						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
					}

					// trash, untrash and delete
					if ( in_array( $v->event_slug, [ 'post_trash', 'post_delete', 'untrashed_post' ], true ) ) {
						$posts        = ( array ) $v->details->posts;
						$postmeta     = ( array ) $v->details->postmeta;
						$reference_id = $postmeta['instawp_event_sync_reference_id'][0] ?? '';
						$post_name    = $posts['post_name'];
						$function     = 'wp_delete_post';

						if ( $v->event_slug !== 'post_delete' ) {
							$post_name = ( $v->event_slug === 'untrashed_post' ) ? $posts['post_name'] . '__trashed' : str_replace( '__trashed', '', $posts['post_name'] );
							$function  = ( $v->event_slug === 'untrashed_post' ) ? 'wp_untrash_post' : 'wp_trash_post';
						}
						$post_by_reference_id = $this->get_post_by_reference( $posts['post_type'], $reference_id, $post_name );

						if ( ! empty( $post_by_reference_id ) ) {
							$post_id = $post_by_reference_id->ID;
							$post    = call_user_func( $function, $post_id );
							$status  = isset( $post->ID ) ? 'completed' : 'pending';
							$message = isset( $post->ID ) ? 'Sync successfully.' : 'Something went wrong.';
						} else {
							$status               = 'completed';
							$message              = 'Sync successfully.';
							$this->logs[ $v->id ] = sprintf( '%s not found at destination', ucfirst( str_replace( [ '-', '_' ], '', $posts['post_type'] ) ) );
						}
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
					}

					// plugin activate
					if ( $v->event_slug === 'activate_plugin' ) {
						$is_plugin_installed = $this->is_plugin_installed( $v->details );

						// install plugin if not exists
						if ( ! $is_plugin_installed ) {
							$pluginData = get_plugin_data( $v->details );
							if ( ! empty( $pluginData['TextDomain'] ) ) {
								$this->plugin_install( $pluginData['TextDomain'] );
							} else {
								$this->logs[ $v->id ] = sprintf( 'plugin %s not found at destination', $v->details );
							}
						}

						$this->plugin_activation( $v->details );

						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
					}

					// plugin deactivate
					if ( $v->event_slug === 'deactivate_plugin' ) {
						$this->plugin_deactivation( $v->details );

						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
					}

					// plugin install and update
					if ( in_array( $v->event_slug, [ 'plugin_install', 'plugin_update' ], true ) && ! empty( $v->details->slug ) ) {
						$check_plugin_installed = $this->check_plugin_installed_by_textdomain( $v->details->slug );
						if ( ! $check_plugin_installed ) {
							$this->plugin_install( $v->details->slug, ( $v->event_slug === 'plugin_update' ) );
						} else {
							$this->logs[ $v->id ] = ( $v->event_slug === 'plugin_update' ) ? sprintf( 'Plugin %s not found for update operation.', $v->details->slug ) : sprintf( 'Plugin %s already exists.', $v->details->slug );
						}

						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
					}

					// plugin delete
					if ( $v->event_slug === 'deleted_plugin' ) {
						$this->plugin_deactivation( $v->details );

						$plugin = plugin_basename( sanitize_text_field( wp_unslash( $v->details ) ) );
						$result = delete_plugins( [ $plugin ] );

						if ( is_wp_error( $result ) ) {
							$this->logs[ $v->id ] = $result->get_error_message();
						} elseif ( false === $result ) {
							$this->logs[ $v->id ] = __( 'Plugin could not be deleted.' );
						}

						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
					}

					// theme install, update and change
					if ( in_array( $v->event_slug, [ 'switch_theme', 'theme_install', 'theme_update' ], true ) && ! empty( $v->details->stylesheet ) ) {
						$stylesheet = $v->details->stylesheet;
						$theme      = wp_get_theme( $stylesheet );

						if ( $v->event_slug === 'theme_update' ) {
							if ( $theme->exists() ) {
								$this->theme_install( $stylesheet, true );
							} else {
								$this->logs[ $v->id ] = sprintf( 'Theme %s not found for update operation.', $stylesheet );
							}
						} else {
							if ( ! $theme->exists() ) {
								$this->theme_install( $stylesheet );
							}
						}

						if ( $v->event_slug === 'switch_theme' ) {
							switch_theme( $stylesheet );
						}

						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
					}

					// delete theme
					if ( isset( $v->details->stylesheet ) && $v->event_slug === 'deleted_theme' ) {
						$stylesheet = $v->details->stylesheet;
						$theme      = wp_get_theme( $stylesheet );

						if ( $theme->exists() ) {
							require_once( ABSPATH . 'wp-includes/pluggable.php' );

							$result = delete_theme( $stylesheet );
							if ( is_wp_error( $result ) ) {
								$this->logs[ $v->id ] = $result->get_error_message();
							} elseif ( false === $result ) {
								$this->logs[ $v->id ] = sprintf( 'Theme %s could not be deleted.', $stylesheet );
							}

						} else {
							$this->logs[ $v->id ] = sprintf( 'Theme %s not found for delete operation.', $stylesheet );
						}

						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
					}

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

						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
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

						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
					}

					// users
					if ( $v->event_type === 'users' ) {
						$user_data        = isset( $v->details->user_data ) ? ( array ) $v->details->user_data : [];
						$user_meta        = isset( $v->details->user_meta ) ? ( array ) $v->details->user_meta : [];
						$source_db_prefix = isset( $v->details->db_prefix ) ? ( array ) $v->details->db_prefix : '';
						$user_table       = $this->wpdb->prefix . 'users';

						$get_user_by_reference_id = get_users( [
							'meta_key'   => 'instawp_event_user_sync_reference_id',
							'meta_value' => $user_meta['instawp_event_user_sync_reference_id'][0] ?? '',
						] );
						$user = ! empty( $get_user_by_reference_id ) ? reset( $get_user_by_reference_id ) : get_user_by( 'email', $user_data['email'] );

						if ( $v->event_slug === 'user_register' && ! empty( $user_data ) && ! $user ) {
							$user_id = wp_insert_user( $user_data );
							if ( is_wp_error( $user_id ) ) {
								$this->logs[ $v->id ] = $user_id->get_error_message();
							} else {
								$this->manage_usermeta( $user_meta, $user_id, $source_db_prefix );
							}
						}

						if ( $v->event_slug === 'profile_update' && ! empty( $user_data ) ) {
							if ( $user ) {
								$user_data['ID'] = $user->data->ID;
								$user_pass       = $user_data['user_pass'];
								unset( $user_data['user_pass'] );
								$user_id = wp_update_user( $user_data );

								if ( is_wp_error( $user_id ) ) {
									$this->logs[ $v->id ] = $user_id->get_error_message();
								} else {
									$this->wpdb->update( $user_table, [ 'user_pass' => $user_pass ], [ 'ID' => $user_id ] );
									$this->manage_usermeta( $user_meta, $user_id );
									$user->add_role( $v->details->role );
								}
							} else {
								$this->logs[ $v->id ] = sprintf( 'User not found for update operation.' );
							}
						}

						if ( $v->event_slug === 'delete_user' ) {
							if ( $user ) {
								wp_delete_user( $user->data->ID );
							} else {
								$this->logs[ $v->id ] = sprintf( 'User not found for delete operation.' );
							}
						}

						$message         = 'Sync successfully.';
						$status          = 'completed';
						$sync_response[] = $this->sync_opration_response( $status, $message, $v );
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

	/**
	 * get post type singular name
	 *
	 * @param $post_name
	 * @param $post_type
	 */
	public function get_post_by_name( $post_name, $post_type ) {
		global $wpdb;

		$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type= %s ", $post_name, $post_type ) );
		if ( $post ) {
			return get_post( $post );
		}

		return null;
	}

	private function get_post_by_reference( $post_type, $reference_id, $post_name ) {
		$post = get_posts( [
			'post_type'   => $post_type,
			'meta_key'    => 'instawp_event_sync_reference_id',
			'meta_value'  => $reference_id,
			'post_status' => 'any',
			'nopaging'    => true,
		] );

		return ! empty( $post ) ? reset( $post ) : $this->get_post_by_name( $post_name, $post_type );
	}

	private function create_or_update_post( $post, $post_meta ) {
		$reference_id     = $post_meta['instawp_event_sync_reference_id'][0] ?? '';
		$destination_post = $this->get_post_by_reference( $post['post_type'], $reference_id, $post['post_name'] );

		if ( ! empty( $destination_post ) ) {
			unset( $post['post_author'] );
			$post_id = wp_update_post( $this->parse_post_data( $post, $destination_post->ID ) );
		} else {
			$default_post_user = InstaWP_Setting::get_option( 'instawp_default_user' );
			if ( ! empty( $default_post_user ) ) {
				$post['post_author'] = $default_post_user;
			}
			$post_id = wp_insert_post( $this->parse_post_data( $post ) );
		}
		$this->process_post_meta( $post_meta, $post_id );

		return $post_id;
	}

	private function parse_post_data( $post, $post_id = null ) {
		unset( $post['ID'] );
		unset( $post['guid'] );

		if ( $post_id ) {
			$post['ID'] = $post_id;
		}

		return $post;
	}

	public function event_sync_logs( $data, $source_url ) {
		$data = [
			'event_id'   => $data->id,
			'event_hash' => $data->event_hash,
			'source_url' => $source_url,
			'data'       => json_encode( $data->details ),
			'logs'       => isset( $this->logs[ $data->id ] ) ? $this->logs[ $data->id ] : '',
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

	/**
	 * Set product gallery
	 */
	public function set_product_gallery( $product_id = null, $gallery_ids = null ) {
		if ( class_exists( 'woocommerce' ) ) {
			$product = new WC_product( $product_id );
			$product->set_gallery_image_ids( $gallery_ids );
			$product->save();
		}
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
	 * upload_content_media
	 *
	 * @param $media
	 * @param $post_id
	 *
	 * @return void
	 */
	public function upload_content_media( $media = null, $post_id = null ) {
		$media   = json_decode( reset( $media ) );
		$post    = get_post( $post_id );
		$content = $post->post_content;
		$new     = $old = [];
		if ( ! empty( $media ) ) {
			foreach ( $media as $v ) {
				$v = (array) $v;

				if ( isset( $v['attachment_id'] ) && isset( $v['attachment_url'] ) ) {
					$attachment_media = (array) $v['attachment_media'];
					$attachment_id    = $this->handle_attachments( $attachment_media, (array) $v['attachment_media_meta'], $attachment_media['guid'] );
					$new[]            = wp_get_attachment_url( $attachment_id );
					$old[]            = $v['attachment_url'];
				}
			}

			$newContent = str_replace( $old, $new, $content ); #str_replace(old,new,str)
			$arg        = array(
				'ID'           => $post_id,
				'post_content' => $newContent,
			);
			wp_update_post( $arg );
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

	/**
	 * notExistMsg
	 * @return string
	 */
	public function notExistMsg(): string {
		return __( 'Item not found.', 'instawp-connect' );
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
		$this->wpdb->insert( $this->wpdb->prefix . 'terms', $wp_terms );
		$this->wpdb->insert( $this->wpdb->prefix . 'term_taxonomy', $wp_term_taxonomy );
	}

	public function update_taxonomy( $term_id = null, $wp_terms = null, $wp_term_taxonomy = null ) {
		$this->wpdb->update( $this->wpdb->prefix . 'terms', $wp_terms, array( 'term_id' => $term_id ) );
		$this->wpdb->update( $this->wpdb->prefix . 'term_taxonomy', $wp_term_taxonomy, array( 'term_id' => $term_id ) );
	}

	public function reset_post_terms( $post_id ) {
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->wpdb->prefix}term_relationships WHERE object_id = %d",
				$post_id,
			)
		);
	}

	/**
	 * process_post_meta
	 *
	 * @param $meta_data
	 * @param $post_id
	 *
	 * @return void
	 */
	public function process_post_meta( $meta_data, $post_id ) {
		if ( empty( $meta_data ) || ! is_array( $meta_data ) ) {
			return;
		}

		foreach ( $meta_data as $key => $value ) {
			update_post_meta( $post_id, $key, maybe_unserialize( reset( $value ) ) );
		}

		//if _elementor_css this key not existing then it's giving a error.
		if ( array_key_exists( '_elementor_version', $meta_data ) && ! array_key_exists( '_elementor_css', $meta_data ) ) {
			/*$elementor_css = [
				'time' => time(),
				'fonts' => [],
				'icons' => [],
				'dynamic_elements_ids' => [],
				'status' => 'empty',
				'css' => ''
			];
			*/
			$elementor_css = [];
			add_post_meta( $post_id, '_elementor_css', $elementor_css );
		}
		delete_post_meta( $post_id, '_edit_lock' );
	}

	public function postData( $posts = null, $op = null ) {
		$args = array(
			'post_author'           => $posts['post_author'],
			'post_date'             => $posts['post_date'],
			'post_date_gmt'         => $posts['post_date_gmt'],
			'post_content'          => $posts['post_content'],
			'post_title'            => $posts['post_title'],
			'post_excerpt'          => $posts['post_excerpt'],
			'post_status'           => $posts['post_status'],
			'comment_status'        => $posts['comment_status'],
			'ping_status'           => $posts['ping_status'],
			'post_password'         => $posts['post_password'],
			'post_name'             => $posts['post_name'],
			'to_ping'               => $posts['to_ping'],
			'pinged'                => $posts['pinged'],
			'post_modified'         => $posts['post_modified'],
			'post_modified_gmt'     => $posts['post_modified_gmt'],
			'post_content_filtered' => $posts['post_content_filtered'],
			'post_parent'           => $posts['post_parent'],
			//'guid' => $posts['guid'],
			'menu_order'            => $posts['menu_order'],
			'post_type'             => $posts['post_type'],
			'post_mime_type'        => $posts['post_mime_type'],
			'comment_count'         => $posts['comment_count'],
			'filter'                => $posts['filter'],
		);
		#ID to update existing post
		if ( isset( $posts['ID'] ) && $posts['ID'] > 0 ) {
			$args = array_merge( [ 'ID' => $posts['ID'] ], $args );
		}

		return $args;
	}

	/** handle_attachments
	 *
	 * @param $attachment_post
	 * @param $attachment_post_meta
	 * @param $file
	 *
	 * @return string|void
	 */
	# import attechments form source to destination.
	public function handle_attachments( $attachment_post, $attachment_post_meta, $file ) {
		$reference_id    = $attachment_post_meta['instawp_event_sync_reference_id'][0] ?? '';
		$attachment_id   = $attachment_post['ID'];
		$attachment_post = $this->get_post_by_reference( $attachment_post['post_type'], $reference_id, $attachment_post['post_name'] );

		if ( ! $attachment_post ) {
			$filename          = basename( $file );
			$arrContextOptions = array(
				"ssl" => array(
					"verify_peer"      => false,
					"verify_peer_name" => false,
				),
			);

			$parent_post_id = 0;
			$upload_file    = wp_upload_bits( $filename, null, file_get_contents( $file, false, stream_context_create( $arrContextOptions ) ) );
			if ( ! $upload_file['error'] ) {
				$wp_filetype = wp_check_filetype( $filename, null );

				$attachment = array(
					'post_mime_type' => $wp_filetype['type'],
					'post_parent'    => $parent_post_id,
					'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				);

				$default_post_user = InstaWP_Setting::get_option( 'instawp_default_user' );
				if ( ! empty( $default_post_user ) ) {
					$attachment['post_author'] = $default_post_user;
				}

				require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
				require_once( ABSPATH . "wp-admin" . '/includes/file.php' );
				require_once( ABSPATH . "wp-admin" . '/includes/media.php' );
				$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $parent_post_id );

				if ( ! is_wp_error( $attachment_id ) ) {
					$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
					wp_update_attachment_metadata( $attachment_id, $attachment_data );
					update_post_meta( $attachment_id, 'instawp_event_sync_reference_id', $reference_id );
				}
			}
		} else {
			$attachment_id = $attachment_post->ID;
		}

		return $attachment_id;
	}

	# import attechments form source to destination.
	public function insert_attachment( $attachment_id = null, $file = null ) {
		$filename          = basename( $file );
		$arrContextOptions = array(
			"ssl" => array(
				"verify_peer"      => false,
				"verify_peer_name" => false,
			),
		);
		$parent_post_id    = 0;
		$upload_file       = wp_upload_bits( $filename, null, file_get_contents( $file, false, stream_context_create( $arrContextOptions ) ) );

		if ( ! $upload_file['error'] ) {
			$wp_filetype = wp_check_filetype( $filename, null );
			$attachment  = array(
				'import_id'      => $attachment_id,
				'post_mime_type' => $wp_filetype['type'],
				'post_parent'    => $parent_post_id,
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);

			require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
			require_once( ABSPATH . "wp-admin" . '/includes/file.php' );
			require_once( ABSPATH . "wp-admin" . '/includes/media.php' );

			$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $parent_post_id );

			if ( ! is_wp_error( $attachment_id ) ) {
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
				wp_update_attachment_metadata( $attachment_id, $attachment_data );
			}
		}

		return $attachment_id;
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

	#Plugin activate.
	public function plugin_activation( $plugin ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( $plugin ) ) {
			activate_plugin( $plugin );
		}
	}

	#Plugin deactivate.
	public function plugin_deactivation( $plugin ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( $plugin ) ) {
			deactivate_plugins( $plugin );
		}
	}

	/** sync operation response
	 *
	 * @param $status
	 * @param $message
	 * @param $v
	 *
	 * @return array
	 */
	public function sync_opration_response( $status = null, $message = null, $v = null ) {
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

	/*
	* Create elementor css file 'post-{post_id}.css'
	*/
	public function create_elementor_css_file( $data = null, $post_id = null ) {
		$upload_dir = wp_upload_dir();
		$filename   = 'post-' . $post_id . '.css';
		$filePath   = $upload_dir['basedir'] . '/elementor/css/' . $filename;
		$file       = fopen( $filePath, "w+" );//w+,w
		fwrite( $file, $data );
		fclose( $file );
	}

	/**
	 * Plugin install
	 */
	public function plugin_install( $plugin_slug, $overwrite_package = false ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' ); //for plugins_api..

		$api = plugins_api( 'plugin_information', array(
			'slug'   => $plugin_slug,
			'fields' => array(
				'short_description' => false,
				'sections'          => false,
				'requires'          => false,
				'rating'            => false,
				'ratings'           => false,
				'downloaded'        => false,
				'last_updated'      => false,
				'added'             => false,
				'tags'              => false,
				'compatibility'     => false,
				'homepage'          => false,
				'donate_link'       => false,
			),
		) );

		//includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
		include_once( ABSPATH . 'wp-admin/includes/file.php' );
		include_once( ABSPATH . 'wp-admin/includes/misc.php' );
		include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

		$upgrader = new \Plugin_Upgrader( new \Plugin_Installer_Skin() );
		$upgrader->install( $api->download_link, array( 'overwrite_package' => $overwrite_package ) );
	}

	/**
	 * Theme install
	 */
	public function theme_install( $stylesheet, $overwrite_package = false ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; // For themes_api().

		$api = themes_api( 'theme_information', [
			'slug'   => $stylesheet,
			'fields' => [
				'sections' => false,
				'tags'     => false,
			],
		] );

		include_once( ABSPATH . 'wp-includes/pluggable.php' );
		include_once( ABSPATH . 'wp-admin/includes/file.php' );
		include_once( ABSPATH . 'wp-admin/includes/misc.php' );

		if ( ! is_wp_error( $api ) ) {
			$upgrader = new Theme_Upgrader();
			$upgrader->install( $api->download_link, [ 'overwrite_package' => $overwrite_package ] );
		}
	}

	/**
	 * Check if plugin is installed by getting all plugins from the plugins dir
	 *
	 * @param $plugin_slug
	 *
	 * @return bool
	 */
	public function is_plugin_installed( $plugin_slug ): bool {
		$installed_plugins = get_plugins();

		return array_key_exists( $plugin_slug, $installed_plugins ) || in_array( $plugin_slug, $installed_plugins, true );
	}

	/**
	 * Check if plugin is installed by getting all plugins from the plugins dir
	 *
	 * @param $plugin_slug
	 *
	 * @return bool
	 */
	public function check_plugin_installed_by_textdomain( $textdomain ): bool {
		$installed_plugins_data = get_plugins();
		$installed_text_domains = array_column( array_values( $installed_plugins_data ), 'TextDomain' );

		return in_array( $textdomain, $installed_text_domains, true );
	}

	/**
	 * Check if theme is installed by getting all themes from the theme dir
	 *
	 * @param $stylesheet
	 *
	 * @return bool
	 */
	public function check_theme_installed( $stylesheet ): bool {
		$installed_themes = wp_get_themes();

		return array_key_exists( $stylesheet, $installed_themes );
	}

	//add and update user meta
	public function manage_usermeta( $user_meta = null, $user_id = null, $source_db_prefix = null ) {
		if ( ! empty( $user_meta ) && is_array( $user_meta ) ) {
			foreach ( $user_meta as $k => $v ) {
				if ( isset( $v[0] ) ) {
					$k              = $source_db_prefix != '' ? str_replace( $source_db_prefix, $this->wpdb->prefix, $k ) : $k;
					$checkSerialize = @unserialize( $v[0] );
					$metaVal        = ( $checkSerialize !== false || $v[0] === 'b:0;' ) ? unserialize( $v[0] ) : $v[0];
					if ( metadata_exists( 'user', $user_id, $k ) ) {
						update_user_meta( $user_id, $k, $metaVal );
					} else {
						add_user_meta( $user_id, $k, $metaVal );
					}
				}
			}
		}
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