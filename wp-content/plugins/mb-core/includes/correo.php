<?php
/**
 * Envío de correos (pedidos, contraseñas, códigos de verificación, avisos).
 *
 * Por orden de prioridad:
 * 1. RESEND_API_KEY definida  → API HTTPS de Resend (necesita un dominio propio verificado).
 * 2. BREVO_API_KEY definida   → API HTTPS de Brevo (sirve con un Gmail verificado como remitente, sin dominio).
 * 3. SMTP definido            → SMTP (Mailpit en tu PC, o un SMTP real en un VPS).
 * 4. Nada definido            → WordPress usa su envío normal.
 *
 * Railway bloquea el SMTP, por eso allí se usa Resend o Brevo (por HTTPS).
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

/** Remitente configurado (debe estar verificado en el proveedor de correo). */
function mb_correo_remitente(): string {
	return mb_correo_cfg( 'MAIL_FROM' ) ?: mb_correo_cfg( 'SMTP_FROM' );
}

add_filter( 'wp_mail_from', static function ( string $from ): string {
	return mb_correo_remitente() ?: $from;
}, 99 );

/** Separa "Nombre <correo@x.com>" en array( 'email' => ..., 'name' => ... ). */
function mb_correo_direccion( string $texto ): array {
	$texto = trim( $texto );
	if ( preg_match( '/^(.*)<([^>]+)>$/', $texto, $m ) ) {
		return array( 'email' => trim( $m[2] ), 'name' => trim( $m[1], " \"'" ) );
	}
	return array( 'email' => $texto, 'name' => '' );
}

/**
 * Convierte los argumentos de wp_mail() en un correo listo para una API:
 * from, to, cc, bcc, reply_to (listas de direcciones "Nombre <correo>"), subject, html o text, attachments.
 */
function mb_correo_preparar( array $atts ): array {
	$lista = static function ( $valor ): array {
		$items = is_array( $valor ) ? $valor : explode( ',', (string) $valor );
		return array_values( array_filter( array_map( 'trim', $items ) ) );
	};

	$headers = $atts['headers'] ?? array();
	if ( ! is_array( $headers ) ) {
		$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
	}

	$tipo   = (string) apply_filters( 'wp_mail_content_type', 'text/plain' );
	$correo = array( 'cc' => array(), 'bcc' => array(), 'reply_to' => array() );
	$from   = '';
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
			case 'bcc':
				$correo[ strtolower( $nombre ) ] = array_merge( $correo[ strtolower( $nombre ) ], $lista( $valor ) );
				break;
			case 'reply-to':
				$correo['reply_to'] = array_merge( $correo['reply_to'], $lista( $valor ) );
				break;
			case 'from':
				$from = $valor;
				break;
		}
	}

	if ( '' === $from || mb_correo_remitente() ) {
		$direccion = (string) apply_filters( 'wp_mail_from', 'wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST ) );
		$nombre    = (string) apply_filters( 'wp_mail_from_name', get_bloginfo( 'name' ) );
		$from      = sprintf( '%s <%s>', str_replace( array( '<', '>', '"' ), '', $nombre ), $direccion );
	}

	$correo['from']    = $from;
	$correo['to']      = $lista( $atts['to'] ?? '' );
	$correo['subject'] = (string) ( $atts['subject'] ?? '' );
	$mensaje           = (string) ( $atts['message'] ?? '' );
	if ( false !== stripos( $tipo, 'text/html' ) ) {
		$correo['html'] = $mensaje;
	} else {
		$correo['text'] = $mensaje;
	}

	$correo['attachments'] = array();
	$adjuntos              = $atts['attachments'] ?? array();
	if ( ! is_array( $adjuntos ) ) {
		$adjuntos = array_filter( explode( "\n", str_replace( "\r\n", "\n", (string) $adjuntos ) ) );
	}
	foreach ( $adjuntos as $nombre_archivo => $ruta ) {
		if ( is_readable( $ruta ) ) {
			$correo['attachments'][] = array(
				'filename' => is_string( $nombre_archivo ) ? $nombre_archivo : basename( $ruta ),
				'content'  => base64_encode( (string) file_get_contents( $ruta ) ), // phpcs:ignore
			);
		}
	}
	return $correo;
}

/* ---------- 1. Resend ---------- */

function mb_correo_resend( array $correo, string $clave ) {
	$cuerpo = array(
		'from'    => $correo['from'],
		'to'      => $correo['to'],
		'subject' => $correo['subject'],
	);
	foreach ( array( 'html', 'text', 'cc', 'bcc', 'reply_to', 'attachments' ) as $campo ) {
		if ( ! empty( $correo[ $campo ] ) ) {
			$cuerpo[ $campo ] = $correo[ $campo ];
		}
	}
	return wp_remote_post( 'https://api.resend.com/emails', array(
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Bearer ' . $clave,
			'Content-Type'  => 'application/json',
		),
		'body'    => wp_json_encode( $cuerpo ),
	) );
}

/* ---------- 2. Brevo ---------- */

function mb_correo_brevo( array $correo, string $clave ) {
	// Brevo pide cada dirección como { email, name } (el nombre no puede ir vacío).
	$direccion = static function ( string $texto ): array {
		$d = mb_correo_direccion( $texto );
		return '' === $d['name'] ? array( 'email' => $d['email'] ) : $d;
	};
	$cuerpo = array(
		'sender'  => $direccion( $correo['from'] ),
		'to'      => array_map( $direccion, $correo['to'] ),
		'subject' => $correo['subject'],
	);
	if ( ! empty( $correo['html'] ) ) {
		$cuerpo['htmlContent'] = $correo['html'];
	} else {
		$cuerpo['textContent'] = (string) ( $correo['text'] ?? '' );
	}
	if ( $correo['cc'] ) {
		$cuerpo['cc'] = array_map( $direccion, $correo['cc'] );
	}
	if ( $correo['bcc'] ) {
		$cuerpo['bcc'] = array_map( $direccion, $correo['bcc'] );
	}
	if ( $correo['reply_to'] ) {
		$cuerpo['replyTo'] = $direccion( $correo['reply_to'][0] );
	}
	foreach ( $correo['attachments'] as $adjunto ) {
		$cuerpo['attachment'][] = array( 'name' => $adjunto['filename'], 'content' => $adjunto['content'] );
	}
	return wp_remote_post( 'https://api.brevo.com/v3/smtp/email', array(
		'timeout' => 15,
		'headers' => array(
			'api-key'      => $clave,
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		),
		'body'    => wp_json_encode( $cuerpo ),
	) );
}

/* ---------- Envío por API (Resend o Brevo) en lugar de wp_mail ---------- */

add_filter( 'pre_wp_mail', static function ( $resultado, array $atts ) {
	if ( null !== $resultado ) {
		return $resultado;
	}
	$resend = mb_correo_cfg( 'RESEND_API_KEY' );
	$brevo  = mb_correo_cfg( 'BREVO_API_KEY' );
	if ( '' === $resend && '' === $brevo ) {
		return $resultado; // Sigue con SMTP o el envío normal de WordPress.
	}

	$correo    = mb_correo_preparar( $atts );
	$proveedor = '' !== $resend ? 'Resend' : 'Brevo';
	$respuesta = '' !== $resend ? mb_correo_resend( $correo, $resend ) : mb_correo_brevo( $correo, $brevo );

	$codigo = is_wp_error( $respuesta ) ? 0 : (int) wp_remote_retrieve_response_code( $respuesta );
	if ( $codigo >= 200 && $codigo < 300 ) {
		do_action( 'wp_mail_succeeded', $atts );
		return true;
	}

	$error = is_wp_error( $respuesta ) ? $respuesta->get_error_message() : wp_remote_retrieve_body( $respuesta );
	error_log( "Mechanical Bull: {$proveedor} no pudo enviar el correo: {$error}" ); // phpcs:ignore
	do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', $error, $atts ) );
	return false;
}, 10, 2 );

/* ---------- 3. SMTP (Mailpit en local o SMTP real) ---------- */

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
