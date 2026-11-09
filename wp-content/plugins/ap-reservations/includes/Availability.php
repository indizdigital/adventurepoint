<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

/**
 * Stock-based availability. For every item we look at all bookings overlapping the
 * requested period (plus the turnaround buffer) and compute the *peak* number of
 * units in use at the same time – two bookings 9–12 and 13–16 only block one canoe
 * for a full-day request, not two.
 */
final class Availability {

	/** Statuses that hold stock. pending_payment only holds it for a limited time. */
	public const BLOCKING = [ 'pending', 'confirmed', 'completed' ];

	/**
	 * @param int[] $ids canonical IDs
	 * @return array<int,int> canonical ID => peak units in use
	 */
	public static function peak_usage( string $item_type, array $ids, Period $period, int $exclude_booking = 0 ): array {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return [];
		}

		$buffer   = (int) Settings::get( 'buffer_minutes' );
		$from     = $period->start->modify( '-' . $buffer . ' minutes' )->format( 'Y-m-d H:i:s' );
		$to       = $period->end->modify( '+' . $buffer . ' minutes' )->format( 'Y-m-d H:i:s' );
		$hold     = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->modify( '-' . (int) Settings::get( 'payment_hold_minutes' ) . ' minutes' )->format( 'Y-m-d H:i:s' );
		$bookings = Installer::bookings_table();
		$items    = Installer::items_table();
		$in_ids   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$in_stat  = implode( ',', array_fill( 0, count( self::BLOCKING ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT i.item_id, i.qty, b.start_at, b.end_at
			FROM {$items} i INNER JOIN {$bookings} b ON b.id = i.booking_id
			WHERE i.item_type = %s AND i.item_id IN ({$in_ids})
			  AND b.id <> %d
			  AND b.start_at < %s AND b.end_at > %s
			  AND ( b.status IN ({$in_stat}) OR ( b.status = 'pending_payment' AND b.created_at > %s ) )";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( [ $item_type ], $ids, [ $exclude_booking, $to, $from ], self::BLOCKING, [ $hold ] ) ) );

		$events = [];
		foreach ( $rows as $row ) {
			$events[ (int) $row->item_id ][] = [ strtotime( $row->start_at ) - $buffer * 60, (int) $row->qty ];
			$events[ (int) $row->item_id ][] = [ strtotime( $row->end_at ) + $buffer * 60, -(int) $row->qty ];
		}

		$peak = array_fill_keys( $ids, 0 );
		foreach ( $events as $id => $list ) {
			// Ends sort before starts at the same instant (back-to-back is fine).
			usort( $list, static fn( $a, $b ) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1] );
			$current = 0;
			foreach ( $list as [ , $delta ] ) {
				$current += $delta;
				$peak[ $id ] = max( $peak[ $id ], $current );
			}
		}
		return $peak;
	}

	/**
	 * Units still free for the whole period.
	 *
	 * @param array<int,array> $catalog canonical ID => described item
	 * @return array<int,int|null> null = unlimited
	 */
	public static function free( string $item_type, array $catalog, Period $period, int $exclude_booking = 0 ): array {
		$usage = self::peak_usage( $item_type, array_keys( $catalog ), $period, $exclude_booking );
		$free  = [];
		foreach ( $catalog as $id => $item ) {
			$stock = (int) $item['stock'];
			if ( 'extra' === $item_type && 0 === $stock ) {
				$free[ $id ] = null;
				continue;
			}
			$free[ $id ] = max( 0, $stock - ( $usage[ $id ] ?? 0 ) );
		}
		return $free;
	}
}
