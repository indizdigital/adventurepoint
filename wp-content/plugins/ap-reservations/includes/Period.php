<?php
namespace AP\Reservations;

use DateTimeImmutable;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A validated rental period. Mode "hour": one date, start time + number of hours.
 * Mode "day": start date to end date (inclusive), opening to closing time.
 */
final class Period {

	public const HOUR = 'hour';
	public const DAY  = 'day';

	private function __construct(
		public readonly string $mode,
		public readonly DateTimeImmutable $start,
		public readonly DateTimeImmutable $end,
		public readonly int $units
	) {}

	/**
	 * @param array{mode?:string,date?:string,start_time?:string,hours?:int|string,end_date?:string} $input
	 */
	public static function from_input( array $input ): self|WP_Error {
		$s    = Settings::all();
		$tz   = wp_timezone();
		$mode = (string) ( $input['mode'] ?? '' );
		$date = (string) ( $input['date'] ?? '' );

		if ( ! in_array( $mode, [ self::HOUR, self::DAY ], true ) ) {
			return new WP_Error( 'ap_mode', __( 'Please choose whether you want to rent by the hour or by the day.', 'ap-reservations' ) );
		}
		$day = self::parse_date( $date );
		if ( ! $day ) {
			return new WP_Error( 'ap_date', __( 'Please choose a valid date.', 'ap-reservations' ) );
		}

		$open  = self::minutes( $s['open_time'] );
		$close = self::minutes( $s['close_time'] );

		if ( self::HOUR === $mode ) {
			$start_time = (string) ( $input['start_time'] ?? '' );
			$hours      = (int) ( $input['hours'] ?? 0 );
			if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $start_time ) ) {
				return new WP_Error( 'ap_time', __( 'Please choose a start time.', 'ap-reservations' ) );
			}
			$start_min = self::minutes( $start_time );
			if ( $start_min < $open || ( $start_min - $open ) % (int) $s['slot_minutes'] !== 0 ) {
				return new WP_Error( 'ap_time', __( 'The chosen start time is not available.', 'ap-reservations' ) );
			}
			if ( $hours < 1 || $start_min + $hours * 60 > $close ) {
				/* translators: %s: closing time */
				return new WP_Error( 'ap_hours', sprintf( __( 'The rental must end by closing time (%s).', 'ap-reservations' ), $s['close_time'] ) );
			}
			$start = $day->setTime( intdiv( $start_min, 60 ), $start_min % 60 );
			$end   = $start->modify( '+' . $hours . ' hours' );
			$units = $hours;
			$last  = $day;
		} else {
			$last = self::parse_date( (string) ( $input['end_date'] ?? '' ) ) ?: $day;
			if ( $last < $day ) {
				return new WP_Error( 'ap_date', __( 'The return date must not be before the pick-up date.', 'ap-reservations' ) );
			}
			$units = (int) $day->diff( $last )->days + 1;
			if ( $units > (int) $s['max_days'] ) {
				/* translators: %d: maximum number of days */
				return new WP_Error( 'ap_days', sprintf( __( 'Online reservations are limited to %d days. Please contact us for longer rentals.', 'ap-reservations' ), $s['max_days'] ) );
			}
			$start = $day->setTime( intdiv( $open, 60 ), $open % 60 );
			$end   = $last->setTime( intdiv( $close, 60 ), $close % 60 );
		}

		$now = new DateTimeImmutable( 'now', $tz );
		if ( $start < $now->modify( '+' . (int) $s['min_lead_hours'] . ' hours' ) ) {
			/* translators: %d: hours */
			return new WP_Error( 'ap_lead', sprintf( _n( 'Reservations must be made at least %d hour in advance.', 'Reservations must be made at least %d hours in advance.', (int) $s['min_lead_hours'], 'ap-reservations' ), $s['min_lead_hours'] ) );
		}
		if ( $start > $now->modify( '+' . (int) $s['max_advance_days'] . ' days' ) ) {
			return new WP_Error( 'ap_advance', __( 'This date is too far in the future for online reservations.', 'ap-reservations' ) );
		}
		if ( ! self::in_season( $day ) || ! self::in_season( $last ) ) {
			return new WP_Error( 'ap_season', __( 'We are closed on the chosen date (outside the season).', 'ap-reservations' ) );
		}

		return new self( $mode, $start, $end, $units );
	}

	public static function from_booking( object $booking ): self {
		$tz = wp_timezone();
		return new self(
			$booking->mode,
			new DateTimeImmutable( $booking->start_at, $tz ),
			new DateTimeImmutable( $booking->end_at, $tz ),
			self::HOUR === $booking->mode
				? (int) round( ( strtotime( $booking->end_at ) - strtotime( $booking->start_at ) ) / 3600 )
				: (int) ( new DateTimeImmutable( substr( $booking->start_at, 0, 10 ) ) )->diff( new DateTimeImmutable( substr( $booking->end_at, 0, 10 ) ) )->days + 1
		);
	}

	public static function in_season( DateTimeImmutable $day ): bool {
		$md    = $day->format( 'm-d' );
		$start = (string) Settings::get( 'season_start' );
		$end   = (string) Settings::get( 'season_end' );
		return $start <= $end ? ( $md >= $start && $md <= $end ) : ( $md >= $start || $md <= $end );
	}

	public function label(): string {
		$date_fmt = get_option( 'date_format' );
		$time_fmt = get_option( 'time_format' );
		if ( self::HOUR === $this->mode ) {
			return sprintf(
				/* translators: 1: date, 2: start time, 3: end time, 4: number of hours */
				_n( '%1$s, %2$s – %3$s (%4$d hour)', '%1$s, %2$s – %3$s (%4$d hours)', $this->units, 'ap-reservations' ),
				wp_date( $date_fmt, $this->start->getTimestamp() ),
				wp_date( $time_fmt, $this->start->getTimestamp() ),
				wp_date( $time_fmt, $this->end->getTimestamp() ),
				$this->units
			);
		}
		return sprintf(
			/* translators: 1: start date, 2: end date, 3: number of days */
			_n( '%1$s – %2$s (%3$d day)', '%1$s – %2$s (%3$d days)', $this->units, 'ap-reservations' ),
			wp_date( $date_fmt, $this->start->getTimestamp() ),
			wp_date( $date_fmt, $this->end->getTimestamp() ),
			$this->units
		);
	}

	public function to_array(): array {
		return [
			'mode'  => $this->mode,
			'start' => $this->start->format( 'Y-m-d H:i' ),
			'end'   => $this->end->format( 'Y-m-d H:i' ),
			'units' => $this->units,
			'label' => $this->label(),
		];
	}

	private static function parse_date( string $date ): ?DateTimeImmutable {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}
		$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		return $d && $d->format( 'Y-m-d' ) === $date ? $d : null;
	}

	private static function minutes( string $hhmm ): int {
		[ $h, $m ] = array_map( 'intval', explode( ':', $hhmm ) );
		return $h * 60 + $m;
	}
}
