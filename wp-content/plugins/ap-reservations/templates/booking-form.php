<?php
/**
 * Reservation wizard. Rendered server-side in the current language, enhanced by assets/js/booking.js.
 *
 * @var array  $canoes
 * @var array  $extras
 * @var array  $settings
 * @var int    $preselect
 * @var string $min_date
 * @var string $max_date
 * @var string $terms_url
 */
use AP\Reservations\Frontend;

defined( 'ABSPATH' ) || exit;

[ $oh, $om ] = array_map( 'intval', explode( ':', $settings['open_time'] ) );
[ $ch, $cm ] = array_map( 'intval', explode( ':', $settings['close_time'] ) );
$open_min    = $oh * 60 + $om;
$close_min   = $ch * 60 + $cm;
$max_hours   = intdiv( $close_min - $open_min, 60 );
$has_hourly  = (bool) array_filter( $canoes, static fn( $c ) => $c['allow_hour'] );
$has_daily   = (bool) array_filter( $canoes, static fn( $c ) => $c['allow_day'] );
$placeholder = AP_RES_URL . 'assets/img/canoe.svg';
?>
<div class="apr" id="ap-reservation">
	<noscript><p class="apr-alert"><?php esc_html_e( 'Please enable JavaScript to make a reservation.', 'ap-reservations' ); ?></p></noscript>

	<ol class="apr-progress" aria-label="<?php esc_attr_e( 'Reservation steps', 'ap-reservations' ); ?>">
		<li class="is-active" data-progress="1"><span>1</span> <?php esc_html_e( 'Date', 'ap-reservations' ); ?></li>
		<li data-progress="2"><span>2</span> <?php esc_html_e( 'Canoes', 'ap-reservations' ); ?></li>
		<li data-progress="3"><span>3</span> <?php esc_html_e( 'Extras', 'ap-reservations' ); ?></li>
		<li data-progress="4"><span>4</span> <?php esc_html_e( 'Your details', 'ap-reservations' ); ?></li>
	</ol>

	<div class="apr-layout">
		<form class="apr-form" novalidate>
			<!-- Step 1: when -->
			<section class="apr-step is-active" data-step="1" aria-labelledby="apr-h1">
				<h2 id="apr-h1"><?php esc_html_e( 'When do you want to go on the water?', 'ap-reservations' ); ?></h2>

				<fieldset class="apr-modes">
					<legend class="screen-reader-text"><?php esc_html_e( 'Rental type', 'ap-reservations' ); ?></legend>
					<?php if ( $has_hourly ) : ?>
						<label class="apr-mode">
							<input type="radio" name="mode" value="hour" checked>
							<span class="apr-mode__title"><?php esc_html_e( 'By the hour', 'ap-reservations' ); ?></span>
							<span class="apr-mode__hint"><?php esc_html_e( 'Short trips, a few hours on the river', 'ap-reservations' ); ?></span>
						</label>
					<?php endif; ?>
					<?php if ( $has_daily ) : ?>
						<label class="apr-mode">
							<input type="radio" name="mode" value="day" <?php checked( ! $has_hourly ); ?>>
							<span class="apr-mode__title"><?php esc_html_e( 'By the day', 'ap-reservations' ); ?></span>
							<span class="apr-mode__hint"><?php esc_html_e( 'Day tours and multi-day trips', 'ap-reservations' ); ?></span>
						</label>
					<?php endif; ?>
				</fieldset>

				<div class="apr-fields">
					<label class="apr-field">
						<span data-label-hour><?php esc_html_e( 'Date', 'ap-reservations' ); ?></span>
						<span data-label-day hidden><?php esc_html_e( 'Pick-up date', 'ap-reservations' ); ?></span>
						<input type="date" name="date" required min="<?php echo esc_attr( $min_date ); ?>" max="<?php echo esc_attr( $max_date ); ?>">
					</label>

					<label class="apr-field" data-mode="hour">
						<span><?php esc_html_e( 'Start time', 'ap-reservations' ); ?></span>
						<select name="start_time">
							<?php for ( $m = $open_min; $m <= $close_min - 60; $m += (int) $settings['slot_minutes'] ) : $t = sprintf( '%02d:%02d', intdiv( $m, 60 ), $m % 60 ); ?>
								<option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t ); ?></option>
							<?php endfor; ?>
						</select>
					</label>

					<label class="apr-field" data-mode="hour">
						<span><?php esc_html_e( 'Duration', 'ap-reservations' ); ?></span>
						<select name="hours">
							<?php for ( $h = 1; $h <= $max_hours; $h++ ) : ?>
								<option value="<?php echo (int) $h; ?>" <?php selected( 2, $h ); ?>>
									<?php /* translators: %d: hours */ echo esc_html( sprintf( _n( '%d hour', '%d hours', $h, 'ap-reservations' ), $h ) ); ?>
								</option>
							<?php endfor; ?>
						</select>
					</label>

					<label class="apr-field" data-mode="day" hidden>
						<span><?php esc_html_e( 'Return date', 'ap-reservations' ); ?></span>
						<input type="date" name="end_date" min="<?php echo esc_attr( $min_date ); ?>" max="<?php echo esc_attr( $max_date ); ?>">
					</label>
				</div>
				<p class="apr-hint">
					<?php
					/* translators: 1: opening time, 2: closing time */
					printf( esc_html__( 'Opening hours: %1$s – %2$s. Day rentals run from opening to closing time.', 'ap-reservations' ), esc_html( $settings['open_time'] ), esc_html( $settings['close_time'] ) );
					?>
				</p>
			</section>

			<!-- Step 2: canoes -->
			<section class="apr-step" data-step="2" aria-labelledby="apr-h2" hidden>
				<h2 id="apr-h2"><?php esc_html_e( 'Choose your canoes', 'ap-reservations' ); ?></h2>
				<div class="apr-items">
					<?php foreach ( $canoes as $canoe ) : ?>
						<div class="apr-item" data-type="canoe" data-id="<?php echo (int) $canoe['id']; ?>">
							<img class="apr-item__img" src="<?php echo esc_url( $canoe['image'] ?: $placeholder ); ?>" alt="" loading="lazy">
							<div class="apr-item__body">
								<h3><?php echo esc_html( $canoe['title'] ); ?></h3>
								<p class="apr-item__meta">
									<?php /* translators: %d: persons */ printf( esc_html( _n( 'Up to %d person', 'Up to %d persons', (int) $canoe['capacity'], 'ap-reservations' ) ), (int) $canoe['capacity'] ); ?>
									· <?php echo esc_html( Frontend::price_badges( $canoe ) ); ?>
								</p>
								<?php if ( $canoe['excerpt'] ) : ?><p class="apr-item__text"><?php echo esc_html( $canoe['excerpt'] ); ?></p><?php endif; ?>
								<p class="apr-item__avail" aria-live="polite"></p>
							</div>
							<div class="apr-qty">
								<button type="button" class="apr-qty__btn" data-delta="-1" aria-label="<?php esc_attr_e( 'Less', 'ap-reservations' ); ?>">−</button>
								<input type="number" inputmode="numeric" min="0" max="<?php echo (int) $canoe['stock']; ?>" value="<?php echo $preselect === (int) $canoe['id'] ? 1 : 0; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: item */ __( 'Quantity %s', 'ap-reservations' ), $canoe['title'] ) ); ?>">
								<button type="button" class="apr-qty__btn" data-delta="1" aria-label="<?php esc_attr_e( 'More', 'ap-reservations' ); ?>">+</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<!-- Step 3: extras -->
			<section class="apr-step" data-step="3" aria-labelledby="apr-h3" hidden>
				<h2 id="apr-h3"><?php esc_html_e( 'Add equipment', 'ap-reservations' ); ?></h2>
				<p class="apr-hint"><?php esc_html_e( 'Paddles are always included. Add life vests, dry bags and more.', 'ap-reservations' ); ?></p>
				<div class="apr-items apr-items--compact">
					<?php foreach ( $extras as $extra ) : ?>
						<div class="apr-item" data-type="extra" data-id="<?php echo (int) $extra['id']; ?>" <?php echo $extra['per_person'] ? 'data-per-person' : ''; ?>>
							<div class="apr-item__body">
								<h3><?php echo esc_html( $extra['title'] ); ?></h3>
								<p class="apr-item__meta"><?php echo esc_html( Frontend::price_badges( $extra ) ); ?></p>
								<?php if ( $extra['excerpt'] ) : ?><p class="apr-item__text"><?php echo esc_html( $extra['excerpt'] ); ?></p><?php endif; ?>
								<p class="apr-item__avail" aria-live="polite"></p>
								<?php if ( $extra['per_person'] ) : ?><button type="button" class="apr-link" data-suggest hidden></button><?php endif; ?>
							</div>
							<div class="apr-qty">
								<button type="button" class="apr-qty__btn" data-delta="-1" aria-label="<?php esc_attr_e( 'Less', 'ap-reservations' ); ?>">−</button>
								<input type="number" inputmode="numeric" min="0" max="<?php echo (int) ( $extra['max_per_booking'] ?: 99 ); ?>" value="0" aria-label="<?php echo esc_attr( sprintf( __( 'Quantity %s', 'ap-reservations' ), $extra['title'] ) ); ?>">
								<button type="button" class="apr-qty__btn" data-delta="1" aria-label="<?php esc_attr_e( 'More', 'ap-reservations' ); ?>">+</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<!-- Step 4: customer -->
			<section class="apr-step" data-step="4" aria-labelledby="apr-h4" hidden>
				<h2 id="apr-h4"><?php esc_html_e( 'Your details', 'ap-reservations' ); ?></h2>
				<div class="apr-fields apr-fields--2">
					<label class="apr-field"><span><?php esc_html_e( 'Name', 'ap-reservations' ); ?> *</span><input type="text" name="name" autocomplete="name" required></label>
					<label class="apr-field"><span><?php esc_html_e( 'Email', 'ap-reservations' ); ?> *</span><input type="email" name="email" autocomplete="email" required></label>
					<label class="apr-field"><span><?php esc_html_e( 'Phone', 'ap-reservations' ); ?> *</span><input type="tel" name="phone" autocomplete="tel" required></label>
					<label class="apr-field apr-field--full"><span><?php esc_html_e( 'Notes (optional)', 'ap-reservations' ); ?></span><textarea name="notes" rows="3"></textarea></label>
					<label class="apr-hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
				</div>
				<label class="apr-check">
					<input type="checkbox" name="terms" required>
					<span>
						<?php
						if ( $terms_url ) {
							/* translators: %s: link to terms page */
							printf( esc_html__( 'I accept the %s.', 'ap-reservations' ), '<a href="' . esc_url( $terms_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'terms and conditions', 'ap-reservations' ) . '</a>' );
						} else {
							esc_html_e( 'I accept the terms and conditions.', 'ap-reservations' );
						}
						?>
					</span>
				</label>
			</section>

			<div class="apr-alert" role="alert" hidden></div>

			<div class="apr-nav">
				<button type="button" class="apr-btn apr-btn--ghost" data-prev hidden><?php esc_html_e( 'Back', 'ap-reservations' ); ?></button>
				<button type="button" class="apr-btn apr-btn--primary" data-next><?php esc_html_e( 'Continue', 'ap-reservations' ); ?></button>
				<button type="submit" class="apr-btn apr-btn--primary" data-submit hidden><?php esc_html_e( 'Reserve now', 'ap-reservations' ); ?></button>
			</div>
		</form>

		<aside class="apr-summary" aria-live="polite">
			<h2><?php esc_html_e( 'Your reservation', 'ap-reservations' ); ?></h2>
			<p class="apr-summary__period"></p>
			<ul class="apr-summary__lines"></ul>
			<p class="apr-summary__empty"><?php esc_html_e( 'Choose a date and a canoe to see your price.', 'ap-reservations' ); ?></p>
			<p class="apr-summary__total" hidden><span><?php esc_html_e( 'Total', 'ap-reservations' ); ?></span><strong></strong></p>
			<p class="apr-summary__note"><?php echo esc_html( __( 'Payment', 'ap-reservations' ) . ': ' . \AP\Reservations\Payments\Registry::active()->label() ); ?></p>
		</aside>
	</div>

	<div class="apr-success" hidden tabindex="-1">
		<div class="apr-success__icon" aria-hidden="true">✓</div>
		<h2><?php esc_html_e( 'Thank you for your reservation!', 'ap-reservations' ); ?></h2>
		<p class="apr-success__ref"></p>
		<p class="apr-success__details"></p>
		<p class="apr-success__message"></p>
	</div>
</div>
