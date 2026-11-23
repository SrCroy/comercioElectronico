<?php
/**
 * Comandos WP-CLI de Mechanical Bull.
 *
 *   wp mb instalar          → configura toda la tienda (se puede repetir sin duplicar nada)
 *   wp mb demo              → crea productos de ejemplo
 *   wp mb demo --borrar     → borra los productos de ejemplo
 */

defined( 'ABSPATH' ) || exit;

class MB_CLI {

	/** Si es true, se reescriben páginas, menú e imágenes de categorías. */
	private bool $rehacer = false;

	/**
	 * Versión del contenido de cada página de contenido/*.html (las que no están valen 1).
	 * Súbela al cambiar el archivo para que `wp mb instalar` (en cada despliegue) la reescriba en el servidor.
	 */
	private const VERSION_PAGINAS = array(
		'reparaciones' => 2,
	);

	/** Imagen de cada producto de ejemplo (en assets/productos del tema). */
	private const IMAGENES_DEMO = array(
		'Pantalla iPhone 13 OLED'                     => 'pantalla-oled.jpg',
		'Pantalla iPhone 13 Incell'                   => 'pantalla-incell.jpg',
		'Batería Samsung Galaxy A54'                  => 'bateria.jpg',
		'Puerto de carga Redmi Note 12'               => 'puerto-carga.jpg',
		'Memoria RAM DDR4 16 GB 3200 MHz para laptop' => 'ram.jpg',
		'SSD NVMe M.2 1 TB'                           => 'ssd.jpg',
		'Tarjeta de video RTX 4060 8 GB'              => 'gpu.jpg',
		'Kit de desarmadores de precisión 24 en 1'    => 'kit-herramientas.jpg',
	);

	/**
	 * Configura toda la tienda: datos del negocio, impuestos, envíos, pagos, páginas, menú, logos y catálogo.
	 *
	 * ## OPTIONS
	 * [--rehacer]
	 * : Vuelve a escribir el contenido de las páginas de Mechanical Bull (Inicio, Reparaciones, Garantías,
	 *   Envíos, Contacto), el menú principal y las imágenes de categorías con la versión actual del plugin.
	 *   Úsalo después de actualizar el diseño. Borra los cambios que hayas hecho a mano en esas páginas.
	 *
	 * ## EXAMPLES
	 *     wp mb instalar
	 *     wp mb instalar --rehacer
	 */
	public function instalar( array $args = array(), array $assoc = array() ): void {
		$this->rehacer = ! empty( $assoc['rehacer'] );
		$this->opciones();
		$this->impuestos();
		$this->envios();
		$this->pagos();
		$this->catalogo();
		$this->paginas();
		$this->menu();
		$this->marca();
		$this->cabecera();
		WP_CLI::success( 'Tienda Mechanical Bull configurada.' );
	}

	/**
	 * Crea o borra productos de ejemplo.
	 *
	 * ## OPTIONS
	 * [--borrar]
	 * : Borra los productos de ejemplo.
	 *
	 * ## EXAMPLES
	 *     wp mb demo
	 *     wp mb demo --borrar
	 */
	public function demo( array $args, array $assoc ): void {
		$existentes = get_posts( array(
			'post_type'   => 'product',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_key'    => '_mb_demo', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => '1',        // phpcs:ignore WordPress.DB.SlowDBQuery
		) );

		if ( ! empty( $assoc['borrar'] ) ) {
			foreach ( $existentes as $id ) {
				wp_delete_post( $id, true );
			}
			WP_CLI::success( sprintf( '%d productos de ejemplo borrados.', count( $existentes ) ) );
			return;
		}
		if ( $existentes ) {
			$n = 0;
			foreach ( $existentes as $id ) {
				if ( ! get_post_thumbnail_id( $id ) && $this->imagen_demo( $id, get_the_title( $id ) ) ) {
					$n++;
				}
			}
			WP_CLI::log( $n ? "Se agregaron fotos a {$n} productos de ejemplo." : 'Los productos de ejemplo ya existen.' );
			return;
		}

		$productos = array(
			array( 'Pantalla iPhone 13 OLED', 85, 'celulares-pantallas', array( 'iphone-13' ), 'oled', 6, 5, 'Pantalla OLED completa con marco para iPhone 13. Colores y brillo cercanos al original, Face ID y True Tone funcionan con nuestra instalación.' ),
			array( 'Pantalla iPhone 13 Incell', 55, 'celulares-pantallas', array( 'iphone-13' ), 'incell', 6, 3, 'Pantalla LCD Incell para iPhone 13. Opción económica: buen táctil, colores menos intensos que una OLED y un poco más de consumo de batería.' ),
			array( 'Batería Samsung Galaxy A54', 25, 'celulares-baterias', array( 'galaxy-a54' ), 'oem', 6, 10, 'Batería OEM de 5000 mAh para Galaxy A54. Incluye adhesivo de instalación.' ),
			array( 'Puerto de carga Redmi Note 12', 12, 'celulares-puertos-de-carga', array( 'redmi-note-12' ), 'alternativo-aaa', 6, 8, 'Placa de carga USB-C con micrófono para Redmi Note 12.' ),
			array( 'Memoria RAM DDR4 16 GB 3200 MHz para laptop', 45, 'laptops-memoria-ram', array( 'hp', 'dell', 'lenovo', 'asus', 'acer' ), 'nuevo', 12, 4, 'Módulo SO-DIMM DDR4 de 16 GB a 3200 MHz. Compatible con la mayoría de laptops de 2017 en adelante. Escríbenos tu modelo y te confirmamos.' ),
			array( 'SSD NVMe M.2 1 TB', 75, 'pc-de-escritorio-y-gamer-almacenamiento-ssd-hdd', array(), 'nuevo', 12, 6, 'Unidad de estado sólido NVMe PCIe 3.0 de 1 TB. Hasta 3500 MB/s de lectura. Sirve para PC y laptops con ranura M.2.' ),
			array( 'Tarjeta de video RTX 4060 8 GB', 380, 'pc-de-escritorio-y-gamer-tarjetas-de-video', array(), 'nuevo', 12, null, 'Tarjeta gráfica GeForce RTX 4060 de 8 GB GDDR6. Ideal para jugar en 1080p con trazado de rayos. Pieza por encargo.' ),
			array( 'Kit de desarmadores de precisión 24 en 1', 15, 'herramientas-y-accesorios-herramientas', array(), 'nuevo', 3, 12, 'Kit con puntas Pentalobe, Phillips, Torx y Y para abrir celulares, laptops y consolas.' ),
		);

		foreach ( $productos as [ $nombre, $precio, $cat, $modelos, $calidad, $garantia, $stock, $desc ] ) {
			$p = new WC_Product_Simple();
			$p->set_name( $nombre );
			$p->set_status( 'publish' );
			$p->set_regular_price( (string) $precio );
			$p->set_description( $desc );
			$p->set_short_description( $desc );
			$p->set_sku( 'MB-' . strtoupper( substr( md5( $nombre ), 0, 6 ) ) );
			$cat_term = get_term_by( 'slug', $cat, 'product_cat' );
			if ( $cat_term ) {
				$p->set_category_ids( array( $cat_term->term_id ) );
			}
			if ( null === $stock ) {
				$p->set_manage_stock( false );
				$p->set_backorders( 'notify' );
				$p->set_stock_status( 'onbackorder' );
				$p->update_meta_data( '_mb_disponibilidad', 'encargo' );
				$p->update_meta_data( '_mb_encargo_dias', 10 );
				$p->update_meta_data( '_mb_encargo_anticipo', 50 );
			} else {
				$p->set_manage_stock( true );
				$p->set_stock_quantity( $stock );
				$p->update_meta_data( '_mb_disponibilidad', 'stock' );
			}
			$p->update_meta_data( '_mb_garantia_meses', (string) $garantia );
			$p->update_meta_data( '_mb_demo', '1' );
			$id = $p->save();

			if ( $modelos ) {
				wp_set_object_terms( $id, $modelos, 'mb_compatible' );
			}
			wp_set_object_terms( $id, array( $calidad ), 'mb_calidad' );
			$this->imagen_demo( $id, $nombre );
		}
		WP_CLI::success( sprintf( '%d productos de ejemplo creados. Bórralos con: wp mb demo --borrar', count( $productos ) ) );
	}

	private function imagen_demo( int $id, string $nombre ): bool {
		$archivo = self::IMAGENES_DEMO[ $nombre ] ?? '';
		if ( ! $archivo ) {
			return false;
		}
		$img = $this->importar_imagen( get_stylesheet_directory() . '/assets/productos/' . $archivo, $nombre );
		if ( $img ) {
			set_post_thumbnail( $id, $img );
		}
		return (bool) $img;
	}

	/* ------------------------------------------------------------------ */

	private function opciones(): void {
		$opciones = array(
			// WordPress
			'blogdescription'                          => 'Venta de repuestos y reparación',
			'timezone_string'                          => 'America/El_Salvador',
			'date_format'                              => 'd/m/Y',
			'time_format'                              => 'g:i a',
			'start_of_week'                            => '1',
			'default_comment_status'                   => 'closed',

			// Dirección de la tienda
			'woocommerce_store_address'                => 'Km 144, Carretera al Cuco',
			'woocommerce_store_address_2'              => 'Cantón El Jute',
			'woocommerce_store_city'                   => 'San Miguel',
			'woocommerce_default_country'              => 'SV:SV-SM',
			'woocommerce_store_postcode'               => '',

			// Venta y moneda
			'woocommerce_allowed_countries'            => 'specific',
			'woocommerce_specific_allowed_countries'   => array( 'SV' ),
			'woocommerce_ship_to_countries'            => '',
			'woocommerce_default_customer_address'     => 'base',
			'woocommerce_currency'                     => 'USD',
			'woocommerce_currency_pos'                 => 'left',
			'woocommerce_price_thousand_sep'           => ',',
			'woocommerce_price_decimal_sep'            => '.',
			'woocommerce_price_num_decimals'           => '2',

			// Impuestos: IVA 13 % incluido en el precio
			'woocommerce_calc_taxes'                   => 'yes',
			'woocommerce_prices_include_tax'           => 'yes',
			'woocommerce_tax_based_on'                 => 'base',
			'woocommerce_tax_display_shop'             => 'incl',
			'woocommerce_tax_display_cart'             => 'incl',
			'woocommerce_tax_total_display'            => 'single',
			'woocommerce_tax_round_at_subtotal'        => 'no',

			// Productos e inventario
			'woocommerce_weight_unit'                  => 'lbs',
			'woocommerce_dimension_unit'               => 'cm',
			'woocommerce_manage_stock'                 => 'yes',
			'woocommerce_hold_stock_minutes'           => '60',
			'woocommerce_notify_low_stock_amount'      => '2',
			'woocommerce_notify_no_stock_amount'       => '0',
			'woocommerce_hide_out_of_stock_items'      => 'no',
			'woocommerce_stock_format'                 => 'low_amount',
			'woocommerce_review_rating_verification_label' => 'yes',
			'woocommerce_enable_ajax_add_to_cart'      => 'yes',

			// Cuentas y pago
			'woocommerce_enable_guest_checkout'        => 'yes',
			'woocommerce_enable_checkout_login_reminder' => 'yes',
			'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			'woocommerce_enable_myaccount_registration' => 'yes',
			'woocommerce_registration_generate_password' => 'yes',
			'woocommerce_checkout_phone_field'         => 'required',
			'woocommerce_checkout_company_field'       => 'hidden',
			'woocommerce_checkout_address_2_field'     => 'optional',
			'woocommerce_ship_to_destination'          => 'billing',

			// Correos
			'woocommerce_email_from_name'              => 'Mechanical Bull',
			'woocommerce_email_base_color'             => '#FF0200',
			'woocommerce_email_background_color'       => '#FAFAF7',
			'woocommerce_email_body_background_color'  => '#FFFFFF',
			'woocommerce_email_text_color'             => '#0E0E0E',
			'woocommerce_email_footer_text'            => 'Mechanical Bull · Venta de repuestos y reparación · San Miguel, El Salvador',

			// Asistente de WooCommerce y modo "Próximamente"
			'woocommerce_onboarding_profile'           => array( 'skipped' => true ),
			'woocommerce_task_list_hidden'             => 'yes',
			'woocommerce_task_list_complete'           => 'yes',
			'woocommerce_show_marketplace_suggestions' => 'no',
			'woocommerce_allow_tracking'               => 'no',
		);
		foreach ( $opciones as $clave => $valor ) {
			update_option( $clave, $valor );
		}

		// Solo en la primera instalación: no indexar y modo "Próximamente". Si después lo cambias, se respeta.
		if ( ! get_option( 'mb_instalado' ) ) {
			update_option( 'blog_public', '0' );
			update_option( 'woocommerce_coming_soon', 'yes' );
			update_option( 'woocommerce_store_pages_only', 'no' );
			update_option( 'mb_instalado', gmdate( 'c' ) );
		}

		// Datos de contacto (se cambian en Apariencia → Personalizar → Mechanical Bull). add_option no pisa cambios.
		foreach ( array( 'whatsapp', 'direccion', 'horario', 'email', 'mapa' ) as $clave ) {
			add_option( 'mb_' . $clave, mb_dato( $clave ) );
		}
		// Instalaciones antiguas guardaron el número de relleno: se cambia por el real (un número puesto a mano no se toca).
		if ( '50300000000' === get_option( 'mb_whatsapp' ) ) {
			delete_option( 'mb_whatsapp' );
			add_option( 'mb_whatsapp', mb_dato( 'whatsapp' ) );
		}

		// Borra el contenido de ejemplo de WordPress.
		foreach ( array( 'hola-mundo', 'hello-world' ) as $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'post' );
			if ( $post ) {
				wp_delete_post( $post->ID, true );
			}
		}
		foreach ( array( 'pagina-ejemplo', 'sample-page' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				wp_delete_post( $page->ID, true );
			}
		}
		WP_CLI::log( '✔ Datos de la tienda (San Miguel, USD, IVA incluido, inventario, cuentas)' );
	}

	private function impuestos(): void {
		global $wpdb;
		$existe = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = 'SV' AND tax_rate_class = ''" );
		if ( ! $existe ) {
			WC_Tax::_insert_tax_rate( array(
				'tax_rate_country'  => 'SV',
				'tax_rate_state'    => '',
				'tax_rate'          => '13.0000',
				'tax_rate_name'     => 'IVA',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			) );
		}
		WP_CLI::log( '✔ IVA 13 %' );
	}

	private function envios(): void {
		$zonas = array(
			array(
				'nombre'     => 'San Miguel',
				'ubicacion'  => array( 'SV:SV-SM', 'state' ),
				'domicilio'  => array( 'Entrega a domicilio en San Miguel', '2.50' ),
			),
			array(
				'nombre'     => 'Resto de El Salvador',
				'ubicacion'  => array( 'SV', 'country' ),
				'domicilio'  => array( 'Envío por courier (2 a 4 días hábiles)', '5.00' ),
			),
		);

		$existentes = wp_list_pluck( WC_Shipping_Zones::get_zones(), 'zone_name' );

		foreach ( $zonas as $orden => $z ) {
			if ( in_array( $z['nombre'], $existentes, true ) ) {
				continue;
			}
			$zone = new WC_Shipping_Zone();
			$zone->set_zone_name( $z['nombre'] );
			$zone->set_zone_order( $orden );
			$zone->add_location( $z['ubicacion'][0], $z['ubicacion'][1] );
			$zone->save();

			$pickup = $zone->add_shipping_method( 'local_pickup' );
			update_option( "woocommerce_local_pickup_{$pickup}_settings", array(
				'title'      => 'Retiro en tienda (gratis)',
				'tax_status' => 'none',
				'cost'       => '',
			) );

			$flat = $zone->add_shipping_method( 'flat_rate' );
			update_option( "woocommerce_flat_rate_{$flat}_settings", array(
				'title'      => $z['domicilio'][0],
				'tax_status' => 'none',
				'cost'       => $z['domicilio'][1],
			) );
		}
		WP_CLI::log( '✔ Envíos: San Miguel ($2.50 o retiro gratis) y resto del país ($5.00 o retiro gratis)' );
	}

	private function pagos(): void {
		$this->ajustes_pasarela( 'bacs', array(
			'enabled'      => 'yes',
			'title'        => 'Transferencia bancaria',
			'description'  => 'Transfiere el total a nuestra cuenta y envíanos el comprobante por WhatsApp con tu número de pedido. Despachamos al confirmar el pago.',
			'instructions' => 'Usa tu número de pedido como referencia y envíanos el comprobante por WhatsApp. Tu pedido se procesa al confirmar el pago.',
		) );
		if ( ! get_option( 'woocommerce_bacs_accounts' ) ) {
			update_option( 'woocommerce_bacs_accounts', array( array(
				'account_name'   => 'Mechanical Bull (CAMBIAR)',
				'account_number' => '0000000000',
				'bank_name'      => 'Banco (CAMBIAR)',
				'sort_code'      => '',
				'iban'           => '',
				'bic'            => '',
			) ) );
		}

		$this->ajustes_pasarela( 'cod', array(
			'enabled'            => 'yes',
			'title'              => 'Pago en efectivo o tarjeta al retirar en tienda',
			'description'        => 'Pagas al recoger tu pedido en el local. Te reservamos las piezas por 48 horas.',
			'instructions'       => 'Te reservamos tus piezas por 48 horas. Pasa por el local con tu número de pedido.',
			'enable_for_methods' => array( 'local_pickup' ),
			'enable_for_virtual' => 'no',
		) );

		$this->ajustes_pasarela( 'mb_chivo', array( 'enabled' => 'yes' ) );

		update_option( 'woocommerce_gateway_order', array( 'mb_chivo' => 2, 'bacs' => 1, 'cod' => 3 ) );
		WP_CLI::log( '✔ Pagos: transferencia, Chivo Wallet y pago al retirar (Wompi y PayPal se agregan con sus credenciales)' );
	}

	private function ajustes_pasarela( string $id, array $valores ): void {
		$clave  = "woocommerce_{$id}_settings";
		$actual = (array) get_option( $clave, array() );
		// Solo se rellenan los valores que no existen, para no pisar cambios hechos en el panel.
		update_option( $clave, array_merge( $valores, array_filter( $actual, static fn( $v ) => '' !== $v && array() !== $v ) ) );
	}

	private function catalogo(): void {
		$arbol = array(
			'Celulares'                 => array( 'Pantallas', 'Baterías', 'Puertos de carga', 'Flex', 'Cámaras', 'Tapas traseras', 'Altavoces y micrófonos', 'Botones', 'Placas y componentes' ),
			'Laptops'                   => array( 'Pantallas', 'Baterías', 'Teclados', 'Cargadores', 'Memoria RAM', 'Almacenamiento (SSD/HDD)', 'Bisagras y carcasas', 'Ventiladores', 'Placas y componentes' ),
			'PC de escritorio y Gamer'  => array( 'Procesadores', 'Tarjetas madre', 'Memoria RAM', 'Almacenamiento (SSD/HDD)', 'Tarjetas de video', 'Fuentes de poder', 'Gabinetes', 'Enfriamiento' ),
			'Herramientas y accesorios' => array( 'Herramientas', 'Adhesivos y consumibles', 'Cables y cargadores' ),
		);
		$orden = 0;
		foreach ( $arbol as $padre => $hijos ) {
			$padre_id = $this->term( $padre, 'product_cat' );
			update_term_meta( $padre_id, 'order', $orden++ );
			$version_img = (int) get_term_meta( $padre_id, 'mb_img_version', true );
			if ( $padre_id && ( ! get_term_meta( $padre_id, 'thumbnail_id', true ) || $version_img < 2 || $this->rehacer ) ) {
				$img = $this->importar_imagen( get_stylesheet_directory() . '/assets/categorias-v2/' . sanitize_title( $padre ) . '.jpg', $padre );
				update_term_meta( $padre_id, 'mb_img_version', 2 );
				if ( $img ) {
					update_term_meta( $padre_id, 'thumbnail_id', $img );
				}
			}
			foreach ( $hijos as $hijo ) {
				$this->term( $hijo, 'product_cat', $padre_id, sanitize_title( $padre . '-' . $hijo ) );
			}
		}

		// Cambia "Sin categorizar" por "Otros".
		$sin = get_term_by( 'slug', 'uncategorized', 'product_cat' ) ?: get_term_by( 'slug', 'sin-categorizar', 'product_cat' );
		if ( $sin ) {
			wp_update_term( $sin->term_id, 'product_cat', array( 'name' => 'Otros', 'slug' => 'otros' ) );
		}

		$marcas = array(
			'Apple'    => array( 'iPhone 11', 'iPhone 11 Pro', 'iPhone 12', 'iPhone 12 Pro', 'iPhone 13', 'iPhone 13 Pro', 'iPhone 14', 'iPhone 14 Pro', 'iPhone 15', 'iPhone 15 Pro', 'iPhone 16', 'iPhone 16 Pro', 'MacBook Air', 'MacBook Pro' ),
			'Samsung'  => array( 'Galaxy A15', 'Galaxy A25', 'Galaxy A35', 'Galaxy A54', 'Galaxy A55', 'Galaxy S23', 'Galaxy S24' ),
			'Xiaomi'   => array( 'Redmi Note 12', 'Redmi Note 13', 'Redmi 13C', 'Poco X6' ),
			'Motorola' => array( 'Moto G54', 'Moto G84', 'Moto E13' ),
			'Huawei'   => array(),
			'HP'       => array(),
			'Dell'     => array(),
			'Lenovo'   => array(),
			'Asus'     => array(),
			'Acer'     => array(),
		);
		foreach ( $marcas as $marca => $modelos ) {
			$marca_id = $this->term( $marca, 'mb_compatible' );
			foreach ( $modelos as $modelo ) {
				$this->term( $modelo, 'mb_compatible', $marca_id );
			}
		}

		$calidades = array(
			'Original'          => 'Pieza original del fabricante.',
			'OEM'               => 'Fabricada por el mismo proveedor del fabricante, sin la marca.',
			'Alternativo (AAA)' => 'Pieza compatible de buena calidad, más económica.',
			'Incell'            => 'Pantalla LCD compatible para modelos OLED; más económica, menor calidad de imagen.',
			'OLED'              => 'Pantalla OLED compatible, colores y brillo cercanos al original.',
			'Nuevo'             => 'Componente nuevo sellado (RAM, SSD, GPU, etc.).',
			'Reacondicionado'   => 'Pieza usada, revisada y probada por nuestros técnicos.',
		);
		foreach ( $calidades as $nombre => $descripcion ) {
			$this->term( $nombre, 'mb_calidad', 0, '', $descripcion );
		}
		WP_CLI::log( '✔ Catálogo: categorías, marcas/modelos y calidades' );
	}

	private function paginas(): void {
		WC_Install::create_pages();

		// Carrito y pago clásicos: compatibles con Wompi, Chivo y los campos de factura / crédito fiscal.
		foreach ( array( 'cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]' ) as $pagina => $shortcode ) {
			$id = wc_get_page_id( $pagina );
			if ( $id > 0 && ! has_shortcode( (string) get_post_field( 'post_content', $id ), str_replace( array( '[', ']' ), '', $shortcode ) ) ) {
				wp_update_post( array( 'ID' => $id, 'post_content' => "<!-- wp:shortcode -->\n{$shortcode}\n<!-- /wp:shortcode -->" ) );
			}
		}

		$dir = MB_CORE_DIR . 'contenido/';
		$paginas = array(
			'inicio'               => 'Inicio',
			'reparaciones'         => 'Reparaciones',
			'garantias'            => 'Garantías',
			'envios-y-devoluciones' => 'Envíos y devoluciones',
			'contacto'             => 'Contacto',
		);
		foreach ( $paginas as $slug => $titulo ) {
			$existente = get_page_by_path( $slug );
			// Una página con versión nueva en VERSION_PAGINAS se reescribe una vez en cada servidor, sin --rehacer.
			$version   = self::VERSION_PAGINAS[ $slug ] ?? 1;
			$vieja     = $existente && $version > 1 && (int) get_post_meta( $existente->ID, '_mb_version_contenido', true ) < $version;
			if ( $existente && ! $this->rehacer && ! $vieja ) {
				continue;
			}
			// Una página hecha en Elementor solo se reemplaza con: wp mb portada --rehacer
			if ( $existente && 'builder' === get_post_meta( $existente->ID, '_elementor_edit_mode', true ) ) {
				continue;
			}
			$contenido = file_exists( "{$dir}{$slug}.html" ) ? (string) file_get_contents( "{$dir}{$slug}.html" ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
			$contenido = str_replace(
				array( '{{TIENDA}}', '{{REPARACIONES}}', '{{GARANTIAS}}', '{{CATEGORIAS}}', '{{ASSETS}}' ),
				array(
					esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ),
					esc_url( home_url( '/reparaciones/' ) ),
					esc_url( home_url( '/garantias/' ) ),
					implode( ',', array_filter( array_map(
						static fn( $slug ) => get_term_by( 'slug', $slug, 'product_cat' )->term_id ?? 0,
						array( 'celulares', 'laptops', 'pc-de-escritorio-y-gamer', 'herramientas-y-accesorios' )
					) ) ),
					esc_url( get_stylesheet_directory_uri() . '/assets' ),
				),
				$contenido
			);
			$id = wp_insert_post( array(
				'ID'           => $existente ? $existente->ID : 0,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $titulo,
				'post_name'    => $slug,
				'post_content' => $contenido,
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_mb_version_contenido', $version );
			}
			if ( 'reparaciones' === $slug && $id ) {
				update_post_meta( $id, 'site-sidebar-layout', 'no-sidebar' );
				update_post_meta( $id, 'ast-site-content-layout', 'normal-width-container' );
				update_post_meta( $id, 'site-content-style', 'unboxed' );
			}
			if ( 'inicio' === $slug && $id ) {
				update_post_meta( $id, 'site-post-title', 'disabled' );      // Astra: oculta el título
				update_post_meta( $id, 'ast-title-bar-display', 'disabled' );
				update_post_meta( $id, 'site-content-layout', 'page-builder' );
				update_post_meta( $id, 'ast-site-content-layout', 'full-width-container' );
				update_post_meta( $id, 'site-sidebar-layout', 'no-sidebar' );
			}
		}

		$inicio = get_page_by_path( 'inicio' );
		if ( $inicio ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $inicio->ID );
		}
		WP_CLI::log( '✔ Páginas: Inicio, Tienda, Reparaciones, Garantías, Envíos y devoluciones, Contacto' );
	}

	private function menu(): void {
		$nombre = 'Menú principal';
		$menu   = wp_get_nav_menu_object( $nombre );
		if ( $menu && $this->rehacer ) {
			wp_delete_nav_menu( $menu->term_id );
			$menu = false;
		}
		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $nombre );
			$items   = array(
				array( 'page', wc_get_page_id( 'shop' ), 'Tienda' ),
				array( 'cat', 'celulares' ),
				array( 'cat', 'laptops' ),
				array( 'cat', 'pc-de-escritorio-y-gamer', 'PC y Gamer' ),
				array( 'page', get_page_by_path( 'reparaciones' )?->ID ),
				array( 'page', get_page_by_path( 'garantias' )?->ID ),
				array( 'page', get_page_by_path( 'contacto' )?->ID ),
			);
			foreach ( $items as $item ) {
				if ( 'page' === $item[0] && $item[1] > 0 ) {
					wp_update_nav_menu_item( $menu_id, 0, array(
						'menu-item-object-id' => $item[1],
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
						'menu-item-title'     => $item[2] ?? '',
						'menu-item-status'    => 'publish',
					) );
				} elseif ( 'cat' === $item[0] ) {
					$term = get_term_by( 'slug', $item[1], 'product_cat' );
					if ( $term ) {
						wp_update_nav_menu_item( $menu_id, 0, array(
							'menu-item-object-id' => $term->term_id,
							'menu-item-object'    => 'product_cat',
							'menu-item-type'      => 'taxonomy',
							'menu-item-title'     => $item[2] ?? '',
							'menu-item-status'    => 'publish',
						) );
					}
				}
			}
		} else {
			$menu_id = $menu->term_id;
		}
		$ubicaciones                = (array) get_theme_mod( 'nav_menu_locations', array() );
		$ubicaciones['primary']     = $menu_id;
		$ubicaciones['mobile_menu'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $ubicaciones );
		WP_CLI::log( '✔ Menú principal' );
	}

	private function marca(): void {
		$assets = get_stylesheet_directory() . '/assets/';

		$logo = $this->importar_imagen( $assets . 'logo-horizontal.png', 'Logo Mechanical Bull' );
		if ( $logo ) {
			set_theme_mod( 'custom_logo', $logo );
		}
		$icono = $this->importar_imagen( $assets . 'icono-512.png', 'Ícono Mechanical Bull' );
		if ( $icono ) {
			update_option( 'site_icon', $icono );
		}

		// Ajustes de Astra: colores de marca, ancho del logo, sin título de texto junto al logo.
		$astra = (array) get_option( 'astra-settings', array() );
		$astra['global-color-palette']          = array(
			'palette' => array( '#FF0200', '#CC0200', '#0E0E0E', '#2B2B2B', '#FAFAF7', '#FFFFFF', '#DAD8D3', '#8A8782', '#0E0E0E' ),
		);
		$astra['ast-header-responsive-logo-width'] = array( 'desktop' => 210, 'tablet' => 180, 'mobile' => 150 );
		$astra['display-site-title-responsive']    = array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 );
		$astra['display-site-tagline-responsive']  = array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 );
		$astra['site-content-layout']              = 'plain-container';
		$astra['woocommerce-content-layout']       = 'plain-container';
		$astra['site-sidebar-layout']              = 'no-sidebar';
		$astra['woocommerce-sidebar-layout']       = 'no-sidebar';
		$astra['shop-grid']                        = array( 'desktop' => 4, 'tablet' => 3, 'mobile' => 2 );
		$astra['footer-copyright-editor']          = '© [current_year] Mechanical Bull · Venta de repuestos y reparación · San Miguel, El Salvador';
		update_option( 'astra-settings', $astra );

		WP_CLI::log( '✔ Logo, ícono del sitio y colores de marca' );
	}

	/**
	 * Cabecera con el constructor de Astra (editable en Apariencia → Personalizar → Cabecera).
	 * Solo se configura la primera vez o con --rehacer, para no pisar lo que edites en el Personalizador.
	 */
	private function cabecera(): void {
		$astra = (array) get_option( 'astra-settings', array() );
		if ( ! empty( $astra['mb-cabecera-version'] ) && ! $this->rehacer ) {
			WP_CLI::log( '✔ Cabecera (sin cambios: ya configurada)' );
			return;
		}
		$fondo = static fn( string $color ): array => array(
			'desktop' => array( 'background-color' => $color, 'background-image' => '', 'background-repeat' => 'repeat', 'background-position' => 'center center', 'background-size' => 'auto', 'background-attachment' => 'scroll', 'overlay-type' => '', 'overlay-color' => '', 'overlay-opacity' => '', 'overlay-gradient' => '' ),
			'tablet'  => array( 'background-color' => '', 'background-image' => '', 'background-repeat' => 'repeat', 'background-position' => 'center center', 'background-size' => 'auto', 'background-attachment' => 'scroll', 'overlay-type' => '', 'overlay-color' => '', 'overlay-opacity' => '', 'overlay-gradient' => '' ),
			'mobile'  => array( 'background-color' => '', 'background-image' => '', 'background-repeat' => 'repeat', 'background-position' => 'center center', 'background-size' => 'auto', 'background-attachment' => 'scroll', 'overlay-type' => '', 'overlay-color' => '', 'overlay-opacity' => '', 'overlay-gradient' => '' ),
		);
		$vacio5 = static fn( string $fila ): array => array( "{$fila}_left" => array(), "{$fila}_left_center" => array(), "{$fila}_center" => array(), "{$fila}_right_center" => array(), "{$fila}_right" => array() );

		$astra['header-desktop-items'] = array(
			'popup'   => array( 'popup_content' => array( 'mobile-menu' ) ),
			'above'   => array_merge( $vacio5( 'above' ), array( 'above_left' => array( 'html-1' ), 'above_right' => array( 'html-2' ) ) ),
			'primary' => array_merge( $vacio5( 'primary' ), array( 'primary_left' => array( 'logo' ), 'primary_right' => array( 'menu-1', 'search', 'account', 'woo-cart' ) ) ),
			'below'   => $vacio5( 'below' ),
		);
		$astra['header-mobile-items'] = array(
			'popup'   => array( 'popup_content' => array( 'mobile-menu', 'html-1' ) ),
			'above'   => array( 'above_left' => array(), 'above_center' => array( 'html-2' ), 'above_right' => array() ),
			'primary' => array( 'primary_left' => array( 'logo' ), 'primary_center' => array(), 'primary_right' => array( 'search', 'woo-cart', 'mobile-trigger' ) ),
			'below'   => array( 'below_left' => array(), 'below_center' => array(), 'below_right' => array() ),
		);

		// Franja superior negra
		$astra['header-html-1']                = 'Envíos a todo El Salvador · Garantía real en piezas y reparaciones';
		$astra['header-html-2']                = '[mb_topbar_whatsapp]';
		$astra['header-html-1color']           = array( 'desktop' => '#D8D6D1', 'tablet' => '#D8D6D1', 'mobile' => '#555555' );
		$astra['header-html-2color']           = array( 'desktop' => '#FFFFFF', 'tablet' => '#FFFFFF', 'mobile' => '#FFFFFF' );
		$astra['header-html-2link-color']      = array( 'desktop' => '#FFFFFF', 'tablet' => '#FFFFFF', 'mobile' => '#FFFFFF' );
		$astra['header-html-2link-h-color']    = array( 'desktop' => '#FF0200', 'tablet' => '#FF0200', 'mobile' => '#FF0200' );
		$astra['hba-header-height']            = array( 'desktop' => 40, 'tablet' => 36, 'mobile' => 34 );
		$astra['hba-header-bg-obj-responsive'] = $fondo( '#0E0E0E' );
		$astra['hba-header-separator']         = 0;

		// Barra principal blanca
		$astra['hb-header-height']             = array( 'desktop' => 86, 'tablet' => 70, 'mobile' => 64 );
		$astra['hb-header-bg-obj-responsive']  = $fondo( '#FFFFFF' );
		$astra['hb-header-main-sep']           = 1;
		$astra['hb-header-main-sep-color']     = '#E4E2DD';

		// Buscar, cuenta y carrito
		$astra['header-search-box-type']       = 'slide-search';
		$astra['header-search-icon-color']     = array( 'desktop' => '#0E0E0E', 'tablet' => '#0E0E0E', 'mobile' => '#0E0E0E' );
		$astra['header-account-type']          = 'default';
		$astra['header-account-login-style']   = 'icon';
		$astra['header-account-logout-style']  = 'icon';
		$astra['header-account-logout-action'] = 'link';
		$astra['header-account-icon-color']    = array( 'desktop' => '#0E0E0E', 'tablet' => '#0E0E0E', 'mobile' => '#0E0E0E' );
		$astra['woo-header-cart-icon']         = 'cart';
		$astra['woo-header-cart-badge-display'] = true;
		$astra['woo-header-cart-icon-style']    = 'none';
		$astra['header-woo-cart-icon-color']   = '#0E0E0E';
		$astra['mobile-header-type']           = 'off-canvas';
		$astra['mobile-header-toggle-btn-style'] = 'minimal';

		$astra['mb-cabecera-version'] = 1;
		update_option( 'astra-settings', $astra );
		WP_CLI::log( '✔ Cabecera (editable en Apariencia → Personalizar → Cabecera)' );
	}

	/** Copia una imagen del tema a la biblioteca de medios (una sola vez) y devuelve su ID. */
	private function importar_imagen( string $ruta, string $titulo ): int {
		$id = mb_importar_imagen_tema( $ruta, $titulo );
		if ( ! $id ) {
			WP_CLI::warning( "No se pudo importar {$ruta}" );
		}
		return $id;
	}

	/** Crea el término si no existe y devuelve su ID. */
	private function term( string $nombre, string $taxonomia, int $padre = 0, string $slug = '', string $descripcion = '' ): int {
		$slug     = $slug ?: sanitize_title( $nombre );
		$existing = get_term_by( 'slug', $slug, $taxonomia );
		if ( $existing ) {
			return (int) $existing->term_id;
		}
		$res = wp_insert_term( $nombre, $taxonomia, array(
			'parent'      => $padre,
			'slug'        => $slug,
			'description' => $descripcion,
		) );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( "{$taxonomia} «{$nombre}»: " . $res->get_error_message() );
			return 0;
		}
		return (int) $res['term_id'];
	}
}

WP_CLI::add_command( 'mb', 'MB_CLI' );
