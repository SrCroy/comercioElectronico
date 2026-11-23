<?php
/**
 * Envío de correos (pedidos, contraseñas, avisos).
 *
 * Por orden de prioridad:
 * 1. RESEND_API_KEY definida  → se envía por la API HTTPS de Resend (sirve en Railway, que bloquea SMTP).
 * 2. SMTP definido            → se envía por SMTP (Mailpit en tu PC, o un SMTP real en un VPS).
 * 3. Nada definido            → WordPress usa su envío normal.
 *
 * Cada ajuste se lee de una constante MB_<NOMBRE> (wp-config) o de la variable de entorno <NOMBRE>.
 */

defined( 'ABSPATH' ) || exit;

function mb_correo_cfg( string $nombre ): string {
	$constante = 'MB_' . $nombre;
	if ( defined( $constante ) && '' !== (string) constant( $constante ) ) {
		return (string) constant( $constante );
	}
	$valor = getenv( $nombre );
	return false === $valor ? '' : (string) $valor;
}

/** Remitente configurado (debe ser de un dominio verificado en el proveedor de correo). */
function mb_correo_remitente(): string {
	return mb_correo_cfg( 'MAIL_FROM' ) ?: mb_correo_cfg( 'SMTP_FROM' );
}

add_filter( 'wp_mail_from', static function ( string $from ): string {
	return mb_correo_remitente() ?: $from;
}, 99 );

/* ---------- 1. Resend (API HTTPS) ---------- */

add_filter( 'pre_wp_mail', static function ( $resultado, array $atts ) {
	$clave = mb_correo_cfg( 'RESEND_API_KEY' );
	if ( null !== $resultado || '' === $clave ) {
		return $resultado;
	}

	$lista = static function ( $valor ): array {
		$items = is_array( $valor ) ? $valor : explode( ',', (string) $valor );
		return array_values( array_filter( array_map( 'trim', $items ) ) );
	};

	$headers = $atts['headers'] ?? array();
	if ( ! is_array( $headers ) ) {
		$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
	}

	$tipo     = (string) apply_filters( 'wp_mail_content_type', 'text/plain' );
	$cc       = array();
	$bcc      = array();
	$reply_to = array();
	$from     = '';
	foreach ( $headers as $linea ) {
		if ( false === strpos( (string) $linea, ':' ) ) {
			continue;
		}
		list( $nombre, $valor ) = array_map( 'trim', explode( ':', (string) $linea, 2 ) );
		switch ( strtolower( $nombre ) ) {
			case 'content-type':
				$tipo = $valor;
				break;
			case 'cc':
				$cc = array_merge( $cc, $lista( $valor ) );
				break;
			case 'bcc':
				$bcc = array_merge( $bcc, $lista( $valor ) );
				break;
			case 'reply-to':
				$reply_to = array_merge( $reply_to, $lista( $valor ) );
				break;
			case 'from':
				$from = $valor;
				break;
		}
	}

	if ( '' === $from || mb_correo_remitente() ) {
		$correo = (string) apply_filters( 'wp_mail_from', 'wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST ) );
		$nombre = (string) apply_filters( 'wp_mail_from_name', get_bloginfo( 'name' ) );
		$from   = sprintf( '%s <%s>', str_replace( array( '<', '>', '"' ), '', $nombre ), $correo );
	}

	$cuerpo = array(
		'from'    => $from,
		'to'      => $lista( $atts['to'] ?? '' ),
		'subject' => (string) ( $atts['subject'] ?? '' ),
	);
	$mensaje = (string) ( $atts['message'] ?? '' );
	if ( false !== stripos( $tipo, 'text/html' ) ) {
		$cuerpo['html'] = $mensaje;
	} else {
		$cuerpo['text'] = $mensaje;
	}
	if ( $cc ) {
		$cuerpo['cc'] = $cc;
	}
	if ( $bcc ) {
		$cuerpo['bcc'] = $bcc;
	}
	if ( $reply_to ) {
		$cuerpo['reply_to'] = $reply_to;
	}

	$adjuntos = $atts['attachments'] ?? array();
	if ( ! is_array( $adjuntos ) ) {
		$adjuntos = array_filter( explode( "\n", str_replace( "\r\n", "\n", (string) $adjuntos ) ) );
	}
	foreach ( $adjuntos as $nombre_archivo => $ruta ) {
		if ( is_readable( $ruta ) ) {
			$cuerpo['attachments'][] = array(
				'filename' => is_string( $nombre_archivo ) ? $nombre_archivo : basename( $ruta ),
				'content'  => base64_encode( (string) file_get_contents( $ruta ) ), // phpcs:ignore
			);
		}
	}

	$respuesta = wp_remote_post( 'https://api.resend.com/emails', array(
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Bearer ' . $clave,
			'Content-Type'  => 'application/json',
		),
		'body'    => wp_json_encode( $cuerpo ),
	) );

	$codigo = is_wp_error( $respuesta ) ? 0 : (int) wp_remote_retrieve_response_code( $respuesta );
	if ( $codigo >= 200 && $codigo < 300 ) {
		do_action( 'wp_mail_succeeded', $atts );
		return true;
	}

	$error = is_wp_error( $respuesta ) ? $respuesta->get_error_message() : wp_remote_retrieve_body( $respuesta );
	error_log( 'Mechanical Bull: Resend no pudo enviar el correo: ' . $error ); // phpcs:ignore
	do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', $error, $atts ) );
	return false;
}, 10, 2 );

/* ---------- 2. SMTP (Mailpit en local o SMTP real) ---------- */

add_action( 'phpmailer_init', static function ( $phpmailer ): void {
	$host = mb_correo_cfg( 'SMTP_HOST' );
	if ( '' === $host ) {
		return;
	}
	$puerto = (int) ( mb_correo_cfg( 'SMTP_PORT' ) ?: 587 );
	$usuario = mb_correo_cfg( 'SMTP_USER' );

	$phpmailer->isSMTP();
	$phpmailer->Host = $host;
	$phpmailer->Port = $puerto;

	if ( '' !== $usuario ) {
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $usuario;
		$phpmailer->Password   = mb_correo_cfg( 'SMTP_PASSWORD' );
		$phpmailer->SMTPSecure = 465 === $puerto ? 'ssl' : 'tls';
	} else {
		// Mailpit (local): sin usuario ni cifrado.
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPAutoTLS = false;
	}
} );
