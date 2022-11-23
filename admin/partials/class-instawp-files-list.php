<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class InstaWP_Files_List extends WP_List_Table
{
    public $page_num;
    public $file_list;
    public $backup_id;

    public function __construct( $args = array() ) {
        parent::__construct(
            array(
                'plural' => 'files',
                'screen' => 'files',
            )
        );
    }

    protected function get_table_classes() {
        return array( 'widefat striped' );
    }

    public function print_column_headers( $with_id = true ) {
        list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

        if ( ! empty($columns['cb']) ) {
            static $cb_counter = 1;
            $columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __('Select All', 'instawp-connect') . '</label>'
                . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox"/>';
            $cb_counter++;
        }

        foreach ( $columns as $column_key => $column_display_name ) {
            $class = array( 'manage-column', "column-$column_key" );

            if ( in_array( $column_key, $hidden ) ) {
                $class[] = 'hidden';
            }

            if ( $column_key === $primary ) {
                $class[] = 'column-primary';
            }

            if ( $column_key === 'cb' ) {
                $class[] = 'check-column';
            }

            $tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
            $scope = ( 'th' === $tag ) ? 'scope="col"' : '';
            $id    = $with_id ? "id='$column_key'" : '';

            if ( ! empty( $class ) ) {
                $class = "class='" . join( ' ', $class ) . "'";
            }
            $html = "<$tag $scope $id $class>$column_display_name</$tag>";
            echo wp_kses_post( $html );
        }
    }

    public function get_columns() {
        $columns = array();
        $columns['instawp_file'] = __( 'File', 'instawp-connect' );
        return $columns;
    }

    public function _column_instawp_file( $file ) {
        $html = '<td class="tablelistcolumn">
                    <div style="padding:0 0 10px 0;">
                        <span>'. $file['key'].'</span>
                    </div>
                    <div class="instawp-download-status" style="padding:0;">';
        if ( $file['status'] == 'completed' ) {
            $html .= '<span>'.__('File Size: ', 'instawp-connect').'</span><span class="instawp-element-space-right instawp-download-file-size">'.$file['size'].'</span><span class="instawp-element-space-right">|</span><span class=" instawp-element-space-right instawp-ready-download"><a style="cursor: pointer;">'. __('Download', 'instawp-connect').'</a></span>';
        }
        elseif ( $file['status'] == 'file_not_found' ) {
            $html .= '<span>' . __('File not found', 'instawp-connect') . '</span>';
        }
        elseif ( $file['status'] == 'need_download' ) {
            $html .= '<span>'.__('File Size: ', 'instawp-connect').'</span><span class="instawp-element-space-right instawp-download-file-size">'.$file['size'].'</span><span class="instawp-element-space-right">|</span><span class="instawp-element-space-right"><a class="instawp-download" style="cursor: pointer;">'. __('Prepare to Download', 'instawp-connect').'</a></span>';
        }
        elseif ( $file['status'] == 'running' ) {
            $html .= '<div class="instawp-element-space-bottom">
                        <span class="instawp-element-space-right">' . __('Retriving (remote storage to web server)', 'instawp-connect') . '</span><span class="instawp-element-space-right">|</span><span>' . __('File Size: ', 'instawp-connect') . '</span><span class="instawp-element-space-right instawp-download-file-size">'.$file['size'].'</span><span class="instawp-element-space-right">|</span><span>'. __('Downloaded Size: ', 'instawp-connect').'</span><span>'.$file['downloaded_size'].'</span>
                    </div>
                    <div style="width:100%;height:10px; background-color:#dcdcdc;">
                        <div style="background-color:#0085ba; float:left;width:'.$file['progress_text'].'%;height:10px;"></div>
                    </div>';
        }
        elseif ( $file['status'] == 'timeout' ) {
            $html .= '<div class="instawp-element-space-bottom">
                        <span>Download timeout, please retry.</span>
                    </div>
                    <div>
                        <span>'.__('File Size: ', 'instawp-connect').'</span><span class="instawp-element-space-right instawp-download-file-size">'.$file['size'].'</span><span class="instawp-element-space-right">|</span><span class="instawp-element-space-right"><a class="instawp-download" style="cursor: pointer;">'. __('Prepare to Download', 'instawp-connect').'</a></span>
                    </div>';
        }
        elseif ( $file['status'] == 'error' ) {
            $html .= '<div class="instawp-element-space-bottom">
                        <span>'.$file['error'].'</span>
                    </div>
                    <div>
                        <span>'.__('File Size: ', 'instawp-connect').'</span><span class="instawp-element-space-right instawp-download-file-size">'.$file['size'].'</span><span class="instawp-element-space-right">|</span><span class="instawp-element-space-right"><a class="instawp-download" style="cursor: pointer;">'. __('Prepare to Download', 'instawp-connect').'</a></span>
                    </div>';
        }

        $html .= '</div></td>';
        echo wp_kses_post( $html );
        //size
    }

    public function set_files_list( $file_list,$backup_id,$page_num=1 ) {
        $this->file_list = $file_list;
        $this->backup_id = $backup_id;
        $this->page_num = $page_num;
    }

    public function get_pagenum() {
        if ( $this->page_num == 'first' ) {
            $this->page_num = 1;
        }
        elseif ( $this->page_num == 'last' ) {
            $this->page_num = $this->_pagination_args['total_pages'];
        }
        $pagenum = $this->page_num ? $this->page_num : 0;

        if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] ) {
            $pagenum = $this->_pagination_args['total_pages'];
        }

        return max( 1, $pagenum );
    }

    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $total_items = sizeof($this->file_list);

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => 10,
            )
        );
    }

    public function has_items() {
        return ! empty($this->file_list);
    }

    public function display_rows() {
        $this->_display_rows($this->file_list);
    }

    private function _display_rows( $file_list ) {
        $page = $this->get_pagenum();

        $page_file_list = array();
        $count = 0;
        while ( $count < $page ) {
            $page_file_list = array_splice( $file_list, 0, 10);
            $count++;
        }
        foreach ( $page_file_list as $key => $file ) {
            $file['key'] = $key;
            $this->single_row($file);
        }
    }

    public function single_row( $file ) {
        ?>
        <tr slug="<?php echo esc_attr( $file['key'] )?>">
            <?php $this->single_row_columns( $file ); ?>
        </tr>
        <?php
    }

    protected function pagination( $which ) {
        if ( empty( $this->_pagination_args ) ) {
            return;
        }

        $total_items     = $this->_pagination_args['total_items'];
        $total_pages     = $this->_pagination_args['total_pages'];
        $infinite_scroll = false;
        if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
            $infinite_scroll = $this->_pagination_args['infinite_scroll'];
        }

        if ( 'top' === $which && $total_pages > 1 ) {
            $this->screen->render_screen_reader_content( 'heading_pagination' );
        }
        /* translators: %s: search term */
        $output = '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items, 'instawp-connect' ), number_format_i18n( $total_items ) ) . '</span>';

        $current              = $this->get_pagenum();

        $page_links = array();

        $total_pages_before = '<span class="paging-input">';
        $total_pages_after  = '</span></span>';

        $disable_first = $disable_last = $disable_prev = $disable_next = false;

        if ( $current == 1 ) {
            $disable_first = true;
            $disable_prev  = true;
        }
        if ( $current == 2 ) {
            $disable_first = true;
        }
        if ( $current == $total_pages ) {
            $disable_last = true;
            $disable_next = true;
        }
        if ( $current == $total_pages - 1 ) {
            $disable_last = true;
        }

        if ( $disable_first ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<div class='first-page button'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></div>",
                __( 'First page', 'instawp-connect' ),
                '&laquo;'
            );
        }

        if ( $disable_prev ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<div class='prev-page button' value='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></div>",
                $current,
                __( 'Previous page', 'instawp-connect' ),
                '&lsaquo;'
            );
        }

        if ( 'bottom' === $which ) {
            $html_current_page  = $current;
            $total_pages_before = '<span class="screen-reader-text">' . __( 'Current Page', 'instawp-connect' ) . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
        } else {
            $html_current_page = sprintf(
                "%s<input class='current-page' id='current-page-selector-filelist' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
                '<label for="current-page-selector-filelist" class="screen-reader-text">' . __( 'Current Page', 'instawp-connect' ) . '</label>',
                $current,
                strlen( $total_pages )
            );
        }
        $html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
        /* translators: %s: search term */
        $page_links[]     = $total_pages_before . sprintf( _x( '%1$s of %2$s', 'paging', 'instawp-connect' ), $html_current_page, $html_total_pages ) . $total_pages_after;

        if ( $disable_next ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<div class='next-page button' value='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></div>",
                $current,
                __( 'Next page', 'instawp-connect' ),
                '&rsaquo;'
            );
        }

        if ( $disable_last ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<div class='last-page button'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></div>",
                __( 'Last page', 'instawp-connect' ),
                '&raquo;'
            );
        }

        $pagination_links_class = 'pagination-links';
        if ( ! empty( $infinite_scroll ) ) {
            $pagination_links_class .= ' hide-if-js';
        }
        $output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';

        if ( $total_pages ) {
            $page_class = $total_pages < 2 ? ' one-page' : '';
        } else {
            $page_class = ' no-pages';
        }
        $this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

        echo wp_kses_post( $this->_pagination );
    }

    protected function display_tablenav( $which ) {
        $css_type = '';
        if ( 'top' === $which ) {
            wp_nonce_field( 'bulk-' . $this->_args['plural'] );
            $css_type = 'margin: 0 0 10px 0';
        }
        elseif ( 'bottom' === $which ) {
            $css_type = 'margin: 10px 0 0 0';
        }

        $total_pages     = $this->_pagination_args['total_pages'];
        if ( $total_pages > 1 ) {
            ?>
            <div class="tablenav <?php echo esc_attr( $which ); ?>" style="<?php esc_attr($css_type); ?>">
                <?php
                $this->extra_tablenav( $which );
                $this->pagination( $which );
                ?>

                <br class="clear" />
            </div>
            <?php
        }
    }

    public function display() {
        $singular = $this->_args['singular'];

        $this->display_tablenav( 'top' );

        $this->screen->render_screen_reader_content( 'heading_list' );
        ?>
        <table class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
            <thead>
            <tr>
                <?php $this->print_column_headers(); ?>
            </tr>
            </thead>

            <tbody id="the-list"
                <?php
                if ( $singular ) {
                    echo wp_kses_post( " data-wp-lists='list:$singular'" );
                }
                ?>
            >
            <?php $this->display_rows_or_placeholder(); ?>
            </tbody>

        </table>
        <?php
        $this->display_tablenav( 'bottom' );
    }
}


function instawp_add_backup_type( $html, $type_name ) {
    $html .= '<label>
                    <input type="radio" option="backup" name="'.$type_name.'" value="files+db" checked />
                    <span>'.__( 'Database + Files (WordPress Files)', 'instawp-connect' ).'</span>
                </label><br>
                <label>
                <input type="radio" id="instawp_backup_local" option="backup_ex" name="local_remote" value="local" checked />
                <span>'.__( "Save Backups to Local", "instawp-connect" ).'</span>
            </label>
                ';
    return $html;
}

function instawp_backup_do_js(){
    global $instawp_plugin;
    $backup_task = array();
    $backup_task = $instawp_plugin->_list_tasks($backup_task, false);
    $general_setting = InstaWP_Setting::get_setting(true, "");
    if ( isset( $general_setting['options']['instawp_common_setting']['estimate_backup'] ) && $general_setting['options']['instawp_common_setting']['estimate_backup'] == 0 ) {
        ?>
        jQuery('#instawp_estimate_backup_info').hide();
        <?php
    }
    if ( empty($backup_task['backup']['data']) ) {
        ?>
        jQuery('#instawp_postbox_backup_percent').hide();
        jQuery('#instawp_backup_cancel_btn').css({'pointer-events': 'auto', 'opacity': '1'});
        jQuery('#instawp_quickbackup_btn').css({'pointer-events': 'auto', 'opacity': '1'});

        jQuery('#instawp_quick_backup_btn').css({'pointer-events': 'auto', 'opacity': '1'});
        <?php
    }
    else {
        foreach ( $backup_task['backup']['data'] as $key => $value ) {
            if ( $value['status']['str'] === 'running' ) {
                $percent = $value['data']['progress'];
                ?>
                jQuery('#instawp_postbox_backup_percent').show();
                jQuery('#instawp_backup_cancel_btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_quickbackup_btn').css({'pointer-events': 'none', 'opacity': '0.4'});

                jQuery('#instawp_quick_backup_btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_action_progress_bar_percent').css('width', <?php echo esc_html( $percent ); ?>+'%');
                jQuery('#instawp_backup_database_size').html('<?php echo esc_html( $value['size']['db_size'] ); ?>');
                jQuery('#instawp_backup_file_size').html('<?php echo esc_html( $value['size']['files_size']['sum'] ); ?>');
                <?php
                if ( $value['is_canceled'] == false ) {
                    $descript = $value['data']['descript'];
                    if ( $value['data']['type'] ) {
                        $find_str = 'Total size: ';
                        if ( stripos($descript, $find_str) != false ) {
                            $pos = stripos($descript, $find_str);
                            $descript = substr($descript, 0, $pos);
                        }
                    }
                    $backup_running_time = $value['data']['running_stamp'];
                    $output = '';
                    foreach ( array(
						86400 => 'day',
						3600  => 'hour',
						60    => 'min',
						1     => 'second',
					) as $key => $value ) {
                        if ($backup_running_time >= $key) $output .= floor($backup_running_time / $key) . $value;
                        $backup_running_time %= $key;
                    }
                    if ( $output == '' ) {
                        $output = 0;
                    }
                    ?>
                    jQuery('#instawp_current_doing').html('<?php echo wp_kses_post( $descript ); ?> Progress: <?php echo esc_html( $percent ); ?>%, running time: <?php echo esc_html( $output ); ?>');
                    <?php
                }
                else {
                    ?>
                    jQuery('#instawp_current_doing').html('The backup will be canceled after backing up the current chunk ends.');
                    <?php
                }
            }
        }
    }
}




function instawp_backuppage_load_backuplist( $backuplist_array ) {
    $backuplist_array['list_backup'] = array(
		'index'     => '1',
		'tab_func'  => 'instawp_backuppage_add_tab_backup',
		'page_func' => 'instawp_backuppage_add_page_backup',
	);
    $backuplist_array['list_log'] = array(
		'index'     => '3',
		'tab_func'  => 'instawp_backuppage_add_tab_log',
		'page_func' => 'instawp_backuppage_add_page_log',
	);
    $backuplist_array['list_restore'] = array(
		'index'     => '4',
		'tab_func'  => 'instawp_backuppage_add_tab_restore',
		'page_func' => 'instawp_backuppage_add_page_restore',
	);
    $backuplist_array['list_download'] = array(
		'index'     => '5',
		'tab_func'  => 'instawp_backuppage_add_tab_downlaod',
		'page_func' => 'instawp_backuppage_add_page_downlaod',
	);
    return $backuplist_array;
}

function instawp_backuppage_add_tab_backup(){
    ?>
    <a href="#" id="instawp_tab_backup" class="nav-tab backup-nav-tab nav-tab-active" onclick="switchrestoreTabs(event,'page-backups')" style="display: none;"><?php esc_html_e('Backups', 'instawp-connect'); ?></a>
    <?php
}

function instawp_backuppage_add_tab_log(){
    ?>
    <a href="#" id="instawp_tab_backup_log" class="nav-tab backup-nav-tab delete" onclick="switchrestoreTabs(event,'page-log')" style="display: none;">
        <div style="margin-right: 15px;"><?php esc_html_e('Log', 'instawp-connect'); ?></div>
        <div class="nav-tab-delete-img">
            <img src="<?php echo esc_url(plugins_url( 'images/delete-tab.png', __FILE__ )); ?>" style="vertical-align:middle; cursor:pointer;" onclick="instawp_close_tab(event, 'instawp_tab_backup_log', 'backup', 'instawp_tab_backup');" />
        </div>
    </a>
    <?php
}

function instawp_backuppage_add_tab_restore(){
    ?>
    <a href="#" id="instawp_tab_restore" class="nav-tab backup-nav-tab delete" onclick="switchrestoreTabs(event,'page-restore')" style="display: none;">
        <div style="margin-right: 15px;"><?php esc_html_e('Restore', 'instawp-connect'); ?></div>
        <div class="nav-tab-delete-img">
            <img src="<?php echo esc_url(plugins_url( 'images/delete-tab.png', __FILE__ )); ?>" style="vertical-align:middle; cursor:pointer;" onclick="instawp_close_tab(event, 'instawp_tab_restore', 'backup', 'instawp_tab_backup');" />
        </div>
    </a>
    <?php
}

function instawp_backuppage_add_tab_downlaod(){
    ?>
    <a href="#" id="instawp_tab_download" class="nav-tab backup-nav-tab delete" onclick="switchrestoreTabs(event,'page-download')" style="display: none;">
        <div style="margin-right: 15px;"><?php esc_html_e('Download', 'instawp-connect'); ?></div>
        <div class="nav-tab-delete-img">
            <img src="<?php echo esc_url(plugins_url( 'images/delete-tab.png', __FILE__ )); ?>" style="vertical-align:middle; cursor:pointer;" onclick="instawp_close_tab(event, 'instawp_tab_download', 'backup', 'instawp_tab_backup');" />
        </div>
    </a>
    <?php
}

function instawp_backuppage_add_page_backup(){
    ?>
    <script>
        function instawp_retrieve_backup_list(){
            var ajax_data = {
                'action': 'instawp_get_backup_list'
            };
            instawp_post_request(ajax_data, function(data){
                try {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success') {
                        jQuery('#instawp_backup_list').html('');
                        jQuery('#instawp_backup_list').append(jsonarray.html);
                    }
                }
                catch(err){
                    alert(err);
                }
            },function(XMLHttpRequest, textStatus, errorThrown) {
                setTimeout(function () {
                    instawp_retrieve_backup_list();
                }, 3000);
            });
        }

        function instawp_handle_backup_data(data){
            try {
                var jsonarray = jQuery.parseJSON(data);
                if (jsonarray.result === 'success') {
                    jQuery('#instawp_backup_list').html('');
                    jQuery('#instawp_backup_list').append(jsonarray.html);
                }
                else if(jsonarray.result === 'failed'){
                    alert(jsonarray.error);
                }
            }
            catch(err){
                alert(err);
            }
        }

        function instawp_click_check_backup(backup_id, list_name){
            var name = "";
            var all_check = true;
            jQuery('#'+list_name+' tr').each(function (i) {
                jQuery(this).children('th').each(function (j) {
                    if(j === 0) {
                        var id = jQuery(this).find("input[type=checkbox]").attr("id");
                        if (id === backup_id) {
                            name = jQuery(this).parent().children('td').eq(0).find("img").attr("name");
                            if (name === "unlock") {
                                if (jQuery(this).find("input[type=checkbox]").prop('checked') === false) {
                                    all_check = false;
                                }
                            }
                            else {
                                jQuery(this).find("input[type=checkbox]").prop('checked', false);
                                all_check = false;
                            }
                        }
                        else {
                            if (jQuery(this).find("input[type=checkbox]").prop('checked') === false) {
                                all_check = false;
                            }
                        }
                    }
                });
            });
            if(all_check === true){
                jQuery('#backup_list_all_check').prop('checked', true);
            }
            else{
                jQuery('#backup_list_all_check').prop('checked', false);
            }
        }

        function instawp_set_backup_lock(backup_id, lock_status){
            var max_count_limit = '<?php //echo $display_backup_count; ?>';
            var check_status = true;
            if(lock_status === "lock"){
                var lock=0;
            }
            else{
                var lock=1;
                var check_can_lock=false;
                var baackup_list_count = jQuery('#instawp_backup_list').find('tr').length;
                if(baackup_list_count >= max_count_limit) {
                    jQuery('#instawp_backup_list').find('tr').find('td:eq(0)').find('span:eq(0)').each(function () {
                        var span_id = jQuery(this).attr('id');
                        span_id = span_id.replace('instawp_lock_', '');
                        if (span_id !== backup_id) {
                            var name = jQuery(this).find('img:eq(0)').attr('name');
                            if (name === 'unlock') {
                                check_can_lock = true;
                                return false;
                            }
                        }
                    });
                    if (!check_can_lock) {
                        check_status = false;
                        alert('The locked backups will reach the maximum limits of retained backups, which causes being unable to create a new backup. So, please unlock one of them and continue.');
                    }
                }
            }
            if(check_status) {
                var ajax_data = {
                    'action': 'instawp_set_security_lock',
                    'backup_id': backup_id,
                    'lock': lock
                };
                instawp_post_request(ajax_data, function (data) {
                    try {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success') {
                            jQuery('#instawp_lock_' + backup_id).html(jsonarray.html);
                        }
                    }
                    catch (err) {
                        alert(err);
                    }
                }, function (XMLHttpRequest, textStatus, errorThrown) {
                    var error_message = instawp_output_ajaxerror('setting up a lock for the backup', textStatus, errorThrown);
                    alert(error_message);
                });
            }
        }

        function instawp_read_log(action, param, log_type){
            var tab_id = '';
            var content_id = '';
            var ajax_data = '';
            var show_page = '';
            if(typeof param === 'undefined')    param = '';
            if(typeof log_type === 'undefined')    log_type = '';
            switch(action){
                case 'instawp_view_backup_task_log':
                    ajax_data = {
                        'action':action,
                        'id':running_backup_taskid
                    };
                    tab_id = 'instawp_tab_backup_log';
                    content_id = 'instawp_display_log_content';
                    show_page = 'backup_page';
                    break;
                case 'instawp_read_last_backup_log':
                    var ajax_data = {
                        'action': action,
                        'log_file_name': param
                    };
                    tab_id = 'instawp_tab_backup_log';
                    content_id = 'instawp_display_log_content';
                    show_page = 'backup_page';
                    break;
                case 'instawp_view_backup_log':
                    var ajax_data={
                        'action':action,
                        'id':param
                    };
                    tab_id = 'instawp_tab_backup_log';
                    content_id = 'instawp_display_log_content';
                    show_page = 'backup_page';
                    break;
                case 'instawp_view_log':
                    var ajax_data={
                        'action':action,
                        'id':param,
                        'log_type':log_type
                    };
                    tab_id = 'instawp_tab_read_log';
                    content_id = 'instawp_read_log_content';
                    show_page = 'log_page';
                    break;
                default:
                    break;
            }
            jQuery('#'+tab_id).show();
            jQuery('#'+content_id).html("");
            if(show_page === 'backup_page'){
                //instawp_click_switch_backup_page(tab_id);
                instawp_click_switch_page('backup', tab_id, true);
            }
            else if(show_page === 'log_page') {
                instawp_click_switch_page('wrap', tab_id, true);
            }
            instawp_post_request(ajax_data, function(data){
                instawp_show_log(data, content_id);
            }, function(XMLHttpRequest, textStatus, errorThrown) {
                var div = 'Reading the log failed. Please try again.';
                jQuery('#instawp_display_log_content').html(div);
            });
        }

        /*function instawp_initialize_download(backup_id, list_name){
            instawp_reset_backup_list(list_name);
            jQuery('#instawp_download_loading_'+backup_id).addClass('is-active');
            tmp_current_click_backupid = backup_id;
            var ajax_data = {
                'action':'instawp_init_download_page',
                'backup_id':backup_id
            };
            instawp_post_request(ajax_data, function(data){
                try {
                    var jsonarray = jQuery.parseJSON(data);
                    jQuery('#instawp_download_loading_'+backup_id).removeClass('is-active');
                    if (jsonarray.result === 'success') {
                        jQuery('#instawp_file_part_' + backup_id).html("");
                        var i = 0;
                        var file_not_found = false;
                        var file_name = '';
                        jQuery.each(jsonarray.files, function (index, value) {
                            i++;
                            file_name = index;
                            if (value.status === 'need_download') {
                                jQuery('#instawp_file_part_' + backup_id).append(value.html);
                                //tmp_current_click_backupid = '';
                            }
                            else if (value.status === 'running') {
                                if (m_downloading_file_name === file_name) {
                                    instawp_lock_download(tmp_current_click_backupid);
                                }
                                jQuery('#instawp_file_part_' + backup_id).append(value.html);
                            }
                            else if (value.status === 'completed') {
                                if (m_downloading_file_name === file_name) {
                                    instawp_unlock_download(tmp_current_click_backupid);
                                    m_downloading_id = '';
                                    m_downloading_file_name = '';
                                }
                                jQuery('#instawp_file_part_' + backup_id).append(value.html);
                                //tmp_current_click_backupid = '';
                            }
                            else if (value.status === 'timeout') {
                                if (m_downloading_file_name === file_name) {
                                    instawp_unlock_download(tmp_current_click_backupid);
                                    m_downloading_id = '';
                                    m_downloading_file_name = '';
                                }
                                jQuery('#instawp_file_part_' + backup_id).append(value.html);
                                //tmp_current_click_backupid = '';
                            }
                            else if (value.status === 'file_not_found') {
                                instawp_unlock_download(tmp_current_click_backupid);
                                instawp_reset_backup_list(list_name);
                                file_not_found = true;
                                alert("Download failed, file not found. The file might has been moved, renamed or deleted. Please verify the file exists and try again.");
                                //tmp_current_click_backupid = '';
                                return false;
                            }
                        });
                        if (file_not_found === false) {
                            jQuery('#instawp_file_part_' + backup_id).append(jsonarray.place_html);
                        }
                    }
                }
                catch(err){
                    alert(err);
                    jQuery('#instawp_download_loading_'+backup_id).removeClass('is-active');
                }
            },function(XMLHttpRequest, textStatus, errorThrown){
                jQuery('#instawp_download_loading_'+backup_id).removeClass('is-active');
                var error_message = instawp_output_ajaxerror('initializing download information', textStatus, errorThrown);
                alert(error_message);
            });
        }*/

        function instawp_reset_backup_list(list_name){
            jQuery('#'+list_name+' tr').each(function(i){
                jQuery(this).children('td').each(function (j) {
                    if (j == 2) {
                        var backup_id = jQuery(this).parent().children('th').find("input[type=checkbox]").attr("id");
                        var download_btn = '<div id="instawp_file_part_' + backup_id + '" style="float:left;padding:10px 10px 10px 0px;">' +
                            '<div style="cursor:pointer;" onclick="instawp_initialize_download(\'' + backup_id + '\', \''+list_name+'\');" title="<?php esc_html_e('Prepare to download the backup', 'instawp-connect'); ?>">' +
                            '<img id="instawp_download_btn_' + backup_id + '" src="' + instawp_plugurl + '/admin/partials/images/download.png" style="vertical-align:middle;" />Download' +
                            '<div class="spinner" id="instawp_download_loading_' + backup_id + '" style="float:right;width:auto;height:auto;padding:10px 180px 10px 0;background-position:0 0;"></div>' +
                            '</div>' +
                            '</div>';
                        jQuery(this).html(download_btn);
                    }
                });
            });
        }

        function instawp_lock_download(backup_id){
            jQuery('#instawp_backup_list tr').each(function(i){
                jQuery(this).children('td').each(function (j) {
                    if (j == 2) {
                        jQuery(this).css({'pointer-events': 'none', 'opacity': '0.4'});
                    }
                });
            });
        }

        function instawp_unlock_download(backup_id){
            jQuery('#instawp_backup_list tr').each(function(i){
                jQuery(this).children('td').each(function (j) {
                    if (j == 2) {
                        jQuery(this).css({'pointer-events': 'auto', 'opacity': '1'});
                    }
                });
            });
        }

        /**
         * Start downloading backup
         *
         * @param part_num  - The part number for the download object
         * @param backup_id - The unique ID for the backup
         * @param file_name - File name
         */
        function instawp_prepare_download(part_num, backup_id, file_name){
            var ajax_data = {
                'action': 'instawp_prepare_download_backup',
                'backup_id':backup_id,
                'file_name':file_name
            };
            var progress = '0%';
            jQuery("#"+backup_id+"-text-part-"+part_num).html("<a>Retriving(remote storage to web server)</a>");
            jQuery("#"+backup_id+"-progress-part-"+part_num).css('width', progress);
            task_retry_times = 0;
            m_need_update = true;
            instawp_lock_download(backup_id);
            m_downloading_id = backup_id;
            tmp_current_click_backupid = backup_id;
            m_downloading_file_name = file_name;
            instawp_post_request(ajax_data, function(data)
            {
            }, function(XMLHttpRequest, textStatus, errorThrown)
            {
            }, 0);
        }

        /**
         * Download backups to user's computer.
         *
         * @param backup_id     - The unique ID for the backup
         * @param backup_type   - The types of the backup
         * @param file_name     - File name
         */
        function instawp_download(backup_id, backup_type, file_name){
            instawp_location_href=true;
            location.href =ajaxurl+'?_wpnonce='+instawp_ajax_object.ajax_nonce+'&action=instawp_download_backup&backup_id='+backup_id+'&download_type='+backup_type+'&file_name='+file_name;
        }

        function instawp_initialize_restore(backup_id, backup_time, backup_type, restore_type='backup'){
            var time_type = 'backup';
            var log_type = '';
            var tab_type = '';
            var page_type = 'backup';
            if(restore_type == 'backup'){
                time_type = 'backup';
                log_type = '';
                tab_type = '';
                page_type = 'backup';
            }
            else if(restore_type == 'transfer'){
                time_type = 'transfer';
                log_type = 'transfer_';
                tab_type = 'add_';
                page_type = 'migrate';
            }
            instawp_restore_backup_type = backup_type;
            jQuery('#instawp_restore_'+time_type+'_time').html(backup_time);
            m_restore_backup_id = backup_id;
            jQuery('#instawp_restore_'+log_type+'log').html("");
            jQuery('#instawp_'+tab_type+'tab_restore').show();
            instawp_click_switch_page(page_type, 'instawp_'+tab_type+'tab_restore', true);
            instawp_init_restore_data(restore_type);
        }

        function click_dismiss_restore_check_notice(obj){
            instawp_display_restore_check = false;
            jQuery(obj).parent().remove();
        }

        /**
         * This function will initialize restore information
         *
         * @param backup_id - The unique ID for the backup
         */
        function instawp_init_restore_data(restore_type)
        {
            instawp_resotre_is_migrate=0;
            var restore_method = '';
            if(restore_type == 'backup'){
                restore_method = '';
            }
            else if(restore_type == 'transfer'){
                restore_method = 'transfer_';
            }
            jQuery('#instawp_replace_domain').prop('checked', false);
            jQuery('#instawp_keep_domain').prop('checked', false);
            jQuery('#instawp_restore_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
            jQuery('#instawp_clean_'+restore_method+'restore').css({'pointer-events': 'none', 'opacity': '0.4'});
            jQuery('#instawp_rollback_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
            jQuery('#instawp_download_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
            jQuery('#instawp_restore_'+restore_method+'part').show();
            jQuery('#instawp_clean_'+restore_method+'part').hide();
            jQuery('#instawp_rollback_'+restore_method+'part').hide();
            jQuery('#instawp_download_'+restore_method+'part').hide();

            jQuery('#instawp_init_restore_data').addClass('is-active');
            var ajax_data = {
                'action':'instawp_init_restore_page',
                'backup_id':m_restore_backup_id
            };
            instawp_post_request(ajax_data, function(data){
                try {
                    var jsonarray = jQuery.parseJSON(data);
                    var init_status = false;
                    if(jsonarray.result === 'success') {
                        jQuery('#instawp_restore_'+restore_method+'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                        jQuery('#instawp_download_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                        jQuery('#instawp_restore_'+restore_method+'part').show();
                        jQuery('#instawp_download_'+restore_method+'part').hide();
                        instawp_restore_need_download = false;
                        init_status = true;
                    }
                    else if (jsonarray.result === "need_download"){
                        init_status = true;
                        instawp_restore_download_array = new Array();
                        var download_num = 0;
                        jQuery.each(jsonarray.files, function (index, value)
                        {
                            if (value.status === "need_download")
                            {
                                instawp_restore_download_array[download_num] = new Array('file_name', 'size', 'md5');
                                instawp_restore_download_array[download_num]['file_name'] = index;
                                instawp_restore_download_array[download_num]['size'] = value.size;
                                instawp_restore_download_array[download_num]['md5'] = value.md5;
                                download_num++;
                            }
                        });
                        instawp_restore_download_index=0;
                        instawp_restore_need_download = true;
                        jQuery('#instawp_restore_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                        jQuery('#instawp_download_'+restore_method+'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                        jQuery('#instawp_restore_'+restore_method+'part').hide();
                        jQuery('#instawp_download_'+restore_method+'part').show();
                    }
                    else if (jsonarray.result === "failed") {
                        jQuery('#instawp_init_restore_data').removeClass('is-active');
                        instawp_display_restore_msg(jsonarray.error, restore_type);
                    }

                    if(init_status){
                        if(jsonarray.max_allow_packet_warning != false || jsonarray.memory_limit_warning != false) {
                            if(!instawp_display_restore_check) {
                                instawp_display_restore_check = true;
                                var output = '';
                                if(jsonarray.max_allow_packet_warning != false){
                                    output += "<p>" + jsonarray.max_allow_packet_warning + "</p>";
                                }
                                if(jsonarray.memory_limit_warning != false){
                                    output += "<p>" + jsonarray.memory_limit_warning + "</p>";
                                }
                                var div = "<div class='notice notice-warning is-dismissible inline'>" +
                                    output +
                                    "<button type='button' class='notice-dismiss' onclick='click_dismiss_restore_check_notice(this);'>" +
                                    "<span class='screen-reader-text'>Dismiss this notice.</span>" +
                                    "</button>" +
                                    "</div>";
                                jQuery('#instawp_restore_check').append(div);
                            }
                        }
                        jQuery('#instawp_init_restore_data').removeClass('is-active');
                        if (jsonarray.has_exist_restore === 0) {
                            if(instawp_restore_need_download == false) {
                                jQuery('#instawp_restore_' + restore_method + 'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                                jQuery('#instawp_clean_' + restore_method + 'restore').css({'pointer-events': 'none', 'opacity': '0.4'});
                                jQuery('#instawp_rollback_' + restore_method + 'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                                jQuery('#instawp_restore_' + restore_method + 'part').show();
                                jQuery('#instawp_clean_' + restore_method + 'part').hide();
                                jQuery('#instawp_rollback_' + restore_method + 'part').hide();
                                jQuery('#instawp_restore_is_migrate').css({'pointer-events': 'auto', 'opacity': '1'});

                                jQuery('#instawp_restore_is_migrate').hide();
                                jQuery('#instawp_restore_' + restore_method + 'btn').css({'pointer-events': 'auto', 'opacity': '1'});

                                instawp_resotre_is_migrate = jsonarray.is_migrate;

                                if (jsonarray.is_migrate_ui === 1) {
                                    jQuery('#instawp_restore_is_migrate').show()
                                    jQuery('#instawp_replace_domain').prop('checked', false);
                                    jQuery('#instawp_keep_domain').prop('checked', false);
                                }
                                else {
                                    jQuery('#instawp_restore_is_migrate').hide();
                                    jQuery('#instawp_restore_' + restore_method + 'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                                }

                                instawp_interface_flow_control();
                            }
                        }
                        else if (jsonarray.has_exist_restore === 1) {
                            jQuery('#instawp_restore_' + restore_method + 'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                            jQuery('#instawp_clean_' + restore_method + 'restore').css({'pointer-events': 'auto', 'opacity': '1'});
                            jQuery('#instawp_rollback_' + restore_method + 'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                            jQuery('#instawp_restore_'+restore_method+'part').hide();
                            jQuery('#instawp_clean_'+restore_method+'part').show();
                            jQuery('#instawp_rollback_'+restore_method+'part').hide();
                            jQuery('#instawp_restore_is_migrate').hide();
                            instawp_display_restore_msg("An uncompleted restore task exists, please terminate it first.", restore_type);
                        }
                    }
                }
                catch(err){
                    alert(err);
                }
            }, function(XMLHttpRequest, textStatus, errorThrown) {
                jQuery('#instawp_init_restore_data').removeClass('is-active');
                var error_message = instawp_output_ajaxerror('initializing restore information', textStatus, errorThrown);
                instawp_display_restore_msg(error_message, restore_type);
            });
        }

        function instawp_delete_selected_backup(backup_id, list_name){
            var name = '';
            jQuery('#instawp_backup_list tr').each(function(i){
                jQuery(this).children('td').each(function (j) {
                    if (j == 0) {
                        var id = jQuery(this).parent().children('th').find("input[type=checkbox]").attr("id");
                        if(id === backup_id){
                            name = jQuery(this).parent().children('td').eq(0).find('img').attr('name');
                        }
                    }
                });
            });
            var descript = '';
            var force_del = 0;
            var bdownloading = false;
            if(name === 'lock') {
                descript = '<?php esc_html_e('This backup is locked, are you sure to remove it? This backup will be deleted permanently from your hosting (localhost) and remote storages.', 'instawp-connect'); ?>';
                force_del = 1;
            }
            else{
                descript = '<?php esc_html_e('Are you sure to remove this backup? This backup will be deleted permanently.', 'instawp-connect'); ?>';
                force_del = 0;
            }
            if(m_downloading_id === backup_id){
                bdownloading = true;
                descript = '<?php esc_html_e('This request will delete the backup being downloaded, are you sure you want to continue?', 'instawp-connect'); ?>';
                force_del = 1;
            }
            var ret = confirm(descript);
            if(ret === true){
                var ajax_data={
                    'action': 'instawp_delete_backup',
                    'backup_id': backup_id,
                    'force': force_del
                };
                instawp_post_request(ajax_data, function(data){
                    instawp_handle_backup_data(data);
                    if(bdownloading){
                        m_downloading_id = '';
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown) {
                    var error_message = instawp_output_ajaxerror('deleting the backup', textStatus, errorThrown);
                    alert(error_message);
                });
            }
        }
        function instawp_delete_backups_inbatches(){
            var delete_backup_array = new Array();
            var count = 0;
            var bdownloading = false;
            jQuery('#instawp_backup_list tr').each(function (i) {
                jQuery(this).children('th').each(function (j) {
                    if (j == 0) {
                        if(jQuery(this).find('input[type=checkbox]').prop('checked')){
                            delete_backup_array[count] = jQuery(this).find('input[type=checkbox]').attr('id');
                            if(m_downloading_id === jQuery(this).find('input[type=checkbox]').attr('id')){
                                bdownloading = true;
                            }
                            count++;
                        }
                    }
                });
            });
            if( count === 0 ){
                alert('<?php esc_html_e('Please select at least one item.','instawp-connect'); ?>');
            }
            else {
                var descript = '';
                if(bdownloading) {
                    descript = '<?php esc_html_e('This request might delete the backup being downloaded, are you sure you want to continue?', 'instawp-connect'); ?>';
                }
                else{
                    descript = '<?php esc_html_e('Are you sure to remove the selected backups? These backups will be deleted permanently.', 'instawp-connect'); ?>';
                }
                var ret = confirm(descript);
                if (ret === true) {
                    var ajax_data = {
                        'action': 'instawp_delete_backup_array',
                        'backup_id': delete_backup_array
                    };
                    instawp_post_request(ajax_data, function (data) {
                        instawp_handle_backup_data(data);
                        jQuery('#backup_list_all_check').prop('checked', false);
                        if(bdownloading){
                            m_downloading_id = '';
                        }
                    }, function (XMLHttpRequest, textStatus, errorThrown) {
                        var error_message = instawp_output_ajaxerror('deleting the backup', textStatus, errorThrown);
                        alert(error_message);
                    });
                }
            }
        }

        jQuery('#backup_list_all_check').click(function(){
            var name = '';
            if(jQuery('#backup_list_all_check').prop('checked')) {
                jQuery('#instawp_backup_list tr').each(function (i) {
                    jQuery(this).children('th').each(function (j) {
                        if (j == 0) {
                            name = jQuery(this).parent().children('td').eq(0).find("img").attr("name");
                            if(name === 'unlock') {
                                jQuery(this).find("input[type=checkbox]").prop('checked', true);
                            }
                            else{
                                jQuery(this).find("input[type=checkbox]").prop('checked', false);
                            }
                        }
                    });
                });
            }
            else{
                jQuery('#instawp_backup_list tr').each(function (i) {
                    jQuery(this).children('th').each(function (j) {
                        if (j == 0) {
                            jQuery(this).find("input[type=checkbox]").prop('checked', false);
                        }
                    });
                });
            }
        });

        function click_dismiss_restore_notice(obj){
            instawp_display_restore_backup = false;
            jQuery(obj).parent().remove();
        }

        function instawp_click_how_to_restore_backup(){
            if(!instawp_display_restore_backup){
                instawp_display_restore_backup = true;
                var top = jQuery('#instawp_how_to_restore_backup_describe').offset().top-jQuery('#instawp_how_to_restore_backup_describe').height();
                jQuery('html, body').animate({scrollTop:top}, 'slow');
                var div = "<div class='notice notice-info is-dismissible inline'>" +
                    "<p>" + instawplion.restore_step1 + "</p>" +
                    "<p>" + instawplion.restore_step2 + "</p>" +
                    "<p>" + instawplion.restore_step3 + "</p>" +
                    "<button type='button' class='notice-dismiss' onclick='click_dismiss_restore_notice(this);'>" +
                    "<span class='screen-reader-text'>Dismiss this notice.</span>" +
                    "</button>" +
                    "</div>";
                jQuery('#instawp_how_to_restore_backup').append(div);
            }
        }
    </script>
    <?php
}

function instawp_backuppage_add_page_log(){
    ?>
    <div class="backup-tab-content instawp_tab_backup_log" id="page-log" style="display:none;">
        <div class="postbox restore_log" id="instawp_display_log_content">
            <div></div>
        </div>
    </div>
    <?php
}

function instawp_backuppage_add_page_restore(){
    $general_setting = InstaWP_Setting::get_setting(true, "");
    if ( isset($general_setting['options']['instawp_common_setting']['restore_max_execution_time']) ) {
        $restore_max_execution_time = intval($general_setting['options']['instawp_common_setting']['restore_max_execution_time']);
    }
    else {
        $restore_max_execution_time = INSTAWP_RESTORE_MAX_EXECUTION_TIME;
    }
    ?>
    <div class="backup-tab-content instawp_tab_restore" id="page-restore" style="display:none;">
        <div>
            <h3><?php esc_html_e('Restore backup from:', 'instawp-connect'); ?><span id="instawp_restore_backup_time"></span></h3>
            <p><strong><?php esc_html_e('Please do not close the page or switch to other pages when a restore task is running, as it could trigger some unexpected errors.', 'instawp-connect'); ?></strong></p>
            <p><?php esc_html_e('Restore function will replace the current site\'s themes, plugins, uploads, database and/or other content directories with the existing equivalents in the selected backup.', 'instawp-connect'); ?></p>
            <div id="instawp_restore_is_migrate" style="padding-bottom: 10px; display: none;">
                <label >
                    <input type="radio" id="instawp_replace_domain" option="restore" name="restore_domain" value="1" />
                    <?php 
                    /* translators: %s: search term */
                    echo sprintf(esc_html_e('Restore and replace the original domain (URL) with %s (migration)', 'instawp-connect'), esc_url( home_url() ) ); ?>
                </label><br>
                <label >
                    <input type="radio" id="instawp_keep_domain" option="restore" name="restore_domain" value="0" /><?php esc_html_e('Restore and keep the original domain (URL) unchanged', 'instawp-connect'); ?>
                </label><br>
            </div>
            <div>
                <p><strong><?php esc_html_e('Tips:', 'instawp-connect'); ?></strong>&nbsp<?php esc_html_e('If you are migrating a website, the source domain will be replaced with the target domain automatically. For example, if you are migrating a.com to b.com, then a.com will be replaced with b.com during the restore.', 'instawp-connect'); ?></p>
            </div>
            <div id="instawp_restore_check"></div>
            <div class="restore-button-position" id="instawp_restore_part"><input class="button-primary" id="instawp_restore_btn" type="submit" name="restore" value="<?php esc_attr_e( 'Restore', 'instawp-connect' ); ?>" onclick="instawp_start_restore();" /></div>
            <div class="restore-button-position" id="instawp_clean_part"><input class="button-primary" id="instawp_clean_restore" type="submit" name="clear_restore" value="<?php esc_attr_e( 'Terminate', 'instawp-connect' ); ?>" /></div>
            <div class="restore-button-position" id="instawp_rollback_part"><input class="button-primary" id="instawp_rollback_btn" type="submit" name="rollback" value="<?php esc_attr_e( 'Rollback', 'instawp-connect' ); ?>" /></div>
            <div class="restore-button-position" id="instawp_download_part">
                <input class="button-primary" id="instawp_download_btn" type="submit" name="download" value="<?php esc_attr_e( 'Retrieve the backup to localhost', 'instawp-connect' ); ?>" />
                <span><?php esc_html_e('The backup is stored on the remote storage, click on the button to download it to localhost.', 'instawp-connect'); ?></span>
            </div>
            <div class="spinner" id="instawp_init_restore_data" style="float:left;width:auto;height:auto;padding:10px 20px 20px 0;background-position:0 10px;"></div>
        </div>
        <div class="postbox restore_log" id="instawp_restore_log"></div>
    </div>
    <script>
        var restore_max_exection_time = '<?php echo esc_html( $restore_max_execution_time ); ?>';
        restore_max_exection_time = restore_max_exection_time * 1000;
        jQuery('#instawp_clean_restore').click(function(){
            instawp_delete_incompleted_restore();
        });

        jQuery('#instawp_download_btn').click(function(){
            instawp_download_restore_file('backup');
        });

        function instawp_delete_incompleted_restore(restore_type = 'backup'){
            var restore_method = '';
            if(restore_type == 'backup'){
                restore_method = '';
            }
            else if(restore_type == 'transfer'){
                restore_method = 'transfer_';
            }

            var ajax_data={
                'action': 'instawp_delete_last_restore_data'
            };
            jQuery('#instawp_restore_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
            jQuery('#instawp_clean_'+restore_method+'restore').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_rollback_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
            jQuery('#instawp_restore_'+restore_method+'part').hide();
            jQuery('#instawp_clean_'+restore_method+'part').show();
            jQuery('#instawp_rollback_'+restore_method+'part').hide();
            instawp_post_request(ajax_data, function(data) {
                try {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === "success") {
                        instawp_display_restore_msg("The restore task is terminated.", restore_type);
                        instawp_init_restore_data(restore_type);
                    }
                }
                catch(err){
                    alert(err);
                }
            }, function(XMLHttpRequest, textStatus, errorThrown) {
                var error_message = instawp_output_ajaxerror('deleting the last incomplete restore task', textStatus, errorThrown);
                instawp_display_restore_msg(error_message, restore_type);
            });
        }

        function instawp_restore_is_migrate(restore_type){
            var ajax_data = {
                'action': 'instawp_get_restore_file_is_migrate',
                'backup_id': m_restore_backup_id
            };
            var restore_method = '';
            instawp_post_request(ajax_data, function(data)
            {
                try
                {
                    var jsonarray = jQuery.parseJSON(data);
                    if(jsonarray.result === "success")
                    {
                        if (jsonarray.is_migrate_ui === 1)
                        {
                            jQuery('#instawp_restore_is_migrate').show();
                            jQuery('#instawp_replace_domain').prop('checked', false);
                            jQuery('#instawp_keep_domain').prop('checked', false);
                        }
                        else {
                            jQuery('#instawp_restore_is_migrate').hide();
                            jQuery('#instawp_restore_' + restore_method + 'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                        }
                    }
                    else if (jsonarray.result === "failed") {
                        jQuery('#instawp_init_restore_data').removeClass('is-active');
                        instawp_display_restore_msg(jsonarray.error, restore_type);
                    }
                }
                catch(err){
                    alert(err);
                }
            }, function(XMLHttpRequest, textStatus, errorThrown)
            {
                setTimeout(function()
                {
                    instawp_restore_is_migrate(restore_type);
                }, 3000);
            });
        }

        /**
         * This function will start the process of restoring a backup
         */
        function instawp_start_restore(restore_type = 'backup'){
            if(!instawp_restore_sure){
                var descript = '<?php esc_html_e('Are you sure to continue?', 'instawp-connect'); ?>';
                var ret = confirm(descript);
            }
            else{
                ret = true;
            }
            if (ret === true) {
                instawp_restore_sure = true;
                var restore_method = '';
                if (restore_type == 'backup') {
                    restore_method = '';
                }
                else if (restore_type == 'transfer') {
                    restore_method = 'transfer_';
                }
                jQuery('#instawp_restore_' + restore_method + 'log').html("");
                jQuery('#instawp_restore_' + restore_method + 'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_clean_' + restore_method + 'restore').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_rollback_' + restore_method + 'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_restore_' + restore_method + 'part').show();
                jQuery('#instawp_clean_' + restore_method + 'part').hide();
                jQuery('#instawp_rollback_' + restore_method + 'part').hide();
                instawp_restore_lock();
                instawp_restoring = true;
                if (instawp_restore_need_download) {
                    instawp_download_restore_file(restore_type);
                }
                else {
                    instawp_monitor_restore_task(restore_type);
                    if(instawp_resotre_is_migrate==0)
                    {
                        jQuery('input:radio[option=restore]').each(function()
                        {
                            if(jQuery(this).prop('checked'))
                            {
                                var value = jQuery(this).prop('value');
                                if(value == '1')
                                {
                                    instawp_resotre_is_migrate = '1';
                                }
                            }
                        });
                    }

                    instawp_restore(restore_type);
                }
            }
        }

        function instawp_download_restore_file(restore_type)
        {
            var restore_method = '';
            if(restore_type == 'backup'){
                restore_method = '';
            }
            else if(restore_type == 'transfer'){
                restore_method = 'transfer_';
            }

            jQuery('#instawp_download_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
            instawp_restore_lock();
            if(instawp_restore_download_array.length===0)
            {
                instawp_display_restore_msg("Downloading backup file failed. Backup file might be deleted or network doesn't work properly. Please verify the file and confirm the network connection and try again later.", restore_type);
                instawp_restore_unlock();
                return false;
            }

            if(instawp_restore_download_index+1>instawp_restore_download_array.length)
            {
                instawp_display_restore_msg("Download succeeded.", restore_type);
                instawp_restore_is_migrate(restore_type);
                instawp_restore_need_download = false;
                jQuery('#instawp_restore_' + restore_method + 'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                jQuery('#instawp_clean_' + restore_method + 'restore').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_rollback_' + restore_method + 'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_download_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_restore_' + restore_method + 'part').show();
                jQuery('#instawp_clean_' + restore_method + 'part').hide();
                jQuery('#instawp_rollback_' + restore_method + 'part').hide();
                jQuery('#instawp_download_'+restore_method+'part').hide();
                //instawp_start_restore(restore_type);
            }
            else
            {
                instawp_display_restore_msg("Downloading backup file " +  instawp_restore_download_array[instawp_restore_download_index]['file_name'], restore_type);
                instawp_display_restore_msg('', restore_type, instawp_restore_download_index);
                var ajax_data = {
                    'action': 'instawp_download_restore',
                    'backup_id': m_restore_backup_id,
                    'file_name': instawp_restore_download_array[instawp_restore_download_index]['file_name'],
                    'size': instawp_restore_download_array[instawp_restore_download_index]['size'],
                    'md5': instawp_restore_download_array[instawp_restore_download_index]['md5']
                }
                instawp_get_download_restore_progress_retry=0;
                instawp_monitor_download_restore_task(restore_type);
                instawp_post_request(ajax_data, function (data) {
                }, function (XMLHttpRequest, textStatus, errorThrown) {
                }, 0);
            }
        }

        function instawp_monitor_download_restore_task(restore_type)
        {
            var restore_method = '';
            if(restore_type == 'backup'){
                restore_method = '';
            }
            else if(restore_type == 'transfer'){
                restore_method = 'transfer_';
            }

            var ajax_data={
                'action':'instawp_get_download_restore_progress',
                'file_name': instawp_restore_download_array[instawp_restore_download_index]['file_name'],
                'size': instawp_restore_download_array[instawp_restore_download_index]['size'],
                'md5': instawp_restore_download_array[instawp_restore_download_index]['md5']
            };

            instawp_post_request(ajax_data, function(data)
            {
                try
                {
                    var jsonarray = jQuery.parseJSON(data);
                    if(typeof jsonarray ==='object')
                    {
                        if(jsonarray.result === "success")
                        {
                            if(jsonarray.status==='completed')
                            {
                                instawp_display_restore_msg(instawp_restore_download_array[instawp_restore_download_index]['file_name'] + ' download succeeded.', restore_type, instawp_restore_download_index, false);
                                instawp_restore_download_index++;
                                instawp_download_restore_file(restore_type);
                                instawp_restore_unlock();
                            }
                            else if(jsonarray.status==='error')
                            {
                                jQuery('#instawp_restore_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                                jQuery('#instawp_clean_'+restore_method+'restore').css({'pointer-events': 'none', 'opacity': '0.4'});
                                jQuery('#instawp_rollback_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                                jQuery('#instawp_download_'+restore_method+'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                                jQuery('#instawp_restore_'+restore_method+'part').hide();
                                jQuery('#instawp_clean_'+restore_method+'part').hide();
                                jQuery('#instawp_rollback_'+restore_method+'part').hide();
                                jQuery('#instawp_download_'+restore_method+'part').show();
                                var error_message = jsonarray.error;
                                instawp_display_restore_msg(error_message,restore_type,instawp_restore_download_array[instawp_restore_download_index]['file_name'],false);
                                instawp_restore_unlock();
                            }
                            else if(jsonarray.status==='running')
                            {
                                jQuery('#instawp_download_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                                instawp_display_restore_msg(jsonarray.log, restore_type, instawp_restore_download_index, false);
                                setTimeout(function()
                                {
                                    instawp_monitor_download_restore_task(restore_type);
                                }, 3000);
                                instawp_restore_lock();
                            }
                            else if(jsonarray.status==='timeout')
                            {
                                instawp_get_download_restore_progress_retry++;
                                if(instawp_get_download_restore_progress_retry>10)
                                {
                                    jQuery('#instawp_restore_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                                    jQuery('#instawp_clean_'+restore_method+'restore').css({'pointer-events': 'none', 'opacity': '0.4'});
                                    jQuery('#instawp_rollback_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                                    jQuery('#instawp_download_'+restore_method+'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                                    jQuery('#instawp_restore_'+restore_method+'part').hide();
                                    jQuery('#instawp_clean_'+restore_method+'part').hide();
                                    jQuery('#instawp_rollback_'+restore_method+'part').hide();
                                    jQuery('#instawp_download_'+restore_method+'part').show();
                                    var error_message = jsonarray.error;
                                    instawp_display_restore_msg(error_message, restore_type);
                                    instawp_restore_unlock();
                                }
                                else
                                {
                                    setTimeout(function()
                                    {
                                        instawp_monitor_download_restore_task(restore_type);
                                    }, 3000);
                                }
                            }
                            else
                            {
                                setTimeout(function()
                                {
                                    instawp_monitor_download_restore_task(restore_type);
                                }, 3000);
                            }
                        }
                        else
                        {
                            instawp_get_download_restore_progress_retry++;
                            if(instawp_get_download_restore_progress_retry>10)
                            {
                                jQuery('#instawp_restore_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                                jQuery('#instawp_clean_'+restore_method+'restore').css({'pointer-events': 'none', 'opacity': '0.4'});
                                jQuery('#instawp_rollback_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                                jQuery('#instawp_download_'+restore_method+'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                                jQuery('#instawp_restore_'+restore_method+'part').hide();
                                jQuery('#instawp_clean_'+restore_method+'part').hide();
                                jQuery('#instawp_rollback_'+restore_method+'part').hide();
                                jQuery('#instawp_download_'+restore_method+'part').show();
                                var error_message = jsonarray.error;
                                instawp_display_restore_msg(error_message, restore_type);
                                instawp_restore_unlock();
                            }
                            else
                            {
                                setTimeout(function()
                                {
                                    instawp_monitor_download_restore_task(restore_type);
                                }, 3000);
                            }
                        }
                    }
                    else
                    {
                        setTimeout(function()
                        {
                            instawp_monitor_download_restore_task(restore_type);
                        }, 3000);
                    }
                }
                catch(err){
                    setTimeout(function()
                    {
                        instawp_monitor_download_restore_task(restore_type);
                    }, 3000);
                }
            }, function(XMLHttpRequest, textStatus, errorThrown)
            {
                setTimeout(function()
                {
                    instawp_monitor_download_restore_task(restore_type);
                }, 1000);
            });
        }

        /**
         * Monitor restore task.
         */
        function instawp_monitor_restore_task(restore_type){
            var restore_method = '';
            if(restore_type == 'backup'){
                restore_method = '';
            }
            else if(restore_type == 'transfer'){
                restore_method = 'transfer_';
            }

            var ajax_data={
                'action':'instawp_get_restore_progress',
                'instawp_restore' : '1',
                'backup_id':m_restore_backup_id,
            };

            if(instawp_restore_timeout){
                jQuery('#instawp_restore_'+restore_method+'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                jQuery('#instawp_clean_'+restore_method+'restore').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_rollback_'+restore_method+'btn').css({'pointer-events': 'none', 'opacity': '0.4'});
                jQuery('#instawp_restore_'+restore_method+'part').show();
                jQuery('#instawp_clean_'+restore_method+'part').hide();
                jQuery('#instawp_rollback_'+restore_method+'part').hide();
                instawp_restore_unlock();
                instawp_restoring = false;
                instawp_display_restore_msg("Website restore times out.", restore_type);
            }
            else {
                instawp_post_request(ajax_data, function (data) {
                    try {
                        var jsonarray = jQuery.parseJSON(data);
                        if (typeof jsonarray === 'object') {
                            if (jsonarray.result === "success") {
                                jQuery('#instawp_restore_' + restore_method + 'log').html("");
                                while (jsonarray.log.indexOf('\n') >= 0) {
                                    var iLength = jsonarray.log.indexOf('\n');
                                    var log = jsonarray.log.substring(0, iLength);
                                    jsonarray.log = jsonarray.log.substring(iLength + 1);
                                    var insert_log = "<div style=\"clear:both;\">" + log + "</div>";
                                    jQuery('#instawp_restore_' + restore_method + 'log').append(insert_log);
                                    var div = jQuery('#instawp_restore_' + restore_method + 'log');
                                    div[0].scrollTop = div[0].scrollHeight;
                                }

                                if (jsonarray.status === 'wait') {
                                    instawp_restoring = true;
                                    jQuery('#instawp_restore_' + restore_method + 'btn').css({
                                        'pointer-events': 'none',
                                        'opacity': '0.4'
                                    });
                                    jQuery('#instawp_clean_' + restore_method + 'restore').css({
                                        'pointer-events': 'none',
                                        'opacity': '0.4'
                                    });
                                    jQuery('#instawp_rollback_' + restore_method + 'btn').css({
                                        'pointer-events': 'none',
                                        'opacity': '0.4'
                                    });
                                    jQuery('#instawp_restore_' + restore_method + 'part').show();
                                    jQuery('#instawp_clean_' + restore_method + 'part').hide();
                                    jQuery('#instawp_rollback_' + restore_method + 'part').hide();
                                    instawp_restore(restore_type);
                                    setTimeout(function () {
                                        instawp_monitor_restore_task(restore_type);
                                    }, 1000);
                                }
                                else if (jsonarray.status === 'completed') {
                                    instawp_restoring = false;
                                    instawp_restore(restore_type);
                                    instawp_restore_unlock();
                                    alert("<?php esc_html_e('Restore completed successfully.', 'instawp-connect'); ?>");
                                    //location.reload();
                                }
                                else if (jsonarray.status === 'error') {
                                    instawp_restore_unlock();
                                    instawp_restoring = false;
                                    jQuery('#instawp_restore_' + restore_method + 'btn').css({'pointer-events': 'auto', 'opacity': '1'});
                                    alert("<?php esc_html_e('Restore failed.', 'instawp-connect'); ?>");
                                }
                                else {
                                    setTimeout(function () {
                                        instawp_monitor_restore_task(restore_type);
                                    }, 1000);
                                }
                            }
                            else {
                                setTimeout(function () {
                                    instawp_monitor_restore_task(restore_type);
                                }, 1000);
                            }
                        }
                        else {
                            setTimeout(function () {
                                instawp_monitor_restore_task(restore_type);
                            }, 1000);
                        }
                    }
                    catch (err) {
                        setTimeout(function () {
                            instawp_monitor_restore_task(restore_type);
                        }, 1000);
                    }
                }, function (XMLHttpRequest, textStatus, errorThrown) {
                    setTimeout(function () {
                        instawp_monitor_restore_task(restore_type);
                    }, 1000);
                });
            }
        }

        function instawp_restore(restore_type){
            var restore_method = '';
            if(restore_type == 'backup'){
                restore_method = '';
            }
            else if(restore_type == 'transfer'){
                restore_method = 'transfer_';
            }

            var skip_old_site = '1';
            var extend_option = {
                'skip_backup_old_site':skip_old_site,
                'skip_backup_old_database':skip_old_site
            };

            var migrate_option = {
                'is_migrate':instawp_resotre_is_migrate,
            };
            jQuery.extend(extend_option, migrate_option);

            var restore_options = {
                0:'backup_db',
                1:'backup_themes',
                2:'backup_plugin',
                3:'backup_uploads',
                4:'backup_content',
                5:'backup_core'
            };
            jQuery.extend(restore_options, extend_option);
            var json = JSON.stringify(restore_options);
            var ajax_data={
                'action':'instawp_restore',
                'instawp_restore':'1',
                'backup_id':m_restore_backup_id,
                'restore_options':json
            };
            setTimeout(function () {
                instawp_restore_timeout = true;
            }, restore_max_exection_time);
            instawp_post_request(ajax_data, function(data) {
            }, function(XMLHttpRequest, textStatus, errorThrown) {
            });
        }

        function instawp_display_restore_msg(msg, restore_type, div_id, append = true){
            var restore_method = '';
            if(restore_type == 'backup'){
                restore_method = '';
            }
            else if(restore_type == 'transfer'){
                restore_method = 'transfer_';
            }

            if(typeof div_id == 'undefined') {
                var restore_msg = "<div style=\"clear:both;\">" + msg + "</div>";
            }
            else{
                var restore_msg = "<div id=\"restore_file_"+div_id+"\"  style=\"clear:both;\">" + msg + "</div>";
            }
            if(append == true) {
                jQuery('#instawp_restore_'+restore_method+'log').append(restore_msg);
            }
            else{
                if(jQuery('#restore_file_'+div_id).length )
                {
                    jQuery('#restore_file_'+div_id).html(msg);
                }
                else
                {
                    jQuery('#instawp_restore_'+restore_method+'log').append(restore_msg);
                }
            }
            var div = jQuery('#instawp_restore_' + restore_method + 'log');
            div[0].scrollTop = div[0].scrollHeight;
        }

        /**
         * Lock certain operations while a restore task is running.
         */
        function instawp_restore_lock(){
            jQuery('#instawp_postbox_backup_percent').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_postbox_backup').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_postbox_backup_schedule').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_quickbackup_btn').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_quick_backup_btn').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_tab_backup').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_tab_upload').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_tab_backup_log').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_tab_restore').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#page-backups').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#storage-page').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#settings-page').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#debug-page').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#logs-page').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_tab_migrate').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_add_tab_migrate').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_add_tab_import').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_add_tab_key').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_add_tab_log').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_add_tab_restore').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_restore_is_migrate').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_replace_domain').css({'pointer-events': 'none', 'opacity': '1'});
            jQuery('#instawp_keep_domain').css({'pointer-events': 'none', 'opacity': '1'});
        }

        /**
         * Unlock the operations once restore task completed.
         */
        function instawp_restore_unlock(){
            jQuery('#instawp_postbox_backup_percent').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_postbox_backup').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_postbox_backup_schedule').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_quickbackup_btn').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_quick_backup_btn').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_tab_backup').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_tab_upload').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_tab_backup_log').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_tab_restore').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#page-backups').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#storage-page').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#settings-page').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#debug-page').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#logs-page').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_tab_migrate').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_add_tab_migrate').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_add_tab_import').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_add_tab_key').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_add_tab_log').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_add_tab_restore').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_restore_is_migrate').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_replace_domain').css({'pointer-events': 'auto', 'opacity': '1'});
            jQuery('#instawp_keep_domain').css({'pointer-events': 'auto', 'opacity': '1'});
        }
    </script>
    <?php
}

function instawp_backuppage_add_page_downlaod(){
    ?>
    <div class="backup-tab-content instawp_tab_download" id="page-download" style="padding-top: 1em; display:none;">
        <div id="instawp_init_download_info">
            <div style="float: left; height: 20px; line-height: 20px; margin-top: 4px;">Initializing the download info</div>
            <div class="spinner" style="float: left;"></div>
            <div style="clear: both;"></div>
        </div>
        <div class="instawp-element-space-bottom" id="instawp_files_list">
        </div>
    </div>

    <script>
        var instawp_download_files_list = instawp_download_files_list || {};
        instawp_download_files_list.backup_id='';
        instawp_download_files_list.instawp_download_file_array = Array();
        instawp_download_files_list.instawp_download_lock_array = Array();
        instawp_download_files_list.init=function(backup_id) {
            instawp_download_files_list.backup_id=backup_id;
            instawp_download_files_list.instawp_download_file_array.splice(0, instawp_download_files_list.instawp_download_file_array.length);
        };

        instawp_download_files_list.add_download_queue=function(filename) {
            var download_file_size = jQuery("[slug='"+filename+"']").find('.instawp-download-status').find('.instawp-download-file-size').html();
            var tmp_html = '<div class="instawp-element-space-bottom">' +
                '<span class="instawp-element-space-right">Retriving (remote storage to web server)</span><span class="instawp-element-space-right">|</span><span>File Size: </span><span class="instawp-element-space-right">'+download_file_size+'</span><span class="instawp-element-space-right">|</span><span>Downloaded Size: </span><span>0</span>' +
                '</div>' +
                '<div style="width:100%;height:10px; background-color:#dcdcdc;">' +
                '<div style="background-color:#0085ba; float:left;width:0%;height:10px;"></div>' +
                '</div>';
            jQuery("[slug='"+filename+"']").find('.instawp-download-status').html(tmp_html);
            if(jQuery.inArray(filename, instawp_download_files_list.instawp_download_file_array) === -1) {
                instawp_download_files_list.instawp_download_file_array.push(filename);
            }
            var ajax_data = {
                'action': 'instawp_prepare_download_backup',
                'backup_id':instawp_download_files_list.backup_id,
                'file_name':filename
            };
            instawp_post_request(ajax_data, function(data)
            {
            }, function(XMLHttpRequest, textStatus, errorThrown)
            {
            }, 0);

            instawp_download_files_list.check_queue();
        };

        instawp_download_files_list.check_queue=function() {
            if(jQuery.inArray(instawp_download_files_list.backup_id, instawp_download_files_list.instawp_download_lock_array) !== -1){
                return;
            }
            var ajax_data = {
                'action': 'instawp_get_download_progress',
                'backup_id':instawp_download_files_list.backup_id,
            };
            instawp_download_files_list.instawp_download_lock_array.push(instawp_download_files_list.backup_id);
            instawp_post_request(ajax_data, function(data)
            {
                instawp_download_files_list.instawp_download_lock_array.splice(jQuery.inArray(instawp_download_files_list.backup_id, instawp_download_files_list.instawp_download_file_array),1);
                var jsonarray = jQuery.parseJSON(data);
                if (jsonarray.result === 'success')
                {
                    jQuery.each(jsonarray.files,function (index, value)
                    {
                        if(jQuery.inArray(index, instawp_download_files_list.instawp_download_file_array) !== -1) {
                            if(value.status === 'timeout' || value.status === 'completed' || value.status === 'error'){
                                instawp_download_files_list.instawp_download_file_array.splice(jQuery.inArray(index, instawp_download_files_list.instawp_download_file_array),1);
                            }
                            instawp_download_files_list.update_item(index, value);
                        }
                    });

                    //if(jsonarray.need_update)
                    if(instawp_download_files_list.instawp_download_file_array.length > 0)
                    {
                        setTimeout(function()
                        {
                            instawp_download_files_list.check_queue();
                        }, 3000);
                    }
                }
            }, function(XMLHttpRequest, textStatus, errorThrown)
            {
                instawp_download_files_list.instawp_download_lock_array.splice(jQuery.inArray(instawp_download_files_list.backup_id, instawp_download_files_list.instawp_download_file_array),1);
                setTimeout(function()
                {
                    instawp_download_files_list.check_queue();
                }, 3000);
            }, 0);
        };

        instawp_download_files_list.update_item=function(index,file) {
            jQuery("[slug='"+index+"']").find('.instawp-download-status').html(file.html);
        };

        instawp_download_files_list.download_now=function(filename) {
            instawp_location_href=true;
            location.href =ajaxurl+'?_wpnonce='+instawp_ajax_object.ajax_nonce+'&action=instawp_download_backup&backup_id='+instawp_download_files_list.backup_id+'&file_name='+filename;
        };

        function instawp_initialize_download(backup_id, list_name){
            jQuery('#instawp_tab_download').show();
            instawp_click_switch_page('backup', 'instawp_tab_download', true);
            instawp_init_download_page(backup_id);


            /*instawp_reset_backup_list(list_name);
            jQuery('#instawp_download_loading_'+backup_id).addClass('is-active');
            tmp_current_click_backupid = backup_id;
            var ajax_data = {
                'action':'instawp_init_download_page',
                'backup_id':backup_id
            };
            instawp_post_request(ajax_data, function(data){
                try {
                    var jsonarray = jQuery.parseJSON(data);
                    jQuery('#instawp_download_loading_'+backup_id).removeClass('is-active');
                    if (jsonarray.result === 'success') {
                        jQuery('#instawp_file_part_' + backup_id).html("");
                        var i = 0;
                        var file_not_found = false;
                        var file_name = '';
                        jQuery.each(jsonarray.files, function (index, value) {
                            i++;
                            file_name = index;
                            if (value.status === 'need_download') {
                                jQuery('#instawp_file_part_' + backup_id).append(value.html);
                                //tmp_current_click_backupid = '';
                            }
                            else if (value.status === 'running') {
                                if (m_downloading_file_name === file_name) {
                                    instawp_lock_download(tmp_current_click_backupid);
                                }
                                jQuery('#instawp_file_part_' + backup_id).append(value.html);
                            }
                            else if (value.status === 'completed') {
                                if (m_downloading_file_name === file_name) {
                                    instawp_unlock_download(tmp_current_click_backupid);
                                    m_downloading_id = '';
                                    m_downloading_file_name = '';
                                }
                                jQuery('#instawp_file_part_' + backup_id).append(value.html);
                                //tmp_current_click_backupid = '';
                            }
                            else if (value.status === 'timeout') {
                                if (m_downloading_file_name === file_name) {
                                    instawp_unlock_download(tmp_current_click_backupid);
                                    m_downloading_id = '';
                                    m_downloading_file_name = '';
                                }
                                jQuery('#instawp_file_part_' + backup_id).append(value.html);
                                //tmp_current_click_backupid = '';
                            }
                            else if (value.status === 'file_not_found') {
                                instawp_unlock_download(tmp_current_click_backupid);
                                instawp_reset_backup_list(list_name);
                                file_not_found = true;
                                alert("Download failed, file not found. The file might has been moved, renamed or deleted. Please verify the file exists and try again.");
                                //tmp_current_click_backupid = '';
                                return false;
                            }
                        });
                        if (file_not_found === false) {
                            jQuery('#instawp_file_part_' + backup_id).append(jsonarray.place_html);
                        }
                    }
                }
                catch(err){
                    alert(err);
                    jQuery('#instawp_download_loading_'+backup_id).removeClass('is-active');
                }
            },function(XMLHttpRequest, textStatus, errorThrown){
                jQuery('#instawp_download_loading_'+backup_id).removeClass('is-active');
                var error_message = instawp_output_ajaxerror('initializing download information', textStatus, errorThrown);
                alert(error_message);
            });*/
        }

        function instawp_init_download_page(backup_id){
            jQuery('#instawp_files_list').html('');
            jQuery('#instawp_init_download_info').show();
            jQuery('#instawp_init_download_info').find('.spinner').addClass('is-active');
            var ajax_data = {
                'action':'instawp_init_download_page',
                'backup_id':backup_id
            };
            var retry = '<input type="button" class="button button-primary" value="Retry the initialization" onclick="instawp_init_download_page(\''+backup_id+'\');" />';

            instawp_post_request(ajax_data, function(data)
            {
                jQuery('#instawp_init_download_info').hide();
                jQuery('#instawp_init_download_info').find('.spinner').removeClass('is-active');
                try
                {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success')
                    {
                        instawp_download_files_list.init(backup_id);
                        var need_check_queue = false;
                        jQuery.each(jsonarray.files,function (index, value)
                        {
                            if(value.status === 'running'){
                                if(jQuery.inArray(index, instawp_download_files_list.instawp_download_file_array) === -1) {
                                    instawp_download_files_list.instawp_download_file_array.push(index);
                                    need_check_queue = true;
                                }
                            }
                        });
                        if(need_check_queue) {
                            instawp_download_files_list.check_queue();
                        }
                        jQuery('#instawp_files_list').html(jsonarray.html);
                    }
                    else{
                        alert(jsonarray.error);
                        jQuery('#instawp_files_list').html(retry);
                    }
                }
                catch(err)
                {
                    alert(err);
                    jQuery('#instawp_files_list').html(retry);
                }
            },function(XMLHttpRequest, textStatus, errorThrown)
            {
                jQuery('#instawp_init_download_info').hide();
                jQuery('#instawp_init_download_info').find('.spinner').removeClass('is-active');
                var error_message = instawp_output_ajaxerror('initializing download information', textStatus, errorThrown);
                alert(error_message);
                jQuery('#instawp_files_list').html(retry);
            });
        }

        function instawp_download_change_page(page)
        {
            var backup_id=instawp_download_files_list.backup_id;

            var ajax_data = {
                'action':'instawp_get_download_page_ex',
                'backup_id':backup_id,
                'page':page
            };

            jQuery('#instawp_files_list').html('');
            jQuery('#instawp_init_download_info').show();
            jQuery('#instawp_init_download_info').find('.spinner').addClass('is-active');

            instawp_post_request(ajax_data, function(data)
            {
                jQuery('#instawp_init_download_info').hide();
                jQuery('#instawp_init_download_info').find('.spinner').removeClass('is-active');
                try
                {
                    var jsonarray = jQuery.parseJSON(data);
                    if (jsonarray.result === 'success')
                    {
                        jQuery('#instawp_files_list').html(jsonarray.html);
                    }
                    else{
                        alert(jsonarray.error);
                    }
                }
                catch(err)
                {
                    alert(err);
                }
            },function(XMLHttpRequest, textStatus, errorThrown)
            {
                jQuery('#instawp_init_download_info').hide();
                jQuery('#instawp_init_download_info').find('.spinner').removeClass('is-active');
                var error_message = instawp_output_ajaxerror('initializing download information', textStatus, errorThrown);
                alert(error_message);
            });
        }

        jQuery('#instawp_files_list').on("click",'.instawp-download',function()
        {
            var Obj=jQuery(this);
            var file_name=Obj.closest('tr').attr('slug');
            instawp_download_files_list.add_download_queue(file_name);
        });
        jQuery('#instawp_files_list').on("click",'.instawp-ready-download',function()
        {
            var Obj=jQuery(this);
            var file_name=Obj.closest('tr').attr('slug');
            instawp_download_files_list.download_now(file_name);
        });

        jQuery('#instawp_files_list').on("click",'.first-page',function() {
            instawp_download_change_page('first');
        });

        jQuery('#instawp_files_list').on("click",'.prev-page',function() {
            var page=parseInt(jQuery(this).attr('value'));
            instawp_download_change_page(page-1);
        });

        jQuery('#instawp_files_list').on("click",'.next-page',function() {
            var page=parseInt(jQuery(this).attr('value'));
            instawp_download_change_page(page+1);
        });

        jQuery('#instawp_files_list').on("click",'.last-page',function() {
            instawp_download_change_page('last');
        });

        jQuery('#instawp_files_list').on("keypress", '.current-page', function(){
            if(event.keyCode === 13){
                var page = jQuery(this).val();
                instawp_download_change_page(page);
            }
        });
    </script>
    <?php
}

function instawp_backuppage_add_progress_module(){
    ?>
    
    <script>
        jQuery('#instawp_postbox_backup_percent').on("click", "input", function(){
            if(jQuery(this).attr('id') === 'instawp_backup_cancel_btn'){
                instawp_cancel_backup();
            }
            if(jQuery(this).attr('id') === 'instawp_backup_log_btn'){
                instawp_read_log('instawp_view_backup_task_log');
            }
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
    </script>
    <?php
}

function instawp_backuppage_add_backup_module(){
    ?>
    <div class="postbox quickbackup" id="instawp_postbox_backup">
        <?php
        do_action('instawp_backup_module_add_sub');
        ?>
    </div>
   <?php
}

function instawp_backup_module_add_descript(){
    $backupdir = InstaWP_Setting::get_backupdir();
    ?>
    <div style="font-size: 14px; padding: 8px 12px; margin: 0; line-height: 1.4; font-weight: 600;">
        <span style="margin-right: 5px;"><?php esc_html_e( 'Back Up Manually','instawp-connect'); ?></span>
        <span style="margin-right: 5px;">|</span>
        <span style="margin-right: 0;"><a href="<?php echo esc_url('https://wordpress.org/plugins/instawp-imgoptim/'); ?>" style="text-decoration: none;"><?php esc_html_e('Compress images with our image optimization plugin, it\'s free', 'instawp-connect'); ?></a></span>
    </div>
    <div class="quickstart-storage-setting">
        <span class="list-top-chip backup" name="ismerge" value="1" style="margin: 10px 10px 10px 0;"><?php esc_html_e('Local Storage Directory:', 'instawp-connect'); ?></span>
        <span class="list-top-chip" id="instawp_local_storage_path" style="margin: 10px 10px 10px 0;"><?php esc_html(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$backupdir); ?></span>
        <span class="list-top-chip" style="margin: 10px 10px 10px 0;"><a href="#" onclick="instawp_click_switch_page('wrap', 'instawp_tab_setting', true);" style="text-decoration: none;"><?php esc_html_e('rename directory', 'instawp-connect'); ?></a></span>
    </div>
    <?php
}

function instawp_backup_module_add_backup_type(){
    $backup_type = '';
    $type_name = 'backup_files';
    ?>
    <div class="quickstart-archive-block" style="display:none;">
        <fieldset>
            <legend class="screen-reader-text"><span>input type="radio"</span></legend>
            <?php //echo apply_filters('instawp_add_backup_type', $backup_type, $type_name); ?>
            <label style="display: none;">
                <input type="checkbox" option="backup" name="ismerge" value="1" checked />
            </label><br>
            <label>
                    <input type="radio" option="backup" name="<?php echo esc_attr( $type_name ) ?>" value="files+db" checked />
                    <span><?php esc_html_e( 'Database + Files (WordPress Files)', 'instawp-connect' ) ?></span>
                </label><br>
                <label>
                <input type="radio" id="instawp_backup_local" option="backup_ex" name="local_remote" value="local" checked />
                <span><?php esc_html_e( "Save Backups to Local", "instawp-connect" ) ?></span>
            </label>
            
        </fieldset>
    </div>
    <?php
}

function instawp_backup_module_add_send_remote(){
    $pic = '';
    ?>
    <div class="quickstart-storage-block">
        <fieldset>
            <legend class="screen-reader-text"><span>input type="checkbox"</span></legend>
           
            <label>
                <input type="radio" id="instawp_backup_remote" option="backup_ex" name="local_remote" value="remote" />
                <span><?php esc_html_e( 'Send Backup to Remote Storage:', 'instawp-connect' ); ?></span>
            </label><br>
            <div id="upload_storage" style="cursor:pointer;" title="Highlighted icon illuminates that you have choosed a remote storage to store backups">
                <?php 
                $pic = apply_filters('instawp_schedule_add_remote_pic',$pic );
                echo wp_kses_post( $pic ); ?>
            </div>
        </fieldset>
    </div>
    <?php
}

function instawp_backup_module_add_exec(){
    ?>
    
    <?php
}






add_filter('instawp_add_backup_type', 'instawp_add_backup_type', 11, 2);
add_action('instawp_backup_do_js', 'instawp_backup_do_js', 10);



add_filter('instawp_backuppage_load_backuplist', 'instawp_backuppage_load_backuplist', 10);

add_action('instawp_backuppage_add_module', 'instawp_backuppage_add_progress_module', 10);
add_action('instawp_backuppage_add_module', 'instawp_backuppage_add_backup_module', 11);


//add_action('instawp_backup_module_add_sub', 'instawp_backup_module_add_descript');
add_action('instawp_backup_module_add_sub', 'instawp_backup_module_add_backup_type');
//add_action('instawp_backup_module_add_sub', 'instawp_backup_module_add_send_remote');
add_action('instawp_backup_module_add_sub', 'instawp_backup_module_add_exec');


?>