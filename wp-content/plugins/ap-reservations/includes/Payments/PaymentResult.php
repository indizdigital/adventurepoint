<?php
namespace AP\Reservations\Payments;

defined( 'ABSPATH' ) || exit;

final class PaymentResult {

	public function __construct(
		public readonly string $status = '',        // new booking status, '' = unchanged
		public readonly string $redirect_url = '',  // where to send the customer (external payment page)
		public readonly string $payment_ref = '',   // gateway transaction / session id
		public readonly string $message = ''
	) {}

	public static function done( string $status = '', string $message = '' ): self {
		return new self( $status, '', '', $message );
	}

	public static function redirect( string $url, string $payment_ref = '' ): self {
		return new self( '', $url, $payment_ref );
	}
}
