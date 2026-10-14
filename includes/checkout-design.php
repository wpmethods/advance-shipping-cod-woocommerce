<?php
defined( 'ABSPATH' ) || exit;

function wcasc_get_design_defaults() {
	return array(
		'primary_color' => '#2563eb', 'background_color' => '#f8fafc', 'border_color' => '#dbe4ef',
		'text_color' => '#1e293b', 'heading_color' => '#0f172a', 'muted_color' => '#475569',
		'border_style' => 'solid', 'border_width' => 1, 'border_radius' => 12,
		'padding_top' => 20, 'padding_right' => 20, 'padding_bottom' => 20, 'padding_left' => 20,
		'margin_top' => 16, 'margin_right' => 13, 'margin_bottom' => 16, 'margin_left' => 13,
		'custom_css' => '',
	);
}

function wcasc_sanitize_design_settings( $input ) {
	$defaults = wcasc_get_design_defaults(); $clean = $defaults;
	$input = is_array( $input ) ? $input : array();
	foreach ( $defaults as $key => $default ) {
		if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) { continue; }
		$value = (string) $input[ $key ];
		if ( false !== strpos( $key, '_color' ) ) {
			$clean[ $key ] = sanitize_hex_color( $value ) ?: $default;
		} elseif ( 'border_style' === $key ) {
			$clean[ $key ] = in_array( $value, array( 'none', 'solid', 'dashed', 'dotted', 'double' ), true ) ? $value : $default;
		} elseif ( 'custom_css' === $key ) {
			// CSS is allowed; literal HTML delimiters must never escape an inline style element.
			$clean[ $key ] = str_replace( array( '<', '>', "\0" ), '', $value );
		} elseif ( is_numeric( $value ) ) {
			$limit = in_array( $key, array( 'border_width', 'border_radius' ), true ) ? 100 : 200;
			$clean[ $key ] = max( 0, min( $limit, (float) $value ) );
		}
	}
	return $clean;
}

function wcasc_get_design_settings() {
	return wcasc_sanitize_design_settings( get_option( 'wcasc_checkout_design', array() ) );
}

function wcasc_get_design_css() {
	$design = wcasc_get_design_settings(); $declarations = array();
	foreach ( array( 'primary_color' => 'primary', 'background_color' => 'background', 'border_color' => 'border-color', 'text_color' => 'text', 'heading_color' => 'heading', 'muted_color' => 'muted' ) as $key => $variable ) {
		$declarations[] = '--wcasc-' . $variable . ':' . $design[ $key ];
	}
	foreach ( array( 'border_width', 'border_radius' ) as $key ) { $declarations[] = '--wcasc-' . str_replace( '_', '-', $key ) . ':' . $design[ $key ] . 'px'; }
	$declarations[] = '--wcasc-border-style:' . $design['border_style'];
	foreach ( array( 'padding', 'margin' ) as $type ) {
		$sides = array();
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) { $sides[] = $design[ $type . '_' . $side ] . 'px'; }
		$declarations[] = '--wcasc-' . $type . ':' . implode( ' ', $sides );
	}
	return '#wcasc-notice-wrap{' . implode( ';', $declarations ) . ';}' . "\n" . $design['custom_css'];
}

function wcasc_handle_design_save() {
	if ( ! isset( $_POST['wcasc_save_design'] ) && ! isset( $_POST['wcasc_reset_design'] ) ) { return; }
	if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'wcasc_save_design', 'wcasc_design_nonce' ) ) { return; }
	$design = isset( $_POST['wcasc_reset_design'] ) ? wcasc_get_design_defaults() : wcasc_sanitize_design_settings( isset( $_POST['wcasc_design'] ) ? wp_unslash( $_POST['wcasc_design'] ) : array() );
	update_option( 'wcasc_checkout_design', $design );
	return __( 'Checkout design saved.', 'wcasc' );
}

function wcasc_render_checkout_design_tab() {
	$design = wcasc_get_design_settings();
	$colors = array( 'primary_color' => __( 'Primary color', 'wcasc' ), 'background_color' => __( 'Background color', 'wcasc' ), 'border_color' => __( 'Border color', 'wcasc' ), 'text_color' => __( 'Text color', 'wcasc' ), 'heading_color' => __( 'Heading color', 'wcasc' ), 'muted_color' => __( 'Description color', 'wcasc' ) );
	?>
	<div class="wcasc-design-layout">
		<div class="wcasc-card"><h2><?php esc_html_e( 'Checkout Design', 'wcasc' ); ?></h2><p class="description"><?php esc_html_e( 'Match the advance payment box to your theme. These settings affect the checkout summary.', 'wcasc' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'wcasc_save_design', 'wcasc_design_nonce' ); ?>
			<table class="form-table"><tbody>
			<?php foreach ( $colors as $key => $label ) : ?>
			<tr><th><label for="wcasc-design-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="text" class="wcasc-color-picker" id="wcasc-design-<?php echo esc_attr( $key ); ?>" name="wcasc_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" data-default-color="<?php echo esc_attr( wcasc_get_design_defaults()[ $key ] ); ?>" /></td></tr>
			<?php endforeach; ?>
			<tr><th><label for="wcasc-design-border_style"><?php esc_html_e( 'Border style', 'wcasc' ); ?></label></th><td><select id="wcasc-design-border_style" name="wcasc_design[border_style]" data-design-key="border_style"><?php foreach ( array( 'none' => __( 'None', 'wcasc' ), 'solid' => __( 'Solid', 'wcasc' ), 'dashed' => __( 'Dashed', 'wcasc' ), 'dotted' => __( 'Dotted', 'wcasc' ), 'double' => __( 'Double', 'wcasc' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $design['border_style'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
			<?php foreach ( array( 'border_width' => __( 'Border width', 'wcasc' ), 'border_radius' => __( 'Corner radius', 'wcasc' ) ) as $key => $label ) : ?>
			<tr><th><label for="wcasc-design-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input type="number" min="0" max="100" step="0.5" class="small-text" id="wcasc-design-<?php echo esc_attr( $key ); ?>" name="wcasc_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" /> px</td></tr>
			<?php endforeach; ?>
			<?php foreach ( array( 'padding' => __( 'Padding (inside)', 'wcasc' ), 'margin' => __( 'Margin (outside)', 'wcasc' ) ) as $type => $label ) : ?>
			<tr><th><?php echo esc_html( $label ); ?></th><td><div class="wcasc-spacing-fields">
			<?php foreach ( array( 'top' => __( 'Top', 'wcasc' ), 'right' => __( 'Right', 'wcasc' ), 'bottom' => __( 'Bottom', 'wcasc' ), 'left' => __( 'Left', 'wcasc' ) ) as $side => $side_label ) : $key = $type . '_' . $side; ?>
			<label><?php echo esc_html( $side_label ); ?><input type="number" min="0" max="200" step="0.5" name="wcasc_design[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $design[ $key ] ); ?>" data-design-key="<?php echo esc_attr( $key ); ?>" /></label>
			<?php endforeach; ?></div><p class="description"><?php esc_html_e( 'All spacing values are in pixels.', 'wcasc' ); ?></p></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<h3><label for="wcasc-custom-css"><?php esc_html_e( 'Custom CSS', 'wcasc' ); ?></label></h3>
			<p class="description"><?php esc_html_e( 'Applied after the design settings on checkout. Use #wcasc-notice-wrap to target this plugin’s summary. Custom CSS is applied after saving.', 'wcasc' ); ?></p>
			<textarea id="wcasc-custom-css" name="wcasc_design[custom_css]" class="large-text code" rows="10" spellcheck="false" placeholder="#wcasc-notice-wrap .wcasc-payment-card { box-shadow: 0 4px 16px rgba(0,0,0,.05); }" aria-label="<?php esc_attr_e( 'Checkout custom CSS', 'wcasc' ); ?>"><?php echo esc_textarea( $design['custom_css'] ); ?></textarea>
			<p><button type="submit" class="button button-primary" name="wcasc_save_design"><?php esc_html_e( 'Save Design', 'wcasc' ); ?></button> <button type="submit" class="button" name="wcasc_reset_design"><?php esc_html_e( 'Reset to defaults', 'wcasc' ); ?></button></p>
		</form></div>
		<aside class="wcasc-card wcasc-design-preview"><h2><?php esc_html_e( 'Live preview', 'wcasc' ); ?></h2><p class="description"><?php esc_html_e( 'Sample amounts. Colors, borders and spacing update as you edit.', 'wcasc' ); ?></p>
			<div class="wcasc-preview-canvas"><div id="wcasc-notice-wrap"><section class="wcasc-payment-card"><h3><?php echo esc_html( wcasc_get_texts()['notice_heading'] ); ?></h3><dl class="wcasc-payment-breakdown"><div><dt><?php esc_html_e( 'Full order total', 'wcasc' ); ?></dt><dd><?php echo wp_kses_post( wc_price( 1000 ) ); ?></dd></div><div class="wcasc-pay-now"><dt><?php esc_html_e( 'Payable Now', 'wcasc' ); ?></dt><dd><?php echo wp_kses_post( wc_price( 200 ) ); ?></dd></div><div><dt><?php esc_html_e( 'Due on delivery', 'wcasc' ); ?></dt><dd><?php echo wp_kses_post( wc_price( 800 ) ); ?></dd></div></dl><p class="wcasc-payment-message"><?php esc_html_e( 'Pay the advance now. Pay the remaining balance on delivery.', 'wcasc' ); ?></p></section></div></div>
		</aside>
	</div>
	<?php
}
