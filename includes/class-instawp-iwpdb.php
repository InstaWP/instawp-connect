<?php

class IWPDB {

	private $connection;

	private $isPDO;

	private $lastError;

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

