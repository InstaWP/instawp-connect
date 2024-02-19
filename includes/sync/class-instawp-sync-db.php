<?php
/**
 *
 * Define the database methods
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_DB {

	private $wpdb;

	public static $tables = array(
		'ch_table' => INSTAWP_DB_TABLE_EVENTS,
		'sh_table' => INSTAWP_DB_TABLE_SYNC_HISTORY,
		'se_table' => INSTAWP_DB_TABLE_EVENT_SITES,
	);

	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
	}

	public static function wpdb() {
		$class = new self();

		return $class->wpdb;
	}

	/**
	 * Insert
	 */
	public static function insert( $table_name, $data ) {
		if ( ! empty( $data ) && is_array( $data ) ) {
			self::wpdb()->insert( $table_name, $data );
		}
	}

	/**
	 * Delete
	 */
	public static function delete( $table_name, $id, $key = 'id' ) {
		self::wpdb()->delete( $table_name, array( $key => $id ) );
	}

	/**
	 * Delete
	 */
	public static function prefix(): string {
		return self::wpdb()->prefix;
	}

	/**
	 * Update
	 */
	public static function update( $table_name = null, $data = null, $id = null, $key = 'id' ) {
		return self::wpdb()->update( $table_name, $data, array( $key => $id ) );
	}

	/**
	 * update/insert event data
	 */
	public static function insert_update_event( $event_name = null, $event_slug = null, $event_type = null, $source_id = null, $title = null, $details = null, $event_id = null ) {
		$details['site_url'] = site_url();

		$data = array(
			'event_name'     => $event_name,
			'event_slug'     => $event_slug,
			'event_hash'     => InstaWP_Tools::get_random_string(),
			'event_type'     => $event_type,
			'source_id'      => $source_id,
			'title'          => $title,
			'details'        => wp_json_encode( $details ),
			'user_id'        => get_current_user_id(),
			'date'           => date( 'Y-m-d H:i:s' ),
			'status'         => 'pending',
			'prod'           => '',
			'synced_message' => '',
		);

		if ( is_numeric( $event_id ) ) {
			self::update( INSTAWP_DB_TABLE_EVENTS, $data, $event_id );
		} else {
			self::insert( INSTAWP_DB_TABLE_EVENTS, $data );
		}
	}

	/**
	 * Select
	 */
	public static function get( $table_name ) {
		return self::wpdb()->get_results( "SELECT * FROM $table_name" );
	}

	/**
	 * Bulk delete
	 */
	public static function bulk_delete( $table_name, $ids = array() ) {
		foreach ( $ids as $id ) {
			self::delete( $table_name, $id );
		}
	}

	/**
	 * Fatch row via id
	 */
	public static function getRowById( $table_name, $id ) {
		return self::wpdb()->get_results( self::wpdb()->prepare( "SELECT * FROM $table_name WHERE `id` = %d", $id ) );
	}

	/*
	* Count total traking events
	*/
	public static function totalEvents( $table_name, $status ) {
		return self::wpdb()->get_var( self::wpdb()->prepare( "SELECT COUNT(*) FROM $table_name WHERE `status`=%s", $status ) );
	}

	/**
	 * Select
	 */
	public static function getAllEvents( $orderBy = 'id asc' ) {
		$table_name = self::$tables['ch_table'];

		return self::wpdb()->get_results( "SELECT * FROM {$table_name} order by {$orderBy}" );
	}

	/*
	* Get site event status row
	*/
	public static function getSiteEventStatus( $connect_id, $event_id ) {
		$table_name = self::$tables['se_table'];

		return self::wpdb()->get_row( "SELECT * FROM {$table_name}  WHERE `connect_id`={$connect_id} AND `event_hash`='{$event_id}'" );
	}

	/*
	* To get unique or distinct values of a column in MySQL Table
	*/
	public static function get_with_distinct( $table_name, $key ) {
		return self::wpdb()->get_results( "SELECT DISTINCT($key) FROM $table_name" );
	}

	public static function getByInCondition( $table_name, $conditions ) {
		$str = array();
		foreach ( $conditions as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = join( ',', $value );
				$str[] = "$key IN ($value)";
			} else {
				$str[] = "$key='" . $value . "'";
			}
		}
		$str = join( ' AND ', $str );

		return self::wpdb()->get_results( "SELECT * FROM $table_name WHERE {$str}" );
	}

	public static function get_count( $table_name, $conditions = array() ) {
		$str = array();
		$sql = "SELECT COUNT(*) FROM $table_name";

		foreach ( $conditions as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = join( ',', $value );
				$str[] = "$key IN ($value)";
			} else {
				$str[] = "$key='" . $value . "'";
			}
		}
		if ( ! empty( $str ) ) {
			$str = join( ' AND ', $str );
			$sql .= " WHERE {$str}";
		}

		return self::wpdb()->get_var( $sql );
	}

	public static function existing_update_events( $table_name, $event_slug, $source_id ) {
		return self::wpdb()->get_var( "SELECT id FROM $table_name WHERE `event_slug`='" . $event_slug . "' AND `source_id`='" . $source_id . "'" );
	}

	public static function checkCustomizerChanges( $table_name ) {
		return self::wpdb()->get_results( "SELECT `id` FROM $table_name WHERE `event_slug`='customizer_changes'" );
	}

	public static function getDistinictCol( $table_name, $column ) {
		return self::wpdb()->get_results( "SELECT DISTINCT $column FROM $table_name" );
	}
}
