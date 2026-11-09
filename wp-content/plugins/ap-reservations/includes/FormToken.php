<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

/**
 * Anti-bot token for the public booking form.
 *
 * Unlike wp_create_nonce() it does not depend on the logged-in user: REST requests
 * without an X-WP-Nonce are treated as anonymous by WordPress, so a user-bound nonce
 * created on the page for a logged-in visitor would never validate. Same lifetime as
 * WordPress nonces (12–24 h); the form fetches a fresh one if a cached page holds an old token.
 */
final class FormToken {

	private const ACTION = 'ap_booking_form';

	public static function create( int $tick = 0 ): string {
		$tick = $tick ?: (int) wp_nonce_tick( self::ACTION );
		return substr( hash_hmac( 'sha256', $tick . '|' . self::ACTION, wp_salt( 'nonce' ) ), 0, 20 );
	}

	public static function verify( string $token ): bool {
		if ( '' === $token ) {
			return false;
		}
		$tick = (int) wp_nonce_tick( self::ACTION );
		return hash_equals( self::create( $tick ), $token ) || hash_equals( self::create( $tick - 1 ), $token );
	}
}
