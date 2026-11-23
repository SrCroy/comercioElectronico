<?php
/**
 * Envío de correos.
 * - Local: todos los correos van a Mailpit (http://localhost:8025) y no llegan a clientes reales.
 * - Producción: salen por el SMTP configurado en .env (SMTP_HOST, SMTP_USER, ...).
 *   Si no hay SMTP configurado, WordPress usa su envío normal.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'phpmailer_init', static function ( $phpmailer ): void {
	if ( ! defined( 'MB_SMTP_HOST' ) || '' === MB_SMTP_HOST ) {
		return;
	}
	$phpmailer->isSMTP();
	$phpmailer->Host = MB_SMTP_HOST;
	$phpmailer->Port = defined( 'MB_SMTP_PORT' ) ? (int) MB_SMTP_PORT : 1025;

	if ( defined( 'MB_SMTP_USER' ) && '' !== MB_SMTP_USER ) {
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = MB_SMTP_USER;
		$phpmailer->Password   = defined( 'MB_SMTP_PASSWORD' ) ? MB_SMTP_PASSWORD : '';
		$phpmailer->SMTPSecure = 465 === $phpmailer->Port ? 'ssl' : 'tls';
	} else {
		// Mailpit (local): sin usuario ni cifrado.
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPAutoTLS = false;
	}
} );

// Remitente de los correos en producción (debe ser de tu dominio, verificado en el proveedor SMTP).
add_filter( 'wp_mail_from', static function ( string $from ): string {
	return ( defined( 'MB_SMTP_FROM' ) && '' !== MB_SMTP_FROM ) ? MB_SMTP_FROM : $from;
} );
