<?php
/**
 * Order collection ledger and native sales analytics.
 *
 * @package AdvShippingWPM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Determine whether the advance was confirmed.
 *
 * @param WC_Order $order Order to inspect.
 *
 * @return bool Whether the advance was confirmed.
 */
function aswpm_advance_is_confirmed( $order ) {
	$flag = $order->get_meta( '_aswpm_advance_paid' );
	return 'yes' === $flag || ( '' === $flag && $order->is_paid() );
}

/**
 * Get collections.
 *
 * @param WC_Order $order order value.
 *
 * @return array Result value.
 */
function aswpm_get_collections( $order ) {
	$entries = $order->get_meta( '_aswpm_collections' );
	return is_array( $entries ) ? $entries : array();
}

/**
 * Get due collected.
 *
 * @param WC_Order $order order value.
 *
 * @return float Result value.
 */
function aswpm_get_due_collected( $order ) {
	$amount = 0;
	foreach ( aswpm_get_collections( $order ) as $entry ) {
		$amount += (float) $entry['amount'];
	}
	return (float) wc_format_decimal( max( 0, $amount ), wc_get_price_decimals() );
}

/**
 * Get outstanding due.
 *
 * @param WC_Order $order order value.
 *
 * @return float Result value.
 */
function aswpm_get_outstanding_due( $order ) {
	return (float) wc_format_decimal( max( 0, (float) $order->get_meta( '_aswpm_due_amount' ) - aswpm_get_due_collected( $order ) ), wc_get_price_decimals() );
}

// Admin item/shipping edits recalculate the sale value, preserving the agreed gateway advance.
add_action( 'woocommerce_order_after_calculate_totals', 'aswpm_update_full_order_value', 20, 2 );
/**
 * Update full order value.
 *
 * @param bool     $and_taxes and taxes value.
 * @param WC_Order $order order value.
 *
 * @return void No return value.
 */
function aswpm_update_full_order_value( $and_taxes, $order ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_aswpm_is_advance_order' ) ) {
		return;
	}
	$full    = (float) $order->get_total( 'edit' );
	$advance = (float) $order->get_meta( '_aswpm_advance_amount' );
	$order->update_meta_data( '_aswpm_original_total', $full );
	$order->update_meta_data( '_aswpm_due_amount', max( 0, $full - $advance ) );
	$order->update_meta_data( '_aswpm_outstanding_due', aswpm_get_outstanding_due( $order ) );
	$order->set_total( $advance );
}

/**
 * Get collection methods.
 *
 * @return array Result value.
 */
function aswpm_get_collection_methods() {
	return array(
		'cash'    => __( 'Cash', 'advshipping-wpm' ),
		'courier' => __( 'Courier settlement', 'advshipping-wpm' ),
		'bkash'   => __( 'bKash', 'advshipping-wpm' ),
		'nagad'   => __( 'Nagad', 'advshipping-wpm' ),
		'bank'    => __( 'Bank transfer', 'advshipping-wpm' ),
		'other'   => __( 'Other', 'advshipping-wpm' ),
	);
}

/**
 * Add collection entry.
 *
 * @param WC_Order $order order value.
 * @param float    $amount amount value.
 * @param string   $method method value.
 * @param string   $reference reference value.
 * @param string   $request_id request id value.
 * @param string   $reverse_id reverse id value.
 *
 * @return bool Result value.
 * @throws InvalidArgumentException When collection input or order state is invalid.
 */
function aswpm_add_collection_entry( $order, $amount, $method, $reference, $request_id, $reverse_id = '' ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_aswpm_is_advance_order' ) ) {
		throw new InvalidArgumentException( esc_html__( 'This order does not use advance payments.', 'advshipping-wpm' ) );
	}
	if ( ! preg_match( '/^[a-zA-Z0-9-]{16,64}$/', $request_id ) ) {
		throw new InvalidArgumentException( esc_html__( 'Invalid collection request. Reload the order and try again.', 'advshipping-wpm' ) );
	}
	$entries = aswpm_get_collections( $order );
	foreach ( $entries as $entry ) {
		if ( $request_id === $entry['id'] ) {
			return false;
		}
	}
	if ( $reverse_id ) {
		$original = null;
		foreach ( $entries as $entry ) {
			if ( $reverse_id === $entry['id'] && (float) $entry['amount'] > 0 ) {
				$original = $entry;
			}
			if ( isset( $entry['reverses'] ) && $reverse_id === $entry['reverses'] ) {
				throw new InvalidArgumentException( esc_html__( 'This collection has already been reversed.', 'advshipping-wpm' ) );
			}
		}
		if ( ! $original ) {
			throw new InvalidArgumentException( esc_html__( 'Collection entry not found.', 'advshipping-wpm' ) );
		}
		$amount    = - (float) $original['amount'];
		$method    = $original['method'];
		$reference = $original['reference'];
	} else {
		if ( ! aswpm_advance_is_confirmed( $order ) ) {
			throw new InvalidArgumentException( esc_html__( 'Verify the advance payment before recording delivery collections.', 'advshipping-wpm' ) );
		}
		if ( in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) || (float) $order->get_total_refunded() > 0 ) {
			throw new InvalidArgumentException( esc_html__( 'This order is cancelled, failed or has refunds. Reconcile it before recording further collections.', 'advshipping-wpm' ) );
		}
		$amount = (float) wc_format_decimal( $amount, wc_get_price_decimals() );
		if ( ! is_finite( $amount ) || $amount <= 0 || $amount > aswpm_get_outstanding_due( $order ) ) {
			throw new InvalidArgumentException( esc_html__( 'Enter an amount greater than zero and no more than the outstanding balance.', 'advshipping-wpm' ) );
		}
		if ( ! array_key_exists( $method, aswpm_get_collection_methods() ) ) {
			throw new InvalidArgumentException( esc_html__( 'Choose a valid collection method.', 'advshipping-wpm' ) );
		}
	}
	$reference = substr( sanitize_text_field( $reference ), 0, 200 );
	$entries[] = array(
		'id'          => $request_id,
		'amount'      => $amount,
		'method'      => $method,
		'reference'   => sanitize_text_field( $reference ),
		'recorded_at' => gmdate( 'Y-m-d H:i:s' ),
		'user_id'     => get_current_user_id(),
		'reverses'    => $reverse_id,
	);
	$order->update_meta_data( '_aswpm_collections', $entries );
	$order->update_meta_data( '_aswpm_due_collected', aswpm_get_due_collected( $order ) );
	$order->update_meta_data( '_aswpm_outstanding_due', aswpm_get_outstanding_due( $order ) );
	$order->add_order_note(
		sprintf(
			$reverse_id ? /* translators: %1$s: Collection amount, %2$s: collection method, %3$s: receipt reference, %4$s: remaining balance. */
			__( 'Delivery collection reversed: %1$s. Method: %2$s. Reference: %3$s. Outstanding: %4$s.', 'advshipping-wpm' ) : /* translators: %1$s: Collection amount, %2$s: collection method, %3$s: receipt reference, %4$s: remaining balance. */
			__( 'Delivery collection recorded: %1$s. Method: %2$s. Reference: %3$s. Outstanding: %4$s.', 'advshipping-wpm' ),
			wp_strip_all_tags( wc_price( abs( $amount ), array( 'currency' => $order->get_currency() ) ) ),
			aswpm_get_collection_methods()[ $method ],
			sanitize_text_field( $reference ),
			wp_strip_all_tags( wc_price( aswpm_get_outstanding_due( $order ), array( 'currency' => $order->get_currency() ) ) )
		)
	);
	// Never mutate order/gateway totals, fulfillment status or the gateway transaction ID.
	$order->save();
	return true;
}

/** Native sales analytics use full order value; collection entries are not new sales. */
add_filter( 'woocommerce_analytics_update_order_stats_data', 'aswpm_full_value_analytics', 20, 2 );
/**
 * Full value analytics.
 *
 * @param array    $data data value.
 * @param WC_Order $order order value.
 *
 * @return array Result value.
 */
function aswpm_full_value_analytics( $data, $order ) {
	if ( $order instanceof WC_Order && 'yes' === $order->get_meta( '_aswpm_is_advance_order' ) && '' !== $order->get_meta( '_aswpm_original_total' ) ) {
		$full                = (float) $order->get_meta( '_aswpm_original_total' );
		$data['total_sales'] = $full;
		$data['net_total']   = $full - (float) $order->get_total_tax() - (float) $order->get_shipping_total();
	}
	return $data;
}

/**
 * Sync order analytics.
 *
 * @param int $order_id order id value.
 *
 * @return bool Result value.
 */
function aswpm_sync_order_analytics( $order_id ) {
	$class = '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore';
	if ( ! class_exists( $class ) ) {
		return false;
	}
	$result = $class::sync_order( $order_id );
	if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Cache' ) ) {
		\Automattic\WooCommerce\Admin\API\Reports\Cache::invalidate();
	}
	return true === $result;
}

add_action( 'wp_ajax_aswpm_order_collection', 'aswpm_ajax_order_collection' );
/**
 * Handle authorized collection writes and analytics refresh requests.
 *
 * @return void No return value.
 * @throws InvalidArgumentException Caught internally to return a validation error.
 */
function aswpm_ajax_order_collection() {
	foreach ( array( 'order_id', 'nonce', 'operation', 'amount', 'method', 'reference', 'request_id', 'entry_id' ) as $key ) {
		if ( isset( $_POST[ $key ] ) && ! is_scalar( $_POST[ $key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid collection request.', 'advshipping-wpm' ) ), 400 );
		}
	}
	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You cannot edit this order.', 'advshipping-wpm' ) ), 403 );
	}
	check_ajax_referer( 'aswpm_collection_' . $order_id, 'nonce' );
	global $wpdb;
	// MySQL/MariaDB connection lock serializes simultaneous collections for this order.
	$lock = 'aswpm_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . $order_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Connection-level advisory locks cannot be cached and prevent duplicate financial ledger writes.
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
		wp_send_json_error( array( 'message' => __( 'Another collection is being saved. Please try again.', 'advshipping-wpm' ) ), 409 );
	}
	$error        = '';
	$analytics_ok = false;
	try {
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' !== $order->get_meta( '_aswpm_is_advance_order' ) ) {
			throw new InvalidArgumentException( esc_html__( 'Advance payment order not found.', 'advshipping-wpm' ) );
		}
		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		if ( in_array( $operation, array( 'record', 'reverse' ), true ) ) {
			$amount     = isset( $_POST['amount'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
			$method     = isset( $_POST['method'] ) ? sanitize_key( wp_unslash( $_POST['method'] ) ) : '';
			$reference  = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
			$request_id = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
			$reverse_id = 'reverse' === $operation && isset( $_POST['entry_id'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_id'] ) ) : '';
			if ( 'reverse' === $operation && ! $reverse_id ) {
				throw new InvalidArgumentException( esc_html__( 'Collection entry not found.', 'advshipping-wpm' ) );
			}
			aswpm_add_collection_entry( $order, $amount, $method, $reference, $request_id, $reverse_id );
		} elseif ( 'sync' !== $operation ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid collection operation.', 'advshipping-wpm' ) );
		}
		try {
			$analytics_ok = aswpm_sync_order_analytics( $order_id );
		} catch ( Exception $analytics_error ) {
			$analytics_ok = false;
		}
	} catch ( Exception $exception ) {
		$error = $exception->getMessage();
	} finally {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the same uncached connection-level advisory lock.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
	if ( $error ) {
		wp_send_json_error( array( 'message' => $error ), 400 );
	}
	wp_send_json_success(
		array(
			'html'    => aswpm_get_order_payment_panel( $order ),
			'message' => $analytics_ok ? __( 'Saved. Order analytics refreshed.', 'advshipping-wpm' ) : __( 'Saved. Analytics refresh is pending; use WooCommerce historical data import if needed.', 'advshipping-wpm' ),
		)
	);
}

add_action( 'woocommerce_admin_order_data_after_order_details', 'aswpm_render_order_payment_summary' );
/**
 * Render order payment summary.
 *
 * @param WC_Order $order order value.
 *
 * @return void No return value.
 */
function aswpm_render_order_payment_summary( $order ) {
	if ( ! $order instanceof WC_Order || 'yes' !== $order->get_meta( '_aswpm_is_advance_order' ) ) {
		return;
	}
	echo '<div class="aswpm-order-payment-slot">' . aswpm_get_order_payment_panel( $order ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The panel builder escapes all dynamic data and returns required form controls.
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'aswpm-order-payments', plugin_dir_url( __DIR__ . '/../adv-shipping-wpm.php' ) . 'assets/css/aswpm-order-payments.css', array(), ASWPM_VERSION );
		wp_enqueue_script( 'aswpm-order-payments', plugin_dir_url( __DIR__ . '/../adv-shipping-wpm.php' ) . 'assets/js/aswpm-order-payments.js', array( 'jquery' ), ASWPM_VERSION, true );
	}
);

/**
 * Get order payment panel.
 *
 * @param WC_Order $order order value.
 *
 * @return string Result value.
 */
function aswpm_get_order_payment_panel( $order ) {
	$currency     = array( 'currency' => $order->get_currency() );
	$full         = (float) $order->get_meta( '_aswpm_original_total' );
	$advance      = aswpm_advance_is_confirmed( $order ) ? (float) $order->get_meta( '_aswpm_advance_amount' ) : 0;
	$collected    = aswpm_get_due_collected( $order );
	$due          = aswpm_get_outstanding_due( $order );
	$confirmed    = aswpm_advance_is_confirmed( $order );
	$has_refunds  = (float) $order->get_total_refunded() > 0;
	$status_label = $confirmed ? ( $due <= 0 ? __( 'Fully collected', 'advshipping-wpm' ) : __( 'Collection outstanding', 'advshipping-wpm' ) ) : __( 'Awaiting advance', 'advshipping-wpm' );
	if ( $has_refunds ) {
		$status_label = __( 'Refund reconciliation', 'advshipping-wpm' );
	}
	$can_record = aswpm_advance_is_confirmed( $order ) && $due > 0 && ! in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) && (float) $order->get_total_refunded() <= 0;
	$methods    = aswpm_get_collection_methods();
	$entries    = aswpm_get_collections( $order );
	$reversed   = array();
	foreach ( $entries as $entry ) {
		if ( ! empty( $entry['reverses'] ) ) {
			$reversed[] = $entry['reverses'];
		}
	}
	ob_start(); ?>
	<div class="aswpm-order-payments" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'aswpm_collection_' . $order->get_id() ) ); ?>" data-request-id="<?php echo esc_attr( wp_generate_uuid4() ); ?>" data-reverse-confirm="<?php esc_attr_e( 'Reverse this collection entry? The amount will return to the outstanding balance. This records a correction; it does not refund money.', 'advshipping-wpm' ); ?>" data-saving-text="<?php esc_attr_e( 'Saving...', 'advshipping-wpm' ); ?>" data-error-text="<?php esc_attr_e( 'Could not confirm the result. Retry this request or reload the order to check the ledger.', 'advshipping-wpm' ); ?>">
		<div class="aswpm-payment-top"><h3><?php esc_html_e( 'Payments', 'advshipping-wpm' ); ?></h3><span class="aswpm-payment-status <?php echo $due <= 0 && $confirmed && ! $has_refunds ? 'is-settled' : ''; ?>"><?php echo esc_html( $status_label ); ?></span></div>
		<div class="aswpm-payment-metrics">
		<?php
		foreach ( array(
			__( 'Order value', 'advshipping-wpm' )     => $full,
			__( 'Advance paid', 'advshipping-wpm' )    => $advance,
			__( 'Due collected', 'advshipping-wpm' )   => $collected,
			__( 'Outstanding due', 'advshipping-wpm' ) => $due,
		) as $label => $amount ) :
			?>
			<div><span><?php echo esc_html( $label ); ?></span><strong><?php echo wp_kses_post( wc_price( $amount, $currency ) ); ?></strong></div>
		<?php endforeach; ?>
		</div>
		<?php
		if ( ! $confirmed ) :
			?>
			<p><?php esc_html_e( 'Advance awaiting verification:', 'advshipping-wpm' ); ?> <?php echo wp_kses_post( wc_price( $order->get_meta( '_aswpm_advance_amount' ), $currency ) ); ?></p><?php endif; ?>
		<?php
		if ( $has_refunds ) :
			?>
			<p class="aswpm-payment-warning"><?php esc_html_e( 'Collections show historical receipts. Refunds require manual reconciliation against online and delivery payments.', 'advshipping-wpm' ); ?></p><?php endif; ?>
		<?php
		if ( $advance + $collected > $full ) :
			?>
			<p class="aswpm-payment-warning"><?php esc_html_e( 'Recorded payments exceed the revised order value. Review the ledger and arrange any required refund separately.', 'advshipping-wpm' ); ?></p><?php endif; ?>
		<?php if ( $can_record ) : ?>
		<details class="aswpm-collection-editor"><summary><?php esc_html_e( 'Record collection', 'advshipping-wpm' ); ?></summary>
		<div class="aswpm-collection-fields">
			<label><?php esc_html_e( 'Amount collected', 'advshipping-wpm' ); ?><input class="aswpm-collection-amount" type="number" min="<?php echo esc_attr( pow( 10, -wc_get_price_decimals() ) ); ?>" max="<?php echo esc_attr( $due ); ?>" step="<?php echo esc_attr( pow( 10, -wc_get_price_decimals() ) ); ?>" value="<?php echo esc_attr( $due ); ?>" /></label>
			<label><?php esc_html_e( 'Collection method', 'advshipping-wpm' ); ?><select class="aswpm-collection-method">
			<?php
			foreach ( $methods as $method => $label ) :
				?>
				<option value="<?php echo esc_attr( $method ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label class="aswpm-reference-field"><?php esc_html_e( 'Reference / receipt', 'advshipping-wpm' ); ?><input class="aswpm-collection-reference" type="text" maxlength="200" placeholder="<?php esc_attr_e( 'Optional reference', 'advshipping-wpm' ); ?>" /></label>
			<button type="button" class="button button-primary aswpm-record-collection"><?php esc_html_e( 'Record collection', 'advshipping-wpm' ); ?></button>
		</div>
		<p class="description"><?php esc_html_e( 'Record money already received. Order status stays unchanged.', 'advshipping-wpm' ); ?></p>
		</details>
			<?php
		elseif ( $due > 0 ) :
			?>
			<p class="aswpm-payment-warning"><?php esc_html_e( 'Verify the advance first. Further collections are disabled for cancelled, failed or refunded orders; refund cases require manual reconciliation.', 'advshipping-wpm' ); ?></p><?php endif; ?>
		<?php if ( $entries ) : ?>
		<details class="aswpm-collection-history"><summary>
			<?php
			echo esc_html(
				sprintf( /* translators: %d: Number of recorded collection entries. */
					__( 'Collection history (%d)', 'advshipping-wpm' ),
					count( $entries )
				)
			);
			?>
															</summary><ul class="aswpm-collection-ledger">
			<?php
			foreach ( array_reverse( $entries ) as $entry ) :
				$user = get_userdata( $entry['user_id'] );
				?>
		<li><div class="aswpm-ledger-entry-top"><strong><?php echo wp_kses_post( wc_price( $entry['amount'], $currency ) ); ?></strong><span><?php echo esc_html( $methods[ $entry['method'] ] ?? $entry['method'] ); ?></span></div>
		<small><?php echo esc_html( get_date_from_gmt( $entry['recorded_at'], 'Y-m-d H:i' ) . ' · ' . ( $user ? $user->display_name : '#' . $entry['user_id'] ) ); ?></small>
				<?php
				if ( $entry['reference'] ) :
					?>
					<small><?php echo esc_html( $entry['reference'] ); ?></small><?php endif; ?>
				<?php
				if ( $entry['amount'] > 0 && ! in_array( $entry['id'], $reversed, true ) ) :
					?>
					<button type="button" class="button-link aswpm-reverse-collection" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>"><?php esc_html_e( 'Reverse entry', 'advshipping-wpm' ); ?></button>
					<?php
else :
					echo esc_html__( 'Reversed / correction', 'advshipping-wpm' );
endif;
?>
		</li><?php endforeach; ?>
		</ul></details><?php endif; ?>
		<details class="aswpm-accounting-details"><summary><?php esc_html_e( 'Analytics & help', 'advshipping-wpm' ); ?></summary><p class="aswpm-accounting-help"><?php esc_html_e( 'Sales analytics use full order value. Collections do not add new sales. Completing an order does not collect its balance.', 'advshipping-wpm' ); ?></p><button type="button" class="button aswpm-sync-analytics"><?php esc_html_e( 'Refresh order analytics', 'advshipping-wpm' ); ?></button></details>
		<div class="aswpm-payment-footer"><span class="aswpm-collection-feedback" role="status" aria-live="polite" tabindex="-1"></span></div>
	</div>
	<?php
	return ob_get_clean();
}
