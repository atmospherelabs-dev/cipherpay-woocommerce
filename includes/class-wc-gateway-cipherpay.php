<?php
defined('ABSPATH') || exit;

class WC_Gateway_CipherPay extends WC_Payment_Gateway {

    private $api_key;
    private $api_url;
    private $webhook_secret;

    public function __construct() {
        $this->id                 = 'cipherpay';
        $this->method_title       = __('CipherPay (Zcash)', 'cipherpay-for-woocommerce');
        $this->method_description = __('Accept shielded Zcash (ZEC) payments. Fully private, non-custodial.', 'cipherpay-for-woocommerce');
        $this->has_fields         = false;
        $this->icon               = plugin_dir_url(dirname(__FILE__)) . 'assets/zcash-icon.png';

        $this->init_form_fields();
        $this->init_settings();

        $this->title          = $this->get_option('title', __('Pay with Zcash (ZEC)', 'cipherpay-for-woocommerce'));
        $this->description    = $this->get_option('description', __('Private payment powered by CipherPay. Shielded ZEC only.', 'cipherpay-for-woocommerce'));
        $this->api_key        = $this->get_option('api_key');
        $this->api_url        = rtrim($this->get_option('api_url', 'https://api.cipherpay.app'), '/');
        $this->webhook_secret = $this->get_option('webhook_secret');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $webhook_url = rest_url('cipherpay/v1/webhook');

        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'cipherpay-for-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable CipherPay payments', 'cipherpay-for-woocommerce'),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __('Title', 'cipherpay-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Payment method title shown at checkout.', 'cipherpay-for-woocommerce'),
                'default'     => __('Pay with Zcash (ZEC)', 'cipherpay-for-woocommerce'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Description', 'cipherpay-for-woocommerce'),
                'type'        => 'textarea',
                'description' => __('Payment method description shown at checkout.', 'cipherpay-for-woocommerce'),
                'default'     => __('Private payment powered by CipherPay. Shielded ZEC only.', 'cipherpay-for-woocommerce'),
                'desc_tip'    => true,
            ],
            'api_key' => [
                'title'       => __('API Key', 'cipherpay-for-woocommerce'),
                'type'        => 'password',
                'description' => __('Your CipherPay API key (cpay_sk_...).', 'cipherpay-for-woocommerce'),
                'default'     => '',
            ],
            'api_url' => [
                'title'       => __('API URL', 'cipherpay-for-woocommerce'),
                'type'        => 'text',
                'description' => __('CipherPay API endpoint. Default: https://api.cipherpay.app', 'cipherpay-for-woocommerce'),
                'default'     => 'https://api.cipherpay.app',
            ],
            'webhook_secret' => [
                'title'       => __('Webhook Secret', 'cipherpay-for-woocommerce'),
                'type'        => 'password',
                'description' => sprintf(
                    /* translators: %s: webhook URL */
                    __('Your CipherPay webhook secret (whsec_...). Set this webhook URL in your CipherPay dashboard: %s', 'cipherpay-for-woocommerce'),
                    '<code>' . esc_html($webhook_url) . '</code>'
                ),
                'default'     => '',
            ],
            'checkout_url' => [
                'title'       => __('Checkout Page URL', 'cipherpay-for-woocommerce'),
                'type'        => 'text',
                'description' => __('Base URL of the CipherPay hosted checkout page. Default: https://cipherpay.app', 'cipherpay-for-woocommerce'),
                'default'     => 'https://cipherpay.app',
            ],
        ];
    }

    public function payment_fields() {
        if ($this->description) {
            echo '<p>' . wp_kses_post($this->description) . '</p>';
        }
        echo '<p style="font-size: 12px; color: #666;">' . esc_html__('You will be redirected to a secure CipherPay checkout page to complete your payment with shielded ZEC.', 'cipherpay-for-woocommerce') . '</p>';
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Order not found.', 'cipherpay-for-woocommerce'), 'error');
            return ['result' => 'failure'];
        }

        $items_summary = [];
        foreach ($order->get_items() as $item) {
            $items_summary[] = $item->get_name() . ' x' . $item->get_quantity();
        }

        $store_currency = get_woocommerce_currency();

        $payload = [
            'amount'           => floatval($order->get_total()),
            'currency'         => $store_currency,
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
            wc_add_notice(
                /* translators: %s: error message */
                sprintf(__('CipherPay error: %s', 'cipherpay-for-woocommerce'), esc_html($response->get_error_message())),
                'error'
            );
            return ['result' => 'failure'];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300 || empty($body['invoice_id'])) {
            $error_msg = $body['error'] ?? __('Failed to create CipherPay invoice', 'cipherpay-for-woocommerce');
            wc_add_notice(
                /* translators: %s: error message */
                sprintf(__('CipherPay: %s', 'cipherpay-for-woocommerce'), esc_html($error_msg)),
                'error'
            );
            return ['result' => 'failure'];
        }

        $invoice_id = sanitize_text_field($body['invoice_id']);
        $memo_code = sanitize_text_field($body['memo_code'] ?? '');

        $order->update_meta_data('_cipherpay_invoice_id', $invoice_id);
        $order->update_meta_data('_cipherpay_memo_code', $memo_code);
        $order->update_meta_data('_cipherpay_price_zec', floatval($body['price_zec']));
        $order->save();

        $order->update_status('pending', sprintf(
            /* translators: 1: invoice ID, 2: memo code */
            __('CipherPay invoice created: %1$s (memo: %2$s)', 'cipherpay-for-woocommerce'),
            esc_html($invoice_id),
            esc_html($memo_code)
        ));

        $checkout_base = rtrim(esc_url_raw($this->get_option('checkout_url', 'https://cipherpay.app')), '/');
        $return_url = urlencode($order->get_checkout_order_received_url());
        $redirect_url = $checkout_base . '/pay/' . urlencode($invoice_id) . '?return_url=' . $return_url;

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
