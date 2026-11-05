<?php
define('ABSPATH', __DIR__);
class WC_Payment_Gateway { function get_option($key, $default = '') { return $default; } }
class FixtureOrder {
    function get_items() { return [new class { function get_name() { return 'Fixture item'; } function get_quantity() { return 1; } }]; }
    function get_total() { return '12.50'; }
    function update_meta_data($key, $value) {}
    function save() {}
    function update_status($status, $note = '') {}
    function get_checkout_order_received_url() { return 'https://store.test/checkout/order-received/1/'; }
    function __call($name, $args) { throw new Exception('Unexpected buyer data access: ' . $name); }
}
$GLOBALS['fixture_body'] = [];
function wc_get_order($id) { return new FixtureOrder(); }
function get_woocommerce_currency() { return 'USD'; }
function wp_json_encode($value) { return json_encode($value); }
function wp_remote_post($url, $options) { return ['body' => json_encode($GLOBALS['fixture_body'])]; }
function is_wp_error($value) { return false; }
function wp_remote_retrieve_response_code($response) { return 201; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function sanitize_text_field($value) { return $value; }
function esc_url_raw($value) { return $value; }
function esc_html($value) { return $value; }
function __($text, $domain = '') { return $text; }
require __DIR__ . '/../includes/class-wc-gateway-cipherpay.php';
$gateway = (new ReflectionClass('WC_Gateway_CipherPay'))->newInstanceWithoutConstructor();

function redirect_for($token) {
    global $gateway;
    $GLOBALS['fixture_body'] = ['invoice_id' => 'inv_1', 'memo_code' => 'CP-1', 'price_zec' => 0.25] + ($token === null ? [] : ['checkout_token' => $token]);
    $result = $gateway->process_payment(1);
    if ($result['result'] !== 'success') throw new Exception('Payment did not succeed');
    return $result['redirect'];
}

$base = 'https://cipherpay.app/pay/inv_1?return_url=' . urlencode('https://store.test/checkout/order-received/1/');
$token = str_repeat('ab', 32);
if (redirect_for($token) !== $base . '#checkout=' . $token) throw new Exception('Valid checkout token not passed');
foreach ([null, strtoupper($token), substr($token, 1), $token . '&x=1', ['array']] as $invalid) {
    if (redirect_for($invalid) !== $base) throw new Exception('Invalid checkout token passed: ' . json_encode($invalid));
}
echo "Checkout token verified: valid tokens passed in the fragment, others dropped\n";
