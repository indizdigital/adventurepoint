<?php
/**
 * Plugin Name:       AP Reservations
 * Description:       Canoe rental reservations: canoe types rentable per hour or day, stock-based availability, bookable extras, pluggable payment gateways.
 * Version:           1.0.2
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            AdventurePoint
 * Text Domain:       ap-reservations
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AP_RES_VERSION', '1.0.2' );
define( 'AP_RES_DB_VERSION', '1' );
define( 'AP_RES_FILE', __FILE__ );
define( 'AP_RES_DIR', plugin_dir_path( __FILE__ ) );
define( 'AP_RES_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'AP\\Reservations\\';
	if ( ! str_starts_with( $class, $prefix ) ) {
		return;
	}
	$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
	$file     = AP_RES_DIR . 'includes/' . $relative . '.php';
	if ( is_readable( $file ) ) {
		require $file;
	}
} );

register_activation_hook( __FILE__, [ AP\Reservations\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ AP\Reservations\Installer::class, 'deactivate' ] );

add_action( 'plugins_loaded', [ AP\Reservations\Plugin::class, 'boot' ] );
