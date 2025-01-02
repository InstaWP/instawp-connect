<?php

namespace InstaWP\Connect\Helpers;

class Helper {

	public static function instawp_generate_api_key( $api_key, $jwt = '', $managed = true ) {
		return self::generate_api_key( $api_key, $jwt, $managed );
	}

	public static function generate_api_key( $api_key, $jwt = '', $managed = true ) {
		if ( empty( $api_key ) ) {
			error_log( 'instawp_generate_api_key empty api_key parameter' );

			return false;
		}

		$api_response = Curl::do_curl( 'check-key?jwt=' . $jwt, array(), array(), 'GET', 'v1', $api_key );

		if ( ! empty( $api_response['data']['status'] ) ) {
			$api_options = Option::get_option( 'instawp_api_options', array() );

			if ( is_array( $api_options ) && is_array( $api_response['data'] ) ) {
				Option::update_option( 'instawp_api_options', array_merge( $api_options, array(
					'api_key'  => $api_key,
					'jwt'      => $jwt,
					'response' => $api_response['data'],
				) ) );
			}
		} else {
			error_log( 'instawp_generate_api_key error, response from check-key api: ' . wp_json_encode( $api_response ) );

			return false;
		}

		$connect_body     = array(
			'url'            => self::wp_site_url(),
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => phpversion(),
			'plugin_version' => INSTAWP_PLUGIN_VERSION,
			'title'          => get_bloginfo( 'name' ),
			'icon'           => get_site_icon_url(),
			'username'       => base64_encode( self::get_admin_username() ),
			'plan_id'        => self::get_connect_plan_id(),
			'managed'        => $managed,
		);
		$connect_response = Curl::do_curl( 'connects', $connect_body, array(), 'POST', 'v1' );

		if ( ! empty( $connect_response['data']['status'] ) ) {
			$connect_id   = ! empty( $connect_response['data']['id'] ) ? intval( $connect_response['data']['id'] ) : '';
			$connect_uuid = isset( $connect_response['data']['uuid'] ) ? $connect_response['data']['uuid'] : '';

			if ( $connect_id && $connect_uuid ) {
				self::set_connect_id( $connect_id );
				self::set_connect_uuid( $connect_uuid );

				if ( empty( $jwt ) ) {
					self::generate_jwt( $connect_id );
				}

				do_action( 'instawp_connect_connected', $connect_id );
			} else {
				error_log( 'instawp_generate_api_key connect id not found in response.' );
				return false;
			}
		} else {
			error_log( 'generate_api_key error, response from connects api: ' . wp_json_encode( $connect_response ) );
			return false;
		}

		return true;
	}

	public static function generate_jwt( $connect_id = '' ) {
		$connect_id = ! empty( $connect_id ) ? $connect_id : self::get_connect_id();
		if ( empty( $connect_id ) ) {
			return false;
		}

		$response = Curl::do_curl( "connects/{$connect_id}/generate-token", array(), array(), 'GET' );
		if ( ! empty( $response['success'] ) ) {
			$jwt = ! empty( $response['data']['token'] ) ? $response['data']['token'] : '';
			
			if ( ! empty( $jwt ) ) {
				self::set_jwt( $jwt );
				return true;
			}
		}

		error_log( 'generate_jwt error, response from generate-token api: ' . wp_json_encode( $response ) );
		return false;
	}

	public static function get_random_string( $length = 6 ) {
		try {
			$length        = ( int ) round( ceil( absint( $length ) / 2 ) );
			$bytes         = function_exists( 'random_bytes' ) ? random_bytes( $length ) : openssl_random_pseudo_bytes( $length );
			$random_string = bin2hex( $bytes );
		} catch ( \Exception $e ) {
			$random_string = substr( hash( 'sha256', wp_generate_uuid4() ), 0, absint( $length ) );
		}

		return $random_string;
	}

	public static function get_args_option( $key = '', $args = [], $default = '' ) {
		$default = is_array( $default ) && empty( $default ) ? [] : $default;
		$value   = ! is_array( $default ) && ! is_bool( $default ) && empty( $default ) ? '' : $default;
		$key     = empty( $key ) ? '' : $key;

		if ( ! empty( $args[ $key ] ) ) {
			$value = $args[ $key ];
		}

		if ( isset( $args[ $key ] ) && is_bool( $default ) ) {
			$value = ! ( 0 == $args[ $key ] || '' == $args[ $key ] );
		}

		return $value;
	}

	public static function get_directory_info( $path ) {
		$bytes_total = 0;
		$files_total = 0;
		$path        = realpath( $path );

		try {
			if ( $path !== false && $path != '' && file_exists( $path ) ) {
				foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) ) as $object ) {
					try {
						$bytes_total += $object->getSize();
						++ $files_total;
					} catch ( \Exception $e ) {
						continue;
					}
				}
			}
		} catch ( \Exception $e ) {
		}

		return [
			'size'  => $bytes_total,
			'count' => $files_total
		];
	}

	public static function is_on_wordpress_org( $slug, $type ) {
		$api_url  = 'https://api.wordpress.org/' . ( $type === 'plugin' ? 'plugins' : 'themes' ) . '/info/1.2/';
		$response = wp_remote_get( add_query_arg( [
			'action'  => $type . '_information',
			'request' => [
				'slug' => $slug
			],
		], $api_url ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $data['name'] ) && ! empty( $data['slug'] ) && $data['slug'] === $slug ) {
			return true;
		}

		return false;
	}

	public static function clean_file( $directory ) {
		if ( file_exists( $directory ) && is_dir( $directory ) ) {
			if ( $handle = opendir( $directory ) ) {
				while ( false !== ( $file = readdir( $handle ) ) ) {
					if ( $file != "." && $file != ".." && strpos( $file, 'instawp' ) !== false ) {
						unlink( $directory . $file );
					}
				}
				closedir( $handle );
			}
		}
	}

	public static function get_admin_username() {
		$username = '';

		foreach (
			get_users( array(
				'role__in' => array( 'administrator' ),
				'fields'   => array( 'user_login' ),
			) ) as $admin
		) {
			if ( empty( $username ) && isset( $admin->user_login ) ) {
				$username = $admin->user_login;
				break;
			}
		}

		return $username;
	}

	public static function get_api_key( $return_hashed = false, $default_key = '' ) {
		$api_options = Option::get_option( 'instawp_api_options' );
		$api_key     = self::get_args_option( 'api_key', $api_options, $default_key );

		if ( ! $return_hashed ) {
			return $api_key;
		}

		if ( ! empty( $api_key ) && strpos( $api_key, '|' ) !== false ) {
			$exploded             = explode( '|', $api_key );
			$current_api_key_hash = hash( 'sha256', $exploded[1] );
		} else {
			$current_api_key_hash = ! empty( $api_key ) ? hash( 'sha256', $api_key ) : "";
		}

		return $current_api_key_hash;
	}

	public static function get_connect_id() {
		$api_options = Option::get_option( 'instawp_api_options' );

		return self::get_args_option( 'connect_id', $api_options );
	}

	public static function get_connect_uuid() {
		$api_options = Option::get_option( 'instawp_api_options' );

		return self::get_args_option( 'connect_uuid', $api_options );
	}

	public static function get_jwt() {
		$api_options = Option::get_option( 'instawp_api_options' );

		return self::get_args_option( 'jwt', $api_options );
	}

	public static function get_api_domain( $default_domain = '' ) {
		$api_options = Option::get_option( 'instawp_api_options' );

		if ( empty( $default_domain ) && defined( 'INSTAWP_API_DOMAIN_PROD' ) ) {
			$default_domain = INSTAWP_API_DOMAIN_PROD;
		}

		if ( empty( $default_domain ) ) {
			$default_domain = esc_url_raw( 'https://app.instawp.io' );
		}

		return self::get_args_option( 'api_url', $api_options, $default_domain );
	}

	public static function get_api_server_domain() {
		if ( defined( 'INSTAWP_API_SERVER_DOMAIN' ) ) {
			return INSTAWP_API_SERVER_DOMAIN;
		}

		$api_domain = self::get_api_domain();
		if ( strpos( $api_domain, 'stage' ) !== false ) {
			return 'https://stage-api.instawp.io';
		}

		return 'https://api.instawp.io';
	}

	public static function set_api_key( $api_key ) {
		$api_options            = Option::get_option( 'instawp_api_options' );
		$api_options['api_key'] = $api_key;

		return Option::update_option( 'instawp_api_options', $api_options );
	}

	public static function set_connect_id( $connect_id ) {
		$api_options               = Option::get_option( 'instawp_api_options' );
		$api_options['connect_id'] = intval( $connect_id );

		return Option::update_option( 'instawp_api_options', $api_options );
	}

	public static function set_connect_uuid( $connect_uuid ) {
		$api_options                 = Option::get_option( 'instawp_api_options' );
		$api_options['connect_uuid'] = $connect_uuid;

		return Option::update_option( 'instawp_api_options', $api_options );
	}

	public static function set_jwt( $jwt ) {
		$api_options        = Option::get_option( 'instawp_api_options' );
		$api_options['jwt'] = $jwt;

		return Option::update_option( 'instawp_api_options', $api_options );
	}

	public static function set_api_domain( $api_domain = '' ) {
		if ( empty( $api_domain ) ) {
			$api_domain = esc_url_raw( 'https://app.instawp.io' );
		}

		$api_options            = Option::get_option( 'instawp_api_options' );
		$api_options['api_url'] = $api_domain;

		return Option::update_option( 'instawp_api_options', $api_options );
	}

	public static function get_connect_plan_id() {
		$default_plan_id = defined( 'INSTAWP_CONNECT_PLAN_ID' ) ? INSTAWP_CONNECT_PLAN_ID : 1;
		$plan_id         = Option::get_option( 'instawp_connect_plan_id', $default_plan_id );

		return ! empty( $plan_id ) ? (int) $plan_id : $default_plan_id;
	}

	public static function wp_site_url( $path = '' ) {
		global $wpdb;

		$site_url = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'" );

		if ( empty( $site_url ) ) {
			return get_site_url( null, $path );
		}

		if ( $path && is_string( $path ) ) {
			$site_url .= '/' . ltrim( $path, '/' );
		}

		return $site_url;
	}
}