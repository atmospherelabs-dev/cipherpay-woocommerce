<?php
define('ABSPATH', __DIR__);
class WC_Payment_Gateway {}
class FixtureOrder {
    function get_items() { return [new class { function get_name() { return 'Fixture item'; } function get_quantity() { return 1; } }]; }
    function get_total() { return '12.50'; }
    function __call($name, $args) { throw new Exception('Unexpected buyer data access: ' . $name); }
}
function wc_get_order($id) { return new FixtureOrder(); }
function get_woocommerce_currency() { return 'USD'; }
function wp_json_encode($value) { return json_encode($value); }
function wp_remote_post($url, $options) {
    $payload = json_decode($options['body'], true);
    if (array_keys($payload) !== ['amount', 'currency', 'product_name']) throw new Exception('Unexpected transmitted fields');
    if ($payload['amount'] !== 12.5) throw new Exception('Amount changed');
    throw new RuntimeException('verified');
}
require __DIR__ . '/../includes/class-wc-gateway-cipherpay.php';
$reflection = new ReflectionClass('WC_Gateway_CipherPay');
$gateway = $reflection->newInstanceWithoutConstructor();
try { $gateway->process_payment(1); throw new Exception('Request not sent'); }
catch (RuntimeException $e) { if ($e->getMessage() !== 'verified') throw $e; }
echo "Gateway payload verified: no buyer identity or address fields\n";
