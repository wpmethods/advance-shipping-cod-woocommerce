<?php
defined( 'ABSPATH' ) || exit;

function wcasc_advance_is_confirmed( $order ) {
	$flag = $order->get_meta( '_wcasc_advance_paid' );
	return 'yes' === $flag || ( '' === $flag && $order->is_paid() );
}

function wcasc_get_collections( $order ) {
	$entries = $order->get_meta( '_wcasc_collections' );
	return is_array( $entries ) ? $entries : array();
}

function wcasc_get_due_collected( $order ) {
	$amount = 0;
	foreach ( wcasc_get_collections( $order ) as $entry ) {
		$amount += (float) $entry['amount'];
	}
	return (float) wc_format_decimal( max( 0, $amount ), wc_get_price_decimals() );
}

function wcasc_get_outstanding_due( $order ) {
	return (float) wc_format_decimal( max( 0, (float) $order->get_meta( '_wcasc_due_amount' ) - wcasc_get_due_collected( $order ) ), wc_get_price_decimals() );
}

// Admin item/shipping edits recalculate the sale value, preserving the agreed gateway advance.
add_action( 'woocommerce_order_after_calculate_totals', 'wcasc_update_full_order_value', 20, 2 );
function wcasc_update_full_order_value( $and_taxes, $order ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) { return; }
	$full = (float) $order->get_total( 'edit' );
	$advance = (float) $order->get_meta( '_wcasc_advance_amount' );
	$order->update_meta_data( '_wcasc_original_total', $full );
	$order->update_meta_data( '_wcasc_due_amount', max( 0, $full - $advance ) );
	$order->update_meta_data( '_wcasc_outstanding_due', wcasc_get_outstanding_due( $order ) );
	$order->set_total( $advance );
}

function wcasc_get_collection_methods() {
	return array( 'cash' => __( 'Cash', 'wcasc' ), 'courier' => __( 'Courier settlement', 'wcasc' ), 'bkash' => __( 'bKash', 'wcasc' ), 'nagad' => __( 'Nagad', 'wcasc' ), 'bank' => __( 'Bank transfer', 'wcasc' ), 'other' => __( 'Other', 'wcasc' ) );
}

/** Append-only ledger: retries are idempotent; corrections append a negative reversal. */
function wcasc_add_collection_entry( $order, $amount, $method, $reference, $request_id, $reverse_id = '' ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) {
		throw new InvalidArgumentException( __( 'This order does not use advance payments.', 'wcasc' ) );
	}
	if ( ! preg_match( '/^[a-zA-Z0-9-]{16,64}$/', $request_id ) ) {
		throw new InvalidArgumentException( __( 'Invalid collection request. Reload the order and try again.', 'wcasc' ) );
	}
	$entries = wcasc_get_collections( $order );
	foreach ( $entries as $entry ) {
		if ( $request_id === $entry['id'] ) { return false; }
	}
	if ( $reverse_id ) {
		$original = null;
		foreach ( $entries as $entry ) {
			if ( $reverse_id === $entry['id'] && (float) $entry['amount'] > 0 ) { $original = $entry; }
			if ( isset( $entry['reverses'] ) && $reverse_id === $entry['reverses'] ) {
				throw new InvalidArgumentException( __( 'This collection has already been reversed.', 'wcasc' ) );
			}
		}
		if ( ! $original ) { throw new InvalidArgumentException( __( 'Collection entry not found.', 'wcasc' ) ); }
		$amount = - (float) $original['amount'];
		$method = $original['method'];
		$reference = $original['reference'];
	} else {
		if ( ! wcasc_advance_is_confirmed( $order ) ) {
			throw new InvalidArgumentException( __( 'Verify the advance payment before recording delivery collections.', 'wcasc' ) );
		}
		if ( in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) || (float) $order->get_total_refunded() > 0 ) {
			throw new InvalidArgumentException( __( 'This order is cancelled, failed or has refunds. Reconcile it before recording further collections.', 'wcasc' ) );
		}
		$amount = (float) wc_format_decimal( $amount, wc_get_price_decimals() );
		if ( ! is_finite( $amount ) || $amount <= 0 || $amount > wcasc_get_outstanding_due( $order ) ) {
			throw new InvalidArgumentException( __( 'Enter an amount greater than zero and no more than the outstanding balance.', 'wcasc' ) );
		}
		if ( ! array_key_exists( $method, wcasc_get_collection_methods() ) ) {
			throw new InvalidArgumentException( __( 'Choose a valid collection method.', 'wcasc' ) );
		}
	}
	$reference = substr( sanitize_text_field( $reference ), 0, 200 );
	$entries[] = array( 'id' => $request_id, 'amount' => $amount, 'method' => $method, 'reference' => sanitize_text_field( $reference ), 'recorded_at' => gmdate( 'Y-m-d H:i:s' ), 'user_id' => get_current_user_id(), 'reverses' => $reverse_id );
	$order->update_meta_data( '_wcasc_collections', $entries );
	$order->update_meta_data( '_wcasc_due_collected', wcasc_get_due_collected( $order ) );
	$order->update_meta_data( '_wcasc_outstanding_due', wcasc_get_outstanding_due( $order ) );
	$order->add_order_note( sprintf( $reverse_id ? __( 'Delivery collection reversed: %1$s. Method: %2$s. Reference: %3$s. Outstanding: %4$s.', 'wcasc' ) : __( 'Delivery collection recorded: %1$s. Method: %2$s. Reference: %3$s. Outstanding: %4$s.', 'wcasc' ), wp_strip_all_tags( wc_price( abs( $amount ), array( 'currency' => $order->get_currency() ) ) ), wcasc_get_collection_methods()[ $method ], sanitize_text_field( $reference ), wp_strip_all_tags( wc_price( wcasc_get_outstanding_due( $order ), array( 'currency' => $order->get_currency() ) ) ) ) );
	// Never mutate order/gateway totals, fulfillment status or the gateway transaction ID.
	$order->save();
	return true;
}

/** Native sales analytics use full order value; collection entries are not new sales. */
add_filter( 'woocommerce_analytics_update_order_stats_data', 'wcasc_full_value_analytics', 20, 2 );
function wcasc_full_value_analytics( $data, $order ) {
	if ( $order instanceof WC_Order && 'yes' === $order->get_meta( '_wcasc_is_advance_order' ) && '' !== $order->get_meta( '_wcasc_original_total' ) ) {
		$full = (float) $order->get_meta( '_wcasc_original_total' );
		$data['total_sales'] = $full;
		$data['net_total'] = $full - (float) $order->get_total_tax() - (float) $order->get_shipping_total();
	}
	return $data;
}

function wcasc_sync_order_analytics( $order_id ) {
	$class = '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore';
	if ( ! class_exists( $class ) ) { return false; }
	$result = $class::sync_order( $order_id );
	if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Cache' ) ) {
		\Automattic\WooCommerce\Admin\API\Reports\Cache::invalidate();
	}
	return true === $result;
}

add_action( 'wp_ajax_wcasc_order_collection', 'wcasc_ajax_order_collection' );
function wcasc_ajax_order_collection() {
	foreach ( array( 'order_id', 'nonce', 'operation', 'amount', 'method', 'reference', 'request_id', 'entry_id' ) as $key ) {
		if ( isset( $_POST[ $key ] ) && ! is_scalar( $_POST[ $key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid collection request.', 'wcasc' ) ), 400 );
		}
	}
	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You cannot edit this order.', 'wcasc' ) ), 403 );
	}
	check_ajax_referer( 'wcasc_collection_' . $order_id, 'nonce' );
	global $wpdb;
	// MySQL/MariaDB connection lock serializes simultaneous collections for this order.
	$lock = 'wcasc_' . md5( $wpdb->prefix . ':' . $order_id );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Another collection is being saved. Please try again.', 'wcasc' ) ), 409 );
	}
	$error = ''; $analytics_ok = false;
	try {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) { throw new InvalidArgumentException( __( 'Advance payment order not found.', 'wcasc' ) ); }
		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		if ( in_array( $operation, array( 'record', 'reverse' ), true ) ) {
			$amount = isset( $_POST['amount'] ) ? wc_format_decimal( wp_unslash( $_POST['amount'] ) ) : 0;
			$method = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '';
			$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
			$request_id = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
			$reverse_id = 'reverse' === $operation && isset( $_POST['entry_id'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_id'] ) ) : '';
			if ( 'reverse' === $operation && ! $reverse_id ) { throw new InvalidArgumentException( __( 'Collection entry not found.', 'wcasc' ) ); }
			wcasc_add_collection_entry( $order, $amount, $method, $reference, $request_id, $reverse_id );
		} elseif ( 'sync' !== $operation ) { throw new InvalidArgumentException( __( 'Invalid collection operation.', 'wcasc' ) ); }
		try { $analytics_ok = wcasc_sync_order_analytics( $order_id ); } catch ( Exception $analytics_error ) { $analytics_ok = false; }
	} catch ( Exception $exception ) {
		$error = $exception->getMessage();
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
	if ( $error ) { wp_send_json_error( array( 'message' => $error ), 400 ); }
	wp_send_json_success( array( 'html' => wcasc_get_order_payment_panel( $order ), 'message' => $analytics_ok ? __( 'Saved. Order analytics refreshed.', 'wcasc' ) : __( 'Saved. Analytics refresh is pending; use WooCommerce historical data import if needed.', 'wcasc' ) ) );
}

add_action( 'woocommerce_admin_order_data_after_order_details', 'wcasc_render_order_payment_summary' );
function wcasc_render_order_payment_summary( $order ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_wcasc_is_advance_order' ) ) { return; }
	echo '<div class="wcasc-order-payment-slot">' . wcasc_get_order_payment_panel( $order ) . '</div>';
}

add_action( 'admin_enqueue_scripts', function () {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ), true ) ) { return; }
	wp_enqueue_style( 'wcasc-order-payments', plugin_dir_url( __DIR__ . '/../advance-shipping-cod-woocommerce.php' ) . 'assets/css/wcasc-order-payments.css', array(), WCASC_VERSION );
	wp_enqueue_script( 'wcasc-order-payments', plugin_dir_url( __DIR__ . '/../advance-shipping-cod-woocommerce.php' ) . 'assets/js/wcasc-order-payments.js', array( 'jquery' ), WCASC_VERSION, true );
} );

function wcasc_get_order_payment_panel( $order ) {
	$currency = array( 'currency' => $order->get_currency() );
	$full = (float) $order->get_meta( '_wcasc_original_total' );
	$advance = wcasc_advance_is_confirmed( $order ) ? (float) $order->get_meta( '_wcasc_advance_amount' ) : 0;
	$collected = wcasc_get_due_collected( $order );
	$due = wcasc_get_outstanding_due( $order );
	$confirmed = wcasc_advance_is_confirmed( $order );
	$has_refunds = (float) $order->get_total_refunded() > 0;
	$status_label = $confirmed ? ( $due <= 0 ? __( 'Fully collected', 'wcasc' ) : __( 'Collection outstanding', 'wcasc' ) ) : __( 'Awaiting advance', 'wcasc' );
	if ( $has_refunds ) { $status_label = __( 'Refund reconciliation', 'wcasc' ); }
	$can_record = wcasc_advance_is_confirmed( $order ) && $due > 0 && ! in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) && (float) $order->get_total_refunded() <= 0;
	$methods = wcasc_get_collection_methods();
	$entries = wcasc_get_collections( $order ); $reversed = array();
	foreach ( $entries as $entry ) { if ( ! empty( $entry['reverses'] ) ) { $reversed[] = $entry['reverses']; } }
	ob_start(); ?>
	<div class="wcasc-order-payments" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wcasc_collection_' . $order->get_id() ) ); ?>" data-request-id="<?php echo esc_attr( wp_generate_uuid4() ); ?>" data-reverse-confirm="<?php esc_attr_e( 'Reverse this collection entry? The amount will return to the outstanding balance. This records a correction; it does not refund money.', 'wcasc' ); ?>" data-saving-text="<?php esc_attr_e( 'Saving...', 'wcasc' ); ?>" data-error-text="<?php esc_attr_e( 'Could not confirm the result. Retry this request or reload the order to check the ledger.', 'wcasc' ); ?>">
		<div class="wcasc-payment-top"><h3><?php esc_html_e( 'Payments', 'wcasc' ); ?></h3><span class="wcasc-payment-status <?php echo $due <= 0 && $confirmed && ! $has_refunds ? 'is-settled' : ''; ?>"><?php echo esc_html( $status_label ); ?></span></div>
		<div class="wcasc-payment-metrics">
		<?php foreach ( array( __( 'Order value', 'wcasc' ) => $full, __( 'Advance paid', 'wcasc' ) => $advance, __( 'Due collected', 'wcasc' ) => $collected, __( 'Outstanding due', 'wcasc' ) => $due ) as $label => $amount ) : ?>
			<div><span><?php echo esc_html( $label ); ?></span><strong><?php echo wp_kses_post( wc_price( $amount, $currency ) ); ?></strong></div>
		<?php endforeach; ?>
		</div>
		<?php if ( ! $confirmed ) : ?><p><?php esc_html_e( 'Advance awaiting verification:', 'wcasc' ); ?> <?php echo wp_kses_post( wc_price( $order->get_meta( '_wcasc_advance_amount' ), $currency ) ); ?></p><?php endif; ?>
		<?php if ( $has_refunds ) : ?><p class="wcasc-payment-warning"><?php esc_html_e( 'Collections show historical receipts. Refunds require manual reconciliation against online and delivery payments.', 'wcasc' ); ?></p><?php endif; ?>
		<?php if ( $advance + $collected > $full ) : ?><p class="wcasc-payment-warning"><?php esc_html_e( 'Recorded payments exceed the revised order value. Review the ledger and arrange any required refund separately.', 'wcasc' ); ?></p><?php endif; ?>
		<?php if ( $can_record ) : ?>
		<details class="wcasc-collection-editor"><summary><?php esc_html_e( 'Record collection', 'wcasc' ); ?></summary>
		<div class="wcasc-collection-fields">
			<label><?php esc_html_e( 'Amount collected', 'wcasc' ); ?><input class="wcasc-collection-amount" type="number" min="<?php echo esc_attr( pow( 10, -wc_get_price_decimals() ) ); ?>" max="<?php echo esc_attr( $due ); ?>" step="<?php echo esc_attr( pow( 10, -wc_get_price_decimals() ) ); ?>" value="<?php echo esc_attr( $due ); ?>" /></label>
			<label><?php esc_html_e( 'Collection method', 'wcasc' ); ?><select class="wcasc-collection-method"><?php foreach ( $methods as $method => $label ) : ?><option value="<?php echo esc_attr( $method ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label class="wcasc-reference-field"><?php esc_html_e( 'Reference / receipt', 'wcasc' ); ?><input class="wcasc-collection-reference" type="text" maxlength="200" placeholder="<?php esc_attr_e( 'Optional reference', 'wcasc' ); ?>" /></label>
			<button type="button" class="button button-primary wcasc-record-collection"><?php esc_html_e( 'Record collection', 'wcasc' ); ?></button>
		</div>
		<p class="description"><?php esc_html_e( 'Record money already received. Order status stays unchanged.', 'wcasc' ); ?></p>
		</details>
		<?php elseif ( $due > 0 ) : ?><p class="wcasc-payment-warning"><?php esc_html_e( 'Verify the advance first. Further collections are disabled for cancelled, failed or refunded orders; refund cases require manual reconciliation.', 'wcasc' ); ?></p><?php endif; ?>
		<?php if ( $entries ) : ?>
		<details class="wcasc-collection-history"><summary><?php echo esc_html( sprintf( __( 'Collection history (%d)', 'wcasc' ), count( $entries ) ) ); ?></summary><ul class="wcasc-collection-ledger">
		<?php foreach ( array_reverse( $entries ) as $entry ) : $user = get_userdata( $entry['user_id'] ); ?>
		<li><div class="wcasc-ledger-entry-top"><strong><?php echo wp_kses_post( wc_price( $entry['amount'], $currency ) ); ?></strong><span><?php echo esc_html( $methods[ $entry['method'] ] ?? $entry['method'] ); ?></span></div>
		<small><?php echo esc_html( get_date_from_gmt( $entry['recorded_at'], 'Y-m-d H:i' ) . ' · ' . ( $user ? $user->display_name : '#' . $entry['user_id'] ) ); ?></small>
		<?php if ( $entry['reference'] ) : ?><small><?php echo esc_html( $entry['reference'] ); ?></small><?php endif; ?>
		<?php if ( $entry['amount'] > 0 && ! in_array( $entry['id'], $reversed, true ) ) : ?><button type="button" class="button-link wcasc-reverse-collection" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>"><?php esc_html_e( 'Reverse entry', 'wcasc' ); ?></button><?php else : echo esc_html__( 'Reversed / correction', 'wcasc' ); endif; ?>
		</li><?php endforeach; ?>
		</ul></details><?php endif; ?>
		<details class="wcasc-accounting-details"><summary><?php esc_html_e( 'Analytics & help', 'wcasc' ); ?></summary><p class="wcasc-accounting-help"><?php esc_html_e( 'Sales analytics use full order value. Collections do not add new sales. Completing an order does not collect its balance.', 'wcasc' ); ?></p><button type="button" class="button wcasc-sync-analytics"><?php esc_html_e( 'Refresh order analytics', 'wcasc' ); ?></button></details>
		<div class="wcasc-payment-footer"><span class="wcasc-collection-feedback" role="status" aria-live="polite" tabindex="-1"></span></div>
	</div>
	<?php return ob_get_clean();
}
