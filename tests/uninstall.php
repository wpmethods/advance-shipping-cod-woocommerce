<?php
/** Standalone uninstall checks. No database connection or writes. */
if ( 'cli' !== PHP_SAPI ) { exit; }
define( 'WP_UNINSTALL_PLUGIN', 'adv-shipping-wpm.php' );
$site = 1;
$options = array( 1 => array( 'aswpm_texts' => 'keep' ), 2 => array( 'aswpm_delete_data_on_uninstall' => 'yes', 'aswpm_texts' => 'remove', 'other_plugin_option' => 'keep' ) );
$deleted = array();
$queries = array();
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $GLOBALS['site'] ][ $key ] ?? $default; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $GLOBALS['site'] ][ $key ] ); $GLOBALS['deleted'][] = $key; }
function is_multisite() { return true; }
function get_sites( $args ) { return $args['offset'] ? array() : array( 1, 2 ); }
function switch_to_blog( $id ) { $GLOBALS['site'] = $id; $GLOBALS['wpdb']->prefix = 'wp_' . $id . '_'; $GLOBALS['wpdb']->postmeta = 'wp_' . $id . '_postmeta'; }
function restore_current_blog() { $GLOBALS['site'] = 1; }
function delete_metadata( $type, $id, $key, $value, $all ) {
    if ( 'post' !== $type || 0 !== $id || ! $all || 0 !== strpos( $key, '_aswpm_' ) ) { throw new RuntimeException( 'Unsafe metadata deletion.' ); }
    $GLOBALS['deleted'][] = $key;
}
class UninstallDatabase {
    public $prefix = 'wp_'; public $postmeta = 'wp_postmeta'; public $hpos_exists = true;
    public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
    public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
    public function get_col( $query ) {
        if ( '\\_aswpm\\_%' !== $query[1][0] ) { throw new RuntimeException( 'Metadata prefix is not escaped.' ); }
        $GLOBALS['queries'][] = $query;
        return array( '_aswpm_advance_amount', '_aswpm_collections' );
    }
    public function get_var( $query ) { return $this->hpos_exists ? $this->prefix . 'wc_orders_meta' : null; }
    public function query( $query ) {
        if ( false === strpos( $query[0], 'DELETE FROM `' . $this->prefix . 'wc_orders_meta` WHERE meta_key LIKE %s' ) || '\\_aswpm\\_%' !== $query[1][0] ) { throw new RuntimeException( 'Unscoped HPOS cleanup.' ); }
        $GLOBALS['queries'][] = $query;
    }
}
$wpdb = new UninstallDatabase();
require __DIR__ . '/../uninstall.php';
if ( 1 !== $site || 'keep' !== $options[1]['aswpm_texts'] || isset( $options[2]['aswpm_texts'] ) || isset( $options[2]['aswpm_delete_data_on_uninstall'] ) || 'keep' !== $options[2]['other_plugin_option'] || 2 !== count( $queries ) ) { throw new RuntimeException( 'Per-site uninstall opt-in failed.' ); }
$deleted = $queries = array();
aswpm_uninstall_site_data();
if ( $deleted || $queries ) { throw new RuntimeException( 'Default uninstall must retain data.' ); }
$options[1]['aswpm_delete_data_on_uninstall'] = 'yes';
$wpdb->hpos_exists = false;
aswpm_uninstall_site_data();
if ( 1 !== count( $queries ) ) { throw new RuntimeException( 'Missing HPOS table must not be queried for deletion.' ); }
echo "Passed uninstall checks: default retention, per-site opt-in, scoped legacy/HPOS cleanup, missing HPOS table, unrelated settings retained.\n";
