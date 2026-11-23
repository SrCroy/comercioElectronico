<?php
/**
 * Tema hijo Mechanical Bull (sobre Astra).
 * Los datos de contacto y el WhatsApp se configuran en Apariencia → Personalizar → Mechanical Bull
 * (los provee el plugin mb-core).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', static function (): void {
	wp_enqueue_style(
		'mb-fonts',
		'https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'mechanical-bull', get_stylesheet_uri(), array( 'mb-fonts' ), (string) filemtime( get_stylesheet_directory() . '/style.css' ) );
}, 20 );

add_filter( 'wp_resource_hints', static function ( array $urls, string $relation ): array {
	if ( 'preconnect' === $relation ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}, 10, 2 );

/* ---------- Pie de página de marca ---------- */
add_action( 'astra_footer_before', static function (): void {
	if ( ! function_exists( 'mb_dato' ) ) {
		return;
	}
	$logo = get_stylesheet_directory_uri() . '/assets/logo-horizontal-blanco.png';
	$link = static function ( string $destino, string $txt ): string {
		if ( str_starts_with( $destino, 'wc:' ) ) {
			$url = get_permalink( wc_get_page_id( substr( $destino, 3 ) ) );
		} elseif ( str_starts_with( $destino, 'cat:' ) ) {
			$url = get_term_link( substr( $destino, 4 ), 'product_cat' );
		} else {
			$url = home_url( $destino );
		}
		if ( ! $url || is_wp_error( $url ) ) {
			return '';
		}
		return sprintf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $txt ) );
	};
	?>
	<div class="mb-footer">
		<div class="mb-footer__col mb-footer__marca">
			<img src="<?php echo esc_url( $logo ); ?>" alt="Mechanical Bull" width="220" height="66" loading="lazy">
			<p>Venta de repuestos y reparación de celulares, laptops y PC. Repuestos de calidad, precios justos y garantía real.</p>
		</div>
		<div class="mb-footer__col">
			<h4>Tienda</h4>
			<ul>
				<?php
				echo $link( 'wc:shop', 'Todos los repuestos' );          // phpcs:ignore WordPress.Security.EscapeOutput
				echo $link( 'cat:celulares', 'Celulares' ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo $link( 'cat:laptops', 'Laptops' );     // phpcs:ignore WordPress.Security.EscapeOutput
				echo $link( 'cat:pc-de-escritorio-y-gamer', 'PC y Gamer' ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</ul>
		</div>
		<div class="mb-footer__col">
			<h4>Ayuda</h4>
			<ul>
				<?php
				echo $link( '/reparaciones/', 'Reparaciones' );                 // phpcs:ignore WordPress.Security.EscapeOutput
				echo $link( '/garantias/', 'Garantías' );                      // phpcs:ignore WordPress.Security.EscapeOutput
				echo $link( '/envios-y-devoluciones/', 'Envíos y devoluciones' ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo $link( 'wc:myaccount', 'Mi cuenta' );                      // phpcs:ignore WordPress.Security.EscapeOutput
				?>
			</ul>
		</div>
		<div class="mb-footer__col">
			<h4>Contacto</h4>
			<p><?php echo esc_html( mb_dato( 'direccion' ) ); ?></p>
			<p><?php echo esc_html( mb_dato( 'horario' ) ); ?></p>
			<p><a href="<?php echo esc_url( mb_whatsapp_url() ); ?>" target="_blank" rel="noopener">WhatsApp <?php echo esc_html( mb_telefono_legible() ); ?></a></p>
		</div>
	</div>
	<?php
} );

/* ---------- Botón flotante de WhatsApp ---------- */
add_action( 'wp_footer', static function (): void {
	if ( ! function_exists( 'mb_whatsapp_url' ) ) {
		return;
	}
	$mensaje = 'Hola Mechanical Bull, quiero información.';
	if ( function_exists( 'is_product' ) && is_product() ) {
		$mensaje = sprintf( 'Hola Mechanical Bull, me interesa: %s (%s)', get_the_title(), get_permalink() );
	}
	printf(
		'<a class="mb-whatsapp" href="%s" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">%s<span>¿Dudas? Escríbenos</span></a>',
		esc_url( mb_whatsapp_url( $mensaje ) ),
		'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3z"/></svg>'
	);
} );

/* ---------- Imagen de marca para productos sin foto ---------- */
add_filter( 'woocommerce_placeholder_img_src', static fn() => get_stylesheet_directory_uri() . '/assets/placeholder.jpg' );

/* ---------- Tienda: buscador y marcas arriba del listado ---------- */
add_action( 'woocommerce_before_shop_loop', static function (): void {
	if ( ! ( is_shop() || is_product_taxonomy() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) ) ) {
		return;
	}
	echo '<div class="mb-tienda-filtros">' . do_shortcode( '[mb_buscador]' ) . do_shortcode( '[mb_marcas]' ) . '</div>';
}, 5 );
