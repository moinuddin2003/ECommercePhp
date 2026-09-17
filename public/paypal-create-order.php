<?php
/**
 * public/paypal-create-order.php
 * ------------------------------------------------
 * Called by the PayPal JS SDK's createOrder() callback (see checkout.php).
 * We calculate the total ourselves from the session cart — NEVER trust
 * an amount sent from the browser, or a shopper could edit it in devtools.
 *
 * Returns JSON: { "id": "PAYPAL_ORDER_ID" } on success.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paypal.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Cart.php';

Session::start();

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be logged in to check out.']);
    exit;
}

$db = new Database($conn);
$cartItems = Cart::getItems($db);

if (empty($cartItems)) {
    http_response_code(400);
    echo json_encode(['error' => 'Your cart is empty.']);
    exit;
}

$total = Cart::getTotal($cartItems);

$accessToken = paypalGetAccessToken();
if (!$accessToken) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not connect to PayPal. Check your sandbox credentials in config/paypal.php.']);
    exit;
}

[$statusCode, $body] = paypalApiRequest('POST', '/v2/checkout/orders', $accessToken, [
    'intent' => 'CAPTURE',
    'purchase_units' => [
        [
            'amount' => [
                'currency_code' => 'USD',
                'value' => number_format($total, 2, '.', ''),
            ],
        ],
    ],
]);

if ($statusCode !== 201 || empty($body['id'])) {
    http_response_code(500);
    echo json_encode(['error' => 'PayPal order creation failed.']);
    exit;
}

echo json_encode(['id' => $body['id']]);