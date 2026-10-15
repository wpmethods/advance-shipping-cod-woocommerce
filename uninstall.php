<?php
/**
 * Remove plugin settings while preserving order bookkeeping records.
 *
 * @package AdvShippingWPM
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$advshipping_wpm_options = array(
	'advshipping_wpm_selected_shipping_methods',
	'advshipping_wpm_texts',
	'advshipping_wpm_advance_cost_type',
	'advshipping_wpm_custom_advance_price',
	'advshipping_wpm_payment_modes',
	'advshipping_wpm_payment_mode_labels',
	'advshipping_wpm_advance_percentage',
	'advshipping_wpm_percentage_base',
	'advshipping_wpm_checkout_position',
	'advshipping_wpm_checkout_position_updated',
	'advshipping_wpm_gateways',
	'advshipping_wpm_gateway_plans',
	'advshipping_wpm_show_cod',
	'advshipping_wpm_checkout_design',
);

foreach ( $advshipping_wpm_options as $advshipping_wpm_option ) {
	delete_option( $advshipping_wpm_option );
}

// Retain order payment metadata and collection audit history for store accounting.
