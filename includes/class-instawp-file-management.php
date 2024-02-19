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

			add_action( 'init', array( $this, 'add_endpoint' ) );
			add_action( 'template_redirect', array( $this, 'redirect' ) );
			add_action( self::$action, array( $this, 'clean' ) );
			add_action( 'admin_post_instawp-file-manager-auto-login', array( $this, 'auto_login' ) );
			add_action( 'admin_post_nopriv_instawp-file-manager-auto-login', array( $this, 'auto_login' ) );
			add_action( 'update_option_instawp_rm_file_manager', array( $this, 'clean_file' ) );
			add_action( 'instawp_connect_create_file_manager_task', array( $this, 'create_task' ) );
			add_action( 'instawp_connect_remove_file_manager_task', array( $this, 'remove_task' ) );
			add_filter( 'query_vars', array( $this, 'query_vars' ), 99 );
			add_filter( 'template_include', array( $this, 'load_template' ), 999 );
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
			$this->file_manager->clean( $file_name );
		}

		public function clean_file() {
			$this->file_manager->clean();
		}

		public function create_task( $file_name ) {
			wp_schedule_single_event( time() + DAY_IN_SECONDS, self::$action, array( $file_name ) );
		}

		public function remove_task( $file_name ) {
			wp_clear_scheduled_hook( self::$action, array( $file_name ) );
		}

		public function auto_login() {
			$file_name = defined( 'INSTAWP_FILE_MANAGER_FILE_NAME' ) ? INSTAWP_FILE_MANAGER_FILE_NAME : '';

			if ( empty( $file_name ) ) {
				wp_die( esc_html__( 'File Manager file not found!', 'instawp-connect' ) );
			}

			$token = get_transient( 'instawp_file_manager_login_token' );
			if ( empty( $_GET['token'] ) || ! $token ) {
				wp_die( esc_html__( 'Auto Login token expired or missing!', 'instawp-connect' ) );
			}

			if ( ! hash_equals( sanitize_text_field( wp_unslash( $_GET['token'] ) ), hash( 'sha256', $token ) ) ) {
				wp_die( esc_html__( 'InstaWP File Manager: Token mismatch or not valid!', 'instawp-connect' ) );
			}

			$file_manager_url = $this->file_manager::get_file_manager_url( $file_name );
			ob_start() ?>

            <script type="text/javascript">
                window.onload = function () {
                    setTimeout(function () {
                        location.href = '<?php echo esc_url( $file_manager_url ); ?>?autologin';
                    }, 2000);
                }
            </script>

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