<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around Polylang so the plugin keeps working without it.
 * Bookings always reference the *canonical* post ID (default-language translation),
 * which makes DE and FR bookings share the same stock.
 */
final class I18n {

	public static function has_polylang(): bool {
		return function_exists( 'pll_get_post' );
	}

	public static function default_lang(): string {
		return self::has_polylang() ? (string) pll_default_language() : substr( get_locale(), 0, 2 );
	}

	public static function current_lang(): string {
		if ( self::has_polylang() ) {
			$lang = pll_current_language();
			return $lang ? (string) $lang : self::default_lang();
		}
		return substr( determine_locale(), 0, 2 );
	}

	/** @return string[] */
	public static function languages(): array {
		return self::has_polylang() ? (array) pll_languages_list() : [ self::default_lang() ];
	}

	public static function is_valid_lang( string $lang ): bool {
		return in_array( $lang, self::languages(), true );
	}

	public static function canonical_id( int $post_id ): int {
		if ( ! self::has_polylang() ) {
			return $post_id;
		}
		$id = pll_get_post( $post_id, self::default_lang() );
		return $id ? (int) $id : $post_id;
	}

	public static function translated_id( int $post_id, string $lang ): int {
		if ( ! self::has_polylang() ) {
			return $post_id;
		}
		$id = pll_get_post( $post_id, $lang );
		return $id ? (int) $id : $post_id;
	}

	public static function locale_for( string $lang ): string {
		if ( self::has_polylang() && function_exists( 'PLL' ) && PLL()->model ) {
			$language = PLL()->model->get_language( $lang );
			if ( $language ) {
				return $language->locale;
			}
		}
		return [ 'de' => 'de_DE', 'fr' => 'fr_FR', 'en' => 'en_US' ][ $lang ] ?? get_locale();
	}

	/**
	 * Run a callback in another language (emails, REST responses).
	 * switch_to_locale() also reloads all text domains (incl. ours) just in time.
	 */
	public static function with_lang( string $lang, callable $callback ): mixed {
		$switched = $lang && switch_to_locale( self::locale_for( $lang ) );
		try {
			return $callback();
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/** URL of the page holding the [ap_reservation] shortcode, in the given language. */
	public static function booking_page_url( string $lang = '' ): string {
		$page_id = (int) get_option( 'ap_reservations_page_id' );
		if ( ! $page_id ) {
			return home_url( '/' );
		}
		$page_id = self::translated_id( $page_id, $lang ?: self::current_lang() );
		return (string) get_permalink( $page_id );
	}
}
