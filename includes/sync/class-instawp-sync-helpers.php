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
 * This file is used for change event traking 
 * 
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/admin
 * @author     instawp team
 */

defined( 'ABSPATH' ) || die;

class InstaWP_Sync_Helpers {

	/**
	 * get post type singular name
	 *
	 * @param $post_type
	 *
	 * @return string
	 */
	public static function get_post_type_name( $post_type ): string {
		$post_type_object = get_post_type_object( $post_type );
		if ( ! empty( $post_type_object ) ) {
			return $post_type_object->labels->singular_name;
		}

		return '';
	}

	/*
     * Update post metas
     */
	public static function get_post_reference_id( $post_id ): string {
		return get_post_meta( $post_id, 'instawp_event_sync_reference_id', true ) ?? '0';
	}

    /*
     * Update post metas
     */
    public static function set_post_reference_id( $post_id ): void {
		$reference_id = self::get_post_reference_id( $post_id );

        if ( empty( $reference_id ) ) {
            update_post_meta( $post_id, 'instawp_event_sync_reference_id', InstaWP_Tools::get_random_string() );
        }
    }

	/*
     * Get user metas
     */
	public static function get_user_reference_id( $user_id ): string {
		return get_user_meta( $user_id, 'instawp_event_user_sync_reference_id', true ) ?? '0';
	}

    /*
     * Update user metas
     */
    public static function set_user_reference_id( $user_id ): void {
	    $reference_id = self::get_user_reference_id( $user_id );

	    if ( empty( $reference_id ) ) {
            update_user_meta( $user_id, 'instawp_event_user_sync_reference_id', InstaWP_Tools::get_random_string() );
		}
    }

	public static function can_sync(): bool {
		$syncing_status = get_option( 'instawp_is_event_syncing', 0 );

		return ( intval( $syncing_status ) === 1 );
	}

	/**
	 * Get media from content
	 */
	public static function get_media_from_content( $content = null ): string {
		global $wpdb;
		
		#find media form content.
		preg_match_all( '!(https?:)?//\S+\.(?:jpe?g|jpg|png|gif|mp4|pdf|doc|docx|xls|xlsx|csv|txt|rtf|html|zip|mp3|wma|mpg|flv|avi)!Ui', $content, $match );
		
		$media = [];
		if ( isset( $match[0] ) ) {
			$attachment_urls = array_unique( $match[0] );

			foreach ( $attachment_urls as $attachment_url ) {
				if ( strpos( $attachment_url, $_SERVER['HTTP_HOST'] ) !== false ) {
					$full_attachment_url = preg_replace('~-[0-9]+x[0-9]+.~', '.', $attachment_url );
					$attachment_id       = attachment_url_to_postid( $full_attachment_url );

					if ( $attachment_id === 0 ) {
						$post_name = sanitize_title( pathinfo( $full_attachment_url, PATHINFO_FILENAME ) );

						$sql     = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_name = '%s'", $post_name );
						$results = $wpdb->get_results( $sql );

						if ( $results ) {
							// Use the first available result, but prefer a case-sensitive match, if exists.
							$attachment_id = reset( $results )->ID;
						}
					}

					#It's check media exist or not
					$media[] = [
						'attachment_url'        => $attachment_url,
						'attachment_id'         => $attachment_id,
						'attachment_media'      => get_post( $attachment_id ),
						'attachment_media_meta' => get_post_meta( $attachment_id ),
					];
				}
			}
		}

		return wp_json_encode( $media );
	}

	public static function sync_response( $data, $log_data = [], $args = [] ): array {
		$data = [
			'data' => wp_parse_args( $args, [
				'id'      => $data->id,
				'status'  => 'completed',
				'message' => 'Sync successfully.'
			] )
		];

		if ( ! empty( $log_data ) ) {
			$data['log_data'] = $log_data;
		}

		return $data;
	}
}