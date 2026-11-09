<?php
/**
 * AdventurePoint theme.
 */

defined( 'ABSPATH' ) || exit;

define( 'ADVENTUREPOINT_VERSION', '1.1.2' );

add_action( 'after_setup_theme', static function () {
	load_theme_textdomain( 'adventurepoint', get_template_directory() . '/languages' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'post-thumbnails' );
	add_editor_style( 'style.css' );

	// Classic menu locations: Polylang assigns one menu per language to each location.
	register_nav_menus( [
		'primary' => __( 'Main navigation', 'adventurepoint' ),
		'footer'  => __( 'Footer navigation', 'adventurepoint' ),
	] );
} );

add_action( 'wp_enqueue_scripts', static function () {
	wp_enqueue_style( 'adventurepoint', get_stylesheet_uri(), [], ADVENTUREPOINT_VERSION );
	wp_enqueue_script( 'adventurepoint', get_theme_file_uri( 'assets/js/theme.js' ), [], ADVENTUREPOINT_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
} );

add_action( 'init', static function () {
	register_block_pattern_category( 'adventurepoint', [ 'label' => __( 'AdventurePoint', 'adventurepoint' ) ] );
} );

/**
 * Page in the current language by option name (set by the setup script), e.g. ap_reservations_page_id.
 */
function adventurepoint_page_url( string $option, string $fallback = '/' ): string {
	$id = (int) get_option( $option );
	if ( ! $id ) {
		return home_url( $fallback );
	}
	if ( function_exists( 'pll_get_post' ) ) {
		$id = pll_get_post( $id ) ?: $id;
	}
	return (string) get_permalink( $id );
}

function adventurepoint_home_url(): string {
	return function_exists( 'pll_home_url' ) ? pll_home_url() : home_url( '/' );
}

/** Language switcher (Polylang), rendered as DE | FR pills. */
function adventurepoint_language_switcher(): string {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return '';
	}
	$langs = pll_the_languages( [ 'raw' => 1, 'hide_if_empty' => 0, 'hide_if_no_translation' => 0 ] );
	if ( ! is_array( $langs ) || count( $langs ) < 2 ) {
		return '';
	}
	$out = '<ul class="ap-langs" aria-label="' . esc_attr__( 'Language', 'adventurepoint' ) . '">';
	foreach ( $langs as $lang ) {
		$label = esc_html( strtoupper( $lang['slug'] ) );
		if ( $lang['current_lang'] ) {
			$out .= '<li class="is-current"><span aria-current="true" title="' . esc_attr( $lang['name'] ) . '">' . $label . '</span></li>';
		} else {
			$out .= '<li><a href="' . esc_url( $lang['url'] ) . '" hreflang="' . esc_attr( $lang['slug'] ) . '" lang="' . esc_attr( $lang['slug'] ) . '" title="' . esc_attr( $lang['name'] ) . '">' . $label . '</a></li>';
		}
	}
	return $out . '</ul>';
}

function adventurepoint_menu( string $location, string $class ): string {
	if ( ! has_nav_menu( $location ) ) {
		return '';
	}
	return (string) wp_nav_menu( [
		'theme_location' => $location,
		'container'      => false,
		'menu_class'     => $class,
		'depth'          => 1,
		'echo'           => false,
		'fallback_cb'    => false,
	] );
}

/** Theme favicon (spiral from the logo), unless a Site Icon is set under Settings → General. */
add_action( 'wp_head', static function () {
	if ( has_site_icon() ) {
		return;
	}
	$icon = esc_url( get_theme_file_uri( 'assets/img/icon.png' ) );
	echo '<link rel="icon" type="image/png" sizes="512x512" href="' . $icon . '">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . $icon . '">' . "\n";
} );
