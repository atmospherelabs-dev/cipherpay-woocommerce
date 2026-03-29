=== CipherPay for WooCommerce ===
Contributors: cipherpay
Tags: zcash, payment, privacy, crypto, woocommerce
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept shielded Zcash (ZEC) payments on your WooCommerce store via CipherPay.

== Description ==

CipherPay for WooCommerce enables your store to accept fully shielded Zcash payments. Every transaction is private — no transparent addresses, no leaked metadata.

**Features:**

* Non-custodial — payments go directly to your shielded address
* Real-time payment detection via mempool scanning
* Automatic order status updates via webhooks
* Hosted checkout page — no sensitive data touches your server
* HMAC webhook verification for security

**How it works:**

1. Customer selects "Pay with Zcash" at checkout
2. Plugin creates a CipherPay invoice via API
3. Customer is redirected to the CipherPay hosted checkout page
4. Customer scans the QR code and pays with their Zcash wallet
5. CipherPay detects and confirms the payment
6. Webhook fires and the WooCommerce order is marked as paid

**External Service**

This plugin connects to the [CipherPay](https://cipherpay.app) API to create payment invoices
and receive webhook notifications when payments are confirmed. The following data is sent to
CipherPay when a customer checks out:

* Order total and currency
* Customer shipping name and address (used to display on the invoice)

No data is sent unless a customer actively initiates a Zcash payment.

* CipherPay website: [https://cipherpay.app](https://cipherpay.app)
* Terms of Service: [https://cipherpay.app/terms](https://cipherpay.app/terms)
* Privacy Policy: [https://cipherpay.app/privacy](https://cipherpay.app/privacy)

If you are self-hosting CipherPay, data is sent to your own instance instead.

== Installation ==

1. Upload the `cipherpay-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress
3. Go to WooCommerce > Settings > Payments > CipherPay
4. Enter your CipherPay API Key and Webhook Secret
5. Set the Webhook URL in your CipherPay dashboard to: `https://your-store.com/wp-json/cipherpay/v1/webhook`

== Configuration ==

**API Key:** Your CipherPay server-side API key (`cpay_sk_...`). Get it from the CipherPay dashboard.

**API URL:** The CipherPay API endpoint. Use `https://api.cipherpay.app` for production or your self-hosted instance URL.

**Webhook Secret:** Your CipherPay webhook secret (`whsec_...`). Used to verify webhook signatures.

**Checkout Page URL:** The base URL for the CipherPay hosted checkout (default: `https://cipherpay.app`).

== Frequently Asked Questions ==

= Is this custodial? =
No. CipherPay never holds your funds. Payments go directly to your shielded Zcash address.

= Does it work with self-hosted CipherPay? =
Yes. Change the API URL and Checkout Page URL to point to your own CipherPay instance.

= What currencies are supported? =
Invoices are denominated in your store's configured currency and automatically converted to ZEC at the current market rate.

== Changelog ==

= 1.0.0 =
* Initial release
* WooCommerce payment gateway with hosted checkout
* Webhook integration with HMAC verification
* Automatic order status management
