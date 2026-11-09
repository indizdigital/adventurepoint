<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a price quote incl. availability. Always computed on the server – client totals are never trusted.
 */
final class Quote {

	public bool $valid = false;
	/** @var string[] */
	public array $errors = [];
	public ?Period $period = null;
	/** @var array<int,int|null> */
	public array $free_canoes = [];
	/** @var array<int,int|null> */
	public array $free_extras = [];
	/** @var array<int,array> */
	public array $lines = [];
	public float $total = 0.0;
	public int $persons = 0;

	/**
	 * @param array                $period_input see Period::from_input()
	 * @param array<int|string,int> $canoes canonical ID => qty
	 * @param array<int|string,int> $extras canonical ID => qty
	 */
	public static function build( array $period_input, array $canoes, array $extras, string $lang = '', int $exclude_booking = 0 ): self {
		$q      = new self();
		$period = Period::from_input( $period_input );
		if ( is_wp_error( $period ) ) {
			$q->errors[] = $period->get_error_message();
			return $q;
		}
		$q->period = $period;

		$canoe_catalog = PostTypes::catalog( PostTypes::CANOE, $lang );
		$extra_catalog = PostTypes::catalog( PostTypes::EXTRA, $lang );

		$q->free_canoes = Availability::free( 'canoe', $canoe_catalog, $period, $exclude_booking );
		$q->free_extras = Availability::free( 'extra', $extra_catalog, $period, $exclude_booking );

		$canoes = self::clean_qty( $canoes );
		$extras = self::clean_qty( $extras );

		foreach ( $canoes as $id => $qty ) {
			$item = $canoe_catalog[ $id ] ?? null;
			if ( ! $item ) {
				$q->errors[] = __( 'One of the selected canoes is no longer available.', 'ap-reservations' );
				continue;
			}
			if ( Period::HOUR === $period->mode && ! $item['allow_hour'] ) {
				/* translators: %s: canoe name */
				$q->errors[] = sprintf( __( '%s can only be rented by the day.', 'ap-reservations' ), $item['title'] );
				continue;
			}
			if ( Period::DAY === $period->mode && ! $item['allow_day'] ) {
				/* translators: %s: canoe name */
				$q->errors[] = sprintf( __( '%s can only be rented by the hour.', 'ap-reservations' ), $item['title'] );
				continue;
			}
			if ( Period::HOUR === $period->mode && $period->units < $item['min_hours'] ) {
				/* translators: 1: canoe name, 2: hours */
				$q->errors[] = sprintf( __( '%1$s must be rented for at least %2$d hours.', 'ap-reservations' ), $item['title'], $item['min_hours'] );
				continue;
			}
			if ( $qty > ( $q->free_canoes[ $id ] ?? 0 ) ) {
				/* translators: 1: canoe name, 2: number available */
				$q->errors[] = sprintf( __( 'Only %2$d × %1$s available for the chosen period.', 'ap-reservations' ), $item['title'], $q->free_canoes[ $id ] ?? 0 );
				continue;
			}
			$unit = Period::HOUR === $period->mode ? $item['price_hour'] * $period->units : $item['price_day'] * $period->units;
			$q->add_line( 'canoe', $id, $item['title'], $qty, $unit );
			$q->persons += $qty * max( 1, (int) $item['capacity'] );
		}

		foreach ( $extras as $id => $qty ) {
			$item = $extra_catalog[ $id ] ?? null;
			if ( ! $item ) {
				$q->errors[] = __( 'One of the selected extras is no longer available.', 'ap-reservations' );
				continue;
			}
			if ( $item['max_per_booking'] > 0 && $qty > $item['max_per_booking'] ) {
				/* translators: 1: max quantity, 2: extra name */
				$q->errors[] = sprintf( __( 'You can add at most %1$d × %2$s.', 'ap-reservations' ), $item['max_per_booking'], $item['title'] );
				continue;
			}
			$free = array_key_exists( $id, $q->free_extras ) ? $q->free_extras[ $id ] : 0; // null = unlimited
			if ( null !== $free && $qty > $free ) {
				/* translators: 1: extra name, 2: number available */
				$q->errors[] = sprintf( __( 'Only %2$d × %1$s available for the chosen period.', 'ap-reservations' ), $item['title'], $free );
				continue;
			}
			$unit = $item['price_flat'] + ( Period::HOUR === $period->mode ? $item['price_hour'] : $item['price_day'] ) * $period->units;
			$q->add_line( 'extra', $id, $item['title'], $qty, $unit );
		}

		if ( ! $canoes ) {
			$q->errors[] = __( 'Please choose at least one canoe.', 'ap-reservations' );
		}

		$q->valid = ! $q->errors;
		return $q;
	}

	private function add_line( string $type, int $id, string $title, int $qty, float $unit ): void {
		$unit          = round( $unit, 2 );
		$line          = round( $unit * $qty, 2 );
		$this->lines[] = [
			'type'       => $type,
			'id'         => $id,
			'title'      => $title,
			'qty'        => $qty,
			'unit_price' => $unit,
			'line_total' => $line,
		];
		$this->total = round( $this->total + $line, 2 );
	}

	/** @return array<int,int> */
	private static function clean_qty( array $map ): array {
		$out = [];
		foreach ( $map as $id => $qty ) {
			$id  = absint( $id );
			$qty = absint( $qty );
			if ( $id && $qty ) {
				$out[ $id ] = min( $qty, 99 );
			}
		}
		return $out;
	}

	public function to_array(): array {
		return [
			'valid'        => $this->valid,
			'errors'       => array_values( array_unique( $this->errors ) ),
			'period'       => $this->period?->to_array(),
			'availability' => [
				'canoes' => (object) $this->free_canoes,
				'extras' => (object) $this->free_extras,
			],
			'lines'        => $this->lines,
			'persons'      => $this->persons,
			'total'        => $this->total,
			'currency'     => Settings::get( 'currency' ),
			'total_label'  => Money::format( $this->total ),
		];
	}
}
