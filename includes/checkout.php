<?php
defined( 'ABSPATH' ) || exit;

/* Accept mode identifiers only; all amounts are calculated on the server. */
add_action( 'woocommerce_checkout_update_order_review', 'wcasc_update_checkout_choice', 5 );
function wcasc_update_checkout_choice( $posted_data ) {
	parse_str( $posted_data, $data );
	if ( WC()->session && isset( $data['payment_method'] ) && is_string( $data['payment_method'] ) ) {
		WC()->session->set( 'chosen_payment_method', sanitize_key( $data['payment_method'] ) );
	}
	if ( WC()->session && isset( $data['wcasc_payment_mode'] ) && is_string( $data['wcasc_payment_mode'] ) ) {
		$mode = sanitize_key( $data['wcasc_payment_mode'] );
		if ( in_array( $mode, wcasc_get_enabled_modes(), true ) ) {
			WC()->session->set( 'wcasc_payment_mode', $mode );
		}
	}
}

add_action( 'woocommerce_checkout_process', function () {
	if ( isset( $_POST['wcasc_payment_mode'] ) ) {
		$mode = is_string( $_POST['wcasc_payment_mode'] ) ? sanitize_key( wp_unslash( $_POST['wcasc_payment_mode'] ) ) : '';
		if ( ! in_array( $mode, wcasc_get_enabled_modes(), true ) ) {
			wc_add_notice( __( 'Please select an available advance payment option.', 'wcasc' ), 'error' );
		} elseif ( WC()->session ) {
			WC()->session->set( 'wcasc_payment_mode', $mode );
		}
	}
} );

add_action( 'woocommerce_review_order_after_cart_contents', 'wcasc_render_subtotal_notice' );
function wcasc_render_subtotal_notice() {
	if ( 'subtotal' === wcasc_get_checkout_position() ) {
		// Core's subtotal follows this hook; the checkout script moves the row below it.
		echo '<tr class="wcasc-summary-row"><td colspan="2">' . wcasc_get_notice_html() . '</td></tr>';
	}
}
add_action( 'woocommerce_review_order_before_order_total', 'wcasc_render_total_notice' );
function wcasc_render_total_notice() {
	if ( 'before_total' === wcasc_get_checkout_position() ) {
		echo '<tr class="wcasc-summary-row"><td colspan="2">' . wcasc_get_notice_html() . '</td></tr>';
	}
}
add_action( 'woocommerce_review_order_before_payment', 'wcasc_render_notice_wrapper' );
function wcasc_render_notice_wrapper() {
	if ( 'before_payment' === wcasc_get_checkout_position() ) {
		echo wcasc_get_notice_html();
	}
}
add_filter( 'woocommerce_update_order_review_fragments', 'wcasc_add_notice_fragment' );
function wcasc_add_notice_fragment( $fragments ) {
	if ( 'before_payment' === wcasc_get_checkout_position() ) {
		$fragments['#wcasc-notice-wrap'] = wcasc_get_notice_html();
	}
	return $fragments;
}
function wcasc_get_notice_html() {
	ob_start();
	echo '<div id="wcasc-notice-wrap" data-position="' . esc_attr( wcasc_get_checkout_position() ) . '" data-allow-cod="' . ( wcasc_should_show_cod() ? 'true' : 'false' ) . '">';
	if ( WC()->cart && wcasc_is_selected_shipping_chosen() ) {
		$texts = wcasc_get_texts();
		$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
		$total = wcasc_get_cart_grand_total();
		$cod = wcasc_is_cod_checkout_selected();
		$advance = $cod ? 0 : wcasc_calculate_advance_amount( $shipping, $total );
		$due = max( 0, $total - $advance );
		echo '<section class="wcasc-payment-card" aria-labelledby="wcasc-payment-heading">';
		echo '<h3 id="wcasc-payment-heading">' . esc_html( $cod ? __( 'Cash on Delivery', 'wcasc' ) : $texts['notice_heading'] ) . '</h3>';
		$modes = wcasc_get_enabled_modes();
		$labels = wcasc_get_checkout_mode_labels();
		if ( count( $modes ) > 1 && ! $cod ) {
			echo '<fieldset class="wcasc-payment-options"><legend class="screen-reader-text">' . esc_html__( 'Advance payment option', 'wcasc' ) . '</legend>';
			foreach ( $modes as $mode ) {
				$amount = wcasc_calculate_advance_amount( $shipping, $total, $mode );
				echo '<label class="wcasc-payment-option"><input type="radio" name="wcasc_payment_mode" value="' . esc_attr( $mode ) . '" ' . checked( wcasc_get_chosen_mode(), $mode, false ) . ' /><span>' . esc_html( $labels[ $mode ] ) . '</span><strong>' . wp_kses_post( wc_price( $amount ) ) . '</strong></label>';
			}
			echo '</fieldset>';
		}
		echo '<dl class="wcasc-payment-breakdown"><div><dt>' . esc_html__( 'Full order total', 'wcasc' ) . '</dt><dd>' . wp_kses_post( wc_price( $total ) ) . '</dd></div><div class="wcasc-pay-now"><dt>' . esc_html( $texts['order_review_heading'] ) . '</dt><dd>' . wp_kses_post( wc_price( $advance ) ) . '</dd></div><div><dt>' . esc_html__( 'Due on delivery', 'wcasc' ) . '</dt><dd>' . wp_kses_post( wc_price( $due ) ) . '</dd></div></dl>';
		if ( $advance > 0 ) {
			$message = 'full_payment' === wcasc_get_chosen_mode() ? __( 'Pay {advance_amount} now using your preferred payment method. No payment is due on delivery.', 'wcasc' ) : $texts['notice_message'];
			echo '<p class="wcasc-payment-message">' . wp_kses_post( wcasc_format_text( $message, array( 'shipping_amount' => wc_price( $shipping ), 'advance_amount' => wc_price( $advance ), 'due_amount' => wc_price( $due ) ) ) ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'No advance is required for this option. Pay the full amount on delivery using Cash on Delivery.', 'wcasc' ) . '</p>';
		}
		echo '</section>';
	}
	echo '</div>';
	return ob_get_clean();
}

add_action( 'wp_enqueue_scripts', function () {
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
		wp_enqueue_style( 'wcasc-checkout', plugin_dir_url( __DIR__ . '/../advance-shipping-cod-woocommerce.php' ) . 'assets/css/wcasc-checkout.css', array(), WCASC_VERSION );
		wp_add_inline_style( 'wcasc-checkout', wcasc_get_design_css() );
		wp_enqueue_script( 'wcasc-checkout', plugin_dir_url( __DIR__ . '/../advance-shipping-cod-woocommerce.php' ) . 'assets/js/wcasc-checkout.js', array( 'jquery', 'wc-checkout' ), WCASC_VERSION, true );
	}
} );

/** Scope numeric cart totals to gateway fields, including legacy cart->total reads.
 * Template hooks fire during AJAX too. Stored cart/session totals remain intact.
 */
add_action( 'woocommerce_before_template_part', function ( $template_name ) {
	if ( 'checkout/payment-method.php' === $template_name ) {
		$GLOBALS['wcasc_rendering_gateway'] = true;
	}
} );
add_action( 'woocommerce_after_template_part', function ( $template_name ) {
	if ( 'checkout/payment-method.php' === $template_name ) {
		$GLOBALS['wcasc_rendering_gateway'] = false;
	}
} );
add_filter( 'woocommerce_cart_get_total', function ( $total ) {
	if ( empty( $GLOBALS['wcasc_rendering_gateway'] ) ) {
		return $total;
	}
	if ( is_checkout_pay_page() ) {
		$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		return $order && 'yes' === $order->get_meta( '_wcasc_is_advance_order' ) ? (float) $order->get_meta( '_wcasc_advance_amount' ) : $total;
	}
	if ( wcasc_is_selected_shipping_chosen() ) {
		$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
		$advance = wcasc_calculate_advance_amount( $shipping, wcasc_get_cart_grand_total() );
		return $advance > 0 ? $advance : $total;
	}
	return $total;
}, 999 );

/* Persist the advance before the first order save so every gateway reads it. */
add_action( 'woocommerce_checkout_create_order', 'wcasc_prepare_advance_order', 999, 2 );
function wcasc_prepare_advance_order( $order, $data = array() ) {
	// WooCommerce can reuse an unpaid order after the shipping choice changes.
	foreach ( array( '_wcasc_is_advance_order', '_wcasc_advance_amount', '_wcasc_due_amount', '_wcasc_original_total', '_wcasc_payment_mode', '_wcasc_advance_paid', '_wcasc_note_added' ) as $key ) {
		$order->delete_meta_data( $key );
	}
	if ( ! wcasc_order_uses_selected_shipping( $order ) || 'cod' === $order->get_payment_method() ) {
		return;
	}
	$shipping = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
	$total = (float) $order->get_total( 'edit' );
	$advance = wcasc_calculate_advance_amount( $shipping, $total );
	if ( $advance <= 0 ) {
		return;
	}
	$order->update_meta_data( '_wcasc_is_advance_order', 'yes' );
	$order->update_meta_data( '_wcasc_advance_amount', $advance );
	$order->update_meta_data( '_wcasc_due_amount', max( 0, $total - $advance ) );
	$order->update_meta_data( '_wcasc_original_total', $total );
	$order->update_meta_data( '_wcasc_payment_mode', wcasc_get_chosen_mode() );
	$order->update_meta_data( '_wcasc_advance_paid', 'no' );
	$order->set_total( $advance );
}

// Keep gateway amounts stable through on-hold, verification callbacks and retries.
add_filter( 'woocommerce_order_get_total', 'wcasc_override_total_for_payment_gateway', 999, 2 );
function wcasc_override_total_for_payment_gateway( $total, $order ) {
	if ( $order instanceof WC_Order && 'yes' === $order->get_meta( '_wcasc_is_advance_order' ) ) {
		$advance = $order->get_meta( '_wcasc_advance_amount' );
		if ( '' !== $advance && null !== $advance ) {
			return (float) $advance;
		}
	}
	return $total;
}

add_action( 'woocommerce_payment_complete', 'wcasc_confirm_advance_payment' );
function wcasc_confirm_advance_payment( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) || 'yes' === $order->get_meta( '_wcasc_advance_paid' ) ) {
		return;
	}
	$texts = wcasc_get_texts();
	$order->update_meta_data( '_wcasc_advance_paid', 'yes' );
	$order->add_order_note( wp_strip_all_tags( wcasc_format_text( $texts['order_note'], array( 'advance_amount' => wc_price( $order->get_meta( '_wcasc_advance_amount' ), array( 'currency' => $order->get_currency() ) ), 'due_amount' => wc_price( $order->get_meta( '_wcasc_due_amount' ), array( 'currency' => $order->get_currency() ) ) ) ) ) );
	$order->update_meta_data( '_wcasc_note_added', 'yes' );
	$order->save();
}
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
	if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
		wcasc_confirm_advance_payment( $order_id );
	}
}, 20, 4 );

function wcasc_get_advance_status_label( $order ) {
	$confirmed = wcasc_advance_is_confirmed( $order );
	if ( 'full_payment' === $order->get_meta( '_wcasc_payment_mode' ) ) {
		return $confirmed ? __( 'Payment confirmed', 'wcasc' ) : __( 'Payment awaiting confirmation', 'wcasc' );
	}
	return $confirmed ? __( 'Advance payment confirmed', 'wcasc' ) : __( 'Advance payment awaiting confirmation', 'wcasc' );
}

add_filter( 'woocommerce_get_order_item_totals', function ( $rows, $order ) {
	if ( 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) {
		return $rows;
	}
	$currency = array( 'currency' => $order->get_currency() );
	if ( isset( $rows['order_total'] ) ) {
		$rows['order_total']['label'] = __( 'Advance payment:', 'wcasc' );
		$rows['order_total']['value'] = wc_price( $order->get_meta( '_wcasc_advance_amount' ), $currency );
	}
	$rows['wcasc_full_total'] = array( 'label' => __( 'Full order total:', 'wcasc' ), 'value' => wc_price( $order->get_meta( '_wcasc_original_total' ), $currency ) );
	if ( wcasc_get_due_collected( $order ) > 0 ) {
		$rows['wcasc_collected'] = array( 'label' => __( 'Delivery payments collected:', 'wcasc' ), 'value' => wc_price( wcasc_get_due_collected( $order ), $currency ) );
	}
	$rows['wcasc_due'] = array( 'label' => __( 'Outstanding on delivery:', 'wcasc' ), 'value' => wc_price( wcasc_get_outstanding_due( $order ), $currency ) );
	return $rows;
}, 10, 2 );
