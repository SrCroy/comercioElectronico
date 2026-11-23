<?php
/**
 * Portada de Mechanical Bull construida con Elementor (widgets nativos, editable arrastrando).
 *
 *   wp mb portada                         → crea/reemplaza la portada en Elementor
 *   wp mb portada --exportar=archivo.json → además la exporta como plantilla para importar en Elementor
 *
 * También configura el "Kit" de Elementor con los colores y tipografías de la marca.
 */

defined( 'ABSPATH' ) || exit;

/** Copia una imagen del tema a la biblioteca de medios (una sola vez) y devuelve su ID. */
function mb_importar_imagen_tema( string $ruta, string $titulo ): int {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// Las imágenes en subcarpetas (categorias-v2/, productos/, elementor/) se distinguen también por carpeta.
	$carpeta = basename( dirname( $ruta ) );
	$clave   = 'mb_media_' . md5( 'assets' === $carpeta ? basename( $ruta ) : $carpeta . '/' . basename( $ruta ) );
	$id      = (int) get_option( $clave );
	if ( $id && get_post( $id ) ) {
		return $id;
	}
	if ( ! file_exists( $ruta ) ) {
		return 0;
	}
	$tmp = wp_tempnam( basename( $ruta ) );
	copy( $ruta, $tmp );
	$id = media_handle_sideload( array( 'name' => basename( $ruta ), 'tmp_name' => $tmp ), 0, $titulo );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	update_option( $clave, $id );
	return (int) $id;
}

final class MB_Portada_Elementor {

	/* ---------------- Ayudantes para armar la estructura de Elementor ---------------- */

	private static function id(): string {
		return substr( md5( uniqid( '', true ) . wp_rand() ), 0, 7 );
	}

	private static function c( array $settings, array $hijos = array() ): array {
		return array( 'id' => self::id(), 'elType' => 'container', 'isInner' => false, 'settings' => $settings, 'elements' => $hijos );
	}

	private static function w( string $tipo, array $settings ): array {
		return array( 'id' => self::id(), 'elType' => 'widget', 'widgetType' => $tipo, 'isInner' => false, 'settings' => $settings, 'elements' => array() );
	}

	private static function px( $n, string $unit = 'px' ): array {
		return array( 'unit' => $unit, 'size' => $n, 'sizes' => array() );
	}

	private static function caja( $t, $r, $b, $l, string $unit = 'px' ): array {
		return array( 'unit' => $unit, 'top' => (string) $t, 'right' => (string) $r, 'bottom' => (string) $b, 'left' => (string) $l, 'isLinked' => false );
	}

	private static function gap( int $n ): array {
		return array( 'column' => (string) $n, 'row' => (string) $n, 'isLinked' => true, 'unit' => 'px', 'size' => $n );
	}

	private static function img( string $archivo, string $titulo ): array {
		$id = mb_importar_imagen_tema( get_stylesheet_directory() . '/assets/' . $archivo, $titulo );
		return array( 'id' => $id, 'url' => $id ? (string) wp_get_attachment_url( $id ) : get_stylesheet_directory_uri() . '/assets/' . $archivo, 'alt' => $titulo, 'source' => 'library' );
	}

	private static function link( string $url, bool $nueva = false ): array {
		return array( 'url' => $url, 'is_external' => $nueva ? 'on' : '', 'nofollow' => '', 'custom_attributes' => '' );
	}

	/** Sección de ancho completo con contenido centrado (1240 px). */
	private static function seccion( array $hijos, array $extra = array() ): array {
		// e-no-lazyload: los fondos se muestran de inmediato (sin carga diferida de Elementor).
		$extra['css_classes'] = trim( ( $extra['css_classes'] ?? '' ) . ' e-no-lazyload' );
		return self::c( array_merge( array(
			'content_width'  => 'boxed',
			'boxed_width'    => self::px( 1240 ),
			'flex_direction' => 'column',
			'flex_gap'       => self::gap( 24 ),
			'padding'        => self::caja( 72, 20, 72, 20 ),
			'padding_mobile' => self::caja( 48, 16, 48, 16 ),
		), $extra ), $hijos );
	}

	/** Fila que en móvil pasa a columna. */
	private static function fila( array $hijos, array $extra = array() ): array {
		return self::c( array_merge( array(
			'content_width'         => 'full',
			'flex_direction'        => 'row',
			'flex_direction_mobile' => 'column',
			'flex_wrap'             => 'nowrap',
			'flex_gap'              => self::gap( 24 ),
			'padding'               => self::caja( 0, 0, 0, 0 ),
		), $extra ), $hijos );
	}

	/** Columna con ancho en %. */
	private static function col( int $ancho, array $hijos, array $extra = array() ): array {
		return self::c( array_merge( array(
			'content_width'  => 'full',
			'width'          => self::px( $ancho, '%' ),
			'width_tablet'   => self::px( 100, '%' ),
			'width_mobile'   => self::px( 100, '%' ),
			'flex_direction' => 'column',
			'flex_gap'       => self::gap( 16 ),
			'padding'        => self::caja( 0, 0, 0, 0 ),
		), $extra ), $hijos );
	}

	private static function etiqueta( string $texto, string $clase = '' ): array {
		return self::w( 'heading', array( 'title' => $texto, 'header_size' => 'p', '_css_classes' => trim( 'mb-el-etiqueta ' . $clase ) ) );
	}

	private static function titulo( string $texto, string $tag = 'h2', string $clase = '' ): array {
		return self::w( 'heading', array( 'title' => $texto, 'header_size' => $tag, '_css_classes' => trim( 'mb-el-titulo ' . $clase ) ) );
	}

	private static function texto( string $html, string $clase = '' ): array {
		return self::w( 'text-editor', array( 'editor' => $html, '_css_classes' => trim( 'mb-el-texto ' . $clase ) ) );
	}

	private static function boton( string $texto, string $url, string $estilo = 'rojo', bool $nueva = false, string $icono = '' ): array {
		$s = array(
			'text'         => $texto,
			'link'         => self::link( $url, $nueva ),
			'_css_classes' => 'mb-el-boton mb-el-boton--' . $estilo,
		);
		if ( $icono ) {
			$s['selected_icon'] = array( 'value' => $icono, 'library' => str_starts_with( $icono, 'fab' ) ? 'fa-brands' : 'fa-solid' );
			$s['icon_align']    = 'left';
			$s['icon_indent']   = self::px( 8 );
		}
		return self::w( 'button', $s );
	}

	private static function shortcode( string $sc, string $clase = '' ): array {
		return self::w( 'shortcode', array( 'shortcode' => $sc, '_css_classes' => $clase ) );
	}

	private static function caja_icono( string $icono, string $titulo, string $texto, string $clase = 'mb-el-beneficio' ): array {
		return self::w( 'icon-box', array(
			'selected_icon'    => array( 'value' => $icono, 'library' => 'fa-solid' ),
			'view'             => 'stacked',
			'shape'            => 'square',
			'position'         => 'left',
			'title_text'       => $titulo,
			'description_text' => $texto,
			'title_size'       => 'h3',
			'_css_classes'     => $clase,
		) );
	}

	/* ---------------- Contenido de la portada ---------------- */

	public static function estructura(): array {
		$tienda     = get_permalink( wc_get_page_id( 'shop' ) );
		$cat_link   = static function ( string $slug ) use ( $tienda ): string {
			$l = get_term_link( $slug, 'product_cat' );
			return is_wp_error( $l ) ? $tienda : $l;
		};
		$cat_ids    = implode( ',', array_filter( array_map(
			static fn( $s ) => get_term_by( 'slug', $s, 'product_cat' )->term_id ?? 0,
			array( 'celulares', 'laptops', 'pc-de-escritorio-y-gamer', 'herramientas-y-accesorios' )
		) ) );
		$wa         = static fn( string $m ) => function_exists( 'mb_whatsapp_url' ) ? mb_whatsapp_url( $m ) : '#';
		$buscar     = static fn( string $q ) => add_query_arg( array( 's' => $q, 'post_type' => 'product' ), home_url( '/' ) );

		$chips = sprintf(
			'<span>Populares:</span> <a href="%s">iPhone</a> <a href="%s">Samsung Galaxy</a> <a href="%s">Xiaomi Redmi</a> <a href="%s">Memoria RAM</a> <a href="%s">SSD</a>',
			esc_url( $buscar( 'iPhone' ) ), esc_url( $buscar( 'Galaxy' ) ), esc_url( $buscar( 'Redmi' ) ), esc_url( $buscar( 'RAM' ) ), esc_url( $buscar( 'SSD' ) )
		);

		return array(

			/* 1. Portada */
			self::seccion( array(
				self::fila( array(
					self::col( 56, array(
						self::etiqueta( 'Repuestos · Reparación · Garantía real', 'mb-el-etiqueta--linea' ),
						self::titulo( 'Tu equipo vuelve <span>a la vida.</span>', 'h1', 'mb-el-h1' ),
						self::texto( '<p>Repuestos de calidad para celulares Android y iPhone, laptops y PC Gamer. Y si prefieres que lo reparemos nosotros, tráelo al local o envíalo desde cualquier parte del país.</p>', 'mb-el-lead' ),
						self::shortcode( '[mb_buscador]', 'mb-el-buscador' ),
						self::texto( '<p>' . $chips . '</p>', 'mb-el-chips' ),
					), array( 'flex_justify_content' => 'center' ) ),
					self::col( 44, array(
						self::w( 'image', array( 'image' => self::img( 'elementor/hero-toro.png', 'Mechanical Bull' ), 'image_size' => 'full', '_css_classes' => 'mb-el-hero-img' ) ),
						self::w( 'html', array( 'html' => '<div class="mb-flotante mb-flotante--1"><strong>Hasta 12 meses</strong><span>de garantía por escrito</span></div><div class="mb-flotante mb-flotante--2"><strong>Diagnóstico gratis</strong><span>si dejas tu equipo para reparar</span></div><div class="mb-flotante mb-flotante--3"><strong>Envíos</strong><span>a los 14 departamentos</span></div>', '_css_classes' => 'mb-el-flotantes' ) ),
					), array( 'flex_justify_content' => 'center', 'css_classes' => 'mb-el-hero-visual' ) ),
				), array( 'flex_align_items' => 'center', 'flex_gap' => self::gap( 40 ) ) ),
			), array(
				'content_width'           => 'boxed',
				'padding'                 => self::caja( 80, 20, 110, 20 ),
				'padding_mobile'          => self::caja( 32, 16, 80, 16 ),
				'background_background'   => 'classic',
				'background_color'        => '#0E0E0E',
				'css_classes'             => 'mb-el-hero e-no-lazyload',
			) ),

			/* 2. Beneficios */
			self::seccion( array(
				self::fila( array(
					self::caja_icono( 'fas fa-shield-alt', 'Garantía real', 'Hasta 12 meses, por escrito' ),
					self::caja_icono( 'fas fa-truck', 'Envíos a todo el país', 'O retira gratis en San Miguel' ),
					self::caja_icono( 'fas fa-wallet', 'Paga como prefieras', 'Transferencia, Chivo o efectivo' ),
					self::caja_icono( 'fas fa-tools', 'Técnicos con experiencia', 'Te decimos la verdad sobre tu equipo' ),
				), array( 'flex_wrap_tablet' => 'wrap', 'css_classes' => 'mb-el-beneficios', 'flex_gap' => self::gap( 0 ) ) ),
			), array( 'padding' => self::caja( 0, 20, 0, 20 ), 'padding_mobile' => self::caja( 0, 16, 0, 16 ), 'css_classes' => 'mb-el-sube' ) ),

			/* 3. Categorías */
			self::seccion( array(
				self::etiqueta( 'Catálogo', 'mb-el-centro' ),
				self::titulo( 'Busca por tipo de equipo', 'h2', 'mb-el-centro' ),
				self::shortcode( '[product_categories ids="' . $cat_ids . '" columns="4" hide_empty="0" orderby="include"]', 'mb-el-categorias' ),
			), array( 'flex_gap' => self::gap( 8 ) ) ),

			/* 4. Banners */
			self::seccion( array(
				self::fila( array(
					self::col( 50, array(
						self::c( array( 'content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => self::gap( 12 ), 'padding' => self::caja( 0, 0, 0, 0 ), 'width' => self::px( 58, '%' ), 'width_mobile' => self::px( 100, '%' ), 'css_classes' => 'mb-el-banner-texto' ), array(
							self::etiqueta( 'PC y Gamer', 'mb-el-etiqueta--clara' ),
							self::titulo( 'Arma o mejora tu PC', 'h3', 'mb-el-titulo--blanco' ),
							self::texto( '<p>Tarjetas de video, RAM, SSD, fuentes y más. Lo que no tengamos en stock, lo traemos por encargo.</p>', 'mb-el-texto--claro' ),
							self::boton( 'Ver PC y Gamer', $cat_link( 'pc-de-escritorio-y-gamer' ), 'blanco' ),
						) ),
						self::w( 'image', array( 'image' => self::img( 'elementor/gpu-sin-fondo.png', 'Tarjeta de video' ), 'image_size' => 'full', '_css_classes' => 'mb-el-banner-img' ) ),
					), array( 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_align_items' => 'center', 'padding' => self::caja( 36, 36, 36, 36 ), 'padding_mobile' => self::caja( 28, 24, 28, 24 ), 'css_classes' => 'mb-el-banner mb-el-banner--negro e-no-lazyload' ) ),
					self::col( 50, array(
						self::c( array( 'content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => self::gap( 12 ), 'padding' => self::caja( 0, 0, 0, 0 ), 'width' => self::px( 62, '%' ), 'width_mobile' => self::px( 100, '%' ), 'css_classes' => 'mb-el-banner-texto' ), array(
							self::etiqueta( 'Celulares', 'mb-el-etiqueta--blanca' ),
							self::titulo( 'Pantallas para tu celular', 'h3', 'mb-el-titulo--blanco' ),
							self::texto( '<p>OLED, Incell y originales para iPhone, Samsung, Xiaomi y más. Te decimos la diferencia sin rodeos.</p>', 'mb-el-texto--claro' ),
							self::boton( 'Ver pantallas', $cat_link( 'celulares-pantallas' ), 'blanco' ),
						) ),
						self::w( 'image', array( 'image' => self::img( 'elementor/telefono-sin-fondo.png', 'Pantalla de celular' ), 'image_size' => 'full', '_css_classes' => 'mb-el-banner-img mb-el-banner-img--tel' ) ),
					), array( 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_align_items' => 'center', 'padding' => self::caja( 36, 36, 36, 36 ), 'padding_mobile' => self::caja( 28, 24, 28, 24 ), 'css_classes' => 'mb-el-banner mb-el-banner--rojo e-no-lazyload' ) ),
				) ),
			), array( 'padding' => self::caja( 0, 20, 0, 20 ), 'padding_mobile' => self::caja( 0, 16, 0, 16 ) ) ),

			/* 5. Marcas */
			self::seccion( array(
				self::etiqueta( 'Marcas', 'mb-el-centro' ),
				self::titulo( 'Repuestos para las marcas que usas', 'h2', 'mb-el-centro' ),
				self::shortcode( '[mb_marcas]' ),
			), array( 'flex_gap' => self::gap( 8 ) ) ),

			/* 6. Novedades */
			self::seccion( array(
				self::fila( array(
					self::c( array( 'content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => self::gap( 4 ), 'padding' => self::caja( 0, 0, 0, 0 ) ), array(
						self::etiqueta( 'Novedades' ),
						self::titulo( 'Lo más nuevo en la tienda' ),
					) ),
					self::boton( 'Ver todos los repuestos →', $tienda, 'texto' ),
				), array( 'flex_justify_content' => 'space-between', 'flex_align_items' => 'flex-end', 'flex_direction_mobile' => 'column', 'flex_align_items_mobile' => 'flex-start' ) ),
				self::shortcode( '[products limit="8" columns="4" orderby="date" order="DESC"]' ),
			), array( 'padding' => self::caja( 24, 20, 72, 20 ) ) ),

			/* 7. Taller */
			self::seccion( array(
				self::fila( array(
					self::col( 55, array(
						self::etiqueta( 'Taller de reparación', 'mb-el-etiqueta--clara' ),
						self::titulo( '¿Se arruinó tu equipo? Nosotros lo reparamos.', 'h2', 'mb-el-titulo--blanco' ),
						self::texto( '<p>Tráelo al local o envíalo desde tu departamento. Te damos el diagnóstico, tú apruebas el precio y te lo devolvemos funcionando, con garantía.</p>', 'mb-el-texto--claro' ),
						self::fila( array(
							self::w( 'icon-list', array( 'icon_list' => array(
								array( '_id' => self::id(), 'text' => 'Cambio de pantalla', 'selected_icon' => array( 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ) ),
								array( '_id' => self::id(), 'text' => 'Cambio de batería', 'selected_icon' => array( 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ) ),
								array( '_id' => self::id(), 'text' => 'Puerto de carga', 'selected_icon' => array( 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ) ),
							), '_css_classes' => 'mb-el-servicios' ) ),
							self::w( 'icon-list', array( 'icon_list' => array(
								array( '_id' => self::id(), 'text' => 'Equipos mojados', 'selected_icon' => array( 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ) ),
								array( '_id' => self::id(), 'text' => 'Reparación de placa', 'selected_icon' => array( 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ) ),
								array( '_id' => self::id(), 'text' => 'Laptops y PC Gamer', 'selected_icon' => array( 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ) ),
							), '_css_classes' => 'mb-el-servicios' ) ),
						), array( 'flex_gap' => self::gap( 40 ), 'flex_direction_mobile' => 'column', 'flex_gap_mobile' => self::gap( 8 ) ) ),
						self::fila( array(
							self::boton( 'Cotizar mi reparación', $wa( 'Hola Mechanical Bull, quiero cotizar una reparación. Equipo: , Modelo: , Falla: ' ), 'verde', true, 'fab fa-whatsapp' ),
							self::boton( 'Cómo funciona', home_url( '/reparaciones/' ), 'borde' ),
						), array( 'flex_gap' => self::gap( 12 ), 'flex_direction_mobile' => 'row', 'flex_wrap_mobile' => 'wrap' ) ),
					) ),
					self::col( 45, array(
						self::caja_icono( 'fas fa-comments', '1. Nos cuentas la falla', 'Por WhatsApp, con marca y modelo.', 'mb-el-paso' ),
						self::caja_icono( 'fas fa-search', '2. Diagnóstico', 'Gratis si dejas tu equipo para reparar.', 'mb-el-paso' ),
						self::caja_icono( 'fas fa-handshake', '3. Tú decides', 'No reparamos nada sin tu autorización.', 'mb-el-paso' ),
						self::caja_icono( 'fas fa-check-circle', '4. Listo, con garantía', 'Retiras en el local o te lo enviamos.', 'mb-el-paso' ),
					), array( 'flex_gap' => self::gap( 12 ) ) ),
				), array( 'flex_align_items' => 'center', 'flex_gap' => self::gap( 48 ) ) ),
			), array(
				'padding'               => self::caja( 64, 48, 64, 48 ),
				'padding_mobile'        => self::caja( 36, 22, 36, 22 ),
				'background_background' => 'classic',
				'background_color'      => '#0E0E0E',
				'css_classes'           => 'mb-el-taller e-no-lazyload',
			) ),

			/* 8. Cifras */
			self::seccion( array(
				self::fila( array(
					self::w( 'counter', array( 'starting_number' => 0, 'ending_number' => 12, 'suffix' => '', 'title' => 'meses de garantía', '_css_classes' => 'mb-el-cifra' ) ),
					self::w( 'counter', array( 'starting_number' => 0, 'ending_number' => 14, 'suffix' => '', 'title' => 'departamentos con envío', '_css_classes' => 'mb-el-cifra' ) ),
					self::w( 'counter', array( 'starting_number' => 0, 'ending_number' => 48, 'suffix' => ' h', 'title' => 'de reserva al retirar en tienda', '_css_classes' => 'mb-el-cifra' ) ),
					self::w( 'counter', array( 'starting_number' => 0, 'ending_number' => 100, 'suffix' => '%', 'title' => 'honestos con tu equipo', '_css_classes' => 'mb-el-cifra' ) ),
				), array( 'flex_direction_mobile' => 'row', 'flex_wrap_mobile' => 'wrap', 'css_classes' => 'mb-el-cifras' ) ),
			) ),

			/* 9. Preguntas frecuentes */
			self::seccion( array(
				self::fila( array(
					self::col( 38, array(
						self::etiqueta( 'Preguntas frecuentes' ),
						self::titulo( 'Lo que más nos preguntan' ),
						self::texto( '<p>¿Tienes otra duda? Escríbenos y te respondemos rápido, sin rodeos.</p>' ),
						self::boton( 'Escríbenos por WhatsApp', $wa( 'Hola Mechanical Bull, tengo una pregunta: ' ), 'verde', true, 'fab fa-whatsapp' ),
					) ),
					self::col( 62, array(
						self::w( 'accordion', array(
							'tabs'         => array(
								array( '_id' => self::id(), 'tab_title' => '¿Cuánto tarda una reparación?', 'tab_content' => '<p>Los cambios de pantalla, batería y puerto de carga normalmente quedan el mismo día o al día siguiente si tenemos la pieza. Reparaciones de placa o equipos mojados pueden tardar de 2 a 5 días hábiles. Siempre te damos el tiempo estimado antes de empezar.</p>' ),
								array( '_id' => self::id(), 'tab_title' => '¿Qué diferencia hay entre una pantalla OLED e Incell?', 'tab_content' => '<p>La OLED tiene colores y brillo muy cercanos a la original y consume menos batería. La Incell es más económica: funciona bien pero los colores son menos intensos. Te explicamos cuál conviene para tu equipo y tu presupuesto.</p>' ),
								array( '_id' => self::id(), 'tab_title' => '¿Puedo enviar mi equipo desde otro departamento?', 'tab_content' => '<p>Sí. Escríbenos antes de enviarlo para darte tu número de orden. Al recibirlo te mandamos fotos de cómo llegó, te enviamos el presupuesto y, cuando esté listo, te lo devolvemos por courier.</p>' ),
								array( '_id' => self::id(), 'tab_title' => '¿Qué cubre la garantía?', 'tab_content' => '<p>Cubre defectos de la pieza y del trabajo realizado: 12 meses en componentes como RAM y SSD, y 6 meses en pantallas, baterías y puertos. No cubre golpes, humedad ni equipos abiertos por terceros.</p>' ),
								array( '_id' => self::id(), 'tab_title' => '¿Venden a técnicos y talleres?', 'tab_content' => '<p>Sí. Si eres técnico o tienes un taller, escríbenos para crear tu cuenta de cliente técnico o mayorista con precios especiales.</p>' ),
							),
							'_css_classes' => 'mb-el-faq',
						) ),
					) ),
				), array( 'flex_gap' => self::gap( 48 ) ) ),
			) ),

			/* 10. Llamado final */
			self::seccion( array(
				self::fila( array(
					self::c( array( 'content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => self::gap( 6 ), 'padding' => self::caja( 0, 0, 0, 0 ) ), array(
						self::titulo( '¿No encuentras tu pieza?', 'h2', 'mb-el-titulo--blanco' ),
						self::texto( '<p>Muchas piezas las traemos por encargo. Escríbenos el modelo de tu equipo y te cotizamos.</p>', 'mb-el-texto--claro' ),
					) ),
					self::boton( 'Cotizar por WhatsApp', $wa( 'Hola Mechanical Bull, busco una pieza para mi equipo: ' ), 'blanco', true, 'fab fa-whatsapp' ),
				), array( 'flex_justify_content' => 'space-between', 'flex_align_items' => 'center', 'flex_align_items_mobile' => 'flex-start' ) ),
			), array(
				'padding'               => self::caja( 44, 44, 44, 44 ),
				'padding_mobile'        => self::caja( 30, 22, 30, 22 ),
				'margin'                => self::caja( 0, 0, 72, 0 ),
				'margin_mobile'         => self::caja( 0, 0, 48, 0 ),
				'background_background' => 'classic',
				'background_color'      => '#FF0200',
				'css_classes'           => 'mb-el-cta e-no-lazyload',
			) ),
		);
	}

	/** Marca los contenedores anidados como "isInner" (lo espera el editor de Elementor). */
	private static function marcar_internos( array $elementos, bool $interno = false ): array {
		foreach ( $elementos as &$e ) {
			$e['isInner'] = $interno && 'container' === $e['elType'];
			if ( ! empty( $e['elements'] ) ) {
				$e['elements'] = self::marcar_internos( $e['elements'], true );
			}
		}
		return $elementos;
	}

	/** Colores y tipografías de la marca en el Kit de Elementor (aparecen en el selector de colores del editor). */
	public static function configurar_kit(): void {
		update_option( 'elementor_disable_color_schemes', 'yes' );
		update_option( 'elementor_disable_typography_schemes', 'yes' );
		update_option( 'elementor_load_fa4_shim', 'yes' );
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) {
			return;
		}
		$ajustes = (array) get_post_meta( $kit_id, '_elementor_page_settings', true );
		$ajustes['system_colors'] = array(
			array( '_id' => 'primary', 'title' => 'Rojo Mechanical Bull', 'color' => '#FF0200' ),
			array( '_id' => 'secondary', 'title' => 'Negro', 'color' => '#0E0E0E' ),
			array( '_id' => 'text', 'title' => 'Texto', 'color' => '#2B2B2B' ),
			array( '_id' => 'accent', 'title' => 'Verde WhatsApp', 'color' => '#25D366' ),
		);
		$ajustes['custom_colors'] = array(
			array( '_id' => 'mbfondo', 'title' => 'Fondo', 'color' => '#F6F5F2' ),
			array( '_id' => 'mbgris', 'title' => 'Gris', 'color' => '#8A8782' ),
			array( '_id' => 'mbgrisclaro', 'title' => 'Gris claro', 'color' => '#E4E2DD' ),
			array( '_id' => 'mbblanco', 'title' => 'Blanco', 'color' => '#FFFFFF' ),
		);
		$ajustes['system_typography'] = array(
			array( '_id' => 'primary', 'title' => 'Títulos', 'typography_typography' => 'custom', 'typography_font_family' => 'Fredoka', 'typography_font_weight' => '600' ),
			array( '_id' => 'secondary', 'title' => 'Subtítulos', 'typography_typography' => 'custom', 'typography_font_family' => 'Fredoka', 'typography_font_weight' => '500' ),
			array( '_id' => 'text', 'title' => 'Texto', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter', 'typography_font_weight' => '400' ),
			array( '_id' => 'accent', 'title' => 'Botones', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter', 'typography_font_weight' => '600' ),
		);
		$ajustes['container_width'] = array( 'unit' => 'px', 'size' => 1240, 'sizes' => array() );
		update_post_meta( $kit_id, '_elementor_page_settings', $ajustes );
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/** Guarda la portada en la página "Inicio" como documento de Elementor. Devuelve el ID de la página. */
	public static function construir(): int {
		$pagina = get_page_by_path( 'inicio' );
		$id     = $pagina ? $pagina->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Inicio', 'post_name' => 'inicio' ) );
		$datos  = self::marcar_internos( self::estructura() );

		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		// Astra: sin título, ancho completo, sin barra lateral.
		update_post_meta( $id, 'site-post-title', 'disabled' );
		update_post_meta( $id, 'ast-title-bar-display', 'disabled' );
		update_post_meta( $id, 'ast-site-content-layout', 'full-width-container' );
		update_post_meta( $id, 'site-sidebar-layout', 'no-sidebar' );

		$documento = \Elementor\Plugin::$instance->documents->get( $id, false );
		$documento->save( array(
			'elements' => $datos,
			'settings' => array( 'hide_title' => 'yes', 'template' => 'elementor_header_footer' ),
		) );

		// No se fija como portada: la portada del sitio es la Tienda (ver MB_CLI::paginas()).
		return (int) $id;
	}

	/** Exporta la portada como plantilla .json (Elementor → Plantillas → Importar). */
	public static function exportar( string $archivo ): void {
		$plantilla = array(
			'title'         => 'Mechanical Bull — Portada',
			'type'          => 'page',
			'version'       => '0.4',
			'page_settings' => array( 'hide_title' => 'yes', 'template' => 'elementor_header_footer' ),
			'content'       => self::marcar_internos( self::estructura() ),
		);
		file_put_contents( $archivo, wp_json_encode( $plantilla, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Crea la portada de Mechanical Bull en Elementor (reemplaza el contenido de la página Inicio).
	 *
	 * ## OPTIONS
	 * [--rehacer]
	 * : Reemplaza la portada aunque ya la hayas editado en Elementor (se pierden esos cambios).
	 *
	 * [--exportar=<archivo>]
	 * : Además guarda la portada como plantilla .json para importarla en Elementor.
	 *
	 * ## EXAMPLES
	 *     wp mb portada
	 */
	WP_CLI::add_command( 'mb portada', static function ( array $args, array $assoc ): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			WP_CLI::error( 'Elementor no está activo. Instálalo con: wp plugin install elementor --activate' );
		}
		// Elementor limpia el HTML si no hay un administrador: se ejecuta como el primer administrador.
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		if ( $admins ) {
			wp_set_current_user( (int) $admins[0] );
		}
		MB_Portada_Elementor::configurar_kit();
		$inicio = get_page_by_path( 'inicio' );
		if ( $inicio && 'builder' === get_post_meta( $inicio->ID, '_elementor_edit_mode', true ) && empty( $assoc['rehacer'] ) ) {
			if ( ! empty( $assoc['exportar'] ) ) {
				MB_Portada_Elementor::exportar( $assoc['exportar'] );
				WP_CLI::log( 'Plantilla exportada en ' . $assoc['exportar'] );
			}
			WP_CLI::success( 'La portada ya está hecha en Elementor; no se tocó. Usa --rehacer para reemplazarla.' );
			return;
		}
		$id = MB_Portada_Elementor::construir();
		if ( ! empty( $assoc['exportar'] ) ) {
			MB_Portada_Elementor::exportar( $assoc['exportar'] );
			WP_CLI::log( 'Plantilla exportada en ' . $assoc['exportar'] );
		}
		WP_CLI::success( "Portada de Elementor lista (página #{$id}). Edítala en Páginas → Inicio → Editar con Elementor." );
	} );
}
