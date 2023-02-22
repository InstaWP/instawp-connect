<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instaWP
 * @subpackage instaWP/admin/partials
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class InstaWP_Staging_Site_Table extends WP_List_Table {

	private int $items_per_page = 20;


	function get_columns() {
		return [
			'cb'         => '<input type="checkbox" />',
			'site_url'   => esc_html__( 'Website URL', 'instawp-connect' ),
			'username'   => esc_html__( 'User Name', 'instawp-connect' ),
			'password'   => esc_html__( 'Password', 'instawp-connect' ),
			'auto_login' => esc_html__( 'Auto login', 'instawp-connect' ),
		];
	}


	function prepare_items() {

		global $wpdb;

		$this->process_bulk_action();

		$staging_sites         = $wpdb->get_results( "SELECT * FROM " . INSTAWP_DB_TABLE_STAGING_SITES, ARRAY_A );
		$this->_column_headers = array( $this->get_columns(), [], [] );
		$this->items           = array_slice( $staging_sites, ( ( $this->get_pagenum() - 1 ) * $this->items_per_page ), $this->items_per_page );;


		// Setting up pagination arguments
		$this->set_pagination_args(
			array(
				'total_items' => count( $staging_sites ),
				'per_page'    => $this->items_per_page,
			)
		);
	}


	function process_bulk_action() {
		if (
			( isset( $_GET['action'] ) && 'bulk-delete' == $_GET['action'] ) ||
			( isset( $_GET['action2'] ) && 'bulk-delete' == $_GET['action2'] ) &&
			isset( $_GET['staging_sites_ids'] ) &&
			! empty( $_GET['staging_sites_ids'] )
		) {

			global $wpdb;

			$sites_ids = isset( $_GET['staging_sites_ids'] ) ? wp_unslash( $_GET['staging_sites_ids'] ) : array();

			if ( sizeof( $sites_ids ) > 0 ) {

				foreach ( $sites_ids as $id ) {

					do_action( 'INSTAWP/Staging_sites/before_delete_site', $id );

					$wpdb->delete( INSTAWP_DB_TABLE_STAGING_SITES, array( 'id' => $id ) );

					if ( ! empty( $wpdb->last_error ) ) {
						error_log( sprintf( esc_html__( 'Error in deleting from stating sites table. Error: %s', 'instawp-connect' ), $wpdb->last_error ) );
					}
				}
			}
		}
	}


	public function get_bulk_actions() {
		$actions = array(
			'bulk-delete' => __( 'Delete', 'instawp-bulk-delete' ),
		);

		return $actions;
	}


	function column_default( $item, $column_name ) {

		$output_html = '';

		switch ( $column_name ) {
			case 'site_url':

				$site_url    = isset( $item['site_url'] ) ? $item['site_url'] : '';
				$output_html = sprintf( '<strong><a class="row-title" href="%s" target="_blank">%s</a></strong>', esc_url_raw( 'https://' . $site_url ), $site_url );

				break;
			case 'username':

				$output_html = sprintf( '<p class="username">%s</p>', ( isset( $item['username'] ) ? $item['username'] : '' ) );

				break;
			case 'password':

				$output_html = sprintf( '<p class="password">%s</p>', ( isset( $item['password'] ) ? $item['password'] : '' ) );

				break;
			case 'auto_login':

				$api_domain      = InstaWP_Setting::get_api_domain();
				$auto_login_hash = isset( $item['auto_login_hash'] ) ? $item['auto_login_hash'] : '';
				$auto_login_url  = $api_domain . '/wordpress-auto-login?' . http_build_query( array( 'site' => $auto_login_hash ) );
				$output_html     = sprintf( '<a class="button primary-button" href="%s" target="_blank">%s</a>', esc_url_raw( $auto_login_url ), esc_html__( 'Auto Login', 'instawp-connect' ) );

				break;
			default:
				break;
		}

		return $output_html;
	}


	function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="staging_sites_ids[]" value="%s" />', $item['id'] );
	}
}

function instawp_render_staging_site_table() {

	$staging_table = new InstaWP_Staging_Site_Table();
	$current_page  = isset( $_REQUEST['page'] ) ? sanitize_text_field( $_REQUEST['page'] ) : '';
	$url           = admin_url( 'admin.php?page=instawp-connect' );
	?>
    <div class="wrap instabtn">
        <h1 class="wp-heading-inline">Staging Sites</h1>
        <a href="<?php echo esc_url_raw( $url ); ?>" class="button" style="background-color: #005E54;color:#fff;margin-top:10px;border-radius:6px;"><?php esc_html_e( 'Create New', 'instawp-connect' ); ?></a>
        <form method="get" id="stage_sites">
            <input type="hidden" name="page" value="<?php echo esc_attr( $current_page ); ?>"/>
			<?php
			$staging_table->prepare_items();
			$staging_table->display();
			?>
        </form>
    </div>
	<?php
}

instawp_render_staging_site_table();