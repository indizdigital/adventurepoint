<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'ap_reservations_settings';

	public static function defaults(): array {
		return [
			'currency'           => 'CHF',
			'open_time'          => '09:00',
			'close_time'         => '18:00',
			'slot_minutes'       => 30,
			'buffer_minutes'     => 30,
			'season_start'       => '04-01',
			'season_end'         => '10-31',
			'min_lead_hours'     => 2,
			'max_days'           => 14,
			'max_advance_days'   => 365,
			'admin_email'        => get_option( 'admin_email' ),
			'gateway'            => 'onsite',
			'terms_page_id'      => 0,
			'payment_hold_minutes' => 30,
			'auto_confirm'       => false,
		];
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, [] );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}

	public static function sanitize( array $input ): array {
		$d   = self::defaults();
		$out = [];

		$out['currency']     = preg_match( '/^[A-Z]{3}$/', strtoupper( $input['currency'] ?? '' ) ) ? strtoupper( $input['currency'] ) : $d['currency'];
		$out['open_time']    = self::time( $input['open_time'] ?? '', $d['open_time'] );
		$out['close_time']   = self::time( $input['close_time'] ?? '', $d['close_time'] );
		if ( $out['close_time'] <= $out['open_time'] ) {
			$out['close_time'] = $d['close_time'];
			$out['open_time']  = $d['open_time'];
		}
		$out['slot_minutes']         = in_array( (int) ( $input['slot_minutes'] ?? 0 ), [ 15, 30, 60 ], true ) ? (int) $input['slot_minutes'] : $d['slot_minutes'];
		$out['buffer_minutes']       = max( 0, min( 240, (int) ( $input['buffer_minutes'] ?? $d['buffer_minutes'] ) ) );
		$out['season_start']         = self::monthday( $input['season_start'] ?? '', $d['season_start'] );
		$out['season_end']           = self::monthday( $input['season_end'] ?? '', $d['season_end'] );
		$out['min_lead_hours']       = max( 0, min( 168, (int) ( $input['min_lead_hours'] ?? $d['min_lead_hours'] ) ) );
		$out['max_days']             = max( 1, min( 60, (int) ( $input['max_days'] ?? $d['max_days'] ) ) );
		$out['max_advance_days']     = max( 1, min( 730, (int) ( $input['max_advance_days'] ?? $d['max_advance_days'] ) ) );
		$out['admin_email']          = is_email( $input['admin_email'] ?? '' ) ? sanitize_email( $input['admin_email'] ) : $d['admin_email'];
		$out['gateway']              = array_key_exists( $input['gateway'] ?? '', Payments\Registry::all() ) ? $input['gateway'] : 'onsite';
		$out['terms_page_id']        = absint( $input['terms_page_id'] ?? 0 );
		$out['payment_hold_minutes'] = max( 5, min( 1440, (int) ( $input['payment_hold_minutes'] ?? $d['payment_hold_minutes'] ) ) );

		$out['auto_confirm']         = ! empty( $input['auto_confirm'] );

		return $out;
	}

	private static function time( string $v, string $fallback ): string {
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v ) ? $v : $fallback;
	}

	private static function monthday( string $v, string $fallback ): string {
		if ( ! preg_match( '/^(\d{2})-(\d{2})$/', $v, $m ) || ! checkdate( (int) $m[1], (int) $m[2], 2024 ) ) {
			return $fallback;
		}
		return $v;
	}
}
