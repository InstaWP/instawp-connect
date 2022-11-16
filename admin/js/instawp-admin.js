var task_retry_times=0;
var running_backup_taskid='';
var tmp_current_click_backupid = '';
var m_need_update=true;
var m_restore_backup_id;
var m_backup_task_id;
var m_downloading_file_name = '';
var m_downloading_id = '';
var instawp_settings_changed = false;
var instawp_cur_log_page = 1;
var instawp_completed_backup = 1;
var instawp_prepare_backup=false;
var instawp_restoring=false;
var instawp_location_href=false;
var instawp_editing_storage_id = '';
var instawp_editing_storage_type = '';
var instawp_restore_download_array;
var instawp_restore_download_index = 0;
var instawp_get_download_restore_progress_retry = 0;
var instawp_restore_timeout = false;
var instawp_restore_need_download = false;
var instawp_display_restore_backup = false;
var instawp_restore_backup_type = '';
var instawp_display_restore_check = false;
var instawp_restore_sure = false;
var is_instawp_check_staging_running = false;
var is_instawp_check_staging_compteted = false;
var instawp_resotre_is_migrate=0;
var instawp_check_staging_interval;

(function ($) {
    'use strict';

    /**
     * All of the code for your admin-facing JavaScript source
     * should reside in this file.
     *
     * Note: It has been assumed you will write jQuery code here, so the
     * $ function reference has been prepared for usage within the scope
     * of this function.
     *
     * This enables you to define handlers, for when the DOM is ready:
     *
     * $(function() {
     *
     * });
     *
     * When the window is loaded:
     *
     * $( window ).load(function() {
     *
     * });
     *
     * ...and/or other possibilities.
     *
     * Ideally, it is not considered best practise to attach more than a
     * single DOM-ready or window-load handler for a particular page.
     * Although scripts in the WordPress core, Plugins and Themes may be
     * practising this, we should strive to set a better example in our own work.
     */
    $(document).ready(function () {
        //instawp_getrequest();

        instawp_interface_flow_control();

        $('input[option=review]').click(function(){
            var name = jQuery(this).prop('name');
            instawp_add_review_info(name);
        });

        $(document).on('click', '.notice-wp-cron .notice-dismiss', function(){
            var ajax_data = {
                'action': 'instawp_hide_wp_cron_notice'
            };
            instawp_post_request(ajax_data, function(res){
            }, function(XMLHttpRequest, textStatus, errorThrown) {
            });
        });
    });
    
})(jQuery);

function instawp_popup_tour(style) {
    var popup = document.getElementById("instawp_popup_tour");
    if (popup != null) {
        popup.classList.add(style);
    }
}

window.onbeforeunload = function(e) {
    if (instawp_settings_changed) {
        if (instawp_location_href){
            instawp_location_href = false;
        }
        else {
            return 'You are leaving the page without saving your changes, any unsaved changes on the page will be lost, are you sure you want to continue?';
        }
    }
}

/**
 * Refresh the scheduled task list as regularly as a preset interval(3-minute), to retrieve and activate the scheduled cron jobs.
 */
function instawp_activate_cron(){
    var next_get_time = 3 * 60 * 1000;
    instawp_cron_task();
    setTimeout("instawp_activate_cron()", next_get_time);
    setTimeout(function(){
        m_need_update=true;
    }, 10000);
}

/**
 * Send an Ajax request
 *
 * @param ajax_data         - Data in Ajax request
 * @param callback          - A callback function when the request is succeeded
 * @param error_callback    - A callback function when the request is failed
 * @param time_out          - The timeout for Ajax request
 */
function instawp_post_request(ajax_data, callback, error_callback, time_out){
    if(typeof time_out === 'undefined')    time_out = 30000;
    ajax_data.nonce=instawp_ajax_object.ajax_nonce;
    jQuery.ajax({
        type: "post",
        url: instawp_ajax_object.ajax_url,
        data: ajax_data,
        success: function (data) {
            //console.log(ajax_data);
            callback(data);
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            error_callback(XMLHttpRequest, textStatus, errorThrown);
        },
        timeout: time_out
    });
}

/**
 * Check if there are running tasks (backup and download)
 */
function instawp_check_runningtask(){
    var ajax_data = {
        'action': 'instawp_list_tasks',
        'backup_id': tmp_current_click_backupid
    };
    if(instawp_restoring === false) {
        instawp_post_request(ajax_data, function (data) {
            setTimeout(function () {
                instawp_manage_task();
            }, 3000);
            try {
                var jsonarray = jQuery.parseJSON(data);
                if (jsonarray.success_notice_html != false) {
                    jQuery('#instawp_backup_notice').show();
                    jQuery('#instawp_backup_notice').append(jsonarray.success_notice_html);
                }
                if(jsonarray.error_notice_html != false){
                    jQuery('#instawp_backup_notice').show();
                    jQuery.each(jsonarray.error_notice_html, function (index, value) {
                        jQuery('#instawp_backup_notice').append(value.error_msg);
                    });
                }
                if(jsonarray.backuplist_html != false) {
                    jQuery('#instawp_backup_list').html('');
                    jQuery('#instawp_backup_list').append(jsonarray.backuplist_html);
                }
                var b_has_data = false;
                if (jsonarray.backup.data.length !== 0) {
                    b_has_data = true;
                    task_retry_times = 0;
                    if (jsonarray.backup.result === 'success') {
                        instawp_prepare_backup = false;
                        jQuery.each(jsonarray.backup.data, function (index, value) {
                            if (value.status.str === 'ready') {
                                jQuery('#instawp_postbox_backup_percent').html(value.progress_html);
                                m_need_update = true;
                            }

                            else if (value.status.str === 'running') {
                                console.log('running');
                                console.log(value);
                                running_backup_taskid = index;
                                instawp_control_backup_lock();
                                jQuery('#instawp_postbox_backup_percent').show();
                                jQuery('#instawp_postbox_backup_percent').html(value.progress_html);
                                m_need_update = true;
                                
                            }
                            else if (value.status.str === 'wait_resume') {
                                running_backup_taskid = index;
                                instawp_control_backup_lock();
                                jQuery('#instawp_postbox_backup_percent').show();
                                jQuery('#instawp_postbox_backup_percent').html(value.progress_html);
                                if (value.data.next_resume_time !== 'get next resume time failed.') {
                                    instawp_resume_backup(index, value.data.next_resume_time);
                                }
                                else {
                                    instawp_delete_backup_task(index);
                                }
                            }
                            else if (value.status.str === 'no_responds') {
                                running_backup_taskid = index;
                                instawp_control_backup_lock();
                                jQuery('#instawp_postbox_backup_percent').show();
                                jQuery('#instawp_postbox_backup_percent').html(value.progress_html);
                                m_need_update = true;
                            }
                            else if (value.status.str === 'completed') {
                                jQuery('#instawp_postbox_backup_percent').html(value.progress_html);
                                instawp_control_backup_unlock();
                                jQuery('#instawp_postbox_backup_percent').hide();
                                jQuery('#instawp_last_backup_msg').html(jsonarray.last_msg_html);
                                jQuery('#instawp_loglist').html("");
                                jQuery('#instawp_loglist').append(jsonarray.log_html);
                                instawp_log_count = jsonarray.log_count;
                                    //instawp_display_log_page();
                                jQuery('#instawp-wizard-screen-3').removeClass('instawp-show');
                                jQuery('#instawp-wizard-screen-4').addClass('instawp-show');
                                running_backup_taskid = '';
                                m_backup_task_id = '';
                                m_need_update = true;
                                console.log('completed');
                                console.log(value);
                                
                                if( is_instawp_check_staging_running == false ) {
                                    console.log('is_instawp_check_staging_running');
                                        //instawp_check_staging()
                                        // //instawp_check_staging_interval = setTimeout(instawp_check_staging, 15000);
                                        // console.log(is_instawp_check_staging_running);
                                        // is_instawp_check_staging_running = true;
                                    instawp_check_staging_interval = setInterval(instawp_check_staging, 30000);
                                    is_instawp_check_staging_running = true;
                                }
                                if( is_instawp_check_staging_compteted == true ) {
                                    console.log('is_instawp_check_staging_compteted');
                                    clearInterval( instawp_check_staging_interval );
                                }
                                
                                    //
                                    //console.log('start checking staging site');
                            }
                            else if (value.status.str === 'upload_completed') {
                                jQuery('#instawp_postbox_backup_percent').html(value.progress_html);
                                instawp_control_backup_unlock();
                                jQuery('#instawp_postbox_backup_percent').hide();
                                jQuery('#instawp_last_backup_msg').html(jsonarray.last_msg_html);
                                jQuery('#instawp_loglist').html("");
                                jQuery('#instawp_loglist').append(jsonarray.log_html);
                                instawp_log_count = jsonarray.log_count;
                                    //instawp_display_log_page();
                                running_backup_taskid = '';
                                m_backup_task_id = '';
                                m_need_update = true;
                                console.log('upload_completed');
                                console.log(value);
                            }
                            else if (value.status.str === 'error') {
                                jQuery('#instawp_postbox_backup_percent').html(value.progress_html);
                                instawp_control_backup_unlock();
                                jQuery('#instawp_postbox_backup_percent').hide();
                                jQuery('#instawp_last_backup_msg').html(jsonarray.last_msg_html);
                                jQuery('#instawp_loglist').html("");
                                jQuery('#instawp_loglist').append(jsonarray.log_html);
                                running_backup_taskid = '';
                                m_backup_task_id = '';
                                m_need_update = true;
                            }
                        });
                    }
                }
                else
                {
                    if(running_backup_taskid !== '')
                    {
                        jQuery('#instawp_backup_cancel_btn').css({'pointer-events': 'auto', 'opacity': '1'});
                        jQuery('#instawp_backup_log_btn').css({'pointer-events': 'auto', 'opacity': '1'});
                        instawp_control_backup_unlock();
                        jQuery('#instawp_postbox_backup_percent').hide();
                        instawp_retrieve_backup_list();
                        instawp_retrieve_last_backup_message();
                        instawp_retrieve_log_list();
                        running_backup_taskid='';
                    }
                }
                    /*if (jsonarray.download.length !== 0) {
                        if(jsonarray.download.result === 'success') {
                            b_has_data = true;
                            task_retry_times = 0;
                            var i = 0;
                            var file_name = '';
                            jQuery('#instawp_file_part_' + tmp_current_click_backupid).html("");
                            var b_download_finish = false;
                            jQuery.each(jsonarray.download.files, function (index, value) {
                                i++;
                                file_name = index;
                                var progress = '0%';
                                if (value.status === 'need_download') {
                                    if (m_downloading_file_name === file_name) {
                                        m_need_update = true;
                                    }
                                    jQuery('#instawp_file_part_' + tmp_current_click_backupid).append(value.html);
                                    //b_download_finish=true;
                                }
                                else if (value.status === 'running') {
                                    if (m_downloading_file_name === file_name) {
                                        instawp_lock_download(tmp_current_click_backupid);
                                    }
                                    m_need_update = true;
                                    jQuery('#instawp_file_part_' + tmp_current_click_backupid).append(value.html);
                                    b_download_finish = false;
                                }
                                else if (value.status === 'completed') {
                                    if (m_downloading_file_name === file_name) {
                                        instawp_unlock_download(tmp_current_click_backupid);
                                        m_downloading_id = '';
                                        m_downloading_file_name = '';
                                    }
                                    jQuery('#instawp_file_part_' + tmp_current_click_backupid).append(value.html);
                                    b_download_finish = true;
                                }
                                else if (value.status === 'error') {
                                    if (m_downloading_file_name === file_name) {
                                        instawp_unlock_download(tmp_current_click_backupid);
                                        m_downloading_id = '';
                                        m_downloading_file_name = '';
                                    }
                                    alert(value.error);
                                    jQuery('#instawp_file_part_' + tmp_current_click_backupid).append(value.html);
                                    b_download_finish = true;
                                }
                                else if (value.status === 'timeout') {
                                    if (m_downloading_file_name === file_name) {
                                        instawp_unlock_download(tmp_current_click_backupid);
                                        m_downloading_id = '';
                                        m_downloading_file_name = '';
                                    }
                                    alert('Download timeout, please retry.');
                                    jQuery('#instawp_file_part_' + tmp_current_click_backupid).append(value.html);
                                    b_download_finish = true;
                                }
                            });
                            jQuery('#instawp_file_part_' + tmp_current_click_backupid).append(jsonarray.download.place_html);
                            if (b_download_finish == true) {
                                tmp_current_click_backupid = '';
                            }
                        }
                        else{
                            b_has_data = true;
                            alert(jsonarray.download.error);
                        }
                    }*/
                    if (!b_has_data) {
                        task_retry_times++;
                        if (task_retry_times < 5) {
                            m_need_update = true;
                        }
                    }
            }
            catch(err){
                alert(err);
            }
        }, function (XMLHttpRequest, textStatus, errorThrown)
        {
            task_retry_times++;
            if (task_retry_times < 5)
            {
                setTimeout(function () {
                    m_need_update = true;
                    instawp_manage_task();
                }, 3000);
            }
        });
    }
}

/**
 * This function will show the log on a text box.
 *
 * @param data - The log message returned by server
 */
function instawp_show_log(data, content_id){
    jQuery('#'+content_id).html("");
    try {
        var jsonarray = jQuery.parseJSON(data);
        if (jsonarray.result === "success") {
            var log_data = jsonarray.data;
            while (log_data.indexOf('\n') >= 0) {
                var iLength = log_data.indexOf('\n');
                var log = log_data.substring(0, iLength);
                log_data = log_data.substring(iLength + 1);
                var insert_log = "<div style=\"clear:both;\">" + log + "</div>";
                jQuery('#'+content_id).append(insert_log);
            }
        }
        else if (jsonarray.result === "failed") {
            jQuery('#'+content_id).html(jsonarray.error);
        }
    }
    catch(err){
        alert(err);
        var div = "Reading the log failed. Please try again.";
        jQuery('#'+content_id).html(div);
    }
}

/**
 * Resume the backup task automatically in 1 minute in a timeout situation
 *
 * @param backup_id         - A unique ID for a backup
 * @param next_resume_time  - A time interval for resuming next timeout backup task
 */
function instawp_resume_backup(backup_id, next_resume_time){
    if(next_resume_time < 0){
        next_resume_time = 0;
    }
    next_resume_time = next_resume_time * 1000;
    setTimeout("instawp_cron_task()", next_resume_time);
    setTimeout(function(){
        task_retry_times = 0;
        m_need_update=true;
    }, next_resume_time);
}

/**
 * This function will retrieve the last backup message
 */
function instawp_retrieve_last_backup_message(){
    var ajax_data={
        'action': 'instawp_get_last_backup'
    };
    instawp_post_request(ajax_data, function(data){
        try {
            var jsonarray = jQuery.parseJSON(data);
            jQuery('#instawp_last_backup_msg').html(jsonarray.data);
        }
        catch(err){
            alert(err);
        }
    }, function(XMLHttpRequest, textStatus, errorThrown) {
        var error_message = instawp_output_ajaxerror('retrieving the last backup log', textStatus, errorThrown);
        jQuery('#instawp_last_backup_msg').html(error_message);
    });
}

/**
 * This function will control interface flow.
 */
function instawp_interface_flow_control(){
    jQuery('#instawp_general_email_enable').click(function(){
        if(jQuery('#instawp_general_email_enable').prop('checked') === true){
            jQuery('#instawp_general_email_setting').show();

        }
        else{
            jQuery('#instawp_general_email_setting').hide();
        }
    });

    jQuery("input[name='schedule-backup-files']").bind("click",function(){
        if(jQuery(this).val() === "custom"){
            jQuery('#instawp_choosed_folders').show();
            if(jQuery("input[name='instawp-schedule-custom-folders'][value='other']").prop('checked')){
                jQuery('#instawp_file_tree_browser').show();
            }
            else{
                jQuery('#instawp_file_tree_browser').hide();
            }
        }
        else{
            jQuery('#instawp_choosed_folders').hide();
            jQuery('#instawp_file_tree_browser').hide();
        }
    });

    jQuery("input[name='instawp-schedule-custom-folders']").bind("click",function(){
        if(jQuery("input[name='instawp-schedule-custom-folders'][value='other']").prop('checked')){
            jQuery('#instawp_file_tree_browser').show();
        }
        else{
            jQuery('#instawp_file_tree_browser').hide();
        }
    });

    jQuery('#settings-page input[type=checkbox]:not([option=junk-files])').on("change", function(){
        instawp_settings_changed = true;
    });

    jQuery('#settings-page input[type=radio]').on("change", function(){
        instawp_settings_changed = true;
    });

    jQuery('#settings-page input[type=text]').on("keyup", function(){
        instawp_settings_changed = true;
    });

    /*jQuery("#instawp_storage_account_block input:not([type=checkbox])").on("keyup", function(){
        instawp_settings_changed = true;
    });*/

    /*jQuery('#instawp_storage_account_block input[type=checkbox]').on("change", function(){
        instawp_settings_changed = true;
    });*/

    jQuery('input:radio[option=restore]').click(function() {
        jQuery('input:radio[option=restore]').each(function () {
            if (jQuery(this).prop('checked')) {
                jQuery('#instawp_restore_btn').css({'pointer-events': 'auto', 'opacity': '1'});
            }
        });
    });
}

/**
 * Manage backup and download tasks. Retrieve the data every 3 seconds for checking if the backup or download tasks exist or not.
 */
function instawp_manage_task() {
    console.log("instawp manage task call");
    if(m_need_update === true){
        //console.log(m_need_update);
        m_need_update = false;
        instawp_check_runningtask();
        console.log('Mange Task IF');
        console.count('Mange Task IF');
    }
    else{
        //console.log(m_need_update);
        console.log('Mange Task ELSE');
        console.count('Mange Task ELSE');
        setTimeout(function(){
            instawp_manage_task();
        }, 3000);
    }
}

function instawp_add_notice(notice_action, notice_type, notice_msg){
    var notice_id="";
    var tmp_notice_msg = "";
    if(notice_type === "Warning"){
        tmp_notice_msg = "Warning: " + notice_msg;
    }
    else if(notice_type === "Error"){
        tmp_notice_msg = "Error: " + notice_msg;
    }
    else if(notice_type === "Success"){
        tmp_notice_msg = "Success: " + notice_msg;
    }
    else if(notice_type === "Info"){
        tmp_notice_msg = notice_msg;
    }
    switch(notice_action){
    case "Backup":
        notice_id="instawp_backup_notice";
        break;
    }
    var bfind = false;
    $div = jQuery('#'+notice_id).children('div').children('p');
    $div.each(function (index, value) {
        if(notice_action === "Backup" && notice_type === "Success"){
            bfind = false;
            return false;
        }
        if (value.innerHTML === tmp_notice_msg) {
            bfind = true;
            return false;
        }
    });
    if (bfind === false) {
        jQuery('#'+notice_id).show();
        var div = '';
        if(notice_type === "Warning"){
            div = "<div class='notice notice-warning is-dismissible inline'><p>" + instawplion.warning + notice_msg + "</p>" +
            "<button type='button' class='notice-dismiss' onclick='click_dismiss_notice(this);'>" +
            "<span class='screen-reader-text'>Dismiss this notice.</span>" +
            "</button>" +
            "</div>";
        }
        else if(notice_type === "Error"){
            div = "<div class=\"notice notice-error inline\"><p>" + instawplion.error + notice_msg + "</p></div>";
        }
        else if(notice_type === "Success"){
            instawp_clear_notice('instawp_backup_notice');
            jQuery('#instawp_backup_notice').show();
            var success_msg = instawp_completed_backup + " backup tasks have been completed.";
            div = "<div class='notice notice-success is-dismissible inline'><p>" + success_msg + "</p>" +
            "<button type='button' class='notice-dismiss' onclick='click_dismiss_notice(this);'>" +
            "<span class='screen-reader-text'>Dismiss this notice.</span>" +
            "</button>" +
            "</div>";
            instawp_completed_backup++;
        }
        else if(notice_type === "Info"){
            div = "<div class='notice notice-info is-dismissible inline'><p>" + notice_msg + "</p>" +
            "<button type='button' class='notice-dismiss' onclick='click_dismiss_notice(this);'>" +
            "<span class='screen-reader-text'>Dismiss this notice.</span>" +
            "</button>" +
            "</div>";
        }
        jQuery('#'+notice_id).append(div);
    }
}

function click_dismiss_notice(obj){
    instawp_completed_backup = 1;
    jQuery(obj).parent().remove();
}

function instawp_cron_task(){
    jQuery.get(instawp_siteurl+'/wp-cron.php');
}

function instawp_clear_notice(notice_id){
    var t = document.getElementById(notice_id);
    if(t !== null)
    {
        var oDiv = t.getElementsByTagName("div");
        var count = oDiv.length;
        for (count; count > 0; count--) {
            var i = count - 1;
            oDiv[i].parentNode.removeChild(oDiv[i]);
        }
    }
    jQuery('#'+notice_id).hide();
}

function instawp_click_switch_page(tab, type, scroll)
{
    jQuery('.'+tab+'-tab-content:not(.' + type + ')').hide();
    jQuery('.'+tab+'-tab-content.' + type).show();
    jQuery('.'+tab+'-nav-tab:not(#' + type + ')').removeClass('nav-tab-active');
    jQuery('.'+tab+'-nav-tab#' + type).addClass('nav-tab-active');
    if(scroll == true){
        var top = jQuery('#'+type).offset().top-jQuery('#'+type).height();
        jQuery('html, body').animate({scrollTop:top}, 'slow');
    }
}

function instawp_close_tab(event, hide_tab, type, show_tab){
    event.stopPropagation();
    jQuery('#'+hide_tab).hide();
    if(hide_tab === 'instawp_tab_mainwp'){
        instawp_hide_mainwp_tab_page();
    }
    instawp_click_switch_page(type, show_tab, true);
}

function instawp_hide_mainwp_tab_page(){
    var ajax_data = {
        'action': 'instawp_hide_mainwp_tab_page'
    };
    instawp_post_request(ajax_data, function(res){
    }, function(XMLHttpRequest, textStatus, errorThrown) {
    });
}

/**
 * Output ajax error in a standard format.
 *
 * @param action        - The specific operation
 * @param textStatus    - The textual status message returned by the server
 * @param errorThrown   - The error message thrown by server
 *
 * @returns {string}
 */
function instawp_output_ajaxerror(action, textStatus, errorThrown){
    action = 'trying to establish communication with your server';
    var error_msg = "instawp_request: "+ textStatus + "(" + errorThrown + "): an error occurred when " + action + ". " +
    "This error may be request not reaching or server not responding. Please try again later.";
        //"This error could be caused by an unstable internet connection. Please try again later.";
    return error_msg;
}

function instawp_add_review_info(review){
    var ajax_data={
        'action': 'instawp_need_review',
        'review': review
    };
    jQuery('#instawp_notice_rate').hide();
    instawp_post_request(ajax_data, function(res){
        if(typeof res != 'undefined' && res != ''){
            var tempwindow=window.open('_blank');
            tempwindow.location=res;
        }
    }, function(XMLHttpRequest, textStatus, errorThrown) {
    });
}

function instawp_click_amazons3_notice(){
    var ajax_data={
        'action': 'instawp_amazons3_notice'
    };
    jQuery('#instawp_amazons3_notice').hide();
    instawp_post_request(ajax_data, function(res){
    }, function(XMLHttpRequest, textStatus, errorThrown) {
    });
}

function instawp_ajax_data_transfer(data_type){
    var json = {};
    jQuery('input:checkbox[option='+data_type+']').each(function() {
        var value = '0';
        var key = jQuery(this).prop('name');
        if(jQuery(this).prop('checked')) {
            value = '1';
        }
        else {
            value = '0';
        }
        json[key]=value;
    });
    jQuery('input:radio[option='+data_type+']').each(function() {
        if(jQuery(this).prop('checked'))
        {
            var key = jQuery(this).prop('name');
            var value = jQuery(this).prop('value');
            json[key]=value;
        }
    });
    jQuery('input:text[option='+data_type+']').each(function(){
        var obj = {};
        var key = jQuery(this).prop('name');
        var value = jQuery(this).val();
        json[key]=value;
    });
    jQuery('input:password[option='+data_type+']').each(function(){
        var obj = {};
        var key = jQuery(this).prop('name');
        var value = jQuery(this).val();
        json[key]=value;
    });
    jQuery('select[option='+data_type+']').each(function(){
        var obj = {};
        var key = jQuery(this).prop('name');
        var value = jQuery(this).val();
        json[key]=value;
    });
    return JSON.stringify(json);
}

jQuery(document).on("click","#instawp_backup_cancel_btn",function(){

    instawp_cancel_backup();
});
function instawp_cancel_backup(){

    var ajax_data= {
        'action': 'instawp_backup_cancel'
                //'task_id': running_backup_taskid
    };
    jQuery('#instawp_backup_cancel_btn').css({'pointer-events': 'none', 'opacity': '0.4'});
    instawp_post_request(ajax_data, function(data){
        try {
            var jsonarray = jQuery.parseJSON(data);
            jQuery('#instawp_current_doing').html(jsonarray.msg);
        }
        catch(err){
            alert(err);
        }
    }, function(XMLHttpRequest, textStatus, errorThrown) {
        jQuery('#instawp_backup_cancel_btn').css({'pointer-events': 'auto', 'opacity': '1'});
        var error_message = instawp_output_ajaxerror('cancelling the backup', textStatus, errorThrown);
        instawp_add_notice('Backup', 'Error', error_message);
    });
}




//var instawp_check_staging_interval = setInterval(instawp_check_staging, 9000);
//instawp_check_staging();
//clearInterval(instawp_check_staging_interval);
function instawp_check_staging(){
    console.log("ON 770 ---> ");

    is_instawp_check_staging_running = true;
    console.log( 'instawp_check_staging call');
    var ajax_data= {
        'action': 'instawp_check_staging'
        //'task_id': running_backup_taskid
    };
    
    instawp_post_request(ajax_data, function(data){
        try {
            console.log(JSON.parse(data));
            var jsonarray = JSON.parse(data);
            
            if (jsonarray === null) {
                //instawp_check_staging_interval = setTimeout(instawp_check_staging, 15000);
                //clearInterval(instawp_check_staging_interval);
                console.log(jsonarray);
                
            }
            else {
                console.log("ON 790 ---> " , jsonarray);
                console.log("ON 791 ---> " , jsonarray.status);
                if( jsonarray.status == 1 ) {
                    console.log(jsonarray);
                    console.log('jsonarray.status == 1');    
                    is_instawp_check_staging_compteted = true;
                    jQuery('#instawp_site_url a').html(jsonarray.data.wp[0].site_name); 
                    jQuery('#instawp_site_url a').attr('href',jsonarray.data.wp[0].wp_admin_url); 
                    jQuery('#instawp_admin_url a').html(jsonarray.data.wp[0].wp_admin_url);
                    jQuery('#instawp_admin_url a').attr('href',jsonarray.data.wp[0].wp_admin_url);
                    jQuery('#instawp_user_name span').html(jsonarray.data.wp[0].wp_username); 
                    jQuery('#instawp_password span').html(jsonarray.data.wp[0].wp_password);  

                    var admin_url = instawp_ajax_object.cloud_url;
                    //var admin_url = 'https://s.instawp.io/';
                    
                    var auto_login_hash = jsonarray.data.wp[0].auto_login_hash;
                    var auto_login_url = admin_url + 'wordpress-auto-login?site='+auto_login_hash;
                    jQuery('.instawp-site-details-wrapper .login-btn #instawp_autologin_quick_access').attr('href',auto_login_url); 

                    var connect_page = instawp_ajax_object.plugin_connect_url;

                    console.log("connect_page" , connect_page);
                    jQuery('.instawp-site-details-wrapper .login-btn #instawp_startover_quick_access').attr('href',connect_page); 

                    //clearInterval(instawp_check_staging_interval);
                    clearInterval(instawp_check_staging_interval);
                    jQuery('#site-details-progress').hide();
                    jQuery('.instawp-site-details-wrapper .site-details').removeClass('instawp-display-none');
                    jQuery('.instawp-site-details-wrapper .instawp-stage-links').removeClass('instawp-display-none');
                    jQuery('.instawp-site-details-wrapper .instawp-wizard-btn-wrap').removeClass('instawp-display-none');
                    
                    /* Hide Computer Image on Last Screen Code Start */
                    jQuery('.instawp-wizard-container.wizard-screen-4.instawp-show .main-img').addClass('instawp-display-none');
                    /* Hide Computer Image on Last Screen Code End */
                    
                    console.log("DISPLAYED INFO, NOW USER CAN RELOAD");

                    // delete unused zip from plugin after success or failed creating site
                   
                    jQuery.ajax({
                        method: 'post',
                        url: instawp_ajax_object.ajax_url,
                        data: {
                            action: 'instawp_logger',
                            n: instawp_ajax_object.nlogger,
                            l: 1,
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            console.log(errorThrown);                             
                        },success: function (response) {
                            console.log("-- Called The delete option -- ");
                            console.log(response);
                        }
                    });
                }
            }
            console.log("ON 818 ---> ");
            
            
            
                // 
                // jQuery('#instawp_site_url a').attr('href',jsonarray.data.wp[0].site_name); 
                // jQuery('#instawp_admin_url a').html(jsonarray.data.wp[0].wp_admin_url) 
                // jQuery('#instawp_admin_url a').attr('href',jsonarray.data.wp[0].wp_admin_url); 
                // jQuery('#instawp_user_name span').html(jsonarray.data.wp[0].wp_username) 
                // jQuery('#instawp_password span').html(jsonarray.data.wp[0].wp_password) 
                // var admin_url = jsonarray.data.wp[0].wp_admin_url;
                // admin_url = admin_url.replace('wp-admin','');
                // var auto_login_hash = jsonarray.data.wp[0].auto_login_hash;
                // var auto_login_url = admin_url + 'wordpress-auto-login?site='+auto_login_hash;
                // jQuery('.instawp-site-details-wrapper .login-btn a').attr('href',auto_login_url); 
                // jQuery('#site-details-progress').hide();
                //clearInterval(instawp_check_staging_interval);
            
            
            
            //clearInterval(instawp_check_staging_interval);

        }
        catch(err){
            //clearInterval(instawp_check_staging_interval);
            console.log(err);
            //alert(err);
        }
    }, function(XMLHttpRequest, textStatus, errorThrown) {
        // jQuery('#instawp_backup_cancel_btn').css({'pointer-events': 'auto', 'opacity': '1'});
        // var error_message = instawp_output_ajaxerror('cancelling the backup', textStatus, errorThrown);
        // instawp_add_notice('Backup', 'Error', error_message);
    });
}
