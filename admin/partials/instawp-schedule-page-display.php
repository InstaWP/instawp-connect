<?php

function instawp_schedule_settings() {
    ?>
    <tr>
        <td class="row-title instawp-backup-settings-table tablelistcolumn"><label for="tablecell"><?php esc_html_e('Schedule Settings', 'instawp-connect'); ?></label></td>
        <td class="tablelistcolumn">
            <div id="storage-brand-3">
                <div>
                    <div>
                        <div class="postbox schedule-tab-block">
                            <label for="instawp_schedule_enable">
                                <input option="schedule" name="enable" type="checkbox" id="instawp_schedule_enable" />
                                <span><?php esc_html_e( 'Enable backup schedule', 'instawp-connect' ); ?></span>
                            </label><br>
                            <label>
                                <div style="float: left;">
                                    <input type="checkbox" disabled />
                                    <span class="instawp-element-space-right" style="color: #ddd;"><?php esc_html_e('Enable Incremental Backup', 'instawp-connect'); ?></span>
                                </div>
                                <div style="float: left; height: 32px; line-height: 32px;">
                                    <span class="instawp-feature-pro">
                                        <a href="https://docs.instawp.com/instawp-backup-pro-incremental-backups.html"><?php esc_html_e('Pro feature: learn more', 'instawp-connect'); ?></a>
                                    </span>
                                </div>
                                <div style="clear: both;"></div>
                            </label>
                            <label>
                                <div style="float: left;">
                                    <input type="checkbox" disabled />
                                    <span class="instawp-element-space-right" style="color: #ddd;"><?php esc_html_e('Advanced Schedule', 'instawp-connect'); ?></span>
                                </div>
                                <div style="float: left; height: 32px; line-height: 32px;">
                                    <span class="instawp-feature-pro">
                                        <a href="https://docs.instawp.com/instawp-backup-pro-schedule-overview.html"><?php esc_html_e('Pro feature: learn more', 'instawp-connect'); ?></a>
                                    </span>
                                </div>
                                <div style="clear: both;"></div>
                            </label>
                            <div style="clear: both;"></div>
                            <div>
                                <?php
                                $notice = '';
                                $notice = apply_filters('instawp_schedule_notice',$notice);
                                echo wp_kses_post( $notice );
                                ?>
                            </div>
                        </div>
                        <div class="postbox schedule-tab-block">
                            <fieldset>
                                <legend class="screen-reader-text"><span>input type="radio"</span></legend>
                                <?php
                                $time = '';
                                $time = apply_filters('instawp_schedule_time',$time);
                                echo wp_kses_post($time);
                                ?>
                            </fieldset>
                        </div>
                    </div>
                </div>
                <div class="postbox schedule-tab-block" id="instawp_schedule_backup_type">
                    <div>
                        <div>
                            <fieldset>
                                <legend class="screen-reader-text"><span>input type="radio"</span></legend>
                                <?php
                                $backup_type = '';
                                $backup_type = apply_filters('instawp_schedule_backup_type',$backup_type);
                                echo wp_kses_post( $backup_type );
                                ?>
                            </fieldset>
                        </div>
                        <div style="clear:both;"></div>
                    </div>
                </div>
                <div class="postbox schedule-tab-block" id="instawp_schedule_remote_storage">
                    <div id="instawp_schedule_backup_local_remote">
                        <?php
                        $html = '';
                        $html = apply_filters('instawp_schedule_local_remote',$html);
                        echo wp_kses_post( $html );
                        ?>
                    </div>
                    <div id="schedule_upload_storage" style="cursor:pointer;" title="<?php esc_html_e('Highlighted icon illuminates that you have choosed a remote storage to store backups', 'instawp-connect'); ?>">
                        <?php
                        $pic = '';
                        $pic = apply_filters('instawp_schedule_add_remote_pic',$pic);
                        echo wp_kses_post( $pic );
                        ?>
                    </div>
                </div>
                <div class="postbox schedule-tab-block">
                    <div style="float:left; color: #ddd; margin-right: 10px;">
                        <?php esc_html_e('+ Add another schedule', 'instawp-connect'); ?>
                    </div>
                    <span class="instawp-feature-pro">
                        <a href="https://docs.instawp.com/instawp-backup-pro-creating-schedules.html"><?php esc_html_e('Pro feature: learn more', 'instawp-connect'); ?></a>
                    </span>
                </div>
            </div>
        </td>
    </tr>
    <script>
        
    </script>
    <?php
}

function instawp_schedule_notice( $html ) {
    $offset = get_option('gmt_offset');
    $time = '00:00:00';
    $utime = strtotime($time) + $offset * 60 * 60;
    $html = '<p>1) '.__('Scheduled job will start at <strong>UTC</strong> time:', 'instawp-connect').'&nbsp'.date('H:i:s', $utime).'</p>';
    $html .= '<p>2) '.__('Being subjected to mechanisms of PHP, a scheduled backup task for your site will be triggered only when the site receives at least a visit at any page.', 'instawp-connect').'</p>';
    return $html;
}

function instawp_schedule_backup_type( $html ) {
    $html = '<label>';
    $html .= '<input type="radio" option="schedule" name="backup_type" value="files+db"/>';
    $html .= '<span>'.__('Database + Files (WordPress Files)', 'instawp-connect').'</span>';
    $html .= '</label><br>';

    $html .= '<label>';
    $html .= '<input type="radio" option="schedule" name="backup_type" value="files"/>';
    $html .= '<span>'.__('WordPress Files (Exclude Database)', 'instawp-connect').'</span>';
    $html .= '</label><br>';

    $html .= '<label>';
    $html .= '<input type="radio" option="schedule" name="backup_type" value="db"/>';
    $html .= '<span>'.__('Only Database', 'instawp-connect').'</span>';
    $html .= '</label><br>';

    $html .= '<label>';
    $html .= '<div style="float: left;">';
    $html .= '<input type="radio" disabled />';
    $html .= '<span class="instawp-element-space-right" style="color: #ddd;">'.__('Custom', 'instawp-connect').'</span>';
    $html .= '</div>';
    $html .= '<div style="float: left; height: 32px; line-height: 32px;">';
    $html .= '<span class="instawp-feature-pro">';
    $html .= '<a href="https://docs.instawp.com/instawp-backup-pro-customize-what-to-backup-for-schedule.html" style="text-decoration: none;">'.__('Pro feature: learn more', 'instawp-connect').'</a>';
    $html .= '</span>';
    $html .= '</div>';
    $html .= '</label><br>';
    return $html;
}



add_action('instawp_schedule_add_cell','instawp_schedule_settings',11);

add_filter('instawp_schedule_backup_type','instawp_schedule_backup_type');
add_filter('instawp_schedule_notice','instawp_schedule_notice',10);
?>

