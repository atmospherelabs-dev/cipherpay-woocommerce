<?php
/**
 * CipherPay for WooCommerce — Uninstall
 *
 * Fired when the plugin is deleted from the WordPress admin.
 * Removes all plugin options from the database.
 *
 * @package CipherPay_WooCommerce
 * @license GPLv2 or later
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'woocommerce_cipherpay_settings' );
