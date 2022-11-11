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

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class InstaWP_Staging_Site_Table extends WP_List_Table {

    function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" />',
            'stage_site_url' => 'Stage Site URL',
            'stage_site_user' => 'Stage Site USER',
            'stage_site_pass' => 'Stage Site PASS',
            'stage_site_login_button' => 'Stage Site LOGIN',
        ];
    }

    function prepare_items()
    {
        $options = get_user_meta(get_current_user_id(), 'instawp_stagelist_page_options', true);
        
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : null;

        $orderby = (isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : '';
        $order = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'ASC';

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->process_bulk_action();
        // Get all Data for anchor section
        $data = array();
        $connect_ids  = get_option('instawp_connect_id_options', '');
        if ( isset( $connect_ids['data']['id'] ) && ! empty($connect_ids['data']['id']) ) {
            $connect_id    = $connect_ids['data']['id'];
            $staging_sites = get_option('instawp_staging_list_items', array());

            if (sizeof($staging_sites) > 0) {
                $data = $staging_sites[ $connect_id ];
            }
        }

        // $staging_sites = get_option('instawp_staging_list_items', array());
        //$data = $staging_sites[ '539' ];

        $this->items = $data;

        $this->set_pagination_args(
            array(
                'total_items' => sizeof($data),
                'per_page' => $per_page,
                'total_pages' => ceil(sizeof($data) / $per_page)
            )
        );
    }

    function process_bulk_action()
    {   
        if ( 
            ( 
                isset( $_GET['action'] ) && 
                'bulk-delete' == $_GET['action'] 
            ) || ( 
                isset( $_GET['action2'] ) && 
                'bulk-delete' == $_GET['action2'] 
            ) && 
            isset( $_GET['staging_sites_ids'] )
        ) {
            $row_ids = esc_sql( $_GET['staging_sites_ids'] );

            $staging_sites = get_option('instawp_staging_list_items', array());
            
            $connect_id = '';
            $connect_ids  = get_option('instawp_connect_id_options', '');
            if ( isset( $connect_ids['data']['id'] ) && ! empty($connect_ids['data']['id']) ) {
                $connect_id = $connect_ids['data']['id'];
            }
            //$connect_id = '539';
            if (!empty($connect_id)) {
                //$staging_sites = $staging_sites['539'];
                $staging_sites = $staging_sites[$connect_id];

                foreach ( $row_ids as $index => $array_row_id ) {
                    if (array_key_exists($array_row_id, $staging_sites)){
                        unset($staging_sites[$array_row_id]);
                    }
                }

                // Reset with connect ID
                //$staging_sites_new['539'] = $staging_sites;
                $staging_sites_new[$connect_id] = $staging_sites;

                update_option('instawp_staging_list_items', $staging_sites_new);
            }
        }
    }

    public function get_bulk_actions() {
        $actions = array(
            'bulk-delete'    => __( 'Delete', 'instawp-bulk-delete' ),
        );
        return $actions;
    }

    function column_default($item, $column_name)
    {       
        switch ( $column_name ) {
            case 'stage_site_url':
            $site_name = $item['stage_site_url']['site_name'];
            $site_admin_url = $item['stage_site_url']['wp_admin_url'];

            $col_html = '<a href="'.$site_admin_url.'">'.$site_name.'</p>';
            return $col_html;
            case 'stage_site_user':

            $site_user = $item['stage_site_user'];
            $col_html = '<p>'.$site_user.'</p>';
            return $col_html;

            case 'stage_site_pass':
            $site_pass = $item['stage_site_pass'];
            $col_html = '<p>'.$site_pass.'</p>';
            return $col_html;

            case 'stage_site_login_button':
            $site_login = $item['stage_site_login_button'];
            $col_html = '<a class="button primary-button" href="'.$site_login.'">Auto Login</a>';
            return $col_html;
            default:
            break;
        }
    }

    function get_sortable_columns()
    {
        return array();
    }

    public function get_hidden_columns() {
        return array( 'id' );
    }

    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="staging_sites_ids[]" value="%s" />', $item['stage_site_task_id']
        );
    }
}

function display_instawp_staging_site_table() {
    $instawp_staging_site_table = new InstaWP_Staging_Site_Table();
    ?>
    <form method="get" id="stage_sites">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
        <?php 
        $instawp_staging_site_table->prepare_items(); 
        $instawp_staging_site_table->display(); 
        ?>
    </form>
    <?php
}
display_instawp_staging_site_table();