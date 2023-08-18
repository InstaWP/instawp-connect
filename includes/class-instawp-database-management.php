<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

if ( ! class_exists( 'InstaWP_Database_Management' ) ) {
	class InstaWP_Database_Management {
		
		protected static $_instance = null;
		private static $query_var;
		private $database_manager;

		/**
		 * @return InstaWP_Database_Management
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			$this->database_manager = new \InstaWP\Connect\Helpers\DatabaseManager();
			self::$query_var        = $this->database_manager::get_query_var();

			add_action( 'init', [ $this, 'add_endpoint' ] );
			add_action( 'template_redirect', [ $this, 'redirect' ] );
			add_action( 'instawp_clean_database_manager', [ $this, 'clean' ] );
			add_action( 'admin_post_instawp-database-manager-auto-login', [ $this, 'auto_login' ] );
			add_action( 'admin_post_nopriv_instawp-database-manager-auto-login', [ $this, 'auto_login' ] );
			add_filter( 'query_vars', [ $this, 'query_vars' ], 99 );
			add_filter( 'template_include', [ $this, 'load_template' ], 999 );
		}

		public function add_endpoint() {
			add_rewrite_endpoint( self::$query_var, EP_ROOT | EP_PAGES );
		}

		public function redirect() {
			$template_name = get_query_var( self::$query_var, false );
			if ( $template_name && ! $this->get_template() ) {
				wp_safe_redirect( home_url() );
				exit();
			}
		}

		public function clean( $file_name ) {
			$file_path = $this->database_manager::get_file_path( $file_name );
			if ( file_exists( $file_path ) ) {
				@unlink( $file_path );
			}

			InstaWP_Setting::delete_option( 'instawp_database_manager_name' );
			flush_rewrite_rules();
		}

		public function auto_login() {
			$file_name = InstaWP_Setting::get_option( 'instawp_database_manager_name', '' );
			if ( ! $file_name ) {
				wp_die( esc_html__( 'Database Manager file not found!', 'instawp-connect' ) );
			}

			$token = get_transient( 'instawp_database_manager_login_token' );
			if ( empty( $_GET['token'] ) || ! $token ) {
				wp_die( esc_html__( 'Auto Login token expired or missing!', 'instawp-connect' ) );
			}

			if ( ! hash_equals( sanitize_text_field( wp_unslash( $_GET['token'] ) ), hash( 'sha256', $token ) ) ) {
				wp_die( esc_html__( 'InstaWP Database Manager: Token mismatch or not valid!', 'instawp-connect' ) );
			}

			$database_manager_url = $this->database_manager::get_database_manager_url( $file_name );
			ob_start() ?>

			<input type="hidden" name="auth[driver]" required="required" value="server">
			<input type="hidden" name="auth[server]" required="required" value="<?php echo esc_attr( DB_HOST ); ?>">
			<input type="hidden" name="auth[username]" required="required" value="<?php echo esc_attr( DB_USER ); ?>">
			<input type="hidden" name="auth[password]" required="required" value="<?php echo esc_attr( DB_PASSWORD ); ?>">
			<input type="hidden" name="auth[db]" required="required" value="<?php echo esc_attr( DB_NAME ); ?>">
			<input type="hidden" name="auth[permanent]" required="required" value="1">
			
			<?php
			$fields = ob_get_clean();
			instawp()->auto_login_page( $fields, $database_manager_url, __( 'InstaWP Database Manager', 'instawp-connect' ) );
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
			$template_path = $this->database_manager::get_file_path( $template_name );
			$loader_path   = INSTAWP_PLUGIN_DIR . '/includes/database-manager/loader.php';

			if ( file_exists( $template_path ) && file_exists( $loader_path ) ) {
				$template = $loader_path;
			}

			return $template;
		}
	}
}

InstaWP_Database_Management::instance();