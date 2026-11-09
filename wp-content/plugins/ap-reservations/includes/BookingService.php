<?php
namespace AP\Reservations;

use AP\Reservations\Payments\Registry;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class BookingService {

	private const LOCK = 'ap_reservations_booking';

	/**
	 * Validate, re-check availability under a lock, store the booking and start the payment.
	 *
	 * @return array{booking: object, payment: Payments\PaymentResult}|WP_Error
	 */
	public static function create( array $input ): array|WP_Error {
		global $wpdb;

		$lang = I18n::is_valid_lang( (string) ( $input['lang'] ?? '' ) ) ? (string) $input['lang'] : I18n::default_lang();

		$customer = self::validate_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		// Serialize concurrent bookings so two customers can't grab the last canoe at once.
		if ( ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', self::LOCK ) ) ) {
			return new WP_Error( 'ap_busy', __( 'The booking system is busy. Please try again in a moment.', 'ap-reservations' ), [ 'status' => 503 ] );
		}

		try {
			$quote = Quote::build( (array) ( $input['period'] ?? [] ), (array) ( $input['canoes'] ?? [] ), (array) ( $input['extras'] ?? [] ), $lang );
			if ( ! $quote->valid ) {
				return new WP_Error( 'ap_invalid', implode( ' ', array_unique( $quote->errors ) ), [ 'status' => 409, 'quote' => $quote->to_array() ] );
			}

			$gateway = Registry::active();
			$id      = BookingRepository::insert( [
				'booking_ref'     => BookingRepository::generate_ref(),
				'status'          => $gateway->is_online() ? 'pending_payment' : ( Settings::get( 'auto_confirm' ) ? 'confirmed' : 'pending' ),
				'mode'            => $quote->period->mode,
				'start_at'        => $quote->period->start->format( 'Y-m-d H:i:s' ),
				'end_at'          => $quote->period->end->format( 'Y-m-d H:i:s' ),
				'persons'         => $quote->persons,
				'customer_name'   => $customer['name'],
				'customer_email'  => $customer['email'],
				'customer_phone'  => $customer['phone'],
				'notes'           => $customer['notes'],
				'lang'            => $lang,
				'currency'        => (string) Settings::get( 'currency' ),
				'total'           => $quote->total,
				'payment_gateway' => $gateway->id(),
				'access_key'      => wp_generate_password( 32, false ),
			], $quote->lines );
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::LOCK ) );
		}

		if ( ! $id ) {
			return new WP_Error( 'ap_db', __( 'Your reservation could not be saved. Please try again.', 'ap-reservations' ), [ 'status' => 500 ] );
		}

		$booking = BookingRepository::find( $id );
		$payment = $gateway->start( $booking );
		if ( $payment->payment_ref ) {
			BookingRepository::update( $id, [ 'payment_ref' => $payment->payment_ref ] );
		}
		if ( $payment->status && $payment->status !== $booking->status ) {
			BookingRepository::set_status( $id, $payment->status );
		}

		$booking = BookingRepository::find( $id );
		do_action( 'ap_reservations_booking_created', $booking );

		return [ 'booking' => $booking, 'payment' => $payment ];
	}

	private static function validate_customer( array $input ): array|WP_Error {
		$name  = sanitize_text_field( (string) ( $input['customer']['name'] ?? '' ) );
		$email = sanitize_email( (string) ( $input['customer']['email'] ?? '' ) );
		$phone = sanitize_text_field( (string) ( $input['customer']['phone'] ?? '' ) );
		$notes = sanitize_textarea_field( (string) ( $input['customer']['notes'] ?? '' ) );

		$errors = [];
		if ( mb_strlen( $name ) < 2 ) {
			$errors[] = __( 'Please enter your name.', 'ap-reservations' );
		}
		if ( ! is_email( $email ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'ap-reservations' );
		}
		if ( ! preg_match( '/^[0-9 +()\/.-]{6,30}$/', $phone ) ) {
			$errors[] = __( 'Please enter a valid phone number.', 'ap-reservations' );
		}
		if ( empty( $input['terms'] ) ) {
			$errors[] = __( 'Please accept the terms and conditions.', 'ap-reservations' );
		}
		if ( $errors ) {
			return new WP_Error( 'ap_customer', implode( ' ', $errors ), [ 'status' => 422 ] );
		}
		return [
			'name'  => mb_substr( $name, 0, 190 ),
			'email' => $email,
			'phone' => $phone,
			'notes' => mb_substr( $notes, 0, 2000 ),
		];
	}
}
