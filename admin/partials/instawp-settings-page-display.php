<?php

function instawp_general_settings() {
    $general_setting = InstaWP_Setting::get_setting(true, "");
    $display_backup_count = $general_setting['options']['instawp_common_setting']['max_backup_count'];
    $display_backup_count = intval($display_backup_count);
    if ( $display_backup_count > 7 ) {
        $display_backup_count = 7;
    }
    if ( $general_setting['options']['instawp_common_setting']['estimate_backup'] ) {
        $instawp_setting_estimate_backup = 'checked';
    }
    else {
        $instawp_setting_estimate_backup = '';
    }
    /*if(!isset($general_setting['options']['instawp_common_setting']['show_tab_menu'])){
        $instawp_show_tab_menu='checked';
    }
    else {
        if ($general_setting['options']['instawp_common_setting']['show_tab_menu']) {
            $instawp_show_tab_menu = 'checked';
        } else {
            $instawp_show_tab_menu = '';
        }
    }*/
    if ( ! isset($general_setting['options']['instawp_common_setting']['show_admin_bar']) ) {
        $show_admin_bar = 'checked';
    }
    else {
        if ( $general_setting['options']['instawp_common_setting']['show_admin_bar'] ) {
            $show_admin_bar = 'checked';
        }
        else {
            $show_admin_bar = '';
        }
    }
    if ( ! isset($general_setting['options']['instawp_common_setting']['domain_include']) ) {
        $instawp_domain_include = 'checked';
    }
    else {
        if ( $general_setting['options']['instawp_common_setting']['domain_include'] ) {
            $instawp_domain_include = 'checked';
        }
        else {
            $instawp_domain_include = '';
        }
    }
    if ( ! isset($general_setting['options']['instawp_common_setting']['ismerge']) ) {
        $instawp_ismerge = 'checked';
    }
    else {
        if ( $general_setting['options']['instawp_common_setting']['ismerge'] == '1' ) {
            $instawp_ismerge = 'checked';
        }
        else {
            $instawp_ismerge = '';
        }
    }
    if ( ! isset($general_setting['options']['instawp_common_setting']['retain_local']) ) {
        $instawp_retain_local = '';
    }
    else {
        if ( $general_setting['options']['instawp_common_setting']['retain_local'] == '1' ) {
            $instawp_retain_local = 'checked';
        }
        else {
            $instawp_retain_local = '';
        }
    }

    if ( ! isset($general_setting['options']['instawp_common_setting']['uninstall_clear_folder']) ) {
        $uninstall_clear_folder = '';
    }
    else {
        if ( $general_setting['options']['instawp_common_setting']['uninstall_clear_folder'] == '1' ) {
            $uninstall_clear_folder = 'checked';
        }
        else {
            $uninstall_clear_folder = '';
        }
    }

    global $instawp_plugin;
    $out_of_date = $instawp_plugin->_get_out_of_date_info();
    ?>
    <div class="postbox schedule-tab-block">
        <div>
            <select option="setting" name="max_backup_count" id="instawp_max_backup_count">
                <?php
                for ( $i = 1; $i < 8;$i++ ) {
                    if ( $i === $display_backup_count ) {
                        echo '<option selected="selected" value="' . esc_attr( $i ) . '">' . esc_html( $i ) . '</option>';
                    }
                    else {
                        echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . '</option>';
                    }
                }
                ?>
            </select><strong style="margin-right: 10px;"><?php esc_html_e('backups retained', 'instawp-connect'); ?></strong><a href="https://docs.instawp.com/instawp-backup-pro-backup-retention.html" style="text-decoration: none;"><?php esc_html_e('Pro feature: Retain more backups', 'instawp-connect'); ?></a>
        </div>
        <div>
            <label for="instawp_estimate_backup">
                <input type="checkbox" option="setting" name="estimate_backup" id="instawp_estimate_backup" value="1" <?php esc_attr($instawp_setting_estimate_backup); ?> />
                <span><?php esc_html_e('Calculate the size of files, folder and database before backing up', 'instawp-connect' ); ?></span>
            </label>
        </div>
        <div>
            <label>
                <input type="checkbox" option="setting" name="show_admin_bar" <?php esc_attr($show_admin_bar); ?> />
                <span><?php esc_html_e('Show instaWP backup plugin on top admin bar', 'instawp-connect'); ?></span>
            </label>
        </div>
        <div>
            <label>
                <input type="checkbox" option="setting" name="ismerge" <?php esc_attr($instawp_ismerge); ?> />
                <span><?php esc_html_e('Merge all the backup files into single package when a backup completes. This will save great disk spaces, though takes longer time. We recommended you check the option especially on sites with insufficient server resources.', 'instawp-connect'); ?></span>
            </label>
        </div>
        <div>
            <label>
                <input type="checkbox" option="setting" name="retain_local" <?php esc_attr($instawp_retain_local); ?> />
                <span><?php esc_html_e('Keep storing the backups in localhost after uploading to remote storage', 'instawp-connect'); ?></span>
            </label>
        </div>
        <div>
            <label>
                <input type="checkbox" option="setting" name="uninstall_clear_folder" <?php esc_attr($uninstall_clear_folder); ?> />
                <span><?php 
                    /* translators: %s: $general_setting */
                    echo sprintf(esc_html__('Delete the /%s folder and all backups in it when deleting instaWP Backup plugin.', 'instawp-connect'), esc_html( $general_setting['options']['instawp_local_setting']['path']) ); ?></span>
            </label>
        </div>
    </div>
    <div class="postbox schedule-tab-block">
        <div><strong><?php esc_html_e('Backup Folder', 'instawp-connect'); ?></strong></div>
        <div class="setting-tab-block">
            <div><p><?php esc_html_e('Name your folder, this folder must be writable for creating backup files.', 'instawp-connect' ); ?><p> </div>
            <input type="text" placeholder="instawpbackups" option="setting" name="path" id="instawp_option_backup_dir" class="all-options" value="<?php esc_attr($general_setting['options']['instawp_local_setting']['path']); ?>" onkeyup="value=value.replace(/[^\a-\z\A-\Z0-9]/g,'')" onpaste="value=value.replace(/[^\a-\z\A-\Z0-9]/g,'')" />
            <p><span class="instawp-element-space-right"><?php esc_html_e('Local storage directory:', 'instawp-connect'); ?></span><span><?php echo esc_html( WP_CONTENT_DIR.'/' ); ?><span id="instawp_setting_local_storage_path"><?php esc_html($general_setting['options']['instawp_local_setting']['path']); ?></span></span></p>
        </div>
        <div>
            <label>
                <input type="checkbox" option="setting" name="domain_include" <?php esc_attr($instawp_domain_include); ?> />
                <span><?php esc_html_e('Display domain(url) of current site in backup name. (e.g. domain_instawp-5ceb938b6dca9_2019-05-27-07-36_backup_all.zip)', 'instawp-connect'); ?></span>
            </label>
        </div>
    </div>
    <div class="postbox schedule-tab-block">
        <div><strong><?php esc_html_e('Remove out-of-date backups', 'instawp-connect'); ?></strong></div>
        <div class="setting-tab-block" style="padding-bottom: 0;">
            <fieldset>
                <label for="users_can_register">
                    <p><span class="instawp-element-space-right"><?php esc_html_e('Web Server Directory:', 'instawp-connect'); ?></span><span id="instawp_out_of_date_local_path"><?php esc_html($out_of_date['web_server']); ?></span></p>
                    <p><span style="margin-right: 2px;"><?php esc_html_e('Remote Storage Directory:', 'instawp-connect'); ?></span><span id="instawp_out_of_date_remote_path">
                                    <?php
                                    $instawp_get_remote_directory = '';
                                    $instawp_get_remote_directory = apply_filters('instawp_get_remote_directory', $instawp_get_remote_directory);
                                    echo esc_html( $instawp_get_remote_directory );
                                    ?>
                                </span>
                    </p>
                </label>
            </fieldset>
        </div>
        <div class="setting-tab-block" style="padding: 10px 10px 0 0;">
            <input class="button-primary" id="instawp_delete_out_of_backup" style="margin-right:10px;" type="submit" name="delete-out-of-backup" value="<?php esc_attr_e( 'Remove', 'instawp-connect' ); ?>" />
            <p><?php esc_html_e('The action is irreversible! It will remove all backups are out-of-date (including local web server and remote storage) if they exist.', 'instawp-connect'); ?> </p>
        </div>
    </div>
    <script>
        jQuery('#instawp_delete_out_of_backup').click(function(){
            instawp_delete_out_of_date_backups();
        });

        /**
         * This function will delete out of date backups.
         */
        function instawp_delete_out_of_date_backups(){
            var ajax_data={
                'action': 'instawp_clean_out_of_date_backup'
            };
            jQuery('#instawp_delete_out_of_backup').css({'pointer-events': 'none', 'opacity': '0.4'});
            instawp_post_request(ajax_data, function(data){
                jQuery('#instawp_delete_out_of_backup').css({'pointer-events': 'auto', 'opacity': '1'});
                try {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === "success") {
                        alert("<?php esc_html_e('Out of date backups have been removed.', 'instawp-connect'); ?>");
                        instawp_handle_backup_data(data);
                    }
                }
                catch(err){
                    alert(err);
                    jQuery('#instawp_delete_out_of_backup').css({'pointer-events': 'auto', 'opacity': '1'});
                }
            }, function(XMLHttpRequest, textStatus, errorThrown) {
                var error_message = instawp_output_ajaxerror('deleting out of date backups', textStatus, errorThrown);
                alert(error_message);
                jQuery('#instawp_delete_out_of_backup').css({'pointer-events': 'auto', 'opacity': '1'});
            });
        }
    </script>
    <?php
}

function instawp_email_report() {
    $general_setting = InstaWP_Setting::get_setting(true, "");
    $setting_email_enable = '';
    $setting_email_display = 'display: none;';
    if ( isset($general_setting['options']['instawp_email_setting']['email_enable']) ) {
        if ( $general_setting['options']['instawp_email_setting']['email_enable'] ) {
            $setting_email_enable = 'checked';
            $setting_email_display = '';
        }
    }
    $instawp_setting_email_always = '';
    $instawp_setting_email_failed = '';
    if ( isset($general_setting['options']['instawp_email_setting']['always']) && $general_setting['options']['instawp_email_setting']['always'] ) {
        $instawp_setting_email_always = 'checked';
    }
    else {
        $instawp_setting_email_failed = 'checked';
    }
    ?>
    <div class="postbox schedule-tab-block" id="instawp_email_report">
        <div><p><?php esc_html_e('In order to use this function, please install a <strong><a target="_blank" href="https://instawp.com//8-best-smtp-plugins-for-wordpress.html" style="text-decoration: none;">WordPress SMTP plugin</a></strong> of your preference and configure your SMTP server first. This is because WordPress uses the PHP Mail function to send its emails by default, which is not supported by many hosts and can cause issues if it is not set properly.', 'instawp-connect'); ?></p>
        </div>
        <div>
            <label for="instawp_general_email_enable">
                <input type="checkbox" option="setting" name="email_enable" id="instawp_general_email_enable" value="1" <?php esc_attr($setting_email_enable); ?> />
                <span><strong><?php esc_html_e( 'Enable email report', 'instawp-connect' ); ?></strong></span>
            </label>
        </div>
        <div id="instawp_general_email_setting" style="<?php esc_attr($setting_email_display); ?>" >
            <input type="text" placeholder="example@yourdomain.com" option="setting" name="send_to" class="regular-text" id="instawp_mail" value="<?php
            if ( ! empty($general_setting['options']['instawp_email_setting']['send_to']) ) {
                foreach ( $general_setting['options']['instawp_email_setting']['send_to'] as $mail ) {
                    if ( ! empty($mail) && ! is_array($mail) ) {
                        echo esc_html($mail);
                        break;
                    }
                }
            }
            ?>" />
            <input class="button-secondary" id="instawp_send_email_test" style="margin-top:10px;" type="submit" name="" value="<?php esc_attr_e( 'Test Email', 'instawp-connect' ); ?>" title="Send an email for testing mail function"/>
            <div id="instawp_send_email_res"></div>
            <fieldset class="setting-tab-block">
                <label >
                    <input type="radio" option="setting" name="always" value="1" <?php esc_attr($instawp_setting_email_always); ?> />
                    <span><?php esc_html_e( 'Always send an email notification when a backup is complete', 'instawp-connect' ); ?></span>
                </label><br>
                <label >
                    <input type="radio" option="setting" name="always" value="0" <?php esc_attr($instawp_setting_email_failed); ?> />
                    <span><?php esc_html_e( 'Only send an email notification when a backup fails', 'instawp-connect' ); ?></span>
                </label><br>
            </fieldset>
            <div style="margin-bottom: 10px;">
                <a href="https://instawp.com//instawp-backup-pro-email-report?utm_source=client_email_report&utm_medium=inner_link&utm_campaign=access" style="text-decoration: none;"><?php esc_html_e('Pro feature: Add another email address to get report', 'instawp-connect'); ?></a>
            </div>
        </div>
    </div>
    <script>
        jQuery('#instawp_send_email_test').click(function(){
            instawp_email_test();
        });

        /**
         * After enabling email report feature, and test if an email address works or not
         */
        function instawp_email_test(){
            var mail = jQuery('#instawp_mail').val();
            var ajax_data = {
                'action': 'instawp_test_send_mail',
                'send_to': mail
            };
            instawp_post_request(ajax_data, function(data){
                try {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success') {
                        jQuery('#instawp_send_email_res').html('Test succeeded.');
                    }
                    else {
                        jQuery('#instawp_send_email_res').html('Test failed, ' + jsonarray.error);
                    }
                }
                catch(err){
                    alert(err);
                }
            }, function(XMLHttpRequest, textStatus, errorThrown) {
                var error_message = instawp_output_ajaxerror('sending test mail', textStatus, errorThrown);
                alert(error_message);
            });
        }
    </script>
    <?php
}

function instawp_clean_junk() {
    global $instawp_plugin;
    $junk_file = $instawp_plugin->_junk_files_info_ex();
    //echo json_encode($junk_file);
    //$junk_file=$instawp_plugin->_junk_files_info();
    /*$junk_file['sum_size']=0;
    $junk_file['log_dir_size']=0;
    $junk_file['backup_dir_size'] =0;
    $junk_file['log_path'] = $log_dir = $instawp_plugin->instawp_log->GetSaveLogFolder();
    $dir = InstaWP_Setting::get_backupdir();
    $junk_file['old_files_path'] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_ROLLBACK_DIR;
    $dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir;
    $junk_file['junk_path'] = $dir;*/
    ?>
    <div class="postbox schedule-tab-block" id="instawp_clean_junk">
        <div>
            <strong><?php esc_html_e('Web-server disk space in use by instaWP', 'instawp-connect'); ?></strong>
        </div>
        <div class="setting-tab-block">
            <div class="setting-tab-block">
                <span class="instawp-element-space-right"><?php esc_html_e('Total Size:', 'instawp-connect'); ?></span>
                <span class="instawp-size-calc instawp-element-space-right" id="instawp_junk_sum_size"><?php echo esc_html($junk_file['sum_size']); ?></span>
                <span class="instawp-element-space-right"><?php echo esc_html( 'Backup Size:' ); ?></span>
                <span class="instawp-size-calc instawp-element-space-right" id="instawp_backup_size"><?php esc_html($junk_file['backup_size']); ?></span>
                <input class="button-secondary" id="instawp_calculate_size" style="margin-left:10px;" type="submit" name="Calculate-Sizes" value="<?php esc_attr_e( 'Calculate Sizes', 'instawp-connect' ); ?>" />
            </div>
            <fieldset>
                <label for="instawp_junk_log">
                    <input type="checkbox" id="instawp_junk_log" option="junk-files" name="log" value="junk-log" />
                    <span class="instawp-element-space-right"><?php esc_html_e( 'Logs Size:', 'instawp-connect' ); ?></span>
                    <span class="instawp-size-calc" id="instawp_log_size"><?php esc_html($junk_file['log_dir_size']); ?></span>
                </label>
            </fieldset>
            <fieldset>
                <label for="instawp_junk_backup_cache">
                    <input type="checkbox" id="instawp_junk_backup_cache" option="junk-files" name="backup_cache" value="junk-backup-cache" />
                    <span class="instawp-element-space-right"><?php esc_html_e( 'Backup Cache Size:', 'instawp-connect' ); ?></span>
                    <span class="instawp-size-calc" id="instawp_backup_cache_size"><?php echo esc_html($junk_file['backup_cache_size']); ?></span>
                </label>
            </fieldset>
            <fieldset>
                <label for="instawp_junk_file">
                    <input type="checkbox" id="instawp_junk_file" option="junk-files" name="junk_files" value="junk-files" />
                    <span class="instawp-element-space-right"><?php esc_html_e( 'Junk Size:', 'instawp-connect' ); ?></span>
                    <span class="instawp-size-calc" id="instawp_junk_size"><?php echo esc_html($junk_file['junk_size']); ?></span>
                </label>
                
            </fieldset>
        </div>
        <div><input class="button-primary" id="instawp_clean_junk_file" type="submit" name="Empty-all-files" value="<?php esc_attr_e( 'Empty', 'instawp-connect' ); ?>" /></div>
        <div style="clear:both;"></div>
    </div>
    <script>
        jQuery('#instawp_calculate_size').click(function(){
            instawp_calculate_diskspaceused();
        });

        jQuery('#instawp_clean_junk_file').click(function(){
            instawp_clean_junk_files();
        });

        /**
         * Calculate the server disk space in use by instaWP.
         */
        function instawp_calculate_diskspaceused(){
            var ajax_data={
                'action': 'instawp_junk_files_info'
            };
            var current_size = jQuery('#instawp_junk_sum_size').html();
            jQuery('#instawp_calculate_size').css({'pointer-events': 'none', 'opacity': '0.4'});
            jQuery('#instawp_clean_junk_file').css({'pointer-events': 'none', 'opacity': '0.4'});
            jQuery('.instawp-size-calc').html("calculating...");
            instawp_post_request(ajax_data, function(data){
                jQuery('#instawp_calculate_size').css({'pointer-events': 'auto', 'opacity': '1'});
                jQuery('#instawp_clean_junk_file').css({'pointer-events': 'auto', 'opacity': '1'});
                try {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === "success") {
                        jQuery('#instawp_junk_sum_size').html(jsonarray.data.sum_size);
                        jQuery('#instawp_log_size').html(jsonarray.data.log_dir_size);
                        jQuery('#instawp_backup_cache_size').html(jsonarray.data.backup_cache_size);
                        jQuery('#instawp_junk_size').html(jsonarray.data.junk_size);
                        jQuery('#instawp_backup_size').html(jsonarray.data.backup_size);
                    }
                }
                catch(err){
                    alert(err);
                    jQuery('#instawp_calculate_size').css({'pointer-events': 'auto', 'opacity': '1'});
                    jQuery('#instawp_clean_junk_file').css({'pointer-events': 'auto', 'opacity': '1'});
                    jQuery('#instawp_junk_sum_size').html(current_size);
                }
            }, function(XMLHttpRequest, textStatus, errorThrown) {
                var error_message = instawp_output_ajaxerror('calculating server disk space in use by instaWP', textStatus, errorThrown);
                alert(error_message);
                jQuery('#instawp_calculate_size').css({'pointer-events': 'auto', 'opacity': '1'});
                jQuery('#instawp_clean_junk_file').css({'pointer-events': 'auto', 'opacity': '1'});
                jQuery('#instawp_junk_sum_size').html(current_size);
            });
        }

        /**
         * Clean junk files created during backups and restorations off your web server disk.
         */
        function instawp_clean_junk_files(){
            var descript = '<?php esc_html_e('The selected item(s) will be permanently deleted. Are you sure you want to continue?', 'instawp-connect'); ?>';
            var ret = confirm(descript);
            if(ret === true){
                var option_data = instawp_ajax_data_transfer('junk-files');
                var ajax_data = {
                    'action': 'instawp_clean_local_storage',
                    'options': option_data
                };
                jQuery('#instawp_calculate_size').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_clean_junk_file').css({'pointer-events': 'none', 'opacity': '0.4'});
                instawp_post_request(ajax_data, function (data) {
                    jQuery('#instawp_calculate_size').css({'pointer-events': 'auto', 'opacity': '1'});
                    jQuery('#instawp_clean_junk_file').css({'pointer-events': 'auto', 'opacity': '1'});
                    jQuery('input[option="junk-files"]').prop('checked', false);
                    try {
                        var jsonarray = jQuery.parseJSON(data);
                        alert(jsonarray.msg);
                        if (jsonarray.result === "success") {
                            jQuery('#instawp_junk_sum_size').html(jsonarray.data.sum_size);
                            jQuery('#instawp_log_size').html(jsonarray.data.log_dir_size);
                            jQuery('#instawp_backup_cache_size').html(jsonarray.data.backup_cache_size);
                            jQuery('#instawp_junk_size').html(jsonarray.data.junk_size);
                            jQuery('#instawp_backup_size').html(jsonarray.data.backup_size);
                            jQuery('#instawp_loglist').html("");
                            jQuery('#instawp_loglist').append(jsonarray.html);
                            instawp_log_count = jsonarray.log_count;
                            instawp_display_log_page();
                        }
                    }
                    catch(err){
                        alert(err);
                    }
                }, function (XMLHttpRequest, textStatus, errorThrown) {
                    var error_message = instawp_output_ajaxerror('cleaning out junk files', textStatus, errorThrown);
                    alert(error_message);
                    jQuery('#instawp_calculate_size').css({'pointer-events': 'auto', 'opacity': '1'});
                    jQuery('#instawp_clean_junk_file').css({'pointer-events': 'auto', 'opacity': '1'});
                });
            }
        }

        jQuery(document).ready(function ()
        {
            instawp_calculate_diskspaceused();
        });
    </script>
    <?php
}

function instawp_export_import_settings() {
    ?>
    <div class="postbox schedule-tab-block" id="instawp_export_import">
        <div class="setting-tab-block" style="padding-bottom: 0;">
            <input class="button-primary" id="instawp_setting_export" type="button" name="" value="<?php esc_attr_e( 'Export', 'instawp-connect' ); ?>" />
            <p><?php echo esc_html__('Click \'Export\' button to save instaWP settings on your local computer.', 'instawp-connect'); ?> </p>
        </div>
        <div class="setting-tab-block" style="padding: 0 10px 0 0;">
            <input type="file" name="fileTrans" id="instawp_select_import_file"></br>
            <input class="button-primary" id="instawp_setting_import" type="button" name="" value="<?php esc_attr_e( 'Import', 'instawp-connect' ); ?>" />
            <p><?php echo esc_html__('Importing the json file can help you set instaWP\'s configuration on another WordPress site quickly.', 'instawp-connect'); ?></p>
        </div>
        <div style="clear:both;"></div>
    </div>
    <script>
        jQuery('#instawp_setting_export').click(function(){
            instawp_export_settings();
        });

        jQuery('#instawp_setting_import').click(function(){
            instawp_import_settings();
        });

        function instawp_export_settings() {
            instawp_location_href=true;
            location.href =ajaxurl+'?_wpnonce='+instawp_ajax_object.ajax_nonce+'&action=instawp_export_setting&setting=1&history=1&review=0';
        }

        function instawp_import_settings(){
            var files = jQuery('input[name="fileTrans"]').prop('files');

            if(files.length == 0){
                alert('Choose a settings file and import it by clicking Import button.');
                return;
            }
            else{
                var reader = new FileReader();
                reader.readAsText(files[0], "UTF-8");
                reader.onload = function(evt){
                    var fileString = evt.target.result;
                    var ajax_data = {
                        'action': 'instawp_import_setting',
                        'data': fileString
                    };
                    instawp_post_request(ajax_data, function(data){
                        try {
                            var jsonarray = jQuery.parseJSON(data);
                            if (jsonarray.result === 'success') {
                                alert('The plugin settings were imported successfully.');
                                location.reload();
                            }
                            else {
                                alert('Error: ' + jsonarray.error);
                            }
                        }
                        catch(err){
                            alert(err);
                        }
                    }, function(XMLHttpRequest, textStatus, errorThrown) {
                        var error_message = instawp_output_ajaxerror('importing the previously-exported settings', textStatus, errorThrown);
                        jQuery('#instawp_display_log_content').html(error_message);
                    });
                }
            }
        }
    </script>
    <?php
}

function instawp_advanced_settings() {
    $general_setting = InstaWP_Setting::get_setting(true, "");
    $instawp_setting_no_compress = '';
    $instawp_setting_compress = '';
    if ( $general_setting['options']['instawp_compress_setting']['no_compress'] ) {
        $instawp_setting_no_compress = 'checked';
    }
    else {
        $instawp_setting_compress = 'checked';
    }

    if ( ! isset($general_setting['options']['instawp_compress_setting']['subpackage_plugin_upload']) ) {
        $subpackage_plugin_upload = '';
    }
    else {
        if ( $general_setting['options']['instawp_compress_setting']['subpackage_plugin_upload'] ) {
            $subpackage_plugin_upload = 'checked';
        }
        else {
            $subpackage_plugin_upload = '';
        }
    }
    if ( ! isset($general_setting['options']['instawp_common_setting']['max_resume_count']) ) {
        $instawp_max_resume_count = INSTAWP_RESUME_RETRY_TIMES;
    }
    else {
        $instawp_max_resume_count = intval($general_setting['options']['instawp_common_setting']['max_resume_count']);
    }
    if ( ! isset($general_setting['options']['instawp_common_setting']['memory_limit']) ) {
        $general_setting['options']['instawp_common_setting']['memory_limit'] = INSTAWP_MEMORY_LIMIT;
    }
    if ( ! isset($general_setting['options']['instawp_common_setting']['restore_memory_limit']) ) {
        $general_setting['options']['instawp_common_setting']['restore_memory_limit'] = INSTAWP_RESTORE_MEMORY_LIMIT;
    }
    if ( ! isset($general_setting['options']['instawp_common_setting']['migrate_size']) ) {
        $general_setting['options']['instawp_common_setting']['migrate_size'] = INSTAWP_MIGRATE_SIZE;
    }
    if ( isset($general_setting['options']['instawp_common_setting']['db_connect_method']) ) {
        if ( $general_setting['options']['instawp_common_setting']['db_connect_method'] === 'wpdb' ) {
            $db_method_wpdb = 'checked';
            $db_method_pdo  = '';
        }
        else {
            $db_method_wpdb = '';
            $db_method_pdo  = 'checked';
        }
    }
    else {
        $db_method_wpdb = 'checked';
        $db_method_pdo  = '';
    }
    if ( isset($general_setting['options']['instawp_common_setting']['restore_max_execution_time']) ) {
        $restore_max_execution_time = intval($general_setting['options']['instawp_common_setting']['restore_max_execution_time']);
    }
    else {
        $restore_max_execution_time = INSTAWP_RESTORE_MAX_EXECUTION_TIME;
    }
    ?>
    <div class="postbox schedule-tab-block setting-page-content">
        <div>
            <p><strong><?php esc_html_e('Enable the option when backup failed.', 'instawp-connect'); ?></strong>&nbsp<?php esc_html_e('Special optimization for web hosting/shared hosting', 'instawp-connect'); ?></p>
            <div>
                <label>
                    <input type="checkbox" option="setting" name="subpackage_plugin_upload" <?php esc_attr($subpackage_plugin_upload); ?> />
                    <span><strong><?php esc_html_e('Enable optimization mode for web hosting/shared hosting', 'instawp-connect'); ?></strong></span>
                </label>
                <div>
                    <p><?php esc_html_e('Enabling this option can improve the backup success rate, but it will take more time for backup.', 'instawp-connect'); ?></p>
                </div>
            </div>
        </div>
    </div>
    <div class="postbox schedule-tab-block instawp-setting-addon" style="margin-bottom: 10px; padding-bottom: 0;">
        <div class="instawp-element-space-bottom">
            <strong><?php esc_html_e('Database access method.', 'instawp-connect'); ?></strong>
        </div>
        <div class="instawp-element-space-bottom">
            <label>
                <input type="radio" option="setting" name="db_connect_method" value="wpdb" <?php esc_attr($db_method_wpdb); ?> />
                <span class="instawp-element-space-right"><strong>WPDB</strong></span><span><?php esc_html_e('WPDB option has a better compatibility, but the speed of backup and restore is slower.', 'instawp-connect'); ?></span>
            </label>
        </div>
        <div class="instawp-element-space-bottom">
            <label>
                <input type="radio" option="setting" name="db_connect_method" value="pdo" <?php esc_attr($db_method_pdo); ?> />
                <span class="instawp-element-space-right"><strong>PDO</strong></span><span><?php esc_html_e('It is recommended to choose PDO option if pdo_mysql extension is installed on your server, which lets you backup and restore your site faster.', 'instawp-connect'); ?></span>
            </label>
        </div>
    </div>
    <div class="postbox schedule-tab-block setting-page-content">
        <fieldset>
            <label>
                <input type="radio" option="setting" name="no_compress" value="1" <?php esc_attr($instawp_setting_no_compress); ?> />
                <span class="instawp-element-space-right" title="<?php esc_attr_e( 'It will cause a lower CPU Usage and is recommended in a web hosting/ shared hosting environment.', 'instawp-connect' ); ?>"><?php esc_html_e( 'Only Archive without compressing', 'instawp-connect' ); ?></span>
            </label>
            <label>
                <input type="radio" option="setting" name="no_compress" value="0" <?php esc_attr($instawp_setting_compress); ?> />
                <span class="instawp-element-space-right" title="<?php esc_attr_e( 'It will cause a higher CPU usage and is recommended in a VPS or dedicated hosting environment.', 'instawp-connect' ); ?>"><?php esc_html_e( 'Compress and Archive', 'instawp-connect' ); ?></span>
            </label>
            <label style="display: none;">
                <input type="radio" option="setting" name="compress_type" value="zip" checked />
                <input type="radio" option="setting" name="use_temp_file" value="1" checked />
                <input type="radio" option="setting" name="use_temp_size" value="16" checked />
            </label>
        </fieldset>
        <div style="padding-top: 10px;">
            <div><strong><?php esc_html_e('Compress Files Every', 'instawp-connect'); ?></strong></div>
            <div class="setting-tab-block">
                <input type="text" placeholder="200" option="setting" name="max_file_size" id="instawp_max_zip" class="all-options" value="<?php esc_attr(str_replace('M', '', $general_setting['options']['instawp_compress_setting']['max_file_size'])); ?>" onkeyup="value=value.replace(/\D/g,'')" />MB
                <div><p><?php esc_html_e( 'Some web hosting providers limit large zip files (e.g. 200MB), and therefore splitting your backup into many parts is an ideal way to avoid hitting the limitation if you are running a big website.  Please try to adjust the value if you are encountering backup errors. If you use a value of 0 MB, any backup files won\'t be split.', 'instawp-connect' ); ?></div></p>
            </div>
            <div><strong><?php esc_html_e('Exclude the files which are larger than', 'instawp-connect'); ?></strong></div>
            <div class="setting-tab-block">
                <input type="text" placeholder="0" option="setting" name="exclude_file_size" id="instawp_ignore_large" class="all-options" value="<?php esc_attr($general_setting['options']['instawp_compress_setting']['exclude_file_size']); ?>" onkeyup="value=value.replace(/\D/g,'')" />MB
                <div><p><?php esc_html_e( 'Using the option will ignore the file larger than the certain size in MB when backing up, \'0\' (zero) means unlimited.', 'instawp-connect' ); ?></p></div>
            </div>
            <div><strong><?php esc_html_e('PHP script execution timeout for backup', 'instawp-connect'); ?></strong></div>
            <div class="setting-tab-block">
                <input type="text" placeholder="900" option="setting" name="max_execution_time" id="instawp_option_timeout" class="all-options" value="<?php esc_attr($general_setting['options']['instawp_common_setting']['max_execution_time']); ?>" onkeyup="value=value.replace(/\D/g,'')" /><?php esc_html_e('Seconds', 'instawp-connect'); ?>
                <div><p><?php esc_html_e( 'The time-out is not your server PHP time-out. With the execution time exhausted, our plugin will shut the process of backup down. If the progress of backup encounters a time-out, that means you have a medium or large sized website, please try to scale the value bigger.', 'instawp-connect' ); ?></p></div>
            </div>
            <div><strong><?php esc_html_e('PHP script execution timeout for restore', 'instawp-connect'); ?></strong></div>
            <div class="setting-tab-block">
                <input type="text" placeholder="1800" option="setting" name="restore_max_execution_time" class="all-options" value="<?php esc_attr($restore_max_execution_time); ?>" onkeyup="value=value.replace(/\D/g,'')" /><?php esc_html_e('Seconds', 'instawp-connect'); ?>
                <div><p><?php esc_html_e( 'The time-out is not your server PHP time-out. With the execution time exhausted, our plugin will shut the process of restore down. If the progress of restore encounters a time-out, that means you have a medium or large sized website, please try to scale the value bigger.', 'instawp-connect' ); ?></p></div>
            </div>
            <div><strong><?php esc_html_e('PHP Memory Limit for backup', 'instawp-connect'); ?></strong></div>
            <div class="setting-tab-block">
                <input type="text" placeholder="256" option="setting" name="memory_limit" class="all-options" value="<?php esc_attr(str_replace('M', '', $general_setting['options']['instawp_common_setting']['memory_limit'])); ?>" onkeyup="value=value.replace(/\D/g,'')" />MB
                <div><p><?php esc_html_e('Adjust this value to apply for a temporary PHP memory limit for instaWP backup plugin to run a backup. We set this value to 256M by default. Increase the value if you encounter a memory exhausted error. Note: some web hosting providers may not support this.', 'instawp-connect'); ?></p></div>
            </div>
            <div><strong><?php esc_html_e('PHP Memory Limit for restoration', 'instawp-connect'); ?></strong></div>
            <div class="setting-tab-block">
                <input type="text" placeholder="256" option="setting" name="restore_memory_limit" class="all-options" value="<?php esc_attr(str_replace('M', '', $general_setting['options']['instawp_common_setting']['restore_memory_limit'])); ?>" onkeyup="value=value.replace(/\D/g,'')" />MB
                <div><p><?php esc_html_e('Adjust this value to apply for a temporary PHP memory limit for instaWP backup plugin in restore process. We set this value to 256M by default. Increase the value if you encounter a memory exhausted error. Note: some web hosting providers may not support this.', 'instawp-connect'); ?></p></div>
            </div>
            <div><strong><?php esc_html_e('Chunk Size', 'instawp-connect'); ?></strong></div>
            <div class="setting-tab-block">
                <input type="text" placeholder="2048" option="setting" name="migrate_size" class="all-options" value="<?php esc_attr($general_setting['options']['instawp_common_setting']['migrate_size']); ?>" onkeyup="value=value.replace(/\D/g,'')" />KB
                <div><p><?php esc_html_e('e.g.  if you choose a chunk size of 2MB, a 8MB file will use 4 chunks. Decreasing this value will break the ISP\'s transmission limit, for example:512KB', 'instawp-connect'); ?></p></div>
            </div>
            <div>
                <?php
                $max_count_option = '';
                for ( $resume_count = 3; $resume_count < 10; $resume_count++ ) {
                    if ( $resume_count === $instawp_max_resume_count ) {
                        $max_count_option .= '<option selected="selected" value="'.$resume_count.'">'.$resume_count.'</option>';
                    }
                    else {
                        $max_count_option .= '<option value="'.$resume_count.'">'.$resume_count.'</option>';
                    }
                }
                $max_count_select = '<select option="setting" name="max_resume_count">'.$max_count_option.'</select>';
                    /* translators: %s: $max_count_select */
                echo sprintf(esc_html__('<strong>Retrying </strong>%s<strong> times when encountering a time-out error</strong>', 'instawp-connect'), esc_html( $max_count_select ));
                ?>
            </div>
        </div>
    </div>
    <?php
}

function instawp_add_setting_tab_page( $setting_array ) {
    $setting_array['general_setting'] = array(
		'index'     => '1',
		'tab_func'  => 'instawp_settingpage_add_tab_general',
		'page_func' => 'instawp_settingpage_add_page_general',
	);
    $setting_array['advance_setting'] = array(
		'index'     => '2',
		'tab_func'  => 'instawp_settingpage_add_tab_advance',
		'page_func' => 'instawp_settingpage_add_page_advance',
	);
    return $setting_array;
}

function instawp_settingpage_add_tab_general(){
    ?>
    <a href="#" id="instawp_tab_general_setting" class="nav-tab setting-nav-tab nav-tab-active" onclick="switchsettingTabs(event,'page-general-setting')"><?php esc_html_e('General Settings', 'instawp-connect'); ?></a>
    <?php
}

function instawp_settingpage_add_tab_advance(){
    ?>
    <a href="#" id="instawp_tab_advance_setting" class="nav-tab setting-nav-tab" onclick="switchsettingTabs(event,'page-advance-setting')"><?php esc_html_e('Advanced Settings', 'instawp-connect'); ?></a>
    <?php
}

function instawp_settingpage_add_page_general(){
    ?>
    <div class="setting-tab-content instawp_tab_general_setting" id="page-general-setting" style="margin-top: 10px;">
        <?php do_action('instawp_setting_add_general_cell'); ?>
    </div>
    <?php
}

function instawp_settingpage_add_page_advance(){
    ?>
    <div class="setting-tab-content instawp_tab_advance_setting" id="page-advance-setting" style="margin-top: 10px; display: none;">
        <?php do_action('instawp_setting_add_advance_cell'); ?>
    </div>
    <?php
}

add_filter('instawp_add_setting_tab_page', 'instawp_add_setting_tab_page', 10);

add_action('instawp_setting_add_general_cell','instawp_general_settings',10);
add_action('instawp_setting_add_advance_cell','instawp_advanced_settings',13);
add_action('instawp_setting_add_general_cell','instawp_email_report',14);
add_action('instawp_setting_add_general_cell','instawp_clean_junk',15);
add_action('instawp_setting_add_general_cell','instawp_export_import_settings',16);
?>
