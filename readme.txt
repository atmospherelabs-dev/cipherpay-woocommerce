=== CipherPay for WooCommerce ===
Contributors: cipherpay
Tags: zcash, zec, payment, privacy, crypto, shielded, woocommerce
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT

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
Invoices are denominated in EUR and automatically converted to ZEC at the current market rate.

== Changelog ==

= 1.0.0 =
* Initial release
* WooCommerce payment gateway with hosted checkout
* Webhook integration with HMAC verification
* Automatic order status management
