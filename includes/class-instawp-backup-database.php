<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}
define( 'NECESSARY', '1' );
define( 'OPTION', '0' );

class InstaWP_Backup_Database {
	private $task_id;

	public function __construct() {
	}

	public function instawp_archieve_database_info( $data = array() ) {

		$databases = array();

		if ( isset( $data['dump_db'] ) ) {
			$sql_info['file_name'] = $data['sql_file_name'];
			$sql_info['database']  = DB_NAME;
			$sql_info['host']      = DB_HOST;
			$sql_info['user']      = DB_USER;
			$sql_info['pass']      = DB_PASSWORD;
			$databases[]           = $sql_info;
		}

		return apply_filters( 'instawp_archieve_database_info', $databases, $data );
	}

	public function backup_database( $data, $task_id = '' ) {

		global $instawp_plugin, $wpdb;

		$dump         = null;
		$backup_files = array();

		try {
			$this->task_id = $task_id;

			require_once 'class-instawp-mysqldump-method.php';
			require_once 'class-instawp-mysqldump.php';

			$db_method = new InstaWP_DB_Method();
			$version   = $db_method->get_mysql_version();

			if ( version_compare( '4.1.0', $version ) > 0 ) {
				return array(
					'result' => INSTAWP_FAILED,
					'error'  => 'Your MySQL version is too old. Please upgrade at least to MySQL 4.1.0.',
				);
			}

			if ( version_compare( '5.3.0', phpversion() ) > 0 ) {
				return array(
					'result' => INSTAWP_FAILED,
					'error'  => 'Your PHP version is too old. Please upgrade at least to PHP 5.3.0.',
				);
			}

			$db_method->check_max_allowed_packet();

			if ( is_multisite() && ! defined( 'MULTISITE' ) ) {
				$prefix = $wpdb->base_prefix;
			} else {
				$prefix = $wpdb->get_blog_prefix( 0 );
			}

			$prefix           = apply_filters( 'instawp_dump_set_prefix', $prefix, $data );
			$is_additional_db = false;

			if ( $data['key'] === 'backup_additional_db' ) {
				$is_additional_db = true;
			}

			foreach ( $this->instawp_archieve_database_info( $data ) as $sql_info ) {

				$backup_file    = $sql_info['file_name'];
				$backup_files[] = $backup_file;
				$dumpSettings   = array(
					'exclude_tables'       => $data['exclude_tables'] ?? array(),
					'exclude_tables_data'  => $data['exclude_tables_data'] ?? array(),
					'exclude_options_keys' => array( 'instawp_api_options' ),
					'include_tables'       => apply_filters( 'instawp_include_db_table', [], $data ),
					'add-drop-table'       => true,
					'extended-insert'      => false,
					'site_url'             => apply_filters( 'instawp_dump_set_site_url', get_site_url(), $data ),
					'home_url'             => apply_filters( 'instawp_dump_set_home_url', get_home_url(), $data ),
					'content_url'          => apply_filters( 'instawp_dump_set_content_url', content_url(), $data ),
					'prefix'               => $prefix,
				);

				$dump = new InstaWP_Mysqldump( $sql_info['host'], $sql_info['database'], $sql_info['user'], $sql_info['pass'], $is_additional_db, $dumpSettings );

				if ( file_exists( $backup_file ) ) {
					@unlink( $backup_file );
				}

				$dump->task_id = $task_id;
				$dump->start( $backup_file );
			}

			unset( $pdo );
		} catch ( Exception $e ) {
			$str_last_query_string = '';
			if ( ! is_null( $dump ) ) {
				$str_last_query_string = $dump->last_query_string;
			}
			if ( ! empty( $str_last_query_string ) ) {
				$instawp_plugin->instawp_log->WriteLog( 'last query string:' . $str_last_query_string, 'error' );
			}
			$message = 'A exception (' . get_class( $e ) . ') occurred ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ', line ' . $e->getLine() . ' in ' . $e->getFile() . ')';

			return array(
				'result' => INSTAWP_FAILED,
				'error'  => $message,
			);
		}

		return array(
			'result' => INSTAWP_SUCCESS,
			'files'  => $backup_files,
		);
	}

	public function exclude_table( $exclude, $data ) {
		global $wpdb;
		if ( is_multisite() && ! defined( 'MULTISITE' ) ) {
			$prefix = $wpdb->base_prefix;
		} else {
			$prefix = $wpdb->get_blog_prefix( 0 );
		}
		$exclude = array( '/^(?!' . $prefix . ')/i' );

		return $exclude;
	}
}