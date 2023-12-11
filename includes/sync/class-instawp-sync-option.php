<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Post {

    public function __construct() {
	    // User actions
	    add_action( 'user_register', [$this,'user_register' ], 10, 2 );
	    add_action( 'delete_user', [ $this,'delete_user' ], 10, 3 );
	    add_action( 'profile_update', [ $this,'profile_update' ], 10, 3 );
    }

	/**
	 * Function for `user_register` action-hook.
	 *
	 * @param int   $user_id  User ID.
	 * @param array $userdata The raw array of data passed to wp_insert_user().
	 *
	 * @return void
	 */
	public function user_register( $user_id, $userdata ) {
		if ( empty( $userdata ) ) {
			return;
		}

		$event_name = __( 'New user registered', 'instawp-connect' );
		$user       = get_user_by( 'id', $user_id );

		$userdata['user_registered']     = $user->data->user_registered;
		$userdata['user_activation_key'] = $user->data->user_activation_key;

		InstaWP_Sync_Helpers::set_user_reference_id( $user_id );
		$details = [ 'user_data' => $userdata, 'user_meta' => get_user_meta( $user_id), 'db_prefix'=> $this->wpdb->prefix ];

		InstaWP_Sync_DB::insert_update_event( $event_name, 'user_register', 'users', $user_id, $userdata['user_login'], $details );
	}

	/**
	 * Function for `delete_user` action-hook.
	 *
	 * @param int      $id       ID of the user to delete.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 * @param WP_User  $user     WP_User object of the user to delete.
	 *
	 * @return void
	 */
	public function delete_user( $id, $reassign, $user ) {
		$event_name = __('User deleted', 'instawp-connect');
		$title      = $user->data->user_login;
		$details    = [ 'user_data' => get_userdata( $id ), 'user_meta' => get_user_meta( $id ) ];

		InstaWP_Sync_DB::insert_update_event( $event_name, 'delete_user', 'users', $id, $title, $details );
	}

	/**
	 * Function for `profile_update` action-hook.
	 *
	 * @param int     $user_id       User ID.
	 * @param WP_User $old_user_data Object containing user's data prior to update.
	 * @param array   $userdata      The raw array of data passed to wp_insert_user().
	 *
	 * @return void
	 */
	public function profile_update( $user_id, $old_user_data, $userdata ) {
		if ( ! empty( $userdata ) && isset( $_POST['submit'] ) ) {
			$event_name = __( 'User updated', 'instawp-connect' );
			InstaWP_Sync_Helpers::set_user_reference_id( $user_id );

			$userData = InstaWP_Sync_DB::getByInCondition( $this->wpdb->prefix . 'users', [ 'ID' => $user_id ] );
			if ( isset( $userData[0] ) ) {
				$details = [
					'user_data' => $userData[0],
					'user_meta' => get_user_meta( $user_id ),
					'role'      => $userdata['role'],
					'db_prefix' => $this->wpdb->prefix
				];

				InstaWP_Sync_DB::insert_update_event( $event_name, 'profile_update', 'users', $user_id, $userdata['user_login'], $details );
			}
		}
	}
}