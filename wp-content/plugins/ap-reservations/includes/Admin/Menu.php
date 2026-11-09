<?php
namespace AP\Reservations\Admin;

use AP\Reservations\BookingRepository;
use AP\Reservations\Emails;
use AP\Reservations\I18n;
use AP\Reservations\Money;
use AP\Reservations\Payments\Registry;
use AP\Reservations\Period;
use AP\Reservations\PostTypes;
use AP\Reservations\Settings;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const SLUG = 'ap-reservations';
	public const CAP  = 'manage_ap_reservations';

	public static function register_hooks(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'admin_post_ap_booking_update', [ self::class, 'handle_update' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
	}

	private static function cap(): string {
		return current_user_can( self::CAP ) ? self::CAP : 'manage_options';
	}

	public static function menu(): void {
		$pending = self::pending_count();
		$badge   = $pending ? ' <span class="awaiting-mod">' . (int) $pending . '</span>' : '';

		add_menu_page( __( 'Reservations', 'ap-reservations' ), __( 'Reservations', 'ap-reservations' ) . $badge, self::cap(), self::SLUG, [ self::class, 'page_bookings' ], 'dashicons-calendar-alt', 26 );
		add_submenu_page( self::SLUG, __( 'Reservations', 'ap-reservations' ), __( 'All reservations', 'ap-reservations' ), self::cap(), self::SLUG, [ self::class, 'page_bookings' ] );
		add_submenu_page( self::SLUG, __( 'Occupancy', 'ap-reservations' ), __( 'Occupancy', 'ap-reservations' ), self::cap(), self::SLUG . '-occupancy', [ self::class, 'page_occupancy' ] );
		add_submenu_page( self::SLUG, __( 'Reservation settings', 'ap-reservations' ), __( 'Settings', 'ap-reservations' ), 'manage_options', self::SLUG . '-settings', [ self::class, 'page_settings' ] );
	}

	private static function pending_count(): int {
		global $wpdb;
		$table = \AP\Reservations\Installer::bookings_table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
	}

	public static function assets( string $hook ): void {
		if ( str_contains( $hook, self::SLUG ) ) {
			wp_enqueue_style( 'ap-reservations-admin', AP_RES_URL . 'assets/css/admin.css', [], AP_RES_VERSION );
		}
	}

	/* ------------------------------------------------------------------ bookings */

	public static function page_bookings(): void {
		if ( isset( $_GET['booking'] ) ) {
			self::page_booking( absint( $_GET['booking'] ) );
			return;
		}
		$table = new BookingsTable();
		$table->prepare_items();
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Reservations', 'ap-reservations' ) . '</h1>';
		self::notice();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		$table->views();
		$table->search_box( __( 'Search', 'ap-reservations' ), 'ap-search' );
		$table->display();
		echo '</form></div>';
	}

	private static function notice(): void {
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Reservation updated.', 'ap-reservations' ) . '</p></div>';
		}
	}

	private static function page_booking( int $id ): void {
		$booking = BookingRepository::find( $id );
		if ( ! $booking ) {
			wp_die( esc_html__( 'Reservation not found.', 'ap-reservations' ) );
		}
		$items   = BookingRepository::items( $id );
		$period  = Period::from_booking( $booking );
		$gateway = Registry::get( $booking->payment_gateway );
		$back    = admin_url( 'admin.php?page=' . self::SLUG );
		?>
		<div class="wrap ap-admin">
			<h1><?php /* translators: %s: booking reference */ printf( esc_html__( 'Reservation %s', 'ap-reservations' ), esc_html( $booking->booking_ref ) ); ?>
				<span class="ap-status ap-status--<?php echo esc_attr( $booking->status ); ?>"><?php echo esc_html( BookingRepository::status_label( $booking->status ) ); ?></span>
			</h1>
			<p><a href="<?php echo esc_url( $back ); ?>">← <?php esc_html_e( 'Back to all reservations', 'ap-reservations' ); ?></a></p>
			<?php self::notice(); ?>

			<div class="ap-cols">
				<div class="ap-card">
					<h2><?php esc_html_e( 'Rental', 'ap-reservations' ); ?></h2>
					<p class="ap-big"><?php echo esc_html( $period->label() ); ?></p>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Item', 'ap-reservations' ); ?></th><th><?php esc_html_e( 'Qty', 'ap-reservations' ); ?></th><th><?php esc_html_e( 'Unit price', 'ap-reservations' ); ?></th><th><?php esc_html_e( 'Total', 'ap-reservations' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td><?php echo esc_html( ( 'canoe' === $item->item_type ? '🛶 ' : '' ) . ( get_the_title( (int) $item->item_id ) ?: $item->label ) ); ?></td>
								<td><?php echo (int) $item->qty; ?></td>
								<td><?php echo esc_html( Money::format( (float) $item->unit_price, $booking->currency ) ); ?></td>
								<td><?php echo esc_html( Money::format( (float) $item->line_total, $booking->currency ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
						<tfoot><tr><th colspan="3"><?php esc_html_e( 'Total', 'ap-reservations' ); ?></th><th><?php echo esc_html( Money::format( (float) $booking->total, $booking->currency ) ); ?></th></tr></tfoot>
					</table>
					<p>
						<?php esc_html_e( 'Persons (max.)', 'ap-reservations' ); ?>: <strong><?php echo (int) $booking->persons; ?></strong> ·
						<?php esc_html_e( 'Payment', 'ap-reservations' ); ?>: <strong><?php echo esc_html( $gateway ? $gateway->label() : $booking->payment_gateway ); ?></strong>
						<?php if ( $booking->payment_ref ) : ?>(<?php echo esc_html( $booking->payment_ref ); ?>)<?php endif; ?>
						<?php if ( $booking->payment_status ) : ?>– <?php echo esc_html( $booking->payment_status ); ?><?php endif; ?>
					</p>
				</div>

				<div class="ap-card">
					<h2><?php esc_html_e( 'Customer', 'ap-reservations' ); ?></h2>
					<p><strong><?php echo esc_html( $booking->customer_name ); ?></strong><br>
						<a href="mailto:<?php echo esc_attr( $booking->customer_email ); ?>"><?php echo esc_html( $booking->customer_email ); ?></a><br>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $booking->customer_phone ) ); ?>"><?php echo esc_html( $booking->customer_phone ); ?></a><br>
						<?php esc_html_e( 'Language', 'ap-reservations' ); ?>: <?php echo esc_html( strtoupper( $booking->lang ) ); ?></p>
					<?php if ( $booking->notes ) : ?>
						<h3><?php esc_html_e( 'Customer notes', 'ap-reservations' ); ?></h3>
						<p><?php echo nl2br( esc_html( $booking->notes ) ); ?></p>
					<?php endif; ?>
					<p class="description"><?php /* translators: %s: date */ printf( esc_html__( 'Created %s', 'ap-reservations' ), esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $booking->created_at ) ) ); ?></p>
				</div>

				<div class="ap-card">
					<h2><?php esc_html_e( 'Manage', 'ap-reservations' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'ap_booking_update_' . $id ); ?>
						<input type="hidden" name="action" value="ap_booking_update">
						<input type="hidden" name="booking" value="<?php echo (int) $id; ?>">
						<p><label for="ap-status"><strong><?php esc_html_e( 'Status', 'ap-reservations' ); ?></strong></label><br>
							<select id="ap-status" name="status">
								<?php foreach ( BookingRepository::statuses() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $booking->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select></p>
						<p class="description"><?php esc_html_e( 'The customer is notified by email when a reservation is confirmed or cancelled.', 'ap-reservations' ); ?></p>
						<p><label for="ap-notes"><strong><?php esc_html_e( 'Internal notes', 'ap-reservations' ); ?></strong></label><br>
							<textarea id="ap-notes" name="admin_notes" rows="4" class="large-text"><?php echo esc_textarea( (string) $booking->admin_notes ); ?></textarea></p>
						<p><label><input type="checkbox" name="resend" value="1"> <?php esc_html_e( 'Resend the current status email to the customer', 'ap-reservations' ); ?></label></p>
						<p class="ap-actions">
							<?php submit_button( __( 'Save', 'ap-reservations' ), 'primary', 'submit', false ); ?>
							<?php if ( 'pending' === $booking->status ) : ?>
								<button class="button" name="quick" value="confirmed"><?php esc_html_e( 'Confirm', 'ap-reservations' ); ?></button>
							<?php endif; ?>
							<?php if ( ! in_array( $booking->status, [ 'cancelled', 'completed' ], true ) ) : ?>
								<button class="button ap-danger" name="quick" value="cancelled" onclick="return confirm('<?php echo esc_js( __( 'Cancel this reservation?', 'ap-reservations' ) ); ?>')"><?php esc_html_e( 'Cancel reservation', 'ap-reservations' ); ?></button>
							<?php endif; ?>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle_update(): void {
		$id = absint( $_POST['booking'] ?? 0 );
		if ( ! current_user_can( self::cap() ) || ! check_admin_referer( 'ap_booking_update_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ap-reservations' ), 403 );
		}
		$booking = BookingRepository::find( $id );
		if ( ! $booking ) {
			wp_die( esc_html__( 'Reservation not found.', 'ap-reservations' ) );
		}
		$status = sanitize_key( wp_unslash( $_POST['quick'] ?? $_POST['status'] ?? $booking->status ) );
		$notes  = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );

		BookingRepository::update( $id, [ 'admin_notes' => $notes ] );
		BookingRepository::set_status( $id, $status );

		if ( ! empty( $_POST['resend'] ) ) {
			$fresh = BookingRepository::find( $id );
			Emails::customer( $fresh, match ( $fresh->status ) { 'confirmed' => 'confirmed', 'cancelled' => 'cancelled', default => 'received' } );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&booking=' . $id . '&updated=1' ) );
		exit;
	}

	/* ------------------------------------------------------------------ occupancy */

	public static function page_occupancy(): void {
		$date = isset( $_GET['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['date'] ) ) )
			? sanitize_text_field( wp_unslash( $_GET['date'] ) )
			: current_time( 'Y-m-d' );
		$s       = Settings::all();
		$canoes  = PostTypes::catalog( PostTypes::CANOE, I18n::default_lang() );
		$rows    = BookingRepository::for_day( $date );
		$open    = (int) substr( $s['open_time'], 0, 2 );
		$close   = (int) ceil( ( (int) substr( $s['close_time'], 0, 2 ) * 60 + (int) substr( $s['close_time'], 3, 2 ) ) / 60 );
		$day_ts  = strtotime( $date . ' 00:00:00' );
		$prev    = gmdate( 'Y-m-d', $day_ts - DAY_IN_SECONDS );
		$next    = gmdate( 'Y-m-d', $day_ts + DAY_IN_SECONDS );
		$url     = admin_url( 'admin.php?page=' . self::SLUG . '-occupancy' );
		?>
		<div class="wrap ap-admin">
			<h1><?php esc_html_e( 'Occupancy', 'ap-reservations' ); ?></h1>
			<form method="get" class="ap-daynav">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG . '-occupancy' ); ?>">
				<a class="button" href="<?php echo esc_url( add_query_arg( 'date', $prev, $url ) ); ?>">←</a>
				<input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" onchange="this.form.submit()">
				<a class="button" href="<?php echo esc_url( add_query_arg( 'date', $next, $url ) ); ?>">→</a>
				<a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Today', 'ap-reservations' ); ?></a>
				<strong><?php echo esc_html( wp_date( 'l, ' . get_option( 'date_format' ), strtotime( $date . ' 12:00:00' ) ) ); ?></strong>
			</form>

			<table class="widefat ap-occupancy">
				<thead><tr><th><?php esc_html_e( 'Canoe', 'ap-reservations' ); ?></th>
					<?php for ( $h = $open; $h < $close; $h++ ) : ?><th><?php echo esc_html( sprintf( '%02d', $h ) ); ?></th><?php endfor; ?>
				</tr></thead>
				<tbody>
				<?php foreach ( $canoes as $id => $canoe ) : ?>
					<tr>
						<th><?php echo esc_html( $canoe['title'] ); ?> <span class="description">(<?php echo (int) $canoe['stock']; ?>)</span></th>
						<?php
						for ( $h = $open; $h < $close; $h++ ) {
							$slot_start = strtotime( sprintf( '%s %02d:00:00', $date, $h ) );
							$slot_end   = $slot_start + HOUR_IN_SECONDS;
							$used       = 0;
							foreach ( $rows as $r ) {
								if ( 'canoe' === $r->item_type && (int) $r->item_id === $id && strtotime( $r->start_at ) < $slot_end && strtotime( $r->end_at ) > $slot_start ) {
									$used += (int) $r->qty;
								}
							}
							$ratio = $canoe['stock'] ? $used / $canoe['stock'] : 1;
							$class = 0 === $used ? 'free' : ( $ratio >= 1 ? 'full' : 'partial' );
							printf( '<td class="ap-slot ap-slot--%s" title="%s">%s</td>', esc_attr( $class ), esc_attr( $used . ' / ' . $canoe['stock'] ), $used ? (int) $used : '' );
						}
						?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Reservations on this day', 'ap-reservations' ); ?></h2>
			<?php
			$by_booking = [];
			foreach ( $rows as $r ) {
				$by_booking[ $r->id ]['row']     = $r;
				$by_booking[ $r->id ]['items'][] = $r->qty . '× ' . ( get_the_title( (int) $r->item_id ) ?: $r->label );
			}
			if ( ! $by_booking ) {
				echo '<p>' . esc_html__( 'No reservations.', 'ap-reservations' ) . '</p>';
			} else {
				echo '<table class="widefat striped"><tbody>';
				foreach ( $by_booking as $bid => $b ) {
					$r = $b['row'];
					printf(
						'<tr><td><a href="%s"><strong>%s</strong></a></td><td>%s – %s</td><td>%s</td><td>%s</td><td><span class="ap-status ap-status--%s">%s</span></td></tr>',
						esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&booking=' . $bid ) ),
						esc_html( $r->booking_ref ),
						esc_html( mysql2date( 'd.m. H:i', $r->start_at ) ),
						esc_html( mysql2date( 'd.m. H:i', $r->end_at ) ),
						esc_html( $r->customer_name ),
						esc_html( implode( ', ', $b['items'] ) ),
						esc_attr( $r->status ),
						esc_html( BookingRepository::status_label( $r->status ) )
					);
				}
				echo '</tbody></table>';
			}
			?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ settings */

	public static function register_settings(): void {
		register_setting( 'ap_reservations', Settings::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ Settings::class, 'sanitize' ],
		] );
	}

	public static function page_settings(): void {
		$s = Settings::all();
		$o = Settings::OPTION;
		$f = static fn( string $key, string $label, string $type = 'text', string $help = '', array $attrs = [] ) => printf(
			'<tr><th><label for="%1$s">%2$s</label></th><td><input type="%3$s" id="%1$s" name="%4$s[%1$s]" value="%5$s" class="regular-text" %6$s>%7$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $o ),
			esc_attr( (string) $s[ $key ] ),
			implode( ' ', array_map( static fn( $k, $v ) => esc_attr( $k ) . '="' . esc_attr( $v ) . '"', array_keys( $attrs ), $attrs ) ),
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reservation settings', 'ap-reservations' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'ap_reservations' ); ?>
				<h2><?php esc_html_e( 'Opening hours & season', 'ap-reservations' ); ?></h2>
				<table class="form-table">
					<?php
					$f( 'open_time', __( 'Opening time', 'ap-reservations' ), 'time' );
					$f( 'close_time', __( 'Closing time', 'ap-reservations' ), 'time' );
					$f( 'season_start', __( 'Season start (MM-DD)', 'ap-reservations' ), 'text', '', [ 'pattern' => '\d{2}-\d{2}' ] );
					$f( 'season_end', __( 'Season end (MM-DD)', 'ap-reservations' ), 'text', '', [ 'pattern' => '\d{2}-\d{2}' ] );
					?>
					<tr><th><label for="slot_minutes"><?php esc_html_e( 'Start time interval', 'ap-reservations' ); ?></label></th><td>
						<select id="slot_minutes" name="<?php echo esc_attr( $o ); ?>[slot_minutes]">
							<?php foreach ( [ 15, 30, 60 ] as $m ) : ?><option value="<?php echo (int) $m; ?>" <?php selected( (int) $s['slot_minutes'], $m ); ?>><?php echo (int) $m; ?> min</option><?php endforeach; ?>
						</select></td></tr>
					<?php
					$f( 'buffer_minutes', __( 'Buffer between rentals (minutes)', 'ap-reservations' ), 'number', __( 'Time to clean and check a canoe before the next rental.', 'ap-reservations' ) );
					$f( 'min_lead_hours', __( 'Minimum notice (hours)', 'ap-reservations' ), 'number' );
					$f( 'max_advance_days', __( 'Bookable up to (days ahead)', 'ap-reservations' ), 'number' );
					$f( 'max_days', __( 'Max. rental length (days)', 'ap-reservations' ), 'number' );
					?>
				</table>

				<h2><?php esc_html_e( 'Payment & notifications', 'ap-reservations' ); ?></h2>
				<table class="form-table">
					<?php $f( 'currency', __( 'Currency (ISO code)', 'ap-reservations' ), 'text', '', [ 'maxlength' => 3 ] ); ?>
					<tr><th><label for="gateway"><?php esc_html_e( 'Payment method', 'ap-reservations' ); ?></label></th><td>
						<select id="gateway" name="<?php echo esc_attr( $o ); ?>[gateway]">
							<?php foreach ( Registry::all() as $gid => $gw ) : ?><option value="<?php echo esc_attr( $gid ); ?>" <?php selected( $s['gateway'], $gid ); ?>><?php echo esc_html( $gw->label() ); ?></option><?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Online payment providers (e.g. Payrexx) appear here once their integration is installed.', 'ap-reservations' ); ?></p></td></tr>
					<?php $f( 'payment_hold_minutes', __( 'Hold canoes while awaiting online payment (minutes)', 'ap-reservations' ), 'number' ); ?>
					<tr><th><?php esc_html_e( 'Auto-confirm', 'ap-reservations' ); ?></th><td><label>
						<input type="checkbox" name="<?php echo esc_attr( $o ); ?>[auto_confirm]" value="1" <?php checked( $s['auto_confirm'] ); ?>>
						<?php esc_html_e( 'Confirm reservations immediately (otherwise staff confirm them manually)', 'ap-reservations' ); ?></label></td></tr>
					<?php $f( 'admin_email', __( 'Notification email', 'ap-reservations' ), 'email' ); ?>
					<tr><th><label for="terms_page_id"><?php esc_html_e( 'Terms page', 'ap-reservations' ); ?></label></th><td>
						<?php wp_dropdown_pages( [ 'name' => $o . '[terms_page_id]', 'id' => 'terms_page_id', 'selected' => (int) $s['terms_page_id'], 'show_option_none' => '—', 'option_none_value' => 0 ] ); ?>
					</td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p class="description"><?php esc_html_e( 'Place the reservation form on any page with the shortcode [ap_reservation]. Canoe overview: [ap_canoes].', 'ap-reservations' ); ?></p>
		</div>
		<?php
	}
}
