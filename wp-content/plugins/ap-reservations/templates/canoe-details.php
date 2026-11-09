<?php
/**
 * @var array  $canoe
 * @var string $booking_url
 */
use AP\Reservations\Frontend;

defined( 'ABSPATH' ) || exit;
?>
<div class="apr-details">
	<dl class="apr-details__list">
		<div><dt><?php esc_html_e( 'Persons', 'ap-reservations' ); ?></dt><dd><?php echo (int) $canoe['capacity']; ?></dd></div>
		<div><dt><?php esc_html_e( 'Prices', 'ap-reservations' ); ?></dt><dd><?php echo esc_html( Frontend::price_badges( $canoe ) ); ?></dd></div>
		<?php if ( $canoe['allow_hour'] && $canoe['min_hours'] > 1 ) : ?>
			<div><dt><?php esc_html_e( 'Minimum rental', 'ap-reservations' ); ?></dt><dd><?php /* translators: %d: hours */ printf( esc_html__( '%d hours', 'ap-reservations' ), (int) $canoe['min_hours'] ); ?></dd></div>
		<?php endif; ?>
	</dl>
	<a class="apr-btn apr-btn--primary apr-btn--lg" href="<?php echo esc_url( $booking_url ); ?>"><?php esc_html_e( 'Reserve this canoe', 'ap-reservations' ); ?></a>
</div>
