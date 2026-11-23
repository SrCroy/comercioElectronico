<?php
/**
 * Acceso y registro de Mi cuenta (Mechanical Bull).
 *
 * Basado en la plantilla de WooCommerce myaccount/form-login.php (versión 9.9.0): se conservan todos
 * sus hooks (mb-core los usa para el teléfono, la verificación y el regreso al producto).
 * Se muestra una sola tarjeta a la vez: "Inicia sesión" o "Crea tu cuenta" (?accion=registro),
 * junto a un panel de marca con el toro.
 *
 * @package MechanicalBull
 * @version 9.9.0
 */

defined( 'ABSPATH' ) || exit;

$mb_registro_activo = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
// phpcs:disable WordPress.Security.NonceVerification -- solo decide qué tarjeta se muestra.
$mb_vista = $mb_registro_activo && ( ( isset( $_GET['accion'] ) && 'registro' === $_GET['accion'] ) || isset( $_POST['register'] ) ) ? 'registro' : 'login';
// phpcs:enable
$mb_url_login    = remove_query_arg( 'accion' );
$mb_url_registro = add_query_arg( 'accion', 'registro' );
$mb_assets       = get_stylesheet_directory_uri() . '/assets';
?>

<div class="mb-acceso" data-vista="<?php echo esc_attr( $mb_vista ); ?>">

	<aside class="mb-acceso__marca" aria-hidden="true">
		<img class="mb-acceso__logo" src="<?php echo esc_url( $mb_assets . '/logo-horizontal-blanco.png' ); ?>" alt="" width="200" height="60">
		<img class="mb-acceso__toro" src="<?php echo esc_url( $mb_assets . '/elementor/hero-toro.png' ); ?>" alt="" width="900" height="820">
		<p class="mb-acceso__titulo">Tu cuenta <span>Mechanical Bull</span></p>
		<ul class="mb-acceso__beneficios">
			<li>Mira precios, fotos y detalles de cada repuesto</li>
			<li>Sigue tus pedidos y tus reparaciones</li>
			<li>Garantía por escrito en cada compra</li>
		</ul>
	</aside>

	<div class="mb-acceso__panel">

		<?php do_action( 'woocommerce_before_customer_login_form' ); ?>

		<section class="mb-acceso__vista mb-acceso__vista--login" id="mb-acceso-login"<?php echo 'login' === $mb_vista ? '' : ' hidden'; ?>>
			<h2>Inicia sesión</h2>
			<?php if ( $mb_registro_activo ) : ?>
				<p class="mb-acceso__cambio">¿No tienes cuenta? <a href="<?php echo esc_url( $mb_url_registro ); ?>" data-mb-vista="registro">Créala gratis</a></p>
			<?php endif; ?>

			<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

				<?php do_action( 'woocommerce_login_form_start' ); ?>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="username">Correo electrónico o usuario&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Obligatorio</span></label>
					<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
				</p>
				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="password">Contraseña&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Obligatorio</span></label>
					<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
				</p>

				<?php do_action( 'woocommerce_login_form' ); ?>

				<div class="mb-acceso__fila">
					<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
						<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span>Recuérdame</span>
					</label>
					<a class="mb-acceso__olvido" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">¿Olvidaste tu contraseña?</a>
				</div>

				<p class="form-row">
					<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
					<button type="submit" class="woocommerce-button button woocommerce-form-login__submit mb-acceso__boton" name="login" value="Acceder">Iniciar sesión</button>
				</p>

				<?php do_action( 'woocommerce_login_form_end' ); ?>

			</form>
		</section>

		<?php if ( $mb_registro_activo ) : ?>
		<section class="mb-acceso__vista mb-acceso__vista--registro" id="mb-acceso-registro"<?php echo 'registro' === $mb_vista ? '' : ' hidden'; ?>>
			<h2>Crea tu cuenta</h2>
			<p class="mb-acceso__cambio">¿Ya tienes cuenta? <a href="<?php echo esc_url( $mb_url_login ); ?>" data-mb-vista="login">Inicia sesión</a></p>

			<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >

				<?php do_action( 'woocommerce_register_form_start' ); ?>

				<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="reg_username">Usuario&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Obligatorio</span></label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
					</p>
				<?php endif; ?>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="reg_email">Correo electrónico&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Obligatorio</span></label>
					<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
				</p>

				<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="reg_password">Contraseña&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text">Obligatorio</span></label>
						<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
					</p>
				<?php else : ?>
					<p>Te enviaremos un enlace a tu correo para crear tu contraseña.</p>
				<?php endif; ?>

				<?php do_action( 'woocommerce_register_form' ); ?>

				<p class="woocommerce-form-row form-row">
					<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
					<button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit mb-acceso__boton" name="register" value="Registrarse">Crear mi cuenta</button>
				</p>

				<?php do_action( 'woocommerce_register_form_end' ); ?>

			</form>
		</section>
		<?php endif; ?>

	</div>
</div>

<script>
/* Cambia entre "Inicia sesión" y "Crea tu cuenta" sin recargar (los enlaces funcionan igual sin JavaScript). */
( function () {
	var caja = document.querySelector( '.mb-acceso' );
	if ( ! caja ) { return; }
	caja.addEventListener( 'click', function ( e ) {
		var enlace = e.target.closest( '[data-mb-vista]' );
		if ( ! enlace ) { return; }
		e.preventDefault();
		var vista = enlace.getAttribute( 'data-mb-vista' );
		caja.setAttribute( 'data-vista', vista );
		caja.querySelector( '#mb-acceso-login' ).hidden = 'login' !== vista;
		caja.querySelector( '#mb-acceso-registro' ).hidden = 'registro' !== vista;
		var avisos = caja.querySelector( '.woocommerce-notices-wrapper' );
		if ( avisos ) { avisos.innerHTML = ''; }
		history.replaceState( null, '', enlace.href );
		var campo = caja.querySelector( 'registro' === vista ? '#mb_nombre' : '#username' );
		if ( campo ) { campo.focus(); }
	} );
} )();
</script>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
