<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}
if ( ! defined( 'INSTAWP_REMOTE_S3COMPAT' ) ) {
	define( 'INSTAWP_REMOTE_S3COMPAT', 's3compat' );
}
if ( ! defined( 'INSTAWP_UPLOAD_SUCCESS' ) ) {
	define( 'INSTAWP_UPLOAD_SUCCESS', 1 );
}

if ( ! defined( 'INSTAWP_UPLOAD_FAILED' ) ) {
	define( 'INSTAWP_UPLOAD_FAILED', 2 );
}

if ( ! defined( 'INSTAWP_UPLOAD_UNDO' ) ) {
	define( 'INSTAWP_UPLOAD_UNDO', 0 );
}

class InstaWP_Curl {
	private $api_key;
	private $task_id;
	public $response;
	public $fp;

	public function __construct() {
		add_action( "http_api_curl", array( $this, "instawp_http_api_curl" ), 10, 3 );
	}


	public static function do_curl( $endpoint, $body = array(), $headers = array(), $is_post = true, $api_version = 'v2' ) {

		$connect_options = InstaWP_Setting::get_option( 'instawp_api_options', array() );

		if ( empty( $api_url = InstaWP_Setting::get_api_domain() ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'Invalid or Empty API Domain', 'instawp-connect' ) );
		}

		if ( empty( $api_key = InstaWP_Setting::get_args_option( 'api_key', $connect_options ) ) ) {
			return array( 'success' => false, 'message' => esc_html__( 'Invalid or Empty API Key', 'instawp-connect' ) );
		}

		$api_url = $api_url . '/api/' . $api_version . '/' . $endpoint;
		$headers = wp_parse_args( $headers,
			array(
				'Authorization: Bearer ' . $api_key,
				'Accept: application/json',
				'Content-Type: application/json',
			)
		);

		if ( is_string( $is_post ) && $is_post == 'patch' ) {
			$api_method = 'PATCH';
		} else {
			$api_method = $is_post ? 'POST' : 'GET';
		}

		$curl = curl_init();

		curl_setopt_array( $curl,
			array(
				CURLOPT_URL            => $api_url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => $api_method,
				CURLOPT_POSTFIELDS     => json_encode( $body ),
				CURLOPT_HTTPHEADER     => $headers,
			)
		);
		$api_response = curl_exec( $curl );
		curl_close( $curl );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'API URL - ' . $api_url );
			error_log( 'API ARGS - ' . json_encode( $body ) );
			error_log( 'API Response - ' . $api_response );
		}

		$api_response     = json_decode( $api_response, true );
		$response_status  = InstaWP_Setting::get_args_option( 'status', $api_response );
		$response_data    = InstaWP_Setting::get_args_option( 'data', $api_response, array() );
		$response_message = InstaWP_Setting::get_args_option( 'message', $api_response );

		return array( 'success' => $response_status, 'message' => $response_message, 'data' => $response_data );
	}


	public function curl( $url, $body, $header = array(), $is_post = true ) {
		global $instawp_plugin, $InstaWP_Backup_Api;
		$this->set_api_key();
		$res     = array();
		$headers = array();
		$headers = array(
			'Authorization: Bearer ' . $this->api_key,
			'Accept: application/json',
			'Content-Type: application/json',
		);

		if ( ! empty( $header ) ) {
			array_push( $headers, $header );
		}

		$instawp_plugin->instawp_log->WriteLog( 'Initiate Api Call API_URL: ' . $url . ' Headres: ' . json_encode( $headers ) . ' Body: ' . $body, 'notice' );
		$InstaWP_Backup_Api->instawp_log->WriteLog( 'Initiate Api Call API_URL: ' . $url . ' Headres: ' . json_encode( $headers ) . ' Body: ' . $body, 'notice' );

		$useragent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$api_method = $is_post ? 'POST' : 'GET';

		$args           = array(
			'method'     => $api_method,
			'body'       => $body,
			'timeout'    => 0,
			'decompress' => false,
			'stream'     => false,
			'filename'   => '',
			'user-agent' => $useragent,
			'headers'    => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json'
			)
		);
		$WP_Http_Curl   = new WP_Http_Curl();
		$this->response = $WP_Http_Curl->request( $url, $args );
		update_option( 'main_curl_1', $this->response );

		if ( $this->response instanceof WP_Error || is_wp_error( $this->response ) ) {
			return array(
				'error'    => true,
				'curl_res' => $this->response,
				'message'  => $this->response->get_error_message(),
			);
		} else if ( ! $this->response ) {

			$res['message']  = '';
			$res['error']    = 1;
			$res['curl_res'] = $this->response;
			$instawp_plugin->instawp_log->WriteLog( 'Response:' . $this->response, 'error' );
			$InstaWP_Backup_Api->instawp_log->WriteLog( 'Response:' . $this->response, 'error' );
		} else {
			$respons_arr = (array) json_decode( $this->response['body'] );
			if ( isset( $respons_arr['status'] ) && $respons_arr['status'] == 1 ) {

				$res['error']    = 0;
				$res['curl_res'] = $this->response['body'];
				$res['message']  = $respons_arr['message'];
				$respons_arr     = $this->response;
				//$instawp_plugin->instawp_log->WriteLog( 'Message:' . $respons_arr['message']. ' API_URL : '.$url, 'success');
				$instawp_plugin->instawp_log->WriteLog( 'Response:' . $this->response['body'], 'response' );
				$InstaWP_Backup_Api->instawp_log->WriteLog( 'Response:' . $this->response['body'] . ' API_URL : ' . $url, 'success' );
			} else {
				$res['message']  = $respons_arr['message'];
				$res['error']    = 1;
				$res['curl_res'] = $this->response;
				$respons_arr     = (array) json_decode( $this->response['body'] );
				$instawp_plugin->instawp_log->WriteLog( 'Response:' . json_encode( $respons_arr ) . ' API_URL : ' . $url, 'error' );
				//$InstaWP_Backup_Api->instawp_log->WriteLog( 'Message:' . $respons_arr['message']. ' API_URL : '.$url, 'error');
				$InstaWP_Backup_Api->instawp_log->WriteLog( 'Response:' . json_encode( $respons_arr ), 'error' );
			}
		}

		return $res;
	}

	public function set_api_key() {
		global $instawp_plugin;
		$res             = array();
		$connect_options = get_option( 'instawp_api_options', '' );

		if ( isset( $connect_options['api_key'] ) && ! empty( $connect_options['api_key'] ) ) {
			$this->api_key = $connect_options['api_key'];
			$instawp_plugin->instawp_log->WriteLog( 'Set Api Key:' . $this->api_key, 'success' );
		} else {
			$res['error']   = true;
			$res['message'] = 'API Key Is Required';
			echo json_encode( $res );
			$instawp_plugin->instawp_log->WriteLog( 'API Key is required', 'success' );
			wp_die();
		}
	}

	public function pre_upload( $task_id, $presigned_urls ) {

		if ( ! empty( $presigned_urls ) ) {
			InstaWP_taskmanager::update_backup_main_task_progress( $task_id, 'upload_initiate', 0, 0 );
			InstaWP_taskmanager::update_backup_task_status( $task_id, false, 'upload_initiate' );

			return array( 'result' => INSTAWP_SUCCESS );
		} else {
			return array( 'result' => INSTAWP_FAILED );
		}

	}

	public function new_upload( $files, $task_id ) {
		global $instawp_plugin;
		$this->task_id = $task_id;

		$InstaWP_BackupUploader = new InstaWP_BackupUploader();
		$res                    = $InstaWP_BackupUploader->_rescan_local_folder_set_backup_api();

		$upload_job = InstaWP_taskmanager::get_backup_sub_task_progress( $task_id, 'upload', INSTAWP_REMOTE_S3COMPAT );
		if ( empty( $upload_job ) ) {
			$job_data = array();
			foreach ( $files as $file ) {
				$file_data['size']             = filesize( $file );
				$file_data['uploaded']         = 0;
				$job_data[ basename( $file ) ] = $file_data;
			}
			InstaWP_taskmanager::update_backup_sub_task_progress( $task_id, 'upload', INSTAWP_REMOTE_S3COMPAT, INSTAWP_UPLOAD_UNDO, 'Start uploading', $job_data );
			$upload_job = InstaWP_taskmanager::get_backup_sub_task_progress( $task_id, 'upload', INSTAWP_REMOTE_S3COMPAT );
		}

		$files_info       = array();
		$backup_info      = array();
		$backup_info_temp = array();
		foreach ( $files as $file ) {
			$filesize               = filesize( $file );
			$size                   = round( $filesize / 1024 / 1024 );
			$files_info['filename'] = basename( $file );
			$files_info['size']     = $size;
			array_push( $backup_info_temp, $files_info );
		}
		$backup_info['task_id']   = $task_id;
		$backup_info['backup_id'] = $task_id;
		$backup_info['files']     = $backup_info_temp;
		update_option( 'instawp_init_upload', $backup_info );

		$presigned_urls = $this->get_presigned_url( $backup_info );
		update_option( 'presigned_urls', $presigned_urls );

		if ( empty( $presigned_urls ) ) {

			$instawp_plugin->instawp_log->WriteLog( 'Presinged URLs are missing', 'error' );
			// return array(
			// 'result' => INSTAWP_FAILED,
			// 'error'  => 'Upload Faild',
			// );
		}
		foreach ( $files as $file_key => $file ) {
			if ( is_array( $upload_job['job_data'] ) && array_key_exists( basename( $file ), $upload_job['job_data'] ) ) {
				if ( $upload_job['job_data'][ basename( $file ) ]['uploaded'] == 1 ) {
					continue;
				}
			}
			//$instawp_plugin->set_time_limit($task_id);
			if ( ! file_exists( $file ) ) {
				$instawp_plugin->instawp_log->WriteLog( 'Uploading ' . basename( $file ) . ' failed.', 'notice' );

				return array(
					'result' => INSTAWP_FAILED,
					'error'  => $file . ' not found. The file might has been moved, renamed or deleted. Please reload the list and verify the file exists.',
				);
			}
			if ( isset( $presigned_urls['data']['urls'][ $file_key ] ) && ! empty( $presigned_urls['data']['urls'][ $file_key ] ) ) {
				$presigned_url = $presigned_urls['data']['urls'][ $file_key ];
				$instawp_plugin->instawp_log->WriteLog( 'Start uploading URL:' . $presigned_url, 'notice' );
				$result                                                  = $this->_upload_loop( $file, $presigned_url, $file_key );
				$upload_job['job_data'][ basename( $file ) ]['uploaded'] = 1;
				$instawp_plugin->instawp_log->WriteLog( 'Finished uploading ' . basename( $file ), 'notice' );
				InstaWP_taskmanager::update_backup_sub_task_progress( $task_id, 'upload', INSTAWP_REMOTE_S3COMPAT, INSTAWP_UPLOAD_SUCCESS, 'Uploading ' . basename( $file ) . ' completed.', $upload_job['job_data'] );
				update_option( 'instawp_job_data', $upload_job );
			} else {
				$instawp_plugin->instawp_log->WriteLog( 'Failed uploading. Reason: Presigned URL is missing for ' . basename( $file ), 'error' );
			}
		}

		return $result;
	}

	public function instawp_http_api_curl( $ch, $parsed_args, $url ) {

		if ( isset( $parsed_args['upload'] ) && $parsed_args['upload'] == true ) {
			curl_setopt( $ch, CURLOPT_PROGRESSFUNCTION, function ( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded ) {
				$this->upload_progress_callback( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded );
			} );
			curl_setopt( $ch, CURLOPT_NOPROGRESS, false );
		}

		if ( isset( $parsed_args['download'] ) && $parsed_args['download'] && ! empty( $this->fp ) ) {
			curl_setopt( $ch, CURLOPT_VERBOSE, 1 );
			curl_setopt( $ch, CURLOPT_AUTOREFERER, false );
			curl_setopt( $ch, CURLOPT_FILE, $this->fp );
			curl_setopt( $ch, CURLOPT_PROGRESSFUNCTION, function ( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded ) {
				$this->download_progress_callback( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded );
			} );
			curl_setopt( $ch, CURLOPT_NOPROGRESS, false );
		}
	}

	public function _upload_loop( $files, $presigned_url, $file_key ) {
		global $instawp_plugin;
		$parts     = array();
		$backupdir = 'instawpbackups';
		$path      = $files;
		$success   = false;
		$filesize  = strlen( $path );

		$curl_error = '';
		$useragent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		for ( $i = 0; $i < INSTAWP_REMOTE_CONNECT_RETRY_TIMES; $i ++ ) {

			$args           = array(
				'method'     => 'PUT',
				'body'       => file_get_contents( $path ),
				'timeout'    => 0,
				'decompress' => false,
				'stream'     => false,
				'filename'   => '',
				'user-agent' => $useragent,
				'headers'    => array(
					'Content-Type' => 'multipart/form-data'
				),
				'upload'     => true

			);
			$WP_Http_Curl   = new WP_Http_Curl();
			$this->response = $WP_Http_Curl->request( $presigned_url, $args );
			// $ch = curl_init();

			// curl_setopt($ch, CURLOPT_URL, $presigned_url);
			// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			// curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');

			// $headers   = array();
			// $headers[] = 'multipart/form-data';
			// curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


			// curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($path));

			// curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded ) use ( $files ) {
			//    $this->upload_progress_callback($resource, $downloadSize, $downloaded, $uploadSize, $uploaded, $files);
			// });
			// curl_setopt($ch, CURLOPT_NOPROGRESS, false);

			// $this->response = curl_exec($ch);

			if ( ! $this->response ) {
				//echo 'Error:' . curl_error($ch);
				//$curl_error = curl_error($ch);
				update_option( 'instawp_demo_parts_error', json_encode( $this->response ) );
				$instawp_plugin->instawp_log->WriteLog( json_encode( $this->response ), 'fail' );
			} else {
				update_option( 'instawp_demo_parts', json_encode( $this->response ) );

				return array(
					'result' => INSTAWP_SUCCESS,
				);
				//return array( 'result' => INSTAWP_SUCCESS );
			}

			//curl_close($ch);

		}
		$instawp_plugin->instawp_log->WriteLog( 'Failed uploading. Reason:' . json_encode( $this->response ), 'error' );

		return array(

			'result' => INSTAWP_FAILED,
			'error'  => 'Multipart upload failed. File name: ' . basename( $path ),
		);

	}

	public function get_presigned_url( $backup_info ) {
		global $instawp_plugin;
		$php_version                = substr( phpversion(), 0, 3 );
		$backup_info['task_id']     = $this->task_id;
		$backup_info['php_version'] = $php_version;
		$backup_info_json           = json_encode( $backup_info );
		$connect_ids                = get_option( 'instawp_connect_id_options', '' );

		if ( ! empty( $connect_ids ) ) {
			if ( isset( $connect_ids['data']['id'] ) && ! empty( $connect_ids['data']['id'] ) ) {
				$id            = $connect_ids['data']['id'];
				$api_doamin    = InstaWP_Setting::get_api_domain();
				$url           = $api_doamin . INSTAWP_API_URL . '/connects/' . $id . '/backup_upload';
				$curl_response = $this->curl( $url, $backup_info_json );
				if ( $curl_response['error'] == 0 ) {

					$response = (array) json_decode( $curl_response['curl_res'], true );
					if ( $response['status'] == 1 ) {
						update_option( 'instawp_backup_upload_options', $response ); // old
						//$backup_init[ 'presigned_urls' ] = $response;
						//InstaWP_Setting::update_connect_option('instawp_connect_options',$response,$id,$this->task_id,'backup_upload');
						$instawp_plugin->instawp_log->WriteLog( 'Presigned URLs Saved' . $url, 'success' );

						return $response;
						// return array(
						// 'result' => INSTAWP_SUCCESS,
						// 'error'  => 'Multipart upload failed. File name: ' . $this->current_file_name,
						// );


					} else {
						$instawp_plugin->instawp_log->WriteLog( 'Presigned URLs Missing API_URL: ' . $url, 'error' );

						return array(
							'result' => INSTAWP_FAILED,
							'error'  => 'Multipart upload failed. File name: ' . $this->current_file_name,
						);

						update_option( 'instawp_backup_upload_err_options', $curl_reponse );
					}
				}
			}
		}
	}

	public function upload_progress_callback( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded, $files = array() ) {
		global $instawp_plugin;
		$progress     = 0;
		$current_size = intval( $uploadSize - $uploaded );
		$dummy        = array();
		//array_push($dummy,$resource);
		$dummy['downloadSize'] = $downloadSize;
		$dummy['downloaded']   = $downloaded;
		$dummy['uploadSize']   = $uploadSize;
		$dummy['uploaded']     = $uploaded;
		$dummy['current_size'] = $current_size;
		//$dummy['files']        = basename($files);
		$offset = $uploaded;
		//$progress = intval( round( $uploaded * 100 / $uploadSize ) );
		if ( $uploadSize > 0 ) {
			$progress = round( ( $uploaded / $uploadSize ) * 100 );
		}

		//$progress = intval(($offset / $uploadSize) * 100);
		$dummy['progress'] = $progress;
		InstaWP_taskmanager::update_backup_main_task_progress( $this->task_id, 'upload', $progress, 0 );
		//InstaWP_taskmanager::update_backup_main_current_upload($this->task_id, 'current_upload', basename($files));
		// InstaWP_taskmanager::update_file_desc($this->task_id, $files['filename']);
		update_option( 'instawp_curl_progress', $dummy );
		$instawp_plugin->instawp_log->WriteLog( 'Upload Progress' . json_encode( $dummy ), 'notice' );

	}

	public function download( $task_id, $parameters = array() ) {

		global $instawp_plugin;

		$this->task_id = $task_id;

		InstaWP_taskmanager::update_backup_main_task_progress( $this->task_id, 'download', 0, 0 );

		$download_job  = InstaWP_taskmanager::get_backup_sub_task_progress( $task_id, 'download', INSTAWP_REMOTE_S3COMPAT );
		$download_urls = InstaWP_Setting::get_args_option( 'urls', $parameters, array() );
		$migrate_id    = isset( $parameters['wp']['options']['instawp_migrate_id'] ) ? $parameters['wp']['options']['instawp_migrate_id'] : '';

		if ( empty( $download_job ) ) {

			$job_data = array();

			foreach ( $download_urls as $download_url ) {

				$url      = InstaWP_Setting::get_args_option( 'url', $download_url );
				$basename = basename( parse_url( $url, PHP_URL_PATH ) );

				$job_data[ $basename ] = array(
					'size'       => 0,
					'downloaded' => 0
				);
			}

			InstaWP_taskmanager::update_backup_sub_task_progress( $task_id, 'download', INSTAWP_REMOTE_S3COMPAT, INSTAWP_UPLOAD_UNDO, 'Start downloading', $job_data );

			$download_job = InstaWP_taskmanager::get_backup_sub_task_progress( $task_id, 'download', INSTAWP_REMOTE_S3COMPAT );
		}

		foreach ( $download_urls as $download_url ) {

			$url      = InstaWP_Setting::get_args_option( 'url', $download_url );
			$part_id  = InstaWP_Setting::get_args_option( 'part_id', $download_url );
			$basename = basename( parse_url( $url, PHP_URL_PATH ) );

			if ( is_array( $download_job['job_data'] ) && array_key_exists( $basename, $download_job['job_data'] ) ) {
				if ( $download_job['job_data'][ $basename ]['downloaded'] == 1 ) {
					continue;
				}
			}

			$instawp_plugin->set_time_limit( $task_id );

			$result = $this->download_loop( $url );

			if ( $result['result'] == INSTAWP_SUCCESS ) {

				$download_job['job_data'][ $basename ]['downloaded'] = 1;

				instawp_update_migration_status( $migrate_id, $part_id, array( 'progress' => 50, 'message' => esc_html__( 'Download completed for this part', 'instawp-connect' ) ) );

				InstaWP_taskmanager::update_backup_sub_task_progress( $task_id, 'upload', INSTAWP_REMOTE_S3COMPAT, INSTAWP_UPLOAD_SUCCESS, 'Uploading ' . basename( $basename ) . ' completed.', $download_job['job_data'] );
			}
		}

		if ( $result['result'] == INSTAWP_SUCCESS ) {
			InstaWP_taskmanager::update_backup_main_task_progress( $this->task_id, 'download', 100, 1 );
			InstaWP_taskmanager::update_backup_task_status( $this->task_id, false, 'completed' );
		}

		return $result;
	}

	public function download_loop( $url ) {

		$basename        = basename( parse_url( $url, PHP_URL_PATH ) );
		$output_filename = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . InstaWP_Setting::get_backupdir() . DIRECTORY_SEPARATOR . basename( $basename );
		$this->fp        = fopen( $output_filename, 'w+' );
		$useragent       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		for ( $i = 0; $i < INSTAWP_REMOTE_CONNECT_RETRY_TIMES; $i ++ ) {

			$args           = array(
				'timeout'    => 1800,
				'download'   => true,
				'decompress' => false,
				'stream'     => false,
				'filename'   => '',
				'user-agent' => $useragent,
			);
			$WP_Http_Curl   = new WP_Http_Curl();
			$this->response = $WP_Http_Curl->request( $url, $args );

			if ( $this->fp ) {
				fclose( $this->fp );
			}

			if ( $this->response ) {
				return array( 'result' => INSTAWP_SUCCESS, 'error' => '' );
			}
		}

		if ( $this->fp ) {
			fclose( $this->fp );
		}

		return array(
			'result' => INSTAWP_FAILED,
			'error'  => 'Download failed, retries exhausted',
		);
	}


	public function _output( $output_filename, $content ) {

		$fp = fopen( $output_filename, 'w+' );
		//fwrite($fp, $this->response);

		$pieces = str_split( $content, 1024 * 4 );
		foreach ( $pieces as $piece ) {
			fwrite( $fp, $piece, strlen( $piece ) );
		}
		fclose( $fp );
	}

	public function download_progress_callback( $resource, $downloadSize, $downloaded, $uploadSize, $uploaded, $files = array() ) {
		global $InstaWP_Backup_Api;


		$current_size = intval( $downloadSize - $downloaded );
		$dummy        = array();
		//array_push($dummy,$resource);
		$dummy['downloadSize'] = $downloadSize;
		$dummy['downloaded']   = $downloaded;
		$dummy['uploadSize']   = $uploadSize;
		$dummy['uploaded']     = $uploaded;
		$dummy['current_size'] = $current_size;
		//$dummy['files']        = basename($files);
		$offset = $downloaded;
		//$progress = intval( round( $uploaded * 100 / $uploadSize ) );

		if ( $downloadSize > 0 ) {

			$progress = round( ( $downloaded / $downloadSize ) * 100 );
		} else {
			$progress = 0;
		}

		//$progress = intval(($offset / $uploadSize) * 100);
		$dummy['progress'] = $progress;
		InstaWP_taskmanager::update_backup_main_task_progress( $this->task_id, 'download', $progress, 0 );
		update_option( 'instawp_curl_download_progress', $dummy );
		$InstaWP_Backup_Api->instawp_log->WriteLog( 'Download Progress : ' . json_encode( $dummy ), 'notice' );
		if ( $downloaded >= $downloadSize ) {
			//$InstaWP_Backup_Api->instawp_log->WriteLog('Finish download : '.json_encode($dummy), 'notice');
			update_option( 'instawp_finish_download', $dummy );
		}

	}
}

global $InstaWP_Curl;
$InstaWP_Curl = new InstaWP_Curl();
