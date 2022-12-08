<?php
/**
 * @link              https://instawp.com/
 * @since             1.0
 * @package           instawp
 *
 * @wordpress-plugin
 * Plugin Name:       InstaWP Connect
 * Description:       Create staging sites with your InstaWP account
 * Version:           0.0.6
 * Author:            InstaWP Team
 * Author URI:        https://instawp.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/copyleft/gpl.html
 * Text Domain:       instawp-connect
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'INSTAWP_PLUGIN_VERSION', '1.0' );
//
define('INSTAWP_RESTORE_INIT','init');
define('INSTAWP_RESTORE_READY','ready');
define('INSTAWP_RESTORE_COMPLETED','completed');
define('INSTAWP_RESTORE_RUNNING','running');
define('INSTAWP_RESTORE_ERROR','error');
define('INSTAWP_RESTORE_WAIT','wait');
define('INSTAWP_RESTORE_TIMEOUT',180);

define('INSTAWP_PLUGIN_SLUG','instawp-connect');
define('INSTAWP_PLUGIN_NAME',plugin_basename(__FILE__));
define('INSTAWP_PLUGIN_URL',plugins_url('',__FILE__));
define('INSTAWP_PLUGIN_DIR',dirname(__FILE__));
define('INSTAWP_PLUGIN_DIR_URL',plugin_dir_url(__FILE__).'admin/');
define('INSTAWP_PLUGIN_IMAGES_URL',INSTAWP_PLUGIN_URL.'/admin/partials/images/');
//We set a long enough default execution time (10 min) to ensure that the backup process can be completed.
define('INSTAWP_MAX_EXECUTION_TIME',1800);
define('INSTAWP_RESTORE_MAX_EXECUTION_TIME', 1800);
define('INSTAWP_MEMORY_LIMIT','256M');
define('INSTAWP_RESTORE_MEMORY_LIMIT','256M');
define('INSTAWP_MIGRATE_SIZE', '2048');
//If the server uses fastcgi then default execution time should be set to 2 min for more efficient.
define('INSTAWP_MAX_EXECUTION_TIME_FCGI',180);
//Default number of reserved backups
define('INSTAWP_MAX_BACKUP_COUNT',7);
define('INSTAWP_DEFAULT_BACKUP_COUNT',3);
define('INSTAWP_DEFAULT_COMPRESS_TYPE','zip');
//Max zip file size.
define('INSTAWP_DEFAULT_MAX_FILE_SIZE',200);
//Instruct PclZip to use all the time temporary files to create the zip archive or not.The default value is 1.
define('INSTAWP_DEFAULT_USE_TEMP',1);
//Instruct PclZip to use temporary files for files with size greater than.The default value is 16M.
define('INSTAWP_DEFAULT_USE_TEMP_SIZE',16);
//Exclude the files which is larger than.The default value is 0 means unlimited.
define('INSTAWP_DEFAULT_EXCLUDE_FILE_SIZE',0);
//Add a file in an archive without compressing the file.The default value is 200.
define('INSTAWP_DEFAULT_NO_COMPRESS',true);
//Backup save folder under WP_CONTENT_DIR
define('INSTAWP_DEFAULT_BACKUP_DIR','instawpbackups');
//Log save folder under WP_CONTENT_DIR
define('INSTAWP_DEFAULT_LOG_DIR','instawpbackups'.DIRECTORY_SEPARATOR.'instawp_log');
//Old files folder under INSTAWP_DEFAULT_BACKUP_DIR
define('INSTAWP_DEFAULT_ROLLBACK_DIR','instawp-old-files');
//
define('INSTAWP_DEFAULT_ADMIN_BAR', true);
define('INSTAWP_DEFAULT_TAB_MENU', true);
define('INSTAWP_DEFAULT_DOMAIN_INCLUDE', false);
//
define('INSTAWP_DEFAULT_ESTIMATE_BACKUP', true);
//Specify the folder and database to be backed up
define('INSTAWP_DEFAULT_SUBPACKAGE_PLUGIN_UPLOAD', false);

//define schedule hooks
define('INSTAWP_MAIN_SCHEDULE_EVENT','instawp_main_schedule_event');
define('INSTAWP_RESUME_SCHEDULE_EVENT','instawp_resume_schedule_event');
define('INSTAWP_CLEAN_BACKING_UP_DATA_EVENT','instawp_clean_backing_up_data_event');
define('INSTAWP_TASK_MONITOR_EVENT','instawp_task_monitor_event');
define('INSTAWP_CLEAN_BACKUP_RECORD_EVENT','instawp_clean_backup_record_event');
//backup resume retry times
define('INSTAWP_RESUME_RETRY_TIMES',6);
define('INSTAWP_RESUME_INTERVAL',60);

define('INSTAWP_REMOTE_CONNECT_RETRY_TIMES','3');
define('INSTAWP_REMOTE_CONNECT_RETRY_INTERVAL','3');

define('INSTAWP_PACK_SIZE',1 << 20);

define('INSTAWP_SUCCESS','success');
define('INSTAWP_FAILED','failed');
define('INSTAWP_UPLOAD_TO_CLOUD', true);
define('INSTAWP_API_URL','/api/v1');
@ini_set('memory_limit', '1024M');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
//when active plugin redirect plugin page.


function instawp_plugin_activate() {
    // Set default option
    InstaWP_Setting::set_api_domain();
    error_log("Settled on acivation");
    
    if ( get_option('permalink_structure') == '' ) {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure('/%postname%/');
        $wp_rewrite->flush_rules();
    }
    instawp_create_table();
    add_option('instawp_do_activation_redirect', true);
}

/*Deactivate Hook Handle*/
function instawp_plugin_deactivate(){
    /*heartbeat*/
    if ( wp_get_schedule('instwp_handle_heartbeat_cron_action') ) {
        wp_clear_scheduled_hook('instwp_handle_heartbeat_cron_action');
        $timestamp = wp_next_scheduled('instwp_handle_heartbeat_cron_action');
        wp_unschedule_event($timestamp,'instwp_handle_heartbeat_cron_action');
    }
}

function instawp_init_plugin_redirect() {
    if ( get_option('instawp_do_activation_redirect', false) ) {
        delete_option('instawp_do_activation_redirect');

        $active_plugins = get_option('active_plugins');
        if ( ! function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        $plugins = get_plugins();
        $pro_instawp_slug = 'instawp-backup-pro/instawp-backup-pro.php';
        $b_redirect_pro = false;
        if ( ! empty($plugins) ) {
            if ( isset($plugins[ $pro_instawp_slug ]) ) {
                if ( in_array($pro_instawp_slug, $active_plugins) ) {
                    $b_redirect_pro = true;
                }
            }
        }

        if ( $b_redirect_pro ) {
            $url = apply_filters('instawp_backup_activate_redirect_url','admin.php?page=instawp-dashboard');
            if ( is_multisite() ) {
                wp_redirect(network_admin_url().$url);
            }
            else {
                wp_redirect(admin_url().$url);
            }
        }
        else {
            $url = apply_filters('instawp_backup_activate_redirect_url','admin.php?page=instawp-connect');
            if ( is_multisite() ) {
                wp_redirect(network_admin_url().$url);
            }
            else {
                wp_redirect(admin_url().$url);
            }
        }
    }
}
register_activation_hook(__FILE__, 'instawp_plugin_activate');
register_deactivation_hook(__FILE__, 'instawp_plugin_deactivate');
add_action('admin_init', 'instawp_init_plugin_redirect');

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0
 */
if ( isset($instawp_plugin) && is_a($instawp_plugin,'instaWP') ) {
    return ;
}

require plugin_dir_path( __FILE__ ) . 'includes/class-instawp.php';

function run_instawp() {
    $instawp_plugin = new instaWP();
    $GLOBALS['instawp_plugin'] = $instawp_plugin;
}
run_instawp();

/*
* Database Tables for 'InstaWP Connect'
*/
function instawp_create_table(){
    global $wpdb;
    $sql = array();
    $event_change_table = $wpdb->prefix . "change_event";
    if( $wpdb->get_var("show tables like '". $event_change_table . "'") !== $event_change_table ) { 
        $sql[] = "CREATE TABLE ". $event_change_table . "     (
        id int(20) NOT NULL AUTO_INCREMENT,
        event_name varchar(128) NOT NULL,
        event_slug varchar(128) NOT NULL,
        event_type varchar(128) NOT NULL,
        source_id int(20) NOT NULL,
        title text NOT NULL,
        details longtext NOT NULL,
        user_id int(20) NOT NULL,
        date datetime NOT NULL,
        prod varchar(128) NOT NULL,
        PRIMARY KEY  (id)
        ) ";
    }

    if ( !empty($sql) ) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-db.php';
add_action( 'admin_enqueue_scripts', 'enqueueScriptsAssets' );
//add_action( 'admin_print_styles', 'utm_user_scripts' );
/**
 * Enqueue my scripts and assets.
 *
 * @param $hook
 */
function enqueueScriptsAssets( $hook ) {
    wp_enqueue_style( 'change-event-css', plugins_url( '/admin/css/instaWp-change-event.css', __FILE__ ) );
	wp_enqueue_script(
		'ajax_script',
		plugins_url( '/admin/js/instaWp-change-event.js', __FILE__ ),
		array( 'jquery' ),
		'1.0.0',
		true
	);
	wp_localize_script(
		'ajax_script',
		'ajax_obj',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'instaWp_change_event' ),
            'plugin_images_url'    => INSTAWP_PLUGIN_IMAGES_URL,
		)
	);
}

add_action( "wp_ajax_sync_action", "sync_data_ajax" );
add_action( "wp_ajax_nopriv_sync_action", "sync_data_ajax" );
function sync_data_ajax(){
    if(isset($_POST['sync_id'])){
        $sync_id = $_POST['sync_id'];
        $InstaWP_db = new InstaWP_DB();
        $InstaWP_tb = new InstaWP_TB();
        $tables = $InstaWP_tb->tb();
        $rel = $InstaWP_db->getRowById($tables['ch_table'],$sync_id);
        echo json_encode($rel);
    }
    wp_die();
}