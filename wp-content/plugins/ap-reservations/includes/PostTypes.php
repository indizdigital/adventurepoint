<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

final class PostTypes {

	public const CANOE = 'ap_canoe';
	public const EXTRA = 'ap_extra';

	/** Meta definitions: key => [type, default]. */
	public const CANOE_META = [
		'capacity'   => [ 'integer', 2 ],
		'stock'      => [ 'integer', 1 ],
		'price_hour' => [ 'number', 0 ],
		'price_day'  => [ 'number', 0 ],
		'min_hours'  => [ 'integer', 1 ],
		'allow_hour' => [ 'boolean', true ],
		'allow_day'  => [ 'boolean', true ],
	];

	public const EXTRA_META = [
		'price_flat'      => [ 'number', 0 ],
		'price_hour'      => [ 'number', 0 ],
		'price_day'       => [ 'number', 0 ],
		'stock'           => [ 'integer', 0 ], // 0 = unlimited
		'max_per_booking' => [ 'integer', 10 ],
		'per_person'      => [ 'boolean', false ], // suggest qty = number of persons
	];

	public static function register_hooks(): void {
		add_action( 'init', [ self::class, 'register' ] );
		add_filter( 'pll_get_post_types', [ self::class, 'polylang_post_types' ], 10, 2 );
		add_filter( 'pll_copy_post_metas', [ self::class, 'polylang_sync_metas' ], 10, 3 );
	}

	public static function register(): void {
		register_post_type( self::CANOE, [
			'labels'       => [
				'name'          => __( 'Canoes', 'ap-reservations' ),
				'singular_name' => __( 'Canoe', 'ap-reservations' ),
				'add_new_item'  => __( 'Add new canoe', 'ap-reservations' ),
				'edit_item'     => __( 'Edit canoe', 'ap-reservations' ),
				'all_items'     => __( 'Canoes', 'ap-reservations' ),
			],
			'public'       => true,
			'has_archive'  => false,
			'show_in_rest' => true,
			'show_in_menu' => Admin\Menu::SLUG,
			'menu_icon'    => 'dashicons-palmtree',
			'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ],
			'rewrite'      => [ 'slug' => 'canoe', 'with_front' => false ],
		] );

		register_post_type( self::EXTRA, [
			'labels'              => [
				'name'          => __( 'Extras', 'ap-reservations' ),
				'singular_name' => __( 'Extra', 'ap-reservations' ),
				'add_new_item'  => __( 'Add new extra', 'ap-reservations' ),
				'edit_item'     => __( 'Edit extra', 'ap-reservations' ),
				'all_items'     => __( 'Extras', 'ap-reservations' ),
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_rest'        => true,
			'show_in_menu'        => Admin\Menu::SLUG,
			'exclude_from_search' => true,
			'supports'            => [ 'title', 'excerpt', 'thumbnail', 'page-attributes' ],
		] );

		foreach ( [ self::CANOE => self::CANOE_META, self::EXTRA => self::EXTRA_META ] as $type => $metas ) {
			foreach ( $metas as $key => [ $meta_type, $default ] ) {
				register_post_meta( $type, '_ap_' . $key, [
					'type'          => $meta_type,
					'single'        => true,
					'default'       => $default,
					'show_in_rest'  => false,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				] );
			}
		}
	}

	public static function polylang_post_types( array $types, bool $is_settings ): array {
		$types[ self::CANOE ] = self::CANOE;
		$types[ self::EXTRA ] = self::EXTRA;
		return $types;
	}

	/** Prices/stock are shared across translations – keep them in sync. */
	public static function polylang_sync_metas( array $metas, bool $sync, int $from ): array {
		$type = get_post_type( $from );
		$defs = match ( $type ) {
			self::CANOE => self::CANOE_META,
			self::EXTRA => self::EXTRA_META,
			default     => [],
		};
		foreach ( array_keys( $defs ) as $key ) {
			$metas[] = '_ap_' . $key;
		}
		return $metas;
	}

	public static function meta( int $post_id, string $key ): mixed {
		$type = get_post_type( $post_id );
		$defs = self::CANOE === $type ? self::CANOE_META : self::EXTRA_META;
		$def  = $defs[ $key ] ?? [ 'string', '' ];
		// Prices/stock live on the canonical post; translations may lag behind.
		$value = get_post_meta( I18n::canonical_id( $post_id ), '_ap_' . $key, true );
		if ( '' === $value || null === $value ) {
			$value = $def[1];
		}
		return match ( $def[0] ) {
			'integer' => (int) $value,
			'number'  => round( (float) $value, 2 ),
			'boolean' => (bool) $value,
			default   => $value,
		};
	}

	/**
	 * Published items of a type in a language, keyed by canonical ID.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public static function catalog( string $type, string $lang = '' ): array {
		$lang  = $lang ?: I18n::current_lang();
		$args  = [
			'post_type'        => $type,
			'post_status'      => 'publish',
			'posts_per_page'   => 100,
			'orderby'          => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
			'suppress_filters' => false,
		];
		if ( I18n::has_polylang() ) {
			$args['lang'] = $lang;
		}
		$items = [];
		foreach ( get_posts( $args ) as $post ) {
			$canonical = I18n::canonical_id( $post->ID );
			$items[ $canonical ] = self::describe( $post->ID ) + [ 'id' => $canonical ];
		}
		return $items;
	}

	/** Public data for a canoe or extra (localized title, shared prices). */
	public static function describe( int $post_id ): array {
		$type = get_post_type( $post_id );
		$defs = self::CANOE === $type ? self::CANOE_META : self::EXTRA_META;
		$data = [
			'id'          => I18n::canonical_id( $post_id ),
			'post_id'     => $post_id,
			'type'        => self::CANOE === $type ? 'canoe' : 'extra',
			'title'       => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES ),
			'excerpt'     => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
			'image'       => get_the_post_thumbnail_url( $post_id, 'medium_large' ) ?: '',
			'url'         => self::CANOE === $type ? get_permalink( $post_id ) : '',
		];
		foreach ( array_keys( $defs ) as $key ) {
			$data[ $key ] = self::meta( $post_id, $key );
		}
		return $data;
	}

	public static function is_bookable( int $canonical_id, string $type ): bool {
		$post = get_post( $canonical_id );
		return $post && 'publish' === $post->post_status && $post->post_type === $type;
	}
}
