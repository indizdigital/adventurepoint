<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

final class BookingRepository {

	public static function statuses(): array {
		return [
			'pending'         => __( 'Pending', 'ap-reservations' ),
			'pending_payment' => __( 'Awaiting payment', 'ap-reservations' ),
			'confirmed'       => __( 'Confirmed', 'ap-reservations' ),
			'completed'       => __( 'Completed', 'ap-reservations' ),
			'cancelled'       => __( 'Cancelled', 'ap-reservations' ),
		];
	}

	public static function status_label( string $status ): string {
		return self::statuses()[ $status ] ?? $status;
	}

	public static function insert( array $booking, array $lines ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert( Installer::bookings_table(), $booking + [ 'created_at' => $now, 'updated_at' => $now ] );
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return 0;
		}
		foreach ( $lines as $line ) {
			$wpdb->insert( Installer::items_table(), [
				'booking_id' => $id,
				'item_type'  => $line['type'],
				'item_id'    => $line['id'],
				'label'      => mb_substr( $line['title'], 0, 190 ),
				'qty'        => $line['qty'],
				'unit_price' => $line['unit_price'],
				'line_total' => $line['line_total'],
			] );
		}
		return $id;
	}

	public static function find( int $id ): ?object {
		global $wpdb;
		$table = Installer::bookings_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null;
	}

	public static function find_by_ref( string $ref ): ?object {
		global $wpdb;
		$table = Installer::bookings_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_ref = %s", $ref ) ) ?: null;
	}

	/** @return object[] */
	public static function items( int $booking_id ): array {
		global $wpdb;
		$table = Installer::items_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY item_type = 'extra', id", $booking_id ) );
	}

	public static function update( int $id, array $fields ): bool {
		global $wpdb;
		$fields['updated_at'] = current_time( 'mysql' );
		return false !== $wpdb->update( Installer::bookings_table(), $fields, [ 'id' => $id ] );
	}

	/**
	 * Change the status and fire ap_reservations_status_changed (emails etc. hook in there).
	 */
	public static function set_status( int $id, string $status, array $extra = [] ): bool {
		$booking = self::find( $id );
		if ( ! $booking || ! isset( self::statuses()[ $status ] ) ) {
			return false;
		}
		$old = $booking->status;
		if ( ! self::update( $id, [ 'status' => $status ] + $extra ) ) {
			return false;
		}
		if ( $old !== $status ) {
			do_action( 'ap_reservations_status_changed', self::find( $id ), $old, $status );
		}
		return true;
	}

	/**
	 * @return array{0: object[], 1: int} rows, total count
	 */
	public static function search( array $args ): array {
		global $wpdb;
		$table  = Installer::bookings_table();
		$where  = [ '1=1' ];
		$params = [];

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['date'] ) ) {
			$where[]  = 'start_at < %s AND end_at > %s';
			$params[] = $args['date'] . ' 23:59:59';
			$params[] = $args['date'] . ' 00:00:00';
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(booking_ref LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['upcoming'] ) ) {
			$where[]  = 'end_at >= %s';
			$params[] = current_time( 'mysql' );
		}

		$orderby  = in_array( $args['orderby'] ?? '', [ 'start_at', 'created_at', 'total', 'booking_ref' ], true ) ? $args['orderby'] : 'start_at';
		$order    = 'asc' === strtolower( $args['order'] ?? '' ) ? 'ASC' : 'DESC';
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$offset   = max( 0, ( (int) ( $args['paged'] ?? 1 ) - 1 ) * $per_page );
		$sql_where = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );
		$rows  = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, [ $per_page, $offset ] ) ) );

		return [ $rows, $total ];
	}

	/** Bookings (with items) that overlap a given day – for the occupancy view. */
	public static function for_day( string $date ): array {
		global $wpdb;
		$b     = Installer::bookings_table();
		$i     = Installer::items_table();
		$stat  = implode( "','", array_map( 'esc_sql', array_merge( Availability::BLOCKING, [ 'pending_payment' ] ) ) );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT b.id, b.booking_ref, b.status, b.start_at, b.end_at, b.customer_name, i.item_type, i.item_id, i.qty, i.label
			 FROM {$b} b INNER JOIN {$i} i ON i.booking_id = b.id
			 WHERE b.start_at < %s AND b.end_at > %s AND b.status IN ('{$stat}')
			 ORDER BY b.start_at",
			$date . ' 23:59:59',
			$date . ' 00:00:00'
		) );
	}

	public static function generate_ref(): string {
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$ref = 'AP-';
			for ( $n = 0; $n < 6; $n++ ) {
				$ref .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}
		} while ( self::find_by_ref( $ref ) );
		return $ref;
	}
}
