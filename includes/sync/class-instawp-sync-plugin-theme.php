<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Post {

    public function __construct() {
	    // Post Actions.
	    add_action( 'save_post', [ $this, 'save_post' ], 10, 3 );
	    add_action( 'delete_post', [ $this, 'delete_post' ], 10, 2 );
	    add_action( 'transition_post_status', [ $this, 'transition_post_status' ], 10, 3 );

	    // Media Actions.
	    add_action( 'add_attachment', [ $this, 'add_attachment' ] );
	    add_action( 'attachment_updated', [ $this, 'attachment_updated' ], 10, 3 );
    }

	/**
	 * Function for `wp_insert_post` action-hook.
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Post object.
	 * @param bool         $update      Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function save_post( $post_id, $post, $update ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check auto save or revision.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check post status auto draft.
		if ( in_array( $post->post_status, [ 'auto-draft', 'trash' ] ) ) {
			return;
		}

		// acf feild group check
		if ( $post->post_type == 'acf-field-group' && $post->post_content == '' ) {
			InstaWP_Sync_Helpers::set_post_reference_id( $post_id );
			return;
		}

		// acf check for acf post type
		if ( in_array( $post->post_type, [ 'acf-post-type','acf-taxonomy' ] ) && $post->post_title == 'Auto Draft' ) {
			return;
		}

		$singular_name = InstaWP_Sync_Helpers::get_post_type_name( $post->post_type );
		if ( $update && strtotime( $post->post_modified_gmt ) > strtotime( $post->post_date_gmt ) ) {
			$this->handle_post_events( sprintf( __('%s modified', 'instawp-connect'), $singular_name ), 'post_change', $post );
		}
	}

	/**
	 * Function for `after_delete_post` action-hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post   Post object.
	 *
	 * @return void
	 */
	public function delete_post( $post_id, $post ) {
		if ( get_post_type( $post_id ) !== 'revision' ) {
			$event_name = sprintf( __('%s deleted', 'instawp-connect' ), InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ) );
			$this->handle_post_events( $event_name, 'post_delete', $post );
		}
	}

	/**
	 * Fire a callback only when my-custom-post-type posts are transitioned to 'publish'.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( $new_status === 'trash' && $new_status !== $old_status && $post->post_type !== 'customize_changeset' ) {
			$event_name = sprintf( __( '%s trashed', 'instawp-connect' ), InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ) );
			$this->handle_post_events( $event_name, 'post_trash', $post );
		}

		if ( $new_status === 'draft' && $old_status === 'trash' ) {
			$event_name = sprintf( __( '%s restored', 'instawp-connect' ), InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ) );
			$this->handle_post_events( $event_name, 'untrashed_post', $post );
		}

		if ( $old_status === 'auto-draft' && $new_status !== $old_status ) {
			$event_name = sprintf( __( '%s created', 'instawp-connect' ), InstaWP_Sync_Helpers::get_post_type_name( $post->post_type ) );
			$this->handle_post_events( $event_name, 'post_new', $post );
		}
	}

	/**
	 * Function for `add_attachment` action-hook
	 *
	 * @param $post_id
	 * @return void
	 */
	public function add_attachment( $post_id ) {
		$event_name = esc_html__( 'Media created', 'instawp-connect' );
		$this->handle_post_events( $event_name, 'post_new', $post_id );
	}

	/**
	 * Function for `attachment_updated` action-hook
	 *
	 * @param $post_id
	 * @param $post_after
	 * @param $post_before
	 * @return void
	 */
	public function attachment_updated( $post_id, $post_after, $post_before ) {
		$event_name = esc_html__('Media updated', 'instawp-connect' );
		$this->handle_post_events( $event_name, 'post_change', $post_after );
	}

	/**
	 * Function for `handle_post_events`
	 *
	 * @param $event_name
	 * @param $event_slug
	 * @param $post
	 * @return void
	 */
	private function handle_post_events( $event_name = null, $event_slug = null, $post = null ) {
		$post               = get_post( $post );
		$post_parent_id     = $post->post_parent;
		$post_content       = $post->post_content ?? '';
		$featured_image_id  = get_post_thumbnail_id( $post->ID );
		$featured_image_url = $featured_image_id ? wp_get_attachment_image_url( $featured_image_id, 'post-thumbnail' ) : false;
		$event_type         = get_post_type( $post );
		$title              = $post->post_title ?? '';
		$taxonomies         = $this->get_taxonomies_items( $post->ID );
		$media              = InstaWP_Sync_Helpers::get_media_from_content( $post_content );
		$elementor_css      = $this->get_elementor_css( $post->ID );

		#if post type products then get product gallery
		$product_gallery = ( $event_type === 'product' ) ? $this->get_product_gallery( $post->ID ) : '';

		#manage custom post metas
		InstaWP_Sync_Helpers::set_post_reference_id( $post->ID );
		InstaWP_Sync_Helpers::set_post_reference_id( $featured_image_id );

		$data = [
			'content'         => $post_content,
			'posts'           => $post,
			'postmeta'        => get_post_meta( $post->ID ),
			'featured_image'  => [
				'featured_image_id'  => $featured_image_id,
				'featured_image_url' => $featured_image_url,
				'media'              => $featured_image_id > 0 ? get_post( $featured_image_id ) : [],
				'media_meta'         => $featured_image_id > 0 ? get_post_meta( $featured_image_id ) : [],
			],
			'taxonomies'      => $taxonomies,
			'media'           => $media,
			'elementor_css'   => $elementor_css,
			'product_gallery' => $product_gallery
		];

		#assign parent post
		if ( $post_parent_id > 0 ) {
			$post_parent = get_post( $post_parent_id );

			if ( $post_parent->post_status !== 'auto-draft' ) {
				InstaWP_Sync_Helpers::set_post_reference_id( $post_parent_id );
				$data = array_merge( $data, [
					'parent' => [
						'post'      => $post_parent,
						'post_meta' => get_post_meta( $post_parent_id ),
					]
				] );
			}
		}

		InstaWP_Sync_DB::insert_update_event( $event_name, $event_slug, $event_type, $post->ID, $title, $data );
	}

	/**
	 * Get taxonomies items
	 */
	private function get_taxonomies_items( $post_id ): array {
		$taxonomies = get_post_taxonomies( $post_id );
		$items      = [];

		if ( ! empty ( $taxonomies ) && is_array( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy_items = get_the_terms( $post_id, $taxonomy );

				if ( ! empty( $taxonomy_items ) && is_array( $taxonomy_items ) ) {
					foreach ( $taxonomy_items as $k => $item ) {
						$items[ $item->taxonomy ][ $k ] = ( array ) $item;

						if ( $item->parent > 0 ) {
							$items[ $item->taxonomy ][ $k ]['cat_parent'] = ( array ) get_term( $item->parent, $taxonomy );
						}
					}
				}
			}
		}

		return $items;
	}

	/*
	 * get post css from elementor files 'post-{post_id}.css'
	 */
	private function get_elementor_css( $post_id ) {
		$upload_dir = wp_upload_dir();
		$filename   = 'post-' . $post_id . '.css';
		$filePath   = $upload_dir['basedir'] . '/elementor/css/' . $filename;
		$css        = '';

		if ( file_exists( $filePath ) ) {
			$css = file_get_contents( $filePath );
		}

		return $css;
	}

	/*
     * Get product gallery images
     */
	private function get_product_gallery( $product_id ): array {
		$product        = new WC_product( $product_id );
		$attachment_ids = $product->get_gallery_image_ids();
		$gallery        = [];

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

		return $gallery;
	}
}