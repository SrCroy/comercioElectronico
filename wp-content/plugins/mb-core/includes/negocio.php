<?php
/**
 * Datos del negocio (WhatsApp, dirección, horario, correo) editables en
 * Apariencia → Personalizar → Mechanical Bull, y shortcodes para usarlos en páginas:
 *
 *   [mb_whatsapp texto="Escríbenos" mensaje="Hola..."]   botón de WhatsApp
 *   [mb_direccion]  [mb_horario]  [mb_email]  [mb_telefono]
 *   [mb_mapa]                                              mapa de Google del local
 */

defined( 'ABSPATH' ) || exit;

function mb_dato( string $clave ): string {
	$defaults = array(
		'whatsapp'  => '50300000000',
		'direccion' => 'Km 144, Carretera al Cuco, Cantón El Jute, San Miguel, El Salvador',
		'horario'   => 'Lunes a viernes 8:00 a. m. – 5:00 p. m. · Sábado 8:00 a. m. – 12:00 m.',
		'email'     => (string) get_option( 'admin_email' ),
		'mapa'      => 'Facultad Multidisciplinaria Oriental UES, San Miguel, El Salvador',
	);
	$valor = get_option( 'mb_' . $clave, '' );
	return '' !== $valor ? (string) $valor : ( $defaults[ $clave ] ?? '' );
}

function mb_whatsapp_url( string $mensaje = '' ): string {
	$url = 'https://wa.me/' . preg_replace( '/\D+/', '', mb_dato( 'whatsapp' ) );
	return $mensaje ? $url . '?text=' . rawurlencode( $mensaje ) : $url;
}

/** Teléfono legible: 50371234567 → +503 7123-4567 */
function mb_telefono_legible(): string {
	$n = preg_replace( '/\D+/', '', mb_dato( 'whatsapp' ) );
	if ( 11 === strlen( $n ) && str_starts_with( $n, '503' ) ) {
		return sprintf( '+503 %s-%s', substr( $n, 3, 4 ), substr( $n, 7 ) );
	}
	return '+' . $n;
}

/* ---------- Personalizador ---------- */
add_action( 'customize_register', static function ( WP_Customize_Manager $c ): void {
	$c->add_section( 'mb_negocio', array(
		'title'       => 'Mechanical Bull',
		'description' => 'Datos de contacto que aparecen en el pie de página, la página de Contacto y el botón de WhatsApp.',
		'priority'    => 25,
	) );
	$campos = array(
		'whatsapp'  => array( 'WhatsApp del negocio', 'Con código de país, solo números. Ej.: 50371234567', 'text' ),
		'direccion' => array( 'Dirección', '', 'textarea' ),
		'horario'   => array( 'Horario de atención', '', 'textarea' ),
		'email'     => array( 'Correo de contacto', '', 'email' ),
		'mapa'      => array( 'Ubicación para el mapa', 'Texto que se busca en Google Maps', 'text' ),
	);
	foreach ( $campos as $clave => [ $label, $desc, $tipo ] ) {
		$c->add_setting( 'mb_' . $clave, array(
			'type'              => 'option',
			'default'           => mb_dato( $clave ),
			'sanitize_callback' => 'whatsapp' === $clave
				? static fn( $v ) => preg_replace( '/\D+/', '', (string) $v )
				: ( 'email' === $clave ? 'sanitize_email' : 'sanitize_textarea_field' ),
		) );
		$c->add_control( 'mb_' . $clave, array(
			'label'       => $label,
			'description' => $desc,
			'section'     => 'mb_negocio',
			'type'        => $tipo,
		) );
	}
} );

/* ---------- Shortcodes ---------- */
add_shortcode( 'mb_whatsapp', static function ( $atts ): string {
	$atts = shortcode_atts( array(
		'texto'   => 'Escríbenos por WhatsApp',
		'mensaje' => 'Hola Mechanical Bull, quiero información.',
	), $atts );
	return sprintf(
		'<a class="mb-boton mb-boton--whatsapp" href="%s" target="_blank" rel="noopener">%s</a>',
		esc_url( mb_whatsapp_url( $atts['mensaje'] ) ),
		esc_html( $atts['texto'] )
	);
} );
add_shortcode( 'mb_direccion', static fn() => esc_html( mb_dato( 'direccion' ) ) );
add_shortcode( 'mb_horario', static fn() => esc_html( mb_dato( 'horario' ) ) );
add_shortcode( 'mb_email', static fn() => sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( mb_dato( 'email' ) ) ) );
add_shortcode( 'mb_telefono', static fn() => esc_html( mb_telefono_legible() ) );
add_shortcode( 'mb_mapa', static function (): string {
	return sprintf(
		'<div class="mb-mapa"><iframe src="%s" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Ubicación de Mechanical Bull"></iframe></div>',
		esc_url( 'https://maps.google.com/maps?q=' . rawurlencode( mb_dato( 'mapa' ) ) . '&z=15&output=embed' )
	);
} );

/**
 * [mb_buscador placeholder="..."]  Buscador de repuestos por modelo (busca también en "Marcas y modelos").
 */
add_shortcode( 'mb_buscador', static function ( $atts ): string {
	$atts = shortcode_atts( array( 'placeholder' => 'Escribe tu modelo: iPhone 13, Galaxy A54, RTX 4060…' ), $atts );
	return sprintf(
		'<form class="mb-buscador" role="search" method="get" action="%1$s">
			<label class="screen-reader-text" for="%4$s">Buscar repuestos</label>
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 3a7.5 7.5 0 0 1 5.9 12.1l4.7 4.8-1.4 1.4-4.8-4.7A7.5 7.5 0 1 1 10.5 3zm0 2a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11z"/></svg>
			<input id="%4$s" type="search" name="s" value="%2$s" placeholder="%3$s" autocomplete="off">
			<input type="hidden" name="post_type" value="product">
			<button type="submit">Buscar</button>
		</form>',
		esc_url( home_url( '/' ) ),
		esc_attr( get_search_query() ),
		esc_attr( $atts['placeholder'] ),
		esc_attr( wp_unique_id( 'mb-buscar-' ) )
	);
} );

/**
 * [mb_marcas]  Botones con las marcas (nivel superior de "Marcas y modelos").
 */
add_shortcode( 'mb_marcas', static function (): string {
	$marcas = get_terms( array(
		'taxonomy'   => 'mb_compatible',
		'parent'     => 0,
		'hide_empty' => false,
		'orderby'    => 'name',
	) );
	if ( is_wp_error( $marcas ) || ! $marcas ) {
		return '';
	}
	$html = '<div class="mb-marcas">';
	foreach ( $marcas as $m ) {
		$html .= sprintf( '<a class="mb-marca" href="%s">%s</a>', esc_url( get_term_link( $m ) ), esc_html( $m->name ) );
	}
	return $html . '</div>';
} );

/** [mb_topbar_whatsapp]  Enlace de WhatsApp para la franja superior de la cabecera (se edita el número en Personalizar). */
add_shortcode( 'mb_topbar_whatsapp', static function (): string {
	return sprintf(
		'<a class="mb-topbar-wa" href="%s" target="_blank" rel="noopener">WhatsApp %s</a>',
		esc_url( mb_whatsapp_url() ),
		esc_html( mb_telefono_legible() )
	);
} );
