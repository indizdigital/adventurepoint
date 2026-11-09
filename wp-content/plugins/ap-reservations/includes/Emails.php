<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

/**
 * Customer emails are sent in the language the booking was made in; admin emails in the site language.
 */
final class Emails {

	public static function register_hooks(): void {
		add_action( 'ap_reservations_booking_created', [ self::class, 'on_created' ] );
		add_action( 'ap_reservations_status_changed', [ self::class, 'on_status_changed' ], 10, 3 );
	}

	public static function on_created( object $booking ): void {
		if ( 'pending_payment' === $booking->status ) {
			return; // Wait for the payment webhook.
		}
		self::customer( $booking, 'confirmed' === $booking->status ? 'confirmed' : 'received' );
		self::admin( $booking );
	}

	public static function on_status_changed( object $booking, string $old, string $new ): void {
		if ( 'confirmed' === $new ) {
			self::customer( $booking, 'confirmed' );
			if ( 'pending_payment' === $old ) {
				self::admin( $booking );
			}
		} elseif ( 'cancelled' === $new && 'pending_payment' !== $old ) {
			self::customer( $booking, 'cancelled' );
		}
	}

	public static function customer( object $booking, string $type ): bool {
		return (bool) I18n::with_lang( $booking->lang, static function () use ( $booking, $type ) {
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			[ $subject, $intro ] = match ( $type ) {
				/* translators: 1: site name, 2: booking reference */
				'confirmed' => [ sprintf( __( '[%1$s] Your reservation %2$s is confirmed', 'ap-reservations' ), $site, $booking->booking_ref ),
					__( 'Great news – your reservation is confirmed. We look forward to seeing you!', 'ap-reservations' ) ],
				/* translators: 1: site name, 2: booking reference */
				'cancelled' => [ sprintf( __( '[%1$s] Your reservation %2$s was cancelled', 'ap-reservations' ), $site, $booking->booking_ref ),
					__( 'Your reservation has been cancelled. If you have any questions, just reply to this email.', 'ap-reservations' ) ],
				/* translators: 1: site name, 2: booking reference */
				default     => [ sprintf( __( '[%1$s] We received your reservation %2$s', 'ap-reservations' ), $site, $booking->booking_ref ),
					__( 'Thank you for your reservation request. We will check it and confirm it shortly by email.', 'ap-reservations' ) ],
			};
			/* translators: %s: customer name */
			$body = sprintf( __( 'Hello %s', 'ap-reservations' ), $booking->customer_name ) . "\n\n" . $intro . "\n\n" . self::summary( $booking );
			if ( 'cancelled' !== $type ) {
				$body .= "\n" . __( 'Please arrive 15 minutes before your rental starts. Life vests are mandatory on the water.', 'ap-reservations' ) . "\n";
			}
			$body .= "\n— " . $site . "\n" . ( function_exists( 'pll_home_url' ) ? pll_home_url( $booking->lang ) : home_url( '/' ) );

			$headers = [ 'Reply-To: ' . Settings::get( 'admin_email' ) ];
			return wp_mail( $booking->customer_email, $subject, $body, $headers );
		} );
	}

	/** Staff notification – always in the site's default language. */
	public static function admin( object $booking ): bool {
		return (bool) I18n::with_lang( I18n::default_lang(), static fn() => self::send_admin( $booking ) );
	}

	private static function send_admin( object $booking ): bool {
		/* translators: 1: booking reference, 2: customer name */
		$subject = sprintf( __( 'New reservation %1$s from %2$s', 'ap-reservations' ), $booking->booking_ref, $booking->customer_name );
		$body    = self::summary( $booking ) . "\n";
		$body   .= __( 'Customer', 'ap-reservations' ) . ': ' . $booking->customer_name . ' <' . $booking->customer_email . '>, ' . $booking->customer_phone . "\n";
		$body   .= __( 'Language', 'ap-reservations' ) . ': ' . strtoupper( $booking->lang ) . "\n";
		if ( $booking->notes ) {
			$body .= __( 'Notes', 'ap-reservations' ) . ":\n" . $booking->notes . "\n";
		}
		$body .= "\n" . admin_url( 'admin.php?page=' . Admin\Menu::SLUG . '&booking=' . $booking->id );
		return wp_mail( (string) Settings::get( 'admin_email' ), $subject, $body, [ 'Reply-To: ' . $booking->customer_email ] );
	}

	public static function summary( object $booking ): string {
		$lines   = [];
		$lines[] = __( 'Reservation', 'ap-reservations' ) . ': ' . $booking->booking_ref;
		$lines[] = __( 'Status', 'ap-reservations' ) . ': ' . BookingRepository::status_label( $booking->status );
		$lines[] = __( 'When', 'ap-reservations' ) . ': ' . Period::from_booking( $booking )->label();
		$lines[] = '';
		foreach ( BookingRepository::items( (int) $booking->id ) as $item ) {
			$title   = get_the_title( I18n::translated_id( (int) $item->item_id, $booking->lang ) ) ?: $item->label;
			$lines[] = sprintf( '%d × %s — %s', $item->qty, html_entity_decode( $title, ENT_QUOTES ), Money::format( (float) $item->line_total, $booking->currency ) );
		}
		$lines[] = '';
		$lines[] = __( 'Total', 'ap-reservations' ) . ': ' . Money::format( (float) $booking->total, $booking->currency );
		$gateway = Payments\Registry::get( $booking->payment_gateway );
		if ( $gateway ) {
			$lines[] = __( 'Payment', 'ap-reservations' ) . ': ' . $gateway->label();
		}
		return implode( "\n", $lines ) . "\n";
	}
}
