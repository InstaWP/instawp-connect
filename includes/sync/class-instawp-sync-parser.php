<?php
/**
 *
 * This file is used for change event traking
 *
 * @link       https://instawp.com/
 * @since      1.0
 * @package    instaWP
 * @subpackage instaWP/admin
 */
/**
 * This file is used for change event tracking
 *
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/admin
 * @author     instawp team
 */

use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || die;

class InstaWP_Sync_Parser {

	public static function get_media_from_content( $content ) {
		preg_match_all( '!(https?:)?//\S+\.(?:jpe?g|jpg|png|gif|mp4|pdf|doc|docx|xls|xlsx|csv|txt|rtf|html|zip|mp3|wma|mpg|flv|avi)!Ui', $content, $match );

		$media = array();
		if ( isset( $match[0] ) ) {
			$attachment_urls = array_unique( $match[0] );

			foreach ( $attachment_urls as $attachment_url ) {
				if ( ! empty( $_SERVER['HTTP_HOST'] ) && strpos( $attachment_url, sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) !== false ) {
					$full_attachment_url = preg_replace( '~-[0-9]+x[0-9]+.~', '.', $attachment_url );
					$attachment_data     = self::url_to_attachment( $full_attachment_url );

					$media[] = array_merge( $attachment_data, array(
						'attachment_url' => $attachment_url,
					) );
				}
			}
		}

		return $media;
	}

	public static function url_to_attachment( $attachment_url ) {
		global $wpdb;

		$attachment_id = attachment_url_to_postid( $attachment_url );

		if ( $attachment_id === 0 ) {
			$post_name = sanitize_title( pathinfo( $attachment_url, PATHINFO_FILENAME ) );
			$results   = $wpdb->get_results(
				$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_name=%s", $post_name )
			);

			if ( $results ) {
				$attachment_id = reset( $results )->ID;
			}
		}

		return self::attachment_to_string( $attachment_id );
	}

	public static function attachment_to_string( $attachment_id, $size = 'thumbnail', $is_attachment = false ) {
		if ( ! $attachment_id ) {
			return array();
		}

		$image_path = wp_get_attachment_image_url( $attachment_id, $size, false );
		if ( ! $image_path ) {
			return array();
		}

		$image_info = pathinfo( $image_path );
		$file_type  = wp_check_filetype( $image_info['basename'], null );
		$attachment = get_post( $attachment_id );

		$image_info['reference_id'] = InstaWP_Sync_Helpers::get_post_reference_id( $attachment_id );
		$image_info['post_name']    = $attachment->post_name;
		$image_info['file_type']    = $file_type['type'];
		$image_info['path']         = $image_path;

		if ( ! $is_attachment ) {
            $image_info['post_meta'] = get_post_meta( $attachment_id );
        }

        if ( $is_attachment && $attachment->post_parent ) {
            $image_info['post_parent'] = self::parse_post_data( $attachment->post_parent );
        }

		return $image_info;
	}

	public static function string_to_attachment( $data ) {
		$attachment_id = 0;

		if ( empty( $data ) ) {
			return $attachment_id;
		}

		$attachment = InstaWP_Sync_Helpers::get_post_by_reference( 'attachment', $data['reference_id'], $data['post_name'] );
		if ( ! $attachment ) {
			$image_data = base64_decode( $data['base_data'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$upload_dir = wp_upload_dir();
			$file_name  = wp_unique_filename( $upload_dir['path'], $data['basename'] );
			$save_path  = $upload_dir['path'] . DIRECTORY_SEPARATOR . $file_name;

			file_put_contents( $save_path, $image_data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

			$attachment = array(
				'post_mime_type' => $data['file_type'],
				'post_title'     => sanitize_file_name( $file_name ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'guid'           => $save_path,
			);

			$parent_post_id = 0;

			if ( ! empty( $data['post_parent'] ) ) {
				$parent_post_id            = self::parse_post_events( $data['post_parent'] );
				$attachment['post_parent'] = $parent_post_id;
			}

			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );

			$attachment_id = wp_insert_attachment( $attachment, $save_path, $parent_post_id );

			if ( ! is_wp_error( $attachment_id ) ) {
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $save_path );
				wp_update_attachment_metadata( $attachment_id, $attachment_data );
				update_post_meta( $attachment_id, 'instawp_event_sync_reference_id', $data['reference_id'] );
			}
		} else {
			$attachment_id = $attachment->ID;
		}

		self::process_post_meta( $data['post_meta'], $attachment_id );

		return $attachment_id;
	}

	public static function process_post_meta( $meta_data, $post_id ) {
		if ( empty( $meta_data ) || ! is_array( $meta_data ) ) {
			return;
		}

		if ( array_key_exists( '_elementor_version', $meta_data ) && ! array_key_exists( '_elementor_css', $meta_data ) ) {
			$elementor_css = array();
			add_post_meta( $post_id, '_elementor_css', $elementor_css );
		}

		$bricks_meta = array( '_bricks_page_content_2', '_bricks_page_header_2', '_bricks_page_footer_2' );

		// Bricks
		add_action( 'init', function () use ( $bricks_meta ) {
			if ( class_exists( '\Bricks\Ajax' ) ) {
				$class = new \Bricks\Ajax();
				remove_filter( 'update_post_metadata', array( $class, 'update_bricks_postmeta' ) );

				foreach ( $bricks_meta as $bricks_meta_key ) {
					remove_filter( 'sanitize_post_meta_' . $bricks_meta_key, array(
						$class,
						'sanitize_bricks_postmeta',
					), 10 );
				}
			}
		} );

		// Bricks
		add_filter( 'update_post_metadata', function ( $check, $object_id, $meta_key, $meta_value, $prev_value ) use ( $bricks_meta ) {
			$is_bricks_postmeta = in_array( $meta_key, $bricks_meta, true );

			return $is_bricks_postmeta ? null : $check;
		}, 9999, 5 );

		foreach ( $meta_data as $meta_key => $values ) {
			$value = $values[0];

			if ( '_elementor_data' === $meta_key ) {
				$value = wp_slash( $value );
			} else {
				$value = maybe_unserialize( $value );
			}

			update_metadata( 'post', $post_id, $meta_key, $value );
		}

		if ( InstaWP_Sync_Helpers::is_built_with_elementor( $post_id ) ) {
			$css_file = \Elementor\Core\Files\CSS\Post::create( $post_id );
			$css_file->delete();
		}

		delete_post_meta( $post_id, '_edit_lock' );
	}

	public static function parse_post_events( $details ) {
		$wp_post     = isset( $details['post'] ) ? $details['post'] : array();
		$parent_data = isset( $details['parent'] ) ? $details['parent'] : array();

		if ( ! $wp_post ) {
			return 0;
		}

		kses_remove_filters();

		if ( $wp_post['post_type'] === 'attachment' ) {
			$wp_post['ID'] = self::string_to_attachment( $details['attachment'] );
		} else {
			$post_meta      = isset( $details['post_meta'] ) ? $details['post_meta'] : array();
			$featured_image = isset( $details['featured_image'] ) ? $details['featured_image'] : array();
			$content_media  = isset( $details['media'] ) ? $details['media'] : array();
			$taxonomies     = isset( $details['taxonomies'] ) ? $details['taxonomies'] : array();
			$wp_post['ID']  = self::create_or_update_post( $wp_post, $post_meta, $details['reference_id'] );

			delete_post_thumbnail( $wp_post['ID'] );

			if ( ! empty( $featured_image ) ) {
				$attachment_id = self::string_to_attachment( $featured_image );
				if ( ! empty( $attachment_id ) ) {
					set_post_thumbnail( $wp_post['ID'], $attachment_id );
				}
			}

			do_action( 'instawp/actions/2waysync/process_event_post', $wp_post, $details );

			InstaWP_Sync_Helpers::reset_post_terms( $wp_post['ID'] );

			foreach ( $taxonomies as $taxonomy => $terms ) {
				$term_ids = array();
				foreach ( $terms as $term ) {
					$term = ( array ) $term;
					if ( ! term_exists( $term['slug'], $taxonomy ) ) {
						$inserted_term = wp_insert_term( $term['name'], $taxonomy, array(
							'description' => $term['description'],
							'slug'        => $term['slug'],
							'parent'      => 0,
						) );
						if ( ! is_wp_error( $inserted_term ) ) {
							$term_ids[] = $inserted_term['term_id'];
						}
					} else {
						$get_term_by = ( array ) get_term_by( 'slug', $term['slug'], $taxonomy );
						$term_ids[]  = $get_term_by['term_id'];
					}
				}
				wp_set_post_terms( $wp_post['ID'], $term_ids, $taxonomy );
			}

			self::upload_content_media( $content_media, $wp_post['ID'] );
		}

		if ( ! empty( $parent_data ) ) {
			$parent_post_id = self::parse_post_events( $parent_data );

			wp_update_post( array(
				'ID'          => $wp_post['ID'],
				'post_parent' => $parent_post_id,
			) );
		}

		kses_init_filters();

		clean_post_cache( $wp_post['ID'] );

		return $wp_post['ID'];
	}

	public static function parse_post_data( $post ) {
		kses_remove_filters();

		$_post = get_post( $post );
		if ( ! $_post instanceof WP_Post ) {
			return $post;
		}

		$post               = $_post;
		$post_content       = isset( $post->post_content ) ? $post->post_content : '';
		$post_parent_id     = $post->post_parent;
		$reference_id       = InstaWP_Sync_Helpers::get_post_reference_id( $post->ID );
		$post->post_content = base64_encode( $post_content ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$data = array(
			'post'         => $post,
			'post_meta'    => get_post_meta( $post->ID ),
			'reference_id' => $reference_id,
		);

		if ( $post->post_type === 'attachment' ) {
			$data['attachment'] = self::attachment_to_string( $post->ID, 'full', true );
		} else {
			$taxonomies = InstaWP_Sync_Helpers::get_taxonomies_items( $post->ID );
			if ( ! empty( $taxonomies ) ) {
				$data['taxonomies'] = $taxonomies;
			}

			$featured_image_id = get_post_thumbnail_id( $post->ID );
			if ( $featured_image_id ) {
				$data['featured_image'] = self::attachment_to_string( $featured_image_id );
			}

			if ( ! empty( $post->post_content ) ) {
				$data['media'] = self::get_media_from_content( $post->post_content );
			}
		}

		if ( $post_parent_id > 0 ) {
			$post_parent = get_post( $post_parent_id );

			if ( $post_parent->post_status !== 'auto-draft' ) {
				$data['parent'] = self::parse_post_data( $post_parent );
			}
		}

		kses_init_filters();

		return $data;
	}

	public static function create_or_update_post( $post, $post_meta, $reference_id ) {
		$destination_post = InstaWP_Sync_Helpers::get_post_by_reference( $post['post_type'], $reference_id, $post['post_name'] );

		if ( ! empty( $destination_post ) ) {
			unset( $post['post_author'] );
			$post_id = wp_update_post( self::prepare_post_data( $post, $destination_post->ID ) );
		} else {
			$default_post_user = Option::get_option( 'instawp_default_user' );
			if ( ! empty( $default_post_user ) ) {
				$post['post_author'] = $default_post_user;
			}
			$post_id = wp_insert_post( self::prepare_post_data( $post ) );
		}

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			self::process_post_meta( $post_meta, $post_id );

			return $post_id;
		}

		return 0;
	}

	public static function prepare_post_data( $post, $post_id = 0 ) {
		unset( $post['ID'], $post['guid'] );

		if ( isset( $post['post_content'] ) ) {
			$post['post_content'] = base64_decode( $post['post_content'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		if ( $post_id ) {
			$post['ID'] = $post_id;
		}

		return $post;
	}

	public static function upload_content_media( $media, $post_id ) {
		$post    = get_post( $post_id );
		$content = $post->post_content;
		$new     = $old = array();

		if ( ! empty( $media ) ) {
			foreach ( $media as $media_item ) {
				if ( ! empty( $media_item['attachment_url'] ) ) {
					$attachment_id = self::string_to_attachment( $media_item );
					$new[]         = wp_get_attachment_url( $attachment_id );
					$old[]         = $media_item['attachment_url'];
				}
			}

			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => str_replace( $old, $new, $content ),
			) );
		}
	}
}