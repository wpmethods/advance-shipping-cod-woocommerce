<?php
/**
 * Plugin Name: AdvShipping WPM
 * Plugin URI:  https://wpmethods.com
 * Description: Collect shipping, fixed or percentage advance payments through online or manual gateways, with the remaining balance due on delivery.
 * Version:     1.4.4
 * Author:      WP Methods, Ajharul Islam
 * Text Domain: advshipping-wpm
 * Requires Plugins: woocommerce
 * Requires at least: 5.2
 * Tested up to: 7.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCASC_VERSION', '1.4.4' );
define( 'WCASC_OPTION_KEY', 'wcasc_selected_shipping_methods' );
define( 'WCASC_TEXTS_KEY', 'wcasc_texts' );
define( 'WCASC_ADVANCE_COST_TYPE_KEY', 'wcasc_advance_cost_type' );
define( 'WCASC_CUSTOM_ADVANCE_PRICE_KEY', 'wcasc_custom_advance_price' );
require_once __DIR__ . '/includes/checkout-design.php';

/* =========================================================
 * 0. Check WooCommerce is active
 * ========================================================= */
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'The "Advance Shipping COD for WooCommerce" plugin requires WooCommerce to be active.', 'wcasc' );
			echo '</p></div>';
		} );
	}
} );

/* =========================================================
 * 1. Default customizable texts
 * ========================================================= */
function wcasc_get_default_texts() {
	return array(
		'notice_heading'        => 'Payment Plan',
		'notice_message'        => 'Pay {advance_amount} now using your preferred payment method. Pay the remaining {due_amount} on delivery.',
		'order_review_heading'  => 'Payable Now',
		'order_note'            => 'Advance payment of {advance_amount} confirmed. Collect {due_amount} as COD upon delivery.',
		'customer_advance_line' => 'Advance payment: {advance_amount}.',
		'customer_due_line'     => 'Pay {due_amount} on delivery (Cash on Delivery).',
		'admin_advance_label'   => 'Advance Payment',
		'admin_due_label'       => 'Due (COD - Collectible on Delivery)',
		'column_label'          => 'Due (COD)',
	);
}

function wcasc_get_texts() {
	$saved = get_option( WCASC_TEXTS_KEY, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$defaults = wcasc_get_default_texts();
	// Refresh previously saved default wording while preserving merchant customization.
	$legacy = array(
		'notice_heading' => 'Advance Shipping Payment',
		'notice_message' => 'Cash on Delivery is not available for this shipping method. Please pay the shipping charge of {shipping_amount} online now. The remaining product price of {due_amount} will be collected as Cash on Delivery at the time of delivery.',
		'order_note' => 'Advance shipping charge of {advance_amount} has been paid online. The product price of {due_amount} must be collected as COD upon delivery.',
		'customer_advance_line' => 'Shipping charge of {advance_amount} has been paid online in advance.',
		'customer_due_line' => 'The product price of {due_amount} must be paid in cash (Cash on Delivery) at the time of delivery.',
		'admin_advance_label' => 'Advance Paid (Shipping)',
	);
	foreach ( $legacy as $key => $value ) {
		if ( isset( $saved[ $key ] ) && $value === $saved[ $key ] ) {
			$saved[ $key ] = $defaults[ $key ];
		}
	}
	return wp_parse_args( $saved, $defaults );
}

function wcasc_get_advance_cost_type() {
	$cost_type = get_option( WCASC_ADVANCE_COST_TYPE_KEY, 'shipping_method' );
	$allowed   = array_keys( wcasc_get_payment_modes() );

	if ( ! in_array( $cost_type, $allowed, true ) ) {
		return 'shipping_method';
	}

	return $cost_type;
}

function wcasc_get_custom_advance_price() {
	$price = get_option( WCASC_CUSTOM_ADVANCE_PRICE_KEY, '' );
	if ( ! is_numeric( $price ) ) {
		return 0;
	}
	return max( 0, (float) $price );
}

function wcasc_get_payment_modes() {
	return array(
		'shipping_method' => __( 'Shipping cost only', 'wcasc' ),
		'percentage' => __( 'Percentage', 'wcasc' ),
		'custom_price' => __( 'Fixed amount', 'wcasc' ),
		'both' => __( 'Shipping + fixed amount', 'wcasc' ),
		'shipping_percentage' => __( 'Shipping + percentage', 'wcasc' ),
		'combined' => __( 'Shipping + percentage + fixed amount', 'wcasc' ),
		'full_payment' => __( 'Full Payment', 'wcasc' ),
	);
}

function wcasc_get_enabled_modes() {
	$modes = array_values( array_intersect( (array) get_option( 'wcasc_payment_modes', array( wcasc_get_advance_cost_type() ) ), array_keys( wcasc_get_payment_modes() ) ) );
	return $modes ? $modes : array( wcasc_get_advance_cost_type() );
}

function wcasc_get_custom_mode_labels() {
	$saved = get_option( 'wcasc_payment_mode_labels', array() );
	$labels = array();
	foreach ( wcasc_get_payment_modes() as $mode => $default ) {
		$labels[ $mode ] = is_array( $saved ) && isset( $saved[ $mode ] ) && is_string( $saved[ $mode ] ) ? sanitize_text_field( $saved[ $mode ] ) : '';
	}
	return $labels;
}

function wcasc_get_checkout_mode_labels() {
	$labels = wcasc_get_payment_modes();
	$custom = wcasc_get_custom_mode_labels();
	$percentage = max( 0, min( 100, (float) get_option( 'wcasc_advance_percentage', 20 ) ) );
	foreach ( $labels as $mode => $label ) {
		if ( '' !== $custom[ $mode ] ) {
			$labels[ $mode ] = str_replace( '{percentage}', (string) $percentage, $custom[ $mode ] );
		} elseif ( in_array( $mode, array( 'percentage', 'shipping_percentage', 'combined' ), true ) ) {
			$labels[ $mode ] .= ' (' . $percentage . '%)';
		}
	}
	return $labels;
}

function wcasc_get_chosen_mode() {
	$modes = wcasc_get_enabled_modes();
	$chosen = function_exists( 'WC' ) && WC()->session ? WC()->session->get( 'wcasc_payment_mode' ) : '';
	return in_array( $chosen, $modes, true ) ? $chosen : ( in_array( wcasc_get_advance_cost_type(), $modes, true ) ? wcasc_get_advance_cost_type() : $modes[0] );
}

/** All sources use the discounted, tax-inclusive total; never collect more than the order. */
function wcasc_calculate_advance_amount( $shipping_total = 0, $grand_total = null, $mode = null ) {
	$shipping_total = max( 0, (float) $shipping_total );
	if ( null === $grand_total ) {
		$grand_total = function_exists( 'WC' ) && WC()->cart ? wcasc_get_cart_grand_total() : $shipping_total;
	}
	$grand_total = max( 0, (float) $grand_total );
	$mode = null === $mode ? wcasc_get_chosen_mode() : $mode;
	if ( 'full_payment' === $mode ) {
		return (float) wc_format_decimal( $grand_total, wc_get_price_decimals() );
	}
	$percentage = max( 0, min( 100, (float) get_option( 'wcasc_advance_percentage', 20 ) ) );
	$base = 'excluding_shipping' === get_option( 'wcasc_percentage_base', 'order_total' ) ? max( 0, $grand_total - $shipping_total ) : $grand_total;
	$amount = 0;
	if ( in_array( $mode, array( 'shipping_method', 'both', 'shipping_percentage', 'combined' ), true ) ) {
		$amount += $shipping_total;
	}
	if ( in_array( $mode, array( 'custom_price', 'both', 'combined' ), true ) ) {
		$amount += wcasc_get_custom_advance_price();
	}
	if ( in_array( $mode, array( 'percentage', 'shipping_percentage', 'combined' ), true ) ) {
		$amount += $base * $percentage / 100;
	}
	return (float) wc_format_decimal( min( $grand_total, max( 0, $amount ) ), wc_get_price_decimals() );
}

function wcasc_get_cart_grand_total() {
	// Read unfiltered totals to avoid recursion while gateway payment fields are rendered.
	$totals = WC()->cart->get_totals();
	return isset( $totals['total'] ) ? (float) $totals['total'] : 0;
}

function wcasc_calculate_due_amount( $grand_total, $shipping_total = 0 ) {
	$grand_total = (float) $grand_total;
	$advance_amount = wcasc_calculate_advance_amount( $shipping_total, $grand_total );
	return max( 0, $grand_total - $advance_amount );
}

/**
 * Replace {placeholder} tokens in a text string with formatted price HTML.
 *
 * @param string   $text        Raw text with {placeholder} tokens.
 * @param array    $replacements Associative array: placeholder => value (already formatted, e.g. via wc_price()).
 * @return string
 */
function wcasc_format_text( $text, $replacements ) {
	foreach ( $replacements as $key => $value ) {
		$text = str_replace( '{' . $key . '}', $value, $text );
	}
	return $text;
}

/* =========================================================
 * 2. Admin settings pages
 * ========================================================= */
add_action( 'admin_menu', 'wcasc_add_admin_menu' );
function wcasc_add_admin_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'Advance Shipping COD', 'wcasc' ),
		__( 'Advance Shipping COD', 'wcasc' ),
		'manage_woocommerce',
		'wcasc-settings',
		'wcasc_render_settings_page'
	);
}

add_action( 'admin_enqueue_scripts', 'wcasc_enqueue_admin_assets' );
function wcasc_enqueue_admin_assets( $hook_suffix ) {
	if ( 'woocommerce_page_wcasc-settings' !== $hook_suffix ) {
		return;
	}

	$plugin_url = plugin_dir_url( __FILE__ );
	wp_enqueue_style( 'wcasc-admin-css', $plugin_url . 'assets/css/wcasc-admin.css', array(), WCASC_VERSION );
	wp_enqueue_script( 'wcasc-admin-js', $plugin_url . 'assets/js/wcasc-admin.js', array( 'jquery' ), WCASC_VERSION, true );
	if ( isset( $_GET['tab'] ) && 'design' === $_GET['tab'] ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'wcasc-design-preview', $plugin_url . 'assets/css/wcasc-checkout.css', array(), WCASC_VERSION );
		wp_enqueue_script( 'wcasc-design-admin', $plugin_url . 'assets/js/wcasc-design-admin.js', array( 'jquery', 'wp-color-picker' ), WCASC_VERSION, true );
		$editor_settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		wp_localize_script( 'wcasc-design-admin', 'wcascDesignEditor', array( 'settings' => $editor_settings ) );
	}
}

/**
 * Get all shipping methods (with rate_id) from every shipping zone.
 */
function wcasc_get_all_shipping_methods() {
	$all = array();

	if ( class_exists( 'WC_Shipping_Zones' ) ) {
		$zones = WC_Shipping_Zones::get_zones();
		foreach ( $zones as $zone_data ) {
			$zone_name = ! empty( $zone_data['zone_name'] ) ? $zone_data['zone_name'] : __( '(Unnamed)', 'wcasc' );
			if ( ! empty( $zone_data['shipping_methods'] ) ) {
				foreach ( $zone_data['shipping_methods'] as $method ) {
					$all[] = array(
						'rate_id' => $method->get_rate_id(),
						'zone'    => $zone_name,
						'title'   => $method->get_title() ? $method->get_title() : $method->method_title,
						'enabled' => 'yes' === $method->enabled,
					);
				}
			}
		}
	}

	// "Rest of the World" (zone 0)
	if ( class_exists( 'WC_Shipping_Zone' ) ) {
		$zone0    = new WC_Shipping_Zone( 0 );
		$methods0 = $zone0->get_shipping_methods( true );
		foreach ( $methods0 as $method ) {
			$all[] = array(
				'rate_id' => $method->get_rate_id(),
				'zone'    => __( 'Rest of the World', 'wcasc' ),
				'title'   => $method->get_title() ? $method->get_title() : $method->method_title,
				'enabled' => 'yes' === $method->enabled,
			);
		}
	}

	return $all;
}

function wcasc_get_checkout_positions() {
	return array( 'before_total' => __( 'Above order total (default)', 'wcasc' ), 'subtotal' => __( 'Below subtotal', 'wcasc' ), 'before_payment' => __( 'Above payment methods', 'wcasc' ) );
}

function wcasc_get_checkout_position() {
	$position = get_option( 'wcasc_checkout_position', 'before_total' );
	// Move the previous subtotal placement to the requested default until explicitly saved again.
	if ( 'subtotal' === $position && ! get_option( 'wcasc_checkout_position_updated', false ) ) {
		return 'before_total';
	}
	return array_key_exists( $position, wcasc_get_checkout_positions() ) ? $position : 'before_total';
}

function wcasc_render_workflow_settings() {
	$custom_labels = wcasc_get_custom_mode_labels();
	?>
	<tr><th><?php esc_html_e( 'Payment options', 'wcasc' ); ?></th><td>
		<p class="description wcasc-options-help"><?php esc_html_e( 'Enable the options customers can use. Choose one default from the enabled options.', 'wcasc' ); ?></p>
		<table class="wcasc-options-table">
			<thead><tr><th scope="col"><?php esc_html_e( 'Enable', 'wcasc' ); ?></th><th scope="col"><?php esc_html_e( 'Advance payment', 'wcasc' ); ?></th><th scope="col"><?php esc_html_e( 'Checkout label', 'wcasc' ); ?></th><th scope="col"><?php esc_html_e( 'Default', 'wcasc' ); ?></th></tr></thead>
			<tbody><?php foreach ( wcasc_get_payment_modes() as $mode => $label ) : ?>
			<tr>
				<td><input type="checkbox" id="wcasc-enable-<?php echo esc_attr( $mode ); ?>" name="wcasc_payment_modes[]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( in_array( $mode, wcasc_get_enabled_modes(), true ) ); ?> aria-label="<?php echo esc_attr( sprintf( __( 'Enable %s', 'wcasc' ), $label ) ); ?>" /></td>
				<td><label for="wcasc-enable-<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( $label ); ?></label></td>
				<td><input class="wcasc-mode-label" type="text" name="wcasc_mode_labels[<?php echo esc_attr( $mode ); ?>]" value="<?php echo esc_attr( $custom_labels[ $mode ] ); ?>" placeholder="<?php echo esc_attr( $label ); ?>" maxlength="120" aria-label="<?php echo esc_attr( sprintf( __( 'Checkout label for %s', 'wcasc' ), $label ) ); ?>" /></td>
				<td><input type="radio" name="wcasc_advance_cost_type" value="<?php echo esc_attr( $mode ); ?>" <?php checked( wcasc_get_advance_cost_type(), $mode ); ?> aria-label="<?php echo esc_attr( sprintf( __( 'Use %s as default', 'wcasc' ), $label ) ); ?>" /></td>
			</tr>
			<?php endforeach; ?></tbody>
		</table>
		<p class="description" id="wcasc-choice-help"><?php esc_html_e( 'One enabled option: applied automatically. Multiple enabled options: customers choose at checkout.', 'wcasc' ); ?></p>
		<p class="description"><?php esc_html_e( 'Rename options using Checkout label. Leave blank for the original name. Use {percentage}% to show the configured percentage in your label.', 'wcasc' ); ?></p>
	</td></tr>
	<tr class="wcasc-fixed-setting"><th><label for="wcasc_custom_advance_price"><?php esc_html_e( 'Fixed advance amount', 'wcasc' ); ?></label></th><td>
		<input type="number" step="0.01" min="0" class="regular-text" id="wcasc_custom_advance_price" name="wcasc_custom_advance_price" value="<?php echo esc_attr( wcasc_get_custom_advance_price() ); ?>" />
		<p class="description"><?php esc_html_e( 'Amount in your store currency. Added to shipping when a combined option is enabled.', 'wcasc' ); ?></p>
	</td></tr>
	<tr class="wcasc-percentage-setting">
		<th><label for="wcasc_advance_percentage"><?php esc_html_e( 'Advance percentage', 'wcasc' ); ?></label></th>
		<td>
				<div class="wcasc-input-suffix"><input id="wcasc_advance_percentage" name="wcasc_advance_percentage" type="number" min="0" max="100" step="0.01" value="<?php echo esc_attr( get_option( 'wcasc_advance_percentage', 20 ) ); ?>" /><span aria-hidden="true">%</span></div>
				<p class="description"><?php esc_html_e( 'Percentage collected in advance. Combined options add shipping or a fixed amount. The advance cannot exceed the order total.', 'wcasc' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="wcasc_checkout_position"><?php esc_html_e( 'Checkout position', 'wcasc' ); ?></label></th>
		<td>
				<select id="wcasc_checkout_position" name="wcasc_checkout_position">
				<?php foreach ( wcasc_get_checkout_positions() as $position => $label ) : ?>
				<option value="<?php echo esc_attr( $position ); ?>" <?php selected( wcasc_get_checkout_position(), $position ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Uses the classic WooCommerce checkout, including compatible CartFlows checkout templates.', 'wcasc' ); ?></p>
		</td>
	</tr>
	<tr class="wcasc-percentage-setting"><th><label for="wcasc_percentage_base"><?php esc_html_e( 'Calculate percentage of', 'wcasc' ); ?></label></th><td>
		<select id="wcasc_percentage_base" name="wcasc_percentage_base">
			<option value="order_total" <?php selected( get_option( 'wcasc_percentage_base', 'order_total' ), 'order_total' ); ?>><?php esc_html_e( 'Full order total after discounts, including tax and fees', 'wcasc' ); ?></option>
			<option value="excluding_shipping" <?php selected( get_option( 'wcasc_percentage_base', 'order_total' ), 'excluding_shipping' ); ?>><?php esc_html_e( 'Order total excluding shipping and shipping tax', 'wcasc' ); ?></option>
		</select>
	</td></tr>
	<tr><th><?php esc_html_e( 'Cash on Delivery', 'wcasc' ); ?></th><td>
		<label><input type="checkbox" name="wcasc_show_cod" value="yes" <?php checked( wcasc_should_show_cod() ); ?> /> <?php esc_html_e( 'Allow full payment on delivery', 'wcasc' ); ?></label>
		<p class="description"><?php esc_html_e( 'Show COD alongside advance payment gateways. Customers choosing COD pay the full order amount on delivery, with no advance. COD must be enabled and available in WooCommerce payment settings.', 'wcasc' ); ?></p>
	</td></tr>
	<tr><th><?php esc_html_e( 'Advance payment gateways', 'wcasc' ); ?></th><td><fieldset>
		<?php foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) : if ( 'cod' === $id ) { continue; } ?>
		<label style="display:block;margin-bottom:8px"><input type="checkbox" name="wcasc_gateways[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, (array) get_option( 'wcasc_gateways', array() ), true ) ); ?> /> <?php echo esc_html( $gateway->get_title() ); ?> <code><?php echo esc_html( $id ); ?></code></label>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( 'Leave all unchecked to allow every enabled non-COD gateway. Select several to offer multiple payment methods. Gateway availability rules still apply.', 'wcasc' ); ?></p>
	</fieldset></td></tr>
	<?php
}

function wcasc_render_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$design_notice = wcasc_handle_design_save();
	$save_notices = $design_notice ? array( $design_notice ) : array();
	$active_tab = isset( $_GET['tab'] ) && 'design' === $_GET['tab'] ? 'design' : 'settings';

	/* ---- Save shipping method selection ---- */
	if ( isset( $_POST['wcasc_save'] ) && check_admin_referer( 'wcasc_save_settings', 'wcasc_nonce' ) ) {
		$selected = isset( $_POST['wcasc_methods'] ) && is_array( $_POST['wcasc_methods'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wcasc_methods'] ) ) : array();
		$advance_cost_type = isset( $_POST['wcasc_advance_cost_type'] ) ? sanitize_key( wp_unslash( $_POST['wcasc_advance_cost_type'] ) ) : 'shipping_method';
		$custom_advance_price = isset( $_POST['wcasc_custom_advance_price'] ) ? sanitize_text_field( wp_unslash( $_POST['wcasc_custom_advance_price'] ) ) : '';

		if ( ! array_key_exists( $advance_cost_type, wcasc_get_payment_modes() ) ) {
			$advance_cost_type = 'shipping_method';
		}

		update_option( WCASC_OPTION_KEY, $selected );
		update_option( WCASC_CUSTOM_ADVANCE_PRICE_KEY, max( 0, (float) wc_format_decimal( $custom_advance_price ) ) );
		$modes = isset( $_POST['wcasc_payment_modes'] ) && is_array( $_POST['wcasc_payment_modes'] ) ? array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST['wcasc_payment_modes'] ) ), array_keys( wcasc_get_payment_modes() ) ) : array();
		if ( ! $modes ) {
			$modes = array( $advance_cost_type );
		} elseif ( ! in_array( $advance_cost_type, $modes, true ) ) {
			$advance_cost_type = reset( $modes );
		}
		update_option( WCASC_ADVANCE_COST_TYPE_KEY, $advance_cost_type );
		update_option( 'wcasc_payment_modes', array_values( array_unique( $modes ) ) );
		if ( isset( $_POST['wcasc_mode_labels'] ) && is_array( $_POST['wcasc_mode_labels'] ) ) {
			$posted_labels = wp_unslash( $_POST['wcasc_mode_labels'] );
			$mode_labels = array();
			foreach ( wcasc_get_payment_modes() as $mode => $label ) {
				$mode_labels[ $mode ] = isset( $posted_labels[ $mode ] ) && is_string( $posted_labels[ $mode ] ) ? sanitize_text_field( $posted_labels[ $mode ] ) : '';
			}
			update_option( 'wcasc_payment_mode_labels', $mode_labels );
		}
		update_option( 'wcasc_advance_percentage', isset( $_POST['wcasc_advance_percentage'] ) ? max( 0, min( 100, (float) wc_format_decimal( wp_unslash( $_POST['wcasc_advance_percentage'] ) ) ) ) : 20 );
		update_option( 'wcasc_percentage_base', isset( $_POST['wcasc_percentage_base'] ) && 'excluding_shipping' === $_POST['wcasc_percentage_base'] ? 'excluding_shipping' : 'order_total' );
		$position = isset( $_POST['wcasc_checkout_position'] ) ? sanitize_key( wp_unslash( $_POST['wcasc_checkout_position'] ) ) : 'before_total';
		update_option( 'wcasc_checkout_position', array_key_exists( $position, wcasc_get_checkout_positions() ) ? $position : 'before_total' );
		update_option( 'wcasc_checkout_position_updated', true );
		$gateway_ids = isset( $_POST['wcasc_gateways'] ) && is_array( $_POST['wcasc_gateways'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wcasc_gateways'] ) ) : array();
		update_option( 'wcasc_gateways', array_values( array_diff( $gateway_ids, array( 'cod' ) ) ) );
		update_option( 'wcasc_show_cod', isset( $_POST['wcasc_show_cod'] ) && 'yes' === $_POST['wcasc_show_cod'] ? 'yes' : 'no' );
		$save_notices[] = __( 'Shipping method and advance cost settings saved successfully.', 'wcasc' );
	}

	/* ---- Save customizable texts ---- */
	if ( isset( $_POST['wcasc_save_texts'] ) && check_admin_referer( 'wcasc_save_texts', 'wcasc_texts_nonce' ) ) {
		$defaults = wcasc_get_default_texts();
		$texts    = array();
		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $_POST['wcasc_text'][ $key ] ) ) {
				$texts[ $key ] = sanitize_textarea_field( wp_unslash( $_POST['wcasc_text'][ $key ] ) );
			} else {
				$texts[ $key ] = $default_value;
			}
		}
		update_option( WCASC_TEXTS_KEY, $texts );
		$save_notices[] = __( 'Text settings saved successfully.', 'wcasc' );
	}

	$selected            = get_option( WCASC_OPTION_KEY, array() );
	$methods             = wcasc_get_all_shipping_methods();
	$texts               = wcasc_get_texts();
	?>
	<div class="wrap wcasc-settings-wrap">
		<div class="wcasc-hero">
			<h1><?php esc_html_e( 'Advance Shipping COD Settings', 'wcasc' ); ?></h1>
			<p>
				<?php esc_html_e( 'Control how advance payment works for selected shipping methods. Choose the cost type, manage trigger methods, and customize checkout messages from one polished admin dashboard.', 'wcasc' ); ?>
			</p>
		</div>

		<?php foreach ( $save_notices as $message ) : ?>
		<div class="wcasc-save-notice" role="status"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endforeach; ?>
		<nav class="nav-tab-wrapper wcasc-settings-tabs" aria-label="<?php esc_attr_e( 'Plugin settings', 'wcasc' ); ?>">
			<a class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wcasc-settings' ) ); ?>"><?php esc_html_e( 'Payment Settings', 'wcasc' ); ?></a>
			<a class="nav-tab <?php echo 'design' === $active_tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wcasc-settings&tab=design' ) ); ?>"><?php esc_html_e( 'Checkout Design', 'wcasc' ); ?></a>
		</nav>
		<?php if ( 'design' === $active_tab ) : wcasc_render_checkout_design_tab(); else : ?>
		<div class="wcasc-settings-grid">
			<div class="wcasc-main-column">
				<div class="wcasc-card">
					<h2><?php esc_html_e( 'Advance Cost Settings', 'wcasc' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'wcasc_save_settings', 'wcasc_nonce' ); ?>
						<table class="form-table" style="max-width:900px;">
							<?php wcasc_render_workflow_settings(); ?>
						</table>

						<div class="wcasc-card">
							<h3 style="margin-bottom:8px;"><?php esc_html_e( 'Shipping Methods', 'wcasc' ); ?></h3>
							<p style="margin-top:0; color:#64748b;">
								<?php esc_html_e( 'Select the shipping methods that should trigger the advance payment flow at checkout.', 'wcasc' ); ?>
							</p>
							<?php if ( empty( $methods ) ) : ?>
								<div class="notice notice-warning"><p>
									<?php esc_html_e( 'No shipping methods found. Please set up shipping zones/methods first under WooCommerce → Settings → Shipping.', 'wcasc' ); ?>
								</p></div>
							<?php else : ?>
								<table class="wcasc-table">
									<thead>
										<tr>
											<th style="width:40px;"></th>
											<th><?php esc_html_e( 'Zone', 'wcasc' ); ?></th>
											<th><?php esc_html_e( 'Shipping Method', 'wcasc' ); ?></th>
											<th><?php esc_html_e( 'Status', 'wcasc' ); ?></th>
											<th><?php esc_html_e( 'Rate ID', 'wcasc' ); ?></th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ( $methods as $m ) : ?>
										<tr>
											<td>
												<input type="checkbox" name="wcasc_methods[]"
													value="<?php echo esc_attr( $m['rate_id'] ); ?>"
													<?php checked( in_array( $m['rate_id'], $selected, true ) ); ?> />
											</td>
											<td><?php echo esc_html( $m['zone'] ); ?></td>
											<td><?php echo esc_html( $m['title'] ); ?></td>
											<td>
												<?php if ( $m['enabled'] ) : ?>
													<span class="wcasc-badge wcasc-badge--enabled">● <?php esc_html_e( 'Enabled', 'wcasc' ); ?></span>
												<?php else : ?>
													<span class="wcasc-badge wcasc-badge--disabled">● <?php esc_html_e( 'Disabled', 'wcasc' ); ?></span>
												<?php endif; ?>
											</td>
											<td><code><?php echo esc_html( $m['rate_id'] ); ?></code></td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>

						<p style="margin-top:18px;">
							<button type="submit" name="wcasc_save" class="button button-primary">
								<?php esc_html_e( 'Save Settings', 'wcasc' ); ?>
							</button>
						</p>
					</form>
				</div>

			</div>

			<aside class="wcasc-sidebar">
				<div class="wcasc-card wcasc-texts-card">
					<h2><?php esc_html_e( 'Customize Texts', 'wcasc' ); ?></h2>
					<p class="description" style="margin-top:0;">
						<?php esc_html_e( 'Edit the customer-facing messages from checkout to order confirmation.', 'wcasc' ); ?>
					</p>
					<form method="post">
						<?php wp_nonce_field( 'wcasc_save_texts', 'wcasc_texts_nonce' ); ?>
						<table class="form-table" style="max-width:900px;">
							<tr>
								<th><label for="wcasc_notice_heading"><?php esc_html_e( 'Checkout Notice Heading', 'wcasc' ); ?></label></th>
								<td><input type="text" class="regular-text" id="wcasc_notice_heading" name="wcasc_text[notice_heading]" value="<?php echo esc_attr( $texts['notice_heading'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="wcasc_notice_message"><?php esc_html_e( 'Checkout Notice Message', 'wcasc' ); ?></label><br /><small><?php esc_html_e( 'Placeholders: {advance_amount}, {shipping_amount}, {due_amount}', 'wcasc' ); ?></small></th>
								<td><textarea class="large-text" rows="3" id="wcasc_notice_message" name="wcasc_text[notice_message]"><?php echo esc_textarea( $texts['notice_message'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="wcasc_order_review_heading"><?php esc_html_e( 'Checkout "Total" Label Override', 'wcasc' ); ?></label></th>
								<td><input type="text" class="regular-text" id="wcasc_order_review_heading" name="wcasc_text[order_review_heading]" value="<?php echo esc_attr( $texts['order_review_heading'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="wcasc_order_note"><?php esc_html_e( 'Order Note (added after payment)', 'wcasc' ); ?></label><br /><small><?php esc_html_e( 'Placeholders: {advance_amount}, {due_amount}', 'wcasc' ); ?></small></th>
								<td><textarea class="large-text" rows="2" id="wcasc_order_note" name="wcasc_text[order_note]"><?php echo esc_textarea( $texts['order_note'] ); ?></textarea></td>
							</tr>
							<tr>
								<th><label for="wcasc_customer_advance_line"><?php esc_html_e( 'Customer Order View — Advance Line', 'wcasc' ); ?></label><br /><small><?php esc_html_e( 'Placeholder: {advance_amount}', 'wcasc' ); ?></small></th>
								<td><input type="text" class="large-text" id="wcasc_customer_advance_line" name="wcasc_text[customer_advance_line]" value="<?php echo esc_attr( $texts['customer_advance_line'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="wcasc_customer_due_line"><?php esc_html_e( 'Customer Order View — Due Line', 'wcasc' ); ?></label><br /><small><?php esc_html_e( 'Placeholder: {due_amount}', 'wcasc' ); ?></small></th>
								<td><input type="text" class="large-text" id="wcasc_customer_due_line" name="wcasc_text[customer_due_line]" value="<?php echo esc_attr( $texts['customer_due_line'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="wcasc_admin_advance_label"><?php esc_html_e( 'Admin Order Page — Advance Label', 'wcasc' ); ?></label></th>
								<td><input type="text" class="regular-text" id="wcasc_admin_advance_label" name="wcasc_text[admin_advance_label]" value="<?php echo esc_attr( $texts['admin_advance_label'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="wcasc_admin_due_label"><?php esc_html_e( 'Admin Order Page — Due Label', 'wcasc' ); ?></label></th>
								<td><input type="text" class="regular-text" id="wcasc_admin_due_label" name="wcasc_text[admin_due_label]" value="<?php echo esc_attr( $texts['admin_due_label'] ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="wcasc_column_label"><?php esc_html_e( 'Orders List Column Label', 'wcasc' ); ?></label></th>
								<td><input type="text" class="regular-text" id="wcasc_column_label" name="wcasc_text[column_label]" value="<?php echo esc_attr( $texts['column_label'] ); ?>" /></td>
							</tr>
						</table>
						<p>
							<button type="submit" name="wcasc_save_texts" class="button button-primary">
								<?php esc_html_e( 'Save Texts', 'wcasc' ); ?>
							</button>
						</p>
					</form>
				</div>
				<div class="wcasc-card">
					<h3><?php esc_html_e( 'Support & Community', 'wcasc' ); ?></h3>
					<div class="wcasc-support-list">
						<a href="https://buymeacoffee.com/ajharrashed" target="_blank" rel="noopener noreferrer">☕ <?php esc_html_e( 'Buy Me a Coffee', 'wcasc' ); ?></a>
						<a href="https://youtube.com/techtriggerbd" target="_blank" rel="noopener noreferrer">▶ <?php esc_html_e( 'YouTube Channel', 'wcasc' ); ?></a>
						<a href="https://www.facebook.com/techtriggerr" target="_blank" rel="noopener noreferrer">📘 <?php esc_html_e( 'Facebook Page', 'wcasc' ); ?></a>
						<a href="https://www.wpmethods.com" target="_blank" rel="noopener noreferrer">🌐 <?php esc_html_e( 'Website', 'wcasc' ); ?></a>
					</div>
				</div>

				<div class="wcasc-card">
					<h3><?php esc_html_e( 'Quick Tips', 'wcasc' ); ?></h3>
					<ul class="wcasc-tip-list">
						<li><?php esc_html_e( 'Use By Shipping Method for normal shipping-based advance payments.', 'wcasc' ); ?></li>
						<li><?php esc_html_e( 'Use By Custom Price for a fixed advance amount.', 'wcasc' ); ?></li>
						<li><?php esc_html_e( 'Use Both to combine shipping cost with a custom amount.', 'wcasc' ); ?></li>
					</ul>
				</div>
			</aside>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

/* =========================================================
 * 3. Helper functions
 * ========================================================= */
function wcasc_get_selected_methods() {
	return (array) get_option( WCASC_OPTION_KEY, array() );
}

/**
 * Reliably determine which shipping method is "chosen" for each cart package.
 *
 * IMPORTANT: We intentionally do NOT read WC()->session->get('chosen_shipping_methods')
 * directly. That session key is only written once WooCommerce stores an explicit
 * customer selection. When a zone/package has only ONE available shipping method
 * (a very common setup), WooCommerce auto-selects it for display purposes but never
 * writes it to that session key — so a direct session read stays empty and any
 * logic built on it (like this plugin's notice) silently never fires.
 *
 * wc_get_chosen_shipping_method_for_package() is WooCommerce's own core resolver:
 * it checks the session first, and if nothing is stored, correctly falls back to
 * the first/only available rate for that package — matching what is actually
 * shown/selected on the front end.
 */
function wcasc_get_chosen_shipping_method_ids() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return array();
	}

	$chosen_ids = array();

	// IMPORTANT: WC()->cart->get_shipping_packages() only returns RAW package
	// data (cart items + destination) — it never contains a 'rates' key. The
	// packages that actually contain calculated rates (which is what
	// wc_get_chosen_shipping_method_for_package() needs) live in
	// WC()->shipping()->get_packages(), which WooCommerce populates earlier in
	// the checkout template render (wc_cart_totals_shipping_html()). Using
	// get_shipping_packages() here meant $package['rates'] was never set, so
	// this always fell back to an empty session and the notice never showed
	// — even on first page load.
	$packages = array();
	if ( function_exists( 'WC' ) && WC()->shipping() && method_exists( WC()->shipping(), 'get_packages' ) ) {
		$packages = WC()->shipping()->get_packages();
	}

	// Safety net: if rates haven't been calculated yet for any reason
	// (e.g. hook fired very early), calculate them now.
	if ( empty( $packages ) && WC()->shipping() ) {
		$packages = WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
	}

	foreach ( $packages as $package_key => $package ) {
		$method_id = '';

		if ( function_exists( 'wc_get_chosen_shipping_method_for_package' )
			&& isset( $package['rates'] ) && is_array( $package['rates'] ) ) {
			$method_id = wc_get_chosen_shipping_method_for_package( $package_key, $package );
		} else {
			$session_methods = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
			$method_id       = isset( $session_methods[ $package_key ] ) ? $session_methods[ $package_key ] : '';
		}

		if ( ! empty( $method_id ) ) {
			$chosen_ids[] = $method_id;
		}
	}

	return $chosen_ids;
}

function wcasc_is_selected_shipping_chosen() {
	$chosen   = wcasc_get_chosen_shipping_method_ids();
	$selected = wcasc_get_selected_methods();

	if ( empty( $chosen ) || empty( $selected ) ) {
		return false;
	}

	foreach ( $chosen as $method ) {
		if ( in_array( $method, $selected, true ) ) {
			return true;
		}
	}
	return false;
}

function wcasc_order_uses_selected_shipping( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}
	$selected = wcasc_get_selected_methods();
	if ( empty( $selected ) ) {
		return false;
	}
	foreach ( $order->get_items( 'shipping' ) as $item ) {
		$rate_id = $item->get_method_id() . ':' . $item->get_instance_id();
		if ( in_array( $rate_id, $selected, true ) ) {
			return true;
		}
	}
	return false;
}

/* =========================================================
 * 4. Hide COD when selected shipping method is chosen
 * ========================================================= */
add_filter( 'woocommerce_available_payment_gateways', 'wcasc_hide_cod_when_selected_shipping' );
function wcasc_should_show_cod() {
	return 'yes' === get_option( 'wcasc_show_cod', 'no' );
}

function wcasc_is_cod_checkout_selected() {
	return wcasc_should_show_cod() && ! is_checkout_pay_page() && WC()->session && 'cod' === WC()->session->get( 'chosen_payment_method' );
}

function wcasc_hide_cod_when_selected_shipping( $gateways ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $gateways;
	}
	$advance_order = false;
	if ( is_checkout_pay_page() ) {
		$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		$advance_order = $order && 'yes' === $order->get_meta( '_wcasc_is_advance_order' );
		if ( ! $advance_order ) {
			return $gateways;
		}
	}
	if ( $advance_order || ( is_checkout() && WC()->cart && wcasc_is_selected_shipping_chosen() ) ) {
		if ( ! $advance_order ) {
			$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
			if ( wcasc_calculate_advance_amount( $shipping, wcasc_get_cart_grand_total() ) <= 0 ) {
				return isset( $gateways['cod'] ) ? array( 'cod' => $gateways['cod'] ) : array();
			}
		}
		$show_cod = ! $advance_order && wcasc_should_show_cod();
		if ( ! $show_cod ) {
			unset( $gateways['cod'] );
		}
		$allowed = (array) get_option( 'wcasc_gateways', array() );
		if ( $allowed ) {
			if ( $show_cod ) {
				$allowed[] = 'cod';
			}
			$gateways = array_intersect_key( $gateways, array_flip( $allowed ) );
		}
	}
	return $gateways;
}

require_once __DIR__ . '/includes/checkout.php';

require_once __DIR__ . '/includes/order-payments.php';

/* =========================================================
 * 9. Show Advance / Due info on customer order view & emails
 * ========================================================= */
add_action( 'woocommerce_order_details_after_order_table', 'wcasc_customer_order_advance_info' );
add_action( 'woocommerce_email_after_order_table', 'wcasc_customer_order_advance_info', 10, 1 );
function wcasc_customer_order_advance_info( $order ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) {
		return;
	}
	$texts   = wcasc_get_texts();
	$advance = wc_price( $order->get_meta( '_wcasc_advance_amount' ), array( 'currency' => $order->get_currency() ) );
	$due     = wc_price( wcasc_get_outstanding_due( $order ), array( 'currency' => $order->get_currency() ) );

	$advance_line = wcasc_format_text( $texts['customer_advance_line'], array( 'advance_amount' => $advance ) );
	$due_line     = wcasc_format_text( $texts['customer_due_line'], array( 'due_amount' => $due ) );
	if ( wcasc_get_outstanding_due( $order ) <= 0 ) {
		$due_line = (float) $order->get_meta( '_wcasc_due_amount' ) <= 0 ? __( 'No payment is due on delivery.', 'wcasc' ) : __( 'Delivery balance fully collected.', 'wcasc' );
	}

	echo '<div style="margin:15px 0;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">';
	echo '<p>' . esc_html( wcasc_get_advance_status_label( $order ) ) . '</p>';
	echo '<p style="margin:4px 0;">' . wp_kses_post( $advance_line ) . '</p>';
	if ( wcasc_get_due_collected( $order ) > 0 ) {
		echo '<p>' . esc_html__( 'Delivery payments collected:', 'wcasc' ) . ' ' . wp_kses_post( wc_price( wcasc_get_due_collected( $order ), array( 'currency' => $order->get_currency() ) ) ) . '</p>';
	}
	echo '<p style="margin:4px 0;">' . wp_kses_post( $due_line ) . '</p>';
	echo '</div>';
}

/* =========================================================
 * 10. Add "Due (COD)" column to the orders list (Classic + HPOS)
 * ========================================================= */
add_filter( 'manage_edit-shop_order_columns', 'wcasc_add_due_column', 999 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'wcasc_add_due_column', 999 );
function wcasc_add_due_column( $columns ) {
	$texts = wcasc_get_texts();
	unset( $columns['wcasc_due'] );
	$ordered = array();
	foreach ( $columns as $key => $label ) {
		$ordered[ $key ] = $label;
		if ( 'order_status' === $key ) {
			$ordered['wcasc_due'] = esc_html( $texts['column_label'] );
		}
	}
	if ( ! isset( $ordered['wcasc_due'] ) ) {
		$ordered['wcasc_due'] = esc_html( $texts['column_label'] );
	}
	return $ordered;
}

add_action( 'manage_shop_order_posts_custom_column', 'wcasc_render_due_column', 10, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'wcasc_render_due_column', 10, 2 );
function wcasc_render_due_column( $column, $order_or_id ) {
	if ( 'wcasc_due' !== $column ) {
		return;
	}
	$order = is_a( $order_or_id, 'WC_Order' ) ? $order_or_id : wc_get_order( $order_or_id );
	if ( ! $order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) {
		echo '&mdash;';
		return;
	}
	$due = wcasc_get_outstanding_due( $order );
	if ( $due > 0 ) {
		echo '<span class="wcasc-due-badge" aria-label="' . esc_attr( sprintf( __( 'Outstanding due: %s', 'wcasc' ), wp_strip_all_tags( wc_price( $due, array( 'currency' => $order->get_currency() ) ) ) ) ) . '">' . wp_kses_post( wc_price( $due, array( 'currency' => $order->get_currency() ) ) ) . '</span>';
	} else {
		echo '<span class="wcasc-due-clear">' . wp_kses_post( wc_price( 0, array( 'currency' => $order->get_currency() ) ) ) . '</span>';
	}
}

/* =========================================================
 * 11. HPOS compatibility declaration
 * ========================================================= */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
	}
} );

/* =========================================================
 * 12. Add "Settings" link on the Plugins list page
 * ========================================================= */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wcasc-settings' ) . '">' . esc_html__( 'Settings', 'wcasc' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );

/* =========================================================
 * 13. Display the advance amount as the checkout total
 * ========================================================= */

// Modify displayed cart total on checkout.
add_filter( 'woocommerce_cart_total', 'wcasc_modify_cart_total_display', 10, 1 );
function wcasc_modify_cart_total_display( $total ) {
	if ( is_checkout() && ! is_order_received_page() && ! is_checkout_pay_page() && ! wcasc_is_cod_checkout_selected() && wcasc_is_selected_shipping_chosen() && WC()->cart ) {
		$shipping_total = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
		$advance_amount = wcasc_calculate_advance_amount( $shipping_total );
		if ( $advance_amount > 0 ) {
			return wc_price( $advance_amount );
		}
	}
	return $total;
}

// Modify the "Total" heading label at checkout.
add_filter( 'gettext', 'wcasc_modify_order_review_heading', 20, 3 );
function wcasc_modify_order_review_heading( $translated, $original, $domain ) {
	if ( 'Total' === $original && 'woocommerce' === $domain && is_checkout() && ! is_order_received_page() && ! is_checkout_pay_page() ) {
		if ( ! wcasc_is_cod_checkout_selected() && wcasc_is_selected_shipping_chosen() ) {
			$texts = wcasc_get_texts();
			$translated = esc_html( $texts['order_review_heading'] );
		}
	}
	return $translated;
}

// Also override the total on the "Pay for order" page.
add_action( 'woocommerce_before_pay_action', 'wcasc_set_order_pay_total' );
function wcasc_set_order_pay_total( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	if ( 'yes' === $order->get_meta( '_wcasc_is_advance_order' ) ) {
		$status = $order->get_status();
		if ( in_array( $status, array( 'pending', 'failed' ), true ) ) {
			$advance = $order->get_meta( '_wcasc_advance_amount' );
			if ( '' !== $advance && null !== $advance && (float) $advance > 0 ) {
				$order->set_total( (float) $advance );
				$order->save();
			}
		}
	}
}
