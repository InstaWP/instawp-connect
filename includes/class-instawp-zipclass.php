<?php

if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}

require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-compress-default.php';

$instawp_extract_option = array();

class InstaWP_ZipClass extends instawp_Compress_Default
{
	public $last_error = '';
	public $path_filter = array();

	public function __construct() {
		if ( ! class_exists('PclZip'))
		    include_once(ABSPATH.'/wp-admin/includes/class-pclzip.php');
		if ( ! class_exists('PclZip') ) {
			$this->last_error = array(
				'result' => INSTAWP_FAILED, 
				'error'  => "Class PclZip is not detected. Please update or reinstall your WordPress.",
			);
		}
    }

	public function get_packages( $data,$write_child_files_json=false ) {
        if ( ! function_exists('get_home_path'))
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        $files = $this -> filesplit($data['compress']['max_file_size'],$data['files']);

        $temp_dir = $data['path'].'temp-'.$data['prefix'].DIRECTORY_SEPARATOR;
        if ( ! file_exists($temp_dir))
            @mkdir($temp_dir);
        $packages = array();
        if ( sizeof($files) > 1 ) {
            for ( $i = 0;$i < sizeof($files);$i ++ ) {
                $package = array();
                $path = $data['path'].$data['prefix'].'.part'.sprintf('%03d',($i + 1)).'.zip';

                if ( isset($data['json_info']) ) {
                    $package['json'] = $data['json_info'];
                }
                /*
                $remove_path_size = strlen( $this -> transfer_path(get_home_path()));
                $package['json']['root'] = substr($data['root_path'], $remove_path_size);
                */
                if ( $write_child_files_json ) {
                    foreach ( $files[ $i ] as $file ) {
                        $ret_file = $this->get_json_data($file);
                        if ( $ret_file['result'] == INSTAWP_SUCCESS ) {
                            $json = $ret_file['json_data'];
                            $json = json_decode($json, 1);
                            $package['json']['child_file'][ basename($file) ] = $json;
                        }
                    }
                }
                if (isset($data['root_flag']))
                    $package['json']['root_flag'] = $data['root_flag'];
                if (isset($data['root_path']))
                    $package['json']['root_path'] = $data['root_path'];
                $package['json']['file'] = basename($path);
                $package['path'] = $path;
                $package['files'] = $files[ $i ];
                $packages[] = $package;
            }
        }else {
            $package = array();
            $path = $data['path'].$data['prefix'].'.zip';

            if ( isset($data['json_info']) ) {
                $package['json'] = $data['json_info'];
            }

            if ( $write_child_files_json ) {
                foreach ( $files[0] as $file ) {
                    $ret_file = $this->get_json_data($file);
                    if ( $ret_file['result'] == INSTAWP_SUCCESS ) {
                        $json = $ret_file['json_data'];
                        $json = json_decode($json, 1);
                        $package['json']['child_file'][ basename($file) ] = $json;
                    }
                }
            }
            /*
            $remove_path_size = strlen( $this -> transfer_path(get_home_path()));
            $package['json']['root'] = substr($data['root_path'], $remove_path_size);
            */
            if (isset($data['root_flag']))
                $package['json']['root_flag'] = $data['root_flag'];
            if (isset($data['root_path']))
                $package['json']['root_path'] = $data['root_path'];
            $package['json']['file'] = basename($path);
            $package['path'] = $path;
            $package['files'] = $files[0];
            $packages[] = $package;
        }

        $ret['packages'] = $packages;
        $ret['temp_dir'] = $temp_dir;
        return $ret;
    }

    public function get_plugin_packages( $data ) {
        if ( ! function_exists('get_home_path'))
            require_once(ABSPATH . 'wp-admin/includes/file.php');

        $max_size = $data['compress']['max_file_size'];

        $max_size = str_replace('M', '', $max_size);
        if ($max_size == 0)
            $max_size = 200;
        $size = intval($max_size) * 1024 * 1024;

        $files = $this -> filesplit_plugin($size,$data['files'],false);

        $temp_dir = $data['path'].'temp-'.$data['prefix'].DIRECTORY_SEPARATOR;
        if ( ! file_exists($temp_dir))
            @mkdir($temp_dir);
        $packages = array();

        if ( sizeof($files) > 1 ) {
            for ( $i = 0;$i < sizeof($files);$i ++ ) {
                $package = array();
                $path = $data['path'].$data['prefix'].'.part'.sprintf('%03d',($i + 1)).'.zip';
                if ( isset($data['json_info']) ) {
                    $package['json'] = $data['json_info'];
                }
                /*
                $remove_path_size = strlen( $this -> transfer_path(get_home_path()));
                $package['json']['root'] = substr($data['root_path'], $remove_path_size);
                */
                if (isset($data['root_flag']))
                    $package['json']['root_flag'] = $data['root_flag'];
                if (isset($data['root_path']))
                    $package['json']['root_path'] = $data['root_path'];
                $package['json']['file'] = basename($path);
                $package['path'] = $path;
                $package['files'] = $files[ $i ];
                $packages[] = $package;
            }
        }else {
            $package = array();
            $path = $data['path'].$data['prefix'].'.zip';

            if ( isset($data['json_info']) ) {
                $package['json'] = $data['json_info'];
            }
            /*
            $remove_path_size = strlen( $this -> transfer_path(get_home_path()));
            $package['json']['root'] = substr($data['root_path'], $remove_path_size);
            */
            if (isset($data['root_flag']))
                $package['json']['root_flag'] = $data['root_flag'];
            if (isset($data['root_path']))
                $package['json']['root_path'] = $data['root_path'];
            $package['json']['file'] = basename($path);
            $package['path'] = $path;
            $package['files'] = $files[0];
            $packages[] = $package;
        }

        $ret['packages'] = $packages;
        $ret['temp_dir'] = $temp_dir;
        return $ret;
    }

    public function get_upload_packages( $data ) {
        if ( ! function_exists('get_home_path'))
            require_once(ABSPATH . 'wp-admin/includes/file.php');

        $max_size = $data['compress']['max_file_size'];

        $max_size = str_replace('M', '', $max_size);
        if ($max_size == 0)
            $max_size = 200;
        $size = intval($max_size) * 1024 * 1024;

        $files = $this -> get_files_cache($size,$data);

        $temp_dir = $data['path'].'temp-'.$data['prefix'].DIRECTORY_SEPARATOR;
        if ( ! file_exists($temp_dir))
            @mkdir($temp_dir);
        $packages = array();

        if ( sizeof($files) > 1 ) {
            $i = 0;
            foreach ( $files as $file ) {
                $package = array();
                $path = $data['path'].$data['prefix'].'.part'.sprintf('%03d',($i + 1)).'.zip';
                if ( isset($data['json_info']) ) {
                    $package['json'] = $data['json_info'];
                }
                /*
                $remove_path_size = strlen( $this -> transfer_path(get_home_path()));
                $package['json']['root'] = substr($data['root_path'], $remove_path_size);
                */
                if (isset($data['root_flag']))
                    $package['json']['root_flag'] = $data['root_flag'];
                if (isset($data['root_path']))
                    $package['json']['root_path'] = $data['root_path'];
                $package['json']['file'] = basename($path);
                $package['path'] = $path;
                $package['files'] = $file;
                $packages[] = $package;
                $i++;
            }
        }else {
            $package = array();
            $path = $data['path'].$data['prefix'].'.zip';
            if ( isset($data['json_info']) ) {
                $package['json'] = $data['json_info'];
            }
            /*
            $remove_path_size = strlen( $this -> transfer_path(get_home_path()));
            $package['json']['root'] = substr($data['root_path'], $remove_path_size);
            */
            if (isset($data['root_flag']))
                $package['json']['root_flag'] = $data['root_flag'];
            if (isset($data['root_path']))
                $package['json']['root_path'] = $data['root_path'];
            $package['json']['file'] = basename($path);
            $package['path'] = $path;
            $package['files'] = $files[0];
            $packages[] = $package;
        }

        $ret['packages'] = $packages;
        return $ret;
    }

    public function compress_additional_database( $data ) {
        if ( ! function_exists('get_home_path'))
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        global $instawp_plugin;
        $instawp_plugin->instawp_log->WriteLog('Start compressing '.$data['key'],'notice');
        $files = $data['files'];
        $temp_dir = $data['path'].'temp-'.$data['prefix'].DIRECTORY_SEPARATOR;
        if ( ! file_exists($temp_dir))
            @mkdir($temp_dir);

        $package_file = array();

        $ret['result'] = INSTAWP_SUCCESS;
        $ret['files'] = array();

        foreach ( $files as $file ) {
            $file_name = $file;
            $file_name = str_replace($data['path'], '', $file_name);
            $file_name = str_replace('.sql', '', $file_name);
            $path = $data['path'].$file_name.'.zip';
            if ( isset($data['json_info']) ) {
                $package_file['json'] = $data['json_info'];
                foreach ( $data['sql_file_name'] as $sql_info ) {
                    if ( $file === $sql_info['file_name'] ) {
                        $package_file['json']['database'] = $sql_info['database'];
                    }
                }
            }
            if (isset($data['root_path']))
                $package['json']['root_path'] = $data['root_path'];
            if (isset($data['root_flag']))
                $package_file['json']['root_flag'] = $data['root_flag'];
            $package_file['json']['file'] = basename($path);
            $package_file['path'] = $path;
            $package_file['files'] = $file;
            $instawp_plugin->set_time_limit($instawp_plugin->current_task['id']);
            $zip_ret = $this -> _zip($package_file['path'],$package_file['files'], $data, $package_file['json']);
            if ( $zip_ret['result'] == INSTAWP_SUCCESS ) {
                $ret['files'][] = $zip_ret['file_data'];
            }
            else {
                $ret = $zip_ret;
                break;
            }
        }

        $instawp_plugin->instawp_log->WriteLog('Compressing '.$data['key'].' completed','notice');
        return $ret;
    }

	public function compress( $data,$write_child_files_json=false ) {
        if ( ! function_exists('get_home_path'))
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        global $instawp_plugin;
        $instawp_plugin->instawp_log->WriteLog('Start compressing '.$data['key'],'notice');
        $instawp_plugin->instawp_log->WriteLog('Start compressing Files 287 '.print_r($data['files']),'notice');
	    $files = $this -> filesplit($data['compress']['max_file_size'],$data['files']);
        $instawp_plugin->instawp_log->WriteLog('Start compressing Files 289 '.print_r($data['files']),'notice');
        $temp_dir = $data['path'].'temp-'.$data['prefix'].DIRECTORY_SEPARATOR;
        if ( ! file_exists($temp_dir))
            @mkdir($temp_dir);
        $packages = array();
	    if ( sizeof($files) > 1 ) {
            for ( $i = 0;$i < sizeof($files);$i ++ ) {
                $package = array();
                $path = $data['path'].$data['prefix'].'.part'.sprintf('%03d',($i + 1)).'.zip';
                if ( isset($data['json_info']) ) {
                    $package['json'] = $data['json_info'];
                }
                /*
                $remove_path_size = strlen( $this -> transfer_path(get_home_path()));
                $package['json']['root'] = substr($data['root_path'], $remove_path_size);
                */
                if (isset($data['root_flag']))
                    $package['json']['root_flag'] = $data['root_flag'];
                if (isset($options['root_path']))
                    $package['json']['root_path'] = $data['root_path'];
                $package['json']['file'] = basename($path);
                $package['path'] = $path;
                $package['files'] = $files[ $i ];
                $packages[] = $package;
            }
        }else {
	        $package = array();
            $path = $data['path'].$data['prefix'].'.zip';
            if ( isset($data['json_info']) ) {
                $package['json'] = $data['json_info'];
            }
            /*
            $remove_path_size = strlen( $this -> transfer_path(get_home_path()));
            $package['json']['root'] = substr($data['root_path'], $remove_path_size);
            */
            if (isset($data['root_flag']))
                $package['json']['root_flag'] = $data['root_flag'];
            if (isset($options['root_path']))
                $package['json']['root_path'] = $data['root_path'];

            $package['json']['file'] = basename($path);
            $package['path'] = $path;
            $package['files'] = $files[0];
            $packages[] = $package;
        }

        $ret['result'] = INSTAWP_SUCCESS;
        $ret['files'] = array();

        foreach ( $packages as $package ) {
            if ( ! empty($package['files']) ) {
                $instawp_plugin->set_time_limit($instawp_plugin->current_task['id']);
                $zip_ret = $this -> _zip($package['path'],$package['files'], $data,$package['json']);
                if ( $zip_ret['result'] == INSTAWP_SUCCESS ) {
                    $ret['files'][] = $zip_ret['file_data'];
                }
                else {
                    $ret = $zip_ret;
                    break;
                }
            }else {
                continue;
            }
        }
        $instawp_plugin->instawp_log->WriteLog('Compressing '.$data['key'].' completed','notice');
        return $ret;
    }

    public function extract( $files, $path = '', $option = array() ) {
        if ( ! empty($option) ) {
            $GLOBALS['instawp_extract_option'] = $option;
        }

        global $instawp_plugin;
        //$instawp_plugin->restore_data->write_log('start prepare extract','notice');
        define(PCLZIP_TEMPORARY_DIR,dirname($path));

        $ret['result'] = INSTAWP_SUCCESS;
        foreach ( $files as $file ) {
            $instawp_plugin->restore_data->write_log('start extracting file:'.$file,'notice');
            $archive = new PclZip($file);
            $zip_ret = $archive->extract(PCLZIP_OPT_PATH, $path,PCLZIP_OPT_REPLACE_NEWER,PCLZIP_CB_PRE_EXTRACT,'instawp_function_pre_extract_callback',PCLZIP_OPT_TEMP_FILE_THRESHOLD,16);
            if ( ! $zip_ret ) {
                $ret['result'] = INSTAWP_FAILED;
                $ret['error'] = $archive->errorInfo(true);
                $instawp_plugin->restore_data->write_log('extract finished:'.json_encode($ret),'notice');
                break;
            }
            else {
                $instawp_plugin->restore_data->write_log('extract finished file:'.$file,'notice');
            }
        }
        //$this->restore_data->write_log('extract finished files:'.json_encode($all_files),'notice');

        return $ret;
    }

    public function extract_ex( $files,$path = '',$extract_files=array() ) {
        global $instawp_plugin;
        //$instawp_plugin->restore_data->write_log('start prepare extract','notice');
        define(PCLZIP_TEMPORARY_DIR,dirname($path));

        $ret['result'] = INSTAWP_SUCCESS;
        foreach ( $files as $file ) {
            $instawp_plugin->restore_data->write_log('start extracting file:'.$file,'notice');
            $instawp_plugin->restore_data->write_log('extract child file:'.json_encode($extract_files),'notice');
            $archive = new PclZip($file);
            $zip_ret = $archive->extract(PCLZIP_OPT_BY_NAME,$extract_files,PCLZIP_OPT_PATH, $path,PCLZIP_OPT_REPLACE_NEWER,PCLZIP_CB_PRE_EXTRACT,'instawp_function_pre_extract_callback',PCLZIP_OPT_TEMP_FILE_THRESHOLD,16);
            if ( ! $zip_ret ) {
                $ret['result'] = INSTAWP_FAILED;
                $ret['error'] = $archive->errorInfo(true);
                $instawp_plugin->restore_data->write_log('extract finished:'.json_encode($ret),'notice');
                break;
            }
            else {
                $instawp_plugin->restore_data->write_log('extract finished file:'.$file,'notice');
            }
        }
        //$this->restore_data->write_log('extract finished files:'.json_encode($all_files),'notice');

        return $ret;
    }

    public function extract_by_files( $files,$zip,$path = '' ) {
        define(PCLZIP_TEMPORARY_DIR,$path);
        $flag = true;
        $table = array();
        $archive = new PclZip($zip);
        $list = $archive -> listContent();
        foreach ( $list as $item ) {
            if ( strstr($item['filename'],INSTAWP_ZIPCLASS_JSONFILE_NAME) ) {
                $result = $archive->extract(PCLZIP_OPT_BY_NAME, INSTAWP_ZIPCLASS_JSONFILE_NAME);
                if ( $result ) {
                    $json = json_decode(file_get_contents(dirname($zip).INSTAWP_ZIPCLASS_JSONFILE_NAME),true);
                    $path = $json['root_path'];
                }
            }
        }

        $str = $archive->extract(PCLZIP_OPT_PATH, $path, PCLZIP_OPT_BY_NAME, $files, PCLZIP_OPT_REPLACE_NEWER,PCLZIP_OPT_TEMP_FILE_THRESHOLD,16);
        if ( ! $str ) {
            $flag = false;
            $error = $archive->errorInfo(true);
        }else {
            $success_num = 0;
            $error_num = 0;
            $last_error = '';
            foreach ( $str as $item ) {
                if ( $item['status'] === 'ok' ) {
                    $success_num ++;
                }else {
                    $error_num ++;
                    $last_error = 'restore '.$item['filename'].' failed status:'.$item['status'];
                }
            }
            $table['succeed'] = $success_num;
            $table['failed'] = $error_num;
            $error = $last_error;
        }

        if ( $flag ) {
            return array(
				'result' => INSTAWP_SUCCESS, 
				'table'  => $table, 
				'error'  => $error,
			);
        }else {
            return array(
				'result' => 'failed', 
				'error'  => $error,
			);
        }
    }

    public function get_include_zip( $files,$allpackages ) {
        $i = sizeof($files);
        $zips = array();
        foreach ( $allpackages as $item ) {
            $archive = new PclZip($item);
            $lists = $archive -> listContent();
            foreach ( $lists as $file ) {
                if ( $this -> _in_array($file['filename'],$files) ) {
                    $zips[ $item ][] = $file['filename'];
                    if ($i -- === 0)
                        break 2;
                }
            }
        }
        return $zips;
    }

    public function _zip( $name,$files,$options,$json_info=false ) {
        $zip_object_class = apply_filters('instawp_get_zip_object_class_ex','InstaWP_PclZip_Class',$options);
        $zip = new $zip_object_class();
        return $zip->zip($name,$files,$options,$json_info);
    }

    public function listcontent( $path ) {
        $zip = new PclZip($path);
        $list = $zip->listContent();
        return $list;
    }
    public function listnum( $path , $includeFolder = false ) {
        $zip = new PclZip($path);
        $list = $zip->listContent();
        $index = 0;
        foreach ( $list as $item ) {
            if ( ! $includeFolder && $item['folder'])
                continue;
            $index ++;
        }
        return $index;
    }

    private function transfer_path( $path ) {
        $path = str_replace('\\','/',$path);
        $values = explode('/',$path);
        return implode(DIRECTORY_SEPARATOR,$values);
    }

    public function get_json_data( $path, $json_type = 'backup' ) {
        $json_file_name = $json_type === 'backup' ? 'instawp_package_info.json' : 'instawp_export_package_info.json';
        $archive = new PclZip($path);
        $list = $archive->listContent();
        if ( $list == false ) {
            return array(
				'result' => INSTAWP_FAILED, 
				'error'  => $archive->errorInfo(true),
			);
        }
        else {
            $b_exist = false;
            foreach ( $list as $item ) {
                if ( basename($item['filename']) === $json_file_name ) {
                    $b_exist = true;
                    $result = $archive->extract(PCLZIP_OPT_BY_NAME, $json_file_name, PCLZIP_OPT_EXTRACT_AS_STRING);
                    if ( $result != 0 ) {
                        return array(
							'result'    => INSTAWP_SUCCESS, 
							'json_data' => $result[0]['content'],
						);
                    } else {
                        return array(
							'result' => INSTAWP_FAILED, 
							'error'  => $archive->errorInfo(true),
						);
                    }
                }
            }
            if ( ! $b_exist ) {
                return array(
					'result' => INSTAWP_FAILED, 
					'error'  => 'Failed to get json, this may be a old version backup.',
				);
            }
        }
        return array(
			'result' => INSTAWP_FAILED, 
			'error'  => 'Unknown error',
		);
    }

    public function list_file( $path ) {
        $archive = new PclZip($path);
        $list = $archive->listContent();

        $files = array();
        foreach ( $list as $item ) {
            if ( basename($item['filename']) === 'instawp_package_info.json' ) {
                continue;
            }
            $file['file_name'] = $item['filename'];
            $files[] = $file;
        }

        return $files;
    }

    public function filesplit_plugin( $max_file_size,$files,$is_num=true ) {
        $packages = array();
        if ( $max_file_size == 0 || empty($max_file_size) ) {
            $packages[] = $files;
        }else {
            $folder_num_sum = 0;
            $package = array();

            if ( $is_num ) {
                foreach ( $files as $file ) {
                    $folder_num = 0;
                    if ( is_dir($file) ) {
                        $folder_num = $this->get_folder_file_count($file);
                    }
                    else {
                        $folder_num_sum += filesize($file);
                    }

                    if ( $folder_num > $max_file_size ) {
                        $temp_package[] = $file;
                        $packages[] = $temp_package;
                        $temp_package = array();
                        continue;
                    }
                    else {
                        $folder_num_sum += $folder_num;
                    }

                    if ( $folder_num_sum > $max_file_size ) {
                        $package[] = $file;
                        $packages[] = $package;
                        $package = array();
                        $folder_num_sum = 0;
                    }
                    else {
                        $package[] = $file;
                    }
                }
            }
            else {
                foreach ( $files as $file ) {
                    $folder_num = 0;
                    if ( is_dir($file) ) {
                        $folder_num = $this->get_folder_file_size($file);
                    }
                    else {
                        $folder_num_sum += filesize($file);
                    }

                    if ( $folder_num > $max_file_size ) {
                        $temp_package[] = $file;
                        $packages[] = $temp_package;
                        $temp_package = array();
                        continue;
                    }
                    else {
                        $folder_num_sum += $folder_num;
                    }

                    if ( $folder_num_sum > $max_file_size ) {
                        $package[] = $file;
                        $packages[] = $package;
                        $package = array();
                        $folder_num_sum = 0;
                    }
                    else {
                        $package[] = $file;
                    }
                }
            }

            if ( ! empty($package))
                $packages[] = $package;
        }
        return $packages;
    }

    public function get_folder_file_count( $file ) {
        $count = 0;
        $this->get_folder_file_count_loop($file,$count);

        return $count;
    }

    function get_folder_file_count_loop( $path,&$count ) {
        $handler = opendir($path);
        if ( $handler !== false ) {
            while ( ($filename = readdir($handler)) !== false ) {
                if ( $filename != "." && $filename != ".." ) {
                    $count++;

                    if ( is_dir($path . DIRECTORY_SEPARATOR . $filename) ) {
                        $this->get_folder_file_count_loop($path . DIRECTORY_SEPARATOR . $filename,$count);
                    }
                }
            }
            if ($handler)
                @closedir($handler);
        }
    }

    function get_folder_file_size( $file ) {
        $count = 0;
        $this->get_folder_file_size_loop($file,$count);

        return $count;
    }

    function get_folder_file_size_loop( $path,&$count ) {
        $handler = opendir($path);
        if ( $handler !== false ) {
            while ( ($filename = readdir($handler)) !== false ) {
                if ( $filename != "." && $filename != ".." ) {
                    if ( is_dir($path . DIRECTORY_SEPARATOR . $filename) ) {
                        $this->get_folder_file_size_loop($path . DIRECTORY_SEPARATOR . $filename,$count);
                    }
                    else {
                        $count += filesize($path . DIRECTORY_SEPARATOR . $filename);
                    }
                }
            }
            if ($handler)
                @closedir($handler);
        }
    }

    public function get_root_flag_path( $flag ) {
        $path = '';
        if ( $flag == INSTAWP_BACKUP_ROOT_WP_CONTENT ) {
            $path = WP_CONTENT_DIR;
        }
        elseif ( $flag == INSTAWP_BACKUP_ROOT_CUSTOM ) {
            $path = WP_CONTENT_DIR.DIRECTORY_SEPARATOR.InstaWP_Setting::get_backupdir();
        }
        elseif ( $flag == INSTAWP_BACKUP_ROOT_WP_ROOT ) {
            $path = ABSPATH;
        }
        return $path;
    }

    public function get_files_cache( $size,$data ) {
        $number = 1;
        $cache_perfix = $data['path'].$data['prefix'].'_file_cache_';
        $cache_file_handle = false;
        $sumsize = 0;

        if (isset($data['exclude_files_regex']))
            $exclude_files_regex = $data['exclude_files_regex'];
        else
            $exclude_files_regex = array();

        if (isset($data['exclude_regex']))
            $exclude_regex = $data['exclude_regex'];
        else
            $exclude_regex = array();

        if (isset($data['compress']) && $data['compress']['exclude_file_size'])
            $exclude_file_size = $data['compress']['exclude_file_size'];
        else
            $exclude_file_size = 0;

        if ( isset($data['skip_files_time']) ) {
            $skip_files_time = $data['skip_files_time'];
        }
        else {
            $skip_files_time = 0;
        }
        global $instawp_plugin;
        $instawp_plugin->instawp_log->WriteLog('exclude_files_regex:'.json_encode($exclude_files_regex),'notice');

        foreach ( $data['files'] as $file ) {
            $this->get_file_cache($size,$file,$cache_perfix,$cache_file_handle,$number,$sumsize,$exclude_regex,$exclude_files_regex,$exclude_file_size,$skip_files_time);
        }

        $file_cache = array();

        for ( $i = 1;$i < $number + 1;$i++ ) {
            $file_cache[] = $cache_perfix.$i.'.txt';
        }
        return $file_cache;
    }

    public function get_file_cache( $size,$path,$cache_perfix,&$cache_file_handle,&$number,&$sumsize,$exclude_regex,$exclude_files_regex,$exclude_file_size,$skip_files_time ) {
        if ( ! $cache_file_handle ) {
            $cache_file = $cache_perfix.$number.'.txt';
            $cache_file_handle = fopen($cache_file,'a');
        }
        $handler = opendir($path);

        if ($handler === false)
            return;

        while ( ($filename = readdir($handler)) !== false ) {
            if ( $filename != "." && $filename != ".." ) {
                if ( is_dir($path . DIRECTORY_SEPARATOR . $filename) ) {
                    if ( $this->regex_match($exclude_regex, $path . DIRECTORY_SEPARATOR . $filename, 0) ) {
                        $this->get_file_cache($size,$path . DIRECTORY_SEPARATOR . $filename,$cache_perfix,$cache_file_handle,$number,$sumsize,$exclude_regex,$exclude_files_regex,$exclude_file_size,$skip_files_time);
                    }
                }
                /*if(is_dir($path . DIRECTORY_SEPARATOR . $filename))
                {
                    $this->get_file_cache($size,$path . DIRECTORY_SEPARATOR . $filename,$cache_perfix,$cache_file_handle,$number,$sumsize,$exclude_regex,$exclude_files_regex,$exclude_file_size,$skip_files_time);
                }*/
                else {
                    if ( $this->regex_match($exclude_files_regex, $filename, 0) ) {
                        if ( $exclude_file_size == 0 || (filesize($path . DIRECTORY_SEPARATOR . $filename) < $exclude_file_size * 1024 * 1024) ) {
                            if ( is_readable($path . DIRECTORY_SEPARATOR . $filename) ) {
                                if ( $skip_files_time > 0 ) {
                                    $file_time = filemtime($path . DIRECTORY_SEPARATOR . $filename);
                                    if ( $file_time > 0 && $file_time > $skip_files_time ) {
                                        $sumsize += filesize($path . DIRECTORY_SEPARATOR . $filename);
                                        if ( $sumsize > $size ) {
                                            $number++;
                                            fclose($cache_file_handle);
                                            $cache_file = $cache_perfix.$number.'.txt';
                                            $cache_file_handle = fopen($cache_file,'a');

                                            $line = $path . DIRECTORY_SEPARATOR . $filename.PHP_EOL;
                                            fwrite($cache_file_handle, $line);

                                            $sumsize = filesize($path . DIRECTORY_SEPARATOR . $filename);
                                        }
                                        else {
                                            $line = $path . DIRECTORY_SEPARATOR . $filename.PHP_EOL;
                                            fwrite($cache_file_handle, $line);
                                        }
                                    }
                                }
                                else {
                                    $sumsize += filesize($path . DIRECTORY_SEPARATOR . $filename);
                                    if ( $sumsize > $size ) {
                                        $number++;
                                        fclose($cache_file_handle);
                                        $cache_file = $cache_perfix.$number.'.txt';
                                        $cache_file_handle = fopen($cache_file,'a');

                                        $line = $path . DIRECTORY_SEPARATOR . $filename.PHP_EOL;
                                        fwrite($cache_file_handle, $line);

                                        $sumsize = filesize($path . DIRECTORY_SEPARATOR . $filename);
                                    }
                                    else {
                                        $line = $path . DIRECTORY_SEPARATOR . $filename.PHP_EOL;
                                        fwrite($cache_file_handle, $line);
                                    }
                                    $files[] = $path . DIRECTORY_SEPARATOR . $filename;
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($handler)
            @closedir($handler);
    }

    private function regex_match( $regex_array,$string,$mode ) {
        if ( empty($regex_array) ) {
            return true;
        }

        if ( $mode == 0 ) {
            foreach ( $regex_array as $regex ) {
                if ( preg_match($regex,$string) ) {
                    return false;
                }
            }

            return true;
        }

        if ( $mode == 1 ) {
            foreach ( $regex_array as $regex ) {
                if ( preg_match($regex,$string) ) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    public function get_upload_files_from_cache( $file ) {
        $files = array();
        $file = new SplFileObject($file);
        $file->seek(0);

        $file->setFlags( \SplFileObject::SKIP_EMPTY | \SplFileObject::READ_AHEAD );

        while ( ! $file->eof() ) {
            $src = $file->fgets();

            $src = trim($src,PHP_EOL);

            if (empty($src))
                continue;

            if ( ! file_exists($src) ) {
                continue;
            }

            $files[] = $src;
        }
        return $files;
    }
}

class InstaWP_PclZip_Class
{
    public function zip( $name,$files,$options,$json_info=false ) {
        global $instawp_plugin;

        if (file_exists($name))
            @unlink($name);

        $archive = new PclZip($name);

        if ( isset($options['compress']['no_compress']) ) {
            $no_compress = $options['compress']['no_compress'];
        }
        else {
            $no_compress = 1;
        }

        if ( isset($options['compress']['use_temp_file']) ) {
            $use_temp_file = 1;
        }
        else {
            $use_temp_file = 0;
        }

        if ( isset($options['compress']['use_temp_size']) ) {
            $use_temp_size = $options['compress']['use_temp_size'];
        }
        else {
            $use_temp_size = 16;
        }

        if ( isset($options['root_path']) ) {
            $replace_path = $options['root_path'];
        }
        elseif ( isset($options['root_flag']) ) {
            $replace_path = $this->get_root_flag_path($options['root_flag']);
        }
        else {
            $replace_path = WP_CONTENT_DIR.DIRECTORY_SEPARATOR.InstaWP_Setting::get_backupdir();
        }

        if ( $json_info !== false ) {
            $temp_path = dirname($name).DIRECTORY_SEPARATOR.'instawp_package_info.json';
            if ( file_exists($temp_path) ) {
                @unlink($temp_path);
            }
            $json_info['php_version'] = phpversion();
            global $wpdb;
            $json_info['mysql_version'] = $wpdb->db_version();
            file_put_contents($temp_path,print_r(json_encode($json_info),true));
            $archive -> add($temp_path,PCLZIP_OPT_REMOVE_PATH,dirname($temp_path));
            @unlink($temp_path);
        }

        $instawp_plugin->instawp_log->WriteLog('Prepare to zip files. file: '.basename($name),'notice');

        /*foreach ($files as $index => $file){
            if(!is_dir($file) && filesize($file) === 0){
                $instawp_plugin->instawp_log->WriteLog('Ignore files with size 0. file: '.$file,'notice');
                unset($files[$index]);
            }
        }*/

        if ( $no_compress ) {
            if ( $use_temp_file == 1 ) {
                if ( $use_temp_size != 0 ) {
                    $ret = $archive -> add($files,PCLZIP_OPT_REMOVE_PATH,$replace_path,PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',PCLZIP_OPT_NO_COMPRESSION,PCLZIP_OPT_TEMP_FILE_THRESHOLD,$use_temp_size);
                }
                else {
                    $ret = $archive -> add($files,PCLZIP_OPT_REMOVE_PATH,$replace_path,PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',PCLZIP_OPT_NO_COMPRESSION,PCLZIP_OPT_TEMP_FILE_ON);
                }
            }
            else {
                $ret = $archive -> add($files,PCLZIP_OPT_REMOVE_PATH,$replace_path,PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',PCLZIP_OPT_NO_COMPRESSION,PCLZIP_OPT_TEMP_FILE_OFF);
            }
        }
        else {
            if ( $use_temp_file == 1 ) {
                if ( $use_temp_size != 0 ) {
                    $ret = $archive -> add($files,PCLZIP_OPT_REMOVE_PATH,$replace_path,PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',PCLZIP_OPT_TEMP_FILE_THRESHOLD,$use_temp_size);
                }
                else {
                    $ret = $archive -> add($files,PCLZIP_OPT_REMOVE_PATH,$replace_path,PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',PCLZIP_OPT_TEMP_FILE_ON);
                }
            }
            else {
                $ret = $archive -> add($files,PCLZIP_OPT_REMOVE_PATH,$replace_path,PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',PCLZIP_OPT_TEMP_FILE_OFF);
            }
        }

        if ( ! $ret ) {
            $instawp_plugin->instawp_log->WriteLog('Failed to add zip files, error: '.$archive->errorInfo(true),'notice');
            $size = size_format(disk_free_space(dirname($name)),2);
            $instawp_plugin->instawp_log->WriteLog('disk_free_space : '.$size,'notice');
            return array(
				'result' => INSTAWP_FAILED, 
				'error'  => $archive->errorInfo(true),
			);
        }

        $size = filesize($name);
        if ( $size === false ) {
            $instawp_plugin->instawp_log->WriteLog('Failed to add zip files, error: file not found after backup success','error');
            $size = size_format(disk_free_space(dirname($name)),2);
            $instawp_plugin->instawp_log->WriteLog('disk_free_space : '.$size,'notice');
            return array(
				'result' => INSTAWP_FAILED, 
				'error'  => 'The file compression failed while backing up becuase of '.$name.' file not found. Please try again. The available disk space: '.$size.'.',
			);
        }
        elseif ( $size == 0 ) {
            $instawp_plugin->instawp_log->WriteLog('Failed to add zip files, error: file size 0B after backup success','error');
            $size = size_format(disk_free_space(dirname($name)),2);
            $instawp_plugin->instawp_log->WriteLog('disk_free_space : '.$size,'notice');
            return array(
				'result' => INSTAWP_FAILED, 
				'error'  => 'The file compression failed while backing up. The size of '.$name.' file is 0. Please make sure there is an enough disk space to backup. Then try again. The available disk space: '.$size.'.',
			);
        }

        $instawp_plugin->instawp_log->WriteLog('Adding zip files completed.'.basename($name).', filesize: '.size_format(filesize($name),2),'notice');
        $file_data = array();
        $file_data['file_name'] = basename($name);
        $file_data['size'] = filesize($name);

        return array(
			'result'    => INSTAWP_SUCCESS, 
			'file_data' => $file_data,
		);
    }

    public function get_root_flag_path( $flag ) {
        $path = '';
        if ( $flag == INSTAWP_BACKUP_ROOT_WP_CONTENT ) {
            $path = WP_CONTENT_DIR;
        }
        elseif ( $flag == INSTAWP_BACKUP_ROOT_CUSTOM ) {
            $path = WP_CONTENT_DIR.DIRECTORY_SEPARATOR.InstaWP_Setting::get_backupdir();
        }
        elseif ( $flag == INSTAWP_BACKUP_ROOT_WP_ROOT ) {
            $path = ABSPATH;
        }
        return $path;
    }
}

class InstaWP_PclZip_Class_Ex
{
    public function zip( $name,$files,$options,$json_info=false ) {
        global $instawp_plugin;

        if (file_exists($name))
            @unlink($name);

        if ( ! class_exists('InstaWP_PclZip'))
            include_once INSTAWP_PLUGIN_DIR . '/includes/zip/class-instawp-pclzip.php';
        $archive = new InstaWP_PclZip($name);

        if ( isset($options['compress']['no_compress']) ) {
            $no_compress = $options['compress']['no_compress'];
        }
        else {
            $no_compress = 1;
        }

        if ( isset($options['compress']['use_temp_file']) ) {
            $use_temp_file = 1;
        }
        else {
            $use_temp_file = 0;
        }

        if ( isset($options['compress']['use_temp_size']) ) {
            $use_temp_size = $options['compress']['use_temp_size'];
        }
        else {
            $use_temp_size = 16;
        }

        if ( isset($options['root_path']) ) {
            $replace_path = $options['root_path'];
        }
        elseif ( isset($options['root_flag']) ) {
            $replace_path = $this->get_root_flag_path($options['root_flag']);
        }
        else {
            $replace_path = WP_CONTENT_DIR.DIRECTORY_SEPARATOR.InstaWP_Setting::get_backupdir();
        }

        if ( $json_info !== false ) {
            $temp_path = dirname($name).DIRECTORY_SEPARATOR.'instawp_package_info.json';
            if ( file_exists($temp_path) ) {
                @unlink($temp_path);
            }
            $json_info['php_version'] = phpversion();
            global $wpdb;
            $json_info['mysql_version'] = $wpdb->db_version();
            file_put_contents($temp_path,print_r(json_encode($json_info),true));
            $archive -> add($temp_path,INSTAWP_PCLZIP_OPT_REMOVE_PATH,dirname($temp_path));
            @unlink($temp_path);
        }

        $instawp_plugin->instawp_log->WriteLog('Prepare to zip files. file: '.basename($name),'notice');

        if ( $no_compress ) {
            if ( $use_temp_file == 1 ) {
                if ( $use_temp_size != 0 ) {
                    $ret = $archive -> add($files,INSTAWP_PCLZIP_OPT_REMOVE_PATH,$replace_path,INSTAWP_PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',INSTAWP_PCLZIP_OPT_NO_COMPRESSION,INSTAWP_PCLZIP_OPT_TEMP_FILE_THRESHOLD,$use_temp_size);
                }
                else {
                    $ret = $archive -> add($files,INSTAWP_PCLZIP_OPT_REMOVE_PATH,$replace_path,INSTAWP_PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',INSTAWP_PCLZIP_OPT_NO_COMPRESSION,INSTAWP_PCLZIP_OPT_TEMP_FILE_ON);
                }
            }
            else {
                $ret = $archive -> add($files,INSTAWP_PCLZIP_OPT_REMOVE_PATH,$replace_path,INSTAWP_PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',INSTAWP_PCLZIP_OPT_NO_COMPRESSION,INSTAWP_PCLZIP_OPT_TEMP_FILE_OFF);
            }
        }
        else {
            if ( $use_temp_file == 1 ) {
                if ( $use_temp_size != 0 ) {
                    $ret = $archive -> add($files,INSTAWP_PCLZIP_OPT_REMOVE_PATH,$replace_path,INSTAWP_PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',INSTAWP_PCLZIP_OPT_TEMP_FILE_THRESHOLD,$use_temp_size);
                }
                else {
                    $ret = $archive -> add($files,INSTAWP_PCLZIP_OPT_REMOVE_PATH,$replace_path,INSTAWP_PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',INSTAWP_PCLZIP_OPT_TEMP_FILE_ON);
                }
            }
            else {
                $ret = $archive -> add($files,INSTAWP_PCLZIP_OPT_REMOVE_PATH,$replace_path,INSTAWP_PCLZIP_CB_PRE_ADD,'instawp_function_per_add_callback',INSTAWP_PCLZIP_OPT_TEMP_FILE_OFF);
            }
        }

        if ( ! $ret ) {
            $instawp_plugin->instawp_log->WriteLog('Failed to add zip files, error: '.$archive->errorInfo(true),'notice');
            $size = size_format(disk_free_space(dirname($name)),2);
            $instawp_plugin->instawp_log->WriteLog('disk_free_space : '.$size,'notice');
            return array(
				'result' => INSTAWP_FAILED, 
				'error'  => $archive->errorInfo(true),
			);
        }

        $size = filesize($name);
        if ( $size === false ) {
            $instawp_plugin->instawp_log->WriteLog('Failed to add zip files, error: file not found after backup success','error');
            $size = size_format(disk_free_space(dirname($name)),2);
            $instawp_plugin->instawp_log->WriteLog('disk_free_space : '.$size,'notice');
            return array(
				'result' => INSTAWP_FAILED, 
				'error'  => 'The file compression failed while backing up becuase of '.$name.' file not found. Please try again. The available disk space: '.$size.'.',
			);
        }
        elseif ( $size == 0 ) {
            $instawp_plugin->instawp_log->WriteLog('Failed to add zip files, error: file size 0B after backup success','error');
            $size = size_format(disk_free_space(dirname($name)),2);
            $instawp_plugin->instawp_log->WriteLog('disk_free_space : '.$size,'notice');
            return array(
				'result' => INSTAWP_FAILED, 
				'error'  => 'The file compression failed while backing up. The size of '.$name.' file is 0. Please make sure there is an enough disk space to backup. Then try again. The available disk space: '.$size.'.',
			);
        }

        $instawp_plugin->instawp_log->WriteLog('Adding zip files completed.'.basename($name).', filesize: '.size_format(filesize($name),2),'notice');
        $file_data = array();
        $file_data['file_name'] = basename($name);
        $file_data['size'] = filesize($name);

        return array(
			'result'    => INSTAWP_SUCCESS, 
			'file_data' => $file_data,
		);
    }

    public function get_root_flag_path( $flag ) {
        $path = '';
        if ( $flag == INSTAWP_BACKUP_ROOT_WP_CONTENT ) {
            $path = WP_CONTENT_DIR;
        }
        elseif ( $flag == INSTAWP_BACKUP_ROOT_CUSTOM ) {
            $path = WP_CONTENT_DIR.DIRECTORY_SEPARATOR.InstaWP_Setting::get_backupdir();
        }
        elseif ( $flag == INSTAWP_BACKUP_ROOT_WP_ROOT ) {
            $path = ABSPATH;
        }
        return $path;
    }
}

$instawp_old_time = 0;

function instawp_function_per_add_callback( $p_event, &$p_header ) {
    if ( ! file_exists($p_header['filename']) ) {
        return 0;
    }
    /*if($p_header['size'] === 0){
        return 0;
    }*/

    $path = str_replace('\\','/',WP_CONTENT_DIR);
    $content_path = $path.'/';
    if ( strpos($p_header['filename'], $content_path.'mu-plugins/endurance-browser-cache.php') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'], $content_path.'mu-plugins/endurance-page-cache.php') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'], $content_path.'mu-plugins/endurance-php-edge.php') !== false ) {
        return 0;
    }

    $plugins = substr(WP_PLUGIN_DIR, strpos(WP_PLUGIN_DIR, 'wp-content/'));

    if ( strpos($p_header['filename'],$plugins.'/instawp-connect') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'],$plugins.'/instawp-backup-pro') !== false ) {
        return 0;
    }

    global $instawp_old_time;
    if ( time() - $instawp_old_time > 30 ) {
        $instawp_old_time = time();
        global $instawp_plugin;
        $instawp_plugin->check_cancel_backup($instawp_plugin->current_task['id']);
        InstaWP_taskmanager::update_backup_task_status($instawp_plugin->current_task['id']);
    }

    return 1;
}

function instawp_function_pre_extract_callback( $p_event, &$p_header ) {
    $plugins = substr(WP_PLUGIN_DIR, strpos(WP_PLUGIN_DIR, 'wp-content/'));

    if ( isset( $GLOBALS['instawp_extract_option'] ) ) {
        $option = $GLOBALS['instawp_extract_option'];
        if ( isset($option['file_type']) ) {
            if ( $option['file_type'] == 'themes' ) {
                if ( isset($option['remove_themes']) ) {
                    foreach ( $option['remove_themes'] as $slug => $themes ) {
                        if (empty($slug))
                            continue;
                        if ( strpos($p_header['filename'],$plugins.DIRECTORY_SEPARATOR.$slug) !== false ) {
                            return 0;
                        }
                    }
                }
            }
            elseif ( $option['file_type'] == 'plugin' ) {
                if ( isset($option['remove_plugin']) ) {
                    foreach ( $option['remove_plugin'] as $slug => $plugin ) {
                        if (empty($slug))
                            continue;
                        if ( strpos($p_header['filename'],$plugins.'/'.$slug) !== false ) {
                            return 0;
                        }
                    }
                }
            }
        }
    }

    $path = str_replace('\\','/',WP_CONTENT_DIR);
    $content_path = $path.'/';
    if ( strpos($p_header['filename'], $content_path.'advanced-cache.php') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'], $content_path.'db.php') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'], $content_path.'object-cache.php') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'],$plugins.'/instawp-connect') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'],'wp-config.php') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'],'instawp_package_info.json') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'],'.htaccess') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'],'.user.ini') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'],'wordfence-waf.php') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'], $content_path.'mu-plugins/endurance-browser-cache.php') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'], $content_path.'mu-plugins/endurance-page-cache.php') !== false ) {
        return 0;
    }

    if ( strpos($p_header['filename'], $content_path.'mu-plugins/endurance-php-edge.php') !== false ) {
        return 0;
    }

    return 1;
}