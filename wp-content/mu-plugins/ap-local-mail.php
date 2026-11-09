<?php
/**
 * Plugin Name: AdventurePoint local mail
 * Description: Routes all WordPress mail to Mailpit when SMTP_HOST is defined (local Docker setup only).
 */

add_action( 'phpmailer_init', static function ( $phpmailer ) {
	if ( ! defined( 'SMTP_HOST' ) ) {
		return;
	}
	$phpmailer->isSMTP();
	$phpmailer->Host     = SMTP_HOST;
	$phpmailer->Port     = defined( 'SMTP_PORT' ) ? SMTP_PORT : 1025;
	$phpmailer->SMTPAuth = false;
	$phpmailer->SMTPAutoTLS = false;
} );

add_filter( 'wp_mail_from', static fn( $from ) => str_contains( $from, '@localhost' ) ? 'noreply@adventurepoint.local' : $from );
