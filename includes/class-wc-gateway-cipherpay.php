<?php
defined('ABSPATH') || exit;

class WC_Gateway_CipherPay extends WC_Payment_Gateway {

    private $api_key;
    private $api_url;
    private $webhook_secret;

    public function __construct() {
        $this->id                 = 'cipherpay';
        $this->method_title       = 'CipherPay (Zcash)';
        $this->method_description = 'Accept shielded Zcash (ZEC) payments. Fully private, non-custodial.';
        $this->has_fields         = false;
        $this->icon               = '';

        $this->init_form_fields();
        $this->init_settings();

        $this->title          = $this->get_option('title', 'Pay with Zcash (ZEC)');
        $this->description    = $this->get_option('description', 'Private payment powered by CipherPay. Shielded ZEC only.');
        $this->api_key        = $this->get_option('api_key');
        $this->api_url        = rtrim($this->get_option('api_url', 'https://api.cipherpay.app'), '/');
        $this->webhook_secret = $this->get_option('webhook_secret');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $webhook_url = rest_url('cipherpay/v1/webhook');

        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable CipherPay payments',
                'default' => 'no',
            ],
            'title' => [
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'Payment method title shown at checkout.',
                'default'     => 'Pay with Zcash (ZEC)',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Payment method description shown at checkout.',
                'default'     => 'Private payment powered by CipherPay. Shielded ZEC only.',
                'desc_tip'    => true,
            ],
            'api_key' => [
                'title'       => 'API Key',
                'type'        => 'password',
                'description' => 'Your CipherPay API key (cpay_sk_...).',
                'default'     => '',
            ],
            'api_url' => [
                'title'       => 'API URL',
                'type'        => 'text',
                'description' => 'CipherPay API endpoint. Default: https://api.cipherpay.app',
                'default'     => 'https://api.cipherpay.app',
            ],
            'webhook_secret' => [
                'title'       => 'Webhook Secret',
                'type'        => 'password',
                'description' => sprintf(
                    'Your CipherPay webhook secret (whsec_...). Set this webhook URL in your CipherPay dashboard: <code>%s</code>',
                    esc_html($webhook_url)
                ),
                'default'     => '',
            ],
            'checkout_url' => [
                'title'       => 'Checkout Page URL',
                'type'        => 'text',
                'description' => 'Base URL of the CipherPay hosted checkout page. Default: https://cipherpay.app',
                'default'     => 'https://cipherpay.app',
            ],
        ];
    }

    public function payment_fields() {
        if ($this->description) {
            echo '<p>' . wp_kses_post($this->description) . '</p>';
        }
        echo '<p style="font-size: 12px; color: #666;">You will be redirected to a secure CipherPay checkout page to complete your payment with shielded ZEC.</p>';
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice('Order not found.', 'error');
            return ['result' => 'failure'];
        }

        $items_summary = [];
        foreach ($order->get_items() as $item) {
            $items_summary[] = $item->get_name() . ' x' . $item->get_quantity();
        }

        $payload = [
            'price_eur'        => floatval($order->get_total()),
            'product_name'     => implode(', ', $items_summary),
            'shipping_alias'   => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'shipping_address' => $this->format_shipping_address($order),
            'shipping_region'  => $order->get_shipping_country() ?: $order->get_billing_country(),
        ];

        $response = wp_remote_post($this->api_url . '/api/invoices', [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            wc_add_notice('CipherPay error: ' . $response->get_error_message(), 'error');
            return ['result' => 'failure'];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300 || empty($body['invoice_id'])) {
            $error_msg = $body['error'] ?? 'Failed to create CipherPay invoice';
            wc_add_notice('CipherPay: ' . $error_msg, 'error');
            return ['result' => 'failure'];
        }

        $order->update_meta_data('_cipherpay_invoice_id', sanitize_text_field($body['invoice_id']));
        $order->update_meta_data('_cipherpay_memo_code', sanitize_text_field($body['memo_code']));
        $order->update_meta_data('_cipherpay_price_zec', floatval($body['price_zec']));
        $order->save();

        $order->update_status('pending', sprintf(
            'CipherPay invoice created: %s (memo: %s)',
            $body['invoice_id'],
            $body['memo_code']
        ));

        $checkout_base = rtrim($this->get_option('checkout_url', 'https://cipherpay.app'), '/');
        $redirect_url = $checkout_base . '/pay/' . $body['invoice_id'];

        return [
            'result'   => 'success',
            'redirect' => $redirect_url,
        ];
    }

    private function format_shipping_address($order) {
        $parts = array_filter([
            $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
            $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
            $order->get_shipping_city() ?: $order->get_billing_city(),
            $order->get_shipping_state() ?: $order->get_billing_state(),
            $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
            WC()->countries->countries[$order->get_shipping_country() ?: $order->get_billing_country()] ?? '',
        ]);

        return implode(', ', $parts);
    }
}
