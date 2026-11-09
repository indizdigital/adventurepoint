<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		load_plugin_textdomain( 'ap-reservations', false, dirname( plugin_basename( AP_RES_FILE ) ) . '/languages' );

		Installer::maybe_upgrade();
		PostTypes::register_hooks();
		Payments\Registry::init();
		Rest\Controller::register_hooks();
		Frontend::register_hooks();
		Emails::register_hooks();

		if ( is_admin() ) {
			Admin\Menu::register_hooks();
			Admin\MetaBoxes::register_hooks();
		}
	}
}
