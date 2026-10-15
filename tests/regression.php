<?php
/** Standalone regression checks: php tests/regression.php. No database writes. */
if ( 'cli' !== PHP_SAPI ) { exit; }
define( 'ABSPATH', __DIR__ );
define( 'DB_NAME', 'regression-test' );
$options = array();
$hooks = array();
$orders = array();
$errors = array();
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][ $priority ][] = array( $callback, $args ); }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { add_action( $name, $callback, $priority, $args ); }
function do_action( $name, ...$args ) {
	$callbacks = $GLOBALS['hooks'][ $name ] ?? array(); ksort( $callbacks );
	foreach ( $callbacks as $group ) { foreach ( $group as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); } }
}
function apply_filters( $name, $value, ...$args ) {
	$callbacks = $GLOBALS['hooks'][ $name ] ?? array(); ksort( $callbacks );
	foreach ( $callbacks as $group ) { foreach ( $group as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) ); } }
	return $value;
}
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function __( $text, $domain = '' ) { return $text; }
function plugin_basename( $file ) { return basename( $file ); }
function wc_format_decimal( $number, $decimals = false ) { return number_format( (float) $number, false === $decimals ? 2 : $decimals, '.', '' ); }
function wc_get_price_decimals() { return 2; }
function wc_price( $number, $args = array() ) { return 'BDT ' . number_format( (float) $number, 2 ); }
function wp_parse_args( $saved, $defaults ) { return array_merge( $defaults, $saved ); }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#([a-f0-9]{3}){1,2}$/i', $value ) ? $value : null; }
function checked( $checked, $current = true, $display = true ) { $html = (string) $checked === (string) $current ? 'checked="checked"' : ''; if ( $display ) { echo $html; } return $html; }
function get_current_user_id() { return 7; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function esc_html_e( $value, $domain = '' ) { echo esc_html( $value ); }
function esc_attr_e( $value, $domain = '' ) { echo esc_attr( $value ); }
function wp_kses_post( $value ) { return $value; }
function wp_create_nonce( $action ) { return 'test-nonce'; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000001'; }
function get_userdata( $id ) { return (object) array( 'display_name' => 'Test Staff' ); }
function get_date_from_gmt( $date, $format ) { return $date; }
function current_user_can( $capability, ...$args ) { return $GLOBALS['can_edit'] ?? true; }
class TestJsonResponse extends RuntimeException {
	public $success; public $data;
	public function __construct( $success, $data ) { $this->success = $success; $this->data = $data; }
}
function wp_send_json_error( $data, $status = 200 ) { throw new TestJsonResponse( false, $data ); }
function wp_send_json_success( $data ) { throw new TestJsonResponse( true, $data ); }
function check_ajax_referer( $action, $field ) { if ( 'test-nonce' !== ( $_POST[ $field ] ?? '' ) ) { wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 ); } }
class TestDatabase {
	public $prefix = 'test_'; public $available = true; public $released = 0;
	public function prepare( $query, ...$args ) { return $query; }
	public function get_var( $query ) { if ( false !== strpos( $query, 'RELEASE_LOCK' ) ) { $this->released++; return 1; } return $this->available ? 1 : 0; }
}
function wc_add_notice( $text, $type ) { $GLOBALS['errors'][] = $text; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function is_checkout() { return true; }
function is_checkout_pay_page() { return ! empty( $GLOBALS['pay_page'] ); }
function absint( $value ) { return abs( (int) $value ); }
function get_query_var( $key ) { return 1; }
function is_order_received_page() { return false; }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }
function wc_get_chosen_shipping_method_for_package( $key, $package ) { return $GLOBALS['chosen_shipping']; }
function WC() { return $GLOBALS['wc']; }

require __DIR__ . '/../../woocommerce/includes/legacy/class-wc-legacy-cart.php';
class TestCart extends WC_Legacy_Cart {
	public function get_total( $context = 'view' ) { return apply_filters( 'woocommerce_cart_get_total', 1100.0 ); }
	public function get_totals() { return array( 'total' => 1100.0 ); }
	public function get_shipping_total() { return 90; }
	public function get_shipping_tax() { return 10; }
	public function get_shipping_packages() { return array(); }
}
class TestSession {
	private $data = array();
	public function get( $key, $default = null ) { return $this->data[ $key ] ?? $default; }
	public function set( $key, $value ) { $this->data[ $key ] = $value; }
}
class TestShipping {
	public function get_packages() { return array( array( 'rates' => array( 'flat_rate:1' => true ) ) ); }
}
class TestWoo {
	public $cart; public $session;
	public function __construct() { $this->cart = new TestCart(); $this->session = new TestSession(); }
	public function shipping() { return new TestShipping(); }
}
class TestShippingItem {
	public function get_method_id() { return 'flat_rate'; }
	public function get_instance_id() { return 1; }
}
class WC_Order {
	public $meta = array(); public $total = 1100; public $notes = array(); public $method = 'bkash'; public $status = 'pending';
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
	public function get_items( $type ) { return array( new TestShippingItem() ); }
	public function get_shipping_total() { return 90; }
	public function get_shipping_tax() { return 10; }
	public function get_total( $context = 'view' ) { return 'edit' === $context ? $this->total : apply_filters( 'woocommerce_order_get_total', $this->total, $this ); }
	public function set_total( $value ) { $this->total = $value; }
	public function get_payment_method() { return $this->method; }
	public function get_currency() { return 'BDT'; }
	public function get_id() { return 2; }
	public function get_status() { return $this->status; }
	public function get_total_refunded() { return $this->meta['test_refunds'] ?? 0; }
	public function get_total_tax() { return 40; }
	public function is_paid() { return in_array( $this->status, array( 'processing', 'completed' ), true ); }
	public function add_order_note( $note ) { $this->notes[] = $note; }
	public function save() {}
}
$wc = new TestWoo();
$chosen_shipping = 'flat_rate:1';
require __DIR__ . '/../adv-shipping-wpm.php';
$checks = 0;
function expect( $actual, $expected, $label ) {
	$GLOBALS['checks']++;
	if ( is_numeric( $actual ) && is_numeric( $expected ) ? abs( $actual - $expected ) > 0.00001 : $actual !== $expected ) {
		throw new RuntimeException( $label . ': expected ' . json_encode( $expected ) . ', got ' . json_encode( $actual ) );
	}
}
$options[ ASWPM_OPTION_KEY ] = array( 'flat_rate:1' );
expect( aswpm_get_checkout_position(), 'before_total', 'Summary defaults above order total' );
$options['aswpm_checkout_position'] = 'subtotal';
expect( aswpm_get_checkout_position(), 'before_total', 'Previous subtotal position adopts requested placement' );
$options['aswpm_checkout_position_updated'] = true;
expect( aswpm_get_checkout_position(), 'subtotal', 'Explicit admin position remains configurable' );
unset( $options['aswpm_checkout_position'], $options['aswpm_checkout_position_updated'] );
$options[ ASWPM_CUSTOM_ADVANCE_PRICE_KEY ] = 150;
$options['aswpm_advance_percentage'] = 20;
foreach ( array( 'shipping_method' => 100, 'custom_price' => 150, 'percentage' => 220, 'both' => 250, 'shipping_percentage' => 320, 'combined' => 470, 'full_payment' => 1100 ) as $mode => $amount ) {
	expect( aswpm_calculate_advance_amount( 100, 1100, $mode ), $amount, $mode );
}
$options['aswpm_percentage_base'] = 'excluding_shipping';
expect( aswpm_calculate_advance_amount( 100, 1100, 'shipping_percentage' ), 300, 'Percentage excluding shipping tax' );
$options['aswpm_advance_percentage'] = 200;
expect( aswpm_calculate_advance_amount( 100, 1100, 'combined' ), 1100, 'Combined capped at full total' );
$options[ ASWPM_CUSTOM_ADVANCE_PRICE_KEY ] = -10;
expect( aswpm_calculate_advance_amount( 100, 1100, 'custom_price' ), 0, 'Negative fixed amount' );
expect( aswpm_calculate_advance_amount( 100, 0, 'shipping_method' ), 0, 'Fully discounted order' );
$options['aswpm_advance_percentage'] = 12.345;
expect( aswpm_calculate_advance_amount( 0, 999.99, 'percentage' ), 123.45, 'Currency precision' );
$options['aswpm_advance_percentage'] = 20;
$options['aswpm_percentage_base'] = 'order_total';
$options[ ASWPM_CUSTOM_ADVANCE_PRICE_KEY ] = 150;
$options['aswpm_payment_modes'] = array( 'shipping_method', 'percentage' );
aswpm_update_checkout_choice( 'aswpm_payment_mode=percentage&advance_amount=1' );
expect( aswpm_get_chosen_mode(), 'percentage', 'Enabled choice saved' );
expect( aswpm_calculate_advance_amount( 100, 1100 ), 220, 'Ignore client amount' );
aswpm_update_checkout_choice( 'aswpm_payment_mode=combined' );
expect( aswpm_get_chosen_mode(), 'percentage', 'Disabled choice ignored' );
$_POST['aswpm_payment_mode'] = 'combined';
do_action( 'woocommerce_checkout_process' );
expect( count( $errors ), 1, 'Reject tampered final checkout' );
unset( $_POST['aswpm_payment_mode'] );
expect( WC()->cart->total, 1100, 'Legacy cart total outside gateway' );
do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php' );
expect( WC()->cart->total, 220, 'Actual WooCommerce legacy cart getter in gateway fields' );
expect( WC()->cart->get_total( 'edit' ), 220, 'Gateway numeric getter' );
expect( aswpm_get_cart_grand_total(), 1100, 'Full total retained while rendering' );
do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
expect( WC()->cart->total, 1100, 'Full cart total after payment fields' );
$gateways = array( 'cod' => true, 'bkash' => true, 'nagad' => true, 'stripe' => true );
expect( array_keys( aswpm_hide_cod_when_selected_shipping( $gateways ) ), array( 'bkash', 'nagad', 'stripe' ), 'All non-COD gateways' );
$options['aswpm_gateways'] = array( 'bkash', 'nagad' );
expect( array_keys( aswpm_hide_cod_when_selected_shipping( $gateways ) ), array( 'bkash', 'nagad' ), 'Multiple gateway sources' );
$options['aswpm_show_cod'] = 'yes';
expect( array_keys( aswpm_hide_cod_when_selected_shipping( $gateways ) ), array( 'cod', 'bkash', 'nagad' ), 'Optional COD remains alongside allowed advance gateways' );
$without_cod = $gateways; unset( $without_cod['cod'] );
expect( array_keys( aswpm_hide_cod_when_selected_shipping( $without_cod ) ), array( 'bkash', 'nagad' ), 'Do not force an unavailable COD gateway' );
aswpm_update_checkout_choice( 'payment_method=cod' );
expect( aswpm_is_cod_checkout_selected(), true, 'COD choice saved during checkout refresh' );
expect( aswpm_modify_cart_total_display( 'BDT 1,100.00' ), 'BDT 1,100.00', 'COD displays full checkout total' );
expect( aswpm_modify_order_review_heading( 'Total', 'Total', 'woocommerce' ), 'Total', 'COD retains normal Total label' );
$cod_order = new WC_Order(); $cod_order->method = 'cod';
aswpm_prepare_advance_order( $cod_order );
expect( $cod_order->get_total( 'edit' ), 1100, 'COD order stores full amount' );
expect( $cod_order->get_meta( '_aswpm_is_advance_order' ), '', 'COD order has no advance flag' );
aswpm_update_checkout_choice( 'payment_method=bkash' );
expect( aswpm_is_cod_checkout_selected(), false, 'Switching back to online restores advance flow' );
$options['aswpm_show_cod'] = 'no';
$order = new WC_Order(); $orders[1] = $order;
do_action( 'woocommerce_checkout_create_order', $order, array() );
expect( $order->get_total( 'edit' ), 220, 'Persist advance before gateway processing' );
expect( $order->get_meta( '_aswpm_original_total' ), 1100, 'Store full order value' );
expect( $order->get_meta( '_aswpm_due_amount' ), 880, 'Balance on delivery' );
$pay_page = true;
$options['aswpm_show_cod'] = 'yes';
expect( array_keys( aswpm_hide_cod_when_selected_shipping( $gateways ) ), array( 'bkash', 'nagad' ), 'Advance retry cannot switch unpaid balance to COD' );
$options['aswpm_show_cod'] = 'no';
do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php' );
expect( WC()->cart->total, 220, 'Manual gateway retry uses saved advance' );
do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
$pay_page = false;
$order->status = 'on-hold';
do_action( 'woocommerce_order_status_changed', 1, 'pending', 'on-hold', $order );
expect( $order->get_total(), 220, 'On-hold callback amount unchanged' );
expect( $order->get_meta( '_aswpm_advance_paid' ), 'no', 'On-hold is not paid' );
expect( count( $order->notes ), 0, 'No paid note before verification' );
$order->status = 'processing';
do_action( 'woocommerce_order_status_changed', 1, 'on-hold', 'processing', $order );
do_action( 'woocommerce_payment_complete', 1 );
expect( $order->get_meta( '_aswpm_advance_paid' ), 'yes', 'Payment confirmed' );
expect( count( $order->notes ), 1, 'Confirmation note is idempotent' );
expect( $order->get_total( 'edit' ), 220, 'Persisted amount stays stable after confirmation' );
$rows = apply_filters( 'woocommerce_get_order_item_totals', array( 'order_total' => array( 'label' => 'Total', 'value' => '220' ) ), $order );
expect( $rows['aswpm_due']['value'], 'BDT 880.00', 'Receipt includes delivery balance' );
$order->total = 1100; $order->method = 'cod';
aswpm_prepare_advance_order( $order );
expect( $order->get_meta( '_aswpm_is_advance_order' ), '', 'Remove flags when reusing order for COD' );
$wc->session->set( 'aswpm_payment_mode', 'shipping_method' );
expect( aswpm_calculate_due_amount( 1100, 100 ), 1000, 'Shipping balance includes full totals' );
$chosen_shipping = 'flat_rate:2';
expect( array_keys( aswpm_hide_cod_when_selected_shipping( $gateways ) ), array_keys( $gateways ), 'Unselected shipping unaffected' );
$chosen_shipping = 'flat_rate:1';

// Exercise the installed gateway implementations with their real private fee calculators.
if ( file_exists( __DIR__ . '/../../bangladeshi-payments-mobile/includes/class-bkash-gateway.php' ) ) {
	class WC_Payment_Gateway {}
	require __DIR__ . '/../../bangladeshi-payments-mobile/includes/class-bkash-gateway.php';
	require __DIR__ . '/../../bangladeshi-payments-mobile/includes/class-nagad-gateway.php';
	$wc->session->set( 'aswpm_payment_mode', 'percentage' );
	do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php' );
	foreach ( array( 'WC_Gateway_Bkash' => 'bkash', 'WC_Gateway_Nagad' => 'nagad' ) as $class => $id ) {
		$reflection = new ReflectionClass( $class );
		$gateway = $reflection->newInstanceWithoutConstructor();
		$gateway->{ 'apply_' . $id . '_charge' } = true;
		$gateway->{ $id . '_charge' } = 2;
		$method = $reflection->getMethod( 'calculate_total_payment' );
		$method->setAccessible( true );
		expect( $method->invoke( $gateway ), '224.40 BDT', $id . ' fee calculated on advance, not full cart' );
		$fees = $reflection->getMethod( 'calculate_' . $id . '_fees' );
		$fees->setAccessible( true );
		$fee_value = $fees->invoke( $gateway );
		expect( (float) $fee_value, 4.40, $id . ' advance fee amount' );
	}
	do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
}
$options['aswpm_payment_modes'] = array( 'custom_price' ); $options[ ASWPM_CUSTOM_ADVANCE_PRICE_KEY ] = 0;
expect( array_keys( aswpm_hide_cod_when_selected_shipping( $gateways ) ), array( 'cod' ), 'Zero advance uses COD only' );

// Due ledger and native Analytics must reconcile without changing gateway charges.
$options['aswpm_payment_modes'] = array( 'percentage' );
$wc->session->set( 'aswpm_payment_mode', 'percentage' );
$ledger_order = new WC_Order(); $orders[2] = $ledger_order;
aswpm_prepare_advance_order( $ledger_order );
function expect_rejected( $callback, $label ) {
	try { $callback(); } catch ( InvalidArgumentException $error ) { expect( true, true, $label ); return; }
	throw new RuntimeException( 'Expected rejection: ' . $label );
}
expect_rejected( function () use ( $ledger_order ) { aswpm_add_collection_entry( $ledger_order, 100, 'cash', '', 'collection-pending-0001' ); }, 'Unverified advance cannot receive delivery collections' );
aswpm_confirm_advance_payment( 2 );
expect( aswpm_add_collection_entry( $ledger_order, 300, 'courier', 'settlement-100', 'collection-request-0001' ), true, 'Partial collection accepted' );
expect( aswpm_get_due_collected( $ledger_order ), 300, 'Collected amount' );
expect( aswpm_get_outstanding_due( $ledger_order ), 580, 'Partial outstanding balance' );
expect( aswpm_add_collection_entry( $ledger_order, 300, 'courier', 'settlement-100', 'collection-request-0001' ), false, 'Duplicate request is idempotent' );
expect( count( aswpm_get_collections( $ledger_order ) ), 1, 'Duplicate does not add ledger entry' );
expect( $ledger_order->get_total(), 220, 'Collection does not increase gateway charge' );
expect_rejected( function () use ( $ledger_order ) { aswpm_add_collection_entry( $ledger_order, 581, 'cash', '', 'collection-overpay-0001' ); }, 'Overcollection rejected' );
expect_rejected( function () use ( $ledger_order ) { aswpm_add_collection_entry( $ledger_order, -10, 'cash', '', 'collection-negative-01' ); }, 'Negative collection rejected' );
expect_rejected( function () use ( $ledger_order ) { aswpm_add_collection_entry( $ledger_order, 100, 'invalid', '', 'collection-badmethod-01' ); }, 'Unknown collection method rejected' );
aswpm_add_collection_entry( $ledger_order, 580, 'cash', '', 'collection-request-0002' );
expect( aswpm_get_outstanding_due( $ledger_order ), 0, 'Full balance settled' );
aswpm_add_collection_entry( $ledger_order, 0, '', '', 'collection-reversal-01', 'collection-request-0001' );
expect( aswpm_get_outstanding_due( $ledger_order ), 300, 'Reversal restores exact outstanding amount' );
expect( count( aswpm_get_collections( $ledger_order ) ), 3, 'Reversal preserves collection history' );
expect_rejected( function () use ( $ledger_order ) { aswpm_add_collection_entry( $ledger_order, 0, '', '', 'collection-reversal-02', 'collection-request-0001' ); }, 'Cannot reverse twice' );
$ledger_order->status = 'completed';
do_action( 'woocommerce_order_status_changed', 2, 'processing', 'completed', $ledger_order );
expect( aswpm_get_outstanding_due( $ledger_order ), 300, 'Completed status does not collect balance' );
$ledger_order->meta['test_refunds'] = 10;
expect_rejected( function () use ( $ledger_order ) { aswpm_add_collection_entry( $ledger_order, 300, 'cash', '', 'collection-refund-test' ); }, 'Refunded order requires reconciliation' );
unset( $ledger_order->meta['test_refunds'] );
$stats = apply_filters( 'woocommerce_analytics_update_order_stats_data', array( 'total_sales' => 220, 'net_total' => 90, 'tax_total' => 40, 'shipping_total' => 90 ), $ledger_order );
expect( $stats['total_sales'], 1100, 'Analytics full order value' );
expect( $stats['net_total'], 970, 'Analytics net revenue uses full value less tax and shipping' );
expect( $stats['tax_total'], 40, 'Analytics tax unchanged' );
expect( $stats['shipping_total'], 90, 'Analytics shipping unchanged' );
aswpm_add_collection_entry( $ledger_order, 300, 'bank', 'receipt-2', 'collection-request-0003' );
expect( aswpm_get_outstanding_due( $ledger_order ), 0, 'Collect after corrected entry' );
$stats_after = aswpm_full_value_analytics( $stats, $ledger_order );
expect( $stats_after['total_sales'], 1100, 'Collections do not create duplicate sales' );
expect( $ledger_order->get_total( 'edit' ), 220, 'Stored gateway total unchanged after full settlement' );
$receipt = apply_filters( 'woocommerce_get_order_item_totals', array( 'order_total' => array( 'label' => '', 'value' => '' ) ), $ledger_order );
expect( $receipt['aswpm_due']['value'], 'BDT 0.00', 'Customer receipt updates remaining balance' );
expect( $receipt['aswpm_collected']['value'], 'BDT 880.00', 'Customer receipt includes collected balance' );
$ordinary = new WC_Order();
expect( aswpm_full_value_analytics( array( 'total_sales' => 1100 ), $ordinary ), array( 'total_sales' => 1100 ), 'Ordinary order analytics unaffected' );
$edited_order = new WC_Order();
aswpm_prepare_advance_order( $edited_order );
$edited_order->total = 1200;
do_action( 'woocommerce_order_after_calculate_totals', true, $edited_order );
expect( $edited_order->get_meta( '_aswpm_original_total' ), 1200, 'Admin recalculation updates full sale value' );
expect( aswpm_get_outstanding_due( $edited_order ), 980, 'Admin item edits update delivery balance' );
expect( $edited_order->get_total( 'edit' ), 220, 'Admin recalculation preserves agreed gateway amount' );

// AJAX security and lock lifecycle checks without touching a real database.
$wpdb = new TestDatabase();
$api_order = new WC_Order(); $orders[3] = $api_order;
aswpm_prepare_advance_order( $api_order ); $api_order->update_meta_data( '_aswpm_advance_paid', 'yes' );
$_POST = array( 'order_id' => 3, 'nonce' => 'test-nonce', 'operation' => 'record', 'amount' => 100, 'method' => 'cash', 'reference' => 'receipt-api', 'request_id' => 'collection-api-request1' );
function api_response() {
	try { aswpm_ajax_order_collection(); } catch ( TestJsonResponse $response ) { return $response; }
	throw new RuntimeException( 'AJAX did not respond' );
}
$can_edit = false;
expect( api_response()->success, false, 'Collection endpoint rejects unauthorized staff' );
expect( count( aswpm_get_collections( $api_order ) ), 0, 'Unauthorized request does not write' );
$can_edit = true; $_POST['nonce'] = 'bad';
expect( api_response()->success, false, 'Collection endpoint rejects invalid nonce' );
$_POST['nonce'] = 'test-nonce'; $wpdb->available = false;
expect( api_response()->success, false, 'Concurrent collection rejected while lock held' );
expect( count( aswpm_get_collections( $api_order ) ), 0, 'Lock rejection does not write' );
$wpdb->available = true;
expect( api_response()->success, true, 'Authorized AJAX collection succeeds' );
expect( aswpm_get_outstanding_due( $api_order ), 780, 'AJAX updates exact balance' );
expect( $wpdb->released, 1, 'Successful request releases database lock' );
expect( api_response()->success, true, 'AJAX retry safely succeeds' );
expect( count( aswpm_get_collections( $api_order ) ), 1, 'AJAX retry does not duplicate collection' );
$_POST['request_id'] = 'collection-api-request2'; $_POST['amount'] = 1000;
expect( api_response()->success, false, 'Endpoint rejects overcollection' );
expect( $wpdb->released, 3, 'Validation failure releases database lock' );
$_POST['amount'] = array( 100 );
expect( api_response()->success, false, 'Malformed request rejected before acquiring lock' );
$_POST = array();

// Style settings cannot inject declarations through bounded controls or escape style markup.
$design = aswpm_sanitize_design_settings( array( 'primary_color' => 'red;display:none', 'border_style' => 'solid;position:fixed', 'padding_top' => -20, 'margin_left' => 3000, 'border_width' => array( 5 ), 'custom_css' => '</style><script>alert(1)</script>' ) );
expect( $design['primary_color'], '#2563eb', 'Invalid design color rejected' );
expect( $design['border_style'], 'solid', 'Border declaration injection rejected' );
expect( $design['padding_top'], 0, 'Negative spacing bounded' );
expect( $design['margin_left'], 200, 'Excessive spacing bounded' );
expect( $design['border_width'], 1, 'Malformed design field rejected' );
expect( strpos( $design['custom_css'], '<' ), false, 'Custom CSS cannot close inline style tag' );
$options['aswpm_checkout_design'] = array( 'primary_color' => '#123456', 'padding_left' => 7.5, 'custom_css' => '#aswpm-notice-wrap .aswpm-payment-card { box-shadow: none; }' );
$css = aswpm_get_design_css();
expect( strpos( $css, '--aswpm-primary:#123456' ) !== false, true, 'Saved primary color applied' );
expect( strpos( $css, '--aswpm-padding:20px 20px 20px 7.5px' ) !== false, true, 'Four-sided saved spacing applied' );
expect( strpos( $css, 'box-shadow: none' ) > strpos( $css, '--aswpm-primary' ), true, 'Custom CSS appended after generated design' );
unset( $options['aswpm_checkout_design'] );

$options['aswpm_payment_modes'] = array( 'shipping_method', 'full_payment' );
aswpm_update_checkout_choice( 'aswpm_payment_mode=full_payment' );
expect( aswpm_get_chosen_mode(), 'full_payment', 'Full payment can be selected alongside partial options' );
$full_order = new WC_Order();
aswpm_prepare_advance_order( $full_order );
expect( $full_order->get_total( 'edit' ), 1100, 'Full payment gateway receives full order amount' );
expect( aswpm_get_outstanding_due( $full_order ), 0, 'Full payment leaves no delivery balance' );
expect( aswpm_get_advance_status_label( $full_order ), 'Payment awaiting confirmation', 'Full payment remains unconfirmed until verification' );
$options[ ASWPM_ADVANCE_COST_TYPE_KEY ] = 'full_payment';
$wc->session->set( 'aswpm_payment_mode', '' );
expect( aswpm_get_chosen_mode(), 'full_payment', 'Full payment supports default selection' );

// Gateway activation is independent of shipping, and does not discount other gateways.
$options[ ASWPM_OPTION_KEY ] = array();
$chosen_shipping = 'flat_rate:2';
$options['aswpm_payment_modes'] = array( 'percentage', 'full_payment' );
$options[ ASWPM_ADVANCE_COST_TYPE_KEY ] = 'percentage';
$options['aswpm_advance_percentage'] = 20;
$options['aswpm_percentage_base'] = 'order_total';
$options['aswpm_gateways'] = array( 'bkash', 'nagad' );
$wc->session->set( 'aswpm_payment_mode', 'percentage' );
aswpm_update_checkout_choice( 'payment_method=bkash' );
expect( aswpm_is_payment_plan_active(), false, 'Shipping rules still apply when gateway option is off' );
$options['aswpm_gateway_plans'] = 'yes';
expect( aswpm_is_payment_plan_active(), true, 'Allowed gateway activates plan with no selected shipping rules' );
expect( aswpm_modify_cart_total_display( 'BDT 1,100.00' ), 'BDT 220.00', 'Allowed gateway checkout amount' );
expect( strpos( aswpm_gateway_payable_title( 'bKash', 'bkash' ), 'BDT 220.00' ) !== false, true, 'Allowed gateway title shows payable amount' );
expect( aswpm_gateway_payable_title( 'Stripe', 'stripe' ), 'Stripe', 'Other gateway title unchanged' );
$notice = aswpm_get_notice_html();
expect( strpos( $notice, 'name="aswpm_payment_mode"' ) !== false, true, 'Checkout choices render without shipping condition' );
$gateway_order = new WC_Order();
aswpm_prepare_advance_order( $gateway_order );
expect( $gateway_order->get_total( 'edit' ), 220, 'Allowed gateway order captures correct plan amount' );
expect( $gateway_order->get_meta( '_aswpm_due_amount' ), 880, 'Gateway plan delivery balance' );
do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php', '', '', array( 'gateway' => (object) array( 'id' => 'stripe' ) ) );
expect( WC()->cart->total, 1100, 'Unallowed gateway fields receive full amount even if an allowed gateway is selected' );
do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
do_action( 'woocommerce_before_template_part', 'checkout/payment-method.php', '', '', array( 'gateway' => (object) array( 'id' => 'nagad' ) ) );
expect( WC()->cart->total, 220, 'Allowed gateway fields receive advance' );
do_action( 'woocommerce_after_template_part', 'checkout/payment-method.php' );
aswpm_update_checkout_choice( 'payment_method=stripe' );
expect( aswpm_is_payment_plan_active(), false, 'Switching to an unallowed gateway disables plan' );
expect( aswpm_modify_cart_total_display( 'BDT 1,100.00' ), 'BDT 1,100.00', 'Other gateway checkout displays full amount' );
$other_order = new WC_Order(); $other_order->method = 'stripe';
aswpm_prepare_advance_order( $other_order );
expect( $other_order->get_total( 'edit' ), 1100, 'Other gateway order remains full value' );
expect( $other_order->get_meta( '_aswpm_is_advance_order' ), '', 'Other gateway has no advance flag' );
expect( array_keys( aswpm_hide_cod_when_selected_shipping( $gateways ) ), array( 'bkash', 'nagad', 'stripe' ), 'Other online gateways remain available in gateway mode' );
$options['aswpm_gateways'] = array();
expect( aswpm_is_payment_plan_active(), true, 'Empty gateway selection supports all enabled online gateways' );
aswpm_update_checkout_choice( 'payment_method=cod' );
expect( aswpm_is_payment_plan_active(), false, 'COD never receives a partial gateway plan' );
$options['aswpm_payment_modes'] = array( 'custom_price' );
$options[ ASWPM_CUSTOM_ADVANCE_PRICE_KEY ] = 0;
$wc->session->set( 'aswpm_payment_mode', 'custom_price' );
expect( array_keys( aswpm_hide_cod_when_selected_shipping( $gateways ) ), array_keys( $gateways ), 'Zero gateway plan keeps alternatives available' );
class TestCheckoutErrors { public $items = array(); public function add( $code, $message ) { $this->items[ $code ] = $message; } }
$validation_errors = new TestCheckoutErrors();
aswpm_validate_gateway_plan( array( 'payment_method' => 'bkash' ), $validation_errors );
expect( isset( $validation_errors->items['aswpm_zero_advance'] ), true, 'Zero online advance cannot silently charge full amount' );
expect( aswpm_gateway_payable_title( 'bKash', 'bkash' ), 'bKash', 'Zero online advance does not advertise a payment amount' );
$options['aswpm_gateway_plans'] = 'no';
$options[ ASWPM_OPTION_KEY ] = array( 'flat_rate:1' );
$chosen_shipping = 'flat_rate:1';
expect( aswpm_is_payment_plan_active(), true, 'Turning option off restores original shipping trigger' );

if ( in_array( '--preview', $argv, true ) ) {
	$panel = aswpm_get_order_payment_panel( $api_order );
	$css = file_get_contents( __DIR__ . '/../assets/css/aswpm-order-payments.css' );
	file_put_contents( __DIR__ . '/payment-panel-preview.html', '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Order payment panel preview</title><style>body{margin:0;background:#f0f0f1;font:13px Arial;color:#1e293b}main{max-width:1150px;margin:40px auto;background:white;border:1px solid #c3c4c7;border-radius:8px}.button{border:1px solid #2271b1;background:white;color:#2271b1;border-radius:4px;padding:8px 12px;cursor:pointer}.button-primary{background:#2271b1;color:white}input,select{box-sizing:border-box;padding:8px;border:1px solid #8c8f94;border-radius:4px}.widefat{width:100%;border-collapse:collapse;border:1px solid #e2e8f0;text-align:left}.button-link{border:0;background:none;color:#2271b1;cursor:pointer}.description{color:#64748b}' . $css . '</style><main>' . $panel . '</main></html>' );
}

echo "Passed $checks regression checks.\n";
