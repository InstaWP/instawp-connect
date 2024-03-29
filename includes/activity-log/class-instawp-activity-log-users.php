<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class InstaWP_Activity_Log_Users {

	public function __construct() {
		add_action( 'wp_login', array( $this, 'hooks_wp_login' ), 10, 2 );
		add_action( 'clear_auth_cookie', array( $this, 'hooks_clear_auth_cookie' ) );
		add_action( 'delete_user', array( $this, 'hooks_delete_user' ) );
		add_action( 'user_register', array( $this, 'hooks_user_register' ) );
		add_action( 'profile_update', array( $this, 'hooks_profile_update' ) );
		add_filter( 'wp_login_failed', array( $this, 'hooks_wrong_password' ) );
	}

	public function hooks_user_register( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		InstaWP_Activity_Log::insert_log( array(
			'action'         => 'user_registered',
			'object_type'    => 'Users',
			'object_subtype' => 'Profile',
			'object_id'      => $user->ID,
			'object_name'    => ! empty( $user->display_name ) ? $user->display_name : $user->user_nicename,
		) );
	}
	public function hooks_delete_user( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		InstaWP_Activity_Log::insert_log( array(
			'action'         => 'user_deleted',
			'object_type'    => 'Users',
			'object_subtype' => 'Profile',
			'object_id'      => $user->ID,
			'object_name'    => ! empty( $user->display_name ) ? $user->display_name : $user->user_nicename,
		) );
	}

	public function hooks_wp_login( $user_login, $user ) {
		InstaWP_Activity_Log::insert_log( array(
			'action'         => 'user_logged_in',
			'object_type'    => 'Users',
			'object_subtype' => 'Session',
			'user_id'        => $user->ID,
			'object_id'      => $user->ID,
			'object_name'    => ! empty( $user->display_name ) ? $user->display_name : $user->user_nicename,
		) );
	}

	public function hooks_clear_auth_cookie() {
		$user = wp_get_current_user();

		if ( empty( $user ) || ! $user->exists() ) {
			return;
		}

		InstaWP_Activity_Log::insert_log( array(
			'action'         => 'user_logged_out',
			'object_type'    => 'Users',
			'object_subtype' => 'Session',
			'user_id'        => $user->ID,
			'object_id'      => $user->ID,
			'object_name'    => ! empty( $user->display_name ) ? $user->display_name : $user->user_nicename,
		) );
	}

	public function hooks_profile_update( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		InstaWP_Activity_Log::insert_log( array(
			'action'         => 'user_updated',
			'object_type'    => 'Users',
			'object_subtype' => 'Profile',
			'object_id'      => $user->ID,
			'object_name'    => ! empty( $user->display_name ) ? $user->display_name : $user->user_nicename,
		) );
	}

	public function hooks_wrong_password( $username ) {
		InstaWP_Activity_Log::insert_log( array(
			'action'         => 'user_failed_login',
			'object_type'    => 'Users',
			'object_subtype' => 'Session',
			'user_id'        => 0,
			'object_id'      => 0,
			'object_name'    => $username,
		) );
	}
}

new InstaWP_Activity_Log_Users();