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

	public function add_api_routes() {

		// GET Table List
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/ipp', '/table-list', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'get_table_list' ),
			'permission_callback' => array( $this, 'validate_ipp_api' ),
		) );

		// Install or update plugins, themes or core
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/ipp', '/is-ready', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'is_ready' ),
			'args'                => array(
				'settings' => array(
					'required' => true,
					'validate_callback' => 'is_array',
				)
			),
			'permission_callback' => array( $this, 'validate_ipp_api' ),
		) );

		// GET files checksum
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/ipp', '/files-checksum', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'get_files_checksum' ),
			'permission_callback' => array( $this, 'validate_ipp_api' ),
		) );
		// GET TABLE CHECKSUM
		register_rest_route( $this->namespace . '/' . $this->version_2 . '/ipp', '/table-checksum', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'get_table_checksum' ),
			'args'                => array(
				'table' => array(
					'required' => true,
					'validate_callback' => 'is_string',
				),
				'start_id' => array(
					'required' => false,
					'validate_callback' => 'is_numeric',
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
					'validate_callback' => 'is_array',
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
		$settings = $request->get_param( 'settings' );
		if ( empty( $settings ) ) {
			return array(
				'success' => false,	
				'message' => __( 'Settings are required', 'instawp-connect' )
			);
		}

		// Check PHP version. Major version must be the same
		if ( $settings['php_version'][0] !== PHP_VERSION[0] ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'PHP version must be %s, but it is %s', 'instawp-connect' ), $settings['php_version'], PHP_VERSION )
			);
		}

		return array(
			'success' => true,
			'message' => __( 'System is ready', 'instawp-connect' )
		);
	}

	/**
	 * REST API for get table list
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_table_list( WP_REST_Request $request ) {
		return array(
			'success' => true,
			'data'    => array(
				'table_list' => $this->helper->get_table_list()
			)
		);
	}

	/**
	 * REST API for get files checksum
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_files_checksum( WP_REST_Request $request ) {
		return array(
			'success' => true,
			'data'    => $this->helper->get_file_settings()
		);
	}

	/**
	 * REST API for get table checksum
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_table_checksum( WP_REST_Request $request ) {
		$table = $request->get_param( 'table' );
		$start_id = $request->get_param( 'start_id' );

		if ( empty( $table ) ) {
			return array(
				'success' => false,	
				'message' => __( 'Table name is required', 'instawp-connect' )
			);
		}
		$db_meta = get_option( $this->db_meta_name, array() );
		$table = sanitize_key( $table );
		if ( ! empty( $db_meta['tables'] ) && ! in_array( $table, $db_meta['tables'] ) ) {
			return array(
				'success' => false,	
				'message' => __( 'Table not found', 'instawp-connect' )
			);
		}
		$start_id = empty( $start_id ) ? 1 : absint( sanitize_key( $start_id ) );
		$meta = $this->helper->get_table_meta( array( $table ), $db_meta, true, $start_id );
		return array(
			'success' => true,
			'data'    => $meta
		);
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
			return array(
				'success' => false,	
				'message' => __( 'Settings are required', 'instawp-connect' )
			);
		}
		if ( $settings['php_version'] !== PHP_VERSION ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'PHP version must be %s, but it is %s', 'instawp-connect' ), $settings['php_version'], PHP_VERSION )
			);
		}
		update_option( $this->db_meta_name . '_pull_settings', $settings );
	
		return array(
			'success' => true,
			'message' => __( 'Settings saved successfully.', 'instawp-connect' )
		);
	}

}

new InstaWP_Rest_Api_IPP();