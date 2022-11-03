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

include_once INSTAWP_PLUGIN_DIR .'/admin/partials/class-instawp-files-list.php';
include_once INSTAWP_PLUGIN_DIR .'/admin/partials/instawp-remote-storage-page-display.php';
include_once INSTAWP_PLUGIN_DIR .'/admin/partials/instawp-settings-page-display.php';
include_once INSTAWP_PLUGIN_DIR .'/admin/partials/instawp-schedule-page-display.php';
include_once INSTAWP_PLUGIN_DIR .'/admin/partials/instawp-website-info-page-display.php';
include_once INSTAWP_PLUGIN_DIR .'/admin/partials/instawp-logs-page-display.php';
include_once INSTAWP_PLUGIN_DIR .'/admin/partials/instawp-log-read-page-display.php';

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}

global $instawp_plugin;


do_action('show_notice');

?>

<?php

$page_array = array();
$page_array = apply_filters('instawp_add_tab_page', $page_array);
foreach ( $page_array as $page_name ) {
    add_action('instawp_backuprestore_add_tab', $page_name['tab_func'], $page_name['index']);
    add_action('instawp_backuprestore_add_page', $page_name['page_func'], $page_name['index']);
}

?>

<div class="wrap">
    <h1 style="display: none;"><?php
        $plugin_display_name = 'instaWP Backup Plugin';
        $plugin_display_name = apply_filters('instawp_display_pro_name', $plugin_display_name);
        echo esc_html__('instaWP Backup Plugin', 'instawp-connect');
        ?></h1>
    <div id="instawp_backup_notice" style="display:none;">
        <?php
        if ( isset($schedule) && $schedule['enable'] == true ) {
            if ( $schedule['backup']['remote'] === 1 ) {
                $remoteslist = InstaWP_Setting::get_all_remote_options();
                $default_remote_storage = '';
                foreach ( $remoteslist['remote_selected'] as $value ) {
                    $default_remote_storage = $value;
                }
                if ( $default_remote_storage == '' ) {
                    echo '<div class="notice notice-warning is-dismissible"><p>'.esc_html__('Warning: There is no default remote storage available for the scheduled backups, please set up it first.', 'instawp-connect').'</p></div>';
                }
            }
        }
        ?>
    </div>
    <?php do_action('instawp_add_schedule_notice'); ?>
    <div id="instawp_remote_notice"></div>
</div>
<h2 class="nav-tab-wrapper instawp-custom-table-manager">
    <?php
    do_action('instawp_backuprestore_add_tab');
    ?>
</h2>
<div class="wrap" style="max-width:1720px;">
    <div id="poststuff" style="padding-top: 0;">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content">
                <div class="inside" style="margin-top:0;">
                    <?php
                    do_action('instawp_backuprestore_add_page');
                    ?>
                </div>
            </div>

            
        </div>
        <br class="clear">
    </div>
</div>

<script>
    function switchTabs(evt,contentName) {
        // Declare all variables
        var i, tabcontent, tablinks;

        // Get all elements with class="tabcontent" and hide them
        tabcontent = document.getElementsByClassName("wrap-tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Get all elements with class="wrap-nav-tab" and remove the class "active"
        tablinks = document.getElementsByClassName("wrap-nav-tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" nav-tab-active", "");
        }

        // Show the current tab, and add an "nav-tab-active" class to the button that opened the tab
        document.getElementById(contentName).style.display = "block";
        evt.currentTarget.className += " nav-tab-active";
        jQuery( document ).trigger( 'instawp-switch-tabs', contentName );
        //nav-tab-active
    }
    function switchrestoreTabs(evt,contentName) {
        // Declare all variables
        var i, tabcontent, tablinks;

        // Get all elements with class="table-list-content" and hide them
        tabcontent = document.getElementsByClassName("backup-tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Get all elements with class="table-nav-tab" and remove the class "nav-tab-active"
        tablinks = document.getElementsByClassName("backup-nav-tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" nav-tab-active", "");
        }

        // Show the current tab, and add an "storage-menu-active" class to the button that opened the tab
        document.getElementById(contentName).style.display = "block";
        evt.currentTarget.className += " nav-tab-active";
    }
    function switchlogTabs(evt,contentName) {
        // Declare all variables
        var i, tabcontent, tablinks;

        // Get all elements with class="table-list-content" and hide them
        tabcontent = document.getElementsByClassName("log-tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Get all elements with class="table-nav-tab" and remove the class "nav-tab-active"
        tablinks = document.getElementsByClassName("log-nav-tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" nav-tab-active", "");
        }

        // Show the current tab, and add an "storage-menu-active" class to the button that opened the tab
        document.getElementById(contentName).style.display = "block";
        evt.currentTarget.className += " nav-tab-active";
    }
    function switchsettingTabs(evt,contentName) {
        // Declare all variables
        var i, tabcontent, tablinks;

        // Get all elements with class="table-list-content" and hide them
        tabcontent = document.getElementsByClassName("setting-tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Get all elements with class="table-nav-tab" and remove the class "nav-tab-active"
        tablinks = document.getElementsByClassName("setting-nav-tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" nav-tab-active", "");
        }

        // Show the current tab, and add an "storage-menu-active" class to the button that opened the tab
        document.getElementById(contentName).style.display = "block";
        evt.currentTarget.className += " nav-tab-active";
    }
    function switchstorageTabs(remote_type,storage_page_id)
    {
        var i, tabcontent, tablinks,contentName;
        contentName='storage-page';
        // Get all elements with class="tabcontent" and hide them
        tabcontent = document.getElementsByClassName("wrap-tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Get all elements with class="wrap-nav-tab" and remove the class "active"
        tablinks = document.getElementsByClassName("wrap-nav-tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" nav-tab-active", "");
        }

        // Show the current tab, and add an "nav-tab-active" class to the button that opened the tab
        document.getElementById(contentName).style.display = "block";
        jQuery('#instawp_tab_remote_storage').addClass('nav-tab-active');
        jQuery( document ).trigger( 'instawp-switch-tabs', contentName );
        start_select_remote_storage(remote_type,storage_page_id);

    }

    function start_select_remote_storage(remote_type, storage_page_id)
    {
        var i, tablecontent, tablinks;
        tablinks = document.getElementsByClassName("storage-providers");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace("storage-providers-active", "");
        }
        jQuery("div[remote_type='"+remote_type+"']").addClass('storage-providers-active');

        jQuery(".storage-account-page").hide();
        jQuery("#"+storage_page_id).show();
    }

    function instawp_getrequest()
    {
        instawp_click_switch_page('wrap', instawp_page_request, false);
    }

    function instawp_task_monitor()
    {
        setTimeout(function () {
            instawp_task_monitor();
        }, 120000);

        var ajax_data = {
            'action': 'instawp_task_monitor'
        };

        instawp_post_request(ajax_data, function (data)
        {
        },function (XMLHttpRequest, textStatus, errorThrown)
        {
        });
    }

    jQuery(document).ready(function ()
    {
        instawp_getrequest();
        instawp_task_monitor();
        <?php
        $default_task_type = array();
        $default_task_type = apply_filters('instawp_get_task_type', $default_task_type);
        if ( empty($default_task_type) ) {
        ?>
        instawp_activate_cron();
        instawp_manage_task();
        <?php
        }
        ?>
       
        //switchTabs(event,'storage-page')
    });

</script>