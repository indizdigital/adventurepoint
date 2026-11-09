<?php
namespace AP\Reservations\Admin;

use AP\Reservations\PostTypes;
use AP\Reservations\Settings;

defined( 'ABSPATH' ) || exit;

final class MetaBoxes {

	public static function register_hooks(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add' ] );
		add_action( 'save_post_' . PostTypes::CANOE, [ self::class, 'save' ], 10, 2 );
		add_action( 'save_post_' . PostTypes::EXTRA, [ self::class, 'save' ], 10, 2 );
		add_filter( 'manage_' . PostTypes::CANOE . '_posts_columns', [ self::class, 'columns' ] );
		add_filter( 'manage_' . PostTypes::EXTRA . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . PostTypes::CANOE . '_posts_custom_column', [ self::class, 'column' ], 10, 2 );
		add_action( 'manage_' . PostTypes::EXTRA . '_posts_custom_column', [ self::class, 'column' ], 10, 2 );
	}

	public static function add(): void {
		add_meta_box( 'ap_canoe_rental', __( 'Rental settings', 'ap-reservations' ), [ self::class, 'render_canoe' ], PostTypes::CANOE, 'normal', 'high' );
		add_meta_box( 'ap_extra_rental', __( 'Rental settings', 'ap-reservations' ), [ self::class, 'render_extra' ], PostTypes::EXTRA, 'normal', 'high' );
	}

	public static function render_canoe( \WP_Post $post ): void {
		wp_nonce_field( 'ap_save_meta', 'ap_meta_nonce' );
		$c = Settings::get( 'currency' );
		echo '<table class="form-table">';
		self::number( $post, 'capacity', __( 'Persons per canoe', 'ap-reservations' ), 1 );
		self::number( $post, 'stock', __( 'Number of canoes available (stock)', 'ap-reservations' ), 1 );
		self::checkbox( $post, 'allow_hour', __( 'Rentable per hour', 'ap-reservations' ) );
		self::number( $post, 'price_hour', sprintf( /* translators: %s: currency */ __( 'Price per hour (%s)', 'ap-reservations' ), $c ), 0.05 );
		self::number( $post, 'min_hours', __( 'Minimum hours', 'ap-reservations' ), 1 );
		self::checkbox( $post, 'allow_day', __( 'Rentable per day', 'ap-reservations' ) );
		self::number( $post, 'price_day', sprintf( /* translators: %s: currency */ __( 'Price per day (%s)', 'ap-reservations' ), $c ), 0.05 );
		echo '</table>';
		self::translation_hint();
	}

	public static function render_extra( \WP_Post $post ): void {
		wp_nonce_field( 'ap_save_meta', 'ap_meta_nonce' );
		$c = Settings::get( 'currency' );
		echo '<table class="form-table">';
		self::number( $post, 'price_flat', sprintf( /* translators: %s: currency */ __( 'One-time price per booking (%s)', 'ap-reservations' ), $c ), 0.05 );
		self::number( $post, 'price_hour', sprintf( /* translators: %s: currency */ __( 'Price per hour (%s)', 'ap-reservations' ), $c ), 0.05 );
		self::number( $post, 'price_day', sprintf( /* translators: %s: currency */ __( 'Price per day (%s)', 'ap-reservations' ), $c ), 0.05 );
		self::number( $post, 'stock', __( 'Stock (0 = unlimited)', 'ap-reservations' ), 1 );
		self::number( $post, 'max_per_booking', __( 'Max. quantity per booking', 'ap-reservations' ), 1 );
		self::checkbox( $post, 'per_person', __( 'Suggest one per person (e.g. life vests)', 'ap-reservations' ) );
		echo '</table>';
		echo '<p class="description">' . esc_html__( 'Prices are added up: one-time price + (hours × hourly price) or (days × daily price). Leave at 0 for free extras.', 'ap-reservations' ) . '</p>';
		self::translation_hint();
	}

	private static function translation_hint(): void {
		if ( function_exists( 'pll_get_post' ) ) {
			echo '<p class="description">' . esc_html__( 'Prices and stock are shared between all translations and are synchronized automatically.', 'ap-reservations' ) . '</p>';
		}
	}

	private static function number( \WP_Post $post, string $key, string $label, float $step ): void {
		$value = PostTypes::meta( $post->ID, $key );
		printf(
			'<tr><th><label for="ap_%1$s">%2$s</label></th><td><input type="number" min="0" step="%3$s" id="ap_%1$s" name="ap_meta[%1$s]" value="%4$s" class="small-text"></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( (string) $step ),
			esc_attr( (string) $value )
		);
	}

	private static function checkbox( \WP_Post $post, string $key, string $label ): void {
		printf(
			'<tr><th>%2$s</th><td><input type="hidden" name="ap_meta[%1$s]" value="0"><label><input type="checkbox" name="ap_meta[%1$s]" value="1" %3$s> %2$s</label></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			checked( PostTypes::meta( $post->ID, $key ), true, false )
		);
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['ap_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ap_meta_nonce'] ), 'ap_save_meta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$defs  = PostTypes::CANOE === $post->post_type ? PostTypes::CANOE_META : PostTypes::EXTRA_META;
		$input = isset( $_POST['ap_meta'] ) && is_array( $_POST['ap_meta'] ) ? wp_unslash( $_POST['ap_meta'] ) : [];

		foreach ( $defs as $key => [ $type ] ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$raw   = $input[ $key ];
			$value = match ( $type ) {
				'integer' => max( 0, (int) $raw ),
				'number'  => max( 0, round( (float) str_replace( ',', '.', (string) $raw ), 2 ) ),
				'boolean' => (bool) $raw ? 1 : 0,
				default   => sanitize_text_field( (string) $raw ),
			};
			update_post_meta( $post_id, '_ap_' . $key, $value );
		}
	}

	public static function columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['ap_stock'] = __( 'Stock', 'ap-reservations' );
		$columns['ap_price'] = __( 'Prices', 'ap-reservations' );
		if ( $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	public static function column( string $column, int $post_id ): void {
		$c = Settings::get( 'currency' );
		if ( 'ap_stock' === $column ) {
			$stock = PostTypes::meta( $post_id, 'stock' );
			echo esc_html( PostTypes::EXTRA === get_post_type( $post_id ) && 0 === $stock ? '∞' : (string) $stock );
		}
		if ( 'ap_price' === $column ) {
			$parts = [];
			foreach ( [ 'price_flat' => __( 'one-time', 'ap-reservations' ), 'price_hour' => __( 'hour', 'ap-reservations' ), 'price_day' => __( 'day', 'ap-reservations' ) ] as $key => $label ) {
				$v = PostTypes::meta( $post_id, $key );
				if ( $v > 0 ) {
					$parts[] = sprintf( '%s %s / %s', $c, number_format( $v, 2 ), $label );
				}
			}
			echo esc_html( $parts ? implode( ' · ', $parts ) : __( 'free', 'ap-reservations' ) );
		}
	}
}
