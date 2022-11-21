<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instaWP
 * @subpackage instaWP/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    instaWP
 * @subpackage instaWP/admin
 * @author     instawp team
 */
if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}
class InstaWP_Admin {

    /**
     * The ID of this plugin.
     *
     * 
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * 
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    private $screen_ids;

    private $toolbar_menus;

    private $submenus;
    /**
     * Initialize the class and set its properties.
     *
     * 
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        add_filter('instawp_get_screen_ids',array( $this, 'get_screen_ids' ),10);
        //add_filter('instawp_get_toolbar_menus',array( $this, 'get_toolbar_menus' ),10);
        add_filter('instawp_get_admin_menus',array( $this, 'get_admin_menus' ),10);
        
      

        add_action('instawp_before_setup_page',array( $this, 'migrate_notice' ));
        //add_action('instawp_before_setup_page',array( $this, 'show_add_my_review' ));
        add_action('instawp_before_setup_page',array( $this, 'check_extensions' ));
        //add_action('instawp_before_setup_page',array( $this, 'check_amazons3' ));
        //add_action('instawp_before_setup_page',array($this,'check_dropbox'));
        add_action('instawp_before_setup_page',array( $this, 'init_js_var' ));

        add_filter('instawp_add_log_tab_page', array( $this, 'add_log_tab_page' ), 10);

        //add_action('admin_notices', array( $this, 'check_instawp_pro_version' ));

        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function add_log_tab_page( $setting_array ) {
        $setting_array['backup_log_page'] = array(
            'index'     => '1',
            'tab_func'  => array( $this, 'instawp_add_tab_log' ),
            'page_func' => array( $this, 'instawp_add_page_log' ),
        );
        //$setting_array['read_log_page'] = array('index' => '2', 'tab_func' =>  array($this, 'instawp_add_tab_read_log'), 'page_func' => array($this, 'instawp_add_page_read_log'));
        return $setting_array;
    }

    public function get_screen_ids( $screen_ids ) {       

        $screen_ids[] = 'toplevel_page_'.$this->plugin_name;
        $screen_ids[] = 'instawp-connect_page_instawp-connect';
        $screen_ids[] = 'instawp-connect_page_instawp-settings';
        $screen_ids[] = 'instawp_page_instawp-settings';
        $screen_ids[] = 'instawp-connect_page_instawp-transfer';
        $screen_ids[] = 'instawp-connect_page_instawp-setting';
        $screen_ids[] = 'instawp-connect_page_instawp-schedule';
        $screen_ids[] = 'instawp-connect_page_instawp-remote';
        $screen_ids[] = 'instawp-connect_page_instawp-website';
        $screen_ids[] = 'instawp-connect_page_instawp-log';
        $screen_ids[] = 'instawp-connect_page_instawp-key';
        $screen_ids[] = 'instawp-connect_page_instawp-mainwp';
        $screen_ids[] = 'instawp-connect_page_instawp_premium';
        return $screen_ids;
    }

    public function get_toolbar_menus( $toolbar_menus ) {
        $menu['id'] = 'instawp_admin_menu';
        $menu['title'] = 'InstaWP Connect';
        $toolbar_menus[ $menu['id'] ] = $menu;

        $admin_url = admin_url();

        $menu['id'] = 'instawp_admin_menu_backup';
        $menu['parent'] = 'instawp_admin_menu';
        $menu['title'] = __('Connect', 'instawp-connect');
        $menu['tab'] = 'admin.php?page=instawp-connect&tab-backup';
        $menu['href'] = $admin_url . 'admin.php?page=instawp-connect';
        $menu['capability'] = 'administrator';
        $menu['index'] = 1;
        $toolbar_menus[ $menu['parent'] ]['child'][ $menu['id'] ] = $menu;

        return $toolbar_menus;
    }

    public function get_admin_menus( $submenus ) {
        $submenu['parent_slug'] = $this->plugin_name;
        $submenu['page_title'] = 'InstaWP Connect';
        $submenu['menu_title'] = __('Backup & Restore', 'instawp-connect');
        $submenu['capability'] = 'administrator';
        $submenu['menu_slug'] = $this->plugin_name;
        $submenu['function'] = array( $this, 'display_plugin_setup_page' );
        $submenu['index'] = 1;
        $submenus[ $submenu['menu_slug'] ] = $submenu;

        $submenu['parent_slug'] = $this->plugin_name;
        $submenu['page_title'] = 'Create New';
        $submenu['menu_title'] = __('Create New', 'instawp-connect');
        $submenu['capability'] = 'administrator';
        $submenu['menu_slug'] = 'instawp-connect';
        $submenu['function'] = array( $this, 'display_wizard_page' );
        $submenu['index'] = 2;
        $submenus[ $submenu['menu_slug'] ] = $submenu;

        //new
        $submenu['parent_slug'] = $this->plugin_name;
        $submenu['page_title'] = 'Staging Sites';
        $submenu['menu_title'] = __('Staging Sites', 'instawp-connect');
        $submenu['capability'] = 'administrator';
        $submenu['menu_slug'] = 'instawp-staging-site';
        $submenu['function'] = array( $this, 'display_staging_sites_page' );
        $submenu['index'] = 2;
        $submenus[ $submenu['menu_slug'] ] = $submenu;

        $submenu['parent_slug'] = $this->plugin_name;
        $submenu['page_title'] = 'Settings';
        $submenu['menu_title'] = __('Settings', 'instawp-connect');
        $submenu['capability'] = 'administrator';
        $submenu['menu_slug'] = 'instawp-settings';
        $submenu['function'] = array( $this, 'display_settings_page' );
        $submenu['index'] = 2;
        $submenus[ $submenu['menu_slug'] ] = $submenu;

        return $submenus;
    }

    

    /**
     * Register the stylesheets for the admin area.
     *
     * 
     */
    public function enqueue_styles() {
        $this->screen_ids = apply_filters('instawp_get_screen_ids',$this->screen_ids);
        
        if ( in_array(get_current_screen()->id,$this->screen_ids) ) {
            wp_enqueue_style($this->plugin_name, INSTAWP_PLUGIN_DIR_URL . 'css/instawp-admin.css', array(), $this->version, 'all');
            do_action('instawp_do_enqueue_styles');
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * 
     */
    public function enqueue_scripts() {
        $this->screen_ids = apply_filters('instawp_get_screen_ids',$this->screen_ids);
        if ( in_array(get_current_screen()->id,$this->screen_ids) ) {
            wp_enqueue_script($this->plugin_name, INSTAWP_PLUGIN_DIR_URL . 'js/instawp-admin.js', array( 'jquery' ), $this->version, false);
            $instawp_api_url = InstaWP_Setting::get_api_domain();
            
            wp_localize_script($this->plugin_name, 'instawp_ajax_object', array(
                'ajax_url'   => admin_url('admin-ajax.php'), 
                'cloud_url'   => $instawp_api_url, 
                'admin_url'   => admin_url(), 
                'ajax_nonce' => wp_create_nonce('instawp_ajax'),
                'nlogger' => wp_create_nonce('instawp_nlogger_update_option_by-nlogger'),
                'plugin_connect_url' => admin_url( "admin.php?page=instawp-connect" ),
            ));

            wp_localize_script($this->plugin_name, 'instawplion', array(
                'warning'             => __('Warning:', 'instawp-connect'),
                'error'               => __('Error:', 'instawp-connect'),
                'remotealias'         => __('Warning: An alias for remote storage is required.', 'instawp-connect'),
                'remoteexist'         => __('Warning: The alias already exists in storage list.', 'instawp-connect'),
                'backup_calc_timeout' => __('Calculating the size of files, folder and database timed out. If you continue to receive this error, please go to the plugin settings, uncheck \'Calculate the size of files, folder and database before backing up\', save changes, then try again.', 'instawp-connect'),
                'restore_step1'       => __('Step One: In the backup list, click the \'Restore\' button on the backup you want to restore. This will bring up the restore tab', 'instawp-connect'),
                'restore_step2'       => __('Step Two: Choose an option to complete restore, if any', 'instawp-connect'),
                'restore_step3'       => __('Step Three: Click \'Restore\' button', 'instawp-connect'),
                'get_key_step1'       => __('1. Visit Key tab page of instaWP backup plugin of destination site.', 'instawp-connect'),
                'get_key_step2'       => __('2. Generate a key by clicking Generate button and copy it.', 'instawp-connect'),
                'get_key_step3'       => __('3. Go back to this page and paste the key in key box below. Lastly, click Save button.', 'instawp-connect'),
            ));

            wp_enqueue_script('plupload-all');
            do_action('instawp_do_enqueue_scripts');
        }
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * 
     */
    public function add_plugin_admin_menu() {

        /*
         * Add a settings page for this plugin to the Settings menu.
         *
         * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
         *
         *        Administration Menus: http://codex.wordpress.org/Administration_Menus
         *
         */
        $dash_icon = esc_url(INSTAWP_PLUGIN_IMAGES_URL.'cloud.svg'); 
        
        $menu['page_title'] = 'InstaWP Connect';
        $menu['menu_title'] = 'InstaWP';
        $menu['capability'] = 'administrator';
        $menu['menu_slug'] = $this->plugin_name;
        $menu['function'] = array( $this, 'display_plugin_setup_page' );
        $menu['icon_url'] = $dash_icon;
        $menu['position'] = 100;
        $menu = apply_filters('instawp_get_main_admin_menus', $menu);
        add_menu_page( $menu['page_title'],$menu['menu_title'], $menu['capability'], $menu['menu_slug'], $menu['function'], $menu['icon_url'], $menu['position']);
        $this->submenus = apply_filters('instawp_get_admin_menus', $this->submenus);
        usort($this->submenus, function ( $a, $b ) {
            if ($a['index'] == $b['index'])
                return 0;

            if ($a['index'] > $b['index'])
                return 1;
            else
                return -1;
        });
        foreach ( $this->submenus as $submenu ) {
            add_submenu_page(
                $submenu['parent_slug'],
                $submenu['page_title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['menu_slug'],
                $submenu['function']);
        }
    }

    function add_toolbar_items( $wp_admin_bar ) {
        if ( is_multisite() ) {
            if ( ! is_network_admin() ) {
                return ;
            }
        }
        
        /*global $instawp_plugin;
        if ( is_admin() ) {
            $show_admin_bar = $instawp_plugin->get_admin_bar_setting();
            if ( $show_admin_bar === true ) {
                $this->toolbar_menus = apply_filters('instawp_get_toolbar_menus', $this->toolbar_menus);
                foreach ( $this->toolbar_menus as $menu ) {
                    $wp_admin_bar->add_menu(array(
                        'id'    => $menu['id'],
                        'title' => $menu['title'],
                    ));
                    if ( isset($menu['child']) ) {
                        usort($menu['child'], function ( $a, $b ) {
                            if ($a['index'] == $b['index'])
                                return 0;

                            if ($a['index'] > $b['index'])
                                return 1;
                            else
                                return -1;
                        });
                        foreach ( $menu['child'] as $child_menu ) {
                            if ( isset($child_menu['capability']) && current_user_can($child_menu['capability']) ) {
                                $wp_admin_bar->add_menu(array(
                                    'id'     => $child_menu['id'],
                                    'parent' => $menu['id'],
                                    'title'  => $child_menu['title'],
                                    'href'   => $child_menu['href'],
                                ));
                            }
                        }
                    }
                }
            }
        }*/
    }

    public function add_action_links( $links ) {
        if ( ! is_multisite() ) {
            $active_plugins = get_option('active_plugins');
        }
        else {
            $active_plugins = array();
            //network active
            $mu_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
            if ( ! empty($mu_active_plugins) ) {
                foreach ( $mu_active_plugins as $plugin_name => $data ) {
                    $active_plugins[] = $plugin_name;
                }
            }
        }
        if ( ! function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $plugins = get_plugins();
        $pro_instawp_slug = 'instawp-backup-pro/instawp-backup-pro.php';
        $is_active_pro = false;
        if ( ! empty($plugins) ) {
            if ( isset($plugins[ $pro_instawp_slug ]) ) {
                if ( in_array($pro_instawp_slug, $active_plugins) ) {
                    $is_active_pro = true;
                }
            }
        }

        if ( $is_active_pro ) {
            if ( ! is_multisite() ) {
                $settings_link = array(
                    '<a href="' . admin_url( 'admin.php?page=' . strtolower(sprintf('%s-dashboard', apply_filters('instawp_white_label_slug', 'instawp'))) ) . '">' . __('Settings', 'instawp-connect') . '</a>',
                );
            }
            else {
                $settings_link = array(
                    '<a href="' . network_admin_url( 'admin.php?page=' . strtolower(sprintf('%s-dashboard', apply_filters('instawp_white_label_slug', 'instawp'))) ) . '">' . __('Settings', 'instawp-connect') . '</a>',
                );
            }
        }
        else {
            if ( ! is_multisite() ) {
                $settings_link = array(
                    '<a href="' . admin_url( 'admin.php?page=' . apply_filters('instawp_white_label_slug', $this->plugin_name) ) . '">' . __('Settings', 'instawp-connect') . '</a>',
                );
            }
            else {
                $settings_link = array(
                    '<a href="' . network_admin_url( 'admin.php?page=' . apply_filters('instawp_white_label_slug', $this->plugin_name) ) . '">' . __('Settings', 'instawp-connect') . '</a>',
                );
            }
        }

        return array_merge(  $settings_link, $links );
    }

    public static function instawp_get_siteurl(){
        $instawp_siteurl = array();
        $instawp_siteurl['home_url'] = home_url();
        $instawp_siteurl['plug_url'] = plugins_url();
        $instawp_siteurl['site_url'] = get_option( 'siteurl' );
        return $instawp_siteurl;
    }

    /**
     * Render the settings page for this plugin.
     *
     * 
     */
    public function display_plugin_setup_page() {
        do_action('instawp_before_setup_page');

        add_action('instawp_display_page',array( $this, 'display' ));

        do_action('instawp_display_page');
    }
    public function display_wizard_page() {

        include_once('partials/instawp-admin-wizard.php');  
    }

    public function display_settings_page() {

        //include_once('partials/instawp-settings-page-display.php');  
        include_once('partials/instawp-admin-settings.php');  
    }

    public function display_staging_sites_page() {

        include_once('partials/instawp-admin-staging-sites.php');  
    }

    public function migrate_notice() {
        $migrate_notice = false;
        $migrate_status = InstaWP_Setting::get_option('instawp_migrate_status');
        if ( ! empty($migrate_status) && $migrate_status == 'completed' ) {
            $migrate_notice = true;
            echo '<div class="notice notice-warning is-dismissible"><p>'.esc_html__('Migration is complete and htaccess file is replaced. In order to successfully complete the migration, you\'d better reinstall 301 redirect plugin, firewall and security plugin, and caching plugin if they exist.', 'instawp-connect').'</p></div>';
            InstaWP_Setting::delete_option('instawp_migrate_status');
        }
        $restore = new InstaWP_restore_data();
        if ( $restore->has_restore() ) {
            $restore_status = $restore->get_restore_status();
            if ( $restore_status === INSTAWP_RESTORE_COMPLETED ) {
                $restore->clean_restore_data();
                do_action('instawp_rebuild_backup_list');
                $need_review = InstaWP_Setting::get_option('instawp_need_review');
                if ( ! $migrate_notice ) {
                    echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Restore completed successfully.', 'instawp-connect').'</p></div>';
                }
                // if($need_review=='not')
                // {
                //     InstaWP_Setting::update_option('instawp_need_review','show');
                //     $msg = __('Cheers! instaWP Backup plugin has restored successfully your website. If you found instaWP Backup plugin helpful, a 5-star rating would be highly appreciated, which motivates us to keep providing new features.', 'instawp-connect');
                //     InstaWP_Setting::update_option('instawp_review_msg',$msg);
                // }
                // else{
                    
                // }
            }
        }
    }

    public function display() {
        include_once('partials/instawp-admin-display.php');
    }

    public static function instawp_get_page_request() {
        $request_page = 'instawp_tab_general';

       
        if ( isset($_REQUEST['tab-backup']) ) {
            $request_page = 'instawp_tab_general';
        }
        
        $request_page = apply_filters('instawp_set_page_request',$request_page);

        return $request_page;
    }

    /*public static function show_add_my_review() {
        $review = InstaWP_Setting::get_option('instawp_need_review');
        $review_msg = InstaWP_Setting::get_option('instawp_review_msg');
        if ( empty($review) ) {
            InstaWP_Setting::update_option('instawp_need_review', 'not');
        } else {
            if ( $review == 'not' ) {
            }
            elseif ( $review == 'show' ) {
                if ( ! empty($review_msg) ) {
                    echo '<div class="notice notice-info is-dismissible" id="instawp_notice_rate">
                    <p>' . $review_msg . '</p>
                    <div style="padding-bottom: 10px;">
                    <span><input type="button" class="button-primary" option="review" name="rate-now" value="'.esc_attr__('Rate Us', 'instawp-connect').'" /></span>
                    <span><input type="button" class="button-secondary" option="review" name="ask-later" value="'.esc_attr__('Maybe Later', 'instawp-connect').'" /></span>
                    <span><input type="button" class="button-secondary" option="review" name="never-ask" value="'.esc_attr__('Never', 'instawp-connect').'" /></span>
                    <span><input type="button" class="button-secondary" option="review" name="already-done" value="'.esc_attr__('Already Done', 'instawp-connect').'" /></span>
                    </div>
                    </div>';
                }
            } elseif ( $review == 'do_not_ask' ) {
            } else {
                if ( time() > $review ) {
                    if ( ! empty($review_msg) ) {
                        echo '<div class="notice notice-info is-dismissible" id="instawp_notice_rate">
                        <p>' . $review_msg . '</p>
                        <div style="padding-bottom: 10px;">
                        <span><input type="button" class="button-primary" option="review" name="rate-now" value="'.esc_attr__('Rate Us', 'instawp-connect').'" /></span>    
                        <span><input type="button" class="button-secondary" option="review" name="ask-later" value="'.esc_attr__('Maybe Later', 'instawp-connect').'" /></span>
                        <span><input type="button" class="button-secondary" option="review" name="never-ask" value="'.esc_attr__('Never', 'instawp-connect').'" /></span>
                        <span><input type="button" class="button-secondary" option="review" name="already-done" value="'.esc_attr__('Already Done', 'instawp-connect').'" /></span>
                        </div>
                        </div>';
                    }
                }
            }
        }
    }*/

    // public function check_amazons3() {
    //     $remoteslist = InstaWP_Setting::get_all_remote_options();
    //     $need_amazons3_notice = false;
    //     if ( isset($remoteslist) && ! empty($remoteslist) ) {
    //         foreach ( $remoteslist as $remote_id => $value ) {
    //             if ( $remote_id === 'remote_selected' ) {
    //                 continue;
    //             }
    //             if ( $value['type'] == 'amazons3' && isset($value['s3Path']) ) {
    //                 $need_amazons3_notice = true;
    //             }
    //             if ( $value['type'] == 's3compat' && isset($value['s3directory']) ) {
    //                 $need_amazons3_notice = true;
    //             }
    //         }
    //     }
    //     if ( $need_amazons3_notice ) {
    //         $amazons3_notice = InstaWP_Setting::get_option('instawp_amazons3_notice', 'not init');
    //         if ( $amazons3_notice === 'not init' ) {
    //             $notice_message = __('As Amazon S3 and DigitalOcean Space have upgraded their connection methods, please delete the previous connections and re-add your Amazon S3/DigitalOcean Space accounts to make sure the connections work.', 'instawp-connect');
    //             echo '<div class="notice notice-warning" id="instawp_amazons3_notice">
    //                     <p>' . $notice_message . '</p>
    //                     <div style="padding-bottom: 10px;">
    //                     <span><input type="button" class="button-secondary" value="I Understand" onclick="instawp_click_amazons3_notice();" /></span>
    //                     </div>
    //                     </div>';
    //         }
    //     }
    // }

    /*public function check_dropbox() {
        if ( is_multisite() ) {
            if ( ! is_network_admin() ) {
                return ;
            }
        }

        if ( ! function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $pro_instawp_slug = 'instawp-backup-pro/instawp-backup-pro.php';
        if ( is_multisite() ) {
            $active_plugins = array();
            //network active
            $mu_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
            if ( ! empty($mu_active_plugins) ) {
                foreach ( $mu_active_plugins as $plugin_name => $data ) {
                    $active_plugins[] = $plugin_name;
                }
            }
            $plugins = get_mu_plugins();
            if ( count($plugins) == 0 || ! isset($plugins[ $pro_instawp_slug ]) ) {
                $plugins = get_plugins();
            }
        }
        else {
            $active_plugins = get_option('active_plugins');
            $plugins = get_plugins();
        }

        if ( ! empty($plugins) ) {
            if ( isset($plugins[ $pro_instawp_slug ]) ) {
                if ( in_array($pro_instawp_slug, $active_plugins) ) {
                    return;
                }
            }

            $remoteslist = InstaWP_Setting::get_all_remote_options();
            $need_dropbox_notice = false;
            if ( isset($remoteslist) && ! empty($remoteslist) ) {
                foreach ( $remoteslist as $remote_id => $value ) {
                    if ( $remote_id === 'remote_selected' ) {
                        continue;
                    }
                    if ( $value['type'] == 'dropbox' && ! isset($value['refresh_token']) ) {
                        $need_dropbox_notice = true;
                    }
                }
            }
            if ( $need_dropbox_notice ) {
                $notice_message = __('Because Dropbox has upgraded their API on September 30, 2021, the new API is no longer compatible with the previous app\'s settings. Please re-add your Dropbox storage to ensure that it works properly.', 'instawp-connect');
                echo '<div class="notice notice-warning">
                                    <p>' . $notice_message . '</p>
                               </div>';
            }
        }
    }*/

    public function check_extensions() {
        $common_setting = InstaWP_Setting::get_setting(false, 'instawp_common_setting');
        $db_connect_method = isset($common_setting['options']['instawp_common_setting']['db_connect_method']) ? $common_setting['options']['instawp_common_setting']['db_connect_method'] : 'wpdb';
        $need_php_extensions = array();
        $need_extensions_count = 0;
        $extensions = get_loaded_extensions();
        if ( ! function_exists("curl_init") ) {
            $need_php_extensions[ $need_extensions_count ] = 'curl';
            $need_extensions_count++;
        }
        if ( ! class_exists('PDO') ) {
            $need_php_extensions[ $need_extensions_count ] = 'PDO';
            $need_extensions_count++;
        }
        if ( ! function_exists("gzopen") ) {
            $need_php_extensions[ $need_extensions_count ] = 'zlib';
            $need_extensions_count++;
        }
        if ( ! array_search('pdo_mysql',$extensions) && $db_connect_method === 'pdo' ) {
            $need_php_extensions[ $need_extensions_count ] = 'pdo_mysql';
            $need_extensions_count++;
        }
        if ( ! empty($need_php_extensions) ) {
            $msg = '';
            $figure = 0;
            foreach ( $need_php_extensions as $extension ) {
                $figure++;
                if ( $figure == 1 ) {
                    $msg .= $extension;
                }
                elseif ( $figure < $need_extensions_count ) {
                    $msg .= ', '.$extension;
                }
                elseif ( $figure == $need_extensions_count ) {
                    $msg .= ' and '.$extension;
                }
            }
            if ( $figure == 1 ) {
                    /* translators: %s: extension */
                echo '<div class="notice notice-error"><p>'.sprintf(esc_html__('The %s extension is not detected. Please install the extension first.', 'instawp-connect'), esc_html( $msg )).'</p></div>';
            }
            else {
                /* translators: %s: extension */
                echo '<div class="notice notice-error"><p>'.sprintf(esc_html__('The %s extensions are not detected. Please install the extensions first.', 'instawp-connect'), esc_html( $msg ) ).'</p></div>';
            }
        }

        if ( ! class_exists('PclZip')) include_once(ABSPATH.'/wp-admin/includes/class-pclzip.php');
        if ( ! class_exists('PclZip') ) {
            echo '<div class="notice notice-error"><p>'.esc_html__('Class PclZip is not detected. Please update or reinstall your WordPress.', 'instawp-connect').'</p></div>';
        }

        $hide_notice = get_option('instawp_hide_wp_cron_notice', false);
        if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON && $hide_notice === false ) {
            echo '<div class="notice notice-error notice-wp-cron is-dismissible"><p>'.esc_html__('In order to execute the scheduled backups properly, please set the DISABLE_WP_CRON constant to false. If you are using an external cron system, simply click \'X\' to dismiss this message.', 'instawp-connect').'</p></div>';
        }
    }

    /*public function check_instawp_pro_version() {
        if ( is_multisite() ) {
            if ( ! is_network_admin() ) {
                return ;
            }
        }

        if ( ! function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $pro_instawp_slug = 'instawp-backup-pro/instawp-backup-pro.php';
        if ( is_multisite() ) {
            $active_plugins = array();
            //network active
            $mu_active_plugins = get_site_option( 'active_sitewide_plugins', array() );
            if ( ! empty($mu_active_plugins) ) {
                foreach ( $mu_active_plugins as $plugin_name => $data ) {
                    $active_plugins[] = $plugin_name;
                }
            }
            $plugins = get_mu_plugins();
            if ( count($plugins) == 0 || ! isset($plugins[ $pro_instawp_slug ]) ) {
                $plugins = get_plugins();
            }
        }
        else {
            $active_plugins = get_option('active_plugins');
            $plugins = get_plugins();
        }

        if ( ! empty($plugins) ) {
            if ( isset($plugins[ $pro_instawp_slug ]) ) {
                if ( in_array($pro_instawp_slug, $active_plugins) ) {
                    if ( version_compare('2.0.23',$plugins[ $pro_instawp_slug ]['Version'],'>') ) {
                        ?>
                        <div class="notice notice-warning" style="padding: 11px 15px;">
                            <?php echo sprintf(__('We detected that you are using a lower version of %s Pro, please update it to 2.0.23 or higher to ensure backing up to Google Drive works properly.', 'instawp-connect'), apply_filters('instawp_white_label_display', 'instaWP Backup Plugin')); ?>
                        </div>
                        <?php
                    }
                }
            }
        }
    }*/

    public function init_js_var() {
        global $instawp_plugin;

        $loglist = $instawp_plugin->get_log_list_ex();
        $remoteslist = InstaWP_Setting::get_all_remote_options();
        $default_remote_storage = '';
        foreach ( $remoteslist['remote_selected'] as $value ) {
            $default_remote_storage = $value;
        }
        ?>
        <script>
            var instawp_siteurl = '<?php
                $instawp_siteurl = array();
                $instawp_siteurl = InstaWP_Admin::instawp_get_siteurl();
                echo esc_url($instawp_siteurl['site_url']);
                ?>';
            var instawp_plugurl =  '<?php
                echo esc_url( INSTAWP_PLUGIN_URL );
                ?>';
            var instawp_log_count = '<?php
                echo esc_html(sizeof($loglist['log_list']['file']));
                ?>';
            var instawp_log_array = '<?php
                 wp_kses_post(wp_json_encode($loglist), 'instawp-connect');
                ?>';
            var instawp_page_request = '<?php
                $page_request = InstaWP_Admin::instawp_get_page_request();
                echo esc_html($page_request);
                ?>';
            var instawp_default_remote_storage = '<?php
                echo esc_html($default_remote_storage);
                ?>';
        </script>
        <?php
    }

    public function instawp_add_default_tab_page( $page_array ) {
        $page_array['backup_restore'] = array(
            'index'     => '1',
            'tab_func'  => array( $this, 'instawp_add_tab_backup_restore' ),
            'page_func' => array( $this, 'instawp_add_page_backup' ),
        );

        
        
        return $page_array;
    }

    public function instawp_add_tab_backup_restore(){
        ?>
        <a href="#" id="instawp_tab_general" class="nav-tab wrap-nav-tab nav-tab-active" onclick="switchTabs(event,'general-page')"><?php esc_html_e('Backup & Restore', 'instawp-connect'); ?></a>
        <?php
    }

    public function instawp_add_tab_schedule(){
        ?>
        <a href="#" id="instawp_tab_schedule" class="nav-tab wrap-nav-tab" onclick="switchTabs(event,'schedule-page')"><?php esc_html_e('Schedule', 'instawp-connect'); ?></a>
        <?php
    }

    public function instawp_add_tab_remote_storage(){
        ?>
        <a href="#" id="instawp_tab_remote_storage" class="nav-tab wrap-nav-tab" onclick="switchTabs(event,'storage-page')"><?php esc_html_e('Remote Storage', 'instawp-connect'); ?></a>
        <?php
    }

    public function instawp_add_tab_setting(){
        ?>
        <a href="#" id="instawp_tab_setting" class="nav-tab wrap-nav-tab" onclick="switchTabs(event,'settings-page')"><?php esc_html_e('Settings', 'instawp-connect'); ?></a>
        <?php
    }

    public function instawp_add_tab_website_info(){
        ?>
        <a href="#" id="instawp_tab_debug" class="nav-tab wrap-nav-tab" onclick="switchTabs(event,'debug-page')"><?php esc_html_e('Debug', 'instawp-connect'); ?></a>
        <?php
    }

    public function instawp_add_tab_log(){
        ?>
        <a href="#" id="instawp_tab_log" class="nav-tab log-nav-tab nav-tab-active" onclick="switchlogTabs(event,'logs-page')"><?php esc_html_e('Backup Logs', 'instawp-connect'); ?></a>
        <?php
    }

    public function instawp_add_tab_read_log(){
        ?>
        <a href="#" id="instawp_tab_read_log" class="nav-tab wrap-nav-tab delete" onclick="switchTabs(event,'log-read-page')" style="display: none;">
            <div style="margin-right: 15px;"><?php esc_html_e('Log', 'instawp-connect'); ?></div>
            <div class="nav-tab-delete-img">
                <img src="<?php echo esc_url( INSTAWP_PLUGIN_URL.'/admin/partials/images/delete-tab.png' ); ?>" style="vertical-align:middle; cursor:pointer;" onclick="instawp_close_tab(event, 'instawp_tab_read_log', 'wrap', 'instawp_tab_log');" />
            </div>
        </a>
        <?php
    }

    public function instawp_add_tab_mwp(){
        ?>
        <a href="#" id="instawp_tab_mainwp" class="nav-tab wrap-nav-tab delete" onclick="switchTabs(event, 'mwp-page')">
            <div style="margin-right: 15px;"><?php esc_html_e('MainWP', 'instawp-connect'); ?></div>
            <div class="nav-tab-delete-img">
                <img src="<?php echo esc_url(INSTAWP_PLUGIN_URL.'/admin/partials/images/delete-tab.png'); ?>" style="vertical-align:middle; cursor:pointer;" onclick="instawp_close_tab(event, 'instawp_tab_mainwp', 'wrap', 'instawp_tab_general');" />
            </div>
        </a>
        <?php
    }

    public function instawp_add_tab_premium(){
        ?>
        <a href="#" id="instawp_tab_premium" class="nav-tab wrap-nav-tab" onclick="switchTabs(event,'premium-page')"><?php esc_html_e('Premium', 'instawp-connect'); ?></a>
        <?php
    }

    public function instawp_add_page_backup() {
        ?>
        <div id="general-page" class="wrap-tab-content instawp_tab_general" name="tab-backup" style="width:100%;">
            <div class="meta-box-sortables ui-sortable">
                <?php
                do_action('instawp_backuppage_add_module');
                ?>
                <h2 class="nav-tab-wrapper" id="instawp_backup_tab" style="padding-bottom:0!important;">
                <?php
                $backuplist_array = array();
                $backuplist_array = apply_filters('instawp_backuppage_load_backuplist', $backuplist_array);
                foreach ( $backuplist_array as $list_name ) {
                    add_action('instawp_backuppage_add_tab', $list_name['tab_func'], $list_name['index']);
                    add_action('instawp_backuppage_add_page', $list_name['page_func'], $list_name['index']);
                }
                do_action('instawp_backuppage_add_tab');
                ?>
                </h2>
                <?php  do_action('instawp_backuppage_add_page'); ?>
            </div>
        </div>
        <script>
            <?php do_action('instawp_backup_do_js'); ?>
        </script>
        <?php
    }

    public function instawp_add_page_schedule() {
        ?>
        <div id="schedule-page" class="wrap-tab-content instawp_tab_schedule" name="tab-schedule" style="display: none;">
            <div>
                <table class="widefat">
                    <tbody>
                    <?php do_action('instawp_schedule_add_cell'); ?>
                    <tfoot>
                    <tr>
                        <th class="row-title"><input class="button-primary storage-account-button" id="instawp_schedule_save" type="submit" name="" value="<?php esc_attr_e( 'Save Changes', 'instawp-connect' ); ?>" /></th>
                        <th></th>
                    </tr>
                    </tfoot>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            jQuery('#instawp_schedule_save').click(function(){
                instawp_set_schedule();
                instawp_settings_changed = false;
            });

            function instawp_set_schedule()
            {
                var schedule_data = instawp_ajax_data_transfer('schedule');
                var ajax_data = {
                    'action': 'instawp_set_schedule',
                    'schedule': schedule_data
                };
                jQuery('#instawp_schedule_save').css({'pointer-events': 'none', 'opacity': '0.4'});
                instawp_post_request(ajax_data, function (data) {
                    try {
                        var jsonarray = jQuery.parseJSON(data);

                        jQuery('#instawp_schedule_save').css({'pointer-events': 'auto', 'opacity': '1'});
                        if (jsonarray.result === 'success') {
                            location.reload();
                        }
                        else {
                            alert(jsonarray.error);
                        }
                    }
                    catch (err) {
                        alert(err);
                        jQuery('#instawp_schedule_save').css({'pointer-events': 'auto', 'opacity': '1'});
                    }
                }, function (XMLHttpRequest, textStatus, errorThrown) {
                    jQuery('#instawp_schedule_save').css({'pointer-events': 'auto', 'opacity': '1'});
                    var error_message = instawp_output_ajaxerror('changing schedule', textStatus, errorThrown);
                    alert(error_message);
                });
            }
        </script>
        <?php
    }

    public function instawp_add_page_remote_storage() {
        ?>
        <div id="storage-page" class="wrap-tab-content instawp_tab_remote_storage" name="tab-storage" style="display:none;">
            <div>
                <div class="storage-content" id="storage-brand-2" style="">
                    <div class="postbox">
                        <?php do_action('instawp_add_storage_tab'); ?>
                    </div>
                    <div class="postbox storage-account-block" id="instawp_storage_account_block">
                        <?php do_action('instawp_add_storage_page'); ?>
                    </div>
                    <h2 class="nav-tab-wrapper" style="padding-bottom:0!important;">
                        <?php do_action('instawp_storage_add_tab'); ?>
                    </h2>
                    <?php do_action('instawp_storage_add_page'); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function instawp_add_page_setting() {
        ?>
        <div id="settings-page" class="wrap-tab-content instawp_tab_setting" name="tab-setting" style="display:none;">
            <div>
                <h2 class="nav-tab-wrapper" style="padding-bottom:0!important;">
                    <?php
                    $setting_array = array();
                    $setting_array = apply_filters('instawp_add_setting_tab_page', $setting_array);
                    foreach ( $setting_array as $setting_name ) {
                        add_action('instawp_settingpage_add_tab', $setting_name['tab_func'], $setting_name['index']);
                        add_action('instawp_settingpage_add_page', $setting_name['page_func'], $setting_name['index']);
                    }
                    do_action('instawp_settingpage_add_tab');
                    ?>
                </h2>
                <?php do_action('instawp_settingpage_add_page'); ?>
                <div><input class="button-primary" id="instawp_setting_general_save" type="submit" value="<?php esc_attr_e( 'Save Changes', 'instawp-connect' ); ?>" /></div>
            </div>
        </div>
        <script>
            jQuery('#instawp_setting_general_save').click(function(){
                instawp_set_general_settings();
                instawp_settings_changed = false;
            });

            function instawp_set_general_settings()
            {
                var setting_data = instawp_ajax_data_transfer('setting');
                var ajax_data = {
                    'action': 'instawp_set_general_setting',
                    'setting': setting_data
                };
                jQuery('#instawp_setting_general_save').css({'pointer-events': 'none', 'opacity': '0.4'});
                instawp_post_request(ajax_data, function (data) {
                    try {
                        var jsonarray = jQuery.parseJSON(data);

                        jQuery('#instawp_setting_general_save').css({'pointer-events': 'auto', 'opacity': '1'});
                        if (jsonarray.result === 'success') {
                            location.reload();
                        }
                        else {
                            alert(jsonarray.error);
                        }
                    }
                    catch (err) {
                        alert(err);
                        jQuery('#instawp_setting_general_save').css({'pointer-events': 'auto', 'opacity': '1'});
                    }
                }, function (XMLHttpRequest, textStatus, errorThrown) {
                    jQuery('#instawp_setting_general_save').css({'pointer-events': 'auto', 'opacity': '1'});
                    var error_message = instawp_output_ajaxerror('changing base settings', textStatus, errorThrown);
                    alert(error_message);
                });
            }
        </script>
        <?php
    }

    public function instawp_add_tab_log_ex(){
        ?>
        <a href="#" id="instawp_tab_log_ex" class="nav-tab wrap-nav-tab" onclick="switchTabs(event,'logs-page-ex')"><?php esc_html_e('Logs', 'instawp-connect'); ?></a>
        <?php
    }

    public function instawp_add_page_log_ex() {
        ?>
         <div id="logs-page-ex" class="wrap-tab-content instawp_tab_log" name="tab-logs" style="display:none;">
             <div>
                 <h2 class="nav-tab-wrapper" style="padding-bottom:0!important;">
                     <?php
                     $setting_array = array();
                     $setting_array = apply_filters('instawp_add_log_tab_page', $setting_array);
                     foreach ( $setting_array as $setting_name ) {
                         add_action('instawp_logpage_add_tab', $setting_name['tab_func'], $setting_name['index']);
                         add_action('instawp_logpage_add_page', $setting_name['page_func'], $setting_name['index']);
                     }
                     do_action('instawp_logpage_add_tab');
                     ?>
                 </h2>
                 <?php do_action('instawp_logpage_add_page'); ?>
             </div>
         </div>
        <?php
    }

    public function instawp_add_page_website_info() {
        ?>
        <div id="debug-page" class="wrap-tab-content instawp_tab_debug" name="tab-debug" style="display:none;">
            <table class="widefat">
                <div style="padding: 0 0 20px 10px;"><?php esc_html_e('There are two ways available to send us the debug information. The first one is recommended.', 'instawp-connect'); ?></div>
                <div style="padding-left: 10px;">
                    <strong><?php esc_html_e('Method 1.', 'instawp-connect'); ?></strong> <?php esc_html_e('If you have configured SMTP on your site, enter your email address and click the button below to send us the relevant information (website info and errors logs) when you are encountering errors. This will help us figure out what happened. Once the issue is resolved, we will inform you by your email address.', 'instawp-connect'); ?>
                </div>
                <div style="padding:10px 10px 0">
                    <span class="instawp-element-space-right"><?php echo esc_html__('instaWP support email:', 'instawp-connect'); ?></span><input type="text" id="instawp_support_mail" value="support@instawp.com" readonly />
                    <span class="instawp-element-space-right"><?php echo esc_html__('Your email:', 'instawp-connect'); ?></span><input type="text" id="instawp_user_mail" />
                </div>
                <div style="padding:10px 10px 0">
                    <div style="float: left;">
                        <div class="instawp-element-space-bottom instawp-text-space-right instawp-debug-text-fix" style="float: left;">
                            <?php esc_html_e('I am using:', 'instawp-connect'); ?>
                        </div>
                        <div class="instawp-element-space-bottom instawp-text-space-right" style="float: left;">
                            <select id="instawp_debug_type">
                                <option selected="selected" value="sharehost"><?php esc_html_e('share hosting', 'instawp-connect'); ?></option>
                                <option value="vps"><?php esc_html_e('VPS hosting', 'instawp-connect'); ?></option>
                            </select>
                        </div>
                        <div style="clear: both;"></div>
                    </div>
                    <div id="instawp_debug_host" style="float: left;">
                        <div class="instawp-element-space-bottom instawp-text-space-right instawp-debug-text-fix" style="float: left;">
                            <?php esc_html_e('My web hosting provider is:', 'instawp-connect'); ?>
                        </div>
                        <div class="instawp-element-space-bottom instawp-text-space-right" style="float: left;">
                            <input type="text" id="instawp_host_provider"/></div>
                        <div style="clear: both;"></div>
                    </div>
                    <div style="clear: both;"></div>
                </div>
                <div style="padding:0 10px;">
                    <textarea id="instawp_debug_comment" class="wp-editor-area" style="width:100%; height: 200px;" autocomplete="off" cols="60" placeholder="<?php esc_attr_e('Please describe your problem here.', 'instawp-connect'); ?>" ></textarea>
                </div>
                <div class="schedule-tab-block">
                    <input class="button-primary" type="submit" value="<?php esc_attr_e( 'Send Debug Information to Us', 'instawp-connect' ); ?>" onclick="instawp_click_send_debug_info();" />
                </div>
                <div style="clear:both;"></div>
                <div style="padding-left: 10px;">
                    <strong><?php esc_html_e('Method 2.', 'instawp-connect'); ?></strong> <?php esc_html_e('If you didn’t configure SMTP on your site, click the button below to download the relevant information (website info and error logs) to your PC when you are encountering some errors. Sending the files to us will help us diagnose what happened.', 'instawp-connect'); ?>
                </div>
                <div class="schedule-tab-block">
                    <input class="button-primary" id="instawp_download_website_info" type="submit" name="download-website-info" value="<?php esc_attr_e( 'Download', 'instawp-connect' ); ?>" />
                </div>
                <thead class="website-info-head">
                <tr>
                    <th class="row-title" style="min-width: 260px;"><?php esc_html_e( 'Website Info Key', 'instawp-connect' ); ?></th>
                    <th><?php esc_html_e( 'Website Info Value', 'instawp-connect' ); ?></th>
                </tr>
                </thead>
                <tbody class="instawp-websiteinfo-list" id="instawp_websiteinfo_list">
                <?php
                global $instawp_plugin;
                $website_info = $instawp_plugin->get_website_info();
                if ( ! empty($website_info['data']) ) {
                    foreach ( $website_info['data'] as $key => $value ) { ?>
                        <?php
                        $website_value = '';
                        if ( is_array($value) ) {
                            foreach ( $value as $arr_value ) {
                                if ( empty($website_value) ) {
                                    $website_value = $website_value . $arr_value;
                                } else {
                                    $website_value = $website_value . ', ' . $arr_value;
                                }
                            }
                        }
                        else {
                            if ( $value === true || $value === false ) {
                                if ( $value === true ) {
                                    $website_value = 'true';
                                }
                                else {
                                    $website_value = 'false';
                                }
                            }
                            else {
                                $website_value = $value;
                            }
                        }
                        ?>
                        <tr>
                            <td class="row-title tablelistcolumn"><label for="tablecell"><?php echo esc_html($key); ?></label></td>
                            <td class="tablelistcolumn"><?php echo esc_html($website_value); ?></td>
                        </tr>
                    <?php }
} ?>
                </tbody>
            </table>
        </div>
        <script>
            jQuery('#instawp_download_website_info').click(function(){
                instawp_download_website_info();
            });

            /**
             * Download the relevant website info and error logs to your PC for debugging purposes.
             */
            function instawp_download_website_info(){
                instawp_location_href=true;
                location.href =ajaxurl+'?_wpnonce='+instawp_ajax_object.ajax_nonce+'&action=instawp_create_debug_package';
            }

            jQuery("#instawp_debug_type").change(function()
            {
                if(jQuery(this).val()=='sharehost')
                {
                    jQuery("#instawp_debug_host").show();
                }
                else
                {
                    jQuery("#instawp_debug_host").hide();
                }
            });

            function instawp_click_send_debug_info(){
                var instawp_user_mail = jQuery('#instawp_user_mail').val();
                var server_type = jQuery('#instawp_debug_type').val();
                var host_provider = jQuery('#instawp_host_provider').val();
                var comment = jQuery('#instawp_debug_comment').val();

                var ajax_data = {
                    'action': 'instawp_send_debug_info',
                    'user_mail': instawp_user_mail,
                    'server_type':server_type,
                    'host_provider':host_provider,
                    'comment':comment
                };
                instawp_post_request(ajax_data, function (data) {
                    try {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === "success") {
                            alert("<?php esc_html_e('Send succeeded.', 'instawp-connect'); ?>");
                        }
                        else {
                            alert(jsonarray.error);
                        }
                    }
                    catch (err) {
                        alert(err);
                    }
                }, function (XMLHttpRequest, textStatus, errorThrown) {
                    var error_message = instawp_output_ajaxerror('sending debug information', textStatus, errorThrown);
                    alert(error_message);
                });
            }
        </script>
        <?php
    }

    public function instawp_add_page_log() {
        global $instawp_plugin;
        $display_log_count = array(
            0 => "10", 
            1 => "20", 
            2 => "30", 
            3 => "40", 
            4 => "50",
        );
        $max_log_diaplay = 20;
        $loglist = $instawp_plugin->get_log_list_ex();
        ?>
        <div id="logs-page" class="log-tab-content instawp_tab_log" name="tab-logs">
            <table class="wp-list-table widefat plugins">
                <thead class="log-head">
                <tr>
                    <th class="row-title"><?php esc_html_e( 'Date', 'instawp-connect' ); ?></th>
                    <th><?php esc_html_e( 'Log Type', 'instawp-connect' ); ?></th>
                    <th><?php esc_html_e( 'Log File Name', 'instawp-connect' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'instawp-connect' ); ?></th>
                </tr>
                </thead>
                <tbody class="instawp-loglist" id="instawp_loglist">
                <?php
                $html = '';
                $html = apply_filters('instawp_get_log_list', $html);
                echo wp_kses_post( $html['html'] );
                ?>
                </tbody>
            </table>
            <div style="padding-top: 10px; text-align: center;">
                <input class="button-secondary log-page" id="instawp_pre_log_page" type="submit" value="<?php esc_attr_e( ' < Pre page ', 'instawp-connect' ); ?>" />
                <div style="font-size: 12px; display: inline-block; padding-left: 10px;">
                                <span id="instawp_log_page_info" style="line-height: 35px;">
                                    <?php
                                    $current_page = 1;
                                    $max_page = ceil(sizeof($loglist['log_list']['file']) / $max_log_diaplay);
                                    if ($max_page == 0) $max_page = 1;
                                    echo esc_html( $current_page ).' / '.esc_html( $max_page );
                                    ?>
                                </span>
                </div>
                <input class="button-secondary log-page" id="instawp_next_log_page" type="submit" value="<?php esc_attr_e( ' Next page > ', 'instawp-connect' ); ?>" />
                <div style="float: right;">
                    <select name="" id="instawp_display_log_count">
                        <?php
                        foreach ( $display_log_count as $value ) {
                            if ( $value == $max_log_diaplay ) {
                                echo '<option selected="selected" value="' . esc_attr($value) . '">' . esc_html( $value ) . '</option>';
                            }
                            else {
                                echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $value ) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
        <script>
            jQuery('#instawp_display_log_count').on("change", function(){
                instawp_display_log_page();
            });

            jQuery('#instawp_pre_log_page').click(function(){
                instawp_pre_log_page();
            });

            jQuery('#instawp_next_log_page').click(function(){
                instawp_next_log_page();
            });

            function instawp_pre_log_page(){
                if(instawp_cur_log_page > 1){
                    instawp_cur_log_page--;
                }
                instawp_display_log_page();
            }

            function instawp_next_log_page(){
                var display_count = jQuery("#instawp_display_log_count option:selected").val();
                var max_pages=Math.ceil(instawp_log_count/display_count);
                if(instawp_cur_log_page < max_pages){
                    instawp_cur_log_page++;
                }
                instawp_display_log_page();
            }

            function instawp_display_log_page(){
                var display_count = jQuery("#instawp_display_log_count option:selected").val();
                var max_pages=Math.ceil(instawp_log_count/display_count);
                if(max_pages == 0) max_pages = 1;
                jQuery('#instawp_log_page_info').html(instawp_cur_log_page+ " / "+max_pages);

                var begin = (instawp_cur_log_page - 1) * display_count;
                var end = parseInt(begin) + parseInt(display_count);
                jQuery("#instawp_loglist tr").hide();
                jQuery('#instawp_loglist tr').each(function(i){
                    if (i >= begin && i < end)
                    {
                        jQuery(this).show();
                    }
                });
            }

            function instawp_retrieve_log_list()
            {
                var ajax_data = {
                    'action': 'instawp_get_log_list'
                };
                instawp_post_request(ajax_data, function(data){
                    try {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === "success") {
                            jQuery('#instawp_loglist').html("");
                            jQuery('#instawp_loglist').append(jsonarray.html);
                            instawp_log_count = jsonarray.log_count;
                            instawp_display_log_page();
                        }
                    }
                    catch(err){
                        alert(err);
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown) {
                    setTimeout(function () {
                        instawp_retrieve_log_list();
                    }, 3000);
                });
            }
        </script>
        <?php
    }

    public function instawp_add_page_read_log() {
        ?>
        <div id="log-read-page" class="wrap-tab-content instawp_tab_read_log" style="display:none;">
            <div class="postbox restore_log" id="instawp_read_log_content">
                <div></div>
            </div>
        </div>
        <?php
    }

    public function instawp_add_page_mwp() {
        ?>
        <div id="mwp-page" class="wrap-tab-content instawp_tab_mainwp" name="tab-mwp" style="display:none;">
            <div style="padding: 10px; background-color: #fff;">
                <div style="margin-bottom: 10px;">
                    <?php echo esc_html__('If you are a MainWP user, you can set up and control instaWP Backup Free and Pro for every child site directly from your MainWP dashboard, using our instaWP Backup for MainWP extension.', 'instawp-connect'); ?>
                </div>
                <div style="margin-bottom: 10px;">
                    <input type="button" class="button-primary" id="instawp_download_mainwp_extension" value="<?php esc_attr_e('Download instaWP Backup for MainWP', 'instawp-connect'); ?>" />
                </div>
                <div style="margin-bottom: 10px;">
                    <?php _esc_html__e('1. Create and download backups for a specific child site', 'instawp-connect'); ?>
                </div>
                <div style="margin-bottom: 10px;">
                    <?php esc_html__('2. Set backup schedules for all child sites', 'instawp-connect'); ?>
                </div>
                <div style="margin-bottom: 10px;">
                    <?php
                    echo esc_html__('3. Set instaWP Backup Free and Pro settings for all child sites', 'instawp-connect');
                    ?>
                </div>
                <div style="margin-bottom: 10px;">
                    <?php
                    echo esc_html__('4. Install, claim and update instaWP Backup Pro for child sites in bulk', 'instawp-connect');
                    ?>
                </div>
                <div>
                    <?php
                    echo esc_html__('5. Set up remote storage for child sites in bulk (for instaWP Backup Pro only)', 'instawp-connect');
                    ?>
                </div>
            </div>
        </div>
        <script>
            jQuery('#instawp_download_mainwp_extension').click(function(){
                var tempwindow=window.open('_blank');
                tempwindow.location='https://wordpress.org/plugins/instawp-backup-mainwp';
            });
            jQuery('#instawp_ask_for_discount').click(function(){
                var tempwindow=window.open('_blank');
                tempwindow.location='https://instawp.com//instawp-backup-for-mainwp';
            });
        </script>
        <?php
    }

   /* public function instawp_add_page_premium(){
        ?>
        <div id="premium-page" class="wrap-tab-content instawp_tab_premium" name="tab-premium" style="display: none;">
            <table class="wp-list-table widefat plugins" style="border-collapse: collapse;">
                <thead>
                <tr class="backup-list-head" style="border-bottom: 0;">
                    <th><?php esc_html__('Features', 'instawp-connect'); ?></th>
                    <th style="text-align:center;"><?php esc_html__('Blogger', 'instawp-connect'); ?></th>
                    <th style="text-align:center;"><?php esc_html__('Freelancer', 'instawp-connect'); ?></th>
                    <th style="text-align:center;"><?php esc_html__('Small Business', 'instawp-connect'); ?></th>
                    <th style="text-align:center;"><?php esc_html__('Ultimate', 'instawp-connect'); ?></th>
                </tr>
                </thead>
                <tbody class="instawp-backuplist">
                <tr style="">
                    <td>
                        <p><strong><?php _e('Domains', 'instawp-connect'); ?></strong></p>
                        <p><strong><?php _e('Backup & Migration Pro', 'instawp-connect'); ?></strong></p>
                        <p><strong><?php _e('Image Optimization Pro (Unlimited/domain)', 'instawp-connect'); ?></strong></p>
                        <p><strong><?php _e('Mulitsite Support', 'instawp-connect'); ?></strong></p>
                        <p><strong><?php _e('Staging Pro', 'instawp-connect'); ?></strong></p>
                        <p><strong><?php _e('White Label', 'instawp-connect'); ?></strong></p>
                        <p><strong><?php _e('Roles & Capabilities', 'instawp-connect'); ?></strong></p>
                    </td>
                    <td style="text-align:center;">
                        <p><span style="color: #81d742;"><?php _e('2 domains', 'instawp-connect'); ?></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #f1f1f1;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #f1f1f1;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #f1f1f1;border-radius: 50%;display: inline-block;"></span></p>
                    </td>
                    <td style="text-align:center;">
                        <p><span style="color: #81d742;"><?php _e('Up to 10 domains', 'instawp-connect'); ?></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                    </td>
                    <td style="text-align:center;">
                        <p><span style="color: #81d742;"><?php _e('Up to 50 domains', 'instawp-connect'); ?></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                    </td>
                    <td style="text-align:center;">
                        <p><span style="color: #81d742;"><?php _e('Unlimited', 'instawp-connect'); ?></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                        <p><span style="height: 12px;width: 12px;background-color: #81d742;border-radius: 50%;display: inline-block;"></span></p>
                    </td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <th><?php _e('*No credit card needed. Trial starts with the Free Trial plan with 2 sites. You can choose a plan at the end of the trial.', 'instawp-connect'); ?></th>
                    <th colspan="4" style="text-align:center;"><p style="margin-top: 6px;"><a href="https://instawp.com//pricing" class="page-title-action"><?php _e('START 14-DAY FREE TRIAL', 'instawp-connect'); ?></a></p></th>
                </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }*/
}