<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_User {

    public function __construct() {
	    // User actions
	    add_action( 'user_register', array( $this, 'user_register' ), 10, 2 );
	    add_action( 'delete_user', array( $this, 'delete_user' ), 10, 3 );
	    add_action( 'profile_update', array( $this, 'profile_update' ), 10, 3 );

		// Process event
	    add_filter( 'INSTAWP_CONNECT/Filters/process_two_way_sync', array( $this, 'parse_event' ), 10, 2 );
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
		if ( ! InstaWP_Sync_Helpers::can_sync( 'user' ) ) {
			return;
		}

		if ( empty( $userdata ) ) {
			return;
		}

		$event_name = __( 'New user registered', 'instawp-connect' );
		$user       = get_user_by( 'id', $user_id );

		$userdata['user_registered']     = $user->data->user_registered;
		$userdata['user_activation_key'] = $user->data->user_activation_key;

		InstaWP_Sync_Helpers::set_user_reference_id( $user_id );
		$details = array(
			'user_data' => $userdata,
			'user_meta' => get_user_meta( $user_id ),
			'db_prefix' => InstaWP_Sync_DB::prefix(),
		);

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
		if ( ! InstaWP_Sync_Helpers::can_sync( 'user' ) ) {
			return;
		}

		$event_name = __('User deleted', 'instawp-connect');
		$title      = $user->data->user_login;
		$details    = array(
			'user_data' => get_userdata( $id ),
			'user_meta' => get_user_meta( $id ),
		);

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
		if ( ! InstaWP_Sync_Helpers::can_sync( 'user' ) ) {
			return;
		}

		if ( ! empty( $userdata ) && isset( $_POST['submit'] ) ) {
			$event_name = __( 'User updated', 'instawp-connect' );
			InstaWP_Sync_Helpers::set_user_reference_id( $user_id );

			$userData = InstaWP_Sync_DB::getByInCondition( InstaWP_Sync_DB::prefix() . 'users', array( 'ID' => $user_id ) );
			if ( isset( $userData[0] ) ) {
				$details = array(
					'user_data' => $userData[0],
					'user_meta' => get_user_meta( $user_id ),
					'role'      => $userdata['role'],
					'db_prefix' => InstaWP_Sync_DB::prefix(),
				);

				InstaWP_Sync_DB::insert_update_event( $event_name, 'profile_update', 'users', $user_id, $userdata['user_login'], $details );
			}
		}
	}

	public function parse_event( $response, $v ) {
		if ( $v->event_type !== 'users' ) {
			return $response;
		}

		$user_data        = isset( $v->details->user_data ) ? ( array ) $v->details->user_data : array();
		$user_meta        = isset( $v->details->user_meta ) ? ( array ) $v->details->user_meta : array();
		$source_db_prefix = isset( $v->details->db_prefix ) ? ( array ) $v->details->db_prefix : '';
		$user_table       = InstaWP_Sync_DB::prefix() . 'users';
		$log_data         = array();

		$get_user_by_reference_id = get_users( array(
			'meta_key'   => 'instawp_event_user_sync_reference_id',
			'meta_value' => isset( $user_meta['instawp_event_user_sync_reference_id'][0] ) ? $user_meta['instawp_event_user_sync_reference_id'][0] : '',
		) );
		$user = ! empty( $get_user_by_reference_id ) ? reset( $get_user_by_reference_id ) : get_user_by( 'email', $user_data['email'] );

		if ( $v->event_slug === 'user_register' && ! empty( $user_data ) && ! $user ) {
			$user_id = wp_insert_user( $user_data );
			if ( is_wp_error( $user_id ) ) {
				$log_data[ $v->id ] = $user_id->get_error_message();
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
					$log_data[ $v->id ] = $user_id->get_error_message();
				} else {
					InstaWP_Sync_DB::update( $user_table, array( 'user_pass' => $user_pass ), array( 'ID' => $user_id ) );
					$this->manage_usermeta( $user_meta, $user_id );
					$user->add_role( $v->details->role );
				}
			} else {
				$log_data[ $v->id ] = sprintf( 'User not found for update operation.' );
			}
		}

		if ( $v->event_slug === 'delete_user' ) {
			if ( $user ) {
				wp_delete_user( $user->data->ID );
			} else {
				$log_data[ $v->id ] = sprintf( 'User not found for delete operation.' );
			}
		}

		return InstaWP_Sync_Helpers::sync_response( $v, $log_data );
	}

	public function manage_usermeta( $user_meta = null, $user_id = null, $source_db_prefix = null ) {
		if ( ! empty( $user_meta ) && is_array( $user_meta ) ) {
			foreach ( $user_meta as $key => $value ) {
				$key = $source_db_prefix != '' ? str_replace( $source_db_prefix, InstaWP_Sync_DB::wpdb()->prefix, $key ) : $key;

				update_user_meta( $user_id, $key, maybe_unserialize( reset( $value ) ) );
			}
		}
	}
}

new InstaWP_Sync_User();