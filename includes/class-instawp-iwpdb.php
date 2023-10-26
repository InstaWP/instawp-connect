<?php

class IWPDB {

	private $connection;

	private $isPDO;

	private $lastError;

	private $table_option = 'serve_data_options';

	public function __construct( $dbname = '' ) {

		if ( extension_loaded( 'PDO' ) && in_array( 'sqlite', PDO::getAvailableDrivers() ) ) {
			$this->isPDO = true;

			try {
				$this->connection = new PDO( "sqlite:$dbname" );
				$this->connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			} catch ( PDOException $e ) {
				$this->lastError = $e->getMessage();
			}
		} else {
			$this->isPDO      = false;
			$this->connection = new SQLite3( $dbname );

			if ( ! $this->connection ) {
				$this->lastError = $this->connection->lastErrorMsg();
			}
		}

		$this->create_option_table();
	}

	public function get_option( $option_name = '', $default = '' ) {
		$query_response = $this->rawQuery( "SELECT * FROM {$this->table_option} WHERE option_name=:option_name LIMIT 1", array( ':option_name' => $option_name ) );
		$query_value    = $this->fetchRows( $query_response );
		$query_value    = is_array( $query_value ) && isset( $query_value[0] ) ? $query_value[0] : [];

		return isset( $query_value['option_value'] ) ? $query_value['option_value'] : $default;
	}

	public function update_option( $option_name, $option_value ) {

		if ( empty( $this->get_option( $option_name ) ) ) {
			return $this->add_option( $option_name, $option_value );
		}

		return $this->rawQuery( "UPDATE {$this->table_option} SET option_value = ':option_value' WHERE option_name=':option_name'",
			array(
				':option_name'  => $option_name,
				':option_value' => $option_value,
			)
		);
	}

	public function add_option( $option_name, $option_value ) {

		if ( is_array( $option_value ) ) {
			$option_value = serialize( $option_value );
		}

		$ret = $this->rawQuery( "INSERT OR IGNORE INTO {$this->table_option} (option_name, option_value) VALUES (:option_name, :option_value)",
			array(
				':option_name'  => $option_name,
				':option_value' => $option_value,
			)
		);

		if ( ! $ret ) {
			return false;
		}

		return $ret;
	}


	public function create_option_table() {
		return $this->rawQuery( "CREATE TABLE IF NOT EXISTS {$this->table_option} (id INTEGER PRIMARY KEY AUTOINCREMENT, option_name TEXT UNIQUE, option_value TEXT)" );
	}


	public function __destruct() {
		$this->disconnect();
	}

	public function disconnect() {
		if ( $this->isPDO ) {
			$this->connection = null;
		} else {
			$this->connection->close();
		}
	}

	public function rawQuery( $query, $params = array() ) {
		if ( $this->isPDO ) {
			try {
				$stmt = $this->connection->prepare( $query );
				$stmt->execute( $params );

				return $stmt;
			} catch ( PDOException $e ) {
				return $e->getMessage();
			}
		} else {

			$stmt = $this->connection->prepare( $query );

			if ( $stmt ) {
				$result = $stmt->execute();
				if ( ! $result ) {
					return $this->connection->lastErrorMsg();
				}

				return $result;
			} else {
				return $this->connection->lastErrorMsg();
			}

//
//			if ( ! $result = $this->connection->query( $query ) ) {
//				return $this->connection->lastErrorMsg();
//			}

//			return $result;
		}
	}

	public function fetchRows( $result ) {
		if ( $this->isPDO ) {
			return $result->fetchAll( PDO::FETCH_ASSOC );
		} else {
			$rows = array();
			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				$rows[] = $row;
			}

			return $rows;
		}
	}

	public function getLastError() {
		return $this->lastError;
	}

	public function getLastErrorCode() {
		if ( $this->isPDO ) {
			return ( $this->connection ) ? $this->connection->errorCode() : null;
		} else {
			return $this->connection->lastErrorCode();
		}
	}

	public function fetchRow( $result ) {
		if ( $this->isPDO ) {
			return $result->fetch( PDO::FETCH_ASSOC );
		} else {
			return $result->fetchArray( SQLITE3_ASSOC );
		}
	}
}

