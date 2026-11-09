<?php
namespace AP\Reservations\Payments;

defined( 'ABSPATH' ) || exit;

/** Pay at the boathouse. Booking is "pending" until staff confirm it, or "confirmed" right away if auto-confirm is on. */
final class OnSiteGateway implements GatewayInterface {

	public function id(): string {
		return 'onsite';
	}

	public function label(): string {
		return __( 'Pay on site', 'ap-reservations' );
	}

	public function is_online(): bool {
		return false;
	}

	public function start( object $booking ): PaymentResult {
		return PaymentResult::done(
			'',
			__( 'You pay on site when picking up your canoe (cash, card or TWINT).', 'ap-reservations' )
		);
	}

	public function handle_webhook( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		return new \WP_Error( 'ap_no_webhook', 'This gateway has no webhook.', [ 'status' => 404 ] );
	}
}
