<?php
/**
 * Plugin Name: Advance Shipping COD for WooCommerce
 * Plugin URI:  https://wpmethods.com
 * Description: Select one or more shipping methods from the admin panel. When a customer selects that shipping method at checkout, Cash on Delivery (COD) is automatically hidden and only the shipping charge is collected online in advance — the product price is collected as COD at the time of delivery.
 * Version:     2.4.0
 * Author:      WP Methods, Ajharul Islam
 * Text Domain: wcasc
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCASC_VERSION', '2.4.0' );
define( 'WCASC_OPTION_KEY', 'wcasc_selected_shipping_methods' );
define( 'WCASC_TEXTS_KEY', 'wcasc_texts' );
define( 'WCASC_ADVANCE_COST_TYPE_KEY', 'wcasc_advance_cost_type' );
define( 'WCASC_CUSTOM_ADVANCE_PRICE_KEY', 'wcasc_custom_advance_price' );

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
		'notice_heading'        => 'Advance Shipping Payment',
		'notice_message'        => 'Cash on Delivery is not available for this shipping method. Please pay the shipping charge of {shipping_amount} online now. The remaining product price of {due_amount} will be collected as Cash on Delivery at the time of delivery.',
		'order_review_heading'  => 'Amount Payable Now (Shipping Charge)',
		'order_note'            => 'Advance shipping charge of {advance_amount} has been paid online. The product price of {due_amount} must be collected as COD upon delivery.',
		'customer_advance_line' => 'Shipping charge of {advance_amount} has been paid online in advance.',
		'customer_due_line'     => 'The product price of {due_amount} must be paid in cash (Cash on Delivery) at the time of delivery.',
		'admin_advance_label'   => 'Advance Paid (Shipping)',
		'admin_due_label'       => 'Due (COD - Collectible on Delivery)',
		'column_label'          => 'Due (COD)',
	);
}

function wcasc_get_texts() {
	$saved = get_option( WCASC_TEXTS_KEY, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, wcasc_get_default_texts() );
}

function wcasc_get_advance_cost_type() {
	$cost_type = get_option( WCASC_ADVANCE_COST_TYPE_KEY, 'shipping_method' );
	$allowed   = array( 'shipping_method', 'custom_price', 'both' );

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
	return (float) $price;
}

function wcasc_calculate_advance_amount( $shipping_total = 0 ) {
	$shipping_total = (float) $shipping_total;
	$custom_price   = wcasc_get_custom_advance_price();
	$cost_type      = wcasc_get_advance_cost_type();

	switch ( $cost_type ) {
		case 'custom_price':
			$advance_amount = $custom_price;
			break;
		case 'both':
			$advance_amount = $shipping_total + $custom_price;
			break;
		case 'shipping_method':
		default:
			$advance_amount = $shipping_total;
			break;
	}

	return max( 0, $advance_amount );
}

function wcasc_calculate_due_amount( $grand_total, $shipping_total = 0 ) {
	$grand_total = (float) $grand_total;
	$advance_amount = wcasc_calculate_advance_amount( $shipping_total );
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

function wcasc_render_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	/* ---- Save shipping method selection ---- */
	if ( isset( $_POST['wcasc_save'] ) && check_admin_referer( 'wcasc_save_settings', 'wcasc_nonce' ) ) {
		$selected = isset( $_POST['wcasc_methods'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wcasc_methods'] ) ) : array();
		$advance_cost_type = isset( $_POST['wcasc_advance_cost_type'] ) ? sanitize_key( wp_unslash( $_POST['wcasc_advance_cost_type'] ) ) : 'shipping_method';
		$custom_advance_price = isset( $_POST['wcasc_custom_advance_price'] ) ? sanitize_text_field( wp_unslash( $_POST['wcasc_custom_advance_price'] ) ) : '';

		if ( ! in_array( $advance_cost_type, array( 'shipping_method', 'custom_price', 'both' ), true ) ) {
			$advance_cost_type = 'shipping_method';
		}

		update_option( WCASC_OPTION_KEY, $selected );
		update_option( WCASC_ADVANCE_COST_TYPE_KEY, $advance_cost_type );
		update_option( WCASC_CUSTOM_ADVANCE_PRICE_KEY, $custom_advance_price );
		echo '<div class="updated"><p>' . esc_html__( 'Shipping method settings saved successfully.', 'wcasc' ) . '</p></div>';
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
		echo '<div class="updated"><p>' . esc_html__( 'Text settings saved successfully.', 'wcasc' ) . '</p></div>';
	}

	$selected           = get_option( WCASC_OPTION_KEY, array() );
	$methods            = wcasc_get_all_shipping_methods();
	$texts              = wcasc_get_texts();
	$advance_cost_type  = wcasc_get_advance_cost_type();
	$custom_advance_price = wcasc_get_custom_advance_price();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Advance Shipping COD Settings', 'wcasc' ); ?></h1>
		<p>
			<?php esc_html_e( 'Check the shipping method(s) below that should trigger this feature. When a customer selects one of these methods at checkout, Cash on Delivery (COD) will automatically be hidden — only online payment methods will show, and only the shipping charge will be collected online in advance. The product price will be collected as COD at delivery.', 'wcasc' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'wcasc_save_settings', 'wcasc_nonce' ); ?>

			<h2><?php esc_html_e( 'Advance Cost Settings', 'wcasc' ); ?></h2>
			<table class="form-table" style="max-width:900px;">
				<tr>
					<th><label for="wcasc_advance_cost_type"><?php esc_html_e( 'Advance Cost Type', 'wcasc' ); ?></label></th>
					<td>
						<select id="wcasc_advance_cost_type" name="wcasc_advance_cost_type">
							<option value="shipping_method" <?php selected( $advance_cost_type, 'shipping_method' ); ?>><?php esc_html_e( 'Shipping Method Price', 'wcasc' ); ?></option>
							<option value="custom_price" <?php selected( $advance_cost_type, 'custom_price' ); ?>><?php esc_html_e( 'Custom Price Only', 'wcasc' ); ?></option>
							<option value="both" <?php selected( $advance_cost_type, 'both' ); ?>><?php esc_html_e( 'Both (Shipping Price + Custom Price)', 'wcasc' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose how the advance amount is calculated for orders matching the selected shipping method.', 'wcasc' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="wcasc_custom_advance_price"><?php esc_html_e( 'Custom Advance Price', 'wcasc' ); ?></label></th>
					<td>
						<input type="number" step="0.01" min="0" class="regular-text" id="wcasc_custom_advance_price" name="wcasc_custom_advance_price" value="<?php echo esc_attr( $custom_advance_price ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Used when Advance Cost Type is set to Custom Price Only or Both.', 'wcasc' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php if ( empty( $methods ) ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'No shipping methods found. Please set up shipping zones/methods first under WooCommerce → Settings → Shipping.', 'wcasc' ); ?>
				</p></div>
			<?php else : ?>
			<table class="widefat striped" style="max-width:900px;">
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
								<span style="color:#2e7d32;">&#9679; <?php esc_html_e( 'Enabled', 'wcasc' ); ?></span>
							<?php else : ?>
								<span style="color:#c62828;">&#9679; <?php esc_html_e( 'Disabled', 'wcasc' ); ?></span>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $m['rate_id'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top:15px;">
				<button type="submit" name="wcasc_save" class="button button-primary">
					<?php esc_html_e( 'Save Shipping Methods', 'wcasc' ); ?>
				</button>
			</p>
		</form>
		<?php endif; ?>

		<hr style="margin:30px 0;" />

		<h2><?php esc_html_e( 'Customize Texts', 'wcasc' ); ?></h2>
		<p>
			<?php esc_html_e( 'You can edit all customer-facing texts below. Available placeholders: {shipping_amount}, {due_amount}, {advance_amount}. Placeholders will automatically be replaced with the actual formatted prices.', 'wcasc' ); ?>
		</p>
		<form method="post">
			<?php wp_nonce_field( 'wcasc_save_texts', 'wcasc_texts_nonce' ); ?>
			<table class="form-table" style="max-width:900px;">
				<tr>
					<th><label for="wcasc_notice_heading"><?php esc_html_e( 'Checkout Notice Heading', 'wcasc' ); ?></label></th>
					<td><input type="text" class="regular-text" id="wcasc_notice_heading" name="wcasc_text[notice_heading]" value="<?php echo esc_attr( $texts['notice_heading'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="wcasc_notice_message"><?php esc_html_e( 'Checkout Notice Message', 'wcasc' ); ?></label><br /><small><?php esc_html_e( 'Placeholders: {shipping_amount}, {due_amount}', 'wcasc' ); ?></small></th>
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
function wcasc_hide_cod_when_selected_shipping( $gateways ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $gateways;
	}
	if ( wcasc_is_selected_shipping_chosen() && isset( $gateways['cod'] ) ) {
		unset( $gateways['cod'] );
	}
	return $gateways;
}

/* =========================================================
 * 5. Show notice to customer before payment methods
 *    (only when COD is actually being hidden)
 *
 *    IMPORTANT: WooCommerce's checkout AJAX (triggered when the customer
 *    changes the shipping method) replaces DOM content by matching the
 *    ".woocommerce-checkout-review-order-table" selector only. Content
 *    that sits AFTER the table (like this notice) gets left behind as a
 *    stray, un-replaced duplicate — so it never updates dynamically and
 *    only reflects the correct state after a full page reload.
 *
 *    Fix: wrap the notice in its own element with a stable ID, and
 *    explicitly register that ID in 'woocommerce_update_order_review_fragments'
 *    so WooCommerce's own AJAX fragment-replace mechanism refreshes it
 *    directly on every shipping method change — same as any other core
 *    checkout fragment.
 * ========================================================= */
add_action( 'woocommerce_review_order_before_payment', 'wcasc_render_notice_wrapper' );
function wcasc_render_notice_wrapper() {
	echo wcasc_get_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- already built with esc_html()/wp_kses_post() internally.
}

add_filter( 'woocommerce_update_order_review_fragments', 'wcasc_add_notice_fragment' );
function wcasc_add_notice_fragment( $fragments ) {
	$fragments['#wcasc-notice-wrap'] = wcasc_get_notice_html();
	return $fragments;
}

/**
 * Build the notice markup, always wrapped in a stable-ID container
 * (even when empty) so AJAX fragment replacement always finds it.
 */
function wcasc_get_notice_html() {
	ob_start();
	echo '<div id="wcasc-notice-wrap">';

	if ( WC()->cart && wcasc_is_selected_shipping_chosen() ) {
		$texts = wcasc_get_texts();

		$shipping_total  = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
		$grand_total     = (float) WC()->cart->get_total( 'edit' );
		$advance_amount  = wcasc_calculate_advance_amount( $shipping_total );
		$due_amount      = wcasc_calculate_due_amount( $grand_total, $shipping_total );

		$message = wcasc_format_text(
			$texts['notice_message'],
			array(
				'shipping_amount' => '<strong style="color:#d39e00;">' . wc_price( $advance_amount ) . '</strong>',
				'due_amount'      => '<strong style="color:#d39e00;">' . wc_price( $due_amount ) . '</strong>',
			)
		);

		echo '<div class="woocommerce-info wcasc-notice" style="margin-bottom:20px;padding:15px 20px;background:#fff3cd;border-left:5px solid #ffc107;border-radius:4px;">';
		echo '<p style="margin:0 0 5px 0;font-weight:600;color:#856404;font-size:16px;">';
		echo '&#128230; ' . esc_html( $texts['notice_heading'] );
		echo '</p>';
		echo '<p style="margin:0;color:#533f03;font-size:14px;">';
		echo wp_kses_post( $message );
		echo '</p>';
		echo '</div>';

		echo '<style>';
		echo '.woocommerce-info.wcasc-notice { border: none; }';
		echo '.woocommerce-info.wcasc-notice::before{ display:none; }';
		echo '</style>';
	}

	echo '</div>';
	return ob_get_clean();
}

/* =========================================================
 * 6. Set an advance total for the payment gateway
 *    (shows only the shipping charge, not the full total)
 * ========================================================= */

// 6.1: On order creation, save meta and update the order total.
add_action( 'woocommerce_checkout_order_processed', 'wcasc_flag_order_for_advance_capture', 5, 3 );
function wcasc_flag_order_for_advance_capture( $order_id, $posted_data, $order ) {
	if ( ! $order ) {
		$order = wc_get_order( $order_id );
	}
	if ( ! $order || ! wcasc_order_uses_selected_shipping( $order ) ) {
		return;
	}

	$shipping_total = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
	$grand_total    = (float) $order->get_total();
	$advance_amount = wcasc_calculate_advance_amount( $shipping_total );
	$due_amount     = wcasc_calculate_due_amount( $grand_total, $shipping_total );

	$order->update_meta_data( '_wcasc_is_advance_order', 'yes' );
	$order->update_meta_data( '_wcasc_advance_amount', $advance_amount );
	$order->update_meta_data( '_wcasc_due_amount', $due_amount );
	$order->update_meta_data( '_wcasc_original_total', $grand_total );
	$order->save();

	// Update the order total for the payment gateway.
	$order->set_total( $shipping_total );
	$order->save();
}

// 6.2: Override the total right before the payment gateway processes it.
add_filter( 'woocommerce_order_get_total', 'wcasc_override_total_for_payment_gateway', 1, 2 );
function wcasc_override_total_for_payment_gateway( $total, $order ) {
	if ( ! $order instanceof WC_Order ) {
		return $total;
	}

	if ( 'yes' === $order->get_meta( '_wcasc_is_advance_order' ) ) {
		$status = $order->get_status();
		if ( in_array( $status, array( 'pending', 'failed' ), true ) ) {
			$advance = $order->get_meta( '_wcasc_advance_amount' );
			if ( '' !== $advance && null !== $advance && (float) $advance > 0 ) {
				return (float) $advance;
			}
		}
	}

	return $total;
}

// 6.3: Restore the original total once payment succeeds.
add_action( 'woocommerce_order_status_changed', 'wcasc_restore_original_total_after_payment', 10, 4 );
function wcasc_restore_original_total_after_payment( $order_id, $from_status, $to_status, $order ) {
	if ( ! $order ) {
		$order = wc_get_order( $order_id );
	}
	if ( ! $order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) {
		return;
	}

	if ( in_array( $to_status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
		$original_total = $order->get_meta( '_wcasc_original_total' );
		if ( '' !== $original_total && null !== $original_total ) {
			$order->set_total( (float) $original_total );
			$order->save();
		}
	}
}

/* =========================================================
 * 7. Add order note once payment succeeds
 * ========================================================= */
add_action( 'woocommerce_order_status_changed', 'wcasc_add_order_note_on_payment', 20, 4 );
function wcasc_add_order_note_on_payment( $order_id, $from_status, $to_status, $order ) {
	if ( ! $order ) {
		$order = wc_get_order( $order_id );
	}
	if ( ! $order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) {
		return;
	}
	if ( 'yes' === $order->get_meta( '_wcasc_note_added' ) ) {
		return;
	}
	if ( in_array( $to_status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
		$texts = wcasc_get_texts();
		$note  = wcasc_format_text(
			$texts['order_note'],
			array(
				'advance_amount' => wc_price( $order->get_meta( '_wcasc_advance_amount' ), array( 'currency' => $order->get_currency() ) ),
				'due_amount'     => wc_price( $order->get_meta( '_wcasc_due_amount' ), array( 'currency' => $order->get_currency() ) ),
			)
		);
		$order->add_order_note( wp_strip_all_tags( $note ) );
		$order->update_meta_data( '_wcasc_note_added', 'yes' );
		$order->save();
	}
}

/* =========================================================
 * 8. Show Advance / Due info on the admin order page
 * ========================================================= */
add_action( 'woocommerce_admin_order_data_after_order_details', 'wcasc_admin_order_advance_info' );
function wcasc_admin_order_advance_info( $order ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) {
		return;
	}
	$texts   = wcasc_get_texts();
	$advance = wc_price( $order->get_meta( '_wcasc_advance_amount' ), array( 'currency' => $order->get_currency() ) );
	$due     = wc_price( $order->get_meta( '_wcasc_due_amount' ), array( 'currency' => $order->get_currency() ) );
	echo '<div class="wcasc-admin-box" style="margin-top:10px;padding:10px;background:#fff8e1;border:1px solid #ffe082;border-radius:4px;">';
	echo '<p style="margin:4px 0;"><strong>' . esc_html( $texts['admin_advance_label'] ) . ':</strong> ' . wp_kses_post( $advance ) . '</p>';
	echo '<p style="margin:4px 0;"><strong>' . esc_html( $texts['admin_due_label'] ) . ':</strong> ' . wp_kses_post( $due ) . '</p>';
	echo '</div>';
}

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
	$due     = wc_price( $order->get_meta( '_wcasc_due_amount' ), array( 'currency' => $order->get_currency() ) );

	$advance_line = wcasc_format_text( $texts['customer_advance_line'], array( 'advance_amount' => $advance ) );
	$due_line     = wcasc_format_text( $texts['customer_due_line'], array( 'due_amount' => $due ) );

	echo '<div style="margin:15px 0;padding:12px;background:#fff8e1;border:1px solid #ffe082;">';
	echo '<p style="margin:4px 0;">' . wp_kses_post( $advance_line ) . '</p>';
	echo '<p style="margin:4px 0;">' . wp_kses_post( $due_line ) . '</p>';
	echo '</div>';
}

/* =========================================================
 * 10. Add "Due (COD)" column to the orders list (Classic + HPOS)
 * ========================================================= */
add_filter( 'manage_edit-shop_order_columns', 'wcasc_add_due_column' );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'wcasc_add_due_column' );
function wcasc_add_due_column( $columns ) {
	$texts                 = wcasc_get_texts();
	$columns['wcasc_due'] = esc_html( $texts['column_label'] );
	return $columns;
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
	echo wp_kses_post( wc_price( $order->get_meta( '_wcasc_due_amount' ), array( 'currency' => $order->get_currency() ) ) );
}

/* =========================================================
 * 11. HPOS compatibility declaration
 * ========================================================= */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
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
 * 13. Show only the shipping charge as the checkout total
 * ========================================================= */

// Modify displayed cart total on checkout.
add_filter( 'woocommerce_cart_total', 'wcasc_modify_cart_total_display', 10, 1 );
function wcasc_modify_cart_total_display( $total ) {
	if ( is_checkout() && wcasc_is_selected_shipping_chosen() && WC()->cart ) {
		$shipping_total = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
		$advance_amount = wcasc_calculate_advance_amount( $shipping_total );
		if ( $advance_amount > 0 ) {
			return wc_price( $advance_amount );
		}
	}
	return $total;
}

// Modify the "Total" heading label at checkout.
add_filter( 'woocommerce_checkout_order_review_heading', 'wcasc_modify_checkout_heading' );
function wcasc_modify_checkout_heading( $heading ) {
	if ( wcasc_is_selected_shipping_chosen() ) {
		$texts = wcasc_get_texts();
		return esc_html( $texts['order_review_heading'] );
	}
	return $heading;
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