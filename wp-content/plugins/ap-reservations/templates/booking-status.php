<?php
/** @var object $booking */
use AP\Reservations\BookingRepository;
use AP\Reservations\Money;
use AP\Reservations\Period;

defined( 'ABSPATH' ) || exit;
$failed = isset( $_GET['ap_payment'] ) && 'success' !== $_GET['ap_payment'] && 'confirmed' !== $booking->status;
?>
<div class="apr apr-status <?php echo $failed ? 'is-error' : 'is-success'; ?>">
	<h2><?php echo $failed ? esc_html__( 'Payment not completed', 'ap-reservations' ) : esc_html__( 'Thank you!', 'ap-reservations' ); ?></h2>
	<p>
		<?php
		/* translators: 1: booking reference, 2: status */
		printf( esc_html__( 'Reservation %1$s – status: %2$s', 'ap-reservations' ), '<strong>' . esc_html( $booking->booking_ref ) . '</strong>', esc_html( BookingRepository::status_label( $booking->status ) ) );
		?>
	</p>
	<p><?php echo esc_html( Period::from_booking( $booking )->label() ); ?> · <?php echo esc_html( Money::format( (float) $booking->total, $booking->currency ) ); ?></p>
	<?php if ( $failed ) : ?>
		<p><?php esc_html_e( 'Your payment was not completed. Please try again or contact us.', 'ap-reservations' ); ?></p>
	<?php endif; ?>
</div>
