<?php
/**
 * Datos de facturación para El Salvador en el pago:
 *   - Tipo de documento: Factura consumidor final / Comprobante de Crédito Fiscal (CCF)
 *   - Para CCF: NIT, NRC, nombre o razón social y giro (obligatorios)
 * Se guardan en el pedido, se ven en el panel y salen en los correos.
 * Además: "Departamento" y "Municipio" en lugar de Estado/Ciudad, sin código postal.
 */

defined( 'ABSPATH' ) || exit;

function mb_campos_ccf(): array {
	return array(
		'mb_nit'          => 'NIT',
		'mb_nrc'          => 'NRC',
		'mb_razon_social' => 'Nombre o razón social',
		'mb_giro'         => 'Giro o actividad económica',
	);
}

/** Etiquetas de dirección para El Salvador. */
add_filter( 'woocommerce_get_country_locale', static function ( array $locale ): array {
	$locale['SV'] = array_merge( $locale['SV'] ?? array(), array(
		'state'    => array( 'label' => 'Departamento', 'required' => true ),
		'city'     => array( 'label' => 'Municipio / Distrito', 'required' => true ),
		'postcode' => array( 'required' => false, 'hidden' => true ),
	) );
	return $locale;
} );

add_filter( 'woocommerce_checkout_fields', static function ( array $fields ): array {
	$fields['billing']['billing_phone']['label'] = 'Teléfono / WhatsApp';

	$fields['billing']['mb_tipo_documento'] = array(
		'type'     => 'select',
		'label'    => 'Tipo de comprobante',
		'required' => true,
		'class'    => array( 'form-row-wide' ),
		'options'  => array(
			'consumidor_final' => 'Factura (consumidor final)',
			'credito_fiscal'   => 'Comprobante de Crédito Fiscal (CCF)',
		),
		'default'  => 'consumidor_final',
		'priority' => 120,
	);
	$prioridad = 121;
	foreach ( mb_campos_ccf() as $clave => $label ) {
		$fields['billing'][ $clave ] = array(
			'type'     => 'text',
			'label'    => $label,
			'required' => false, // se valida solo si eligen CCF
			'class'    => array( 'form-row-wide', 'mb-ccf' ),
			'priority' => $prioridad++,
		);
	}
	return $fields;
} );

add_action( 'woocommerce_after_checkout_validation', static function ( array $data, WP_Error $errors ): void {
	if ( 'credito_fiscal' !== ( $data['mb_tipo_documento'] ?? '' ) ) {
		return;
	}
	foreach ( mb_campos_ccf() as $clave => $label ) {
		if ( '' === trim( (string) ( $data[ $clave ] ?? '' ) ) ) {
			$errors->add( 'validation', sprintf( 'Para Crédito Fiscal, el campo <strong>%s</strong> es obligatorio.', esc_html( $label ) ) );
		}
	}
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order', static function ( WC_Order $order, array $data ): void {
	$tipo = 'credito_fiscal' === ( $data['mb_tipo_documento'] ?? '' ) ? 'credito_fiscal' : 'consumidor_final';
	$order->update_meta_data( '_mb_tipo_documento', $tipo );
	if ( 'credito_fiscal' === $tipo ) {
		foreach ( array_keys( mb_campos_ccf() ) as $clave ) {
			$order->update_meta_data( '_' . $clave, sanitize_text_field( (string) ( $data[ $clave ] ?? '' ) ) );
		}
	}
}, 10, 2 );

/** Muestra/oculta los campos de CCF en el formulario de pago. */
add_action( 'wp_footer', static function (): void {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	?>
	<script>
	(function () {
		function toggle() {
			var sel = document.getElementById('mb_tipo_documento');
			if (!sel) return;
			var ccf = sel.value === 'credito_fiscal';
			document.querySelectorAll('.mb-ccf').forEach(function (row) {
				row.style.display = ccf ? '' : 'none';
				var label = row.querySelector('label');
				if (label && !label.querySelector('.required')) {
					label.insertAdjacentHTML('beforeend', ' <abbr class="required" title="obligatorio">*</abbr>');
				}
			});
		}
		document.addEventListener('change', function (e) { if (e.target && e.target.id === 'mb_tipo_documento') toggle(); });
		document.addEventListener('DOMContentLoaded', toggle);
		if (window.jQuery) jQuery(document.body).on('updated_checkout', toggle);
	})();
	</script>
	<?php
} );

/** Resumen del comprobante para el panel y los correos. */
function mb_datos_comprobante( WC_Order $order ): array {
	$tipo  = $order->get_meta( '_mb_tipo_documento' );
	$filas = array( 'Comprobante' => 'credito_fiscal' === $tipo ? 'Crédito Fiscal (CCF)' : 'Factura consumidor final' );
	if ( 'credito_fiscal' === $tipo ) {
		foreach ( mb_campos_ccf() as $clave => $label ) {
			$filas[ $label ] = (string) $order->get_meta( '_' . $clave );
		}
	}
	return $filas;
}

add_action( 'woocommerce_admin_order_data_after_billing_address', static function ( WC_Order $order ): void {
	echo '<div class="mb-comprobante"><h3>Comprobante</h3>';
	foreach ( mb_datos_comprobante( $order ) as $label => $valor ) {
		printf( '<p><strong>%s:</strong> %s</p>', esc_html( $label ), esc_html( $valor ) );
	}
	echo '</div>';
} );

add_filter( 'woocommerce_email_order_meta_fields', static function ( array $fields, bool $sent_to_admin, $order ): array {
	if ( $order instanceof WC_Order ) {
		foreach ( mb_datos_comprobante( $order ) as $label => $valor ) {
			$fields[ sanitize_title( $label ) ] = array( 'label' => $label, 'value' => $valor );
		}
	}
	return $fields;
}, 10, 3 );
