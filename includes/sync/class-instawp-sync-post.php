<?php

use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Post {

	/**
	 * Post Events
	 * @var array
	 * @since 0.1.0.58
	 */
	private $post_events = array();

	public function __construct() {
		// Post Actions.
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );
		add_action( 'wp_insert_post', array( $this, 'save_post' ), 999, 2 );

		add_action( 'before_delete_post', array( $this, 'delete_post' ), 10, 2 );

		// Media Actions.
		add_action( 'add_attachment', array( $this, 'add_attachment' ) );
		add_action( 'edit_attachment', array( $this, 'edit_attachment' ) );
		add_action( 'delete_attachment', array( $this, 'delete_attachment' ) );
		//add_action( 'wp_ajax_image-editor', array( $this, 'edit_attachment_item' ), 1 );

		// Elementor.
		add_action( 'elementor/document/after_save', array( $this, 'handle_elementor' ), 999 );

        // Divi.
        add_filter( 'et_fb_ajax_save_verification_result', '__return_true' );

		// Duplicate Post.
		add_filter( 'duplicate_post_excludelist_filter', array( $this, 'custom_fields_filter' ) );
		add_filter( 'duplicate_post_post_copy', array( $this, 'generate_reference' ) );

		// Process Events.
		add_filter( 'instawp/filters/2waysync/process_event', array( $this, 'parse_event' ), 10, 2 );
	}

	/**
	 * Fire a callback only when my-custom-post-type posts are transitioned to 'publish'.
	 * If transition_post_status fires, save_post will always fire 
	 * afterward, since both are part of the same post save process in WordPress
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Old post status.
	 * @param WP_Post $post Post object.
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( ! $this->can_sync_post( $post ) ) {
			return;
		}

		// acf field group check
		if ( $post->post_type === 'acf-field-group' && $post->post_content === '' ) {
			InstaWP_Sync_Helpers::set_post_reference_id( $post->ID );
			return;
		}

		// acf check for acf post type
		if ( in_array( $post->post_type, array( 'acf-post-type', 'acf-taxonomy' ) ) && $post->post_title === 'Auto Draft' ) {
			return;
		}

		// Check auto save or revision.
		if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) ) {
			return;
		}

		$singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type );

		if ( 'auto-draft' === $old_status && ( 'auto-draft' !== $new_status && 'inherit' !== $new_status ) ) {
			$event_name = sprintf( __( '%s created', 'instawp-connect' ), $singular_name );
			$action     = 'post_new';
		} elseif ( 'auto-draft' === $new_status || ( 'new' === $old_status && 'inherit' === $new_status ) ) {
			return;
		} elseif ( 'trash' === $new_status ) {
			$event_name = sprintf( __( '%s trashed', 'instawp-connect' ), $singular_name );
			$action     = 'post_trash';
		} elseif ( 'trash' === $old_status ) {
			$event_name = sprintf( __( '%s restored', 'instawp-connect' ), $singular_name );
			$action     = 'untrashed_post';
		} else {
			$event_name = sprintf( __( '%s modified', 'instawp-connect' ), $singular_name );
			$action     = 'post_change';
		}

		// Save event to array.
		$this->post_events[ $post->ID ] = array(
			'event_name' => $event_name,
			'action'     => $action,
		);
	}

	/**
	 * Save post sync event. If transition_post_status fires, save_post will always fire 
	 * afterward, since both are part of the same post save process in WordPress
	 *
	 * @since 0.1.0.58
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function save_post( $post_id, $post ) {
		// Check if post has been transitioned.
		if ( empty( $this->post_events[ $post_id ] ) ) {
			return;
		}
		// Unhook this function so it doesn't loop infinitely
        remove_action( 'save_post', array( $this, 'save_post' ) );
		
		$this->handle_post_events(
			$this->post_events[ $post_id ]['event_name'], 
			$this->post_events[ $post_id ]['action'], 
			$post 
		);

		// Re-hook this function.
        add_action( 'save_post', array( $this, 'save_post' ), 999, 2 );
	}

	/**
	 * Function for `before_delete_post` action-hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function delete_post( $post_id, $post ) {
		if ( ! $this->can_sync_post( $post ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ) ) ) {
			return;
		}

		$event_name = sprintf( __( '%s deleted', 'instawp-connect' ), InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ) );
		$this->handle_post_events( $event_name, 'post_delete', $post );
	}

	private function is_zip_php_file( $file_path ) {
		return ( substr( $file_path, -4) === '.zip' || substr( $file_path, -4) === '.rar' || substr( $file_path, -4) === '.php' );
	}

	public function is_media_plugin_theme_zip( $postData ) {
		if ( empty( $postData ) || 'attachment' !== $postData->post_type || empty( $postData->guid ) || 'private' !== $postData->post_status || ! $this->is_zip_php_file( $postData->guid ) ) {
			return false;
		}

		$context = get_post_meta( $postData->ID, '_wp_attachment_context', true );

		// Check if context is upgrader means plugin or theme install
		if ( empty( $context ) || 'upgrader' !== $context ) {
			return false;
		}

		// Get slug
		$slug = basename($postData->guid);
		$slug = explode('.', $slug);
		$slug = $slug[0];

		// Check if slug is on wordpress.org
		foreach ( array('plugin', 'theme') as $item_type ) {
			if ( Helper::is_on_wordpress_org( $slug, $item_type ) ) {
				return true;
			}
		}

		// Check if slug is in exclude list
		$exclude_slugs = get_set_sync_config_data( 'exclude_upload_plugin_theme_slugs' );

		if ( empty( $exclude_slugs ) || ! in_array( $slug, $exclude_slugs ) ) {
			// Add slug
			$exclude_slugs[] = $slug;
			get_set_sync_config_data( 'exclude_upload_plugin_theme_slugs', $exclude_slugs );
		}
		
		return true;
	}

	/**
	 * Function for `add_attachment` action-hook
	 *
	 * @param $post_id
	 *
	 * @return void
	 */
	public function add_attachment( $attachment_id ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'post' ) ) {
			return;
		}

		$attachment = get_post( $attachment_id );
		if ( $this->is_media_plugin_theme_zip( $attachment ) ) {
			return;
		}

		$event_name = esc_html__( 'Media created', 'instawp-connect' );
		$this->handle_post_events( $event_name, 'post_new', $attachment );
	}

	/**
	 * Function for `edit_attachment` action-hook
	 *
	 * @param $attachment_id
	 *
	 * @return void
	 */
	public function edit_attachment( $attachment_id ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'post' ) ) {
			return;
		}

		$attachment = get_post( $attachment_id );
		$event_name = esc_html__( 'Media updated', 'instawp-connect' );
		$this->handle_post_events( $event_name, 'post_change', $attachment );
	}

	/**
	 * Function for `delete_attachment` action-hook
	 *
	 * @param $attachment_id
	 *
	 * @return void
	 */
	public function delete_attachment( $attachment_id ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'post' ) ) {
			return;
		}

		$attachment = get_post( $attachment_id );

		if ( $this->is_media_plugin_theme_zip( $attachment ) ) {
			return;
		}

		$event_name = esc_html__( 'Media deleted', 'instawp-connect' );
		$this->handle_post_events( $event_name, 'post_delete', $attachment );
	}

    /**
     * Function for `wp_ajax_image-editor` action-hook
     *
     * @return void
     */
    public function edit_attachment_item() {
        if ( ! InstaWP_Sync_Helpers::can_sync( 'post' ) ) {
            return;
        }

        $attachment_id = ! empty( $_POST['postid'] ) ? (int) $_POST['postid'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! $attachment_id ) {
            return;
        }

        $attachment = get_post( $attachment_id );
        $event_name = esc_html__( 'Media updated', 'instawp-connect' );
        $this->handle_post_events( $event_name, 'post_change', $attachment );
    }

	/**
	 * After document save.
	 *
	 * @param $document
	 */
	public function handle_elementor( $document ) {
		$post = $document->get_post();

		if ( ! $this->can_sync_post( $post ) || in_array( $post->post_status, array( 'auto-draft', 'inherit' ) ) ) {
			return;
		}

		$singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type );
		$event_name    = sprintf( __( '%s modified', 'instawp-connect' ), $singular_name );

		$this->handle_post_events( $event_name, 'post_change', $post );
	}

	public function custom_fields_filter( $meta_exclude_list ) {
		$meta_exclude_list[] = 'instawp_event_sync_reference_id';
		return $meta_exclude_list;
	}

	public function generate_reference( $new_post_id ) {
		$reference_id = Helper::get_random_string( 8 );
		update_post_meta( $new_post_id, 'instawp_event_sync_reference_id', $reference_id );
	}

	public function parse_event( $response, $v ) {
		$reference_id = $v->reference_id;
		$details      = InstaWP_Sync_Helpers::object_to_array( $v->details );

		// create and update
		if ( in_array( $v->event_slug, array( 'post_change', 'post_new' ), true ) ) {
			InstaWP_Sync_Parser::parse_post_events( $details );

			return InstaWP_Sync_Helpers::sync_response( $v );
		}

		// trash, untrash and delete
		if ( in_array( $v->event_slug, array( 'post_trash', 'post_delete', 'untrashed_post' ), true ) ) {
			$wp_post   = isset( $details['post'] ) ? $details['post'] : array();
			$post_name = $wp_post['post_name'];
			$function  = 'wp_delete_post';
			$data      = array();
			$logs      = array();

			if ( $v->event_slug !== 'post_delete' ) {
				$post_name = ( $v->event_slug === 'untrashed_post' ) ? $wp_post['post_name'] . '__trashed' : str_replace( '__trashed', '', $wp_post['post_name'] );
				$function  = ( $v->event_slug === 'untrashed_post' ) ? 'wp_untrash_post' : 'wp_trash_post';
			}
			$post_by_reference_id = InstaWP_Sync_Helpers::get_post_by_reference( $wp_post['post_type'], $reference_id, $post_name );

			if ( ! empty( $post_by_reference_id ) ) {
				$post_id = $post_by_reference_id->ID;
				$post    = call_user_func( $function, $post_id );
				$status  = isset( $post->ID ) ? 'completed' : 'pending';
				$message = isset( $post->ID ) ? 'Sync successfully.' : 'Something went wrong.';

				clean_post_cache( $post_id );

				$data = compact( 'status', 'message' );
			} else {
				$logs[ $v->id ] = sprintf( '%s not found at destination', ucfirst( str_replace( array( '-', '_' ), '', $wp_post['post_type'] ) ) );
			}

			return InstaWP_Sync_Helpers::sync_response( $v, $logs, $data );
		}

		return $response;
	}

	/**
	 * Function for `handle_post_events`
	 *
	 * @param $event_name
	 * @param $event_slug
	 * @param $post
	 *
	 * @return void
	 */
	private function handle_post_events( $event_name, $event_slug, $post ) {
		clean_post_cache( $post->ID );

		$post = get_post( $post );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$data         = InstaWP_Sync_Parser::parse_post_data( $post );
		$reference_id = isset( $data['reference_id'] ) ? $data['reference_id'] : '';

		$event_type = $post->post_type;
		$title      = $post->post_title;
		$data       = apply_filters( 'instawp/filters/2waysync/post_data', $data, $event_type, $post );

		if ( is_array( $data ) && ! empty( $reference_id ) ) {
			InstaWP_Sync_DB::insert_update_event( $event_name, $event_slug, $event_type, $reference_id, $title, $data );
		}
	}

	private function can_sync_post( $post ) {
		$can_sync        = false;
		$restricted_cpts = array(
			// WordPress
			'customize_changeset',
			'revision',
			'nav_menu_item',
			'custom_css',
			'oembed_cache',
			'user_request',

			// WooCommerce
			'product',
			'shop_order',
			'shop_order_placehold',
			'shop_coupon',
            'product_variation',

			// SEOPress
			'seopress_404',
		);
		$restricted_cpts = (array) apply_filters( 'instawp/filters/2waysync/restricted_post_types', $restricted_cpts );

		if ( InstaWP_Sync_Helpers::can_sync( 'post' ) && ! in_array( $post->post_type, $restricted_cpts ) ) {
			$can_sync = true;
		}

		return (bool) apply_filters( 'instawp/filters/2waysync/can_sync_post', $can_sync, $post );
	}
}

new InstaWP_Sync_Post();
