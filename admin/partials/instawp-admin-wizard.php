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
$admin_email = get_option( 'admin_email' );
$connect_ids      = get_option('instawp_connect_id_options', array());
$instawp_finish_upload      = get_option('instawp_finish_upload', array());
$screen_1_show = '';
$screen_2_show = '';
$screen_3_show = '';
$screen_4_show = '';
if ( ! empty($connect_ids) ) {
    $screen_1_show = false;
    $screen_3_show = true;
}
else {
    $screen_1_show = true;
    $screen_3_show = false;
}
if ( ! empty($instawp_finish_upload) ) {
    $screen_1_show = false;
    $screen_3_show = false;  
    $screen_4_show = true;  
}
?>
<div class="wrap instawp-connect-wizard">
    <div class="postbox instawp-wizard-container wizard-screen-1 <?php echo ($screen_1_show) ? 'instawp-show' : ''; ?>" id="instawp-wizard-screen-1">
        
        <?php do_action( 'instawp_admin_wizard_img' ); ?>

        <h3><?php echo esc_html__('Creating a Staging Site','instawp-connect') ?> </h3>

        <div class="instawp-confirm-wrap" style="display: none;">
            
            <input type="checkbox" name="email_confirm" id="instawp_email_confirm">
            <label for="instawp_email_confirm">
                <?php /* translators: %s: Email */ ?>
                <?php echo sprintf( esc_html__('I agree to share email id ( %s ) with InstaWP','instawp-connect') ,esc_attr( $admin_email ) ); ?>                
            </label>    
        </div>
        
        <?php 
        $btn_args = array(
         'label' => __('Create a Site','instawp-connect'),
     );

        do_action('instawp_admin_wizard_btn',$btn_args); 

        ?>
        
    </div>
    <div class="postbox instawp-wizard-container wizard-screen-2" id="instawp-wizard-screen-2">
        
        <?php do_action( 'instawp_admin_wizard_img' ); ?>

        <h3><?php echo esc_html__('Creating a Staging Site','instawp-connect') ?> </h3>
        
        <?php 
        $btn_args = array(
           'label' => __('Connect','instawp-connect'),
           'data'  => 'connect',
       );
        do_action('instawp_admin_wizard_btn',$btn_args); 
        do_action('instawp_admin_wizard_prev_btn',null); 

        ?>
        
    </div>
    <div class="postbox instawp-wizard-container wizard-screen-3 <?php echo ($screen_3_show) ? 'instawp-show' : ''; ?>" id="instawp-wizard-screen-3">
        <?php 
        do_action('instawp_before_setup_page');
        include_once INSTAWP_PLUGIN_DIR .'/admin/partials/instawp-admin-display.php';
        do_action('instawp_display_page');
        ?>
        <?php do_action( 'instawp_admin_wizard_img' ); ?>

        <h3><?php echo esc_html__('Creating a Staging Site','instawp-connect') ?> </h3>
        <div class="postbox" id="instawp_postbox_backup_percent" style="display: none;">
            <div class="action-progress-bar" id="instawp_action_progress_bar">
                <div class="action-progress-bar-percent" id="instawp_action_progress_bar_percent" style="height:24px;width:0;"></div>
            </div>
            <div id="instawp_estimate_backup_info" style="float: left; display: none;">
                <div class="backup-basic-info"><span class="instawp-element-space-right"><?php esc_html_e('Database Size:', 'instawp-connect'); ?></span><span id="instawp_backup_database_size">N/A</span></div>
                <div class="backup-basic-info"><span class="instawp-element-space-right"><?php esc_html_e('File Size:', 'instawp-connect'); ?></span><span id="instawp_backup_file_size">N/A</span></div>
            </div>
            <div id="instawp_estimate_upload_info" style="float: left; display: none;">
                <div class="backup-basic-info"><span class="instawp-element-space-right"><?php esc_html_e('Total Size:', 'instawp-connect'); ?></span><span>N/A</span></div>
                <div class="backup-basic-info"><span class="instawp-element-space-right"><?php esc_html_e('Uploaded:', 'instawp-connect'); ?></span><span>N/A</span></div>
                <div class="backup-basic-info"><span class="instawp-element-space-right"><?php esc_html_e('Speed:', 'instawp-connect'); ?></span><span>N/A</span></div>
            </div>
            <div style="float: left;">
                <div class="backup-basic-info"><span class="instawp-element-space-right"><?php esc_html_e('Network Connection:', 'instawp-connect'); ?></span><span>N/A</span></div>
            </div>
            <div style="clear:both;"></div>
            <div style="margin-left:10px; float: left; width:100%;"><p id="instawp_current_doing"></p></div>
            <div style="clear: both;"></div>
            
            
            
        </div>
        <?php 
        
        $btn_args = array(
           'button_1' => array(
              'label' => __('Quick','instawp-connect'),
              'desc'  => __('Coming Soon','instawp-connect'),
              'data'  => __('data','instawp-connect'),
          ),
           'button_2' => array(
              'label' => __('Full','instawp-connect'),
              'desc'  => __('Copies Media Files','instawp-connect'),
              'data'  => __('data','instawp-connect'),
          ),
       );
        do_action('instawp_admin_wizard_two_btn',$btn_args); 
        do_action('instawp_admin_wizard_prev_btn',null); 

        ?>
        
        <?php 
        $backuplist = InstaWP_Backuplist::get_backuplist();
        $display_backup_count = InstaWP_Setting::get_max_backup_count();
        ?>
        <div class="backup-tab-content instawp_tab_backup" id="page-backups">
            <div style="margin-top:10px; margin-bottom:10px;">
                <?php
                $descript = '';
                $descript = apply_filters('instawp_download_backup_descript',$descript);
                echo wp_kses_post( $descript );
                ?>
            </div>
            <div style="margin-bottom:10px;">
                <?php
                $descript = '';
                $descript = apply_filters('instawp_restore_website_descript',$descript);
                echo wp_kses_post( $descript );
                ?>
            </div>
            <div style="clear:both;"></div>
            <?php
            do_action('instawp_rescan_backup_list');
            ?>
            <table class="wp-list-table widefat plugins" id="instawp_backuplist_table" style="border-collapse: collapse;">
                <thead>
                    <tr class="backup-list-head" style="border-bottom: 0;">
                        <td></td>
                        <th><?php esc_html_e( 'Backup','instawp-connect'); ?></th>
                        <th><?php esc_html_e( 'Storage','instawp-connect'); ?></th>
                        <th><?php esc_html_e( 'Download','instawp-connect'); ?></th>
                        <th><?php esc_html_e( 'Restore', 'instawp-connect'); ?></th>
                        <th><?php esc_html_e( 'Delete','instawp-connect'); ?></th>
                    </tr>
                </thead>
                <tbody class="instawp-backuplist" id="instawp_backup_list">
                    <?php
                    $html = '';
                    $html = apply_filters('instawp_add_backup_list', $html);
                    echo wp_kses_post( $html );
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th><input name="" type="checkbox" id="backup_list_all_check" value="1" /></th>
                        <th class="row-title" colspan="5"><a onclick="instawp_delete_backups_inbatches();" style="cursor: pointer;"><?php esc_html_e('Delete the selected backups', 'instawp-connect'); ?></a></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        

        
        
        <script>
            jQuery(document).on('click','#instawp_quickbackup_btn',function(){
                
                instawp_clear_notice('instawp_backup_notice');
                instawp_start_backup();
            });
            
            function instawp_start_backup(){
                var bcheck=true;
                var bdownloading=false;
                if(m_downloading_id !== '') {
                    var descript = '<?php esc_html_e('This request might delete the backup being downloaded, are you sure you want to continue?', 'instawp-connect'); ?>';
                    var ret = confirm(descript);
                    if (ret === true) {
                        bcheck=true;
                        bdownloading=true;
                    }
                    else{
                        bcheck=false;
                    }
                }
                if(bcheck) {
                    var backup_data = instawp_ajax_data_transfer('backup');
                    backup_data = JSON.parse(backup_data);
                    jQuery('input:radio[option=backup_ex]').each(function() {
                        if(jQuery(this).prop('checked'))
                        {
                            var key = jQuery(this).prop('name');
                            var value = jQuery(this).prop('value');
                            var json = new Array();
                            if(value == 'local'){
                                json['local']='1';
                                
                            }
                        }
                        jQuery.extend(backup_data, json);
                    });
                    backup_data = JSON.stringify(backup_data);
                    console.log(backup_data);
                    var ajax_data = {
                        'action': 'instawp_prepare_backup',
                        'backup': backup_data
                    };
                    instawp_control_backup_lock();
                    jQuery('#instawp_backup_cancel_btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                    jQuery('#instawp_backup_log_btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                    jQuery('#instawp_postbox_backup_percent').show();
                    jQuery('#instawp_current_doing').html('Ready to backup. Progress: 0%, running time: 0second.');
                    var percent = '0%';
                    jQuery('#instawp_action_progress_bar_percent').css('width', percent);
                    jQuery('#instawp_backup_database_size').html('N/A');
                    jQuery('#instawp_backup_file_size').html('N/A');
                    jQuery('#instawp_current_doing').html('');
                    instawp_completed_backup = 1;
                    instawp_prepare_backup = true;
                    instawp_post_request(ajax_data, function (data) {
                        try {
                            var jsonarray = jQuery.parseJSON(data);
                            if (jsonarray.result === 'failed') {
                                instawp_delete_ready_task(jsonarray.error);
                            }
                            else if (jsonarray.result === 'success') {
                                if(bdownloading) {
                                    m_downloading_id = '';
                                }
                                m_backup_task_id = jsonarray.task_id;

                                jQuery('#instawp_backup_list').html('');
                                jQuery('#instawp_backup_list').append(jsonarray.html);

                                instawp_backup_now(m_backup_task_id);
                        /*
                         var descript = '';
                        if (jsonarray.check.alert_db === true || jsonarray.check.alter_files === true) {
                            descript = 'The database (the dumping SQL file) might be too large, backing up the database may run out of server memory and result in a backup failure.\n' +
                                'One or more files might be too large, backing up the file(s) may run out of server memory and result in a backup failure.\n' +
                                'Click OK button and continue to back up.';
                            var ret = confirm(descript);
                            if (ret === true) {
                                jQuery('#instawp_backup_list').html('');
                                jQuery('#instawp_backup_list').append(jsonarray.html);
                                instawp_backup_now(m_backup_task_id);
                            }
                            else {
                                jQuery('#instawp_backup_cancel_btn').css({'pointer-events': 'auto', 'opacity': '1'});
                                jQuery('#instawp_backup_log_btn').css({'pointer-events': 'auto', 'opacity': '1'});
                                instawp_control_backup_unlock();
                                jQuery('#instawp_postbox_backup_percent').hide();
                            }
                        }
                        else{
                            jQuery('#instawp_backup_list').html('');
                            jQuery('#instawp_backup_list').append(jsonarray.html);
                            instawp_backup_now(jsonarray.task_id);
                        } */
                    }
                }
                catch (err) {
                    instawp_delete_ready_task(err);
                }
            }, function (XMLHttpRequest, textStatus, errorThrown) {
                //var error_message = instawp_output_ajaxerror('preparing the backup', textStatus, errorThrown);
                var error_message=instawplion.backup_calc_timeout;//'Calculating the size of files, folder and database timed out. If you continue to receive this error, please go to the plugin settings, uncheck \'Calculate the size of files, folder and database before backing up\', save changes, then try again.';
                instawp_delete_ready_task(error_message);
            });
                }
            }
            
            function instawp_backup_now(task_id){
                var ajax_data = {
                    'action': 'instawp_backup_now',
                    'task_id': task_id
                };
                task_retry_times = 0;
                m_need_update=true;
                instawp_post_request(ajax_data, function(data){
                }, function(XMLHttpRequest, textStatus, errorThrown) {
                });
            }
            
            function instawp_delete_backup_task(task_id){
                var ajax_data = {
                    'action': 'instawp_delete_task',
                    'task_id': task_id
                };
                instawp_post_request(ajax_data, function(data){}, function(XMLHttpRequest, textStatus, errorThrown) {
                });
            }
            
            function instawp_control_backup_lock(){
                jQuery('#instawp_quickbackup_btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_transfer_btn').css({'pointer-events': 'none', 'opacity': '0.4'});
            }
            
            function instawp_control_backup_unlock(){
                jQuery('#instawp_quickbackup_btn').css({'pointer-events': 'auto', 'opacity': '1'});
                jQuery('#instawp_transfer_btn').css({'pointer-events': 'auto', 'opacity': '1'});
            }
            
            function instawp_delete_ready_task(error){
                var ajax_data={
                    'action': 'instawp_delete_ready_task'
                };
                instawp_post_request(ajax_data, function (data) {
                    try {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success') {
                            instawp_add_notice('Backup', 'Error', error);
                            instawp_control_backup_unlock();
                            jQuery('#instawp_postbox_backup_percent').hide();
                        }
                    }
                    catch(err){
                        instawp_add_notice('Backup', 'Error', err);
                        instawp_control_backup_unlock();
                        jQuery('#instawp_postbox_backup_percent').hide();
                    }
                }, function (XMLHttpRequest, textStatus, errorThrown) {
                    setTimeout(function () {
                        instawp_delete_ready_task(error);
                    }, 3000);
                });
            }
        </script>

        
    </div>
    <div class="postbox instawp-wizard-container wizard-screen-4 <?php echo ($screen_4_show) ? 'instawp-show' : ''; ?>" id="instawp-wizard-screen-4">
        
        <?php 
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
            $connect_id                 = $connect_ids['data']['id'];
            $staging_sites      = get_option('instawp_staging_list', array());
            if ( isset( $staging_sites[ $connect_id ] ) ) {

                $staging_site = $staging_sites[ $connect_id ];
                if ( isset( $staging_site['data']['status'] ) && $staging_site['data']['status'] == 1 ) {
                  $site_name = $staging_site['data']['wp'][0]['site_name']; 
                  $wp_admin_url = $staging_site['data']['wp'][0]['wp_admin_url']; 
                  $wp_username = $staging_site['data']['wp'][0]['wp_username']; 
                  $wp_password = $staging_site['data']['wp'][0]['wp_password']; 
                  $wp_password = $staging_site['data']['wp'][0]['wp_password']; 
                  $auto_login_hash = $staging_site['data']['wp'][0]['auto_login_hash']; 
                  $auto_login_url = add_query_arg( array( 'site' => $auto_login_hash ), $auto_login_url );
              }   
          }        
      }
      if ( empty( $staging_site) ) {
        $progress_class = '';
        $site_class = 'instawp-display-none';
    }
    else {
        $progress_class = 'instawp-display-none';
        $site_class = '';
    }
    do_action( 'instawp_admin_wizard_img',$progress_class );
    ?>

    <div class="instawp-site-details-heading <?php echo esc_attr( $site_class); ?>">
        <span>
            <strong> <?php echo esc_html__('Congrats!','instawp-connect') ?>  </strong> <?php echo esc_html__('Staging Site Is Created!','instawp-connect') ?> 
        </span> 
    </div>
    
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
                <?php $start_over = admin_url( "admin.php?page=instawp-connect" );?>
                <a  class="instawp-wizard-btn start-over" target="_blank" href="<?php echo esc_url($start_over); ?>">
                    <?php echo esc_html__('Start Over','instawp-connect'); ?> 
                </a>  
            </div>
        </div>
    </div>        
</div>
</div>
<script type="text/javascript">
    jQuery(document).on('click','.instawp-wizard-btn-js',function(){
        $parent = jQuery(this).parents('.instawp-wizard-container');
        if (typeof jQuery(this).data('connect') !== 'undefined') {
            var admin_url = instawp_ajax_object.admin_url;
            admin_url += 'admin.php?page=instawp-settings';
            jQuery.ajax({
                type: 'POST',
                url: instawp_ajax_object.ajax_url,
                data: {
                    action: "instawp_connect",
                    nonce: instawp_ajax_object.ajax_nonce
                },
                success: function (response) {
                    
                    var obj = JSON.parse(response);
                    console.log(obj);
                    if(obj.error == true) {
                        var msg = '<span style="color:red">'+obj.message+'.</span>&nbsp;&nbsp;&nbsp;<a href="'+admin_url+'" style="color:#005E54" >Configure API Key</a>';
                    }
                    else {
                        setTimeout(function () {
                            $parent.removeClass('instawp-show');
                            $parent.next().addClass('instawp-show');
                        }, 2000);
                        
                        var msg = '<span style="color:green">'+obj.message+'</span>';
                    }
                    jQuery('.instawp-err-msg').html(msg);
                },
                error: function (errorThrown) {
                    console.log('error');
                }
            });
            
        }
        else {
            $parent.removeClass('instawp-show');
            $parent.next().addClass('instawp-show');
        }
        
    });
    jQuery(document).on('click','.instawp-wizard-prev-btn',function(){
        $parent = jQuery(this).parents('.instawp-wizard-container');
        
        $parent.removeClass('instawp-show');
        $parent.prev().addClass('instawp-show');
    });
    
</script>
