<?php
/**
 * Remove plugin data only when the store has opted in to uninstall cleanup.
 *
 * @package AdvShippingWPM
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean the current site's data without deleting WooCommerce orders or tables.
 *
 * @return void
 */
function aswpm_uninstall_site_data() {
	if ( 'yes' !== get_option( 'aswpm_delete_data_on_uninstall', 'no' ) ) {
		return;
	}

	global $wpdb;
	$aswpm_options = array(
		'aswpm_selected_shipping_methods',
		'aswpm_texts',
		'aswpm_advance_cost_type',
		'aswpm_custom_advance_price',
		'aswpm_payment_modes',
		'aswpm_payment_mode_labels',
		'aswpm_advance_percentage',
		'aswpm_percentage_base',
		'aswpm_checkout_position',
		'aswpm_checkout_position_updated',
		'aswpm_gateways',
		'aswpm_gateway_plans',
		'aswpm_show_cod',
		'aswpm_checkout_design',
		'aswpm_delete_data_on_uninstall',
	);

	// Escape underscores so similarly named metadata belonging to others cannot match.
	$aswpm_meta_pattern = $wpdb->esc_like( '_aswpm_' ) . '%';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Uninstall must also work when WooCommerce is inactive; WordPress has no HPOS metadata deletion API.
	$aswpm_meta_keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $aswpm_meta_pattern ) );
	foreach ( $aswpm_meta_keys as $aswpm_meta_key ) {
		delete_metadata( 'post', 0, $aswpm_meta_key, '', true );
	}

	$aswpm_hpos_table = $wpdb->prefix . 'wc_orders_meta';
	if ( $aswpm_hpos_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $aswpm_hpos_table ) ) ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier comes exclusively from WordPress's trusted database prefix and a fixed WooCommerce table suffix.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$aswpm_hpos_table}` WHERE meta_key LIKE %s", $aswpm_meta_pattern ) );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery

	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::invalidate_cache_group( 'orders' );
	}
	if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) ) {
		$aswpm_store = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class );
		if ( is_callable( array( $aswpm_store, 'clear_all_cached_data' ) ) ) {
			$aswpm_store->clear_all_cached_data();
		}
	}

	foreach ( $aswpm_options as $aswpm_option ) {
		delete_option( $aswpm_option );
	}
}

if ( is_multisite() ) {
	$aswpm_offset = 0;
	do {
		$aswpm_site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $aswpm_offset,
			)
		);
		foreach ( $aswpm_site_ids as $aswpm_site_id ) {
			switch_to_blog( $aswpm_site_id );
			aswpm_uninstall_site_data();
			restore_current_blog();
		}
		$aswpm_offset    += 100;
		$aswpm_site_count = count( $aswpm_site_ids );
	} while ( 100 === $aswpm_site_count );
} else {
	aswpm_uninstall_site_data();
}
