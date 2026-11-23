<?php
/**
 * Método de pago "Chivo Wallet" (manual).
 * El cliente paga desde Chivo y envía el comprobante; la secretaria lo verifica y procesa el pedido.
 * El pedido queda "En espera" y las piezas reservadas hasta confirmar el pago.
 *
 * Wompi y PayPal se instalan con sus plugins oficiales cuando tengan las credenciales.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', static function (): void {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class MB_Gateway_Chivo extends WC_Payment_Gateway {

		public string $instructions = '';

		public function __construct() {
			$this->id                 = 'mb_chivo';
			$this->method_title       = 'Chivo Wallet';
			$this->method_description = 'Pago manual con Chivo Wallet: el cliente envía el comprobante y se verifica antes de procesar el pedido.';
			$this->has_fields         = false;
			$this->supports           = array( 'products' );

			$this->init_form_fields();
			$this->init_settings();

			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}

		public function init_form_fields(): void {
			$this->form_fields = array(
				'enabled'      => array(
					'title'   => 'Activar',
					'type'    => 'checkbox',
					'label'   => 'Aceptar pagos con Chivo Wallet',
					'default' => 'no',
				),
				'title'        => array(
					'title'   => 'Título',
					'type'    => 'text',
					'default' => 'Chivo Wallet',
				),
				'description'  => array(
					'title'   => 'Descripción en el pago',
					'type'    => 'textarea',
					'default' => 'Paga desde tu app Chivo Wallet y envíanos el comprobante por WhatsApp con tu número de pedido. Procesamos tu pedido al confirmar el pago.',
				),
				'instructions' => array(
					'title'   => 'Instrucciones (página de gracias y correo)',
					'type'    => 'textarea',
					'default' => 'Envía tu pago por Chivo Wallet y luego mándanos el comprobante por WhatsApp con tu número de pedido. Tus piezas quedan reservadas hasta confirmar el pago.',
				),
			);
		}

		public function process_payment( $order_id ): array {
			$order = wc_get_order( $order_id );
			$order->update_status( 'on-hold', 'Esperando comprobante de pago por Chivo Wallet.' );
			WC()->cart->empty_cart();
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		public function thankyou_page(): void {
			if ( $this->instructions ) {
				echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
			}
			if ( function_exists( 'mb_whatsapp_url' ) ) {
				printf(
					'<p><a class="button" href="%s" target="_blank" rel="noopener">Enviar comprobante por WhatsApp</a></p>',
					esc_url( mb_whatsapp_url( 'Hola, envío el comprobante de pago de mi pedido.' ) )
				);
			}
		}

		public function email_instructions( $order, $sent_to_admin, $plain_text = false ): void {
			if ( $this->instructions && ! $sent_to_admin && $order instanceof WC_Order && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
				echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
			}
		}
	}

	add_filter( 'woocommerce_payment_gateways', static function ( array $gateways ): array {
		$gateways[] = 'MB_Gateway_Chivo';
		return $gateways;
	} );
}, 20 );

/** Botón de WhatsApp para enviar comprobante también en transferencia bancaria. */
add_action( 'woocommerce_thankyou_bacs', static function ( $order_id ): void {
	if ( function_exists( 'mb_whatsapp_url' ) ) {
		printf(
			'<p><a class="button" href="%s" target="_blank" rel="noopener">Enviar comprobante por WhatsApp</a></p>',
			esc_url( mb_whatsapp_url( sprintf( 'Hola, envío el comprobante de transferencia del pedido #%s.', $order_id ) ) )
		);
	}
}, 20 );
