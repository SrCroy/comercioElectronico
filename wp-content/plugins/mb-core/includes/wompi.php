<?php
/**
 * Pago con tarjeta por Wompi El Salvador (https://docs.wompi.sv).
 *
 * Flujo:
 * 1. Al pagar, se crea un "Enlace de Pago" de Wompi por el total del pedido y se manda al cliente a pagar ahí.
 * 2. Wompi avisa a la tienda por webhook (firmado con HMAC-SHA256 del API Secret) y además devuelve al
 *    cliente a la tienda. En los dos casos la transacción se confirma consultando la API de Wompi
 *    (TransaccionCompra) antes de marcar el pedido como pagado: no se confía en lo que llega en la URL.
 * 3. Los pagos de prueba (negocio en "modo desarrollo" en el panel de Wompi) dejan el pedido "En espera"
 *    con una nota, porque no se cobró dinero.
 *
 * Credenciales (Panel de Wompi → tu aplicativo): App ID y API Secret. Se escriben en
 * WooCommerce → Ajustes → Pagos → Wompi, o como variables de entorno WOMPI_APP_ID y WOMPI_API_SECRET
 * (o constantes MB_WOMPI_APP_ID / MB_WOMPI_API_SECRET), que tienen prioridad.
 * Los reembolsos se hacen desde el panel de Wompi.
 */

defined( 'ABSPATH' ) || exit;

const MB_WOMPI_API   = 'https://api.wompi.sv';
const MB_WOMPI_TOKEN = 'https://id.wompi.sv/connect/token';

add_action( 'plugins_loaded', static function (): void {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class MB_Gateway_Wompi extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'mb_wompi';
			$this->method_title       = 'Wompi (tarjeta)';
			$this->method_description = 'Pago con tarjeta de crédito o débito por Wompi El Salvador. El cliente paga en la página segura de Wompi y el pedido se marca como pagado cuando Wompi confirma la transacción.';
			$this->has_fields         = false;
			$this->supports           = array( 'products' );
			$this->icon               = '';

			$this->init_form_fields();
			$this->init_settings();

			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields(): void {
			$this->form_fields = array(
				'enabled'     => array(
					'title'   => 'Activar',
					'type'    => 'checkbox',
					'label'   => 'Aceptar pagos con tarjeta por Wompi',
					'default' => 'no',
				),
				'title'       => array(
					'title'   => 'Título',
					'type'    => 'text',
					'default' => 'Tarjeta de crédito o débito (Wompi)',
				),
				'description' => array(
					'title'   => 'Descripción en el pago',
					'type'    => 'textarea',
					'default' => 'Paga con Visa o Mastercard en la página segura de Wompi (Banco Agrícola). Al terminar regresas a la tienda y tu pedido queda pagado.',
				),
				'app_id'      => array(
					'title'       => 'App ID',
					'type'        => 'text',
					'description' => 'Panel de Wompi → Aplicativos → tu aplicativo. Si la variable de entorno WOMPI_APP_ID está definida, se usa esa.',
					'default'     => '',
				),
				'api_secret'  => array(
					'title'       => 'API Secret',
					'type'        => 'password',
					'description' => 'Si la variable de entorno WOMPI_API_SECRET está definida, se usa esa.',
					'default'     => '',
				),
				'webhook'     => array(
					'title'       => 'Webhook',
					'type'        => 'title',
					'description' => 'No hace falta configurarlo en Wompi: cada enlace de pago lleva su webhook. Dirección: <code>' . esc_html( mb_wompi_url_webhook() ) . '</code>',
				),
			);
		}

		public function app_id(): string {
			return trim( mb_correo_cfg( 'WOMPI_APP_ID' ) ?: (string) $this->get_option( 'app_id' ) );
		}

		public function api_secret(): string {
			return trim( mb_correo_cfg( 'WOMPI_API_SECRET' ) ?: (string) $this->get_option( 'api_secret' ) );
		}

		/** Sin credenciales o en otra moneda, Wompi no aparece en el pago. */
		public function is_available(): bool {
			return parent::is_available() && '' !== $this->app_id() && '' !== $this->api_secret() && 'USD' === get_woocommerce_currency();
		}

		public function process_payment( $order_id ): array {
			$order = wc_get_order( $order_id );
			$total = round( (float) $order->get_total(), 2 );
			if ( $total < 0.01 ) {
				wc_add_notice( 'El total del pedido no es válido para pagar con tarjeta.', 'error' );
				return array( 'result' => 'failure' );
			}

			// Referencia única por intento: si el cliente vuelve a intentar, se crea un enlace nuevo.
			$referencia = 'MB-' . $order->get_id() . '-' . wp_generate_password( 8, false );
			$respuesta  = mb_wompi_api( $this, 'POST', '/EnlacePago', array(
				'identificadorEnlaceComercio' => $referencia,
				'monto'                       => $total,
				'nombreProducto'              => sprintf( 'Pedido #%s - %s', $order->get_order_number(), get_bloginfo( 'name' ) ),
				'configuracion'               => array(
					'urlRedirect'                 => mb_wompi_url_retorno( $order ),
					'urlRetorno'                  => wc_get_checkout_url(),
					'urlWebhook'                  => mb_wompi_url_webhook(),
					'notificarTransaccionCliente' => true,
					'esMontoEditable'             => false,
					'esCantidadEditable'          => false,
				),
				'limitesDeUso'                => array( 'cantidadMaximaPagosExitosos' => 1 ),
			) );

			if ( is_wp_error( $respuesta ) || empty( $respuesta['urlEnlace'] ) ) {
				$motivo = is_wp_error( $respuesta ) ? $respuesta->get_error_message() : 'respuesta sin urlEnlace';
				error_log( '[mb-wompi] No se pudo crear el enlace de pago del pedido ' . $order->get_id() . ': ' . $motivo ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				$order->add_order_note( 'Wompi: no se pudo crear el enlace de pago (' . $motivo . ').' );
				wc_add_notice( 'No pudimos conectar con Wompi para el pago con tarjeta. Intenta de nuevo en unos minutos o elige otro método de pago.', 'error' );
				return array( 'result' => 'failure' );
			}

			$order->update_meta_data( '_mb_wompi_referencia', $referencia );
			$order->update_meta_data( '_mb_wompi_enlace', (string) ( $respuesta['idEnlace'] ?? '' ) );
			$order->update_status( 'pending', 'Wompi: el cliente fue a pagar con tarjeta (enlace ' . ( $respuesta['idEnlace'] ?? '?' ) . ( empty( $respuesta['estaProductivo'] ) ? ', MODO PRUEBA' : '' ) . ').' );
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => (string) $respuesta['urlEnlace'],
			);
		}
	}

	add_filter( 'woocommerce_payment_gateways', static function ( array $gateways ): array {
		$gateways[] = 'MB_Gateway_Wompi';
		return $gateways;
	} );
}, 20 );

/* ================================================================== */
/* API de Wompi                                                        */
/* ================================================================== */

function mb_wompi_pasarela(): ?MB_Gateway_Wompi {
	$pasarelas = function_exists( 'WC' ) ? WC()->payment_gateways()->payment_gateways() : array();
	return ( $pasarelas['mb_wompi'] ?? null ) instanceof MB_Gateway_Wompi ? $pasarelas['mb_wompi'] : null;
}

function mb_wompi_url_webhook(): string {
	return add_query_arg( 'wc-api', 'mb_wompi_webhook', home_url( '/' ) );
}

function mb_wompi_url_retorno( WC_Order $order ): string {
	return add_query_arg( array( 'wc-api' => 'mb_wompi_retorno', 'pedido' => $order->get_id(), 'key' => $order->get_order_key() ), home_url( '/' ) );
}

/** Token de acceso (client credentials). Se guarda en caché hasta poco antes de vencer. */
function mb_wompi_token( MB_Gateway_Wompi $gw, bool $nuevo = false ) {
	$cache = 'mb_wompi_token_' . md5( $gw->app_id() . $gw->api_secret() );
	$token = $nuevo ? false : get_transient( $cache );
	if ( $token ) {
		return $token;
	}
	$respuesta = wp_remote_post( MB_WOMPI_TOKEN, array(
		'timeout' => 20,
		'body'    => array(
			'grant_type'    => 'client_credentials',
			'audience'      => 'wompi_api',
			'client_id'     => $gw->app_id(),
			'client_secret' => $gw->api_secret(),
		),
	) );
	if ( is_wp_error( $respuesta ) ) {
		return $respuesta;
	}
	$json = json_decode( (string) wp_remote_retrieve_body( $respuesta ), true );
	if ( empty( $json['access_token'] ) ) {
		$codigo = (int) wp_remote_retrieve_response_code( $respuesta );
		return new WP_Error( 'mb_wompi_token', "Wompi no entregó el token (HTTP {$codigo}). Revisa el App ID y el API Secret." );
	}
	set_transient( $cache, $json['access_token'], max( 60, (int) ( $json['expires_in'] ?? 3600 ) - 120 ) );
	return $json['access_token'];
}

/** Llamada a la API de Wompi. Devuelve el JSON como array o WP_Error. */
function mb_wompi_api( MB_Gateway_Wompi $gw, string $metodo, string $ruta, ?array $cuerpo = null, bool $reintento = false ) {
	$token = mb_wompi_token( $gw, $reintento );
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$args = array(
		'method'  => $metodo,
		'timeout' => 25,
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		),
	);
	if ( null !== $cuerpo ) {
		$args['body'] = wp_json_encode( $cuerpo );
	}
	$respuesta = wp_remote_request( MB_WOMPI_API . $ruta, $args );
	if ( is_wp_error( $respuesta ) ) {
		return $respuesta;
	}
	$codigo = (int) wp_remote_retrieve_response_code( $respuesta );
	if ( 401 === $codigo && ! $reintento ) {
		return mb_wompi_api( $gw, $metodo, $ruta, $cuerpo, true ); // Token vencido: se pide otro una vez.
	}
	$texto = (string) wp_remote_retrieve_body( $respuesta );
	$json  = json_decode( $texto, true );
	if ( $codigo < 200 || $codigo >= 300 || ! is_array( $json ) ) {
		return new WP_Error( 'mb_wompi_' . $codigo, "HTTP {$codigo}: " . substr( wp_strip_all_tags( $texto ), 0, 300 ) );
	}
	return $json;
}

/**
 * Confirma una transacción con la API y, si corresponde, marca el pedido.
 * Devuelve 'pagado', 'prueba', 'rechazado', 'revisar' (hay que verlo a mano) o 'error' (no se pudo consultar a Wompi).
 */
function mb_wompi_confirmar( WC_Order $order, string $id_transaccion, string $origen ): string {
	if ( $order->is_paid() || $order->get_meta( '_mb_wompi_prueba' ) ) {
		return $order->is_paid() ? 'pagado' : 'prueba';
	}
	if ( $order->has_status( 'on-hold' ) && $order->get_transaction_id() === $id_transaccion ) {
		return 'revisar'; // Ya se marcó para revisión con esta misma transacción.
	}
	$gw = mb_wompi_pasarela();
	if ( ! $gw || '' === $id_transaccion || ! preg_match( '/^[A-Za-z0-9-]{8,64}$/', $id_transaccion ) ) {
		return 'error';
	}
	// La misma transacción no puede pagar dos pedidos.
	$otros = wc_get_orders( array( 'transaction_id' => $id_transaccion, 'exclude' => array( $order->get_id() ), 'limit' => 1, 'return' => 'ids' ) );
	if ( $otros ) {
		$order->add_order_note( "Wompi: se ignoró la transacción {$id_transaccion} porque ya está en el pedido #{$otros[0]}." );
		return 'revisar';
	}

	$tx = mb_wompi_api( $gw, 'GET', '/TransaccionCompra/' . rawurlencode( $id_transaccion ) );
	if ( is_wp_error( $tx ) ) {
		error_log( "[mb-wompi] No se pudo consultar la transacción {$id_transaccion} ({$origen}): " . $tx->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return 'error';
	}

	$aprobada = ! empty( $tx['esAprobada'] );
	$real     = ! empty( $tx['esReal'] );
	$monto    = round( (float) ( $tx['monto'] ?? $tx['montoOriginal'] ?? 0 ), 2 );
	$total    = round( (float) $order->get_total(), 2 );

	if ( ! $aprobada ) {
		$mensaje = sanitize_text_field( (string) ( $tx['mensaje'] ?? '' ) );
		if ( ! $order->get_meta( '_mb_wompi_rechazo_' . $id_transaccion ) ) {
			$order->update_meta_data( '_mb_wompi_rechazo_' . $id_transaccion, '1' );
			$order->add_order_note( "Wompi: pago rechazado (transacción {$id_transaccion})" . ( $mensaje ? ": {$mensaje}" : '.' ) );
			$order->save();
		}
		return 'rechazado';
	}
	if ( abs( $monto - $total ) > 0.01 ) {
		$order->set_transaction_id( $id_transaccion );
		$order->update_status( 'on-hold', sprintf( 'Wompi: la transacción %s está aprobada por $%s pero el pedido es de $%s. Revisar antes de despachar.', $id_transaccion, number_format( $monto, 2 ), number_format( $total, 2 ) ) );
		return 'revisar';
	}

	$forma = isset( $tx['formaPago'] ) ? ' Forma de pago: ' . sanitize_text_field( (string) $tx['formaPago'] ) . '.' : '';
	$auth  = ! empty( $tx['codigoAutorizacion'] ) ? ' Autorización: ' . sanitize_text_field( (string) $tx['codigoAutorizacion'] ) . '.' : '';
	if ( ! $real ) {
		$order->set_transaction_id( $id_transaccion );
		$order->update_meta_data( '_mb_wompi_prueba', '1' );
		$order->update_status( 'on-hold', "Wompi: pago de PRUEBA aprobado (transacción {$id_transaccion}). No se cobró dinero: el negocio está en modo desarrollo en Wompi.{$auth}" );
		return 'prueba';
	}
	$order->add_order_note( 'Wompi: pago aprobado por $' . number_format( $monto, 2 ) . " (transacción {$id_transaccion}).{$auth}{$forma}" );
	$order->payment_complete( $id_transaccion );
	return 'pagado';
}

/* ================================================================== */
/* Webhook (Wompi → tienda)                                            */
/* ================================================================== */

/**
 * Lee una cabecera de la petición. Wompi firma con "wompi_hash" (con guion bajo), y Apache descarta las
 * cabeceras con guion bajo de $_SERVER; con mod_php siguen disponibles en getallheaders().
 */
function mb_wompi_cabecera( string $nombre ): string {
	$normal = static fn( string $n ): string => str_replace( '_', '-', strtolower( $n ) );
	if ( function_exists( 'getallheaders' ) ) {
		foreach ( (array) getallheaders() as $clave => $valor ) {
			if ( $normal( (string) $clave ) === $normal( $nombre ) ) {
				return (string) $valor;
			}
		}
	}
	$server = 'HTTP_' . strtoupper( str_replace( '-', '_', $nombre ) );
	return (string) ( $_SERVER[ $server ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

add_action( 'woocommerce_api_mb_wompi_webhook', static function (): void {
	$cuerpo = (string) file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$gw     = mb_wompi_pasarela();
	$firma  = strtolower( trim( mb_wompi_cabecera( 'wompi_hash' ) ) );
	if ( ! $gw || '' === $gw->api_secret() || '' === $firma || ! hash_equals( hash_hmac( 'sha256', $cuerpo, $gw->api_secret() ), $firma ) ) {
		error_log( '[mb-wompi] Webhook con firma inválida o sin credenciales.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		status_header( 401 );
		exit;
	}
	$datos      = json_decode( $cuerpo, true );
	$referencia = (string) ( $datos['EnlacePago']['IdentificadorEnlaceComercio'] ?? '' );
	$id_tx      = (string) ( $datos['IdTransaccion'] ?? '' );
	$order      = preg_match( '/^MB-(\d+)-/', $referencia, $m ) ? wc_get_order( (int) $m[1] ) : false;
	if ( ! $order || $order->get_meta( '_mb_wompi_referencia' ) !== $referencia ) {
		status_header( 200 ); // No es de esta tienda o es un intento viejo: se acepta para que Wompi no reintente.
		exit;
	}
	$resultado = mb_wompi_confirmar( $order, $id_tx, 'webhook' );
	status_header( 'error' === $resultado ? 500 : 200 );
	exit;
} );

/* ================================================================== */
/* Regreso del cliente desde Wompi                                     */
/* ================================================================== */

add_action( 'woocommerce_api_mb_wompi_retorno', static function (): void {
	// phpcs:disable WordPress.Security.NonceVerification -- la clave del pedido y la consulta a Wompi validan el regreso.
	$order = wc_get_order( absint( $_GET['pedido'] ?? 0 ) );
	$key   = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
	$id_tx = sanitize_text_field( wp_unslash( $_GET['idTransaccion'] ?? '' ) );
	// phpcs:enable
	if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) ) {
		wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
		exit;
	}
	$resultado = $id_tx ? mb_wompi_confirmar( $order, $id_tx, 'retorno' ) : 'rechazado';
	if ( 'rechazado' === $resultado ) {
		wc_add_notice( 'El pago con tarjeta no se completó. Puedes intentarlo de nuevo o elegir otro método de pago.', 'error' );
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}
	if ( in_array( $resultado, array( 'error', 'revisar' ), true ) ) {
		wc_add_notice( 'Recibimos tu regreso de Wompi pero aún no pudimos confirmar el pago. Si se te cobró, lo verás reflejado en unos minutos; si no, escríbenos por WhatsApp con tu número de pedido.', 'notice' );
	}
	if ( WC()->cart ) {
		WC()->cart->empty_cart();
	}
	wp_safe_redirect( $order->get_checkout_order_received_url() );
	exit;
} );
