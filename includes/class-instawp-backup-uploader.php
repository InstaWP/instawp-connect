<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class instawp_BackupUploader {
	public function __construct() {
		add_action( 'wp_ajax_instawp_cancel_upload_backup_free', array( $this, 'cancel_upload_backup_free' ) );
		add_action( 'wp_ajax_instawp_is_backup_file_free', array( $this, 'is_backup_file_free' ) );
		add_action( 'wp_ajax_instawp_upload_files_finish_free', array( $this, 'upload_files_finish_free' ) );
		add_action( 'wp_ajax_instawp_get_file_id', array( $this, 'get_file_id' ) );
		add_action( 'wp_ajax_instawp_upload_files', array( $this, 'upload_files' ) );
		add_action( 'wp_ajax_instawp_upload_files_finish', array( $this, 'upload_files_finish' ) );


		add_action( 'wp_ajax_instawp_get_backup_count', array( $this, 'get_backup_count' ) );
		add_action( 'instawp_rebuild_backup_list', array( $this, 'instawp_rebuild_backup_list' ), 10 );
	}

	function cancel_upload_backup_free() {
		global $instawp_plugin;
		$instawp_plugin->ajax_check_security();
		try {
			$path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . InstaWP_Setting::get_backupdir() . DIRECTORY_SEPARATOR;
			if ( is_dir( $path ) ) {
				$handler = opendir( $path );
				if ( $handler !== false ) {
					while ( ( $filename = readdir( $handler ) ) !== false ) {
						if ( $filename != "." && $filename != ".." ) {
							if ( is_dir( $path . $filename ) ) {
								continue;
							} else {
								if ( preg_match( '/.*\.tmp$/', $filename ) ) {
									@unlink( $path . $filename );
								}

								if ( preg_match( '/.*\.part$/', $filename ) ) {
									@unlink( $path . $filename );
								}
							}
						}
					}
					if ( $handler ) {
						@closedir( $handler );
					}
				}
			}
		} catch ( Exception $error ) {
			$message = 'An exception has occurred. class: ' . get_class( $error ) . ';msg: ' . $error->getMessage() . ';code: ' . $error->getCode() . ';line: ' . $error->getLine() . ';in_file: ' . $error->getFile() . ';';
			error_log( $message );
			echo json_encode( array(
				'result' => 'failed',
				'error'  => $message,
			) );
		}
		die();
	}

	public function is_instawp_backup( $file_name ) {
		if ( preg_match( '/instawp-.*_.*_.*\.zip$/', $file_name ) ) {
			return true;
		} else {
			return false;
		}
	}

	function is_backup_file_free() {
		global $instawp_plugin;
		$instawp_plugin->ajax_check_security();

		try {
			$filename = isset( $_POST['file_name'] ) ? sanitize_text_field( wp_unslash( $_POST['file_name'] ) ) : '';
			if ( ! empty( $filename ) ) {
				if ( $this->is_instawp_backup( $filename ) ) {
					$ret['result'] = INSTAWP_SUCCESS;

					$backupdir = InstaWP_Setting::get_backupdir();
					$filePath  = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backupdir . DIRECTORY_SEPARATOR . $filename;
					if ( file_exists( $filePath ) ) {
						$ret['is_exists'] = true;
					} else {
						$ret['is_exists'] = false;
					}
				} else {
					$ret['result'] = INSTAWP_FAILED;
					$ret['error']  = $filename . ' is not created by instaWP backup plugin.';
				}
			} else {
				$ret['result'] = INSTAWP_FAILED;
				$ret['error']  = 'Failed to post file name.';
			}

			echo json_encode( $ret );
		} catch ( Exception $error ) {
			$message = 'An exception has occurred. class: ' . get_class( $error ) . ';msg: ' . $error->getMessage() . ';code: ' . $error->getCode() . ';line: ' . $error->getLine() . ';in_file: ' . $error->getFile() . ';';
			echo json_encode( array(
				'result' => 'failed',
				'error'  => $message,
			) );
		}

		die();
	}

	function upload_files_finish_free() {
		global $instawp_plugin;
		$instawp_plugin->ajax_check_security();

		try {
			$ret = $this->_rescan_local_folder_set_backup();
			echo json_encode( $ret );
		} catch ( Exception $error ) {
			$message = 'An exception has occurred. class: ' . get_class( $error ) . ';msg: ' . $error->getMessage() . ';code: ' . $error->getCode() . ';line: ' . $error->getLine() . ';in_file: ' . $error->getFile() . ';';
			error_log( $message );
			echo json_encode( array(
				'result' => 'failed',
				'error'  => $message,
			) );
		}
		die();
	}

	function get_file_id() {
		global $instawp_plugin;
		$instawp_plugin->ajax_check_security();
		$filename = isset( $_POST['file_name'] ) ? sanitize_text_field( wp_unslash( $_POST['file_name'] ) ) : '';
		if ( ! empty( $filename ) ) {
			if ( preg_match( '/instawp-.*_.*_.*\.zip$/', $filename ) ) {
				if ( preg_match( '/instawp-(.*?)_/', $filename, $matches ) ) {
					$id = $matches[0];
					$id = substr( $id, 0, strlen( $id ) - 1 );
					if ( InstaWP_Backuplist::get_backup_by_id( $id ) === false ) {
						$ret['result'] = INSTAWP_SUCCESS;
						$ret['id']     = $id;
					} else {
						$ret['result'] = INSTAWP_FAILED;
						$ret['error']  = 'The uploading backup already exists in Backups list.';
					}
				} else {
					$ret['result'] = INSTAWP_FAILED;
					$ret['error']  = $filename . ' is not created by instaWP backup plugin.';
				}
			} else {
				$ret['result'] = INSTAWP_FAILED;
				$ret['error']  = $filename . ' is not created by instaWP backup plugin.';
			}
		} else {
			$ret['result'] = INSTAWP_FAILED;
			$ret['error']  = 'Failed to post file name.';
		}

		echo json_encode( $ret );
		die();
	}

	function check_file_is_a_instawp_backup( $file_name, &$backup_id ) {
		global $InstaWP_Backup_Api;
		if ( preg_match( '/instawp-.*_.*_.*\.zip$/', $file_name ) ) {
			if ( preg_match( '/instawp-(.*?)_/', $file_name, $matches ) ) {
				$id = $matches[0];
				$id = substr( $id, 0, strlen( $id ) - 1 );


				if ( InstaWP_Backuplist::get_backup_by_id( $id ) === false ) {
					$backup_id = $id;

					return true;
					$InstaWP_Backup_Api->instawp_log->WriteLog( 'Valid File', 'success' );
				} else {
					$InstaWP_Backup_Api->instawp_log->WriteLog( 'File is not valid', 'error' );

					return false;
				}
			} else {
				$InstaWP_Backup_Api->instawp_log->WriteLog( 'File is not valid', 'error' );

				return false;
			}
		} else {
			$InstaWP_Backup_Api->instawp_log->WriteLog( 'File is not valid', 'error' );

			return false;
		}
	}

	function upload_files() {
		global $instawp_plugin;
		$instawp_plugin->ajax_check_security();

		try {
			$chunk     = isset( $_REQUEST["chunk"] ) ? intval( $_REQUEST["chunk"] ) : 0;
			$chunks    = isset( $_REQUEST["chunks"] ) ? intval( $_REQUEST["chunks"] ) : 0;
			$file_name = isset( $_FILES["file"]["name"] ) ? sanitize_text_field( wp_unslash( $_FILES["file"]["name"] ) ) : '';

			$fileName = isset( $_REQUEST["name"] ) ? sanitize_text_field( wp_unslash( $_REQUEST["name"] ) ) : $file_name;

			$filename = $fileName;

			$backupdir = InstaWP_Setting::get_backupdir();
			$filePath  = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backupdir . DIRECTORY_SEPARATOR . $fileName;
			$out       = @fopen( "{$filePath}.part", $chunk == 0 ? "wb" : "ab" );

			if ( $out ) {
				// Read binary input stream and append it to temp file
				$options['test_form'] = true;
				$options['action']    = 'instawp_upload_files';
				$options['test_type'] = false;
				$options['ext']       = 'zip';
				$options['type']      = 'application/zip';

				add_filter( 'upload_dir', array( $this, 'upload_dir' ) );

				$status = wp_handle_upload( wp_unslash( $_FILES['async-upload'] ), $options );

				remove_filter( 'upload_dir', array( $this, 'upload_dir' ) );

				$in = @fopen( $status['file'], "rb" );

				if ( $in ) {
					while ( $buff = fread( $in, 4096 ) ) {
						fwrite( $out, $buff );
					}
				} else {
					echo json_encode( array(
						'result' => 'failed',
						'error'  => "Failed to open tmp file.path:" . $status['file'],
					) );
					die();
				}

				@fclose( $in );
				@fclose( $out );

				@unlink( $status['file'] );
			} else {
				echo json_encode( array(
					'result' => 'failed',
					'error'  => "Failed to open input stream.path:{$filePath}.part",
				) );
				die();
			}

			if ( ! $chunks || $chunk == $chunks - 1 ) {
				// Strip the temp .part suffix off
				rename( "{$filePath}.part", $filePath );
			}

			echo json_encode( array( 'result' => INSTAWP_SUCCESS ) );
		} catch ( Exception $error ) {
			$message = 'An exception has occurred. class: ' . get_class( $error ) . ';msg: ' . $error->getMessage() . ';code: ' . $error->getCode() . ';line: ' . $error->getLine() . ';in_file: ' . $error->getFile() . ';';
			error_log( $message );
			echo json_encode( array(
				'result' => 'failed',
				'error'  => $message,
			) );
		}
		die();
	}

	function upload_files_finish() {
		global $instawp_plugin;
		$instawp_plugin->ajax_check_security();

		$ret['html'] = false;

		if ( isset( $_POST['files'] ) ) {
			$files = wp_kses_post( wp_unslash( $_POST['files'] ) );
			$files = json_decode( $files, true );
			if ( is_null( $files ) ) {
				die();
			}

			$path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . InstaWP_Setting::get_backupdir() . DIRECTORY_SEPARATOR;

			$backup_data['result'] = 'success';
			$backup_data['files']  = array();
			if ( preg_match( '/instawp-.*_.*_.*\.zip$/', $files[0]['name'] ) ) {
				if ( preg_match( '/instawp-(.*?)_/', $files[0]['name'], $matches_id ) ) {
					if ( preg_match( '/[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}/', $files[0]['name'], $matches ) ) {
						$backup_time = $matches[0];
						$time_array  = explode( '-', $backup_time );
						if ( sizeof( $time_array ) > 4 ) {
							$time = $time_array[0] . '-' . $time_array[1] . '-' . $time_array[2] . ' ' . $time_array[3] . ':' . $time_array[4];
						} else {
							$time = $backup_time;
						}
						$time = strtotime( $time );
					} else {
						$time = time();
					}
					$id            = $matches_id[0];
					$id            = substr( $id, 0, strlen( $id ) - 1 );
					$unlinked_file = '';
					$check_result  = true;
					foreach ( $files as $file ) {
						$res = $this->check_is_a_instawp_backup( $path . $file['name'] );
						if ( $res === true ) {
							$add_file['file_name']  = $file['name'];
							$add_file['size']       = filesize( $path . $file['name'] );
							$backup_data['files'][] = $add_file;
						} else {
							$check_result  = false;
							$unlinked_file .= 'file name: ' . $file['name'] . ', error: ' . $res;
						}
					}
					if ( $check_result === true ) {
						InstaWP_Backuplist::add_new_upload_backup( $id, $backup_data, $time, '' );
						$html          = '';
						$html          = apply_filters( 'instawp_add_backup_list', $html );
						$ret['result'] = INSTAWP_SUCCESS;
						$ret['html']   = $html;
					} else {
						foreach ( $files as $file ) {
							$this->clean_tmp_files( $path, $file['name'] );
							@unlink( $path . $file['name'] );
						}
						$ret['result']   = INSTAWP_FAILED;
						$ret['error']    = 'Upload file failed.';
						$ret['unlinked'] = $unlinked_file;
					}
				} else {
					$ret['result'] = INSTAWP_FAILED;
					$ret['error']  = 'The backup is not created by instaWP backup plugin.';
				}
			} else {
				$ret['result'] = INSTAWP_FAILED;
				$ret['error']  = 'The backup is not created by instaWP backup plugin.';
			}
		} else {
			$ret['result'] = INSTAWP_FAILED;
			$ret['error']  = 'Failed to post file name.';
		}
		echo json_encode( $ret );
		die();
	}

	function clean_tmp_files( $path, $filename ) {
		$handler = opendir( $path );
		if ( $handler === false ) {
			return;
		}
		while ( ( $file = readdir( $handler ) ) !== false ) {
			if ( ! is_dir( $path . $file ) && preg_match( '/instawp-.*_.*_.*\.tmp$/', $file ) ) {
				$iPos      = strrpos( $file, '_' );
				$file_temp = substr( $file, 0, $iPos );
				if ( $file_temp === $filename ) {
					@unlink( $path . $file );
				}
			}
		}
		@closedir( $handler );
	}

	function instawp_check_remove_update_backup( $path ) {
		$backup_list         = InstaWP_Setting::get_option( 'instawp_backup_list' );
		$remove_backup_array = array();
		$update_backup_array = array();
		$tmp_file_array      = array();
		$remote_backup_list  = InstaWP_Backuplist::get_has_remote_backuplist();
		foreach ( $backup_list as $key => $value ) {
			if ( ! in_array( $key, $remote_backup_list ) ) {
				$need_remove = true;
				$need_update = false;
				if ( is_dir( $path ) ) {
					$handler = opendir( $path );
					if ( $handler === false ) {
						return true;
					}
					while ( ( $filename = readdir( $handler ) ) !== false ) {
						if ( $filename != "." && $filename != ".." ) {
							if ( ! is_dir( $path . $filename ) ) {
								if ( $this->check_instawp_file_info( $filename, $backup_id, $need_update ) ) {
									if ( $key === $backup_id ) {
										$need_remove = false;
									}
									if ( $need_update ) {
										if ( $this->check_is_a_instawp_backup( $path . $filename ) === true ) {
											if ( ! in_array( $filename, $tmp_file_array ) ) {
												$add_file['file_name']                        = $filename;
												$add_file['size']                             = filesize( $path . $filename );
												$tmp_file_array[]                             = $filename;
												$update_backup_array[ $backup_id ]['files'][] = $add_file;
											}
										}
									}
								}
							}
						}
					}
					if ( $handler ) {
						@closedir( $handler );
					}
				}
				if ( $need_remove ) {
					$remove_backup_array[] = $key;
				}
			}
		}
		$this->instawp_remove_update_local_backup_list( $remove_backup_array, $update_backup_array );

		return true;
	}

	function check_instawp_file_info( $file_name, &$backup_id, &$need_update = false ) {
		if ( preg_match( '/instawp-.*_.*_.*\.zip$/', $file_name ) ) {
			if ( preg_match( '/instawp-(.*?)_/', $file_name, $matches ) ) {
				$id        = $matches[0];
				$id        = substr( $id, 0, strlen( $id ) - 1 );
				$backup_id = $id;

				if ( InstaWP_Backuplist::get_backup_by_id( $id ) === false ) {
					$need_update = false;

					return true;
				} else {
					$need_update = true;

					return true;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function instawp_remove_update_local_backup_list( $remove_backup_array, $update_backup_array ) {
		$backup_list = InstaWP_Setting::get_option( 'instawp_backup_list' );
		foreach ( $remove_backup_array as $remove_backup_id ) {
			unset( $backup_list[ $remove_backup_id ] );
		}
		foreach ( $update_backup_array as $update_backup_id => $data ) {
			$backup_list[ $update_backup_id ]['backup']['files'] = $data['files'];
		}
		InstaWP_Setting::update_option( 'instawp_backup_list', $backup_list );
	}

	function _rescan_local_folder_set_backup() {
		$path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . InstaWP_Setting::get_backupdir() . DIRECTORY_SEPARATOR;

		$this->instawp_check_remove_update_backup( $path );

		$backups = array();
		$count   = 0;
		if ( is_dir( $path ) ) {
			$handler = opendir( $path );
			if ( $handler !== false ) {
				while ( ( $filename = readdir( $handler ) ) !== false ) {
					if ( $filename != "." && $filename != ".." ) {
						$count ++;

						if ( is_dir( $path . $filename ) ) {
							continue;
						} else {

							if ( $this->check_file_is_a_instawp_backup( $filename, $backup_id ) ) {

								if ( $this->zip_check_sum( $path . $filename ) ) {
									if ( $this->check_is_a_instawp_backup( $path . $filename ) === true ) {
										$backups[ $backup_id ]['files'][] = $filename;
									}
								}
							}
						}
					}
				}
				if ( $handler ) {
					@closedir( $handler );
				}
			}
		} else {
			$ret['result'] = INSTAWP_FAILED;
			$ret['error']  = 'Failed to get local storage directory.';
		}
		if ( ! empty( $backups ) ) {
			foreach ( $backups as $backup_id => $backup ) {
				$backup_data['result'] = 'success';
				$backup_data['files']  = array();
				if ( empty( $backup['files'] ) ) {
					continue;
				}
				$time = false;
				foreach ( $backup['files'] as $file ) {
					if ( $time === false ) {
						if ( preg_match( '/[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}/', $file, $matches ) ) {
							$backup_time = $matches[0];
							$time_array  = explode( '-', $backup_time );
							if ( sizeof( $time_array ) > 4 ) {
								$time = $time_array[0] . '-' . $time_array[1] . '-' . $time_array[2] . ' ' . $time_array[3] . ':' . $time_array[4];
							} else {
								$time = $backup_time;
							}
							$time = strtotime( $time );
						} else {
							$time = time();
						}
					}

					$add_file['file_name']  = $file;
					$add_file['size']       = filesize( $path . $file );
					$backup_data['files'][] = $add_file;
				}

				InstaWP_Backuplist::add_new_upload_backup( $backup_id, $backup_data, $time, '' );
			}
		}
		$ret['result'] = INSTAWP_SUCCESS;
		$html          = '';
		$tour          = true;
		$html          = apply_filters( 'instawp_add_backup_list', $html, 'instawp_backup_list', $tour );
		$ret['html']   = $html;

		return $ret;
	}

	function _rescan_local_folder_set_backup_api( $parameters = array() ) {

		global $InstaWP_Backup_Api;

		$response      = array( 'result' => INSTAWP_SUCCESS, 'html' => '', );
		$path          = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . InstaWP_Setting::get_backupdir() . DIRECTORY_SEPARATOR;
		$download_urls = InstaWP_Setting::get_args_option( 'urls', $parameters, array() );

		// $this->instawp_check_remove_update_backup( $path );

		if ( ! is_dir( $path ) ) {
			return array( 'result' => INSTAWP_FAILED, 'error' => esc_html__( 'Failed to get local storage directory.', 'instawp-connect' ) );
		}

		$backups = array();
		$handler = opendir( $path );

		if ( $handler !== false ) {
			while ( ( $filename = readdir( $handler ) ) !== false ) {

				if ( $filename == "." || $filename == ".." || is_dir( $path . $filename ) || ! $this->check_file_is_a_instawp_backup( $filename, $backup_id ) ) {
					continue;
				}

				if ( $this->zip_check_sum( $path . $filename ) ) {

					if ( $this->check_is_a_instawp_backup( $path . $filename ) === true ) {
						$backups[ $backup_id ]['files'][] = $filename;
					}
				} else {
					$InstaWP_Backup_Api->instawp_log->WriteLog( 'File Not Valid', 'error' );
				}
			}
			if ( $handler ) {
				@closedir( $handler );
			}
		}


		foreach ( $backups as $backup_id => $backup ) {

			$time        = false;
			$backup_data = array( 'result' => 'success', 'files' => array() );

			foreach ( InstaWP_Setting::get_args_option( 'files', $backup, array() ) as $file ) {

				if ( $time === false ) {
					if ( preg_match( '/[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9]{2}/', $file, $matches ) ) {
						$backup_time = $matches[0];
						$time_array  = explode( '-', $backup_time );
						if ( sizeof( $time_array ) > 4 ) {
							$time = $time_array[0] . '-' . $time_array[1] . '-' . $time_array[2] . ' ' . $time_array[3] . ':' . $time_array[4];
						} else {
							$time = $backup_time;
						}
						$time = strtotime( $time );
					} else {
						$time = time();
					}
				}

				$part_id = '';
				foreach ( $download_urls as $download_url ) {
					if ( basename( strtok( $download_url['url'], '?' ) ) === $file ) {
						$part_id = $download_url['part_id'];
						break;
					}
				}

				$backup_data['files'][] = array(
					'part_id'   => $part_id,
					'file_name' => $file,
					'size'      => filesize( $path . $file ),
				);
			}

			InstaWP_Backuplist::add_new_upload_backup( $backup_id, $backup_data, $time );
		}

		return $response;
	}


	public function instawp_rebuild_backup_list() {
		$this->_rescan_local_folder_set_backup();
	}


	function get_backup_count() {
		global $instawp_plugin;
		$instawp_plugin->ajax_check_security();

		$backuplist = InstaWP_Backuplist::get_backuplist();
		echo esc_html( sizeof( $backuplist ) );
		die();
	}

	static function upload_meta_box() {
		?>
        <div id="instawp_plupload-upload-ui" class="hide-if-no-js" style="margin-bottom: 10px;">
            <div id="drag-drop-area">
                <div class="drag-drop-inside">
                    <p class="drag-drop-info"><?php esc_html_e( 'Drop files here', 'instawp-connect' ); ?></p>
                    <p><?php echo esc_html_x( 'or', 'Uploader: Drop files here - or - Select Files', 'instawp-connect' ); ?></p>
                    <p class="drag-drop-buttons"><input id="instawp_select_file_button" type="button" value="<?php esc_attr_e( 'Select Files', 'instawp-connect' ); ?>" class="button"/></p>
                </div>
            </div>
        </div>
        <div id="instawp_uploaded_file_list" class="hide-if-no-js" style="margin-bottom: 10px;"></div>
        <div id="instawp_upload_file_list" class="hide-if-no-js" style="margin-bottom: 10px;"></div>
        <div style="margin-bottom: 10px;">
            <input type="submit" class="button-primary" id="instawp_upload_submit_btn" value="Upload" onclick="instawp_submit_upload();"/>
            <input type="submit" class="button-primary" id="instawp_stop_upload_btn" value="Cancel" onclick="instawp_cancel_upload();"/>
        </div>
        <div style="clear: both;"></div>
		<?php
		$chunk_size    = min( wp_max_upload_size(), 1048576 * 2 );
		$plupload_init = array(
			'browse_button'    => 'instawp_select_file_button',
			'container'        => 'instawp_plupload-upload-ui',
			'drop_element'     => 'drag-drop-area',
			'file_data_name'   => 'async-upload',
			'max_retries'      => 3,
			'multiple_queues'  => true,
			'max_file_size'    => '10Gb',
			'chunk_size'       => $chunk_size . 'b',
			'url'              => admin_url( 'admin-ajax.php' ),
			'multipart'        => true,
			'urlstream_upload' => true,
			// additional post data to send to our ajax hook
			'multipart_params' => array(
				'_ajax_nonce' => wp_create_nonce( 'instawp_ajax' ),
				'action'      => 'instawp_upload_files',            // the ajax action name
			),
		);

		// we should probably not apply this filter, plugins may expect wp's media uploader...
		$plupload_init     = apply_filters( 'plupload_init', $plupload_init );
		$upload_file_image = includes_url( '/images/media/archive.png' );
		?>

        <script type="text/javascript">
            var uploader;

            function instawp_stop_upload() {
                var ajax_data = {
                    'action': 'instawp_cancel_upload_backup_free',
                };
                instawp_post_request(ajax_data, function (data) {
                    jQuery("#instawp_select_file_button").prop('disabled', false);
                    jQuery('#instawp_upload_file_list').html("");
                    jQuery('#instawp_upload_submit_btn').hide();
                    jQuery('#instawp_stop_upload_btn').hide();
                    instawp_init_upload_list();
                }, function (XMLHttpRequest, textStatus, errorThrown) {
                    var error_message = instawp_output_ajaxerror('cancelling upload backups', textStatus, errorThrown);
                    alert(error_message);
                    jQuery('#instawp_upload_file_list').html("");
                    jQuery('#instawp_upload_submit_btn').hide();
                    jQuery('#instawp_stop_upload_btn').hide();
                    instawp_init_upload_list();
                });
            }

            function instawp_check_plupload_added_files(up, files) {
                var repeat_files = '';
                plupload.each(files, function (file) {
                    var brepeat = false;
                    var file_list = jQuery('#instawp_upload_file_list span');
                    file_list.each(function (index, value) {
                        if (value.innerHTML === file.name) {
                            brepeat = true;
                        }
                    });

                    if (!brepeat) {
                        var ajax_data = {
                            'action': 'instawp_is_backup_file_free',
                            'file_name': file.name
                        };
                        instawp_post_request(ajax_data, function (data) {
                            var jsonarray = jQuery.parseJSON(data);
                            if (jsonarray.result === "success") {
                                if (jsonarray.is_exists == true) {
                                    instawp_file_uploaded_queued(file);
                                    uploader.removeFile(file);
                                } else {
                                    instawp_fileQueued(file);
                                }
                            } else if (jsonarray.result === "failed") {
                                uploader.removeFile(file);
                                alert(jsonarray.error);
                            }
                        }, function (XMLHttpRequest, textStatus, errorThrown) {
                            var error_message = instawp_output_ajaxerror('uploading backups', textStatus, errorThrown);
                            uploader.removeFile(file);
                            alert(error_message);
                        });
                    } else {
                        if (repeat_files === '') {
                            repeat_files += file.name;
                        } else {
                            repeat_files += ', ' + file.name;
                        }
                    }
                });
                if (repeat_files !== '') {
                    alert(repeat_files + " already exists in upload list.");
                    repeat_files = '';
                }
            }

            function instawp_fileQueued(file) {
                jQuery('#instawp_upload_file_list').append(
                    '<div id="' + file.id + '" style="width: 100%; height: 36px; background: #fff; margin-bottom: 1px;">' +
                    '<img src=" <?php echo esc_url( $upload_file_image ); ?> " alt="" style="float: left; margin: 2px 10px 0 3px; max-width: 40px; max-height: 32px;">' +
                    '<div style="line-height: 36px; float: left; margin-left: 5px;"><span>' + file.name + '</span></div>' +
                    '<div class="fileprogress" style="line-height: 36px; float: right; margin-right: 5px;"></div>' +
                    '</div>' +
                    '<div style="clear: both;"></div>'
                );
                jQuery('#instawp_upload_submit_btn').show();
                jQuery('#instawp_stop_upload_btn').show();
                jQuery("#instawp_upload_submit_btn").prop('disabled', false);
            }

            function instawp_file_uploaded_queued(file) {
                jQuery('#' + file.id).remove();
                jQuery('#instawp_uploaded_file_list').append(
                    '<div id="' + file.id + '" style="width: 100%; height: 36px; background: #8bc34a; margin-bottom: 1px;">' +
                    '<img src=" <?php echo esc_url( $upload_file_image ); ?> " alt="" style="float: left; margin: 2px 10px 0 3px; max-width: 40px; max-height: 32px;">' +
                    '<div style="line-height: 36px; float: left; margin-left: 5px;"><span>' + file.name + '</span></div>' +
                    '<div class="fileprogress" style="line-height: 36px; float: right; margin-right: 5px;"></div>' +
                    '</div>' +
                    '<div style="clear: both;"></div>'
                );
            }

            function instawp_init_upload_list() {
                uploader = new plupload.Uploader(<?php echo json_encode( $plupload_init ); ?>);

                // checks if browser supports drag and drop upload, makes some css adjustments if necessary
                uploader.bind('Init', function (up) {
                    var uploaddiv = jQuery('#instawp_plupload-upload-ui');

                    if (up.features.dragdrop) {
                        uploaddiv.addClass('drag-drop');
                        jQuery('#drag-drop-area')
                            .bind('dragover.wp-uploader', function () {
                                uploaddiv.addClass('drag-over');
                            })
                            .bind('dragleave.wp-uploader, drop.wp-uploader', function () {
                                uploaddiv.removeClass('drag-over');
                            });

                    } else {
                        uploaddiv.removeClass('drag-drop');
                        jQuery('#drag-drop-area').unbind('.wp-uploader');
                    }
                });

                uploader.init();
                // a file was added in the queue

                uploader.bind('FilesAdded', instawp_check_plupload_added_files);

                uploader.bind('Error', function (up, error) {
                    alert('Upload ' + error.file.name + ' error, error code: ' + error.code + ', ' + error.message);
                    instawp_stop_upload();
                });

                uploader.bind('FileUploaded', function (up, file, response) {
                    var jsonarray = jQuery.parseJSON(response.response);
                    if (jsonarray.result == 'failed') {
                        alert('upload ' + file.name + ' failed, ' + jsonarray.error);

                        uploader.stop();
                        instawp_stop_upload();
                    } else {
                        instawp_file_uploaded_queued(file);
                    }
                });

                uploader.bind('UploadProgress', function (up, file) {
                    jQuery('#' + file.id + " .fileprogress").html(file.percent + "%");
                });

                uploader.bind('UploadComplete', function (up, files) {
                    jQuery('#instawp_upload_file_list').html("");
                    jQuery('#instawp_upload_submit_btn').hide();
                    jQuery('#instawp_stop_upload_btn').hide();
                    jQuery("#instawp_select_file_button").prop('disabled', false);
                    var ajax_data = {
                        'action': 'instawp_upload_files_finish_free'
                    };
                    instawp_post_request(ajax_data, function (data) {
                        try {
                            var jsonarray = jQuery.parseJSON(data);
                            if (jsonarray.result === 'success') {
                                alert('The upload has completed.');
                                jQuery('#instawp_backup_list').html('');
                                jQuery('#instawp_backup_list').append(jsonarray.html);
                                instawp_click_switch_page('backup', 'instawp_tab_backup', true);
                                location.href = '<?php echo esc_url( admin_url() ) . 'admin.php?page=instawp-connect'; ?>';
                            } else {
                                alert(jsonarray.error);
                            }
                        } catch (err) {
                            alert(err);
                        }
                    }, function (XMLHttpRequest, textStatus, errorThrown) {
                        var error_message = instawp_output_ajaxerror('refreshing backup list', textStatus, errorThrown);
                        alert(error_message);
                    });
                    plupload.each(files, function (file) {
                        if (typeof file === 'undefined') {

                        } else {
                            uploader.removeFile(file.id);
                        }
                    });
                });

                uploader.bind('Destroy', function (up, file) {
                    instawp_stop_upload();
                });
            }

            jQuery(document).ready(function ($) {
                // create the uploader and pass the config from above
                jQuery('#instawp_upload_submit_btn').hide();
                jQuery('#instawp_stop_upload_btn').hide();
                instawp_init_upload_list();
            });

            function instawp_submit_upload() {
                jQuery("#instawp_upload_submit_btn").prop('disabled', true);
                jQuery("#instawp_select_file_button").prop('disabled', true);
                uploader.refresh();
                uploader.start();
            }

            function instawp_cancel_upload() {
                uploader.destroy();
            }
        </script>
		<?php
	}

	public function upload_dir( $uploads ) {
		$uploads['path'] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . InstaWP_Setting::get_backupdir();

		return $uploads;
	}

	private function check_is_a_instawp_backup( $file_name ) {
		$ret = InstaWP_Backup_Item::get_backup_file_info( $file_name );
		if ( $ret['result'] === INSTAWP_SUCCESS ) {
			return true;
		} elseif ( $ret['result'] === INSTAWP_FAILED ) {
			return $ret['error'];
		}
	}

	private function zip_check_sum( $file_name ) {
		return true;
	}
}