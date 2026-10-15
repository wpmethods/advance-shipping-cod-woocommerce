<?php
/**
 * Checkout choices, payment amounts and customer summaries.
 *
 * @package AdvShippingWPM
 */

defined( 'ABSPATH' ) || exit;

/* Accept mode identifiers only; all amounts are calculated on the server. */
add_action( 'woocommerce_checkout_update_order_review', 'advshipping_wpm_update_checkout_choice', 5 );
/**
 * Update checkout choice.
 *
 * @param string $posted_data posted data value.
 *
 * @return void No return value.
 */
function advshipping_wpm_update_checkout_choice( $posted_data ) {
	parse_str( $posted_data, $data );
	if ( WC()->session && isset( $data['payment_method'] ) && is_string( $data['payment_method'] ) ) {
		WC()->session->set( 'chosen_payment_method', sanitize_key( $data['payment_method'] ) );
	}
	if ( WC()->session && isset( $data['advshipping_wpm_payment_mode'] ) && is_string( $data['advshipping_wpm_payment_mode'] ) ) {
		$mode = sanitize_key( $data['advshipping_wpm_payment_mode'] );
		if ( in_array( $mode, advshipping_wpm_get_enabled_modes(), true ) ) {
			WC()->session->set( 'advshipping_wpm_payment_mode', $mode );
		}
	}
}

// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before running this action.
add_action(
	'woocommerce_checkout_process',
	function () {
		if ( isset( $_POST['advshipping_wpm_payment_mode'] ) ) {
			$mode = is_string( $_POST['advshipping_wpm_payment_mode'] ) ? sanitize_key( wp_unslash( $_POST['advshipping_wpm_payment_mode'] ) ) : '';
			if ( ! in_array( $mode, advshipping_wpm_get_enabled_modes(), true ) ) {
				wc_add_notice( __( 'Please select an available advance payment option.', 'advshipping-wpm' ), 'error' );
			} elseif ( WC()->session ) {
				WC()->session->set( 'advshipping_wpm_payment_mode', $mode );
			}
		}
	}
);

// phpcs:enable WordPress.Security.NonceVerification.Missing

add_action( 'woocommerce_after_checkout_validation', 'advshipping_wpm_validate_gateway_plan', 10, 2 );
/**
 * Prevent an online gateway from collecting the full order for a zero-advance plan.
 *
 * @param array    $data   Validated checkout fields supplied by WooCommerce.
 * @param WP_Error $errors Checkout validation errors.
 * @return void
 */
function advshipping_wpm_validate_gateway_plan( $data, $errors ) {
	if ( ! advshipping_wpm_uses_gateway_plans() || ! WC()->cart || empty( $data['payment_method'] ) || ! advshipping_wpm_gateway_allows_plan( $data['payment_method'] ) ) {
		return;
	}
	$total    = advshipping_wpm_get_cart_grand_total();
	$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
	if ( $total > 0 && advshipping_wpm_calculate_advance_amount( $shipping, $total ) <= 0 ) {
		$errors->add( 'advshipping_wpm_zero_advance', __( 'No advance is required for this payment option. Choose Cash on Delivery, another payment option, or a different payment method.', 'advshipping-wpm' ) );
	}
}

add_action( 'woocommerce_review_order_after_cart_contents', 'advshipping_wpm_render_subtotal_notice' );
/**
 * Render subtotal notice.
 *
 * @return void No return value.
 */
function advshipping_wpm_render_subtotal_notice() {
	if ( 'subtotal' === advshipping_wpm_get_checkout_position() ) {
		// Core's subtotal follows this hook; the checkout script moves the row below it.
		echo '<tr class="advshipping_wpm-summary-row"><td colspan="2">' . advshipping_wpm_get_notice_html() . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML builder escapes all dynamic attributes and content; checkout controls must be retained.
	}
}
add_action( 'woocommerce_review_order_before_order_total', 'advshipping_wpm_render_total_notice' );
/**
 * Render total notice.
 *
 * @return void No return value.
 */
function advshipping_wpm_render_total_notice() {
	if ( 'before_total' === advshipping_wpm_get_checkout_position() ) {
		echo '<tr class="advshipping_wpm-summary-row"><td colspan="2">' . advshipping_wpm_get_notice_html() . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML builder escapes all dynamic attributes and content; checkout controls must be retained.
	}
}
add_action( 'woocommerce_review_order_before_payment', 'advshipping_wpm_render_notice_wrapper' );
/**
 * Render notice wrapper.
 *
 * @return void No return value.
 */
function advshipping_wpm_render_notice_wrapper() {
	if ( 'before_payment' === advshipping_wpm_get_checkout_position() ) {
		echo advshipping_wpm_get_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML builder escapes all dynamic attributes and content; checkout controls must be retained.
	}
}
add_action( 'woocommerce_review_order_before_submit', 'advshipping_wpm_render_payment_position_notice' );
/**
 * Render payment-area summaries inside WooCommerce's refreshed payment fragment.
 *
 * The checkout script places this wrapper beside the gateway list so themes do
 * not need a template override or a particular payment heading selector.
 *
 * @return void
 */
function advshipping_wpm_render_payment_position_notice() {
	if ( in_array( advshipping_wpm_get_checkout_position(), array( 'after_payment_title', 'after_payment_methods' ), true ) ) {
		echo advshipping_wpm_get_notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The HTML builder escapes all dynamic attributes and content; checkout controls must be retained.
	}
}

add_filter( 'woocommerce_update_order_review_fragments', 'advshipping_wpm_add_notice_fragment' );
/**
 * Add notice fragment.
 *
 * @param array $fragments fragments value.
 *
 * @return array Result value.
 */
function advshipping_wpm_add_notice_fragment( $fragments ) {
	if ( 'before_payment' === advshipping_wpm_get_checkout_position() ) {
		$fragments['#advshipping_wpm-notice-wrap'] = advshipping_wpm_get_notice_html();
	}
	return $fragments;
}

add_filter( 'woocommerce_gateway_title', 'advshipping_wpm_gateway_payable_title', 20, 2 );
/**
 * Show the plan's payable amount beside supported gateway titles at checkout.
 *
 * @param string $title      Existing gateway title.
 * @param string $gateway_id Gateway identifier.
 * @return string Gateway title with its payable amount when applicable.
 */
function advshipping_wpm_gateway_payable_title( $title, $gateway_id ) {
	if ( ! advshipping_wpm_uses_gateway_plans() || ! is_checkout() || is_checkout_pay_page() || is_order_received_page() || ! WC()->cart || ! advshipping_wpm_gateway_allows_plan( $gateway_id ) ) {
		return $title;
	}
	$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
	$amount   = advshipping_wpm_calculate_advance_amount( $shipping, advshipping_wpm_get_cart_grand_total() );
	if ( $amount <= 0 ) {
		return $title;
	}
	/* translators: %s: Formatted amount payable through the gateway. */
	$cost = sprintf( __( 'Pay now: %s', 'advshipping-wpm' ), wc_price( $amount ) );
	return $title . ' <span class="advshipping_wpm-gateway-cost">' . wp_kses_post( $cost ) . '</span>';
}
/**
 * Get notice html.
 *
 * @return string Result value.
 */
function advshipping_wpm_get_notice_html() {
	ob_start();
	echo '<div id="advshipping_wpm-notice-wrap" data-position="' . esc_attr( advshipping_wpm_get_checkout_position() ) . '" data-allow-cod="' . ( advshipping_wpm_should_show_cod() ? 'true' : 'false' ) . '" data-gateway-plans="' . ( advshipping_wpm_uses_gateway_plans() ? 'true' : 'false' ) . '" data-selected-gateway="' . esc_attr( WC()->session ? WC()->session->get( 'chosen_payment_method', '' ) : '' ) . '">';
	if ( WC()->cart && advshipping_wpm_is_payment_plan_active() ) {
		$texts    = advshipping_wpm_get_texts();
		$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
		$total    = advshipping_wpm_get_cart_grand_total();
		$cod      = advshipping_wpm_is_cod_checkout_selected();
		$advance  = $cod ? 0 : advshipping_wpm_calculate_advance_amount( $shipping, $total );
		$due      = max( 0, $total - $advance );
		echo '<section class="advshipping_wpm-payment-card" aria-labelledby="advshipping_wpm-payment-heading">';
		echo '<h3 id="advshipping_wpm-payment-heading">' . esc_html( $cod ? __( 'Cash on Delivery', 'advshipping-wpm' ) : $texts['notice_heading'] ) . '</h3>';
		$modes  = advshipping_wpm_get_enabled_modes();
		$labels = advshipping_wpm_get_checkout_mode_labels();
		if ( count( $modes ) > 1 && ! $cod ) {
			echo '<fieldset class="advshipping_wpm-payment-options"><legend class="screen-reader-text">' . esc_html__( 'Advance payment option', 'advshipping-wpm' ) . '</legend>';
			foreach ( $modes as $mode ) {
				$amount = advshipping_wpm_calculate_advance_amount( $shipping, $total, $mode );
				echo '<label class="advshipping_wpm-payment-option"><input type="radio" name="advshipping_wpm_payment_mode" value="' . esc_attr( $mode ) . '" ' . checked( advshipping_wpm_get_chosen_mode(), $mode, false ) . ' /><span>' . esc_html( $labels[ $mode ] ) . '</span><strong>' . wp_kses_post( wc_price( $amount ) ) . '</strong></label>';
			}
			echo '</fieldset>';
		}
		echo '<dl class="advshipping_wpm-payment-breakdown"><div><dt>' . esc_html__( 'Full order total', 'advshipping-wpm' ) . '</dt><dd>' . wp_kses_post( wc_price( $total ) ) . '</dd></div><div class="advshipping_wpm-pay-now"><dt>' . esc_html( $texts['order_review_heading'] ) . '</dt><dd>' . wp_kses_post( wc_price( $advance ) ) . '</dd></div><div><dt>' . esc_html__( 'Due on delivery', 'advshipping-wpm' ) . '</dt><dd>' . wp_kses_post( wc_price( $due ) ) . '</dd></div></dl>';
		if ( $advance > 0 ) {
			$message = 'full_payment' === advshipping_wpm_get_chosen_mode() ? __( 'Pay {advance_amount} now using your preferred payment method. No payment is due on delivery.', 'advshipping-wpm' ) : $texts['notice_message'];
			echo '<p class="advshipping_wpm-payment-message">' . wp_kses_post(
				advshipping_wpm_format_text(
					$message,
					array(
						'shipping_amount' => wc_price( $shipping ),
						'advance_amount'  => wc_price( $advance ),
						'due_amount'      => wc_price( $due ),
					)
				)
			) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'No advance is required for this option. Pay the full amount on delivery using Cash on Delivery.', 'advshipping-wpm' ) . '</p>';
		}
		echo '</section>';
	}
	echo '</div>';
	return ob_get_clean();
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
			wp_enqueue_style( 'advshipping_wpm-checkout', plugin_dir_url( __DIR__ . '/../adv-shipping-wpm.php' ) . 'assets/css/advshipping_wpm-checkout.css', array(), ADVSHIPPING_WPM_VERSION );
			wp_add_inline_style( 'advshipping_wpm-checkout', advshipping_wpm_get_design_css() );
			wp_enqueue_script( 'advshipping_wpm-checkout', plugin_dir_url( __DIR__ . '/../adv-shipping-wpm.php' ) . 'assets/js/advshipping_wpm-checkout.js', array( 'jquery', 'wc-checkout' ), ADVSHIPPING_WPM_VERSION, true );
		}
	}
);

/** Scope numeric cart totals to gateway fields, including legacy cart->total reads.
 * Template hooks fire during AJAX too. Stored cart/session totals remain intact.
 */
add_action(
	'woocommerce_before_template_part',
	function ( $template_name, $template_path = '', $located = '', $args = array() ) {
		if ( 'checkout/payment-method.php' === $template_name ) {
			$GLOBALS['advshipping_wpm_rendering_gateway']    = true;
			$GLOBALS['advshipping_wpm_rendering_gateway_id'] = isset( $args['gateway']->id ) ? $args['gateway']->id : '';
		}
	},
	10,
	4
);
add_action(
	'woocommerce_after_template_part',
	function ( $template_name ) {
		if ( 'checkout/payment-method.php' === $template_name ) {
			$GLOBALS['advshipping_wpm_rendering_gateway']    = false;
			$GLOBALS['advshipping_wpm_rendering_gateway_id'] = '';
		}
	}
);
add_filter(
	'woocommerce_cart_get_total',
	function ( $total ) {
		if ( empty( $GLOBALS['advshipping_wpm_rendering_gateway'] ) ) {
			return $total;
		}
		if ( is_checkout_pay_page() ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
			return $order && 'yes' === $order->get_meta( '_advshipping_wpm_is_advance_order' ) ? (float) $order->get_meta( '_advshipping_wpm_advance_amount' ) : $total;
		}
		$uses_plan = advshipping_wpm_is_payment_plan_active();
		if ( advshipping_wpm_uses_gateway_plans() ) {
			$gateway_id = ! empty( $GLOBALS['advshipping_wpm_rendering_gateway_id'] ) ? $GLOBALS['advshipping_wpm_rendering_gateway_id'] : ( WC()->session ? WC()->session->get( 'chosen_payment_method', '' ) : '' );
			$uses_plan  = advshipping_wpm_gateway_allows_plan( $gateway_id );
		}
		if ( $uses_plan ) {
			$shipping = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
			$advance  = advshipping_wpm_calculate_advance_amount( $shipping, advshipping_wpm_get_cart_grand_total() );
			return $advance > 0 ? $advance : $total;
		}
		return $total;
	},
	999
);

/* Persist the advance before the first order save so every gateway reads it. */
add_action( 'woocommerce_checkout_create_order', 'advshipping_wpm_prepare_advance_order', 999, 1 );
/**
 * Persist the selected advance and original order value before checkout saves.
 *
 * @param WC_Order $order Order to prepare.
 * @return void
 */
function advshipping_wpm_prepare_advance_order( $order ) {
	// WooCommerce can reuse an unpaid order after the shipping choice changes.
	foreach ( array( '_advshipping_wpm_is_advance_order', '_advshipping_wpm_advance_amount', '_advshipping_wpm_due_amount', '_advshipping_wpm_original_total', '_advshipping_wpm_payment_mode', '_advshipping_wpm_advance_paid', '_advshipping_wpm_note_added' ) as $key ) {
		$order->delete_meta_data( $key );
	}
	$uses_plan = advshipping_wpm_uses_gateway_plans() ? advshipping_wpm_gateway_allows_plan( $order->get_payment_method() ) : advshipping_wpm_order_uses_selected_shipping( $order );
	if ( ! $uses_plan || 'cod' === $order->get_payment_method() ) {
		return;
	}
	$shipping = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
	$total    = (float) $order->get_total( 'edit' );
	$advance  = advshipping_wpm_calculate_advance_amount( $shipping, $total );
	if ( $advance <= 0 ) {
		return;
	}
	$order->update_meta_data( '_advshipping_wpm_is_advance_order', 'yes' );
	$order->update_meta_data( '_advshipping_wpm_advance_amount', $advance );
	$order->update_meta_data( '_advshipping_wpm_due_amount', max( 0, $total - $advance ) );
	$order->update_meta_data( '_advshipping_wpm_original_total', $total );
	$order->update_meta_data( '_advshipping_wpm_payment_mode', advshipping_wpm_get_chosen_mode() );
	$order->update_meta_data( '_advshipping_wpm_advance_paid', 'no' );
	$order->set_total( $advance );
}

// Keep gateway amounts stable through on-hold, verification callbacks and retries.
add_filter( 'woocommerce_order_get_total', 'advshipping_wpm_override_total_for_payment_gateway', 999, 2 );
/**
 * Override total for payment gateway.
 *
 * @param float|string $total total value.
 * @param WC_Order     $order order value.
 *
 * @return array Result value.
 */
function advshipping_wpm_override_total_for_payment_gateway( $total, $order ) {
	if ( $order instanceof WC_Order && 'yes' === $order->get_meta( '_advshipping_wpm_is_advance_order' ) ) {
		$advance = $order->get_meta( '_advshipping_wpm_advance_amount' );
		if ( '' !== $advance && null !== $advance ) {
			return (float) $advance;
		}
	}
	return $total;
}

add_action( 'woocommerce_payment_complete', 'advshipping_wpm_confirm_advance_payment' );
/**
 * Confirm advance payment.
 *
 * @param int $order_id order id value.
 *
 * @return void No return value.
 */
function advshipping_wpm_confirm_advance_payment( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || 'yes' !== $order->get_meta( '_advshipping_wpm_is_advance_order' ) || 'yes' === $order->get_meta( '_advshipping_wpm_advance_paid' ) ) {
		return;
	}
	$texts = advshipping_wpm_get_texts();
	$order->update_meta_data( '_advshipping_wpm_advance_paid', 'yes' );
	$order->add_order_note(
		wp_strip_all_tags(
			advshipping_wpm_format_text(
				$texts['order_note'],
				array(
					'advance_amount' => wc_price( $order->get_meta( '_advshipping_wpm_advance_amount' ), array( 'currency' => $order->get_currency() ) ),
					'due_amount'     => wc_price( $order->get_meta( '_advshipping_wpm_due_amount' ), array( 'currency' => $order->get_currency() ) ),
				)
			)
		)
	);
	$order->update_meta_data( '_advshipping_wpm_note_added', 'yes' );
	$order->save();
}
add_action(
	'woocommerce_order_status_changed',
	function ( $order_id, $from, $to ) {
		if ( in_array( $to, array( 'processing', 'completed' ), true ) ) {
			advshipping_wpm_confirm_advance_payment( $order_id );
		}
	},
	20,
	3
);

/**
 * Get advance status label.
 *
 * @param WC_Order $order order value.
 *
 * @return string Result value.
 */
function advshipping_wpm_get_advance_status_label( $order ) {
	$confirmed = advshipping_wpm_advance_is_confirmed( $order );
	if ( 'full_payment' === $order->get_meta( '_advshipping_wpm_payment_mode' ) ) {
		return $confirmed ? __( 'Payment confirmed', 'advshipping-wpm' ) : __( 'Payment awaiting confirmation', 'advshipping-wpm' );
	}
	return $confirmed ? __( 'Advance payment confirmed', 'advshipping-wpm' ) : __( 'Advance payment awaiting confirmation', 'advshipping-wpm' );
}

add_filter(
	'woocommerce_get_order_item_totals',
	function ( $rows, $order ) {
		if ( 'yes' !== $order->get_meta( '_advshipping_wpm_is_advance_order' ) ) {
			return $rows;
		}
		$currency = array( 'currency' => $order->get_currency() );
		if ( isset( $rows['order_total'] ) ) {
			$rows['order_total']['label'] = __( 'Advance payment:', 'advshipping-wpm' );
			$rows['order_total']['value'] = wc_price( $order->get_meta( '_advshipping_wpm_advance_amount' ), $currency );
		}
		$rows['advshipping_wpm_full_total'] = array(
			'label' => __( 'Full order total:', 'advshipping-wpm' ),
			'value' => wc_price( $order->get_meta( '_advshipping_wpm_original_total' ), $currency ),
		);
		if ( advshipping_wpm_get_due_collected( $order ) > 0 ) {
			$rows['advshipping_wpm_collected'] = array(
				'label' => __( 'Delivery payments collected:', 'advshipping-wpm' ),
				'value' => wc_price( advshipping_wpm_get_due_collected( $order ), $currency ),
			);
		}
		$rows['advshipping_wpm_due'] = array(
			'label' => __( 'Outstanding on delivery:', 'advshipping-wpm' ),
			'value' => wc_price( advshipping_wpm_get_outstanding_due( $order ), $currency ),
		);
		return $rows;
	},
	10,
	2
);
