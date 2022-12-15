<?php

function instawp_add_tab_storage_list() {
    ?>
    <a href="#" id="instawp_tab_storage_list" class="nav-tab storage-nav-tab nav-tab-active" onclick="switchstorageTabs(event,'page-storage-list','page-storage-list')"><?php esc_html_e('Storages', 'instawp-connect'); ?></a>
    <?php
}

function instawp_add_tab_storage_edit() {
    ?>
    <a href="#" id="instawp_tab_storage_edit" class="nav-tab storage-nav-tab delete" onclick="switchstorageTabs(event,'page-storage_edit','page-storage_edit')" style="display: none;">
        <div id="instawp_tab_storage_edit_text" style="margin-right: 15px;"><?php esc_html_e('Storage Edit', 'instawp-connect'); ?></div>
        <div class="nav-tab-delete-img">
            <img src="<?php echo esc_url(plugins_url( 'images/delete-tab.png', __FILE__ )); ?>" style="vertical-align:middle; cursor:pointer;" onclick="instawp_close_tab(event, 'instawp_tab_storage_edit', 'storage', 'instawp_tab_storage_list');" />
        </div>
    </a>
    <?php
}

function instawp_add_page_storage_list() {
    ?>
    <div class="storage-tab-content instawp_tab_storage_list" id="page-storage-list">
        <div style="margin-top:10px;"><p><strong><?php esc_html_e('Please choose one storage to save your backups (remote storage)', 'instawp-connect'); ?></strong></p></div>
        <div class="schedule-tab-block"></div>
        <div class="">
            <table class="widefat">
                <thead>
                <tr>
                    <th></th>
                    <th></th>
                    <th><?php esc_html_e( 'Storage Provider', 'instawp-connect' ); ?></th>
                    <th class="row-title"><?php esc_html_e( 'Remote Storage Alias', 'instawp-connect' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'instawp-connect' ); ?></th>
                </tr>
                </thead>
                <tbody class="instawp-remote-storage-list" id="instawp_remote_storage_list">
                <?php
                $html = '';
                $html = apply_filters('instawp_add_remote_storage_list', $html);
                echo wp_kses_post( $$html );
                ?>
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="5" class="row-title"><input class="button-primary" id="instawp_set_default_remote_storage" type="submit" name="choose-remote-storage" value="<?php esc_attr_e( 'Save Changes', 'instawp-connect' ); ?>" /></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <script>
        jQuery('input[option=add-remote]').click(function(){
            var storage_type = jQuery(".storage-providers-active").attr("remote_type");
            instawp_add_remote_storage(storage_type);
            instawp_settings_changed = false;
        });

        jQuery('#instawp_set_default_remote_storage').click(function(){
            instawp_set_default_remote_storage();
            instawp_settings_changed = false;
        });

        /**
         * Add remote storages to the list
         *
         * @param action        - The action to add or test a remote storage
         * @param storage_type  - Remote storage types (Amazon S3, SFTP and FTP server)
         */
        function instawp_add_remote_storage(storage_type)
        {
            var remote_from = instawp_ajax_data_transfer(storage_type);
            var ajax_data;
            ajax_data = {
                'action': 'instawp_add_remote',
                'remote': remote_from,
                'type': storage_type
            };
            jQuery('input[option=add-remote]').css({'pointer-events': 'none', 'opacity': '0.4'});
            jQuery('#instawp_remote_notice').html('');
            instawp_post_request(ajax_data, function (data)
            {
                try
                {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success')
                    {
                        jQuery('input[option=add-remote]').css({'pointer-events': 'auto', 'opacity': '1'});
                        jQuery('input:text[option='+storage_type+']').each(function(){
                            jQuery(this).val('');
                        });
                        jQuery('input:password[option='+storage_type+']').each(function(){
                            jQuery(this).val('');
                        });
                        instawp_handle_remote_storage_data(data);
                    }
                    else if (jsonarray.result === 'failed')
                    {
                        jQuery('#instawp_remote_notice').html(jsonarray.notice);
                        jQuery('input[option=add-remote]').css({'pointer-events': 'auto', 'opacity': '1'});
                    }
                }
                catch (err)
                {
                    alert(err);
                    jQuery('input[option=add-remote]').css({'pointer-events': 'auto', 'opacity': '1'});
                }

            }, function (XMLHttpRequest, textStatus, errorThrown)
            {
                var error_message = instawp_output_ajaxerror('adding the remote storage', textStatus, errorThrown);
                alert(error_message);
                jQuery('input[option=add-remote]').css({'pointer-events': 'auto', 'opacity': '1'});
            });
        }

        function instawp_edit_remote_storage() {
            var data_tran = 'edit-'+instawp_editing_storage_type;
            var remote_data = instawp_ajax_data_transfer(data_tran);
            var ajax_data;
            ajax_data = {
                'action': 'instawp_edit_remote',
                'remote': remote_data,
                'id': instawp_editing_storage_id,
                'type': instawp_editing_storage_type
            };
            jQuery('#instawp_remote_notice').html('');
            instawp_post_request(ajax_data, function(data){
                try {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success') {
                        jQuery('#instawp_tab_storage_edit').hide();
                        instawp_click_switch_page('storage', 'instawp_tab_storage_list', true);
                        instawp_handle_remote_storage_data(data);
                    }
                    else if (jsonarray.result === 'failed') {
                        jQuery('#instawp_remote_notice').html(jsonarray.notice);
                    }
                }
                catch(err){
                    alert(err);
                }
            },function(XMLHttpRequest, textStatus, errorThrown) {
                var error_message = instawp_output_ajaxerror('editing the remote storage', textStatus, errorThrown);
                alert(error_message);
            });
        }

        /**
         * Set a default remote storage for backups.
         */
        function instawp_set_default_remote_storage(){
            var remote_storage = new Array();
            //remote_storage[0] = jQuery("input[name='remote_storage']:checked").val();
            jQuery.each(jQuery("input[name='remote_storage']:checked"), function()
            {
                remote_storage.push(jQuery(this).val());
            });

            var ajax_data = {
                'action': 'instawp_set_default_remote_storage',
                'remote_storage': remote_storage
            };
            jQuery('#instawp_remote_notice').html('');
            instawp_post_request(ajax_data, function(data){
                instawp_handle_remote_storage_data(data);
            }, function(XMLHttpRequest, textStatus, errorThrown) {
                var error_message = instawp_output_ajaxerror('setting up the default remote storage', textStatus, errorThrown);
                alert(error_message);
            });
        }

        jQuery('#instawp_remote_storage_list').on("click", "input", function(){
            var check_status = true;
            if(jQuery(this).prop('checked') === true){
                check_status = true;
            }
            else {
                check_status = false;
            }
            jQuery('input[name="remote_storage"]').prop('checked', false);
            if(check_status === true){
                jQuery(this).prop('checked', true);
            }
            else {
                jQuery(this).prop('checked', false);
            }
        });

        function instawp_delete_remote_storage(storage_id){
            var descript = '<?php esc_html_e('Deleting a remote storage will make it unavailable until it is added again. Are you sure to continue?', 'instawp-connect'); ?>';
            var ret = confirm(descript);
            if(ret === true){
                var ajax_data = {
                    'action': 'instawp_delete_remote',
                    'remote_id': storage_id
                };
                instawp_post_request(ajax_data, function(data){
                    instawp_handle_remote_storage_data(data);
                },function(XMLHttpRequest, textStatus, errorThrown) {
                    var error_message = instawp_output_ajaxerror('deleting the remote storage', textStatus, errorThrown);
                    alert(error_message);
                });
            }
        }

        function instawp_handle_remote_storage_data(data){
            var i = 0;
            try {
                var jsonarray = jQuery.parseJSON(data);
                if (jsonarray.result === 'success') {
                    jQuery('#instawp_remote_storage_list').html('');
                    jQuery('#instawp_remote_storage_list').append(jsonarray.html);
                    jQuery('#upload_storage').html(jsonarray.pic);
                    jQuery('#schedule_upload_storage').html(jsonarray.pic);
                    jQuery('#instawp_out_of_date_remote_path').html(jsonarray.dir);
                    jQuery('#instawp_schedule_backup_local_remote').html(jsonarray.local_remote);
                    instawp_control_remote_storage(jsonarray.remote_storage);
                    jQuery('#instawp_remote_notice').html(jsonarray.notice);
                }
                else if(jsonarray.result === 'failed'){
                    alert(jsonarray.error);
                }
            }
            catch(err){
                alert(err);
            }
        }

        function instawp_control_remote_storage(has_remote){
            if(!has_remote){
                if(jQuery("input:radio[name='save_local_remote'][value='remote']").prop('checked')) {
                    alert("<?php esc_html_e('There is no default remote storage configured. Please set it up first.', 'instawp-connect'); ?>");
                    jQuery("input:radio[name='save_local_remote'][value='local']").prop('checked', true);
                }
            }
        }

        function click_retrieve_remote_storage(id,type,name)
        {
            instawp_editing_storage_id = id;
            jQuery('.remote-storage-edit').hide();
            jQuery('#instawp_tab_storage_edit').show();
            jQuery('#instawp_tab_storage_edit_text').html(name);
            instawp_editing_storage_type=type;
            jQuery('#remote_storage_edit_'+instawp_editing_storage_type).fadeIn();
            instawp_click_switch_page('storage', 'instawp_tab_storage_edit', true);

            var ajax_data = {
                'action': 'instawp_retrieve_remote',
                'remote_id': id
            };
            instawp_post_request(ajax_data, function(data)
            {
                try
                {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success')
                    {
                        /*jQuery('input:text[option=edit-'+jsonarray.type+']').each(function(){
                            var key = jQuery(this).prop('name');
                            jQuery(this).val(jsonarray[key]);
                        });
                        jQuery('input:password[option=edit-'+jsonarray.type+']').each(function(){
                            var key = jQuery(this).prop('name');
                            jQuery(this).val(jsonarray[key]);
                        });*/
                        jQuery('input:checkbox[option=edit-'+jsonarray.type+']').each(function() {
                            var key = jQuery(this).prop('name');
                            var value;
                            if(jsonarray[key] == '0'){
                                value = false;
                            }
                            else{
                                value = true;
                            }
                            jQuery(this).prop('checked', value);
                        });
                    }
                    else
                    {
                        alert(jsonarray.error);
                    }
                }
                catch(err)
                {
                    alert(err);
                }
            },function(XMLHttpRequest, textStatus, errorThrown)
            {
                var error_message = instawp_output_ajaxerror('retrieving the remote storage', textStatus, errorThrown);
                alert(error_message);
            });
        }
    </script>
    <?php
}

function instawp_add_page_storage_edit() {
    ?>
    <div class="storage-tab-content instawp_tab_storage_edit" id="page-storage_edit" style="display:none;">
        <div><?php do_action('instawp_edit_remote_page'); ?></div>
    </div>
    <script>
        jQuery('input[option=edit-remote]').click(function(){
            instawp_edit_remote_storage();
        });
    </script>
    <?php
}

function instawp_storage_list( $html ) {
    $html = '<h2 class="nav-tab-wrapper" style="padding-bottom:0!important;">';
    $html .= '<a href="#" id="instawp_tab_storage_list" class="nav-tab storage-nav-tab nav-tab-active" onclick="switchstorageTabs(event,\'page-storage-list\',\'page-storage-list\')">'. __('Storages', 'instawp-connect').'</a>';
    $html .= '<a href="#" id="instawp_tab_storage_edit" class="nav-tab storage-nav-tab delete" onclick="switchstorageTabs(event,\'page-storage_edit\',\'page-storage_edit\')" style="display: none;">
        <div id="instawp_tab_storage_edit_text" style="margin-right: 15px;">'.__('Storage Edit', 'instawp-connect').'</div>
        <div class="nav-tab-delete-img">
            <img src="'.esc_url(plugins_url( 'images/delete-tab.png', __FILE__ )).'" style="vertical-align:middle; cursor:pointer;" onclick="instawp_close_tab(event, \'instawp_tab_storage_edit\', \'storage\', \'instawp_tab_storage_list\');" />
        </div>
    </a>';
    $html .= '</h2>';
    $html .= '<div class="storage-tab-content instawp_tab_storage_list" id="page-storage-list">
        <div style="margin-top:10px;"><p><strong>'.__('Please choose one storage to save your backups (remote storage)', 'instawp-connect').'</strong></p></div>
        <div class="schedule-tab-block"></div>
        <div class="">
            <table class="widefat">
                <thead>
                <tr>
                    <th></th>
                    <th></th>
                    <th>'. __( 'Storage Provider', 'instawp-connect' ).'</th>
                    <th class="row-title">'. __( 'Remote Storage Alias', 'instawp-connect' ).'</th>
                    <th>'. __( 'Actions', 'instawp-connect' ).'</th>
                </tr>
                </thead>
                <tbody class="instawp-remote-storage-list" id="instawp_remote_storage_list">
                ';
    $html_list = '';
    $html .= apply_filters('instawp_add_remote_storage_list', $html_list);
    $html .= '</tbody><tfoot><tr>
            <th colspan="5" class="row-title"><input class="button-primary" id="instawp_set_default_remote_storage" type="submit" name="choose-remote-storage" value="'.esc_attr__( 'Save Changes', 'instawp-connect' ).'" /></th>
            </tr></tfoot></table></div></div>';

    $html .= '<script>
            jQuery(\'#instawp_remote_storage_list\').on("click", "input", function(){
                var check_status = true;
                if(jQuery(this).prop(\'checked\') === true){
                     check_status = true;
                }
                else {
                    check_status = false;
                }
                jQuery(\'input[name = "remote_storage"]\').prop(\'checked\', false);
                if(check_status === true){
                    jQuery(this).prop(\'checked\', true);
                 }
                else {
                    jQuery(this).prop(\'checked\', false);
                }
            });
            </script>';
    return $html;
}

add_action('instawp_storage_add_tab', 'instawp_add_tab_storage_list', 10);
add_action('instawp_storage_add_tab', 'instawp_add_tab_storage_edit', 11);
add_action('instawp_storage_add_page', 'instawp_add_page_storage_list', 10);
add_action('instawp_storage_add_page', 'instawp_add_page_storage_edit', 11);
//add_filter('instawp_storage_list','instawp_storage_list',10);
?>



<script>
    function select_remote_storage(evt, storage_page_id)
    {
        var i, tablecontent, tablinks;
        tablinks = document.getElementsByClassName("storage-providers");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace("storage-providers-active", "");
        }
        evt.currentTarget.className += " storage-providers-active";

        jQuery(".storage-account-page").hide();
        jQuery("#"+storage_page_id).show();
    }
    function switchstorageTabs(evt,contentName,storage_page_id) {
        // Declare all variables
        var i, tabcontent, tablinks;

        // Get all elements with class="table-list-content" and hide them
        tabcontent = document.getElementsByClassName("storage-tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Get all elements with class="table-nav-tab" and remove the class "nav-tab-active"
        tablinks = document.getElementsByClassName("storage-nav-tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" nav-tab-active", "");
        }

        // Show the current tab, and add an "storage-menu-active" class to the button that opened the tab
        document.getElementById(contentName).style.display = "block";
        evt.currentTarget.className += " nav-tab-active";

        var top = jQuery('#'+storage_page_id).offset().top-jQuery('#'+storage_page_id).height();
        jQuery('html, body').animate({scrollTop:top}, 'slow');
    }
</script>