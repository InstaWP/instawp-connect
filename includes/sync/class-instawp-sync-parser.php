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

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Option;
use InstaWP\Connect\Helpers\Helper;

defined( 'ABSPATH' ) || die;

class InstaWP_Sync_Parser {

	public static function get_media_from_content( $content ) {
		//preg_match_all( '!(https?:)?//\S+\.(?:jpe?g|jpg|png|gif|webp|svg|mp4|pdf|doc|docx|xls|xlsx|csv|txt|rtf|html|zip|mp3|wma|mpg|flv|avi)!Ui', $content, $match );
		preg_match_all(
			'!(https?:\/\/[^\s",]+?\.(?:jpe?g|png|gif|webp|svg|mp4|pdf|docx?|xlsx?|csv|txt|rtf|html|zip|mp3|wma|mpg|flv|avi))!i',
			$content,
			$match
		);

		$media = array();
		if ( isset( $match[0] ) ) {
			$attachment_urls = array_unique( $match[0] );

			foreach ( $attachment_urls as $attachment_url ) {
				if ( filter_var( $attachment_url, FILTER_VALIDATE_URL ) && ! empty( $_SERVER['HTTP_HOST'] ) && strpos( $attachment_url, sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) !== false ) {
					$attachment_url = esc_url( $attachment_url );
					$full_attachment_url = preg_replace( '~-[0-9]+x[0-9]+.~', '.', $attachment_url );
                    $attachment_id       = self::url_to_attachment( $full_attachment_url );
					if ( empty( $attachment_id ) ) {
						continue;
					}
					$attachment_data     = self::generate_attachment_data( $attachment_id );;
                    $attachment_size     = self::get_image_size_name_from_url( $attachment_url );

					$media[] = array_merge( $attachment_data, array(
                        'size'           => $attachment_size ? $attachment_size : 'full',
						'attachment_url' => $attachment_url,
					) );
				}
			}
		}

		return $media;
	}

    public static function get_image_size_name_from_url( $image_url ) {
        // Get the attachment ID from the image URL
        $attachment_id = self::url_to_attachment( $image_url );
        if ( ! $attachment_id ) {
            return false;
        }

        // Get the image metadata
        $image_meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! $image_meta ) {
            return false;
        }

        // Get the uploads directory URL
        $upload_dir = wp_upload_dir();
        $base_url   = trailingslashit( $upload_dir['baseurl'] );

        // Get the file name relative to the uploads directory
        $relative_file_name = str_replace( $base_url, '', $image_url );

        // Check the original image
        if ( ! empty( $image_meta['file'] ) && $relative_file_name === $image_meta['file'] ) {
            return 'full';
        }

        // Check each image size
		if ( ! empty( $image_meta['sizes'] ) ) {
			foreach ( $image_meta['sizes'] as $size => $size_data ) {
				if ( $relative_file_name === $size_data['file'] ) {
					return $size;
				}
			}
		}

        return false;
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

		return $attachment_id;
	}

	public static function generate_attachment_data( $attachment_id, $size = 'thumbnail', $is_attachment = false ) {
		if ( ! $attachment_id ) {
			return array();
		}

		$attachment_path = wp_attachment_is_image( $attachment_id ) ? wp_get_attachment_image_url( $attachment_id, $size, false ) : wp_get_attachment_url( $attachment_id );
		if ( ! $attachment_path ) {
			return array();
		}

		$attachment_info = pathinfo( $attachment_path );
		$file_type       = wp_check_filetype( $attachment_info['basename'], null );
		$attachment      = get_post( $attachment_id );

		$attachment_info['post']      = $attachment;
		$attachment_info['post_id']   = $attachment->ID;
		$attachment_info['post_name'] = $attachment->post_name;
		$attachment_info['file_type'] = $file_type['type'];
		$attachment_info['path']      = $attachment_path;

        if ( wp_attachment_is_image( $attachment_id ) ) {
		    $attachment_info['size'] = $size;
        }

		if ( ! $is_attachment ) {
		    $attachment_info['reference_id'] = InstaWP_Sync_Helpers::get_post_reference_id( $attachment_id );
            $attachment_info['post_meta']    = get_post_meta( $attachment_id );
        }

		return $attachment_info;
	}

	public static function process_attachment_data( $data ) {
		$attachment_id = 0;

		if ( empty( $data ) ) {
			return $attachment_id;
		}
		try {
			$attachment = InstaWP_Sync_Helpers::get_post_by_reference( 'attachment', $data['reference_id'], $data['post_name'] );
			if ( empty( $attachment ) ) {
				
				$image_url  = empty( $data['url'] ) ? $data['path'] : $data['url'];
				if ( false === wp_http_validate_url( $image_url ) ) {
					error_log( 'Invalid image URL Sync: ' . $image_url );
					return $attachment_id;
				}

				$image_url = esc_url( $image_url );
				$failed_message = 'Error: failed_process_attachment ' . $image_url . ' ';
				if ( empty( $data['url'] ) && ! empty( $data['post_id'] ) && ! empty( $data['path'] ) ) {
					// media path
					$data['path'] = esc_url( $data['path'] );
					// get connected site list
					$staging_sites = instawp_get_connected_sites_list();
					if ( empty( $staging_sites ) ) {
						InstaWP_Sync_Helpers::get_set_sync_parser_log( $failed_message . 'No connected site found.', true );
						return $attachment_id;
					}

					$api_url = '';
					// Get connected site
					$hash = '';
					foreach ( $staging_sites as $site ) {
						if ( false !== strpos( $data['path'], $site['url'] ) ) {
							$api_url = esc_url( $site['url'] );
							if ( empty( $site['uuid'] ) && ! empty( $site['data'] ) && ! empty( $site['data']['uuid'] ) ) {
								$site['uuid'] = $site['data']['uuid'];
							}
							if ( empty( $site['uuid'] ) ) {
								InstaWP_Sync_Helpers::get_set_sync_parser_log( $failed_message . 'Connected site details not found.', true );
								return $attachment_id;
							}
							// Prepare hash
							$hash = hash( 'sha256', $site['connect_id'] . '_' . $site['uuid'] );
							break;
						}
					}

					if ( empty( $api_url ) || empty( $hash ) ) {
						InstaWP_Sync_Helpers::get_set_sync_parser_log( $failed_message . 'Media path not matched with connected sites.', true );
						return $attachment_id;
					}

					$response = wp_remote_post( $api_url . '/wp-json/instawp-connect/v1/sync/download-media', array(
						'timeout'   => 120,
						'headers'   => instawp_get_migration_headers( $hash ),
						'sslverify' => false,
						'body'      => json_encode( array(
							'file'     => $data,
							'media_id' => $data['post_id'],
						) ),
					) );

					// Check for errors
					if ( is_wp_error( $response ) ) {
						InstaWP_Sync_Helpers::get_set_sync_parser_log( $failed_message . '' . $response->get_error_message(), true );
						return $attachment_id;
					}
					// Get response http code
					$response_code = wp_remote_retrieve_response_code( $response );
					// Get file content
					$image_data = wp_remote_retrieve_body($response);
					if ( 200 !== $response_code ) {
						InstaWP_Sync_Helpers::get_set_sync_parser_log( $failed_message . 'Response_code ' . $response_code . '. Error message ' . $image_data, true );
						return $attachment_id;
					}               
} else {
					
					$image_data = file_get_contents( $image_url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					if ( $image_data === false ) {
						$image_data = file_get_contents( $data['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					}
				}

				if ( empty( $image_data ) ) {
					// backward compatibility
					if ( ! empty( $data['base_data'] ) ) {
						$image_data = base64_decode( $data['base_data'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
					} else {
						return $attachment_id;
					}
				}

				$upload_dir = wp_upload_dir();
				$file_name  = wp_unique_filename( $upload_dir['path'], $data['basename'] );
				$save_path  = $upload_dir['path'] . DIRECTORY_SEPARATOR . $file_name;

				file_put_contents( $save_path, $image_data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

				$attachment = wp_parse_args( array(
					'guid' => $save_path,
				), self::prepare_post_data( $data['post'] ) );

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
					self::process_post_meta( $data['post_meta'], $attachment_id );

					$attachment_data = wp_generate_attachment_metadata( $attachment_id, $save_path );
					wp_update_attachment_metadata( $attachment_id, $attachment_data );

					InstaWP_Sync_Helpers::set_post_reference_id( $attachment_id, $data['reference_id'] );
				}
			} else {
				$attachment_id = empty( $attachment->ID ) ? 0 : intval( $attachment->ID );
				if ( 0 < $attachment_id ) {
					wp_update_post( self::prepare_post_data( $data['post'], $attachment_id ) );
					self::process_scaled_image( $data, $attachment_id );
					self::process_post_meta( $data['post_meta'], $attachment_id );
				}
			}
		} catch ( \Throwable $th ) {
			error_log(
				sprintf(
					'Failed to process image (%s) in %s on line %d. Error: %s. Stack Trace: %s',
					$image_url,            // Image URL
					$th->getFile(),        // File where error occurred
					$th->getLine(),        // Line number of error
					$th->getMessage(),     // Error message
					$th->getTraceAsString() // Stack trace
				)
			);
		}

		return $attachment_id;
	}

	
	/**
	 * Process scaled image, if found in post meta.
	 *
	 * @param array $data Post data.
	 * @param int $id Attachment ID.
	 */
	public static function process_scaled_image( $data, $id ) {
		
		if ( empty( $id ) || empty( $data['post_meta'] ) || empty( $data['post_meta']['_wp_attached_file'] ) || ! is_array( $data['post_meta']['_wp_attached_file'] ) ) {
			return;
		}

		try {

			$image_url  = isset( $data['url'] ) ? $data['url'] : $data['path'];
			$scaled_file = $data['post_meta']['_wp_attached_file'][0];

			// Scaled image not required
			if ( empty( $image_url ) || empty( $scaled_file ) || false === stripos( $scaled_file, '-scaled.' ) || false !== stripos( $image_url, $scaled_file ) ) {
				return;
			}

			$id = absint( $id );
			$file = get_post_meta( $id, '_wp_attached_file', true );

			// Already processed
			if ( empty( $file ) || false !== stripos( $file, '-scaled.' ) ) {
				return;
			}

			// Include required files
			if ( file_exists( ABSPATH . 'wp-admin/includes/image.php' ) && file_exists( ABSPATH . 'wp-admin/includes/file.php' ) && file_exists( ABSPATH . 'wp-admin/includes/media.php' ) ) {
				include_once ABSPATH . 'wp-admin/includes/image.php';
				include_once ABSPATH . 'wp-admin/includes/file.php';
				include_once ABSPATH . 'wp-admin/includes/media.php';
			}

			// Get attachment metadata
			$metadata = wp_get_attachment_metadata( $id, true );

			// Already processed
			if ( empty( $metadata ) || empty( $metadata['file'] ) || false !== stripos( $metadata['file'], '-scaled.' ) ) {
				return;
			}
			
			if ( ! function_exists( 'wp_get_image_editor' ) || ! function_exists( '_wp_image_meta_replace_original' ) ) {
				error_log( "wp_get_image_editor or _wp_image_meta_replace_original function not found ");
				return;
			}

			$upload_dir = wp_upload_dir();
			$filepath  = trailingslashit( $upload_dir['basedir'] ) . $file;

			$editor = wp_get_image_editor( $filepath );

			if ( is_wp_error( $editor ) ) {
				error_log( " Failed to get image editor for path " . $filepath . ". Error " . $editor->get_error_message() );
				return;
			}

			$saved = $editor->save( $editor->generate_filename( 'scaled' ) );

			if ( is_wp_error( $saved ) ) {
				error_log( " Failed to create scaled image for path " . $filepath . ". Error " . $saved->get_error_message() );
				return;
			} 

			$metadata = _wp_image_meta_replace_original( $saved, $file, $metadata, $id );
			wp_update_attachment_metadata( $id, $metadata );    
			
		} catch ( \Exception $e ) {
			error_log( " Failed to get scaled image " . $e->getMessage() . " " . json_encode( $data ) );
		}
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
		add_filter( 'update_post_metadata', function ( $check, $object_id, $meta_key ) use ( $bricks_meta ) {
			return in_array( $meta_key, $bricks_meta, true ) ? null : $check;
		}, 9999, 3 );

		foreach ( $meta_data as $meta_key => $values ) {
            if ( in_array( $meta_key, array( '_wp_attachment_metadata', '_wp_attached_file' ) ) ) {
                continue;
            }

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
        $post_meta   = isset( $details['post_meta'] ) ? $details['post_meta'] : array();

		if ( ! $wp_post ) {
			return 0;
		}

		kses_remove_filters();

		if ( $wp_post['post_type'] === 'attachment' ) {
            $attachment = array_merge( $details['attachment'], array(
                'reference_id' => $details['reference_id'],
                'post_parent'  => $parent_data,
                'post_meta'    => $post_meta,
                'post'         => $wp_post,
            ) );

			$wp_post['ID'] = self::process_attachment_data( $attachment );
		} else {
			$featured_image = isset( $details['featured_image'] ) ? $details['featured_image'] : array();
			$content_media  = isset( $details['media'] ) ? $details['media'] : array();
			$taxonomies     = isset( $details['taxonomies'] ) ? $details['taxonomies'] : array();
			$wp_post['ID']  = self::create_or_update_post( $wp_post, $post_meta, $details['reference_id'] );

			delete_post_thumbnail( $wp_post['ID'] );

			if ( ! empty( $featured_image ) ) {
				$attachment_id = self::process_attachment_data( $featured_image );
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
			
			self::replace_media_items( $content_media, $wp_post['ID'], $details );
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

		// Clone of the post object to avoid reference issues
		$post               = clone $_post;
		$post_content       = isset( $post->post_content ) ? $post->post_content : '';
		$post_parent_id     = $post->post_parent;
		$reference_id       = InstaWP_Sync_Helpers::get_post_reference_id( $post->ID );
		$post->post_content = base64_encode( $post_content ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$data = array(
			'post'         => $post,
			'post_meta'    => get_post_meta( $post->ID ),
			'reference_id' => $reference_id,
			'site_url'     => home_url(),
		);

		if ( $post->post_type === 'attachment' ) {
			$data['attachment'] = self::generate_attachment_data( $post->ID, 'full', true );
		} else {
			$taxonomies = InstaWP_Sync_Helpers::get_taxonomies_items( $post->ID );
			if ( ! empty( $taxonomies ) ) {
				$data['taxonomies'] = $taxonomies;
			}

			$featured_image_id = get_post_thumbnail_id( $post->ID );
			if ( $featured_image_id ) {
				$data['featured_image'] = self::generate_attachment_data( $featured_image_id );
			}

			// Set dynamic data
			$data['ids'] = array(
				'post_ids' => array(),
				'term_ids' => array(),
				'user_ids' => array(),
			);

			// Flattens the post meta array by replacing single-element arrays with their sole element.
			$flat_meta = InstaWP_Sync_Helpers::flat_post_meta( $data['post_meta'] );
			// If edit with elementor then get media from elementor data
			if ( ! empty( $flat_meta['_elementor_data'] ) && InstaWP_Sync_Helpers::is_built_with_elementor( $post->ID ) && is_string( $flat_meta['_elementor_data'] ) ) {
				$data['media'] = self::get_media_from_content( wp_unslash( $flat_meta['_elementor_data'] ) );
				$data['ids'] = self::extract_dynamic_elementor_data( json_decode( $flat_meta['_elementor_data'], true ), $data['ids'] );
			} elseif ( ! empty( $post_content ) ) {
				$data['media'] = self::get_media_from_content( $post_content );
				if ( has_blocks( $post_content ) ) {
					// Get dynamic data
					$data['ids'] = self::extract_dynamic_gutenberg_data( parse_blocks( $post_content ), $data['ids'] );
				}           
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

			// Full Site Editing: Clean the caches.
			if ( self::is_fse_cpt( $post ) && function_exists( 'wp_clean_theme_json_cache' ) ) {
				// Cleans the caches under the theme_json group.
				wp_clean_theme_json_cache();
			}
			return $post_id;
		}

		return 0;
	}

	/**
	 * Check if post is FSE CPT. ie. 'wp_global_styles', 'wp_template_part', 'wp_template'
	 * 
	 * @param array $post
	 * 
	 * @return bool is FSE CPT
	 */
	public static function is_fse_cpt( $post ) {
		return ( is_array( $post ) && ! empty( $post['post_type'] ) && in_array( $post['post_type'], array( 'wp_global_styles', 'wp_template_part', 'wp_template' ) ) );
	}

	public static function prepare_post_data( $post, $post_id = 0 ) {
		unset( $post['ID'], $post['guid'] );

		if ( isset( $post['post_content'] ) ) {
			$post['post_content'] = base64_decode( $post['post_content'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( has_blocks( $post['post_content'] ) || self::is_fse_cpt( $post ) ) {
				/**
				 * When getting content with get_post(), the content is already unslashed. 
				 * When updating the same content back, it need to wp_slash(). 
				 * This preserves all encoded characters in the content. 
				 * eg. \n in CSS, var(\u002d\u002dast-global-color-1)
				 */
				$post['post_content'] = wp_slash( $post['post_content'] );
			}
		}

		if ( $post_id ) {
			$post['ID'] = $post_id;
		}

		return $post;
	}

	/**
	 * Replace block content
	 *
	 * @param array $block
	 * @param string|array $search
	 * @param string|array $replace
	 * 
	 * @return array $block The processed block
	 */
	public static function replace_block_content( $block, $search, $replace ) {
		if ( empty( $search ) || empty( $replace ) ) {
			return $block;
		}
		foreach ( array( 'innerHTML', 'innerContent' ) as $key ) {
			if ( ! empty( $block[ $key ] ) ) {
				$block[ $key ] = str_replace( $search, $replace, $block[ $key ] );
			}
		}
		return $block;
	}

	/**
	 * Process Gutenberg content
	 * Replaces dynamic data in the content.
	 * @param array $blocks The Gutenberg parse_blocks
	 * @param array $replace_data post, term and user ids to be replaced
	 * 
	 * @return array The processed blocks
	 */
	private static function process_gutenberg_blocks( $blocks, $replace_data ) {
		if ( empty( $blocks ) || ! is_array( $blocks ) || empty( $replace_data ) || ! is_array( $replace_data ) ) {
			return $blocks;
		}
		foreach ( $blocks as &$block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {

				if ( in_array( $block['blockName'], array( 'core/image', 'kadence/image', 'uagb/image' ) ) && ! empty( $block['attrs']['id'] ) ) {
					$post_id = intval( $replace_data['post_ids'][ $block['attrs']['id'] ] );
					if ( 0 < $post_id ) {
						$block = self::replace_block_content( 
							$block, 
							'-image-' . $block['attrs']['id'], 
							'-image-' . $post_id 
						);
						$block['attrs']['id'] = $post_id;
					}
					continue;
				} elseif ( in_array( $block['blockName'], array( 'stackable/image' ) ) && ! empty( $block['attrs']['imageId'] ) ) {
					$post_id = intval( $replace_data['post_ids'][ $block['attrs']['imageId'] ] );
					if ( 0 < $post_id ) {
						$block = self::replace_block_content( 
							$block, 
							'-image-' . $block['attrs']['imageId'], 
							'-image-' . $post_id 
						);
						$block['attrs']['imageId'] = $post_id;
					}
					continue;
				}
				
				// Kadence blocks
				if ( false !== strpos( $block['blockName'], 'kadence/' ) ) {
					foreach ( array( 'id', 'postID', 'formID', 'ids', 'icon', 'source', 'mediaIcon', 'categories', 'tags', 'authors' ) as $attr_key ) {
						// Skip if attribute is empty
						if ( empty( $block['attrs'][ $attr_key ] ) ) {
							continue;
						}
						$attr_value = $block['attrs'][ $attr_key ];
						
						if ( in_array( $attr_key, array( 'id' ) ) ) {
							if ( is_numeric( $attr_value ) && isset( $replace_data['post_ids'][ $attr_value ] ) ) {
								$block['attrs'][ $attr_key ] = intval( $replace_data['post_ids'][ $attr_value ] );
							}
						} elseif ( in_array( $attr_key, array( 'postID', 'formID' ) ) ) {
							if ( is_numeric( $attr_value ) && isset( $replace_data['post_ids'][ $attr_value ] ) ) {
								// Convert to string as postID and formID are string values
								$post_id = (string) $replace_data['post_ids'][ $attr_value ];
								$block['attrs'][ $attr_key ] = $post_id;

								if ( 'postID' === $attr_key && ! empty( $block['attrs']['uniqueID'] ) && 0 === strpos( $block['attrs']['uniqueID'], $attr_value .'_' ) ) {
									// Refer kadence form block
									// Update uniqueID
									$uniqueID = str_replace( $attr_value .'_', $post_id .'_', $block['attrs']['uniqueID'] );
									
									$block = self::replace_block_content( 
										$block, 
										array( 
											$block['attrs']['uniqueID'],
											'value="' . $attr_value . '"',
											'value=\"' . $attr_value . '\"',
										), 
										array( 
											$uniqueID,
											'value="' . $post_id . '"',
											'value=\"' . $post_id . '\"',
										)
									);
										
									// Update uniqueID
									$block['attrs']['uniqueID'] = $uniqueID;
								}
							}
						} elseif ( 'ids' === $attr_key ) {
							if ( is_array( $attr_value ) ) {
								foreach ( $attr_value as $attr_val_key => $attr_val ) {
									if ( isset( $replace_data['post_ids'][ $attr_val ] ) ) {
										$block['attrs'][ $attr_key ][ $attr_val_key ] = $replace_data['post_ids'][ $attr_val ];
									}
								}
								// kadence advanced gallery block
								if ( ! empty( $block['attrs']['imagesDynamic'] ) && is_array( $block['attrs']['imagesDynamic'] ) ) {
									foreach ( $block['attrs']['imagesDynamic'] as $image_dynamic_key => $image_dynamic_value ) {
										if ( ! empty( $image_dynamic_value['id'] ) && isset( $replace_data['post_ids'][ $image_dynamic_value['id'] ] ) ) {
											// Replace id
											$block['attrs']['imagesDynamic'][ $image_dynamic_key ]['id'] = $replace_data['post_ids'][ $image_dynamic_value['id'] ];
										}
									}
								}
							}
						} elseif ( 'icon' === $attr_key ) {
							if ( false !== strpos( $attr_value, 'kb-custom-' ) ) {
								// Remove kb-custom- prefix
								$icon_id = str_replace( 'kb-custom-', '', $attr_value );
								if ( isset( $replace_data['post_ids'][ $icon_id ] ) ) {
									$new_icon_id = 'kb-custom-' . $replace_data['post_ids'][ $icon_id ];
									$block['attrs'][ $attr_key ] = $new_icon_id;
									$block = self::replace_block_content( $block, $attr_value, $new_icon_id );
								}
							}
						} elseif ( 'source' === $attr_key ) {
							if ( is_numeric( $attr_value ) && isset( $replace_data['post_ids'][ $attr_value ] ) && in_array( $block['blockName'], array( 'kadence/dynamiclist', 'kadence/dynamichtml', 'kadence/repeater' ) ) ) {
								// Convert to string as source is a string value
								$block['attrs'][ $attr_key ] = (string) $replace_data['post_ids'][ $attr_value ];
							}
						} elseif ( 'mediaIcon' === $attr_key ) {
							if ( is_array( $attr_value ) ) {
								foreach ( $attr_value as $media_icon_key => $media_icon ) {
									if ( false !== strpos( $media_icon['icon'], 'kb-custom-' ) ) {
										// Remove kb-custom- prefix
										$icon_id = str_replace( 'kb-custom-', '', $media_icon['icon'] );
										if ( isset( $replace_data['post_ids'][ $icon_id ] ) ) {
											$new_icon_id = 'kb-custom-' . $replace_data['post_ids'][ $icon_id ];
											$block['attrs'][ $attr_key ][ $media_icon_key ]['icon'] = $new_icon_id;
											$block = self::replace_block_content( $block, $media_icon['icon'], $new_icon_id );
										}
									}
								}
							}
						} elseif ( in_array( $attr_key, array( 'categories', 'tags' ) ) ) {
							if ( is_array( $attr_value ) ) {
								foreach ( $attr_value as $attr_val_key => $attr_val ) {
									if ( ! is_array( $attr_val ) || empty( $attr_val['value'] ) || ! is_numeric( $attr_val['value'] ) || ! isset( $replace_data['term_ids'][ $attr_val['value'] ] ) ) {
										continue;
									}
									$block['attrs'][ $attr_key ][ $attr_val_key ]['value'] = $replace_data['term_ids'][ $attr_val['value'] ];
								}
							}
						} elseif ( 'authors' === $attr_key ) {
							if ( is_array( $attr_value ) ) {
								foreach ( $attr_value as $attr_val_key => $attr_val ) {
									if ( ! is_array( $attr_val ) || empty( $attr_val['value'] ) || ! is_numeric( $attr_val['value'] ) || ! isset( $replace_data['user_ids'][ $attr_val['value'] ] ) ) {
										continue;
									}
									$block['attrs'][ $attr_key ][ $attr_val_key ]['value'] = $replace_data['user_ids'][ $attr_val['value'] ];
								}
							}
						}
					}
				} elseif ( false !== strpos( $block['blockName'], 'uagb/' ) ) {
					// Spectra blocks
					foreach ( array( 'id', 'mediaIDs', 'categories', 'tags', 'authors' ) as $attr_key ) {
						// Skip if attribute is empty
						if ( empty( $block['attrs'][ $attr_key ] ) ) {
							continue;
						}
						$attr_value = $block['attrs'][ $attr_key ];
						if ( in_array( $attr_key, array( 'id' ) ) ) {
							if ( is_numeric( $attr_value ) && isset( $replace_data['post_ids'][ $attr_value ] ) ) {
								$block['attrs'][ $attr_key ] = intval( $replace_data['post_ids'][ $attr_value ] );
							}
						} elseif ( 'mediaIDs' === $attr_key ) {
							if ( is_array( $attr_value ) ) {
								foreach ( $attr_value as $media_id_key => $media_id ) {
									if ( isset( $replace_data['post_ids'][ $media_id ] ) ) {
										$block['attrs'][ $attr_key ][ $media_id_key ] = $replace_data['post_ids'][ $media_id ];
									}
								}

								// Spectra gallery block
								if ( ! empty( $block['attrs']['mediaGallery'] ) && is_array( $block['attrs']['mediaGallery'] ) ) {
									foreach ( $block['attrs']['mediaGallery'] as $media_gallery_key => $media ) {
										if ( ! empty( $media['id'] ) && isset( $replace_data['post_ids'][ $media['id'] ] ) ) {
											// Replace id
											$media_id = $replace_data['post_ids'][ $media['id'] ];
											$block['attrs']['mediaGallery'][ $media_gallery_key ]['id'] = $media_id;
											// Replace link
											if ( ! empty( $media['link'] ) && false !== strpos( $media['link'], 'attachment_id=' . $media['id'] ) ) {
												$block['attrs']['mediaGallery'][ $media_gallery_key ]['link'] = str_replace( 'attachment_id=' . $media['id'], 'attachment_id=' . $media_id, $media['link'] );
											}
										}
									}
								}
							}
						} elseif ( 'categories' === $attr_key ) {
							
							if ( is_array( $attr_value ) ) {
								foreach ( $attr_value as $attr_val_key => $attr_val ) {
									if ( is_numeric( $attr_val ) && isset( $replace_data['term_ids'][ $attr_val ] ) ) {
										$block['attrs'][ $attr_key ][ $attr_val_key ] = $replace_data['term_ids'][ $attr_val ];
									}
								}
							} elseif ( is_numeric( $attr_value ) && isset( $replace_data['term_ids'][ $attr_value ] ) ) {
								$block['attrs'][ $attr_key ] = (string) $replace_data['term_ids'][ $attr_value ];
							}
						}
					}
				} elseif ( false !== strpos( $block['blockName'], 'stackable/' ) ) {
					foreach ( array( 'taxonomy' ) as $attr_key ) {
						// Skip if attribute is empty
						if ( empty( $block['attrs'][ $attr_key ] ) ) {
							continue;
						}
						$attr_value = $block['attrs'][ $attr_key ];
						if ( 'taxonomy' === $attr_key ) {
							if ( is_string( $attr_value ) ) {
								$ids = explode( ',', $attr_value );
								foreach ( $ids as $id_key => $id ) {
									if ( isset( $replace_data['term_ids'][ $id ] ) ) {
										$ids[ $id_key ] = $replace_data['term_ids'][ $id ];
									}
								}
								$block['attrs'][ $attr_key ] = implode( ',', $ids );
							}
						}
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::process_gutenberg_blocks( $block['innerBlocks'], $replace_data );
			}
		}
		return $blocks;
	}

	/**
	 * Replace media items in the post content.
	 *
	 * @param array $media Media items to replace.
	 * @param int $post_id Post ID.
	 * @param array $details Details of post content.
	 *
	 * @return void
	 */
	public static function replace_media_items( $media, $post_id, $details = array() ) {
		$post    = get_post( $post_id );
		if ( empty( $post ) ) {
			return;
		}
		$content = $post->post_content;
		$search  = $replace = array();
		$details['ids'] = empty( $details['ids'] ) ? array() : $details['ids'];
		$replace_data = array_merge( array(
			'urls'     => array(),
			'post_ids' => array(),
			'term_ids' => array(),
			'user_ids' => array(),
		), $details['ids'] );
		// Flag to check if the post should be updated
		$should_update_post = false;

		if ( ! empty( $media ) ) {
			foreach ( $media as $media_item ) {
				if ( ! empty( $media_item['attachment_url'] ) && filter_var( $media_item['attachment_url'], FILTER_VALIDATE_URL ) ) {
					$attachment_id   = self::process_attachment_data( $media_item );
					if ( empty( $attachment_id ) ) {
						continue;
					}
                    $attachment_size = isset( $media_item['size'] ) ? $media_item['size'] : 'full';
					$search[]        = $media_item['attachment_url'];
					$attachment_url  = wp_attachment_is_image( $attachment_id ) ? wp_get_attachment_image_url( $attachment_id, $attachment_size, false ) : wp_get_attachment_url( $attachment_id );
					$replace[]       = $attachment_url;
					if ( ! empty( $media_item['post_id'] ) ) {
						$replace_data['urls'][ $media_item['attachment_url'] ] = $attachment_url;
						$replace_data['post_ids'][ $media_item['post_id'] ] = $attachment_id;
					} else {
						error_log( 'MEDIA ID NOT FOUND. Media name: ' . esc_attr( $media_item['basename'] ) . ' Reference id: ' . esc_attr( $media_item['reference_id'] ) );
					}
				}
			}

			if ( ! empty( $search ) && ! empty( $replace ) ) {
				$content = str_replace( $search, $replace, $content );
				$should_update_post = true;
			}
		}

		// Replace links
		if ( ! empty( $details['site_url'] ) && filter_var( $details['site_url'], FILTER_VALIDATE_URL ) && false !== strpos( $content, wp_unslash( $details['site_url'] ) ) && function_exists( 'home_url' ) ) {
			$content = str_replace( 
				wp_unslash( $details['site_url'] ),
				home_url(), 
				$content 
			);
			$should_update_post = true;
		}
		// Prepare post, term and user ids
		$replace_data = InstaWP_Sync_Helpers::prepare_post_term_user_ids( $replace_data );

		// Replace blocks data
		if ( has_blocks( $content ) ) {
			try {
				$blocks = parse_blocks( $content );
				if ( ! empty( $blocks ) ) {
					// Prepare post, term and user ids
					$blocks = self::process_gutenberg_blocks( 
						$blocks,
						$replace_data
					);
					if ( ! empty( $blocks ) && is_array( $blocks ) ) {
						$content = serialize_blocks( $blocks );
						$should_update_post = true;
					}
				}
			} catch ( \Throwable $th ) {
				error_log( 'Error processing Gutenberg blocks: ' . $th->getMessage() );
			}
		}

		if ( $should_update_post ) {
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => wp_slash( $content ),
			) );
		}

		self::replace_elementor_metadata( $post_id, $replace_data );
	}

	
	/**
	 * Replace Elementor metadata
	 * Replaces post and term ids in Elementor data.
	 * @param int $post_id The post id
	 * @param array $replace_data The data for replacement
	 * 
	 * @return void
	 */
	private static function replace_elementor_metadata( $post_id, $replace_data ) {
		if ( ! InstaWP_Sync_Helpers::is_built_with_elementor( $post_id ) ) {
			return;
		}
		// Update Elementor data
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $elementor_data ) || ! is_string( $elementor_data ) || ( empty( $replace_data['post_ids'] ) && empty( $replace_data['term_ids'] ) ) ) {
			return;
		}

		$elementor_data = json_decode( $elementor_data, true );
		if ( ! empty( $elementor_data ) ) {
			$elementor_data = self::replace_in_elementor_data( $elementor_data, $replace_data );
			// We need to use wp_slash in order to avoid unslashing during the update_post_meta
			$elementor_data = wp_slash( wp_json_encode( $elementor_data ) );
			update_metadata( 'post', $post_id, '_elementor_data', $elementor_data );
		}
	}

	/**
	 * Add reference ids to the data array
	 *
	 * @param array $data The data array
	 * @param int|array $id The id or array of ids
	 * @param string $type The type of data
	 * @param string $taxonomy The taxonomy
	 * @return void
	 */
	private static function add_reference_data( &$data, $id, $type = 'post_ids', $taxonomy = '' ) {
		if ( empty( $id ) ) {
			return;
		}
		// Add reference data for array of ids
		if ( is_array( $id ) ) {
			foreach ( $id as $id_val ) {
				if ( is_array( $id_val ) ) {
					return;
				}
				self::add_reference_data( $data, $id_val, $type, $taxonomy );
			}
			return;
		}
		// Return if id is not numeric
		if ( ! is_numeric( $id ) ) {
			return;
		}
		$id = intval( $id );
		// Return if reference id is already set
		if ( isset( $data[ $type ][ $id ] ) || 0 >= $id ) {
			return;
		}

		switch ( $type ) {
			case 'post_ids':
				$post = InstaWP_Sync_Helpers::get_post_type_name_reference_id( $id );
				if ( ! empty( $post ) ) {
					$data[ $type ][ $id ] = $post;
				}
				break;
			case 'term_ids':
				$term = InstaWP_Sync_Helpers::get_term_taxonomy_slug_reference_id( $id, $taxonomy );
				if ( ! empty( $term ) ) {
					$data[ $type ][ $id ] = $term;
				}
				break;
			case 'user_ids':
				$user = get_user_by('id', $id);
				if ( ! empty( $user ) ) {
					$data[ $type ][ $id ] = array(
						'reference_id' => InstaWP_Sync_Helpers::get_user_reference_id( $user->ID ),
						'user_email'   => $user->user_email,
					);
				}
				break;
			default:
				break;
		}
	}

	/**
	 * Get dynamic data from Gutenberg blocks
	 *
	 * @param string $blocks The Gutenberg blocks
	 * 
	 * @return array Dynamic data
	 */
	private static function extract_dynamic_gutenberg_data( $blocks, $dynamic_data = array() ) {
		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			return $dynamic_data;
		}
		// Set dynamic data
		if ( ! isset( $dynamic_data['post_ids'] ) ) {
			$dynamic_data = array(
				'post_ids' => array(),
				'term_ids' => array(),
				'user_ids' => array(),
			);
		}

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				// Image block
				if ( in_array( $block['blockName'], array( 'core/image', 'kadence/image', 'uagb/image' ) ) && ! empty( $block['attrs']['id'] ) ) {
					self::add_reference_data( $dynamic_data, $block['attrs']['id'] );
					continue;
				} elseif ( in_array( $block['blockName'], array( 'stackable/image' ) ) && ! empty( $block['attrs']['imageId'] ) ) {
					self::add_reference_data( $dynamic_data, $block['attrs']['imageId'] );
					continue;
				}

				// Kadence blocks
				if ( false !== strpos( $block['blockName'], 'kadence/' ) ) {
					foreach ( array( 'id', 'postID', 'formID', 'ids', 'icon', 'source', 'mediaIcon', 'categories', 'tags', 'authors' ) as $attr_key ) {
						// Skip if attribute is empty
						if ( empty( $block['attrs'][ $attr_key ] ) ) {
							continue;
						}
						$attr_value = $block['attrs'][ $attr_key ];

						if ( in_array( $attr_key, array( 'id', 'ids', 'postID', 'formID' ) ) ) {
							self::add_reference_data( $dynamic_data, $attr_value );
						} elseif ( 'icon' === $attr_key ) {
							if ( false !== strpos( $attr_value, 'kb-custom-' ) ) {
								// Remove kb-custom- prefix
								$icon_id = str_replace( 'kb-custom-', '', $attr_value );
								self::add_reference_data( $dynamic_data, $icon_id );
							}
						} elseif ( 'source' === $attr_key ) {
							if ( in_array( $block['blockName'], array( 'kadence/dynamiclist', 'kadence/dynamichtml', 'kadence/repeater' ) ) ) {
								self::add_reference_data( $dynamic_data, $attr_value );
							}
						} elseif ( 'mediaIcon' === $attr_key ) {
							if ( is_array( $attr_value ) ) {
								foreach ( $attr_value as $media_icon ) {
									if ( ! empty( $media_icon['icon'] ) && false !== strpos( $media_icon['icon'], 'kb-custom-' ) ) {
										// Remove kb-custom- prefix
										$icon_id = str_replace( 'kb-custom-', '', $media_icon['icon'] );
										self::add_reference_data( $dynamic_data, $icon_id );
									}
								}
							}
						} elseif ( in_array( $attr_key, array( 'categories', 'tags' ) ) ) {
							if ( is_array( $attr_value ) ) {
								foreach ( $attr_value as $attr_val ) {
									if ( ! is_array( $attr_val ) || empty( $attr_val['value'] ) ) {
										continue;
									}
									self::add_reference_data(
										$dynamic_data,
										$attr_val['value'], 
										'term_ids',
										$attr_key === 'categories' ? 'category' : 'post_tag'
									);
								}
							}
						} elseif ( 'authors' === $attr_key ) {
							if ( is_array( $attr_value ) ) {
								foreach ( $attr_value as $attr_val ) {
									if ( ! is_array( $attr_val ) || empty( $attr_val['value'] ) ) {
										continue;
									}
									self::add_reference_data(
										$dynamic_data,
										$attr_val['value'], 
										'user_ids'
									);
								}
							}
						}
					}
				} elseif ( false !== strpos( $block['blockName'], 'uagb/' ) ) {
					// Spectra blocks
					foreach ( array( 'id', 'mediaIDs', 'categories', 'tags', 'authors' ) as $attr_key ) {
						// Skip if attribute is empty
						if ( empty( $block['attrs'][ $attr_key ] ) ) {
							continue;
						}
						$attr_value = $block['attrs'][ $attr_key ];

						if ( in_array( $attr_key, array( 'id', 'mediaIDs' ) ) ) {
							self::add_reference_data( $dynamic_data, $attr_value );
						} elseif ( 'categories' === $attr_key ) {
							self::add_reference_data( 
								$dynamic_data, 
								$attr_value,
								'term_ids',
								empty( $block['attrs']['taxonomyType'] ) ? 'category' : $block['attrs']['taxonomyType']
							);
						}
					}
				} elseif ( false !== strpos( $block['blockName'], 'stackable/' ) ) {
					foreach ( array( 'taxonomy' ) as $attr_key ) {
						// Skip if attribute is empty
						if ( empty( $block['attrs'][ $attr_key ] ) ) {
							continue;
						}
						$attr_value = $block['attrs'][ $attr_key ];
						if ( 'taxonomy' === $attr_key ) {
							if ( is_string( $attr_value ) ) {
								$ids = explode( ',', $attr_value );
								self::add_reference_data( 
									$dynamic_data, 
									$ids, 
									'term_ids', 
									empty( $block['attrs']['taxonomyType'] ) ? 'category' : $block['attrs']['taxonomyType'] 
								);
							}
						}
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$dynamic_data = self::extract_dynamic_gutenberg_data( $block['innerBlocks'], $dynamic_data );
			}
		}
		return $dynamic_data;
	}


	/**
	 * Get dynamic data from Elementor data
	 *
	 * @param array $elementor_data The Elementor data array
	 * 
	 * @return array Dynamic data
	 */
	private static function extract_dynamic_elementor_data( $elementor_data, $dynamic_data = array() ) {
		if ( empty( $elementor_data ) || ! is_array( $elementor_data ) ) {
			return $dynamic_data;
		}

		if ( ! isset( $dynamic_data['post_ids'] ) ) {
			$dynamic_data = array(
				'post_ids' => array(),
				'term_ids' => array(),
				'user_ids' => array(),
			);
		}
		
		// Recursively process each element
		foreach ( $elementor_data as $element ) {
			// Process settings
			if ( ! empty( $element['settings'] ) && is_array( $element['settings'] ) ) {
				foreach ( $element['settings'] as $key => $value ) {

					if ( '__dynamic__' === $key ) {
						foreach ( $value as $dynamic_key => $dynamic_value ) {
							// Extract ID and settings from the dynamic tag.
							$tag = self::elementor_tag_text_to_tag_data( $dynamic_value );
							if ( ! empty( $tag ) && ! empty( $tag['settings'] ) ) {
								$tag = $tag['settings'];
								
								foreach ( array(
									'attachment_id',
									'post_id',
									'author_id',
									'taxonomy_id',
								) as $tag_key ) {
									$type = 'post_ids';
									if ( 'author_id' === $tag_key ) {
										$type = 'user_ids';
									} elseif ( 'taxonomy_id' === $tag_key ) {
										$type = 'term_ids';
									}
									self::add_reference_data( $dynamic_data, $tag[ $tag_key ], $type );
								}
							}
						}
					} elseif ( $key === 'wp' && is_array( $value ) ) {
						// Handle WordPress widgets
						foreach ( $value as $wp_key => $wp_value ) {
							if ( 'exclude' === $wp_key ) {
								$exclude_ids = explode( ',', $wp_value );
								foreach ( $exclude_ids as $exclude_key => $exclude_id ) {
									self::add_reference_data( $dynamic_data, $exclude_id );
								}
							} elseif ( 'nav_menu' === $wp_key ) {
								if ( ! is_array( $wp_value ) && 0 < intval( $wp_value ) ) {
									self::add_reference_data( $dynamic_data, $wp_value, 'term_ids', 'nav_menu' );
								}
							}
						}
					}
				}
			}

			// Process elements recursively
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$dynamic_data = self::extract_dynamic_elementor_data( $element['elements'], $dynamic_data );
			}
		}

		return $dynamic_data;
	}

	/**
	 * Replace items in Elementor data
	 *
	 * @param array $elementor_data The Elementor data array
	 * @param array $replace_data Array of items to replace ['old_id' => 'new_id', 'old_url' => 'new_url']
	 * @return array Modified Elementor data
	 */
	private static function replace_in_elementor_data( $elementor_data, $replace_data ) {
		if ( empty( $elementor_data ) || ! is_array( $elementor_data ) ) {
			return $elementor_data;
		}

		// Recursively process each element
		foreach ( $elementor_data as &$element ) {
			// Process settings
			if ( ! empty( $element['settings'] ) ) {
				$element['settings'] = self::process_elementor_data_settings( $element['settings'], $replace_data );
			}

			// Process elements recursively
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::replace_in_elementor_data( $element['elements'], $replace_data );
			}
		}

		return $elementor_data;
	}

	/**
	 * Process settings array replacements
	 *
	 * @param array $settings
	 * @param array $replace_data
	 * @return array
	 */
	private static function process_elementor_data_settings( $settings, $replace_data ) {
		foreach ( $settings as $key => &$value ) {

			if ( $key === 'image' && is_array( $value ) ) {
				// Handle image widget
				if ( ! empty( $value['id'] ) && isset( $replace_data['post_ids'][ $value['id'] ] ) ) {
					$value['id'] = $replace_data['post_ids'][ $value['id'] ];
				}
				if ( ! empty( $value['url'] ) && ! empty( $replace_data['urls'][ wp_unslash( $value['url'] ) ] ) ) {
					$value['url'] = $replace_data['urls'][ wp_unslash( $value['url'] ) ];
				}
			} elseif ( $key === 'selected_icon' && is_array( $value ) && ! empty( $value['value']['id'] ) ) {
				// Handle icon widget with SVG
				if ( isset( $replace_data['post_ids'][ $value['value']['id'] ] ) ) {
					$value['value']['id'] = $replace_data['post_ids'][ $value['value']['id'] ];
				}
				if ( ! empty( $value['value']['url'] ) && ! empty( $replace_data['urls'][ wp_unslash( $value['value']['url'] ) ] ) ) {
					$value['value']['url'] = $replace_data['urls'][ wp_unslash( $value['value']['url'] ) ];
				}
			} elseif ( '__dynamic__' === $key ) {
				foreach ( $value as $dynamic_key => &$dynamic_value ) {
					// Extract ID and settings from the dynamic tag.
					$tag_data = self::elementor_tag_text_to_tag_data( $dynamic_value );
					if ( ! empty( $tag_data ) ) {
						$tag_id       = $tag_data['id'];
						$tag_name     = $tag_data['name'];
						$tag_settings = $tag_data['settings'];
						
						if ( ! empty( $tag_settings ) ) {
							// Replace attachment ID.
							if ( isset( $tag_settings['attachment_id'] ) && isset( $replace_data['post_ids'][ $tag_settings['attachment_id'] ] ) ) {
								$tag_settings['attachment_id'] = $replace_data['post_ids'][ $tag_settings['attachment_id'] ];
							}
							
							// Replace post ID.
							if ( isset( $tag_settings['post_id'] ) && isset( $replace_data['post_ids'][ $tag_settings['post_id'] ] ) ) {
								$tag_settings['post_id'] = $replace_data['post_ids'][ $tag_settings['post_id'] ];
							}

							// Replace author ID.
							if ( isset( $tag_settings['author_id'] ) && isset( $replace_data['user_ids'][ $tag_settings['author_id'] ] ) ) {
								$tag_settings['author_id'] = $replace_data['user_ids'][ $tag_settings['author_id'] ];
							}

							// Replace taxonomy ID.
							if ( isset( $tag_settings['taxonomy_id'] ) && isset( $replace_data['term_ids'][ $tag_settings['taxonomy_id'] ] ) ) {
								$tag_settings['taxonomy_id'] = $replace_data['term_ids'][ $tag_settings['taxonomy_id'] ];
							}
							
							// Rebuild the dynamic tag with updated settings.
							$dynamic_value = sprintf(
								'[elementor-tag id="%s" name="%s" settings="%s"]',
								$tag_id,
								$tag_name,
								urlencode( wp_json_encode( $tag_settings, JSON_FORCE_OBJECT ) )
							);
						}
					}
				}
			} elseif ( $key === 'wp' && is_array( $value ) ) {
				// Handle WordPress widgets
				foreach ( $value as $wp_key => $wp_value ) {
					if ( 'exclude' === $wp_key ) {
						$exclude_ids = explode( ',', $wp_value );
						foreach ( $exclude_ids as $exclude_key => $exclude_id ) {
							$exclude_ids[ $exclude_key ] = isset( $replace_data['post_ids'][ $exclude_id ] ) ? $replace_data['post_ids'][ $exclude_id ] : $exclude_id;
						}
						$value[ $wp_key ] = implode( ',', $exclude_ids );
					} elseif ( 'nav_menu' === $wp_key && ! empty( $replace_data['term_ids'][ $wp_value ] ) ) {
						$value[ $wp_key ] = $replace_data['term_ids'][ $wp_value ];
					}
				}
			}
		}

		return $settings;
	}

	/**
	 * Convert Elementor tag text to tag data
	 *
	 * @param string $tag_text
	 * @return array|null
	 */
	public static function elementor_tag_text_to_tag_data( $tag_text ) {
		preg_match( '/id="(.*?(?="))"/', $tag_text, $tag_id_match );
		preg_match( '/name="(.*?(?="))"/', $tag_text, $tag_name_match );
		preg_match( '/settings="(.*?(?="]))/', $tag_text, $tag_settings_match );

		if ( ! $tag_id_match || ! $tag_name_match || ! $tag_settings_match ) {
			return null;
		}

		return array(
			'id'       => $tag_id_match[1],
			'name'     => $tag_name_match[1],
			'settings' => json_decode( urldecode( $tag_settings_match[1] ), true ),
		);
	}

	
    public static function upload_attachment( $fields = array() ) {
        $attachment_id = isset( $fields['post_id'] ) ? $fields['post_id'] : 0;
        $connect_id    = instawp_get_connect_id();
        if ( ! $attachment_id || ! $connect_id ) {
            return $fields;
        }

        $local_file    = get_attached_file( $attachment_id );
        $boundary      = wp_generate_password( 24, false );
        $headers       = array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        );
        $payload       = '';
        $file_name     = $fields['filename'];
        $fields['filename'] = $fields['basename'];

        // First, add the standard POST fields
        foreach ( $fields as $name => $value ) {
            if ( is_array( $value ) || is_object( $value ) ) {
                continue;
            }
            $payload .= '--' . $boundary . "\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n";
            $payload .= "\r\n" . $value . "\r\n";
        }

        // Upload the file
        if ( $local_file ) {
            $payload .= '--' . $boundary . "\r\n";
            $payload .= 'Content-Disposition: form-data; name="file"; filename="' . $fields['filename'] . '"' . "\r\n";
            $payload .= 'Content-Type: ' . $fields['file_type'] . "\r\n";
            $payload .= "\r\n" . file_get_contents( $local_file ) . "\r\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        }
        $payload .= '--' . $boundary . '--';

        // Set it back
        $fields['filename'] = $file_name;

        // sync/<connect_id>/upload-attachment
        $response = Curl::do_curl( "sync/{$connect_id}/upload-attachment", $payload, $headers );
        if ( $response['code'] !== 200 || ! $response['success'] ) {
            return $fields;
        }

        $data = $response['data'];
        unset( $data['file'] );

        return array_merge( $fields, $data );
    }

	/**
	 * Sleep for 5 seconds every 25 attachments
	 * 
	 * @param int $process_count
	 * 
	 * @return int $process_count
	 */
	public static function check_sleep( $process_count ) {
		++$process_count;
		if ( 0 === $process_count % 25 ) {
			// sleep for 5 seconds
			sleep( 5 );
		}
		return $process_count;
	}

	/**
	 * Is already uploaded
	 * 
	 * @param array $media
	 * @param array $list media post id list
	 * 
	 * @return boolean
	 */
	public static function is_already_uploaded( $media, $list ) {
		return ( ! empty( $media['post_id'] ) && in_array( $media['post_id'], $list ) );
	}

	/**
	 * Add media in process list
	 * 
	 * @param array $media
	 * 
	 */
	public static function add_media_in_process_list( $media, &$processed_media_ids ) {
		if ( ! empty( $media['post_id'] ) && ! empty( $media['url'] ) && ! in_array( $media['post_id'], $processed_media_ids ) ) {
			$processed_media_ids[] = $media['post_id'];
		}
	}

	/**
	 * Get set processed attachments
	 * 
	 * @param int $dest_connect_id
	 * @param array $processed_ids
	 * 
	 * @return array
	 */
	public static function get_set_processed_attachments( $dest_connect_id, $processed_ids = array() ) {
		// Get processed media ids
		$iwp_sync_processed_media_ids = get_option( 'iwp_sync_processed_media_ids' );
		$iwp_sync_processed_media_ids = empty( $iwp_sync_processed_media_ids ) ? array() : $iwp_sync_processed_media_ids;
		
		if ( 0 < count( $processed_ids ) ) {
			// Update processed media ids
			$iwp_sync_processed_media_ids[ $dest_connect_id ] = $processed_ids;
			update_option( 'iwp_sync_processed_media_ids', $iwp_sync_processed_media_ids );
		}

		// Get processed media ids against destination connect id
		$processed_media_ids = empty( $iwp_sync_processed_media_ids[ $dest_connect_id ] ) ? array() : $iwp_sync_processed_media_ids[ $dest_connect_id ];

		return $processed_media_ids;
	}


    public static function process_attachments( $array_data, $dest_connect_id, $is_upload ) {
		if ( ! $is_upload || empty( $array_data ) || ! is_array( $array_data ) ) {
			return $array_data;
		}
		
		// Get processed media ids
		$processed_media_ids = InstaWP_Sync_Parser::get_set_processed_attachments( $dest_connect_id );

		$process_count = 0;
        foreach ( ( array ) $array_data as $key => $value ) {
            if ( in_array( $key, array( 'attachment', 'featured_image' ) ) ) {
				// Continue if already uploaded before
				if ( InstaWP_Sync_Parser::is_already_uploaded( $value, $processed_media_ids ) ) {
					continue;
				}
				$process_count = InstaWP_Sync_Parser::check_sleep( $process_count );
                $array_data[ $key ] = InstaWP_Sync_Parser::upload_attachment( $value );
				InstaWP_Sync_Parser::add_media_in_process_list( $array_data[ $key ], $processed_media_ids );
				InstaWP_Sync_Parser::get_set_processed_attachments( $dest_connect_id, $processed_media_ids );
            } elseif ( in_array( $key, array( 'media', 'product_gallery' ) ) ) {
                $array_data[ $key ] = array_map( function ( $value ) use ( &$process_count, &$processed_media_ids ) {
					// Continue if already uploaded before
					if ( InstaWP_Sync_Parser::is_already_uploaded( $value, $processed_media_ids ) ) {
						return $value;
					}

					// Sleep if needed
					$process_count = InstaWP_Sync_Parser::check_sleep( $process_count );
                    $value = InstaWP_Sync_Parser::upload_attachment( $value );
					InstaWP_Sync_Parser::add_media_in_process_list( $value, $processed_media_ids );
					return $value;
                }, $value );
				InstaWP_Sync_Parser::get_set_processed_attachments( $dest_connect_id, $processed_media_ids );
            } elseif ( is_array( $value ) ) {
                $array_data[ $key ] = self::process_attachments( $value, $dest_connect_id, $is_upload );
            }
        }
        
        return $array_data;
    }
}