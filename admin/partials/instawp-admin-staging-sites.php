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
            $staging_sites_list = get_option('instawp_staging_list_items', array());
            // echo "<pre>";
            // print_r($staging_sites_list);
            // echo "</pre>";
            // die();
            // if ( isset( $staging_sites[ $connect_id ][ $task_id ] ) ) {

            //     $staging_sites = $staging_sites[ $connect_id ];
            //     foreach ($staging_sites as $key => $staging_site) {
            //         if ( isset( $staging_site['data']['status'] ) && $staging_site['data']['status'] == 1 ) {
            //             $site_name = $staging_site['data']['wp'][0]['site_name']; 
            //             $wp_admin_url = $staging_site['data']['wp'][0]['wp_admin_url']; 
            //             $wp_username = $staging_site['data']['wp'][0]['wp_username']; 
            //             $wp_password = $staging_site['data']['wp'][0]['wp_password']; 
            //             $wp_password = $staging_site['data']['wp'][0]['wp_password']; 
            //             $auto_login_hash = $staging_site['data']['wp'][0]['auto_login_hash']; 
            //             $auto_login_url = add_query_arg( array( 'site' => $auto_login_hash ), $auto_login_url );
                        
            //             $data = array(
            //                 array(
            //                     'id'    => 1,
            //                     'website'  => 'http://localhost/instawp',
            //                     'username' => $wp_username,
            //                     'password' => $wp_password,
            //                     'autologin' => $auto_login_url
            //                 ),            
            //             );        
            //         }   
            //     }
                
            // }   
            $data = array(
                array(
                    'id'    => 1,
                    'website'  => 'http://localhost/instawp',
                    'username' => 'admin',
                    'password' => 'admin',
                    'autologin' => 'http://localhost/instawp/wp-login.php'
                ),   
                array(
                    'id'    => 2,
                    'website'  => 'http://localhost/instawp',
                    'username' => 'admin',
                    'password' => 'admin',
                    'autologin' => 'http://localhost/instawp/wp-login.php'
                ),     
                array(
                    'id'    => 3,
                    'website'  => 'http://localhost/instawp',
                    'username' => 'admin',
                    'password' => 'admin',
                    'autologin' => 'http://localhost/instawp/wp-login.php'
                ),    
                array(
                    'id'    => 4,
                    'website'  => 'http://localhost/instawp',
                    'username' => 'admin',
                    'password' => 'admin',
                    'autologin' => 'http://localhost/instawp/wp-login.php'
                ),    
                array(
                    'id'    => 5,
                    'website'  => 'http://localhost/instawp',
                    'username' => 'admin',
                    'password' => 'admin',
                    'autologin' => 'http://localhost/instawp/wp-login.php'
                ),           
            );        
        }
        
        return $data;
        
        ?>
        <?php /*
         <div class="instawp-site-details-wrapper">
            <p id="site-details-progress" class="<?php echo esc_attr( $progress_class); ?>">
            <?php echo esc_html__( 'Please wait Staging Site Creation Is In Progress','instawp-connect' ); ?>
            </p>
            <div class="site-details <?php echo esc_attr( $site_class); ?>">
                <p> <?php echo esc_html__('WP login Credentials','instawp-connect') ?></p>
                <p id="instawp_site_url"> <?php echo esc_html__('URL','instawp-connect') ?> : <a target="_blank" href="<?php echo esc_url( str_replace('wp-admin', '', $wp_admin_url) ); ?>"><?php echo esc_html($site_name); ?></a></p>
                <p id="instawp_admin_url"> <?php echo esc_html__( 'Admin URL','instawp-connect' ); ?> : <a target="_blank" href="<?php echo esc_url( $wp_admin_url ); ?>"> <?php echo esc_url( $wp_admin_url ); ?> </a></p>
                <p id="instawp_user_name"><?php echo esc_html__( 'Admin Username','instawp-connect' ); ?> : <span> <?php echo esc_html($wp_username); ?> </span></p>
                <p id="instawp_password"> <?php echo esc_html__( 'Admin Password','instawp-connect' ); ?> : <span> : <span> <?php echo esc_html( $wp_password ); ?> </span></p>
            </div>
            <div class="login-btn">
                <div class="instawp-wizard-btn-wrap <?php echo esc_attr( $site_class); ?>">
              <a  class="instawp-wizard-btn" target="_blank" href="<?php echo esc_url($auto_login_url); ?>">
                <?php echo esc_html__('Auto login','instawp-connect'); ?>
                
              </a>
          

        </div>
            </div>
        </div>
        <?php*/
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

/*function search_box( $text, $input_id ) {    
    
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
}*/

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