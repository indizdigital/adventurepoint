<?php
namespace AP\Reservations;

defined( 'ABSPATH' ) || exit;

final class Installer {

	public static function activate(): void {
		self::create_tables();
		PostTypes::register();
		flush_rewrite_rules();
		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
		$role = get_role( 'administrator' );
		$role?->add_cap( 'manage_ap_reservations' );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		if ( get_option( 'ap_res_db_version' ) !== AP_RES_DB_VERSION ) {
			self::create_tables();
		}
	}

	public static function bookings_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ap_bookings';
	}

	public static function items_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ap_booking_items';
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$bookings = self::bookings_table();
		$items    = self::items_table();

		dbDelta( "CREATE TABLE {$bookings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_ref VARCHAR(20) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			mode VARCHAR(10) NOT NULL,
			start_at DATETIME NOT NULL,
			end_at DATETIME NOT NULL,
			persons SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			customer_name VARCHAR(190) NOT NULL,
			customer_email VARCHAR(190) NOT NULL,
			customer_phone VARCHAR(50) NOT NULL DEFAULT '',
			notes TEXT NULL,
			admin_notes TEXT NULL,
			lang VARCHAR(10) NOT NULL DEFAULT '',
			currency CHAR(3) NOT NULL DEFAULT 'CHF',
			total DECIMAL(10,2) NOT NULL DEFAULT 0,
			payment_gateway VARCHAR(40) NOT NULL DEFAULT '',
			payment_ref VARCHAR(190) NOT NULL DEFAULT '',
			payment_status VARCHAR(20) NOT NULL DEFAULT '',
			access_key VARCHAR(64) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY booking_ref (booking_ref),
			KEY status (status),
			KEY period (start_at,end_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$items} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			item_type VARCHAR(10) NOT NULL,
			item_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(190) NOT NULL DEFAULT '',
			qty SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
			line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY item (item_type,item_id)
		) {$charset};" );

		update_option( 'ap_res_db_version', AP_RES_DB_VERSION );
	}
}
