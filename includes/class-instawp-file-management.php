<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

if ( ! class_exists( 'InstaWP_File_Management' ) ) {
	class InstaWP_File_Management {
		
		protected static $_instance = null;
		private static $query_var;
		private static $action;
		private $file_manager;

		/**
		 * @return InstaWP_File_Management
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			$this->file_manager = new \InstaWP\Connect\Helpers\FileManager();
			self::$query_var    = $this->file_manager::get_query_var();
			self::$action       = 'instawp_clean_file_manager';

			add_action( 'init', [ $this, 'add_endpoint' ] );
			add_action( 'wp', [ $this, 'filter_redirect' ] );
			add_action( 'template_redirect', [ $this, 'redirect' ] );
			add_action( self::$action, [ $this, 'clean' ] );
			add_action( 'admin_post_instawp-file-manager-auto-login', [ $this, 'auto_login' ] );
			add_action( 'admin_post_nopriv_instawp-file-manager-auto-login', [ $this, 'auto_login' ] );
			add_action( 'update_option_instawp_rm_file_manager', array( $this, 'clean_file' ) );
			add_action( 'instawp_connect_create_file_manager_task', array( $this, 'create_task' ) );
			add_action( 'instawp_connect_remove_file_manager_task', array( $this, 'remove_task' ) );
			add_filter( 'query_vars', [ $this, 'query_vars' ], 99 );
			add_filter( 'template_include', [ $this, 'load_template' ], 999 );
		}

		public function add_endpoint() {
			add_rewrite_endpoint( self::$query_var, EP_ROOT | EP_PAGES );
		}
		
		public function filter_redirect() {
			if ( $this->get_template() ) {
				remove_action( 'template_redirect', 'redirect_canonical' );
				add_filter( 'redirect_canonical', '__return_false' );
			}
		}

		public function redirect() {
			$template_name = get_query_var( self::$query_var, false );
			if ( $template_name && ! $this->get_template() ) {
				wp_safe_redirect( home_url() );
				exit();
			}
		}

		public function clean( $file_name ) {
			$this->file_manager->clean( $file_name );
		}

		public function clean_file() {
			$this->file_manager->clean();
		}

		public function create_task( $file_name ) {
			as_schedule_single_action( time() + DAY_IN_SECONDS, 'instawp_clean_file_manager', [ $file_name ], 'instawp-connect', false, 5 );
		}

		public function remove_task( $file_name ) {
			as_unschedule_all_actions( 'instawp_clean_file_manager', [ $file_name ], 'instawp-connect' );
		}

		public function auto_login() {
			$file_db_manager = InstaWP_Setting::get_option( 'instawp_file_db_manager', [] );
			$file_name       = InstaWP_Setting::get_args_option( 'file_name', $file_db_manager );
			if ( ! $file_name ) {
				wp_die( esc_html__( 'File Manager file not found!', 'instawp-connect' ) );
			}

			$token = get_transient( 'instawp_file_manager_login_token' );
			if ( empty( $_GET['token'] ) || ! $token ) {
				wp_die( esc_html__( 'Auto Login token expired or missing!', 'instawp-connect' ) );
			}

			if ( ! hash_equals( sanitize_text_field( wp_unslash( $_GET['token'] ) ), hash( 'sha256', $token ) ) ) {
				wp_die( esc_html__( 'InstaWP File Manager: Token mismatch or not valid!', 'instawp-connect' ) );
			}

			@ini_set( 'session.gc_maxlifetime', 1800 ); // 30 minutes
			session_cache_limiter( 'nocache' ); // Prevent logout issue after page was cached
			session_name( INSTAWP_FILE_MANAGER_SESSION_ID );
			function session_error_handling_function( $code, $msg, $file, $line ) {
				// Permission denied for default session, try to create a new one
				if ( $code == 2 ) {
					session_abort();
					session_id( session_create_id() );
					@session_start();
				}
			}
			set_error_handler( 'session_error_handling_function' );
			session_start();
			restore_error_handler();

			if ( empty( $_SESSION['token'] ) ) {
				$_SESSION['token'] = InstaWP_Tools::get_random_string( 64 );
			}
			
			$file_manager_url = $this->file_manager::get_file_manager_url( $file_name ); 
			ob_start() ?>

			<input type="hidden" name="fm_usr" required="required" value="<?php echo esc_attr( INSTAWP_FILE_MANAGER_USERNAME ); ?>">
			<input type="hidden" name="fm_pwd" required="required" value="<?php echo esc_attr( INSTAWP_FILE_MANAGER_PASSWORD ); ?>">
			<input type="hidden" name="token" required="required" value="<?php echo esc_attr( $_SESSION['token'] ); ?>">
			
			<?php
			$fields = ob_get_clean();
			instawp()->tools::auto_login_page( $fields, $file_manager_url, __( 'InstaWP File Manager', 'instawp-connect' ) );
		}

		public function query_vars( $query_vars ) {
			if ( ! in_array( self::$query_var, $query_vars, true ) ) {
				$query_vars[] = self::$query_var;
			}

			return $query_vars;
		}
		
		public function load_template( $template ) {
			return $this->get_template( $template );
		}

		private function get_template( $template = false ) {
			$template_name = get_query_var( self::$query_var );
			$template_path = $this->file_manager::get_file_path( $template_name );

			if ( file_exists( $template_path ) ) {
				$template = $template_path;
			}

			return $template;
		}
	}
}

InstaWP_File_Management::instance();