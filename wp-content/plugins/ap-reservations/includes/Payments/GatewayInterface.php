<?php
namespace AP\Reservations\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * A payment gateway. To add Payrexx later:
 *  1. Create Payments\PayrexxGateway implementing this interface (is_online() = true).
 *  2. start(): create a Payrexx Gateway via their API (amount = booking total × 100, referenceId = booking_ref,
 *     successRedirectUrl / failedRedirectUrl = Registry::return_url( $booking, 'success'|'failed' )) and return
 *     PaymentResult::redirect( $payrexx_link, $payrexx_gateway_id ).
 *  3. handle_webhook(): verify the request, look up the booking by referenceId and call
 *     BookingRepository::set_status( $id, 'confirmed', [ 'payment_status' => 'paid' ] ) on a confirmed transaction.
 *     Webhook URL to configure in Payrexx: /wp-json/ap/v1/payment/payrexx/webhook
 *  4. Register it: add_filter( 'ap_reservations_gateways', fn( $g ) => $g + [ 'payrexx' => new PayrexxGateway() ] );
 *     and select it under Reservations → Settings.
 */
interface GatewayInterface {

	public function id(): string;

	public function label(): string;

	/** True if the customer is sent to an external payment page (booking starts as pending_payment). */
	public function is_online(): bool;

	/** Called right after the booking was stored. */
	public function start( object $booking ): PaymentResult;

	/** Handles POST /wp-json/ap/v1/payment/{id}/webhook. */
	public function handle_webhook( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error;
}
