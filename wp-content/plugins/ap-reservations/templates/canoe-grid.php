<?php
/**
 * @var array  $canoes
 * @var string $booking_url
 */
use AP\Reservations\Frontend;

defined( 'ABSPATH' ) || exit;
if ( ! $canoes ) {
	echo '<p>' . esc_html__( 'No canoes available yet.', 'ap-reservations' ) . '</p>';
	return;
}
?>
<div class="apr-grid">
	<?php foreach ( $canoes as $canoe ) : ?>
		<article class="apr-card">
			<a class="apr-card__media" href="<?php echo esc_url( $canoe['url'] ); ?>" tabindex="-1" aria-hidden="true">
				<img src="<?php echo esc_url( $canoe['image'] ?: AP_RES_URL . 'assets/img/canoe.svg' ); ?>" alt="" loading="lazy">
			</a>
			<div class="apr-card__body">
				<h3 class="apr-card__title"><a href="<?php echo esc_url( $canoe['url'] ); ?>"><?php echo esc_html( $canoe['title'] ); ?></a></h3>
				<p class="apr-card__meta">
					<?php /* translators: %d: persons */ printf( esc_html( _n( 'Up to %d person', 'Up to %d persons', (int) $canoe['capacity'], 'ap-reservations' ) ), (int) $canoe['capacity'] ); ?>
				</p>
				<?php if ( $canoe['excerpt'] ) : ?>
					<p class="apr-card__text"><?php echo esc_html( $canoe['excerpt'] ); ?></p>
				<?php endif; ?>
				<p class="apr-card__price"><?php echo esc_html( Frontend::price_badges( $canoe ) ); ?></p>
				<a class="apr-btn apr-btn--primary" href="<?php echo esc_url( add_query_arg( 'canoe', $canoe['id'], $booking_url ) ); ?>"><?php esc_html_e( 'Reserve', 'ap-reservations' ); ?></a>
			</div>
		</article>
	<?php endforeach; ?>
</div>
