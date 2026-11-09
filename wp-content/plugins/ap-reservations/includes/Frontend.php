<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	public static function register_hooks(): void {
		add_action( 'init', [ self::class, 'register_shortcodes' ] );
		// Registered on init: block themes render the template (and our shortcodes) before wp_enqueue_scripts.
		add_action( 'init', [ self::class, 'register_assets' ] );
	}

	public static function register_shortcodes(): void {
		add_shortcode( 'ap_reservation', [ self::class, 'booking_form' ] );
		add_shortcode( 'ap_canoes', [ self::class, 'canoe_grid' ] );
		add_shortcode( 'ap_canoe_details', [ self::class, 'canoe_details' ] );
	}

	public static function register_assets(): void {
		wp_register_style( 'ap-reservations', AP_RES_URL . 'assets/css/booking.css', [], AP_RES_VERSION );
		wp_register_script( 'ap-reservations', AP_RES_URL . 'assets/js/booking.js', [], AP_RES_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
	}

	private static function template( string $name, array $vars ): string {
		ob_start();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		include AP_RES_DIR . 'templates/' . $name . '.php';
		return (string) ob_get_clean();
	}

	public static function price_badges( array $item ): string {
		$out = [];
		if ( 'canoe' === $item['type'] ) {
			if ( $item['allow_hour'] && $item['price_hour'] > 0 ) {
				/* translators: %s: price */
				$out[] = sprintf( __( '%s / hour', 'ap-reservations' ), Money::format( $item['price_hour'] ) );
			}
			if ( $item['allow_day'] && $item['price_day'] > 0 ) {
				/* translators: %s: price */
				$out[] = sprintf( __( '%s / day', 'ap-reservations' ), Money::format( $item['price_day'] ) );
			}
		} else {
			if ( $item['price_flat'] > 0 ) {
				/* translators: %s: price */
				$out[] = sprintf( __( '%s once', 'ap-reservations' ), Money::format( $item['price_flat'] ) );
			}
			if ( $item['price_hour'] > 0 ) {
				$out[] = sprintf( __( '%s / hour', 'ap-reservations' ), Money::format( $item['price_hour'] ) );
			}
			if ( $item['price_day'] > 0 ) {
				$out[] = sprintf( __( '%s / day', 'ap-reservations' ), Money::format( $item['price_day'] ) );
			}
			if ( ! $out ) {
				$out[] = __( 'included', 'ap-reservations' );
			}
		}
		return implode( ' · ', $out );
	}

	public static function booking_form( $atts = [] ): string {
		$atts = shortcode_atts( [ 'canoe' => 0 ], $atts, 'ap_reservation' );

		wp_enqueue_style( 'ap-reservations' );

		// Customer returning from an external payment page.
		if ( isset( $_GET['ap_booking'], $_GET['ap_key'] ) ) {
			$booking = BookingRepository::find_by_ref( sanitize_text_field( wp_unslash( $_GET['ap_booking'] ) ) );
			if ( $booking && hash_equals( $booking->access_key, sanitize_text_field( wp_unslash( $_GET['ap_key'] ) ) ) ) {
				return self::template( 'booking-status', [ 'booking' => $booking ] );
			}
		}

		$lang     = I18n::current_lang();
		$canoes   = PostTypes::catalog( PostTypes::CANOE, $lang );
		$extras   = PostTypes::catalog( PostTypes::EXTRA, $lang );
		$settings = Settings::all();

		$preselect = absint( $_GET['canoe'] ?? $atts['canoe'] );
		$preselect = $preselect ? I18n::canonical_id( $preselect ) : 0;

		$tz    = wp_timezone();
		$today = new \DateTimeImmutable( 'now', $tz );
		$terms = $settings['terms_page_id'] ? get_permalink( I18n::translated_id( (int) $settings['terms_page_id'], $lang ) ) : '';

		wp_enqueue_script( 'ap-reservations' );
		wp_localize_script( 'ap-reservations', 'apReservations', [
			'restUrl'   => esc_url_raw( rest_url( Rest\Controller::NS . '/' ) ),
			'nonce'     => wp_create_nonce( 'ap_booking' ),
			'lang'      => $lang,
			'locale'    => str_replace( '_', '-', determine_locale() ),
			'currency'  => $settings['currency'],
			'openTime'  => $settings['open_time'],
			'closeTime' => $settings['close_time'],
			'slot'      => (int) $settings['slot_minutes'],
			'preselect' => $preselect,
			'canoes'    => array_values( array_map( static fn( $c ) => array_intersect_key( $c, array_flip( [ 'id', 'title', 'capacity', 'stock', 'price_hour', 'price_day', 'min_hours', 'allow_hour', 'allow_day' ] ) ), $canoes ) ),
			'extras'    => array_values( array_map( static fn( $e ) => array_intersect_key( $e, array_flip( [ 'id', 'title', 'max_per_booking', 'per_person', 'stock' ] ) ), $extras ) ),
			'i18n'      => [
				/* translators: %d: number of free units */
				'available'     => __( '%d available', 'ap-reservations' ),
				'soldOut'       => __( 'Not available', 'ap-reservations' ),
				'unlimited'     => __( 'available', 'ap-reservations' ),
				/* translators: %d: hours */
				'minHours'      => __( 'min. %d h', 'ap-reservations' ),
				'onlyDay'       => __( 'only per day', 'ap-reservations' ),
				'onlyHour'      => __( 'only per hour', 'ap-reservations' ),
				'emptySummary'  => __( 'Choose a date and a canoe to see your price.', 'ap-reservations' ),
				'chooseCanoe'   => __( 'Please choose at least one canoe.', 'ap-reservations' ),
				'loading'       => __( 'Checking availability…', 'ap-reservations' ),
				'networkError'  => __( 'Connection problem – please try again.', 'ap-reservations' ),
				'submitting'    => __( 'Sending reservation…', 'ap-reservations' ),
				/* translators: %d: number of persons */
				'suggestPerson' => __( 'Add one per person (%d)', 'ap-reservations' ),
				'fillRequired'  => __( 'Please fill in all required fields.', 'ap-reservations' ),
			],
		] );

		return self::template( 'booking-form', [
			'canoes'    => $canoes,
			'extras'    => $extras,
			'settings'  => $settings,
			'preselect' => $preselect,
			'min_date'  => $today->format( 'Y-m-d' ),
			'max_date'  => $today->modify( '+' . (int) $settings['max_advance_days'] . ' days' )->format( 'Y-m-d' ),
			'terms_url' => $terms,
		] );
	}

	public static function canoe_grid( $atts = [] ): string {
		$atts = shortcode_atts( [ 'limit' => 12 ], $atts, 'ap_canoes' );
		wp_enqueue_style( 'ap-reservations' );
		$canoes = array_slice( PostTypes::catalog( PostTypes::CANOE ), 0, max( 1, (int) $atts['limit'] ), true );
		return self::template( 'canoe-grid', [ 'canoes' => $canoes, 'booking_url' => I18n::booking_page_url() ] );
	}

	public static function canoe_details(): string {
		$post_id = get_the_ID();
		if ( ! $post_id || PostTypes::CANOE !== get_post_type( $post_id ) ) {
			return '';
		}
		wp_enqueue_style( 'ap-reservations' );
		return self::template( 'canoe-details', [
			'canoe'       => PostTypes::describe( $post_id ),
			'booking_url' => add_query_arg( 'canoe', I18n::canonical_id( $post_id ), I18n::booking_page_url() ),
		] );
	}
}
