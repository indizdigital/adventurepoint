<?php
namespace AP\Reservations\Payments;

use AP\Reservations\I18n;
use AP\Reservations\Settings;

defined( 'ABSPATH' ) || exit;

final class Registry {

	/** @var array<string,GatewayInterface>|null */
	private static ?array $gateways = null;

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/** @return array<string,GatewayInterface> */
	public static function all(): array {
		if ( null === self::$gateways ) {
			$gateways = apply_filters( 'ap_reservations_gateways', [ 'onsite' => new OnSiteGateway() ] );
			self::$gateways = array_filter( $gateways, static fn( $g ) => $g instanceof GatewayInterface );
		}
		return self::$gateways;
	}

	public static function get( string $id ): ?GatewayInterface {
		return self::all()[ $id ] ?? null;
	}

	public static function active(): GatewayInterface {
		return self::get( (string) Settings::get( 'gateway' ) ) ?? self::all()['onsite'] ?? new OnSiteGateway();
	}

	public static function register_routes(): void {
		register_rest_route( 'ap/v1', '/payment/(?P<gateway>[a-z0-9_-]+)/webhook', [
			'methods'             => 'POST',
			'permission_callback' => '__return_true', // Gateways must verify signatures themselves.
			'callback'            => static function ( \WP_REST_Request $request ) {
				$gateway = self::get( (string) $request['gateway'] );
				return $gateway ? $gateway->handle_webhook( $request ) : new \WP_Error( 'ap_gateway', 'Unknown gateway', [ 'status' => 404 ] );
			},
		] );
	}

	/** Customer return URL after an external payment (booking page with status info). */
	public static function return_url( object $booking, string $result ): string {
		return add_query_arg( [
			'ap_booking' => $booking->booking_ref,
			'ap_key'     => $booking->access_key,
			'ap_payment' => $result,
		], I18n::booking_page_url( $booking->lang ) );
	}
}
