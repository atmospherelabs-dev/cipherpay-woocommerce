<?php
defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_CipherPay_Blocks extends AbstractPaymentMethodType {

    protected $name = 'cipherpay';

    public function initialize() {
        $this->settings = get_option('woocommerce_cipherpay_settings', []);
    }

    public function is_active() {
        return !empty($this->settings['enabled']) && $this->settings['enabled'] === 'yes';
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'cipherpay-blocks',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/cipherpay-blocks.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'],
            CIPHERPAY_WC_VERSION,
            true
        );
        return ['cipherpay-blocks'];
    }

    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting('title', __('Pay with Zcash (ZEC)', 'cipherpay-for-woocommerce')),
            'description' => $this->get_setting('description', __('Pay privately with shielded Zcash (ZEC).', 'cipherpay-for-woocommerce')),
            'icon'        => plugin_dir_url(dirname(__FILE__)) . 'assets/zcash-icon.png',
            'supports'    => ['products'],
        ];
    }
}
