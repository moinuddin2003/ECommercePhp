<?php
/**
 * public/paypal-capture-order.php
 * ------------------------------------------------
 * Called by the PayPal JS SDK's onApprove() callback (see checkout.php)
 * once the shopper approves the payment on PayPal's side.
 *
 * We capture the payment server-side, and ONLY IF that succeeds do we
 * write the order into our own database — this is what prevents an
 * order existing without a matching real payment.
 *
 * Expects JSON POST body: { "orderID": "...", "shipping_address": "..." }
 * Returns JSON: { "redirect": "order-confirmation.php?order=..." } on success.
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

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$paypalOrderId = $input['orderID'] ?? '';
$shippingAddress = trim($input['shipping_address'] ?? '');

if ($paypalOrderId === '' || $shippingAddress === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order ID or shipping address.']);
    exit;
}

$db = new Database($conn);
$cartItems = Cart::getItems($db);

if (empty($cartItems)) {
    http_response_code(400);
    echo json_encode(['error' => 'Your cart is empty.']);
    exit;
}

// Re-check stock right before charging — it may have changed since the cart was loaded
foreach ($cartItems as $item) {
    $fresh = $db->fetchOne('SELECT stock FROM products WHERE id = ?', [$item['id']], 'i');
    if (!$fresh || (int) $fresh['stock'] < $item['quantity']) {
        http_response_code(400);
        echo json_encode(['error' => 'Not enough stock for "' . $item['name'] . '". Please update your cart.']);
        exit;
    }
}

$accessToken = paypalGetAccessToken();
if (!$accessToken) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not connect to PayPal.']);
    exit;
}

// Capture the payment
[$statusCode, $body] = paypalApiRequest('POST', "/v2/checkout/orders/{$paypalOrderId}/capture", $accessToken);

if ($statusCode !== 201 && $statusCode !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'PayPal payment capture failed.']);
    exit;
}

$captureStatus = $body['status'] ?? '';
$captureId = $body['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

if ($captureStatus !== 'COMPLETED' || !$captureId) {
    http_response_code(500);
    echo json_encode(['error' => 'Payment was not completed.']);
    exit;
}

// Payment succeeded — now write the order into our own database
$cartTotal = Cart::getTotal($cartItems);

mysqli_begin_transaction($conn);

try {
    $orderNumber = 'ORD-' . strtoupper(bin2hex(random_bytes(5)));

    $orderId = $db->insert(
        'INSERT INTO orders (user_id, order_number, total_amount, payment_method, payment_status, order_status, transaction_id, shipping_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            Session::get('user_id'),
            $orderNumber,
            $cartTotal,
            'paypal',
            'completed',
            'processing',
            $captureId,
            $shippingAddress,
        ],
        'isdsssss'
    );

    foreach ($cartItems as $item) {
        $db->insert(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)',
            [$orderId, $item['id'], $item['quantity'], $item['price'], $item['subtotal']],
            'iiidd'
        );

        $db->execute(
            'UPDATE products SET stock = stock - ? WHERE id = ?',
            [$item['quantity'], $item['id']],
            'ii'
        );
    }

    mysqli_commit($conn);

    Cart::clearCart();

    echo json_encode(['redirect' => 'order-confirmation.php?order=' . urlencode($orderNumber)]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    // NOTE: at this point PayPal HAS charged the customer but our DB write failed.
    // In production you'd log $captureId here and alert yourself to refund/reconcile manually.
    http_response_code(500);
    echo json_encode(['error' => 'Payment succeeded but saving your order failed. Please contact support with this reference: ' . $captureId]);
}