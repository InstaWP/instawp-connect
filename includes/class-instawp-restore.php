<?php

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}

include_once plugin_dir_path( dirname( __FILE__ ) ) .'includes/class-instawp-restore-database.php';
include_once plugin_dir_path( dirname( __FILE__ ) ) .'includes/class-instawp-restore-site.php';
include_once plugin_dir_path( dirname( __FILE__ ) ) .'includes/class-instawp-log.php';
include_once plugin_dir_path( dirname( __FILE__ ) ) .'includes/class-instawp-zipclass.php';

class InstaWP_Restore
{
    public function restore() {
        $general_setting = InstaWP_Setting::get_setting(true, "");
        if ( isset($general_setting['options']['instawp_common_setting']['restore_max_execution_time']) ) {
            $restore_max_execution_time = intval($general_setting['options']['instawp_common_setting']['restore_max_execution_time']);
        }
        else {
            $restore_max_execution_time = INSTAWP_RESTORE_MAX_EXECUTION_TIME;
        }
        @set_time_limit($restore_max_execution_time);

        global $instawp_plugin;

        $next_task = $instawp_plugin->restore_data->get_next_restore_task();

        
        if ( $next_task === false ) {
            $instawp_plugin->restore_data->write_log('Restore task completed.','notice');
            $instawp_plugin->restore_data->update_status(INSTAWP_RESTORE_COMPLETED);
            return array( 'result' => INSTAWP_SUCCESS );
        }
        elseif ( $next_task === INSTAWP_RESTORE_RUNNING ) {
            $instawp_plugin->restore_data->write_log('A restore task is already running.','error');
            return array(
				'result' => INSTAWP_FAILED, 
				'error'  => 'A restore task is already running.',
			);
        }
        else {
            $result = $this -> execute_restore($next_task);
            $instawp_plugin->restore_data->update_sub_task($next_task['index'],$result);

            if ( $result['result'] != INSTAWP_SUCCESS ) {
                $instawp_plugin->restore_data->update_error($result['error']);
                $instawp_plugin->restore_data->write_log($result['error'],'error');
                return array(
					'result' => INSTAWP_FAILED, 
					'error'  => $result['error'],
				);
            }
            else {
                $instawp_plugin->restore_data->update_status(INSTAWP_RESTORE_WAIT);
                return array( 'result' => INSTAWP_SUCCESS );
            }
        }
    }

    function execute_restore( $restore_task ) {
        global $instawp_plugin;

        $backup = $instawp_plugin->restore_data->get_backup_data();
        $backup_item = new InstaWP_Backup_Item($backup);
        $json = $backup_item->get_file_info($restore_task['files'][0]);
        $option = array();
        if ( $json !== false ) {
            $option = $json;
        }
        $option = array_merge($option,$restore_task['option']);
        if ( isset($restore_task['reset']) ) {
            $instawp_plugin->restore_data->write_log('Start resetting '.$restore_task['reset'],'notice');
            $ret = $this->reset_restore($restore_task);
            $instawp_plugin->restore_data->write_log('Finished resetting '.$restore_task['reset'],'notice');
            return $ret;
        }

        $is_type_db = false;
        $is_type_db = apply_filters('instawp_check_type_database', $is_type_db, $option);
        if ( $is_type_db ) {
            $restore_site = new InstaWP_RestoreSite();
            $instawp_plugin->restore_data->write_log('Start restoring '.$restore_task['files'][0],'notice');
            $ret = $restore_site -> restore($option,$restore_task['files']);
            if ( $ret['result'] == INSTAWP_SUCCESS ) {
                if ( isset($option['is_crypt']) && $option['is_crypt'] == '1' ) {
                    $sql_file = $backup_item->get_sql_file($restore_task['files'][0]);
                    $local_path = $instawp_plugin->get_backup_folder(); //$backup_item->get_local_path();
                    $ret = $this->restore_crypt_db($sql_file,$restore_task,$option,$local_path);
                    return $ret;
                }
                $path = $instawp_plugin->get_backup_folder().INSTAWP_DEFAULT_ROLLBACK_DIR.DIRECTORY_SEPARATOR.'instawp_old_database'.DIRECTORY_SEPARATOR;
                //$path = $backup_item->get_local_path().INSTAWP_DEFAULT_ROLLBACK_DIR.DIRECTORY_SEPARATOR.'instawp_old_database'.DIRECTORY_SEPARATOR;
                $sql_file = $backup_item->get_sql_file($restore_task['files'][0]);
                $instawp_plugin->restore_data->write_log('sql file: '.$sql_file,'notice');
                $restore_db = new InstaWP_RestoreDB();
                $check_is_remove = false;
                $check_is_remove = apply_filters('instawp_check_remove_restore_database', $check_is_remove, $option);
                if ( ! $check_is_remove ) {
                    $ret = $restore_db->restore($path, $sql_file, $option);
                    $instawp_plugin->restore_data->write_log('Finished restoring '.$restore_task['files'][0],'notice');
                    $instawp_plugin->restore_data->update_need_unzip_file($restore_task['index'],$restore_task['files']);
                }
                else {
                    $instawp_plugin->restore_data->write_log('Remove file: '.$path.$sql_file, 'notice');
                    $instawp_plugin->restore_data->update_need_unzip_file($restore_task['index'],$restore_task['files']);
                    $ret['result'] = INSTAWP_SUCCESS;
                }
                return $ret;
            }
            else {
                return $ret;
            }
        }
        else {
            $restore_site = new InstaWP_RestoreSite();

            $files = $instawp_plugin->restore_data->get_need_unzip_file($restore_task);
            $json = $backup_item->get_file_info($files[0]);
            $option = array();
            if ( $json !== false ) {
                $option = $json;
            }
            $option = array_merge($option,$restore_task['option']);
            $instawp_plugin->restore_data->write_log('Start restoring '.$files[0],'notice');
            $ret = $restore_site -> restore($option,$files);
            $instawp_plugin->restore_data->update_need_unzip_file($restore_task['index'],$files);
            $instawp_plugin->restore_data->write_log('Finished restoring '.$files[0],'notice');
            return $ret;
        }
    }

    public function restore_crypt_db( $file,$restore_task,$option,$local_path ) {
        $general_setting = InstaWP_Setting::get_setting(true, "");
        if ( isset($general_setting['options']['instawp_common_setting']['encrypt_db']) && $general_setting['options']['instawp_common_setting']['encrypt_db'] == '1' ) {
            global $instawp_plugin;
            $instawp_plugin->restore_data->write_log('Encrypted database detected. Start decrypting database.','notice');

            $general_setting = InstaWP_Setting::get_setting(true, "");
            $password = $general_setting['options']['instawp_common_setting']['encrypt_db_password'];
            if ( empty($password) ) {
                $ret['result'] = INSTAWP_FAILED;
                $ret['error'] = 'Failed to decrypt backup. A password was not set in the plugin settings.';
                return $ret;
            }

            $crypt = new InstaWP_Crypt_File($password);
            $path = $local_path.INSTAWP_DEFAULT_ROLLBACK_DIR.DIRECTORY_SEPARATOR.'instawp_old_database';

            $ret = $crypt->decrypt($path.DIRECTORY_SEPARATOR.$file);
            if ( $ret['result'] == 'success' ) {
                $zip = new InstaWP_ZipClass();
                $all_files = array();
                $all_files[] = $ret['file_path'];
                $file_path = $ret['file_path'];

                $ret = $zip -> extract($all_files,$path);
                if ( $ret['result'] !== 'success' ) {
                    $ret['error'] = 'Failed to unzip the file. Maybe the password is incorrect. Please check your password and try again.';
                    return $ret;
                }

                $instawp_plugin->restore_data->write_log('Decrypting database successfully. Start restoring database.','notice');

                $files = $zip->list_file($file_path);
                unset($zip);
                $sql_file = $files[0]['file_name'];

                $instawp_plugin->restore_data->write_log('sql file: '.$sql_file,'notice');
                $restore_db = new InstaWP_RestoreDB();
                $check_is_remove = false;
                $check_is_remove = apply_filters('instawp_check_remove_restore_database', $check_is_remove, $option);
                if ( ! $check_is_remove ) {
                    $ret = $restore_db->restore($path.DIRECTORY_SEPARATOR, $sql_file, $option);
                    @unlink($file_path);
                    $instawp_plugin->restore_data->write_log('Finished restoring '.$restore_task['files'][0],'notice');
                    $instawp_plugin->restore_data->update_need_unzip_file($restore_task['index'],$restore_task['files']);
                }
                else {
                    @unlink($file_path);
                    $instawp_plugin->restore_data->write_log('Remove file: '.$path.$sql_file, 'notice');
                    $instawp_plugin->restore_data->update_need_unzip_file($restore_task['index'],$restore_task['files']);
                    $ret['result'] = INSTAWP_SUCCESS;
                }
                return $ret;
            }
            else {
                return $ret;
            }
        }
        else {
            $ret['result'] = INSTAWP_FAILED;
            $ret['error'] = 'Failed to decrypt backup. A password was not set in the plugin settings.';
            return $ret;
        }
    }

    public function reset_restore( $restore_task ) {
        $ret['result'] = INSTAWP_SUCCESS;

        if ( $restore_task['reset'] == 'themes' ) {
            return $this->delete_themes();
        }
        elseif ( $restore_task['reset'] == 'deactivate_plugins' ) {
            return $this->deactivate_plugins();
        }
        elseif ( $restore_task['reset'] == 'plugins' ) {
            return $this->delete_plugins();
        }
        elseif ( $restore_task['reset'] == 'uploads' ) {
            return $this->delete_uploads();
        }
        elseif ( $restore_task['reset'] == 'wp_content' ) {
            return $this->delete_wp_content();
        }
        elseif ( $restore_task['reset'] == 'mu_plugins' ) {
            return $this->delete_mu_plugins();
        }
        elseif ( $restore_task['reset'] == 'tables' ) {
            return $this->delete_tables();
        }

        return $ret;
    }

    public function delete_themes() {
        global $instawp_plugin;

        if ( ! function_exists('delete_theme') ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }

        if ( ! function_exists('request_filesystem_credentials') ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $all_themes = wp_get_themes(array( 'errors' => null ));

        $instawp_plugin->restore_data->write_log('Deleting all themes','notice');

        foreach ( $all_themes as $theme_slug => $theme_details ) {
            delete_theme($theme_slug);
        }

        update_option('template', '');
        update_option('stylesheet', '');
        update_option('current_theme', '');

        $ret['result'] = INSTAWP_SUCCESS;
        return $ret;
    }

    public function deactivate_plugins() {
        global $instawp_plugin;

        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! function_exists('request_filesystem_credentials') ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $instawp_plugin->restore_data->write_log('Deactivating all plugins','notice');

        $active_plugins = (array) get_option('active_plugins', array());

        $instawp_backup_pro = 'instawp-backup-pro/instawp-backup-pro.php';
        $instawp_backup = 'instawp-connect/instawp-connect.php';
        $instawp_dashboard = 'instawpdashboard/instawpdashboard.php';

        if ( ($key = array_search($instawp_backup_pro, $active_plugins)) !== false ) {
            unset($active_plugins[ $key ]);
        }

        if ( ($key = array_search($instawp_backup, $active_plugins)) !== false ) {
            unset($active_plugins[ $key ]);
        }

        if ( ($key = array_search($instawp_dashboard, $active_plugins)) !== false ) {
            unset($active_plugins[ $key ]);
        }

        if ( ! empty($active_plugins) ) {
            deactivate_plugins($active_plugins, true, false);
        }

        $ret['result'] = INSTAWP_SUCCESS;
        return $ret;
    }

    public function delete_plugins() {
        global $instawp_plugin;

        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! function_exists('request_filesystem_credentials') ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $instawp_backup_pro = 'instawp-backup-pro/instawp-backup-pro.php';
        $instawp_backup = 'instawp-connect/instawp-connect.php';
        $instawp_dashboard = 'instawpdashboard/instawpdashboard.php';

        $instawp_plugin->restore_data->write_log('Deleting all plugins','notice');

        $all_plugins = get_plugins();
        unset($all_plugins[ $instawp_backup_pro ]);
        unset($all_plugins[ $instawp_backup ]);
        unset($all_plugins[ $instawp_dashboard ]);

        if ( ! empty($all_plugins) ) {
            delete_plugins(array_keys($all_plugins));
        }

        $ret['result'] = INSTAWP_SUCCESS;
        return $ret;
    }

    public function delete_uploads() {
        global $instawp_plugin;

        $upload_dir = wp_get_upload_dir();

        $instawp_plugin->restore_data->write_log('Deleting uploads','notice');

        $this->delete_folder($upload_dir['basedir'], $upload_dir['basedir']);

        $ret['result'] = INSTAWP_SUCCESS;
        return $ret;
    }

    public function delete_folder( $folder, $base_folder ) {
        $files = array_diff(scandir($folder), array( '.', '..' ));

        foreach ( $files as $file ) {
            if ( is_dir($folder . DIRECTORY_SEPARATOR . $file) ) {
                $this->delete_folder($folder . DIRECTORY_SEPARATOR . $file, $base_folder);
            } else {
                @unlink($folder . DIRECTORY_SEPARATOR . $file);
            }
        } // foreach

        if ( $folder != $base_folder ) {
            $tmp = @rmdir($folder);
            return $tmp;
        } else {
            return true;
        }
    }

    public function delete_wp_content() {
        global $instawp_plugin;

        $instawp_plugin->restore_data->write_log('Deleting wp_content','notice');

        $wp_content_dir = trailingslashit(WP_CONTENT_DIR);

        $instawp_backup = InstaWP_Setting::get_backupdir();

        $whitelisted_folders = array( 'mu-plugins', 'plugins', 'themes', 'uploads', $instawp_backup );

        $dirs = glob($wp_content_dir . '*', GLOB_ONLYDIR);
        foreach ( $dirs as $dir ) {
            if ( false == in_array(basename($dir), $whitelisted_folders) ) {
                $this->delete_folder($dir, $dir);
                @rmdir($dir);
            }
        }

        $ret['result'] = INSTAWP_SUCCESS;
        return $ret;
    }

    public function delete_mu_plugins() {
        global $instawp_plugin;

        $instawp_plugin->restore_data->write_log('Deleting mu_plugins','notice');

        $ret['result'] = INSTAWP_SUCCESS;

        $mu_plugins = get_mu_plugins();

        if ( empty($mu_plugins) ) {
            return $ret;
        }

        $this->delete_folder(WPMU_PLUGIN_DIR, WPMU_PLUGIN_DIR);

        return $ret;
    }

    public function delete_tables() {
        global $instawp_plugin,$wpdb;

        $instawp_plugin->restore_data->write_log('Deleting tables','notice');

        $tables = $this->get_tables();

        foreach ( $tables as $table_name ) {
            $wpdb->query('SET foreign_key_checks = 0');
            $wpdb->query('DROP TABLE IF EXISTS ' . $table_name);
            $instawp_plugin->restore_data->write_log('DROP TABLE:'.$table_name,'notice');
        }

        $ret['result'] = INSTAWP_SUCCESS;
        return $ret;
    }

    public function get_tables() {
        global $wpdb;
        $tables = array();
        $core_tables = array();
        $core_tables[] = 'commentmeta';
        $core_tables[] = 'comments';
        $core_tables[] = 'links';
        $core_tables[] = 'options';
        $core_tables[] = 'postmeta';
        $core_tables[] = 'posts';
        $core_tables[] = 'term_relationships';
        $core_tables[] = 'term_taxonomy';
        $core_tables[] = 'termmeta';
        $core_tables[] = 'terms';
        $core_tables[] = 'usermeta';
        $core_tables[] = 'users';
        $core_tables[] = 'blogs';
        $core_tables[] = 'blogmeta';
        $core_tables[] = 'site';
        $core_tables[] = 'sitemeta';

        $sql = $wpdb->prepare("SHOW TABLES LIKE %s;", $wpdb->esc_like($wpdb->base_prefix) . '%');

        $result = $wpdb->get_results($sql, OBJECT_K);
        if ( ! empty($result) ) {
            foreach ( $result as $table_name => $value ) {
                if ( in_array(substr($table_name, strlen($wpdb->base_prefix)),$core_tables) ) {
                    continue;
                }
                else {
                    $tables[] = $table_name;
                }
            }
        }
        return $tables;
    }
}