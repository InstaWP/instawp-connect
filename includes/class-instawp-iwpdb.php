<?php
// phpcs:disable

include_once 'functions-pull-push.php';

class IWPDB {

	/**
	 * @var mysqli
	 */
	public $conn = null;
	public $last_error = '';
	private $migrate_key = '';
	private $options_data = array();
	private $max_retries = 10;

	private static $_table_option = 'iwp_options';

	public function __construct( $key ) {
		$this->migrate_key = $key;

		$this->set_options_data();
		$this->connect_database();
		$this->create_require_tables();
	}

	public function db_get_option( $option_name, $default_value = '' ) {

		$option_data = $this->get_row( self::$_table_option, array( 'option_name' => $option_name ) );

		return isset( $option_data['option_value'] ) ? $option_data['option_value'] : $default_value;
	}

	public function db_update_option( $option_name, $option_value = '' ) {

		$option_data = $this->get_row( self::$_table_option, array( 'option_name' => $option_name ) );

		if ( empty( $option_data ) || ! $option_data ) {
			return $this->insert( self::$_table_option, array(
				'option_name'  => "'{$option_name}'",
				'option_value' => "'{$option_value}'",
			) );
		}

		return $this->update( self::$_table_option, array( 'option_value' => $option_value ), array( 'option_name' => $option_name ) );
	}

	public function insert( $table_name, $data = array() ) {

		$column_names  = implode( ',', array_keys( $data ) );
		$column_values = implode( ',', array_values( $data ) );

		$insert_res = $this->query( "INSERT INTO {$table_name} ({$column_names}) VALUES ({$column_values})" );

		if ( $insert_res ) {
			return true;
		}

		return false;
	}

	public function update( $table_name, $data = array(), $where_array = array() ) {

		$set_arr = array();

		foreach ( $data as $key => $val ) {
			$set_arr[] = "`$key` = '$val'";
		}

		$set_str   = implode( ',', $set_arr );
		$query_res = $this->query( "UPDATE {$table_name} SET {$set_str} WHERE {$this->build_where_clauses($where_array)}" );

		if ( $query_res ) {
			return true;
		}

		return false;
	}

	public function get_row( $table_name, $where_array = array() ) {

		$fetch_row_res = $this->query( "SELECT * FROM {$table_name} WHERE {$this->build_where_clauses($where_array)} LIMIT 1" );

		$this->fetch_rows( $fetch_row_res, $result );

		if ( isset( $result[0] ) ) {
			return $result[0];
		}

		return array();
	}

	public function get_rows( $table_name, $where_array = array() ) {
		/**
		 * @todo implement latter
		 */
		$results   = [];
		$query_res = $this->query( "SELECT * FROM {$table_name} WHERE {$this->build_where_clauses($where_array)}" );

		if ( $query_res instanceof mysqli_result ) {
			$this->fetch_rows( $query_res, $results );
		}

		return $results;
	}

	public function fetch_rows( mysqli_result $mysqli_result, &$rows ) {
		while ( $row = $mysqli_result->fetch_assoc() ) {
			$rows[] = $row;
		}
	}

	public function query_count( $table_name, $where_array = array() ) {
		$query_count_res = $this->query( "SELECT count(*) as count FROM {$table_name} WHERE {$this->build_where_clauses($where_array)}" );

		if ( ! $query_count_res ) {
			return 0;
		}

		$query_count_array = $query_count_res->fetch_array();

		return isset( $query_count_array['count'] ) ? $query_count_array['count'] : 0;
	}

	public function query( $str_query = '' ) {

		try {
			$query_result = $this->conn->query( $str_query );
		} catch ( Exception $e ) {
			$this->last_error = $e->getMessage();
		}

		if ( $query_result instanceof mysqli_result ) {
			return $query_result;
		}

		return false;
	}

	public function create_require_tables() {
		$collate = "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

		$this->query( "CREATE TABLE IF NOT EXISTS iwp_files_sent (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            filepath TEXT, 
            filepath_hash CHAR(64) UNIQUE, 
            sent INT DEFAULT 0, 
            size INT,
            sent_filename VARCHAR(255),
            checksum VARCHAR(32)
        ) {$collate};" );

		$this->query( "CREATE TABLE IF NOT EXISTS iwp_db_sent (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            table_name TEXT, 
            table_name_hash CHAR(64) UNIQUE, 
            `offset` INT DEFAULT 0, 
            rows_total INT DEFAULT 0, 
            completed INT DEFAULT 0
        ) {$collate};" );

		$this->query( "CREATE TABLE IF NOT EXISTS iwp_options (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            option_name CHAR(64), 
            option_value CHAR(64)
        ) {$collate};" );
	}

	public function rename_table( $old_name, $new_name ) {
		$this->query( "RENAME TABLE {$old_name} TO {$new_name};" );
	}

	public function copy_table( $old_name, $new_name ) {
		$this->query( "CREATE TABLE {$new_name} AS SELECT * FROM {$old_name};" );
	}

	public function connect_database() {
		$db_username = $this->get_option( 'db_username' );
		$db_password = $this->get_option( 'db_password' );
		$db_name     = $this->get_option( 'db_name' );
		$db_host     = $this->get_option( 'db_host' );
		$host        = $db_host;
		$port        = null;
		$socket      = null;
		$is_ipv6     = false;
		$host_data   = $this->parse_db_host( $db_host );

		if ( $host_data ) {
			list( $host, $port, $socket, $is_ipv6 ) = $host_data;
		}

		if ( $is_ipv6 && extension_loaded( 'mysqlnd' ) ) {
			$host = "[$host]";
		}

		$attempt = 0;

		while ( $attempt < $this->max_retries ) {
			$mysqli = new mysqli( $host, $db_username, $db_password, $db_name, $port, $socket );

			if ( ! $mysqli->connect_error ) {
				mysqli_set_charset( $mysqli, "utf8" );
				$this->conn = $mysqli;

				return;
			}

			$this->last_error = $mysqli->connect_error;

			$attempt ++;
			if ( $attempt < $this->max_retries ) {
				$retry_delay = iwp_backoff_timer( $attempt );
				sleep( $retry_delay );
			}
		}

		error_log( "Failed to connect to database after {$this->max_retries} attempts. Last error: {$this->last_error}" );
	}

	public function set_options_data() {

		$options_data_filename = INSTAWP_BACKUP_DIR . 'options-' . $this->migrate_key . '.txt';

		if ( ! is_readable( $options_data_filename ) ) {
			return;
		}

		$options_data_encrypted = file_get_contents( $options_data_filename );

		if ( $options_data_encrypted ) {
			$passphrase             = openssl_digest( $this->migrate_key, 'SHA256', true );
			$options_data_decrypted = openssl_decrypt( $options_data_encrypted, 'AES-256-CBC', $passphrase );
			$this->options_data     = json_decode( $options_data_decrypted, true );
		}
	}

	public function get_option( $option_name = '', $default = '' ) {
		return isset( $this->options_data[ $option_name ] ) ? $this->options_data[ $option_name ] : $default;
	}

	public function update_option( $option_name = '', $value = '' ) {

		if ( empty( $option_name || empty( $this->migrate_key ) ) ) {
			return false;
		}

		$this->options_data[ $option_name ] = $value;

		$options_data_str       = json_encode( $this->options_data );
		$passphrase             = openssl_digest( $this->migrate_key, 'SHA256', true );
		$options_data_encrypted = openssl_encrypt( $options_data_str, 'AES-256-CBC', $passphrase );
		$options_data_filename  = INSTAWP_BACKUP_DIR . 'options-' . $this->migrate_key . '.txt';
		$options_data_stored    = file_put_contents( $options_data_filename, $options_data_encrypted );

		if ( ! $options_data_stored ) {
			return false;
		}

		return true;
	}

	private function index_exists( $indexName, $tableName ) {
		$query  = "SHOW INDEX FROM `$tableName` WHERE Key_name = '$indexName'";
		$result = $this->query( $query );

		return $result && $result->num_rows > 0;
	}

	public function create_file_indexes( $table_name, $indexes = array() ) {
		foreach ( $indexes as $indexName => $columnName ) {
			if ( ! $this->index_exists( $indexName, $table_name ) ) {
				$this->query( "CREATE INDEX `$indexName` ON `$table_name`(`$columnName`)" );
			}
		}
	}

	public function get_all_tables() {

		$all_tables      = array();
		$tables          = array();
		$show_tables_res = $this->query( 'SHOW TABLES' );

		if ( $show_tables_res instanceof mysqli_result ) {
			$this->fetch_rows( $show_tables_res, $tables );
		}

		$tables = array_map( function ( $table_name ) {
			if ( is_array( $table_name ) ) {
				$table_name_arr = array_values( $table_name );

				return isset( $table_name_arr[0] ) ? $table_name_arr[0] : '';
			}

			return '';
		}, $tables );

		foreach ( $tables as $table_name ) {

			// remove our tracking tables
			if ( in_array( $table_name, array( 'iwp_db_sent', 'iwp_files_sent', 'iwp_options' ) ) ) {
				continue;
			}

			$row_count_res = $this->query( "SELECT COUNT(*) AS row_count FROM `$table_name`" );
			$row_count_row = $row_count_res->fetch_assoc();
			$row_count     = $row_count_row['row_count'];

			$all_tables[ $table_name ] = $row_count;
		}

		return $all_tables;
	}

	private function build_where_clauses( $where_arr = array() ) {
		$where_str     = '1';
		$where_strings = array();

		foreach ( $where_arr as $key => $value ) {
			$where_strings[] = "`{$key}` = '$value'";
		}

		if ( ! empty( $where_strings ) ) {
			$where_str = implode( ' AND ', $where_strings );
		}

		return $where_str;
	}

	private function parse_db_host( $host ) {
		$socket  = null;
		$is_ipv6 = false;

		$socket_pos = strpos( $host, ':/' );
		if ( false !== $socket_pos ) {
			$socket = substr( $host, $socket_pos + 1 );
			$host   = substr( $host, 0, $socket_pos );
		}

		if ( substr_count( $host, ':' ) > 1 ) {
			$pattern = '#^(?:\[)?(?P<host>[0-9a-fA-F:]+)(?:\]:(?P<port>[\d]+))?#';
			$is_ipv6 = true;
		} else {
			$pattern = '#^(?P<host>[^:/]*)(?::(?P<port>[\d]+))?#';
		}

		$matches = array();
		$result  = preg_match( $pattern, $host, $matches );

		if ( 1 !== $result ) {
			return false;
		}

		$host = ! empty( $matches['host'] ) ? $matches['host'] : '';
		$port = ! empty( $matches['port'] ) ? abs( (int) $matches['port'] ) : null;

		return array( $host, $port, $socket, $is_ipv6 );
	}

	public function __destruct() {
		$this->conn->close();
	}
}
// phpcs:enable