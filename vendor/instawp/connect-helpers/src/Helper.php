<?php

namespace InstaWP\Connect\Helpers;

class Helper {

	public static function instawp_generate_api_key( $api_key, $jwt = '', $config = array() ) {
		return self::generate_api_key( $api_key, $jwt, $config );
	}

	/**
	 * Get Migration Engine
	 *
	 * Hits client-app's GET /api/v2/migrate-v4/engine and returns which migration engine is active
	 * ('v3' | 'v4') so callers branch their flow before deciding which migration API to hit.
	 *
	 * @param string $api_key api key
	 * @param string $migration_mode optional flow the engine is being resolved for (e.g. 'e2e' | 'push')
	 *
	 * @return array
	 */
	public static function getMigrationEngine( $api_key, $migration_mode = '' ) {
		if ( empty( $api_key ) ) {
			return self::sendResponse( false, 'API key is required.' );
		}

		// Forward the flow as a query param so client-app knows which flow asked (logged there).
		$endpoint = ! empty( $migration_mode )
			? add_query_arg( 'migration_mode', $migration_mode, 'migrate-v4/engine' )
			: 'migrate-v4/engine';

		$response = Curl::do_curl( $endpoint, array(), array(), 'GET', 'v2', $api_key );

		if ( empty( $response['success'] ) || empty( $response['data']['engine'] ) ) {
			return self::sendResponse( false, empty( $response['message'] ) ? 'Something went wrong.' : esc_html( $response['message'] ) );
		}

		if ( ! in_array( $response['data']['engine'], array('v3','v4') ) ) {
			return self::sendResponse( false, 'Wrong migration engine ' . $response['data']['engine'] );
		}

		return self::sendResponse(
			true,
			'',
			array( 'engine' => $response['data']['engine'] )
		);
		
	}

	/**
	 * Insta Migrate Request
	 *
	 * @param string $api_key api key
	 * @param string $wlm_slug white label migration slug
	 *
	 * @return array
	 *
	 */
	public static function instaMigrateRequest( $api_key, $wlm_slug, $locale = '' ) {
		if ( empty( $api_key ) ) {
			return self::sendResponse( false, 'API key is required.' );
		}

		if ( empty( $wlm_slug )  ) {
			return self::sendResponse( false, 'White label migration slug is required.' );
		}

		$locale = empty( $locale ) || ! is_string( $locale ) ?  get_locale(): $locale;

		$locale = sanitize_key( $locale );

		$wlm_slug = sanitize_key( $wlm_slug );

		// e2e flow — tell client-app which flow is resolving the engine.
		$engine = self::getMigrationEngine( $api_key, 'e2e' );

		if ( ! $engine['success'] ) {
			return $engine;
		}

		$engine = $engine['data']['engine'];
		
		return $engine === 'v3' ? self::v3MigrationRequest($api_key, $wlm_slug, $locale) : self::v4MigrationRequest($api_key, $wlm_slug, $locale);
	}

	/**
	 * Insta Migrate V4 Request. i.e. based on InstaMigrate plugin + AI agent
	 *
	 * @param string $api_key api key
	 * @param string $wlm_slug white label migration slug
	 * @param string $locale locale
	 *
	 * @return array
	 *
	 */
	private static function v4MigrationRequest($api_key, $wlm_slug, $locale = ''){
		$install = self::installInstaMigrate();

		if ( ! $install['success'] ) {
			return $install;
		}
		
		$insta_mig_key = self::getInstaMigrateApiKey();

		if ( ! $insta_mig_key['success'] ) {
			return $insta_mig_key;
		}

		$param = array(
			'destination_url'   => self::wp_site_url(),
			'wp_version'  		=> get_bloginfo( 'version' ),
			'php_version' 		=> phpversion(),
			'title'       		=> get_bloginfo( 'name' ),
			'plugin_api_key' 	=> $insta_mig_key['data']['insta_mig_key'],
			'locale'			=> $locale,
		);

		$mig_request = Curl::do_curl( 'migrate-v4/' . $wlm_slug . '/e2e-mig', $param, array(), 'POST', 'v2', $api_key );

		if ( ! empty( $mig_request['success'] ) && ! empty( $mig_request['data']['migration_url'] ) ) {
			return self::sendResponse( 
				true, 
				'Migration requested with destination site details. Please visit given url and connect source site to continue migrate site.',
				array(
					'migration_url' => $mig_request['data']['migration_url']
				)
			);
		}

		return self::sendResponse( false, empty( $mig_request['message'] ) ? 'Something went wrong.': esc_html( $mig_request['message'] ) );
	}

	/**
	 * Insta Migrate V3 Request. i.e. based on InstaWP Connect plugin
	 *
	 * @param string $api_key api key
	 * @param string $wlm_slug white label migration slug
	 * @param string $locale locale
	 *
	 * @return array
	 *
	 */
	private static function v3MigrationRequest($api_key, $wlm_slug, $locale = ''){
		$install = self::installInstaWPConnect();

		if ( ! $install['success'] ) {
			return $install;
		}
		
		$generate_api_key = self::generate_api_key( 
			$api_key, 
			'',  
			array(
				'e2e_mig_push_request' => true,
				'wlm_slug'             => $wlm_slug,
				'managed'              => false,
				'locale'			   => $locale,
			)
		);

		if ( ! $generate_api_key ) {
			delete_option( 'instawp_api_options' );
			return self::sendResponse( false, 'Failed to connect site.' );
		}

		return self::sendResponse( 
			true, 
			'Migration requested with destination site details. Please visit given url and connect source site to continue migrate site.',
			array(
				'migration_url' => Helper::get_migration_url(),
			)
		);
	}

	/**
	 * Send Response
	 */
	public static function sendResponse( $success = true, $message = '', $data = array() ) {
		return array(
			'success' 	=> $success,
			'message' 	=> $message,
			'data'		=> $data
		);
	}

	/**
	 * Install instamigrate plugin
	 */
	public static function installInstaMigrate( $retry = false ) {
		try {
	
			if ( class_exists( '\InstaMigrate' ) ) {
				return self::sendResponse();
			}

			if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_mu_plugins' ) ) {
				if ( file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
			}

			if ( ! function_exists( 'is_plugin_active' ) ) {
				return self::sendResponse( false, 'Plugin methods not loaded. Failed to install the InstaMigrate plugin.' );
			}

			// Check if plugin is active
			if ( ! is_plugin_active( 'instamigrate/insta-migrate.php' ) ) {
				$params    = array(
					array(
						'slug'     => 'instamigrate',
						'type'     => 'plugin',
						'activate' => true,
					),
				);
				// Install and active plugin
				$installer = new Installer( $params );
				$response  = $installer->start();

				if ( $response[0]['success'] ) {
					if ( class_exists( '\InstaMigrate' ) && defined('INSTA_MIGRATE_OPTION_KEY') ) {
						return self::sendResponse();
					} else {
						return self::sendResponse( false, 'After install INSTA_MIGRATE_OPTION_KEY not defined.' );
					}
				} else {
					if ( ! $retry ) {
						return self::installInstaMigrate( true );
					}
					$message = $response[0]['message'] ? $response[0]['message'] : 'Failed to install or activate the InstaMigrate plugin.';
					return self::sendResponse( false, $message );
				}
			}

			return self::sendResponse();
		} catch (\Throwable $th) {
			return self::sendResponse( false, $th->getMessage() );
		}
	}

	/**
	 * Install instamigrate plugin
	 */
	public static function installInstaWPConnect( $retry = false ) {
		try {
	
			if ( class_exists( '\instaWP' ) ) {
				return self::sendResponse();
			}

			if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_mu_plugins' ) ) {
				if ( file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
			}

			if ( ! function_exists( 'is_plugin_active' ) ) {
				return self::sendResponse( false, 'Plugin methods not loaded. Failed to install the InstaMigrate plugin.' );
			}

			// Check if plugin is active
			if ( ! is_plugin_active( 'instawp-connect/instawp-connect.php' ) ) {
				$params    = array(
					array(
						'slug'     => 'instawp-connect',
						'type'     => 'plugin',
						'activate' => true,
					),
				);
				// Install and active plugin
				$installer = new Installer( $params );
				$response  = $installer->start();

				if ( $response[0]['success'] ) {
					if ( class_exists( '\instaWP' ) && defined('INSTAWP_PLUGIN_VERSION') ) {
						return self::sendResponse();
					} else {
						return self::sendResponse( false, 'After install INSTAWP_PLUGIN_VERSION not defined.' );
					}
				} else {
					if ( ! $retry ) {
						return self::installInstaWPConnect( true );
					}
					$message = $response[0]['message'] ? $response[0]['message'] : 'Failed to install or activate the InstaWP Connect plugin.';
					return self::sendResponse( false, $message );
				}
			}

			return self::sendResponse();
		} catch (\Throwable $th) {
			return self::sendResponse( false, $th->getMessage() );
		}
	}

	// Get insta migrate plugin api key
	public static function getInstaMigrateApiKey() {
		// Added a leading \ to force global namespace resolution on both the guard
        if ( ! class_exists('\InstaMigrate') || ! defined('INSTA_MIGRATE_OPTION_KEY') ) {
			return self::sendResponse( false, 'InstaMigrate plugin is not installed or activated' );
        }

		$plugin = \InstaMigrate::instance();
        $plugin->ensure_api_key(); // idempotent: generates only if missing

        $insta_mig_key = is_multisite()
            ? get_site_option(INSTA_MIGRATE_OPTION_KEY)
            : get_option(INSTA_MIGRATE_OPTION_KEY);

		if ( empty( $insta_mig_key ) ) {
			return self::sendResponse( false, 'Failed to generate plugin API key.' );
		}
		return self::sendResponse( true, '', [
			'insta_mig_key' => $insta_mig_key
		] );
    }

	/**
	 * Get InstaWP User Agent
	 *
	 * @param null|array|string $agentIdentifier
	 * @return string
	 */
	public static function getInstaWPUserAgent( $agentIdentifier = null ) {
		$userAgentItems = array(
			'InstaWP/1.0 (https://instawp.com; support@instawp.com)',
		);

		if ( ! empty( $agentIdentifier ) ) {
			if ( is_array( $agentIdentifier ) ) {
				$userAgentItems = array_merge( $userAgentItems, $agentIdentifier );
			} else {
				$userAgentItems[] = $agentIdentifier;
			}
		}

		return implode( ' ', $userAgentItems );
	}

	/**
	 * Field names whose VALUE must never be written to the error log.
	 *
	 * add_error_log() persists to an option that the plugin's own debug-info AJAX endpoint returns
	 * verbatim — the payload customers paste into support tickets. Curl::do_curl() logs the whole
	 * request body on any 4xx/5xx, and a 4xx is ROUTINE here (plan and quota rejections are normal),
	 * so any credential travelling in a body lands there by default.
	 *
	 * Matched on a substring, so `plugin_api_key`, `insta_mig_key` and `wp_app_password` are covered
	 * without maintaining an exact list. `salt` and `signature` matter more than they look:
	 * migrate_settings.wp_config_constants carries EVERY define() from wp-config.php, which means
	 * AUTH_SALT / SECURE_AUTH_SALT / LOGGED_IN_SALT / NONCE_SALT — and api_signature is sent on the
	 * V3 serve endpoint.
	 */
	const REDACTED_LOG_KEYS = array(
		'password',
		'pwd',
		'api_key',
		'apikey',
		'secret',
		'token',
		'jwt',
		'_key',
		'salt',
		'signature',
		'credential',
		// Matched by `_key` too, but named explicitly: it is a credential, not the diagnostic its
		// name suggests, and that is worth stating where the list is read rather than inferred.
		'migrate_key',
		// Catches `auth`, `authorization` and `oauth_*`. Known, accepted collision: a field named
		// `author` is also redacted. Losing an author name from an error log is a trivial cost
		// against leaking an authorization value, which is the trade being made deliberately.
		'auth',
	);

	/**
	 * Strip credential values immediately before they are written to the log.
	 *
	 * Deliberately NOT folded into sanitize_data(): that is a shared, general-purpose sanitiser used
	 * by callers that intend to KEEP what it returns, and silently dropping fields there would
	 * corrupt their data. Redaction belongs at the sink, not in the sanitiser.
	 *
	 * @param array $data payload about to be logged.
	 *
	 * @return array
	 */
	private static function redact_for_log( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		foreach ( $data as $key => $value ) {
			if ( self::is_redacted_log_key( $key ) ) {
				$data[ $key ] = '';
				continue;
			}

			if ( is_array( $value ) ) {
				$data[ $key ] = self::redact_for_log( $value );
			}
		}

		return $data;
	}

	/**
	 * Hard ceilings for the stored error log, so a single bad entry (e.g. a
	 * failed API call whose request/response body is unusually large) can
	 * never grow `iwp_connect_helper_error_log` to a size later code can't
	 * process without exhausting memory.
	 */
	const ERROR_LOG_MAX_ENTRIES     = 150;
	const ERROR_LOG_KEEP_ENTRIES    = 100;
	const ERROR_LOG_MAX_ENTRY_BYTES = 20000; // ~20KB per stored entry.
	const ERROR_LOG_MAX_TOTAL_BYTES = 1000000; // ~1MB for the whole option.

	/**
	 * Above this stored size the option is discarded outright rather than trimmed:
	 * trimming means unserializing it first, and the whole point of the guard is to
	 * never load a blob big enough to exhaust memory. Sites that ran a build without
	 * the caps above have been seen at 26MB.
	 */
	const ERROR_LOG_RESET_BYTES = 4000000; // ~4MB.

	/**
	 * Recursion bounds sanitize_data() applies WHEN A CALLER ASKS FOR THEM. The log sink
	 * does; nobody else does. See the note on sanitize_data() for why they are not on by
	 * default.
	 */
	const SANITIZE_MAX_DEPTH    = 10;
	const SANITIZE_MAX_ELEMENTS = 500;

	/**
	 * Add error log
	 *
	 * @param array|string $payload
	 * @param Throwable    $th
	 *
	 * @return void
	 */
	public static function add_error_log( $payload, $th = null ) {
		$log_name = 'iwp_connect_helper_error_log';
		$log      = self::get_error_log();

		if ( self::ERROR_LOG_MAX_ENTRIES < count( $log ) ) {
			// Remove oldest entries, keep the most recent ones.
			$log = array_slice( $log, -1 * self::ERROR_LOG_KEEP_ENTRIES );
		}

		$error         = is_array( $payload ) ? self::redact_for_log( self::sanitize_data( $payload, self::SANITIZE_MAX_ELEMENTS ) ) : array(
			/*
			 * A STRING payload is NOT redacted — by design, and worth stating because the comment
			 * that used to sit here said the opposite (it described a text scrubber that has since
			 * been deleted). Redaction is key-based: there are no keys in a bare string to match.
			 *
			 * So a caller that interpolates a credential into a message — `Authorization: Bearer …`,
			 * `?api_key=…` — logs it verbatim. If that matters for a given call site, pass an ARRAY
			 * with the credential under its own key and it will be blanked.
			 */
			'message' => sanitize_text_field( $payload ),
		);
		$error['time'] = date( 'Y-m-d H:i:s' );

		if ( ! empty( $th ) ) {
			$error = array_merge(
				$error,
				array(
					'error' => $th->getMessage(),
					'line'  => $th->getLine(),
					'file'  => $th->getFile(),
				)
			);
		}

		$log[] = self::truncate_log_entry( $error );
		$log   = self::enforce_log_byte_cap( $log );

		self::set_settings( $log, $log_name );
	}

	/**
	 * Get error log
	 *
	 * Defensive against a stored option that already grew unbounded (e.g. on
	 * a site upgrading from before the caps above existed): rather than hand
	 * a multi-MB blob to a caller that will walk or serialize it, self-heal
	 * by trimming to the most recent entries and persisting the trim.
	 *
	 * @return array
	 */
	public static function get_error_log() {
		global $wpdb;

		$log_name = 'iwp_connect_helper_error_log';

		/*
		 * Measure it in the DATABASE before pulling it into PHP. The option is not
		 * autoloaded, so on an affected site the only thing that can exhaust memory
		 * here is us unserializing it -- which a size check on the loaded value has
		 * already done by the time it runs.
		 */
		$stored_bytes = $wpdb->get_var(
			$wpdb->prepare( "SELECT LENGTH( option_value ) FROM {$wpdb->options} WHERE option_name = %s", $log_name ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);

		if ( null !== $stored_bytes && self::ERROR_LOG_RESET_BYTES < (int) $stored_bytes ) {
			/*
			 * delete_option(), NOT set_settings( array() ). update_option() reads
			 * $old_value = get_option( $option ) before it compares, so writing an empty
			 * array here would unserialize the very blob this branch exists to avoid
			 * loading -- at ~5x expansion, a 26MB row is well over 100MB of PHP arrays.
			 * delete_option() reads only the `autoload` column.
			 */
			Option::delete_option( $log_name );

			/*
			 * Leave a marker rather than nothing. This accessor is reachable from the
			 * plugin's debug-info endpoint, so a support engineer can be the one who
			 * triggers the discard, and an empty log is indistinguishable from a healthy
			 * one. The option is already gone, so this write has nothing left to load.
			 */
			$marker = array(
				array(
					'message' => 'Error log discarded: it had reached ' . (int) $stored_bytes . ' bytes, past the ' . self::ERROR_LOG_RESET_BYTES . '-byte ceiling.',
					'time'    => date( 'Y-m-d H:i:s' ),
				),
			);
			self::set_settings( $marker, $log_name );

			return $marker;
		}

		$log = self::get_options( array(), $log_name );
		$log = ( empty( $log ) || ! is_array( $log ) ) ? array() : $log;

		if ( self::ERROR_LOG_MAX_TOTAL_BYTES < self::log_size( $log ) ) {
			$log = self::enforce_log_byte_cap( array_slice( $log, -1 * self::ERROR_LOG_KEEP_ENTRIES ) );
			self::set_settings( $log, $log_name );
		}

		return $log;
	}

	/**
	 * Byte size of a value as the option store will hold it.
	 *
	 * serialize(), NOT wp_json_encode(), for two reasons. It is the unit the option is
	 * actually stored in, so this agrees with the LENGTH( option_value ) ceiling in
	 * get_error_log() -- a JSON measure disagrees with it by a wide margin (serialized
	 * PHP arrays carry far more overhead), leaving a log that the DB-side tier thinks
	 * is oversized and the in-PHP tier thinks is fine. And wp_json_encode() returns
	 * false on a payload it cannot encode (nesting past its 512 depth limit, a
	 * recursive reference), where strlen( false ) is 0 and the ceiling silently passes.
	 *
	 * @param mixed $data
	 *
	 * @return int
	 */
	private static function log_size( $data ) {
		return strlen( is_string( $data ) ? $data : maybe_serialize( $data ) );
	}

	/**
	 * Cut a string to at most $max_bytes without splitting a UTF-8 character.
	 *
	 * A bare substr() can leave an invalid trailing byte sequence, which then breaks
	 * json_encode() for every consumer of the log downstream (the debug-info endpoint
	 * hands this option back to the customer).
	 *
	 * @param string $value
	 * @param int    $max_bytes
	 *
	 * @return string
	 */
	private static function cut_bytes( $value, $max_bytes ) {
		$cut = substr( $value, 0, $max_bytes );

		/*
		 * Only repair what WE broke. Running the loop on a value that was ALREADY invalid
		 * UTF-8 before the cut shortens it without making it valid, so check the input
		 * first. A character spans at most 4 bytes, so at most 3 trailing bytes can be a
		 * partial one; preg_match( '//u' ) returning false is the validity test.
		 */
		if ( ! preg_match( '//u', $value ) ) {
			return $cut;
		}

		for ( $i = 0; $i < 3 && '' !== $cut && ! preg_match( '//u', $cut ); $i ++ ) {
			$cut = substr( $cut, 0, -1 );
		}

		return $cut;
	}

	/**
	 * Truncate a single log entry so one oversized value (e.g. a large API
	 * request/response body echoed back on failure) can't dominate the log.
	 *
	 * @param array $entry
	 *
	 * @return array
	 */
	private static function truncate_log_entry( $entry ) {
		if ( ! is_array( $entry ) || self::ERROR_LOG_MAX_ENTRY_BYTES >= self::log_size( $entry ) ) {
			return $entry;
		}

		$max_field_bytes = (int) ( self::ERROR_LOG_MAX_ENTRY_BYTES / 4 );

		foreach ( $entry as $key => $value ) {
			if ( $max_field_bytes >= self::log_size( $value ) ) {
				continue;
			}

			$encoded = is_string( $value ) ? $value : wp_json_encode( $value );
			$encoded = is_string( $encoded ) ? $encoded : maybe_serialize( $value );

			$entry[ $key ] = self::cut_bytes( $encoded, $max_field_bytes ) . '... [truncated, encoded form was ' . strlen( $encoded ) . ' bytes]';
		}

		/*
		 * The per-field pass alone does NOT enforce the entry ceiling: many fields each
		 * under the field budget still add up past it, and an entry above the TOTAL
		 * ceiling is one enforce_log_byte_cap() can never evict. So drop the largest
		 * fields, biggest first, sized ONCE -- re-measuring the whole entry per pass is
		 * quadratic on an error path Curl::do_curl() retries ten times.
		 *
		 * Best-effort by construction, and deliberately not claimed otherwise: this
		 * rewrites VALUES and never KEYS, and for a very short value the marker is longer
		 * than what it replaces. The collapse below, not this loop, is what makes the
		 * ceiling hold.
		 */
		if ( self::ERROR_LOG_MAX_ENTRY_BYTES < self::log_size( $entry ) ) {
			$sizes = array();
			foreach ( $entry as $key => $value ) {
				$sizes[ $key ] = self::log_size( $value );
			}

			arsort( $sizes );

			$total = self::log_size( $entry );

			foreach ( $sizes as $key => $size ) {
				if ( self::ERROR_LOG_MAX_ENTRY_BYTES >= $total ) {
					break;
				}

				$marker        = '[dropped, ' . $size . ' bytes]';
				$total         = $total - $size + strlen( $marker );
				$entry[ $key ] = $marker;
			}
		}

		if ( self::ERROR_LOG_MAX_ENTRY_BYTES < self::log_size( $entry ) ) {
			// Nothing above sheds KEY bytes, so an entry carrying very many long keys
			// survives all of it. Replace the whole entry rather than store one the
			// total-byte cap can never evict.
			$entry = array(
				'message' => '[entry discarded: ' . count( $entry ) . ' fields, ' . self::log_size( $entry ) . ' bytes, past the ' . self::ERROR_LOG_MAX_ENTRY_BYTES . '-byte ceiling]',
				'time'    => date( 'Y-m-d H:i:s' ),
			);
		}

		return $entry;
	}

	/**
	 * Drop the oldest entries (FIFO) until the whole log fits under the
	 * total-byte ceiling, regardless of entry count.
	 *
	 * @param array $log
	 *
	 * @return array
	 */
	private static function enforce_log_byte_cap( $log ) {
		if ( self::ERROR_LOG_MAX_TOTAL_BYTES >= self::log_size( $log ) ) {
			return $log;
		}

		// Size each entry ONCE. Re-serialising the whole log per shift is quadratic, and
		// the path that reaches here with a real backlog is the self-heal one, where the
		// log is already megabytes.
		$sizes = array();
		foreach ( $log as $entry ) {
			$sizes[] = self::log_size( $entry );
		}

		$total = array_sum( $sizes );
		while ( 1 < count( $log ) && self::ERROR_LOG_MAX_TOTAL_BYTES < $total ) {
			array_shift( $log );
			$total -= array_shift( $sizes );
		}

		// The per-entry sum omits the array envelope, so settle it exactly.
		while ( 1 < count( $log ) && self::ERROR_LOG_MAX_TOTAL_BYTES < self::log_size( $log ) ) {
			array_shift( $log );
		}

		return $log;
	}

	/**
	 * Sanitize data
	 *
	 * Recursion bounds are OPT-IN and default to OFF, so this call is unchanged for every
	 * existing caller. That is deliberate: this is a shared, general-purpose sanitiser and
	 * at least one caller KEEPS what it returns -- instawp-connect's get_set_sync_config_data()
	 * writes it straight back into an option -- so silently replacing elements here would
	 * corrupt stored data rather than trim a log line. Same reason redact_for_log() is not
	 * folded in. add_error_log() is a sink and asks for the bounds; nobody else gets them.
	 *
	 * @param array|string $data         data
	 * @param int          $max_elements per-array element ceiling, 0 (default) for unbounded
	 * @param int          $depth        internal recursion depth
	 *
	 * @return array|string sanitized data
	 */
	public static function sanitize_data( $data, $max_elements = 0, $depth = 0 ) {
		if ( empty( $data ) ) {
			return $data;
		}

		if ( 0 < $max_elements && self::SANITIZE_MAX_DEPTH < $depth ) {
			return is_scalar( $data ) ? sanitize_text_field( (string) $data ) : '[max depth exceeded]';
		}

		if ( is_array( $data ) ) {
			if ( 0 < $max_elements && $max_elements < count( $data ) ) {
				/*
				 * SLICE, don't mark in place. Replacing each surplus value while keeping
				 * its key bounds the WORK and not the MEMORY -- a 155,110-element array
				 * still costs 155,110 hashtable slots -- and memory is the point here.
				 */
				$dropped                    = count( $data ) - $max_elements;
				$data                       = array_slice( $data, 0, $max_elements, true );
				// Namespaced because it can still shadow a payload key of the same name;
				// on the log path that costs a truncated value we were dropping anyway.
				$data['__instawp_truncated'] = '[truncated: ' . $dropped . ' more elements]';
			}

			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$data[ $key ] = self::sanitize_data( $value, $max_elements, $depth + 1 );
				} else {
					$data[ $key ] = sanitize_text_field( $value );
				}
			}
		} elseif ( is_string( $data ) ) {
			$data = sanitize_text_field( $data );
		} else {
			$data = '';
		}
		return $data;
	}

	/**
	 * Names that look like a needle but are diagnostics, and must survive.
	 *
	 * `_key` matches `meta_key`, which the sync code logs as the entire point of its failure line
	 * ("which meta key failed?"), and `auth` matches `author` / `post_author`. Redacting those
	 * removes the information the log exists to carry, which is a different way of destroying it
	 * than blanking the message.
	 */
	const NEVER_REDACTED_LOG_KEYS = array( 'meta_key', 'author', 'post_author' );

	/**
	 * Does this array key name a credential that must not be logged?
	 *
	 * @param mixed $key Array key from the payload being logged.
	 *
	 * @return bool
	 */
	private static function is_redacted_log_key( $key ) {
		if ( ! is_string( $key ) ) {
			return false;
		}

		$key = strtolower( $key );

		// Diagnostics that the `_key` / `auth` substrings would otherwise swallow.
		if ( in_array( $key, self::NEVER_REDACTED_LOG_KEYS, true ) ) {
			return false;
		}

		foreach ( self::REDACTED_LOG_KEYS as $needle ) {
			if ( false !== strpos( $key, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	public static function generate_api_key( $api_key, $jwt = '', $config = array() ) {
		try {
			if ( empty( $api_key ) ) {
				self::add_error_log( 'instawp_generate_api_key empty api_key parameter' );
				return false;
			}

			$api_options = self::get_options();
			$api_options = is_array( $api_options ) ? $api_options : array();

			$api_response = Curl::do_curl( 'check-key?jwt=' . $jwt, array(), array(), 'GET', 'v1', $api_key );

			if ( ! empty( $api_response['data']['status'] ) ) {
				$api_options = array_merge(
					$api_options,
					array(
						'api_key'  => $api_key,
						'jwt'      => $jwt,
						'origin'   => md5( self::wp_site_url( '', true ) ),
						'response' => $api_response['data'],
					)
				);
				self::set_settings(
					$api_options
				);
			} else {
				self::add_error_log(
					array(
						'message'  => 'instawp_generate_api_key error, response from check-key api',
						'response' => $api_response,
						'config'   => $config,
					)
				);
				return false;
			}

			if ( is_array( $config ) && ! empty( $config['without_connect'] ) ) {
				return true;
			}

			$connect_body = self::get_connect_config( $config );

			if ( is_array( $config ) ) {

				if ( ! empty( $config['e2e_mig_wo_connects'] ) && ! empty( $config['group_uuid'] ) ) {
					self::set_mig_gid( $config['group_uuid'] );
					return $connect_body;
				}

				/**
				 * Migrate White Label
				 *
				 * @param bool e2e_mig_push_request is the end to end migration push request
				 * @param string wlm_slug is the white label migration slug of the migration
				 */
				if ( ! empty( $config['e2e_mig_push_request'] ) || ! empty( $config['wlm_slug'] ) ) {
					$mig_request = Curl::do_curl( 'migrates-v3/' . $config['wlm_slug'] . '/e2e-push-request', $connect_body, array(), 'POST', 'v2' );

					if ( empty( $mig_request['success'] ) ) {
						return false;
					}

					if ( ! empty( $mig_request['data']['migration_url'] ) ) {
						self::set_migration_url( $mig_request['data']['migration_url'] );
					}
					
					if ( ! empty( $mig_request['data']['group_uuid'] ) ) {
						self::set_mig_gid( $mig_request['data']['group_uuid'] );
					}
						
					return true;
				}
			}

			$connect_response = Curl::do_curl( 'connects', $connect_body, array(), 'POST', 'v1' );

			if ( ! empty( $connect_response['data']['status'] ) ) {
				$connect_id   = ! empty( $connect_response['data']['id'] ) ? intval( $connect_response['data']['id'] ) : '';
				$connect_uuid = isset( $connect_response['data']['uuid'] ) ? $connect_response['data']['uuid'] : '';

				if ( $connect_id && $connect_uuid ) {
					$api_options['connect_id']   = intval( $connect_id );
					$api_options['connect_uuid'] = sanitize_text_field( $connect_uuid );
					if ( ! empty( $plan_id ) ) {
						$plan_id = intval( $plan_id );
						$key     = "plan_{$plan_id}_timestamp";
						if ( ! isset( $api_options[ $key ] ) ) {
							$api_options[ $key ] = current_time( 'mysql' );
						}
						$api_options['plan_id'] = $plan_id;
					}

					// Update team_name as we get it from the response
					if ( ! empty( $connect_response['data']['team_name'] ) && ! empty( $api_options['response'] ) && is_array( $api_options['response'] ) && ! empty( $api_options['response']['team_name'] ) ) {
						$api_options['response']['team_name'] = sanitize_text_field( $connect_response['data']['team_name'] );
					}

					self::set_settings( $api_options );

					if ( empty( $jwt ) ) {
						self::generate_jwt( $connect_id );
					}

					if ( ! empty( $connect_response['data']['is_staging_site'] ) && true == $connect_response['data']['is_staging_site'] ) {
						self::set_settings( true, 'instawp_is_staging' );
					}

					do_action( 'instawp_connect_connected', $connect_id );
				} else {
					self::add_error_log(
						array(
							'message'  => 'generate_api_key error, connect id not found in response ',
							'response' => $connect_response,
							'config'   => $config,
						)
					);
					return false;
				}
			} else {
				self::add_error_log(
					array(
						'message'  => 'generate_api_key error, response from connects api: ',
						'response' => $connect_response,
						'config'   => $config,
					)
				);

				return false;
			}

			return true;
		} catch ( \Throwable $th ) {
			self::add_error_log(
				array(
					'message' => 'generate_api_key error, exception: ',
					'config'  => $config,
				),
				$th
			);

			return false;
		}
	}

	/**
	 * Get Connect Config
	 *
	 * @param array $config
	 * @return array
	 */
	public static function get_connect_config( $config = array() ) {
		$connect_body = array(
			'url'         => self::wp_site_url(),
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => phpversion(),
			'title'       => get_bloginfo( 'name' ),
			'icon'        => get_site_icon_url(),
			'username'    => base64_encode( self::get_admin_username() ),
			'managed'     => is_bool( $config ) ? $config : true,
		);

		if ( defined( 'INSTAWP_PLUGIN_VERSION' ) ) {
			$connect_body['plugin_version'] = INSTAWP_PLUGIN_VERSION;
		}

		if ( is_array( $config ) ) {
			$connect_body = array_merge( $connect_body, $config );
		}

		return $connect_body;
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

		self::add_error_log(
			array(
				'message'    => 'generate_jwt error, response from generate-token api',
				'response'   => $response,
				'connect_id' => $connect_id,
			)
		);
		return false;
	}

	public static function get_random_string( $length = 6 ) {
		try {
			$length        = (int) round( ceil( absint( $length ) / 2 ) );
			$bytes         = function_exists( 'random_bytes' ) ? random_bytes( $length ) : openssl_random_pseudo_bytes( $length );
			$random_string = bin2hex( $bytes );
		} catch ( \Exception $e ) {
			$random_string = substr( hash( 'sha256', wp_generate_uuid4() ), 0, absint( $length ) );
		}

		return $random_string;
	}

	public static function get_args_option( $key = '', $args = array(), $default = '' ) {
		$default = is_array( $default ) && empty( $default ) ? array() : $default;
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
						++$files_total;
					} catch ( \Exception $e ) {
						continue;
					}
				}
			}
		} catch ( \Exception $e ) {
		}

		return array(
			'size'  => $bytes_total,
			'count' => $files_total,
		);
	}

	public static function is_on_wordpress_org( $slug, $type ) {
		$api_url  = 'https://api.wordpress.org/' . ( $type === 'plugin' ? 'plugins' : 'themes' ) . '/info/1.2/';
		$response = wp_remote_get(
			add_query_arg(
				array(
					'action'  => $type . '_information',
					'request' => array(
						'slug' => $slug,
					),
				),
				$api_url
			)
		);

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
					if ( $file != '.' && $file != '..' && strpos( $file, 'instawp' ) !== false ) {
						unlink( $directory . $file );
					}
				}
				closedir( $handle );
			}
		}
	}

	public static function get_admin_username() {
		if ( current_user_can( 'manage_options' ) ) {
			$current_user = wp_get_current_user();

			if ( ! empty( $current_user ) ) {
				return $current_user->user_login;
			}
		}

		$username = '';

		foreach (
			get_users(
				array(
					'role__in' => array( 'administrator' ),
					'fields'   => array( 'user_login' ),
				)
			) as $admin
		) {
			if ( empty( $username ) && isset( $admin->user_login ) ) {
				$username = $admin->user_login;
				break;
			}
		}

		return $username;
	}

	public static function get_options( $default = array(), $option_name = 'instawp_api_options' ) {
		return Option::get_option( $option_name, $default );
	}

	public static function get_api_key( $return_hashed = false, $default_key = '' ) {
		$api_options = self::get_options();
		$api_key     = self::get_args_option( 'api_key', $api_options, $default_key );

		if ( ! $return_hashed ) {
			return $api_key;
		}

		if ( ! empty( $api_key ) && strpos( $api_key, '|' ) !== false ) {
			$exploded             = explode( '|', $api_key );
			$current_api_key_hash = hash( 'sha256', $exploded[1] );
		} else {
			$current_api_key_hash = ! empty( $api_key ) ? hash( 'sha256', $api_key ) : '';
		}

		return $current_api_key_hash;
	}

	public static function get_connect_id() {
		$api_options = self::get_options();

		return self::get_args_option( 'connect_id', $api_options );
	}

	public static function get_connect_uuid() {
		$api_options = self::get_options();

		return self::get_args_option( 'connect_uuid', $api_options );
	}

	public static function get_connect_origin() {
		$api_options = self::get_options();

		return self::get_args_option( 'origin', $api_options );
	}

	public static function get_jwt() {
		$api_options = self::get_options();

		return self::get_args_option( 'jwt', $api_options );
	}

	public static function get_response() {
		$api_options = self::get_options();

		return self::get_args_option( 'response', $api_options, array() );
	}

	public static function get_api_domain( $default_domain = '' ) {
		$api_options = self::get_options();

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

	public static function set_settings( $settings, $option_name = 'instawp_api_options' ) {
		return Option::update_option( $option_name, $settings );
	}

	public static function set_api_key( $api_key ) {
		$api_options            = self::get_options();
		$api_options['api_key'] = $api_key;

		return self::set_settings( $api_options );
	}

	public static function set_connect_id( $connect_id ) {
		$api_options               = self::get_options();
		$api_options['connect_id'] = intval( $connect_id );

		return self::set_settings( $api_options );
	}

	public static function set_connect_uuid( $connect_uuid ) {
		$api_options                 = self::get_options();
		$api_options['connect_uuid'] = $connect_uuid;

		return self::set_settings( $api_options );
	}

	
	/**
	 * Set migration url
	 */
	public static function set_migration_url( $url ) {
		$api_options               = self::get_options();
		$api_options['migration_url'] = $url;
		return self::set_settings( $api_options );
	}

	/**
	 * Set migration group id
	 */
	public static function set_mig_gid( $group_uuid ) {
		$api_options               = self::get_options();
		$api_options['group_uuid'] = $group_uuid;

		return self::set_settings( $api_options );
	}

	/**
	 * Get migration url
	 */
	public static function get_migration_url() {
		$api_options = self::get_options();

		return self::get_args_option( 'migration_url', $api_options );
	}

	/**
	 * Get migration group id
	 */
	public static function get_mig_gid() {
		$api_options = self::get_options();

		return self::get_args_option( 'group_uuid', $api_options );
	}

	/**
	 * Has migration group id
	 */
	public static function has_mig_gid( $group_uuid ) {
		if ( empty( $group_uuid ) ) {
			return false;
		}
		return $group_uuid === self::get_mig_gid();
	}

	public static function set_connect_origin( $origin ) {
		$api_options           = self::get_options();
		$api_options['origin'] = $origin;

		return self::set_settings( $api_options );
	}

	public static function set_jwt( $jwt ) {
		$api_options        = self::get_options();
		$api_options['jwt'] = $jwt;

		return self::set_settings( $api_options );
	}

	public static function set_api_domain( $api_domain = '' ) {
		if ( empty( $api_domain ) ) {
			$api_domain = esc_url_raw( 'https://app.instawp.io' );
		}

		$api_options            = self::get_options();
		$api_options['api_url'] = $api_domain;

		return self::set_settings( $api_options );
	}

	public static function get_connect_plan() {
		$api_options = self::get_options();
		$plan_id     = self::get_args_option( 'plan_id', $api_options );

		if ( empty( $plan_id ) ) {
			return array();
		}

		return array(
			'plan_id'        => $plan_id,
			'plan_timestamp' => self::get_args_option( "plan_{$plan_id}_timestamp", $api_options ),
		);
	}

	public static function get_connect_plan_id() {
		$connect_plan = self::get_connect_plan();

		return self::get_args_option( 'plan_id', $connect_plan );
	}

	public static function set_connect_plan_id( $plan_id ) {
		$api_options = self::get_options();

		if ( ! empty( $plan_id ) ) {
			$key = "plan_{$plan_id}_timestamp";

			if ( ! isset( $api_options[ $key ] ) ) {
				$api_options[ $key ] = current_time( 'mysql' );
			}

			$api_options['plan_id'] = $plan_id;
		} else {
			unset( $api_options['plan_id'] );
		}

		return self::set_settings( $api_options );
	}

	public static function remove_connect_plan_id() {
		$api_options = self::get_options();
		$plan_id     = self::get_args_option( 'plan_id', $api_options );

		if ( empty( $plan_id ) ) {
			return false;
		}

		unset( $api_options['plan_id'] );
		unset( $api_options[ "plan_{$plan_id}_timestamp" ] );

		return self::set_settings( $api_options );
	}

	public static function wp_site_url( $path = '', $check_ssl = false ) {
		global $wpdb;

		$site_url = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'" );

		if ( empty( $site_url ) ) {
			return get_site_url( null, $path );
		}

		if ( $path && is_string( $path ) ) {
			$site_url .= '/' . ltrim( $path, '/' );
		}

		if ( $check_ssl ) {
			$parsed_url = parse_url( $site_url );
			$protocol   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] : 'unknown';

			if ( $protocol !== 'https' ) {
				$site_url = site_url( $path );
			}
		}

		return $site_url;
	}
}
