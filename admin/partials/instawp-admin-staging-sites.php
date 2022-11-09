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
    require_once ABSPATH . 'wp-admin/includes/template.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
    require_once ABSPATH . 'wp-admin/includes/screen.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class InstaWP_Staging_Site_Table extends WP_List_Table {

    public function prepare_items() {
        $data         = $this->wp_list_table_data();
        $per_page     = 8;
        $current_page = $this->get_pagenum();
        $total_items  = count( $data );
        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
            )
        );

        // $this->items           = $data;
        $this->items           = array_slice(
            $data,
            ( ( $current_page - 1 ) * $per_page ),
            $per_page
        );
        $columns               = $this->get_columns();
        $hidden                = $this->get_hidden_columns();
        $this->_column_headers = array( $columns, $hidden );
    }

    public function wp_list_table_data() {
        $data = array();
        $listArray = array();
        $site_name = '';
        $wp_admin_url = '';
        $wp_username = '';
        $wp_password = '';
        $auto_login_hash = '';
        $staging_site = array();
        $api_doamin = InstaWP_Setting::get_api_domain();
        $auto_login_url = $api_doamin . '/wordpress-auto-login';
        $connect_ids  = get_option('instawp_connect_id_options', '');
        if ( isset( $connect_ids['data']['id'] ) && ! empty($connect_ids['data']['id']) ) {
            $connect_id    = $connect_ids['data']['id'];
            $staging_sites = get_option('instawp_staging_list_items', array());
            
           
            $connect_id = '1245467637';
            if ( isset( $staging_sites[ $connect_id ] ) ) {          
                $staging_sites = $staging_sites[ $connect_id ];
                foreach ( $staging_sites as $loop_task_id => $staging_site ) {
                    echo $loop_task_id;
                    $site_name =  $staging_site['data']['wp'][0]['site_name']; 
                    $wp_admin_url = $staging_site['data']['wp'][0]['wp_admin_url']; 
                    $wp_username = $staging_site['data']['wp'][0]['wp_username']; 
                    $wp_password = $staging_site['data']['wp'][0]['wp_password']; 
                    $auto_login_hash = $staging_site['data']['wp'][0]['auto_login_hash']; 
                    $auto_login_url = add_query_arg( array( 'site' => $auto_login_hash ), $auto_login_url );

                    $listArray['id'] = $loop_task_id;
                    $listArray['website'] = $site_name;
                    $listArray['username'] = $wp_username;
                    $listArray['password'] = $wp_password;
                    $listArray['autologin'] = '<a href='.$auto_login_url.' target="_blank">Autologin</a>';  
                }
            }   
                  
        }   
        var_export($listArray);
        die;
        return array($listArray);
    }

    public function get_hidden_columns() {
        return array( 'id' );
    }

    function column_cb($item) {             
        return sprintf(
            '<input type="checkbox" name="staging_sites[]" value="%s" />', $item['id']
        );
    }
    
    // function column_name($item){
    //     $item_json = json_decode(json_encode($item), true);
    //     $actions = array(
    //         'edit' => sprintf('<a href="?page=%s&action=%s&id=%s">Edit</a>', $_REQUEST['page'], 'edit', $item_json['id']),
    //         'delete' => sprintf('<a href="?page=%s&action=%s&id=%s">Delete</a>', $_REQUEST['page'], 'delete', $item_json['id']),
    //     );
    //     return '<em>' . sprintf('%s %s', $item_json['name'], $this->row_actions($actions)) . '</em>';
    // }

    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'id'     => 'ID',
            'website'   => 'URL',
            'username'  => 'Username',
            'password' => 'Password',
            'autologin' => 'Autologin'
        );

        return $columns;
    }
    
    public function get_bulk_actions() {

        $actions = array(
            'delete'    => __( 'Delete', 'instawp-bulk-delete' ),
        );
        return $actions;

	}

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
            case 'website':
            case 'username':
            case 'password':
            case 'autologin':
                return $item[ $column_name ];
            default:
                return 'N/A';
        }
    }
}

function search_box( $text, $input_id ) {    
    
    if ( empty( $_REQUEST['s'] ) && !$this->has_items() )
        return;

    $input_id = $input_id . '-search-input';

    if ( ! empty( $_REQUEST['orderby'] ) )
        echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
    if ( ! empty( $_REQUEST['order'] ) )
        echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
    if ( ! empty( $_REQUEST['post_mime_type'] ) )
        echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
    if ( ! empty( $_REQUEST['detached'] ) )
        echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
    ?>
    <p class="search-box">
        <label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
        <input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
        <?php submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); ?>
    </p>
    <?php
}

function display_instawp_staging_site_table() {
    $instawp_staging_site_table = new InstaWP_Staging_Site_Table();
    ?>
    <form method="post">
        <div class="wrap">
            <?php 
                $instawp_staging_site_table->prepare_items();
                //$instawp_staging_site_table->search_box('Search', 'search'); 
                $instawp_staging_site_table->display(); 
            ?>
        </div>
    </form>
    <?php
}
display_instawp_staging_site_table();