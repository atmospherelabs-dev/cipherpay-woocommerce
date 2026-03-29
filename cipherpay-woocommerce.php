<?php
/**
 * Plugin Name: CipherPay for WooCommerce
 * Plugin URI: https://cipherpay.app
 * Description: Accept shielded Zcash (ZEC) payments via CipherPay — fully private, non-custodial.
 * Version: 1.0.0
 * Author: CipherPay
 * Author URI: https://cipherpay.app
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cipherpay-woocommerce
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 10.6
 */

defined('ABSPATH') || exit;

define('CIPHERPAY_WC_VERSION', '1.0.0');
define('CIPHERPAY_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));

add_action('plugins_loaded', 'cipherpay_wc_init');

function cipherpay_wc_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>' . esc_html(
                sprintf(
                    /* translators: %s: plugin name */
                    __('%s requires WooCommerce to be installed and active.', 'cipherpay-woocommerce'),
                    'CipherPay'
                )
            ) . '</p></div>';
        });
        return;
    }

    require_once CIPHERPAY_WC_PLUGIN_DIR . 'includes/class-wc-gateway-cipherpay.php';

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Gateway_CipherPay';
        return $gateways;
    });
}

add_action('rest_api_init', function () {
    register_rest_route('cipherpay/v1', '/webhook', [
        'methods'  => 'POST',
        'callback' => 'cipherpay_handle_webhook',
        'permission_callback' => '__return_true',
    ]);
});

function cipherpay_handle_webhook(WP_REST_Request $request) {
    $settings = get_option('woocommerce_cipherpay_settings', []);
    $webhook_secret = $settings['webhook_secret'] ?? '';

    if (empty($webhook_secret)) {
        return new WP_REST_Response(['error' => 'Webhook secret not configured'], 400);
    }

    $body = $request->get_body();
    $signature = $request->get_header('X-CipherPay-Signature');
    $timestamp = $request->get_header('X-CipherPay-Timestamp');

    if (empty($signature) || empty($timestamp)) {
        return new WP_REST_Response(['error' => 'Missing signature headers'], 401);
    }

    $signed_payload = $timestamp . '.' . $body;
    $expected = hash_hmac('sha256', $signed_payload, $webhook_secret);

    if (!hash_equals($expected, $signature)) {
        return new WP_REST_Response(['error' => 'Invalid signature'], 401);
    }

    $ts = strtotime($timestamp);
    if ($ts === false || abs(time() - $ts) > 300) {
        return new WP_REST_Response(['error' => 'Timestamp too old'], 401);
    }

    $data = json_decode($body, true);
    if (!$data || empty($data['invoice_id']) || empty($data['event'])) {
        return new WP_REST_Response(['error' => 'Invalid payload'], 400);
    }

    $invoice_id = sanitize_text_field($data['invoice_id']);
    $event = sanitize_text_field($data['event']);

    $orders = wc_get_orders([
        'meta_key'   => '_cipherpay_invoice_id',
        'meta_value' => $invoice_id,
        'limit'      => 1,
    ]);

    if (empty($orders)) {
        return new WP_REST_Response(['error' => 'Order not found'], 404);
    }

    $order = $orders[0];

    switch ($event) {
        case 'detected':
            $order->add_order_note(
                sprintf('CipherPay: Payment detected in mempool (txid: %s)',
                    sanitize_text_field($data['txid'] ?? 'unknown'))
            );
            $order->update_status('on-hold', 'CipherPay payment detected, awaiting confirmation.');
            break;

        case 'confirmed':
            $order->payment_complete(sanitize_text_field($data['txid'] ?? ''));
            $order->add_order_note('CipherPay: Payment confirmed on-chain.');
            break;

        case 'expired':
            if (!$order->is_paid()) {
                $order->update_status('cancelled', 'CipherPay: Payment expired.');
            }
            break;
    }

    return new WP_REST_Response(['status' => 'ok']);
}
