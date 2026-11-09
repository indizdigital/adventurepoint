<?php
namespace AP\Reservations\Rest;

use AP\Reservations\BookingRepository;
use AP\Reservations\BookingService;
use AP\Reservations\FormToken;
use AP\Reservations\I18n;
use AP\Reservations\Money;
use AP\Reservations\Period;
use AP\Reservations\Quote;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Controller {

	public const NS = 'ap/v1';

	public static function register_hooks(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		register_rest_route( self::NS, '/quote', [
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => [ self::class, 'quote' ],
		] );
		// Fresh form token – used by the form when a (cached) page carried an expired one.
		register_rest_route( self::NS, '/token', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => static function () {
				nocache_headers();
				$response = new WP_REST_Response( [ 'token' => FormToken::create() ] );
				$response->header( 'Cache-Control', 'no-store, max-age=0' );
				return $response;
			},
		] );
		register_rest_route( self::NS, '/bookings', [
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => [ self::class, 'create_booking' ],
		] );
	}

	private static function lang( WP_REST_Request $request ): string {
		$lang = sanitize_key( (string) $request->get_param( 'lang' ) );
		return I18n::is_valid_lang( $lang ) ? $lang : I18n::default_lang();
	}

	/** Answer in the visitor's language (REST requests have no language in the URL). */
	private static function in_lang( WP_REST_Request $request, callable $callback ): mixed {
		$lang = self::lang( $request );
		if ( I18n::has_polylang() && function_exists( 'PLL' ) && PLL()->model ) {
			$language = PLL()->model->get_language( $lang );
			if ( $language ) {
				PLL()->curlang = $language;
			}
		}
		return I18n::with_lang( $lang, $callback );
	}

	public static function quote( WP_REST_Request $request ): WP_REST_Response {
		return self::in_lang( $request, static function () use ( $request ) {
			$quote = Quote::build(
				(array) $request->get_param( 'period' ),
				(array) $request->get_param( 'canoes' ),
				(array) $request->get_param( 'extras' ),
				self::lang( $request )
			);
			return new WP_REST_Response( $quote->to_array() );
		} );
	}

	public static function create_booking( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$nonce = (string) $request->get_header( 'x_ap_nonce' );
		if ( ! FormToken::verify( $nonce ) ) {
			return new WP_Error( 'ap_nonce', __( 'Your session has expired. Please reload the page and try again.', 'ap-reservations' ), [ 'status' => 403 ] );
		}
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) { // honeypot
			return new WP_Error( 'ap_spam', 'Rejected.', [ 'status' => 400 ] );
		}
		if ( self::rate_limited() ) {
			return new WP_Error( 'ap_rate', __( 'Too many reservations from your connection. Please try again later or contact us.', 'ap-reservations' ), [ 'status' => 429 ] );
		}

		return self::in_lang( $request, static function () use ( $request ) {
			$result = BookingService::create( [
				'lang'     => self::lang( $request ),
				'period'   => (array) $request->get_param( 'period' ),
				'canoes'   => (array) $request->get_param( 'canoes' ),
				'extras'   => (array) $request->get_param( 'extras' ),
				'customer' => (array) $request->get_param( 'customer' ),
				'terms'    => (bool) $request->get_param( 'terms' ),
			] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			self::count_booking();
			$booking = $result['booking'];
			$payment = $result['payment'];

			return new WP_REST_Response( [
				'ref'          => $booking->booking_ref,
				'status'       => $booking->status,
				'status_label' => BookingRepository::status_label( $booking->status ),
				'period'       => Period::from_booking( $booking )->label(),
				'total'        => Money::format( (float) $booking->total, $booking->currency ),
				'email'        => $booking->customer_email,
				'message'      => $payment->message,
				'redirect_url' => $payment->redirect_url,
			], 201 );
		} );
	}

	private static function rate_key(): string {
		return 'ap_rl_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
	}

	/** Max. successful bookings per IP and hour (filterable). Failed attempts don't count. */
	private static function rate_limited(): bool {
		return (int) get_transient( self::rate_key() ) >= (int) apply_filters( 'ap_reservations_rate_limit', 10 );
	}

	private static function count_booking(): void {
		set_transient( self::rate_key(), (int) get_transient( self::rate_key() ) + 1, HOUR_IN_SECONDS );
	}
}
