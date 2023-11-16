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
		return get_post_meta( $post_id, 'instawp_event_sync_reference_id', true ) ?? 0;
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
		return get_user_meta( $user_id, 'instawp_event_user_sync_reference_id', true ) ?? 0;
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
}