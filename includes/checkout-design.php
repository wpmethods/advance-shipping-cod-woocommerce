<?php
/**
 * Checkout appearance settings and preview.
 *
 * @package AdvShippingWPM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get design defaults.
 *
 * @return array Result value.
 */
function aswpm_get_design_defaults() {
	return array(
		'primary_color'    => '#2563eb',
		'background_color' => '#f8fafc',
		'border_color'     => '#dbe4ef',
		'text_color'       => '#1e293b',
		'heading_color'    => '#0f172a',
		'muted_color'      => '#475569',
		'border_style'     => 'solid',
		'border_width'     => 1,
		'border_radius'    => 12,
		'padding_top'      => 20,
		'padding_right'    => 20,
		'padding_bottom'   => 20,
		'padding_left'     => 20,
		'margin_top'       => 16,
		'margin_right'     => 13,
		'margin_bottom'    => 16,
		'margin_left'      => 13,
		'custom_css'       => '',
	);
}

/**
 * Sanitize design settings.
 *
 * @param array $input input value.
 *
 * @return array Result value.
 */
function aswpm_sanitize_design_settings( $input ) {
	$defaults = aswpm_get_design_defaults();
	$clean    = $defaults;
	$input    = is_array( $input ) ? $input : array();
	foreach ( $defaults as $key => $default ) {
		if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) {
			continue;
		}
		$value = (string) $input[ $key ];
		if ( false !== strpos( $key, '_color' ) ) {
			$color         = sanitize_hex_color( $value );
			$clean[ $key ] = $color ? $color : $default;
		} elseif ( 'border_style' === $key ) {
			$clean[ $key ] = in_array( $value, array( 'none', 'solid', 'dashed', 'dotted', 'double' ), true ) ? $value : $default;
		} elseif ( 'custom_css' === $key ) {
			// CSS is allowed; literal HTML delimiters must never escape an inline style element.
			$clean[ $key ] = str_replace( array( '<', '>', "\0" ), '', $value );
		} elseif ( is_numeric( $value ) ) {
			$limit         = in_array( $key, array( 'border_width', 'border_radius' ), true ) ? 100 : 200;
			$clean[ $key ] = max( 0, min( $limit, (float) $value ) );
		}
	}
	return $clean;
}

/**
 * Get design settings.
 *
 * @return array Result value.
 */
function aswpm_get_design_settings() {
	return aswpm_sanitize_design_settings( get_option( 'aswpm_checkout_design', array() ) );
}

/**
 * Get design css.
 *
 * @return string Result value.
 */
function aswpm_get_design_css() {
	$design       = aswpm_get_design_settings();
	$declarations = array();
	foreach ( array(
		'primary_color'    => 'primary',
		'background_color' => 'background',
		'border_color'     => 'border-color',
		'text_color'       => 'text',
		'heading_color'    => 'heading',
		'muted_color'      => 'muted',
	) as $key => $variable ) {
		$declarations[] = '--aswpm-' . $variable . ':' . $design[ $key ];
	}
	foreach ( array( 'border_width', 'border_radius' ) as $key ) {
		$declarations[] = '--aswpm-' . str_replace( '_', '-', $key ) . ':' . $design[ $key ] . 'px';
	}
	$declarations[] = '--aswpm-border-style:' . $design['border_style'];
	foreach ( array( 'padding', 'margin' ) as $type ) {
		$sides = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$sides[] = $design[ $type . '_' . $side ] . 'px';
		}
		$declarations[] = '--aswpm-' . $type . ':' . implode( ' ', $sides );
	}
	return '#aswpm-notice-wrap{' . implode( ';', $declarations ) . ';}' . "\n" . '.aswpm-gateway-cost{--aswpm-primary:' . $design['primary_color'] . ';}' . "\n" . $design['custom_css'];
}

/**
 * Handle design save.
 *
 * @return string|void Saved notice, or no value when not submitted.
 */
function aswpm_handle_design_save() {
	if ( ! isset( $_POST['aswpm_save_design'] ) && ! isset( $_POST['aswpm_reset_design'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'aswpm_save_design', 'aswpm_design_nonce' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The design sanitizer validates each scalar field, preserving CSS formatting while removing HTML delimiters.
	$design = isset( $_POST['aswpm_reset_design'] ) ? aswpm_get_design_defaults() : aswpm_sanitize_design_settings( isset( $_POST['aswpm_design'] ) ? wp_unslash( $_POST['aswpm_design'] ) : array() );
	update_option( 'aswpm_checkout_design', $design );
	return __( 'Checkout design saved.', 'advshipping-wpm' );
}

/**
 * Render checkout design tab.
 *
 * @return void No return value.
 */
function aswpm_render_checkout_design_tab() {
	$design = aswpm_get_design_settings();
	$colors = array(
		'primary_color'    => __( 'Primary color', 'advshipping-wpm' ),
		'background_color' => __( 'Background color', 'advshipping-wpm' ),
		'border_color'     => __( 'Border color', 'advshipping-wpm' ),
		'text_color'       => __( 'Text color', 'advshipping-wpm' ),
		'heading_color'    => __( 'Heading color', 'advshipping-wpm' ),
		'muted_color'      => __( 'Description color', 'advshipping-wpm' ),
	);
	?>
	<div class="aswpm-design-layout">
		<div class="aswpm-card"><h2><?php esc_html_e( 'Checkout Design', 'advshipping-wpm' ); ?></h2><p class="description"><?php esc_html_e( 'Match the advance payment box to your theme. These settings affect the checkout summary.', 'advshipping-wpm' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'aswpm_save_design', 'aswpm_design_nonce' ); ?>
			<table class="form-table"><tbody>
			<?php foreach ( $colors as $key => $label ) : ?>
			<tr><th><label for="aswpm-design-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="text" class="aswpm-color-picker" id="aswpm-design-<?php echo esc_attr( $key ); ?>" name="aswpm_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" data-default-color="<?php echo esc_attr( aswpm_get_design_defaults()[ $key ] ); ?>" /></td></tr>
			<?php endforeach; ?>
			<tr><th><label for="aswpm-design-border_style"><?php esc_html_e( 'Border style', 'advshipping-wpm' ); ?></label></th><td><select id="aswpm-design-border_style" name="aswpm_design[border_style]" data-design-key="border_style">
			<?php
			foreach ( array(
				'none'   => __( 'None', 'advshipping-wpm' ),
				'solid'  => __( 'Solid', 'advshipping-wpm' ),
				'dashed' => __( 'Dashed', 'advshipping-wpm' ),
				'dotted' => __( 'Dotted', 'advshipping-wpm' ),
				'double' => __( 'Double', 'advshipping-wpm' ),
			) as $value => $label ) :
				?>
	<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $design['border_style'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
			<?php
			foreach ( array(
				'border_width'  => __( 'Border width', 'advshipping-wpm' ),
				'border_radius' => __( 'Corner radius', 'advshipping-wpm' ),
			) as $key => $label ) :
				?>
			<tr><th><label for="aswpm-design-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="number" min="0" max="100" step="0.5" class="small-text" id="aswpm-design-<?php echo esc_attr( $key ); ?>" name="aswpm_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" /> px</td></tr>
			<?php endforeach; ?>
			<?php
			foreach ( array(
				'padding' => __( 'Padding (inside)', 'advshipping-wpm' ),
				'margin'  => __( 'Margin (outside)', 'advshipping-wpm' ),
			) as $type => $label ) :
				?>
			<tr><th><?php echo esc_html( $label ); ?></th><td><div class="aswpm-spacing-fields">
				<?php
				foreach ( array(
					'top'    => __( 'Top', 'advshipping-wpm' ),
					'right'  => __( 'Right', 'advshipping-wpm' ),
					'bottom' => __( 'Bottom', 'advshipping-wpm' ),
					'left'   => __( 'Left', 'advshipping-wpm' ),
				) as $side => $side_label ) :
						$key = $type . '_' . $side;
					?>
			<label><?php echo esc_html( $side_label ); ?><input type="number" min="0" max="200" step="0.5" name="aswpm_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" /></label>
			<?php endforeach; ?></div><p class="description"><?php esc_html_e( 'All spacing values are in pixels.', 'advshipping-wpm' ); ?></p></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<h3><label for="aswpm-custom-css"><?php esc_html_e( 'Custom CSS', 'advshipping-wpm' ); ?></label></h3>
			<p class="description"><?php esc_html_e( 'Applied after the design settings on checkout. Use #aswpm-notice-wrap to target this plugin’s summary. Custom CSS is applied after saving.', 'advshipping-wpm' ); ?></p>
			<textarea id="aswpm-custom-css" name="aswpm_design[custom_css]" class="large-text code" rows="10" spellcheck="false" placeholder="#aswpm-notice-wrap .aswpm-payment-card { box-shadow: 0 4px 16px rgba(0,0,0,.05);
}" aria-label="<?php esc_attr_e( 'Checkout custom CSS', 'advshipping-wpm' ); ?>"><?php echo esc_textarea( $design['custom_css'] ); ?></textarea>
			<p><button type="submit" class="button button-primary" name="aswpm_save_design"><?php esc_html_e( 'Save Design', 'advshipping-wpm' ); ?></button> <button type="submit" class="button" name="aswpm_reset_design"><?php esc_html_e( 'Reset to defaults', 'advshipping-wpm' ); ?></button></p>
		</form></div>
		<aside class="aswpm-card aswpm-design-preview"><h2><?php esc_html_e( 'Live preview', 'advshipping-wpm' ); ?></h2><p class="description"><?php esc_html_e( 'Sample amounts. Colors, borders and spacing update as you edit.', 'advshipping-wpm' ); ?></p>
			<div class="aswpm-preview-canvas"><div id="aswpm-notice-wrap"><section class="aswpm-payment-card"><h3><?php echo esc_html( aswpm_get_texts()['notice_heading'] ); ?></h3><dl class="aswpm-payment-breakdown"><div><dt><?php esc_html_e( 'Full order total', 'advshipping-wpm' ); ?></dt><dd><?php echo wp_kses_post( wc_price( 1000 ) ); ?></dd></div><div class="aswpm-pay-now"><dt><?php esc_html_e( 'Payable Now', 'advshipping-wpm' ); ?></dt><dd><?php echo wp_kses_post( wc_price( 200 ) ); ?></dd></div><div><dt><?php esc_html_e( 'Due on delivery', 'advshipping-wpm' ); ?></dt><dd><?php echo wp_kses_post( wc_price( 800 ) ); ?></dd></div></dl><p class="aswpm-payment-message"><?php esc_html_e( 'Pay the advance now. Pay the remaining balance on delivery.', 'advshipping-wpm' ); ?></p></section></div></div>
		</aside>
	</div>
	<?php
}
