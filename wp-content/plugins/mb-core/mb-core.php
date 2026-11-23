<?php
/**
 * Plugin Name: Mechanical Bull — Núcleo
 * Description: Lógica de negocio de Mechanical Bull: roles, compatibilidad por marca/modelo, calidad del repuesto, garantía y piezas por encargo.
 * Version:     0.1.0
 * Author:      Mechanical Bull
 * Text Domain: mb-core
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'MB_CORE_VERSION', '0.1.0' );
define( 'MB_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once MB_CORE_DIR . 'includes/negocio.php';
require_once MB_CORE_DIR . 'includes/roles.php';
require_once MB_CORE_DIR . 'includes/facturacion.php';
require_once MB_CORE_DIR . 'includes/pagos.php';
require_once MB_CORE_DIR . 'includes/taxonomies.php';
require_once MB_CORE_DIR . 'includes/product-fields.php';
require_once MB_CORE_DIR . 'includes/correo.php';
require_once MB_CORE_DIR . 'includes/cuentas.php';
require_once MB_CORE_DIR . 'includes/elementor.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once MB_CORE_DIR . 'includes/cli.php';
}

register_activation_hook( __FILE__, 'mb_core_activate' );

function mb_core_activate(): void {
	mb_register_roles();
	mb_register_taxonomies();
	flush_rewrite_rules();
	update_option( 'mb_core_version', MB_CORE_VERSION );
}

// Si se actualiza el plugin, se vuelven a aplicar los roles.
add_action( 'init', static function (): void {
	if ( get_option( 'mb_core_version' ) !== MB_CORE_VERSION ) {
		mb_register_roles();
		update_option( 'mb_core_version', MB_CORE_VERSION );
	}
}, 20 );
