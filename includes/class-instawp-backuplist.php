<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class InstaWP_Backuplist {
	public static function get_backup_by_id( $id ) {
		$lists[] = 'instawp_backup_list';
		$lists   = apply_filters( 'instawp_get_backuplist_name', $lists );
		foreach ( $lists as $list_name ) {
			$list = InstaWP_Setting::get_option( $list_name );
			foreach ( $list as $k => $backup ) {
				if ( $id == $k ) {
					return $backup;
				}
			}
		}

		return false;
	}

	public static function update_backup_option( $backup_id, $backup_new ) {
		$lists[] = 'instawp_backup_list';
		$lists   = apply_filters( 'instawp_get_backuplist_name', $lists );
		foreach ( $lists as $list_name ) {
			$list = InstaWP_Setting::get_option( $list_name );
			foreach ( $list as $k => $backup ) {
				if ( $backup_id == $k ) {
					$list[ $backup_id ] = $backup_new;
					InstaWP_Setting::update_option( $list_name, $list );

					return;
				}
			}
		}
	}

	public static function get_backuplist( $list_name = '' ) {

		add_filter( 'instawp_get_backuplist', array( 'InstaWP_Backuplist', 'get_backup_list' ), 10, 2 );

		return apply_filters( 'instawp_get_backuplist', array(), $list_name );
	}

	public static function get_backup_list( $list, $list_name ) {
		return self::sort_list( InstaWP_Setting::get_option( 'instawp_backup_list' ) );
	}

	public static function get_backuplist_by_id( $id ) {
		$list = array();
		add_filter( 'instawp_get_backuplist_by_id', array( 'InstaWP_Backuplist', 'get_backup_list_by_id' ), 10, 2 );
		$ret = apply_filters( 'instawp_get_backuplist_by_id', $list, $id );

		return $ret;
	}

	public static function get_backup_list_by_id( $list, $id ) {
		$list = InstaWP_Setting::get_option( 'instawp_backup_list' );
		foreach ( $list as $k => $backup ) {
			if ( $id == $k ) {
				$ret['list_name'] = 'instawp_backup_list';
				$ret['list_data'] = $list;

				return $ret;
			}
		}

		return false;
	}

	public static function get_backuplist_by_key( $key ) {
		add_filter( 'instawp_get_backuplist_item', array( 'InstaWP_Backuplist', 'get_backuplist_item' ), 10, 2 );
		$backup = false;
		$backup = apply_filters( 'instawp_get_backuplist_item', $backup, $key );

		return $backup;
	}

	public static function get_backuplist_item( $backup, $key ) {
		$list = InstaWP_Setting::get_option( 'instawp_backup_list' );
		foreach ( $list as $k => $backup ) {
			if ( $key == $k ) {
				return $backup;
			}
		}

		return false;
	}

	public static function update_backup( $id, $key, $data ) {
		add_action( 'instawp_update_backup', array( 'InstaWP_Backuplist', 'update_backup_item' ), 10, 3 );
		do_action( 'instawp_update_backup', $id, $key, $data );
	}

	public static function update_backup_item( $id, $key, $data ) {
		$list = InstaWP_Setting::get_option( 'instawp_backup_list' );
		if ( array_key_exists( $id, $list ) ) {
			$list[ $id ][ $key ] = $data;
			InstaWP_Setting::update_option( 'instawp_backup_list', $list );
		}
	}

	public static function add_new_upload_backup( $task_id, $backup, $create_time, $log = '' ) {

		$backup_list             = InstaWP_Setting::get_option( 'instawp_backup_list', array() );
		$backup_list[ $task_id ] = array(
			'type'          => 'Upload',
			'create_time'   => $create_time,
			'manual_delete' => 0,
			'save_local'    => 1,
			'lock'          => 0,
			'log'           => $log,
			'backup'        => $backup,
			'remote'        => array(),
			'local'         => array(
				'path' => InstaWP_Setting::get_backupdir(),
			),
			'compress'      => array(
				'compress_type' => 'zip',
			),
		);

		InstaWP_Setting::update_option( 'instawp_backup_list', $backup_list );
	}

	public static function delete_backup( $key ) {
		$lists[] = 'instawp_backup_list';
		$lists   = apply_filters( 'instawp_get_backuplist_name', $lists );
		foreach ( $lists as $list_name ) {
			$list = InstaWP_Setting::get_option( $list_name );
			foreach ( $list as $k => $backup ) {
				if ( $key == $k ) {
					unset( $list[ $key ] );
					InstaWP_Setting::update_option( $list_name, $list );

					return;
				}
			}
		}
	}

	public static function sort_list( $list ) {
		uasort( $list, function ( $a, $b ) {
			if ( $a['create_time'] > $b['create_time'] ) {
				return - 1;
			} elseif ( $a['create_time'] === $b['create_time'] ) {
				return 0;
			} else {
				return 1;
			}
		} );

		return $list;
	}

	public static function get_oldest_backup_id( $list ) {
		$oldest_id = '';
		$oldest    = 0;
		foreach ( $list as $k => $backup ) {
			if ( ! array_key_exists( 'lock', $backup ) || ( isset( $backup['lock'] ) && $backup['lock'] == '0' ) ) {
				if ( $oldest == 0 ) {
					$oldest    = $backup['create_time'];
					$oldest_id = $k;
				} else {
					if ( $oldest > $backup['create_time'] ) {
						$oldest_id = $k;
					}
				}
			}
		}

		return $oldest_id;
	}

	public static function check_backuplist_limit( $max_count ) {
		$list = InstaWP_Setting::get_option( 'instawp_backup_list' );
		$size = sizeof( $list );
		if ( $size >= $max_count ) {
			$oldest_id = self::get_oldest_backup_id( $list );
			if ( empty( $oldest_id ) ) {
				return false;
			} else {
				return $oldest_id;
			}
		} else {
			return false;
		}
	}

	public static function get_out_of_date_backuplist( $max_count ) {
		$list             = InstaWP_Setting::get_option( 'instawp_backup_list' );
		$size             = sizeof( $list );
		$out_of_date_list = array();

		if ( $max_count == 0 ) {
			return $out_of_date_list;
		}

		while ( $size > $max_count ) {
			$oldest_id = self::get_oldest_backup_id( $list );

			if ( ! empty( $oldest_id ) ) {
				$out_of_date_list[] = $oldest_id;
				unset( $list[ $oldest_id ] );
			}
			$new_size = sizeof( $list );
			if ( $new_size == $size ) {
				break;
			} else {
				$size = $new_size;
			}
		}

		return $out_of_date_list;
	}

	public static function get_out_of_date_backuplist_info( $max_count ) {
		$list                      = InstaWP_Setting::get_option( 'instawp_backup_list' );
		$size                      = sizeof( $list );
		$out_of_date_list['size']  = 0;
		$out_of_date_list['count'] = 0;

		if ( $max_count == 0 ) {
			return $out_of_date_list;
		}

		while ( $size > $max_count ) {
			$oldest_id = self::get_oldest_backup_id( $list );

			if ( ! empty( $oldest_id ) ) {
				$out_of_date_list['size'] += self::get_size( $oldest_id );
				$out_of_date_list['count'] ++;
				unset( $list[ $oldest_id ] );
			}
			$new_size = sizeof( $list );
			if ( $new_size == $size ) {
				break;
			} else {
				$size = $new_size;
			}
		}

		return $out_of_date_list;
	}

	public static function get_size( $backup_id ) {
		$size   = 0;
		$list   = InstaWP_Setting::get_option( 'instawp_backup_list' );
		$backup = $list[ $backup_id ];
		if ( isset( $backup['backup']['files'] ) ) {
			foreach ( $backup['backup']['files'] as $file ) {
				$size += $file['size'];
			}
		} else {
			if ( isset( $backup['backup']['data']['type'] ) ) {
				foreach ( $backup['backup']['data']['type'] as $type ) {
					foreach ( $type['files'] as $file ) {
						$size += $file['size'];
					}
				}
			}
		}

		return $size;
	}

	public static function set_security_lock( $backup_id, $lock ) {
		//$list = InstaWP_Setting::get_option('instawp_backup_list');
		$ret = self::get_backuplist_by_id( $backup_id );
		if ( $ret !== false ) {
			$list = $ret['list_data'];
			if ( array_key_exists( $backup_id, $list ) ) {
				if ( $lock == 1 ) {
					$list[ $backup_id ]['lock'] = 1;
				} else {
					if ( array_key_exists( 'lock', $list[ $backup_id ] ) ) {
						unset( $list[ $backup_id ]['lock'] );
					}
				}
			}
			InstaWP_Setting::update_option( $ret['list_name'], $list );
		}

		$ret['result'] = 'success';
		$list          = InstaWP_Setting::get_option( $ret['list_name'] );
		if ( array_key_exists( $backup_id, $list ) ) {
			if ( isset( $list[ $backup_id ]['lock'] ) ) {
				if ( $list[ $backup_id ]['lock'] == 1 ) {
					$backup_lock = '/admin/partials/images/locked.png';
					$lock_status = 'lock';
					$ret['html'] = '<img src="' . esc_url( INSTAWP_PLUGIN_URL . $backup_lock ) . '" name="' . esc_attr( $lock_status, 'instawp-connect' ) . '" onclick="instawp_set_backup_lock(\'' . $backup_id . '\', \'' . $lock_status . '\');" style="vertical-align:middle; cursor:pointer;"/>';
				} else {
					$backup_lock = '/admin/partials/images/unlocked.png';
					$lock_status = 'unlock';
					$ret['html'] = '<img src="' . esc_url( INSTAWP_PLUGIN_URL . $backup_lock ) . '" name="' . esc_attr( $lock_status, 'instawp-connect' ) . '" onclick="instawp_set_backup_lock(\'' . $backup_id . '\', \'' . $lock_status . '\');" style="vertical-align:middle; cursor:pointer;"/>';
				}
			} else {
				$backup_lock = '/admin/partials/images/unlocked.png';
				$lock_status = 'unlock';
				$ret['html'] = '<img src="' . esc_url( INSTAWP_PLUGIN_URL . $backup_lock ) . '" name="' . esc_attr( $lock_status, 'instawp-connect' ) . '" onclick="instawp_set_backup_lock(\'' . $backup_id . '\', \'' . $lock_status . '\');" style="vertical-align:middle; cursor:pointer;"/>';
			}
		} else {
			$backup_lock = '/admin/partials/images/unlocked.png';
			$lock_status = 'unlock';
			$ret['html'] = '<img src="' . esc_url( INSTAWP_PLUGIN_URL . $backup_lock ) . '" name="' . esc_attr( $lock_status, 'instawp-connect' ) . '" onclick="instawp_set_backup_lock(\'' . $backup_id . '\', \'' . $lock_status . '\');" style="vertical-align:middle; cursor:pointer;"/>';
		}

		return $ret;
	}

	public static function get_has_remote_backuplist() {
		$backup_id_list = array();
		$list           = InstaWP_Setting::get_option( 'instawp_backup_list' );
		foreach ( $list as $k => $backup ) {
			if ( ! empty( $backup['remote'] ) ) {
				$backup_id_list[] = $k;
			}
		}

		return $backup_id_list;
	}
}