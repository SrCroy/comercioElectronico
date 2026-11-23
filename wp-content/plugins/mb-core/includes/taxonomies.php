<?php
/**
 * Taxonomías de producto:
 *
 *  - product_cat (WooCommerce)  → TIPO de pieza: Celulares > Pantallas, Laptops > Baterías, PC > Tarjetas de video…
 *  - mb_compatible              → MARCA > MODELO con el que es compatible: Apple > iPhone 13, Samsung > Galaxy A54…
 *  - mb_calidad                 → Original, OEM, Alternativo (AAA), Incell, OLED, Nuevo, Reacondicionado
 *
 * Así el cliente busca "tengo un iPhone 13" y ve todo lo compatible, o filtra por tipo de pieza.
 */

defined( 'ABSPATH' ) || exit;

function mb_register_taxonomies(): void {
	register_taxonomy( 'mb_compatible', array( 'product' ), array(
		'labels'            => array(
			'name'              => 'Marcas y modelos',
			'singular_name'     => 'Modelo compatible',
			'menu_name'         => 'Marcas y modelos',
			'all_items'         => 'Todas las marcas y modelos',
			'parent_item'       => 'Marca',
			'parent_item_colon' => 'Marca:',
			'edit_item'         => 'Editar marca/modelo',
			'add_new_item'      => 'Añadir marca o modelo',
			'search_items'      => 'Buscar modelo',
			'not_found'         => 'No hay modelos.',
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => 'compatible', 'hierarchical' => true ),
	) );

	register_taxonomy( 'mb_calidad', array( 'product' ), array(
		'labels'            => array(
			'name'          => 'Calidad del repuesto',
			'singular_name' => 'Calidad',
			'menu_name'     => 'Calidades',
			'add_new_item'  => 'Añadir calidad',
			'edit_item'     => 'Editar calidad',
		),
		'hierarchical'      => true, // casillas en vez de etiquetas libres
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => 'calidad' ),
	) );
}
add_action( 'init', 'mb_register_taxonomies', 5 );

/** Muestra la compatibilidad y la calidad en la ficha del producto. */
add_action( 'woocommerce_product_meta_end', static function (): void {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$id = $product->get_id();

	$modelos = get_the_term_list( $id, 'mb_compatible', '<span class="mb-meta mb-compatible">Compatible con: ', ', ', '</span>' );
	if ( $modelos && ! is_wp_error( $modelos ) ) {
		echo wp_kses_post( $modelos );
	}

	$calidad = get_the_term_list( $id, 'mb_calidad', '<span class="mb-meta mb-calidad">Calidad: ', ', ', '</span>' );
	if ( $calidad && ! is_wp_error( $calidad ) ) {
		echo wp_kses_post( $calidad );
	}
} );

/** El buscador de la tienda también encuentra productos por el nombre del modelo compatible. */
add_filter( 'posts_search', static function ( string $search, WP_Query $query ): string {
	global $wpdb;
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() || 'product' !== $query->get( 'post_type' ) ) {
		return $search;
	}
	$term = trim( (string) $query->get( 's' ) );
	if ( '' === $term || '' === $search ) {
		return $search;
	}
	$like = '%' . $wpdb->esc_like( $term ) . '%';
	$ids  = $wpdb->get_col( $wpdb->prepare(
		"SELECT tr.object_id
		   FROM {$wpdb->term_relationships} tr
		   JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		   JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		  WHERE tt.taxonomy = 'mb_compatible' AND t.name LIKE %s",
		$like
	) );
	if ( ! $ids ) {
		return $search;
	}
	$ids = implode( ',', array_map( 'absint', $ids ) );
	// $search empieza con " AND (...)": añadimos "OR ID IN (...)" dentro del mismo grupo.
	return preg_replace( '/^\s*AND\s*\(/', " AND ({$wpdb->posts}.ID IN ({$ids}) OR ", $search, 1 ) ?? $search;
}, 10, 2 );
