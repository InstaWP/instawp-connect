<?php

include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-restore-db-pdo-mysql-method.php';
include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-restore-db-wpdb-method.php';

class InstaWP_Restore_DB_Method {
	private $db;
	private $type;

	public function __construct() {
		global $instawp_plugin;
		$common_setting    = InstaWP_Setting::get_setting( false, 'instawp_common_setting' );
		$default_method    = extension_loaded( 'pdo' ) ? 'pdo' : 'wpdb';
		$db_connect_method = isset( $common_setting['options']['instawp_common_setting']['db_connect_method'] ) ? $common_setting['options']['instawp_common_setting']['db_connect_method'] : $default_method;

		if ( $db_connect_method === 'wpdb' ) {
			$instawp_plugin->restore_data->write_log( 'wpdb', 'Warning' );
			$this->db   = new InstaWP_Restore_DB_WPDB_Method();
			$this->type = 'wpdb';
		} else {
			$instawp_plugin->restore_data->write_log( 'pdo_mysql', 'Warning' );
			$this->db   = new InstaWP_Restore_DB_PDO_Mysql_Method();
			$this->type = 'pdo_mysql';
		}
	}

	public function get_type() {
		return $this->type;
	}

	public function connect_db() {
		return $this->db->connect_db();
	}

	public function test_db() {
		return $this->db->test_db();
	}

	public function check_max_allow_packet() {
		$this->db->check_max_allow_packet();
	}

	public function get_max_allow_packet() {
		return $this->db->get_max_allow_packet();
	}

	public function init_sql_mode() {
		$this->db->init_sql_mode();
	}

	public function set_skip_query( $count ) {
		$this->db->set_skip_query( $count );
	}

	public function execute_sql( $query ) {
		$this->db->execute_sql( $query );
	}

	public function query( $sql, $output = ARRAY_A ) {
		return $this->db->query( $sql, $output );
	}

	public function errorInfo() {
		return $this->db->errorInfo();
	}
}