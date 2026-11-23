<?php
/**
 * Campos de negocio en cada producto:
 *
 *  - Garantía (meses): Sin garantía / 3 / 6 / 12. Se guarda también en cada línea del pedido.
 *  - Disponibilidad: En stock o Por encargo.
 *  - Días estimados de llegada (solo encargo).
 *  - Anticipo requerido % (solo encargo). Se muestra al cliente; el cobro parcial llega en una fase posterior.
 */

defined( 'ABSPATH' ) || exit;

const MB_GARANTIA_OPCIONES = array(
	'0'  => 'Sin garantía',
	'3'  => '3 meses',
	'6'  => '6 meses',
	'12' => '12 meses (1 año)',
);

function mb_get_garantia_meses( WC_Product $product ): int {
	$id    = $product->get_parent_id() ?: $product->get_id();
	$meses = get_post_meta( $id, '_mb_garantia_meses', true );
	return '' === $meses ? 6 : (int) $meses;
}

function mb_es_encargo( WC_Product $product ): bool {
	$id = $product->get_parent_id() ?: $product->get_id();
	return 'encargo' === get_post_meta( $id, '_mb_disponibilidad', true );
}

function mb_texto_encargo( WC_Product $product ): string {
	$id       = $product->get_parent_id() ?: $product->get_id();
	$dias     = (int) get_post_meta( $id, '_mb_encargo_dias', true );
	$anticipo = (int) get_post_meta( $id, '_mb_encargo_anticipo', true );

	$texto = 'Por encargo';
	if ( $dias > 0 ) {
		$texto .= sprintf( ': llega en aprox. %d días hábiles', $dias );
	}
	if ( $anticipo > 0 ) {
		$texto .= sprintf( ' · requiere %d%% de anticipo', $anticipo );
	}
	return $texto;
}

/* ---------- Administración ---------- */

add_action( 'woocommerce_product_options_general_product_data', static function (): void {
	global $post;
	$garantia = get_post_meta( $post->ID, '_mb_garantia_meses', true );

	echo '<div class="options_group mb-campos">';

	woocommerce_wp_select( array(
		'id'          => '_mb_garantia_meses',
		'label'       => 'Garantía',
		'options'     => MB_GARANTIA_OPCIONES,
		'value'       => '' === $garantia ? '6' : $garantia,
		'desc_tip'    => true,
		'description' => 'Recomendado: 12 meses para componentes (RAM, SSD, procesadores, fuentes). 6 meses para piezas de desgaste o instalación delicada (pantallas, baterías, flex, puertos).',
	) );

	woocommerce_wp_select( array(
		'id'      => '_mb_disponibilidad',
		'label'   => 'Disponibilidad',
		'options' => array(
			'stock'   => 'En stock (entrega inmediata)',
			'encargo' => 'Por encargo',
		),
	) );

	woocommerce_wp_text_input( array(
		'id'                => '_mb_encargo_dias',
		'label'             => 'Días estimados (encargo)',
		'type'              => 'number',
		'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
		'placeholder'       => '7',
	) );

	woocommerce_wp_text_input( array(
		'id'                => '_mb_encargo_anticipo',
		'label'             => 'Anticipo requerido % (encargo)',
		'type'              => 'number',
		'custom_attributes' => array( 'min' => '0', 'max' => '100', 'step' => '5' ),
		'placeholder'       => '50',
	) );

	echo '</div>';
} );

add_action( 'woocommerce_admin_process_product_object', static function ( WC_Product $product ): void {
	// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce ya verificó el nonce del formulario de producto.
	$garantia = isset( $_POST['_mb_garantia_meses'] ) ? sanitize_text_field( wp_unslash( $_POST['_mb_garantia_meses'] ) ) : '6';
	if ( ! array_key_exists( $garantia, MB_GARANTIA_OPCIONES ) ) {
		$garantia = '6';
	}
	$disponibilidad = ( isset( $_POST['_mb_disponibilidad'] ) && 'encargo' === $_POST['_mb_disponibilidad'] ) ? 'encargo' : 'stock';
	$dias           = isset( $_POST['_mb_encargo_dias'] ) ? absint( $_POST['_mb_encargo_dias'] ) : 0;
	$anticipo       = isset( $_POST['_mb_encargo_anticipo'] ) ? min( 100, absint( $_POST['_mb_encargo_anticipo'] ) ) : 0;
	// phpcs:enable

	if ( 'encargo' === $disponibilidad ) {
		$dias     = $dias ?: 7;
		$anticipo = $anticipo ?: 50;
		// Se puede comprar aunque no haya unidades: WooCommerce lo marca como "pendiente de recibir".
		$product->set_backorders( 'notify' );
		if ( ! $product->get_manage_stock() ) {
			$product->set_stock_status( 'onbackorder' );
		}
	}

	$product->update_meta_data( '_mb_garantia_meses', $garantia );
	$product->update_meta_data( '_mb_disponibilidad', $disponibilidad );
	$product->update_meta_data( '_mb_encargo_dias', $dias );
	$product->update_meta_data( '_mb_encargo_anticipo', $anticipo );
} );

/* ---------- Tienda ---------- */

/** Garantía y encargo debajo del precio en la ficha del producto. */
add_action( 'woocommerce_single_product_summary', static function (): void {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	// La garantía se muestra en el recuadro de confianza (debajo de "Añadir al carrito").
	if ( mb_es_encargo( $product ) ) {
		printf( '<div class="mb-badges"><span class="mb-badge mb-badge--encargo">%s</span></div>', esc_html( mb_texto_encargo( $product ) ) );
	}
}, 11 );

/** Etiqueta "Por encargo" en el listado de productos. */
add_action( 'woocommerce_after_shop_loop_item_title', static function (): void {
	global $product;
	if ( $product instanceof WC_Product && mb_es_encargo( $product ) ) {
		echo '<span class="mb-badge mb-badge--encargo mb-badge--small">Por encargo</span>';
	}
}, 15 );

/** Texto de disponibilidad claro para piezas por encargo. */
add_filter( 'woocommerce_get_availability_text', static function ( string $text, WC_Product $product ): string {
	if ( mb_es_encargo( $product ) && ( $product->is_on_backorder() || ! $product->is_in_stock() ) ) {
		return mb_texto_encargo( $product );
	}
	return $text;
}, 10, 2 );

/** Aviso en el carrito cuando hay piezas por encargo. */
add_action( 'woocommerce_before_cart', 'mb_aviso_encargo_carrito' );
add_action( 'woocommerce_before_checkout_form', 'mb_aviso_encargo_carrito', 5 );
function mb_aviso_encargo_carrito(): void {
	if ( ! WC()->cart ) {
		return;
	}
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( ! empty( $item['data'] ) && mb_es_encargo( $item['data'] ) ) {
			wc_print_notice( 'Tu pedido incluye piezas por encargo. Te avisaremos por WhatsApp o correo cuando lleguen. Las piezas por encargo no tienen devolución, salvo defecto de fábrica.', 'notice' );
			return;
		}
	}
}

/** Guarda la garantía en cada línea del pedido para consultarla después (también en "Mi cuenta"). */
add_action( 'woocommerce_checkout_create_order_line_item', static function ( WC_Order_Item_Product $item, string $key, array $values ): void {
	$product = $values['data'] ?? null;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$meses = mb_get_garantia_meses( $product );
	$item->add_meta_data( '_mb_garantia_meses', $meses, true );
	$item->add_meta_data( 'Garantía', $meses > 0 ? MB_GARANTIA_OPCIONES[ (string) $meses ] ?? $meses . ' meses' : 'Sin garantía', true );
	if ( mb_es_encargo( $product ) ) {
		$item->add_meta_data( 'Disponibilidad', 'Por encargo', true );
	}
}, 10, 3 );

/** Recuadro de confianza y consulta de compatibilidad debajo de "Añadir al carrito". */
add_action( 'woocommerce_after_add_to_cart_form', static function (): void {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$meses = mb_get_garantia_meses( $product );
	$items = array(
		'envio'    => array( 'Envíos a todo El Salvador', 'Domicilio en San Miguel $2.50 · resto del país $5.00' ),
		'tienda'   => array( 'Retiro gratis en tienda', 'San Miguel, Carretera al Cuco' ),
		'garantia' => array( $meses > 0 ? 'Garantía de ' . ( MB_GARANTIA_OPCIONES[ (string) $meses ] ?? $meses . ' meses' ) : 'Revisada antes de entregar', 'Por escrito, con tu pedido' ),
		'pago'     => array( 'Pagas como prefieras', 'Transferencia, Chivo Wallet o efectivo al retirar' ),
	);
	$iconos = array(
		'envio'    => '<path d="M3 6h11v9H3zM14 9h4l3 3v3h-7zM7 18a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>',
		'tienda'   => '<path d="M4 10h16v10H4zM3 5h18l-1 5H4zM10 14h4v6h-4z"/>',
		'garantia' => '<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6zm-1.2 12.2l5.4-5.4-1.4-1.4-4 4-1.8-1.8-1.4 1.4z"/>',
		'pago'     => '<path d="M3 6h18v12H3zM3 9h18v2H3zM6 14h5v2H6z"/>',
	);
	echo '<ul class="mb-confianza">';
	foreach ( $items as $k => [ $titulo, $texto ] ) {
		printf(
			'<li><svg viewBox="0 0 24 24" aria-hidden="true">%s</svg><span><strong>%s</strong>%s</span></li>',
			$iconos[ $k ], // phpcs:ignore WordPress.Security.EscapeOutput -- SVG fijo
			esc_html( $titulo ),
			esc_html( $texto )
		);
	}
	echo '</ul>';
} );

/**
 * Botón "Preguntar por WhatsApp" en la ficha de cada producto, con el nombre, precio y enlace ya escritos.
 * Va en el resumen (después de "Añadir al carrito") para que aparezca también en productos agotados,
 * que no muestran el formulario de compra.
 */
add_action( 'woocommerce_single_product_summary', static function (): void {
	global $product;
	if ( ! $product instanceof WC_Product || ! function_exists( 'mb_whatsapp_url' ) ) {
		return;
	}
	$precio  = wp_strip_all_tags( html_entity_decode( wc_price( (float) wc_get_price_to_display( $product ) ) ) );
	$mensaje = sprintf(
		"Hola Mechanical Bull, quiero preguntar por este producto:\n%s%s\n%s\n\nMi consulta: ",
		$product->get_name(),
		$product->get_price() !== '' ? ' (' . $precio . ')' : '',
		get_permalink( $product->get_id() )
	);
	printf(
		'<div class="mb-preguntar-wa"><a class="mb-boton mb-boton--whatsapp mb-boton--bloque" href="%s" target="_blank" rel="noopener">Preguntar por WhatsApp</a><small>¿Dudas de compatibilidad, precio o disponibilidad? Te atendemos al %s</small></div>',
		esc_url( mb_whatsapp_url( $mensaje ) ),
		esc_html( function_exists( 'mb_telefono_legible' ) ? mb_telefono_legible() : '' )
	);
}, 31 );
