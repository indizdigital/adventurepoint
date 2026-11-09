<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

final class Money {

	public static function format( float $amount, ?string $currency = null ): string {
		$currency ??= (string) Settings::get( 'currency' );
		return $currency . ' ' . number_format_i18n( $amount, 2 );
	}
}
