<?php
/**
 * REST API for Iterative Pull Push
 *
 * @link       https://instawp.com/
 * @since      0.1.0.70
 * @package    instaWP
 * @subpackage instaWP/admin
 * @author     instaWP team
 */
defined( 'ABSPATH' ) || die;

class InstaWP_Rest_Api_IPP extends InstaWP_Rest_Api {
	
	/**
	 * @var Helper
	 */
	private $helper;

	public function __construct() {
		parent::__construct();
		$this->helper = new INSTAWP_IPP_HELPER();
		add_action( 'rest_api_init', array( $this, 'add_api_routes' ) );
	}

	/**
	 * Add REST API routes
	 * developer.wordpress.org/rest-api/requests/#attributes
	 */
	public function add_api_routes() {

		// GET Table List
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/ipp', '/db-schema', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'get_db_schema' ),
			'permission_callback' => array( $this, 'validate_ipp_api' ),
		) );

		// Install or update plugins, themes or core
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/ipp', '/is-ready', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'is_ready' ),
			'args'                => array(
				'php_version' => array(
					'required' => true,
					'type'     => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			),
			'permission_callback' => array( $this, 'validate_ipp_api' ),
		) );

		// GET files checksum
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/ipp', '/files-checksum', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'get_files_checksum' ),
			'args'                => array(
				'settings' => array(
					'required' => true
				)
			),
			'permission_callback' => array( $this, 'validate_ipp_api' ),
		) );
		// GET TABLE CHECKSUM
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/ipp', '/table-checksum', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'get_table_checksum' ),
			'args'                => array(
				'table' => array(
					'required' => true,
					'type'     => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				),
				'start_id' => array(
					'required' => false,
					'type'     => 'integer',
					'minimum'  => 0,
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				),
				'last_id_to_process' => array(
					'required' => false,
					'type'     => 'integer',
					'minimum'  => 1,
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				),
			),
			'permission_callback' => array( $this, 'validate_ipp_api' ),
		) );

		register_rest_route( $this->namespace . '/' . $this->version_2 . '/ipp', '/set-pull-settings', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'set_pull_settings' ),
			'args'                => array(
				'settings' => array(
					'required' => true,
					'type'     => 'array',
				)
			),
			'permission_callback' => array( $this, 'validate_ipp_api' ),
		) );

	}

	/**
	 * REST API permission callback
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function validate_ipp_api( WP_REST_Request $request ) {
		$res = $this->validate_api_request( $request );
		if ( true !== $res ) {
			return $res;
		}
		$connect_id    = instawp_get_connect_id();
		if ( empty( $connect_id ) ) {
			return new WP_Error( 403, esc_html__( 'Site is not connected.', 'instawp-connect' ) );
		}
		return true;
	}

	/**
	 * REST API check if system is ready for iterative pull push
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function is_ready( WP_REST_Request $request ) {
		$php_version = $request->get_param( 'php_version' );
		if ( empty( $php_version ) ) {
			return $this->send_response( array(
				'success' => false,	
				'message' => __( 'PHP version is required', 'instawp-connect' )
			) );
		}

		// Check PHP version. Major version must be the same
		if ( $php_version[0] !== PHP_VERSION[0] ) {
			return $this->send_response( array(
				'success' => false,
				'message' => sprintf( __( 'PHP version must be %s, but it is %s', 'instawp-connect' ), $settings['php_version'], PHP_VERSION )
			) );
		}

		return $this->send_response( array(
			'success' => true,
			'message' => __( 'System is ready', 'instawp-connect' )
		) );
	}

	/**
	 * REST API for get db schema
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_db_schema( WP_REST_Request $request ) {
		// Get db schema
		$db_schema = $this->helper->get_db_schema();
		// Merge db schema
		$db_meta = get_option( $this->helper->vars['db_meta_repo'], array() );
		$db_meta = empty( $db_meta ) ? array() : $db_meta;
		$db_meta = array_merge( $db_meta, $db_schema );
		// Update db meta
		update_option( $this->helper->vars['db_meta_repo'], $db_meta );
		// Send response
		return $this->send_response( array(
			'success' => true,
			'data'    => $db_schema
		) );
	}

	/**
	 * REST API for get files checksum
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_files_checksum( WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );
		if ( empty( $settings ) || ! is_array( $settings ) ) {
			return $this->send_response( array(
				'success' => false,	
				'message' => __( 'Missing or invalid settings argument.', 'instawp-connect' )
			) );
		}

		$settings = $this->helper->sanitize_array( $settings );
		return $this->send_response( array(
			'success' => true,
			'data'    => $this->helper->get_files_checksum( $settings )
		) );
	}

	/**
	 * REST API for get table checksum
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_table_checksum( WP_REST_Request $request ) {
		try {
			$table = $request->get_param( 'table' );
			$start_id = $request->get_param( 'start_id' );
			$last_id_to_process = $request->get_param( 'last_id_to_process' );

			if ( empty( $table ) ) {
				return $this->send_response( array(
					'success' => false,
					'error_code' => 'table_name_required',	
					'message' => __( 'Table name is required', 'instawp-connect' )
				) );
			}
			$db_meta = get_option( $this->helper->vars['db_meta_repo'], array() );
			$table = sanitize_key( $table );

			if ( ! empty( $db_meta['tables'] ) && ! in_array( $table, $db_meta['tables'] ) ) {
				return $this->send_response( array(
					'success' => false,	
					'error_code' => 'table_not_found',	
					'message' => __( 'Table not found', 'instawp-connect' )
				) );
			}
			$start_id = empty( $start_id ) ? 1 : absint( sanitize_key( $start_id ) );
			$last_id_to_process = empty( $last_id_to_process ) ? 0 : absint( sanitize_key( $last_id_to_process ) );
			$meta = $this->helper->get_table_meta( array( $table ), true, $start_id, $last_id_to_process );
			if ( empty( $meta ) || ! empty( $meta['error'] ) ) {
				return $this->send_response( array(
					'success' => false,	
					'message' => $meta['error']
				) );
			}
			return $this->send_response( array(
				'success' => true,
				'data'    => $meta
			) );
		} catch ( \Exception $e ) {
			return $this->send_response( array(
				'success' => false,
				'message' => $e->getMessage()
			) );
		}
	}

	/**
	 * REST API for set pull settings
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_pull_settings( WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );
		if ( empty( $settings ) ) {
			return $this->send_response( array(
				'success' => false,	
				'message' => __( 'Settings are required', 'instawp-connect' )
			) );
		}
		if ( $settings['php_version'] !== PHP_VERSION ) {
			return $this->send_response( array(
				'success' => false,
				'message' => sprintf( __( 'PHP version must be %s, but it is %s', 'instawp-connect' ), $settings['php_version'], PHP_VERSION )
			) );
		}
		update_option( $this->helper->vars['db_meta_repo'] . '_pull_settings', $settings );
	
		return $this->send_response( array(
			'success' => true,
			'message' => __( 'Settings saved successfully.', 'instawp-connect' )
		) );
	}

}

new InstaWP_Rest_Api_IPP();