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
function advshipping_wpm_get_design_defaults() {
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
function advshipping_wpm_sanitize_design_settings( $input ) {
	$defaults = advshipping_wpm_get_design_defaults();
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
function advshipping_wpm_get_design_settings() {
	return advshipping_wpm_sanitize_design_settings( get_option( 'advshipping_wpm_checkout_design', array() ) );
}

/**
 * Get design css.
 *
 * @return string Result value.
 */
function advshipping_wpm_get_design_css() {
	$design       = advshipping_wpm_get_design_settings();
	$declarations = array();
	foreach ( array(
		'primary_color'    => 'primary',
		'background_color' => 'background',
		'border_color'     => 'border-color',
		'text_color'       => 'text',
		'heading_color'    => 'heading',
		'muted_color'      => 'muted',
	) as $key => $variable ) {
		$declarations[] = '--advshipping_wpm-' . $variable . ':' . $design[ $key ];
	}
	foreach ( array( 'border_width', 'border_radius' ) as $key ) {
		$declarations[] = '--advshipping_wpm-' . str_replace( '_', '-', $key ) . ':' . $design[ $key ] . 'px';
	}
	$declarations[] = '--advshipping_wpm-border-style:' . $design['border_style'];
	foreach ( array( 'padding', 'margin' ) as $type ) {
		$sides = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			$sides[] = $design[ $type . '_' . $side ] . 'px';
		}
		$declarations[] = '--advshipping_wpm-' . $type . ':' . implode( ' ', $sides );
	}
	return '#advshipping_wpm-notice-wrap{' . implode( ';', $declarations ) . ';}' . "\n" . '.advshipping_wpm-gateway-cost{--advshipping_wpm-primary:' . $design['primary_color'] . ';}' . "\n" . $design['custom_css'];
}

/**
 * Handle design save.
 *
 * @return string|void Saved notice, or no value when not submitted.
 */
function advshipping_wpm_handle_design_save() {
	if ( ! isset( $_POST['advshipping_wpm_save_design'] ) && ! isset( $_POST['advshipping_wpm_reset_design'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'advshipping_wpm_save_design', 'advshipping_wpm_design_nonce' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The design sanitizer validates each scalar field, preserving CSS formatting while removing HTML delimiters.
	$design = isset( $_POST['advshipping_wpm_reset_design'] ) ? advshipping_wpm_get_design_defaults() : advshipping_wpm_sanitize_design_settings( isset( $_POST['advshipping_wpm_design'] ) ? wp_unslash( $_POST['advshipping_wpm_design'] ) : array() );
	update_option( 'advshipping_wpm_checkout_design', $design );
	return __( 'Checkout design saved.', 'advshipping-wpm' );
}

/**
 * Render checkout design tab.
 *
 * @return void No return value.
 */
function advshipping_wpm_render_checkout_design_tab() {
	$design = advshipping_wpm_get_design_settings();
	$colors = array(
		'primary_color'    => __( 'Primary color', 'advshipping-wpm' ),
		'background_color' => __( 'Background color', 'advshipping-wpm' ),
		'border_color'     => __( 'Border color', 'advshipping-wpm' ),
		'text_color'       => __( 'Text color', 'advshipping-wpm' ),
		'heading_color'    => __( 'Heading color', 'advshipping-wpm' ),
		'muted_color'      => __( 'Description color', 'advshipping-wpm' ),
	);
	?>
	<div class="advshipping_wpm-design-layout">
		<div class="advshipping_wpm-card"><h2><?php esc_html_e( 'Checkout Design', 'advshipping-wpm' ); ?></h2><p class="description"><?php esc_html_e( 'Match the advance payment box to your theme. These settings affect the checkout summary.', 'advshipping-wpm' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'advshipping_wpm_save_design', 'advshipping_wpm_design_nonce' ); ?>
			<table class="form-table"><tbody>
			<?php foreach ( $colors as $key => $label ) : ?>
			<tr><th><label for="advshipping_wpm-design-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="text" class="advshipping_wpm-color-picker" id="advshipping_wpm-design-<?php echo esc_attr( $key ); ?>" name="advshipping_wpm_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" data-default-color="<?php echo esc_attr( advshipping_wpm_get_design_defaults()[ $key ] ); ?>" /></td></tr>
			<?php endforeach; ?>
			<tr><th><label for="advshipping_wpm-design-border_style"><?php esc_html_e( 'Border style', 'advshipping-wpm' ); ?></label></th><td><select id="advshipping_wpm-design-border_style" name="advshipping_wpm_design[border_style]" data-design-key="border_style">
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
			<tr><th><label for="advshipping_wpm-design-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="number" min="0" max="100" step="0.5" class="small-text" id="advshipping_wpm-design-<?php echo esc_attr( $key ); ?>" name="advshipping_wpm_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" /> px</td></tr>
			<?php endforeach; ?>
			<?php
			foreach ( array(
				'padding' => __( 'Padding (inside)', 'advshipping-wpm' ),
				'margin'  => __( 'Margin (outside)', 'advshipping-wpm' ),
			) as $type => $label ) :
				?>
			<tr><th><?php echo esc_html( $label ); ?></th><td><div class="advshipping_wpm-spacing-fields">
				<?php
				foreach ( array(
					'top'    => __( 'Top', 'advshipping-wpm' ),
					'right'  => __( 'Right', 'advshipping-wpm' ),
					'bottom' => __( 'Bottom', 'advshipping-wpm' ),
					'left'   => __( 'Left', 'advshipping-wpm' ),
				) as $side => $side_label ) :
						$key = $type . '_' . $side;
					?>
			<label><?php echo esc_html( $side_label ); ?><input type="number" min="0" max="200" step="0.5" name="advshipping_wpm_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" /></label>
			<?php endforeach; ?></div><p class="description"><?php esc_html_e( 'All spacing values are in pixels.', 'advshipping-wpm' ); ?></p></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<h3><label for="advshipping_wpm-custom-css"><?php esc_html_e( 'Custom CSS', 'advshipping-wpm' ); ?></label></h3>
			<p class="description"><?php esc_html_e( 'Applied after the design settings on checkout. Use #advshipping_wpm-notice-wrap to target this plugin’s summary. Custom CSS is applied after saving.', 'advshipping-wpm' ); ?></p>
			<textarea id="advshipping_wpm-custom-css" name="advshipping_wpm_design[custom_css]" class="large-text code" rows="10" spellcheck="false" placeholder="#advshipping_wpm-notice-wrap .advshipping_wpm-payment-card { box-shadow: 0 4px 16px rgba(0,0,0,.05);
}" aria-label="<?php esc_attr_e( 'Checkout custom CSS', 'advshipping-wpm' ); ?>"><?php echo esc_textarea( $design['custom_css'] ); ?></textarea>
			<p><button type="submit" class="button button-primary" name="advshipping_wpm_save_design"><?php esc_html_e( 'Save Design', 'advshipping-wpm' ); ?></button> <button type="submit" class="button" name="advshipping_wpm_reset_design"><?php esc_html_e( 'Reset to defaults', 'advshipping-wpm' ); ?></button></p>
		</form></div>
		<aside class="advshipping_wpm-card advshipping_wpm-design-preview"><h2><?php esc_html_e( 'Live preview', 'advshipping-wpm' ); ?></h2><p class="description"><?php esc_html_e( 'Sample amounts. Colors, borders and spacing update as you edit.', 'advshipping-wpm' ); ?></p>
			<div class="advshipping_wpm-preview-canvas"><div id="advshipping_wpm-notice-wrap"><section class="advshipping_wpm-payment-card"><h3><?php echo esc_html( advshipping_wpm_get_texts()['notice_heading'] ); ?></h3><dl class="advshipping_wpm-payment-breakdown"><div><dt><?php esc_html_e( 'Full order total', 'advshipping-wpm' ); ?></dt><dd><?php echo wp_kses_post( wc_price( 1000 ) ); ?></dd></div><div class="advshipping_wpm-pay-now"><dt><?php esc_html_e( 'Payable Now', 'advshipping-wpm' ); ?></dt><dd><?php echo wp_kses_post( wc_price( 200 ) ); ?></dd></div><div><dt><?php esc_html_e( 'Due on delivery', 'advshipping-wpm' ); ?></dt><dd><?php echo wp_kses_post( wc_price( 800 ) ); ?></dd></div></dl><p class="advshipping_wpm-payment-message"><?php esc_html_e( 'Pay the advance now. Pay the remaining balance on delivery.', 'advshipping-wpm' ); ?></p></section></div></div>
		</aside>
	</div>
	<?php
}
