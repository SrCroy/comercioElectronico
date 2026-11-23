<?php
/**
 * Cuentas de clientes: registro con teléfono, verificación del correo y del teléfono con un código
 * de 6 dígitos, y cambio de contraseña desde Mi cuenta.
 *
 * - "¿Olvidaste tu contraseña?" es el flujo de WooCommerce (Mi cuenta → Recuperar contraseña): envía
 *   un enlace por correo para crear una contraseña nueva.
 * - Correo: el código lo genera y lo envía la tienda (wp_mail → Resend / SMTP, ver correo.php).
 * - Teléfono: el código lo envía Twilio Verify por SMS o WhatsApp cuando están definidas
 *   TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN y TWILIO_VERIFY_SID (y opcional TWILIO_CANAL = sms|whatsapp),
 *   como constante MB_<NOMBRE> en wp-config o como variable de entorno. Sin Twilio solo se verifica
 *   el correo y el teléfono queda guardado como "sin verificar".
 *
 * Mientras falte verificar algo, el cliente solo puede ver la sección "Verificar cuenta" de Mi cuenta
 * (y no puede pagar). Las cuentas creadas desde el pago (checkout) y por el personal no se bloquean.
 */

defined( 'ABSPATH' ) || exit;

const MB_EP_VERIFICAR  = 'verificar-cuenta';
const MB_EP_CONTRASENA = 'cambiar-contrasena';

const MB_CODIGO_MINUTOS     = 15; // Vigencia del código de correo.
const MB_CODIGO_INTENTOS    = 5;  // Intentos fallidos antes de pedir otro código.
const MB_CODIGO_ESPERA      = 60; // Segundos entre envíos.
const MB_CODIGO_ENVIOS_HORA = 5;  // Envíos máximos por hora (por correo y por teléfono).

/* ================================================================== */
/* Utilidades                                                          */
/* ================================================================== */

/** Teléfono de El Salvador en formato +503XXXXXXXX, o '' si no es válido (8 dígitos, empieza con 2, 6 o 7). */
function mb_normalizar_telefono( string $telefono ): string {
	$digitos = (string) preg_replace( '/\D+/', '', $telefono );
	if ( 11 === strlen( $digitos ) && str_starts_with( $digitos, '503' ) ) {
		$digitos = substr( $digitos, 3 );
	}
	return preg_match( '/^[267]\d{7}$/', $digitos ) ? '+503' . $digitos : '';
}

/** +50360450515 → 6045-0515 */
function mb_telefono_local( string $e164 ): string {
	$d = substr( $e164, -8 );
	return substr( $d, 0, 4 ) . '-' . substr( $d, 4 );
}

/** ¿Está configurado Twilio Verify para enviar códigos al teléfono? */
function mb_sms_activo(): bool {
	return '' !== mb_correo_cfg( 'TWILIO_ACCOUNT_SID' )
		&& '' !== mb_correo_cfg( 'TWILIO_AUTH_TOKEN' )
		&& '' !== mb_correo_cfg( 'TWILIO_VERIFY_SID' );
}

function mb_falta_correo( int $uid ): bool {
	return '1' === get_user_meta( $uid, 'mb_verificar_correo', true );
}

function mb_falta_telefono( int $uid ): bool {
	return mb_sms_activo() && '1' === get_user_meta( $uid, 'mb_verificar_telefono', true );
}

function mb_verificacion_pendiente( int $uid = 0 ): bool {
	$uid = $uid ?: get_current_user_id();
	return $uid > 0 && ( mb_falta_correo( $uid ) || mb_falta_telefono( $uid ) );
}

function mb_url_verificar(): string {
	return wc_get_account_endpoint_url( MB_EP_VERIFICAR );
}

/**
 * Control de envíos: espera mínima entre códigos y máximo por hora.
 * Devuelve un mensaje de error, o '' si se puede enviar (y registra el envío).
 */
function mb_limite_envio( int $uid, string $canal ): string {
	$clave  = 'mb_envios_' . $canal;
	$ahora  = time();
	$envios = array_filter( (array) get_user_meta( $uid, $clave, true ), static fn( $t ) => is_int( $t ) && $t > $ahora - HOUR_IN_SECONDS );
	if ( $envios && max( $envios ) > $ahora - MB_CODIGO_ESPERA ) {
		return sprintf( 'Espera %d segundos antes de pedir otro código.', MB_CODIGO_ESPERA - ( $ahora - max( $envios ) ) );
	}
	if ( count( $envios ) >= MB_CODIGO_ENVIOS_HORA ) {
		return 'Pediste demasiados códigos. Intenta de nuevo en una hora o escríbenos por WhatsApp.';
	}
	$envios[] = $ahora;
	update_user_meta( $uid, $clave, array_values( $envios ) );
	return '';
}

/* ================================================================== */
/* Código por correo                                                   */
/* ================================================================== */

/** El código queda ligado al usuario y al correo: si cambia el correo, el código anterior deja de servir. */
function mb_hash_codigo( string $codigo, int $uid, string $correo ): string {
	return hash_hmac( 'sha256', $codigo . '|' . $uid . '|' . strtolower( $correo ), wp_salt( 'auth' ) );
}

/** Envía un código nuevo al correo del usuario. Devuelve '' si salió bien, o el mensaje de error. */
function mb_enviar_codigo_correo( int $uid, bool $sin_limite = false ): string {
	$user = get_userdata( $uid );
	if ( ! $user ) {
		return 'No encontramos tu cuenta.';
	}
	if ( ! $sin_limite ) {
		$error = mb_limite_envio( $uid, 'correo' );
		if ( $error ) {
			return $error;
		}
	}
	$codigo = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
	update_user_meta( $uid, 'mb_codigo_correo', array(
		'hash'     => mb_hash_codigo( $codigo, $uid, $user->user_email ),
		'expira'   => time() + MB_CODIGO_MINUTOS * MINUTE_IN_SECONDS,
		'intentos' => 0,
	) );

	$nombre = $user->first_name ?: $user->display_name;
	$cuerpo = sprintf(
		'<p>Hola %1$s,</p>
		<p>Este es tu código para verificar tu correo en Mechanical Bull:</p>
		<p style="font-size:34px;font-weight:700;letter-spacing:10px;margin:24px 0;">%2$s</p>
		<p>Vence en %3$d minutos. Escríbelo en la página <strong>Verificar cuenta</strong> de la tienda.</p>
		<p style="color:#8A8782;">Si no creaste una cuenta en Mechanical Bull, ignora este mensaje.</p>',
		esc_html( $nombre ),
		esc_html( $codigo ),
		MB_CODIGO_MINUTOS
	);
	$mailer = WC()->mailer();
	$ok     = $mailer->send( $user->user_email, "Tu código de verificación: {$codigo}", $mailer->wrap_message( 'Verifica tu correo', $cuerpo ) );
	return $ok ? '' : 'No pudimos enviar el correo. Intenta de nuevo en unos minutos.';
}

/** Comprueba el código del correo. Devuelve '' si es correcto (y marca el correo como verificado), o el error. */
function mb_comprobar_codigo_correo( int $uid, string $codigo ): string {
	$user  = get_userdata( $uid );
	$datos = get_user_meta( $uid, 'mb_codigo_correo', true );
	if ( ! $user || ! is_array( $datos ) || empty( $datos['hash'] ) ) {
		return 'Pide un código nuevo con el botón "Enviar otro código".';
	}
	if ( time() > (int) $datos['expira'] ) {
		return 'El código venció. Pide uno nuevo.';
	}
	if ( (int) $datos['intentos'] >= MB_CODIGO_INTENTOS ) {
		return 'Demasiados intentos. Pide un código nuevo.';
	}
	if ( ! hash_equals( (string) $datos['hash'], mb_hash_codigo( $codigo, $uid, $user->user_email ) ) ) {
		$datos['intentos'] = (int) $datos['intentos'] + 1;
		update_user_meta( $uid, 'mb_codigo_correo', $datos );
		$quedan = MB_CODIGO_INTENTOS - $datos['intentos'];
		return $quedan > 0 ? sprintf( 'Código incorrecto. Te quedan %d intentos.', $quedan ) : 'Código incorrecto. Pide un código nuevo.';
	}
	delete_user_meta( $uid, 'mb_codigo_correo' );
	delete_user_meta( $uid, 'mb_verificar_correo' );
	update_user_meta( $uid, 'mb_correo_verificado', current_time( 'mysql' ) );
	return '';
}

/* ================================================================== */
/* Código por teléfono (Twilio Verify)                                 */
/* ================================================================== */

/** Llamada a la API de Twilio Verify. Devuelve la respuesta JSON o WP_Error. */
function mb_twilio( string $ruta, array $campos ) {
	$sid       = mb_correo_cfg( 'TWILIO_ACCOUNT_SID' );
	$respuesta = wp_remote_post(
		'https://verify.twilio.com/v2/Services/' . rawurlencode( mb_correo_cfg( 'TWILIO_VERIFY_SID' ) ) . '/' . $ruta,
		array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $sid . ':' . mb_correo_cfg( 'TWILIO_AUTH_TOKEN' ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			'body'    => $campos,
		)
	);
	if ( is_wp_error( $respuesta ) ) {
		return $respuesta;
	}
	$codigo = (int) wp_remote_retrieve_response_code( $respuesta );
	$json   = json_decode( (string) wp_remote_retrieve_body( $respuesta ), true );
	$json   = is_array( $json ) ? $json : array();
	if ( $codigo >= 300 ) {
		return new WP_Error( 'twilio_' . $codigo, (string) ( $json['message'] ?? "Error {$codigo}" ), array( 'status' => $codigo ) );
	}
	return $json;
}

function mb_enviar_codigo_telefono( int $uid, bool $sin_limite = false ): string {
	$telefono = (string) get_user_meta( $uid, 'mb_telefono', true );
	if ( ! $telefono || ! mb_sms_activo() ) {
		return 'No hay un teléfono para verificar.';
	}
	if ( ! $sin_limite ) {
		$error = mb_limite_envio( $uid, 'telefono' );
		if ( $error ) {
			return $error;
		}
	}
	$canal     = 'whatsapp' === mb_correo_cfg( 'TWILIO_CANAL' ) ? 'whatsapp' : 'sms';
	$respuesta = mb_twilio( 'Verifications', array( 'To' => $telefono, 'Channel' => $canal, 'Locale' => 'es' ) );
	if ( is_wp_error( $respuesta ) ) {
		error_log( '[mb-cuentas] Twilio no envió el código a ' . $telefono . ': ' . $respuesta->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return 'No pudimos enviar el código a tu teléfono. Revisa el número o intenta de nuevo en unos minutos.';
	}
	return '';
}

function mb_comprobar_codigo_telefono( int $uid, string $codigo ): string {
	$telefono = (string) get_user_meta( $uid, 'mb_telefono', true );
	if ( ! $telefono ) {
		return 'No hay un teléfono para verificar.';
	}
	$respuesta = mb_twilio( 'VerificationCheck', array( 'To' => $telefono, 'Code' => $codigo ) );
	if ( is_wp_error( $respuesta ) ) {
		// 404: no hay un código vigente (venció o se agotaron los intentos).
		$estado = (int) ( $respuesta->get_error_data()['status'] ?? 0 );
		if ( 404 !== $estado ) {
			error_log( '[mb-cuentas] Twilio no comprobó el código de ' . $telefono . ': ' . $respuesta->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
		return 404 === $estado ? 'El código venció o se agotaron los intentos. Pide uno nuevo.' : 'No pudimos comprobar el código. Intenta de nuevo.';
	}
	if ( 'approved' !== ( $respuesta['status'] ?? '' ) ) {
		return 'Código incorrecto. Revisa el mensaje e intenta de nuevo.';
	}
	delete_user_meta( $uid, 'mb_verificar_telefono' );
	update_user_meta( $uid, 'mb_telefono_verificado', current_time( 'mysql' ) );
	return '';
}

/* ================================================================== */
/* Registro                                                            */
/* ================================================================== */

// El cliente elige su contraseña al registrarse (no se genera una automática).
add_filter( 'pre_option_woocommerce_registration_generate_password', static fn() => 'no' );
add_filter( 'pre_option_woocommerce_registration_generate_username', static fn() => 'yes' );

/** ¿La petición viene del formulario de registro de Mi cuenta (no del pago)? */
function mb_es_registro_mi_cuenta(): bool {
	return isset( $_POST['register'], $_POST['woocommerce-register-nonce'] ); // phpcs:ignore WordPress.Security.NonceVerification
}

add_action( 'woocommerce_register_form_start', static function (): void {
	// phpcs:disable WordPress.Security.NonceVerification -- solo se vuelve a mostrar lo que escribió el cliente.
	$valor = static fn( string $campo ): string => isset( $_POST[ $campo ] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST[ $campo ] ) ) ) : '';
	// phpcs:enable
	?>
	<p class="woocommerce-form-row form-row form-row-first">
		<label for="mb_nombre">Nombre&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input input-text" name="mb_nombre" id="mb_nombre" autocomplete="given-name" value="<?php echo $valor( 'mb_nombre' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" required>
	</p>
	<p class="woocommerce-form-row form-row form-row-last">
		<label for="mb_apellido">Apellido&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input input-text" name="mb_apellido" id="mb_apellido" autocomplete="family-name" value="<?php echo $valor( 'mb_apellido' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" required>
	</p>
	<div class="clear"></div>
	<p class="woocommerce-form-row form-row form-row-wide">
		<label for="mb_telefono">Teléfono / WhatsApp&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<span class="mb-campo-tel"><span class="mb-campo-tel__pref">+503</span><input type="tel" class="woocommerce-Input input-text" name="mb_telefono" id="mb_telefono" autocomplete="tel-national" inputmode="tel" placeholder="7000-0000" maxlength="12" value="<?php echo $valor( 'mb_telefono' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" required></span>
	</p>
	<?php
} );

add_action( 'woocommerce_register_form', static function (): void {
	?>
	<p class="woocommerce-form-row form-row form-row-wide">
		<label for="mb_password2">Repite la contraseña&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="password" class="woocommerce-Input input-text" name="mb_password2" id="mb_password2" autocomplete="new-password" required>
	</p>
	<p class="mb-registro-aviso">
		<?php echo mb_sms_activo() ? 'Te enviaremos un código a tu correo y otro a tu teléfono para confirmar que son tuyos.' : 'Te enviaremos un código a tu correo para confirmar que es tuyo.'; ?>
	</p>
	<?php
} );

add_filter( 'woocommerce_registration_errors', static function ( WP_Error $errores ): WP_Error {
	if ( ! mb_es_registro_mi_cuenta() ) {
		return $errores;
	}
	// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce ya comprobó el nonce del registro.
	$nombre    = sanitize_text_field( wp_unslash( $_POST['mb_nombre'] ?? '' ) );
	$apellido  = sanitize_text_field( wp_unslash( $_POST['mb_apellido'] ?? '' ) );
	$telefono  = mb_normalizar_telefono( sanitize_text_field( wp_unslash( $_POST['mb_telefono'] ?? '' ) ) );
	$clave     = (string) wp_unslash( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$clave2    = (string) wp_unslash( $_POST['mb_password2'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	// phpcs:enable
	if ( '' === $nombre || '' === $apellido ) {
		$errores->add( 'mb_nombre', 'Escribe tu nombre y tu apellido.' );
	}
	if ( '' === $telefono ) {
		$errores->add( 'mb_telefono', 'Escribe un teléfono válido de El Salvador: 8 dígitos, por ejemplo 7000-0000.' );
	} elseif ( get_users( array( 'meta_key' => 'mb_telefono', 'meta_value' => $telefono, 'number' => 1, 'fields' => 'ID' ) ) ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$errores->add( 'mb_telefono', 'Ya hay una cuenta con ese teléfono. Inicia sesión o usa "¿Olvidaste tu contraseña?".' );
	}
	if ( strlen( $clave ) < 8 ) {
		$errores->add( 'mb_password', 'La contraseña debe tener al menos 8 caracteres.' );
	} elseif ( $clave !== $clave2 ) {
		$errores->add( 'mb_password2', 'Las contraseñas no coinciden.' );
	}
	return $errores;
} );

add_action( 'woocommerce_created_customer', static function ( int $uid ): void {
	if ( ! mb_es_registro_mi_cuenta() ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce ya comprobó el nonce del registro.
	$nombre   = sanitize_text_field( wp_unslash( $_POST['mb_nombre'] ?? '' ) );
	$apellido = sanitize_text_field( wp_unslash( $_POST['mb_apellido'] ?? '' ) );
	$telefono = mb_normalizar_telefono( sanitize_text_field( wp_unslash( $_POST['mb_telefono'] ?? '' ) ) );
	// phpcs:enable
	wp_update_user( array(
		'ID'           => $uid,
		'first_name'   => $nombre,
		'last_name'    => $apellido,
		'display_name' => trim( "{$nombre} {$apellido}" ),
	) );
	update_user_meta( $uid, 'billing_first_name', $nombre );
	update_user_meta( $uid, 'billing_last_name', $apellido );
	update_user_meta( $uid, 'billing_phone', mb_telefono_local( $telefono ) );
	update_user_meta( $uid, 'mb_telefono', $telefono );
	update_user_meta( $uid, 'mb_verificar_correo', '1' );
	update_user_meta( $uid, 'mb_verificar_telefono', '1' );

	mb_limite_envio( $uid, 'correo' );
	mb_enviar_codigo_correo( $uid, true );
	if ( mb_sms_activo() ) {
		mb_limite_envio( $uid, 'telefono' );
		mb_enviar_codigo_telefono( $uid, true );
	}
}, 20 );

add_filter( 'woocommerce_registration_redirect', static function ( $url ) {
	return mb_verificacion_pendiente() ? mb_url_verificar() : $url;
}, 20 );

/* ================================================================== */
/* Secciones nuevas de Mi cuenta                                       */
/* ================================================================== */

add_filter( 'woocommerce_get_query_vars', static function ( array $vars ): array {
	$vars[ MB_EP_VERIFICAR ]  = MB_EP_VERIFICAR;
	$vars[ MB_EP_CONTRASENA ] = MB_EP_CONTRASENA;
	return $vars;
} );

// Las direcciones nuevas necesitan regenerar las reglas de enlaces permanentes una vez por servidor.
add_action( 'wp_loaded', static function (): void {
	if ( '1' !== get_option( 'mb_version_endpoints' ) ) {
		flush_rewrite_rules( false );
		update_option( 'mb_version_endpoints', '1' );
	}
} );

add_filter( 'woocommerce_account_menu_items', static function ( array $items ): array {
	$menu = array();
	if ( mb_verificacion_pendiente() ) {
		$menu[ MB_EP_VERIFICAR ] = 'Verificar cuenta';
	}
	foreach ( $items as $clave => $texto ) {
		if ( 'customer-logout' === $clave ) {
			$menu[ MB_EP_CONTRASENA ] = 'Cambiar contraseña';
		}
		$menu[ $clave ] = $texto;
	}
	return $menu;
} );

add_filter( 'woocommerce_endpoint_' . MB_EP_VERIFICAR . '_title', static fn() => 'Verifica tu cuenta' );
add_filter( 'woocommerce_endpoint_' . MB_EP_CONTRASENA . '_title', static fn() => 'Cambiar contraseña' );

/** Mientras falte verificar, Mi cuenta y el pago llevan a "Verificar cuenta". */
add_action( 'template_redirect', static function (): void {
	if ( ! is_user_logged_in() || ! mb_verificacion_pendiente() ) {
		return;
	}
	if ( ! ( is_account_page() || is_checkout() ) || is_wc_endpoint_url( MB_EP_VERIFICAR ) || is_wc_endpoint_url( 'customer-logout' ) ) {
		return;
	}
	if ( is_checkout() ) {
		wc_add_notice( 'Antes de comprar con tu cuenta, verifica tu correo' . ( mb_falta_telefono( get_current_user_id() ) ? ' y tu teléfono.' : '.' ), 'notice' );
	}
	wp_safe_redirect( mb_url_verificar() );
	exit;
}, 20 );

/* ---------- Formularios (se procesan antes de mostrar la página) ---------- */

add_action( 'template_redirect', static function (): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['mb_accion'] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$accion = sanitize_key( wp_unslash( $_POST['mb_accion'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mb_nonce'] ?? '' ) ), 'mb_cuenta_' . $accion ) ) {
		wc_add_notice( 'La página venció. Intenta de nuevo.', 'error' );
		wp_safe_redirect( wc_get_account_endpoint_url( 'dashboard' ) );
		exit;
	}
	$uid    = get_current_user_id();
	$codigo = (string) preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( $_POST['codigo'] ?? '' ) ) );
	$volver = mb_url_verificar();
	$error  = '';
	$exito  = '';

	switch ( $accion ) {
		case 'verificar_correo':
			$error = 6 === strlen( $codigo ) ? mb_comprobar_codigo_correo( $uid, $codigo ) : 'Escribe el código de 6 dígitos.';
			$exito = 'Correo verificado.';
			break;

		case 'reenviar_correo':
			$error = mb_enviar_codigo_correo( $uid );
			$exito = 'Te enviamos un código nuevo. Revisa tu bandeja de entrada y la carpeta de spam.';
			break;

		case 'cambiar_correo':
			$correo = sanitize_email( wp_unslash( $_POST['correo'] ?? '' ) );
			if ( ! is_email( $correo ) ) {
				$error = 'Escribe un correo válido.';
			} elseif ( email_exists( $correo ) && email_exists( $correo ) !== $uid ) {
				$error = 'Ese correo ya está en otra cuenta.';
			} else {
				wp_update_user( array( 'ID' => $uid, 'user_email' => $correo ) );
				update_user_meta( $uid, 'billing_email', $correo );
				update_user_meta( $uid, 'mb_verificar_correo', '1' );
				$error = mb_enviar_codigo_correo( $uid );
				$exito = 'Correo actualizado. Te enviamos el código a ' . $correo . '.';
			}
			break;

		case 'verificar_telefono':
			$error = ( strlen( $codigo ) >= 4 && strlen( $codigo ) <= 10 ) ? mb_comprobar_codigo_telefono( $uid, $codigo ) : 'Escribe el código que te llegó al teléfono.';
			$exito = 'Teléfono verificado.';
			break;

		case 'reenviar_telefono':
			$error = mb_enviar_codigo_telefono( $uid );
			$exito = 'Te enviamos un código nuevo a tu teléfono.';
			break;

		case 'cambiar_telefono':
			$telefono = mb_normalizar_telefono( sanitize_text_field( wp_unslash( $_POST['telefono'] ?? '' ) ) );
			$otro     = $telefono ? get_users( array( 'meta_key' => 'mb_telefono', 'meta_value' => $telefono, 'exclude' => array( $uid ), 'number' => 1, 'fields' => 'ID' ) ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( ! $telefono ) {
				$error = 'Escribe un teléfono válido de El Salvador: 8 dígitos, por ejemplo 7000-0000.';
			} elseif ( $otro ) {
				$error = 'Ese teléfono ya está en otra cuenta.';
			} else {
				update_user_meta( $uid, 'mb_telefono', $telefono );
				update_user_meta( $uid, 'billing_phone', mb_telefono_local( $telefono ) );
				update_user_meta( $uid, 'mb_verificar_telefono', '1' );
				delete_user_meta( $uid, 'mb_telefono_verificado' );
				$error = mb_enviar_codigo_telefono( $uid );
				$exito = 'Teléfono actualizado. Te enviamos el código al ' . mb_telefono_local( $telefono ) . '.';
			}
			break;

		case 'cambiar_contrasena':
			$volver = wc_get_account_endpoint_url( MB_EP_CONTRASENA );
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- las contraseñas no se modifican.
			$actual = (string) wp_unslash( $_POST['actual'] ?? '' );
			$nueva  = (string) wp_unslash( $_POST['nueva'] ?? '' );
			$nueva2 = (string) wp_unslash( $_POST['nueva2'] ?? '' );
			// phpcs:enable
			$user = wp_get_current_user();
			if ( ! wp_check_password( $actual, $user->user_pass, $uid ) ) {
				$error = 'La contraseña actual no es correcta.';
			} elseif ( strlen( $nueva ) < 8 ) {
				$error = 'La contraseña nueva debe tener al menos 8 caracteres.';
			} elseif ( $nueva !== $nueva2 ) {
				$error = 'Las contraseñas nuevas no coinciden.';
			} elseif ( $nueva === $actual ) {
				$error = 'La contraseña nueva debe ser distinta de la actual.';
			} else {
				// wp_update_user mantiene la sesión abierta. El aviso se envía con la plantilla de WooCommerce.
				add_filter( 'send_password_change_email', '__return_false' );
				wp_update_user( array( 'ID' => $uid, 'user_pass' => $nueva ) );
				mb_aviso_contrasena( $uid );
				$exito = 'Listo: tu contraseña se cambió. Te enviamos un aviso a tu correo.';
			}
			break;

		default:
			return;
	}

	if ( $error ) {
		wc_add_notice( $error, 'error' );
	} else {
		wc_add_notice( $exito, 'success' );
		if ( str_starts_with( $accion, 'verificar_' ) && ! mb_verificacion_pendiente( $uid ) ) {
			wc_add_notice( '¡Tu cuenta está verificada! Ya puedes comprar y ver tus pedidos.', 'success' );
			$volver = wc_get_account_endpoint_url( 'dashboard' );
		}
	}
	wp_safe_redirect( $volver );
	exit;
}, 5 );

/** Aviso al cliente de que su contraseña cambió (por si no fue él). */
function mb_aviso_contrasena( int $uid ): void {
	$user = get_userdata( $uid );
	if ( ! $user ) {
		return;
	}
	$cuerpo = sprintf(
		'<p>Hola %1$s,</p>
		<p>Te avisamos que la contraseña de tu cuenta en Mechanical Bull se cambió el %2$s.</p>
		<p>Si fuiste tú, no tienes que hacer nada. Si <strong>no</strong> fuiste tú, entra a <a href="%3$s">Recuperar contraseña</a> para crear una nueva y escríbenos por WhatsApp al %4$s.</p>',
		esc_html( $user->first_name ?: $user->display_name ),
		esc_html( wp_date( 'd/m/Y \a \l\a\s g:i a' ) ),
		esc_url( wc_lostpassword_url() ),
		esc_html( function_exists( 'mb_telefono_legible' ) ? mb_telefono_legible() : '' )
	);
	$mailer = WC()->mailer();
	$mailer->send( $user->user_email, 'Tu contraseña se cambió', $mailer->wrap_message( 'Tu contraseña se cambió', $cuerpo ) );
}

/** Campos ocultos de cada formulario. */
function mb_campos_formulario( string $accion ): void {
	printf( '<input type="hidden" name="mb_accion" value="%s">', esc_attr( $accion ) );
	wp_nonce_field( 'mb_cuenta_' . $accion, 'mb_nonce', false );
}

/** Tarjeta de un paso de verificación (correo o teléfono). */
function mb_tarjeta_verificacion( string $tipo, string $destino, bool $listo, int $numero ): void {
	$es_correo = 'correo' === $tipo;
	?>
	<div class="mb-verificar__paso<?php echo $listo ? ' is-listo' : ''; ?>">
		<div class="mb-verificar__cab">
			<span class="mb-verificar__num"><?php echo $listo ? '&#10003;' : (int) $numero; ?></span>
			<div>
				<h3><?php echo $es_correo ? 'Tu correo' : 'Tu teléfono'; ?></h3>
				<p><?php echo esc_html( $destino ); ?></p>
			</div>
			<?php if ( $listo ) : ?><span class="mb-verificar__ok">Verificado</span><?php endif; ?>
		</div>
		<?php if ( ! $listo ) : ?>
			<p class="mb-verificar__txt">
				<?php echo $es_correo
					? sprintf( 'Te enviamos un código de 6 dígitos. Vence en %d minutos; si no lo ves, revisa la carpeta de spam.', MB_CODIGO_MINUTOS )
					: ( 'whatsapp' === mb_correo_cfg( 'TWILIO_CANAL' ) ? 'Te enviamos un código por WhatsApp.' : 'Te enviamos un código por mensaje de texto (SMS).' ); ?>
			</p>
			<form method="post" class="mb-verificar__form">
				<?php mb_campos_formulario( $es_correo ? 'verificar_correo' : 'verificar_telefono' ); ?>
				<label class="screen-reader-text" for="mb-codigo-<?php echo esc_attr( $tipo ); ?>">Código</label>
				<input type="text" id="mb-codigo-<?php echo esc_attr( $tipo ); ?>" name="codigo" class="input-text mb-codigo" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7" placeholder="000000" required>
				<button type="submit" class="button">Verificar</button>
			</form>
			<div class="mb-verificar__extra">
				<form method="post">
					<?php mb_campos_formulario( $es_correo ? 'reenviar_correo' : 'reenviar_telefono' ); ?>
					<button type="submit" class="mb-enlace">Enviar otro código</button>
				</form>
				<details>
					<summary class="mb-enlace"><?php echo $es_correo ? '¿Te equivocaste de correo?' : '¿Te equivocaste de número?'; ?></summary>
					<form method="post" class="mb-verificar__form">
						<?php mb_campos_formulario( $es_correo ? 'cambiar_correo' : 'cambiar_telefono' ); ?>
						<?php if ( $es_correo ) : ?>
							<input type="email" name="correo" class="input-text" autocomplete="email" placeholder="tucorreo@ejemplo.com" required>
						<?php else : ?>
							<input type="tel" name="telefono" class="input-text" autocomplete="tel-national" inputmode="tel" placeholder="7000-0000" required>
						<?php endif; ?>
						<button type="submit" class="button">Cambiar y enviar código</button>
					</form>
				</details>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'woocommerce_account_' . MB_EP_VERIFICAR . '_endpoint', static function (): void {
	$uid  = get_current_user_id();
	$user = wp_get_current_user();
	if ( ! mb_verificacion_pendiente( $uid ) ) {
		printf(
			'<p>Tu cuenta ya está verificada. <a href="%s">Ir a Mi cuenta</a></p>',
			esc_url( wc_get_account_endpoint_url( 'dashboard' ) )
		);
		return;
	}
	$con_telefono = mb_sms_activo();
	$telefono     = (string) get_user_meta( $uid, 'mb_telefono', true );
	echo '<div class="mb-verificar">';
	printf(
		'<p class="mb-verificar__intro">Para proteger tu cuenta y tus pedidos, confirma que %s. Solo se hace una vez.</p>',
		$con_telefono ? 'tu correo y tu teléfono son tuyos' : 'tu correo es tuyo'
	);
	mb_tarjeta_verificacion( 'correo', $user->user_email, ! mb_falta_correo( $uid ), 1 );
	if ( $con_telefono ) {
		mb_tarjeta_verificacion( 'telefono', $telefono ? '+503 ' . mb_telefono_local( $telefono ) : 'Sin teléfono', ! mb_falta_telefono( $uid ), 2 );
	}
	echo '</div>';
} );

add_action( 'woocommerce_account_' . MB_EP_CONTRASENA . '_endpoint', static function (): void {
	?>
	<form method="post" class="mb-contrasena woocommerce-EditAccountForm">
		<?php mb_campos_formulario( 'cambiar_contrasena' ); ?>
		<p class="woocommerce-form-row form-row form-row-wide">
			<label for="mb-actual">Contraseña actual&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="password" class="woocommerce-Input input-text" name="actual" id="mb-actual" autocomplete="current-password" required>
		</p>
		<p class="woocommerce-form-row form-row form-row-wide">
			<label for="mb-nueva">Contraseña nueva&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="password" class="woocommerce-Input input-text" name="nueva" id="mb-nueva" autocomplete="new-password" minlength="8" required>
			<span class="mb-ayuda">Mínimo 8 caracteres. Mezcla letras, números y algún símbolo.</span>
		</p>
		<p class="woocommerce-form-row form-row form-row-wide">
			<label for="mb-nueva2">Repite la contraseña nueva&nbsp;<span class="required" aria-hidden="true">*</span></label>
			<input type="password" class="woocommerce-Input input-text" name="nueva2" id="mb-nueva2" autocomplete="new-password" minlength="8" required>
		</p>
		<p><button type="submit" class="woocommerce-Button button">Cambiar contraseña</button></p>
		<p class="mb-ayuda">¿No recuerdas tu contraseña actual? Cierra sesión y usa <strong>"¿Olvidaste la contraseña?"</strong> en la página de acceso: te enviamos un enlace a tu correo para crear una nueva.</p>
	</form>
	<?php
} );

/* ---------- Detalles de la cuenta: teléfono y cambio de correo ---------- */

add_action( 'woocommerce_edit_account_form_start', static function (): void {
	$uid      = get_current_user_id();
	$telefono = (string) get_user_meta( $uid, 'mb_telefono', true );
	if ( ! $telefono ) {
		return;
	}
	$estado = get_user_meta( $uid, 'mb_telefono_verificado', true ) ? '<span class="mb-verificar__ok">Verificado</span>' : '';
	printf(
		'<p class="mb-dato-cuenta"><span>Teléfono</span> <strong>+503 %s</strong> %s</p>',
		esc_html( mb_telefono_local( $telefono ) ),
		$estado // phpcs:ignore WordPress.Security.EscapeOutput
	);
} );

/** Si un cliente cambia su correo en "Detalles de la cuenta", tiene que verificar el nuevo. */
add_action( 'woocommerce_save_account_details_errors', static function ( WP_Error $errores, $user ): void {
	$anterior = get_userdata( (int) $user->ID );
	if ( $anterior && ! $errores->has_errors() && ! user_can( $anterior, 'edit_posts' )
		&& strtolower( (string) $user->user_email ) !== strtolower( $anterior->user_email ) ) {
		$GLOBALS['mb_correo_cambiado'] = true;
	}
}, 99, 2 );

add_action( 'woocommerce_save_account_details', static function ( int $uid ): void {
	if ( empty( $GLOBALS['mb_correo_cambiado'] ) ) {
		return;
	}
	delete_user_meta( $uid, 'mb_correo_verificado' );
	update_user_meta( $uid, 'mb_verificar_correo', '1' );
	mb_enviar_codigo_correo( $uid, true );
	wc_add_notice( 'Cambiaste tu correo: te enviamos un código para verificarlo.', 'notice' );
} );

add_filter( 'woocommerce_save_account_details_redirect', static function ( $url ) {
	return mb_verificacion_pendiente() ? mb_url_verificar() : $url;
} );

/* ---------- Panel: estado de verificación en la lista de usuarios ---------- */

add_filter( 'manage_users_columns', static function ( array $columnas ): array {
	$columnas['mb_verificacion'] = 'Verificación';
	return $columnas;
} );

add_filter( 'manage_users_custom_column', static function ( $salida, string $columna, int $uid ) {
	if ( 'mb_verificacion' !== $columna ) {
		return $salida;
	}
	$partes = array();
	if ( get_user_meta( $uid, 'mb_correo_verificado', true ) ) {
		$partes[] = '✔ Correo';
	} elseif ( mb_falta_correo( $uid ) ) {
		$partes[] = '✗ Correo';
	}
	$telefono = (string) get_user_meta( $uid, 'mb_telefono', true );
	if ( $telefono ) {
		$partes[] = ( get_user_meta( $uid, 'mb_telefono_verificado', true ) ? '✔ ' : '✗ ' ) . mb_telefono_local( $telefono );
	}
	return $partes ? esc_html( implode( ' · ', $partes ) ) : '—';
}, 10, 3 );
