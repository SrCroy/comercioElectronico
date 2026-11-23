<?php
/**
 * Roles de Mechanical Bull.
 *
 *  - administrator          → Dueño (rol nativo de WordPress)
 *  - mb_secretaria          → Secretaria / Vendedor: pedidos, productos, clientes. Sin ajustes, reportes ni costos.
 *  - mb_tecnico             → Técnico: acceso mínimo; las órdenes de reparación llegan en la Fase 2.
 *  - customer               → Cliente final (rol nativo de WooCommerce)
 *  - mb_cliente_tecnico     → Cliente técnico verificado (precio especial, Fase 3)
 *  - mb_cliente_mayorista   → Cliente mayorista verificado (precio por volumen, Fase 3)
 */

defined( 'ABSPATH' ) || exit;

/** Capacidades personalizadas que usará el módulo de taller (Fase 2). */
function mb_taller_caps(): array {
	return array(
		'mb_ver_ordenes'        => 'Ver todas las órdenes de reparación',
		'mb_ver_ordenes_propias' => 'Ver las órdenes asignadas',
		'mb_editar_ordenes'     => 'Crear y editar órdenes de reparación',
		'mb_cambiar_estado'     => 'Cambiar estado de una orden',
		'mb_ver_costos'         => 'Ver costos y márgenes',
		'mb_aprobar_clientes'   => 'Aprobar cuentas de técnicos y mayoristas',
	);
}

function mb_register_roles(): void {
	$caps_taller = array_keys( mb_taller_caps() );

	// Dueño: todo, incluidas las capacidades del taller.
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( $caps_taller as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	// Secretaria: parte de Gerente de tienda, sin ajustes, reportes ni costos.
	$base = get_role( 'shop_manager' );
	$caps = $base ? $base->capabilities : array( 'read' => true );
	foreach ( array( 'view_woocommerce_reports', 'edit_users', 'promote_users', 'delete_users', 'create_users', 'edit_theme_options' ) as $quitar ) {
		unset( $caps[ $quitar ] );
	}
	$caps['list_users']          = true;
	$caps['mb_ver_ordenes']      = true;
	$caps['mb_editar_ordenes']   = true;
	$caps['mb_cambiar_estado']   = true;
	$caps['mb_aprobar_clientes'] = true;
	remove_role( 'mb_secretaria' );
	add_role( 'mb_secretaria', 'Secretaria / Vendedor', $caps );

	// Técnico: solo sus órdenes asignadas y cambio de estado.
	remove_role( 'mb_tecnico' );
	add_role( 'mb_tecnico', 'Técnico', array(
		'read'                   => true,
		'upload_files'           => true, // fotos de diagnóstico
		'mb_ver_ordenes_propias' => true,
		'mb_cambiar_estado'      => true,
	) );

	// Clientes B2B: mismos permisos que un cliente; el precio especial se aplica en la Fase 3.
	foreach ( array(
		'mb_cliente_tecnico'   => 'Cliente técnico',
		'mb_cliente_mayorista' => 'Cliente mayorista',
	) as $slug => $nombre ) {
		remove_role( $slug );
		add_role( $slug, $nombre, array( 'read' => true ) );
	}
}

/** Páginas de WooCommerce que la secretaria no puede abrir (ajustes, estado del sistema, extensiones). */
function mb_paginas_bloqueadas_secretaria(): array {
	return array( 'wc-settings', 'wc-status', 'wc-addons' );
}

function mb_es_secretaria(): bool {
	return in_array( 'mb_secretaria', (array) wp_get_current_user()->roles, true );
}

add_action( 'admin_menu', static function (): void {
	if ( ! mb_es_secretaria() ) {
		return;
	}
	foreach ( mb_paginas_bloqueadas_secretaria() as $slug ) {
		remove_submenu_page( 'woocommerce', $slug );
	}
	remove_menu_page( 'tools.php' );
}, 999 );

add_action( 'admin_init', static function (): void {
	if ( ! mb_es_secretaria() || wp_doing_ajax() ) {
		return;
	}
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( in_array( $page, mb_paginas_bloqueadas_secretaria(), true ) ) {
		wp_die( esc_html__( 'Esta sección solo está disponible para el administrador.', 'mb-core' ), '', array( 'response' => 403 ) );
	}
} );

/** El técnico necesita entrar al escritorio para ver sus órdenes (WooCommerce lo bloquea por defecto). */
add_filter( 'woocommerce_prevent_admin_access', static function ( bool $prevent ): bool {
	if ( in_array( 'mb_tecnico', (array) wp_get_current_user()->roles, true ) ) {
		return false;
	}
	return $prevent;
} );
