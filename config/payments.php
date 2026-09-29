<?php
require_once __DIR__ . '/../core/Mailer.php';

function stripeSetting($name)
{
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }

    static $settings;
    if ($settings === null) {
        $settings = [];
        $envFile = dirname(__DIR__) . '/.env';
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
                if (preg_match('/^\s*([^#:=]+?)\s*[:=]\s*(.*?)\s*$/', $line, $matches)) {
                    $key = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', trim($matches[1])));
                    $settings[$key] = trim($matches[2], " \t\n\r\0\x0B\"'");
                }
            }
        }

        $settings['STRIPE_SECRET_KEY'] = $settings['STRIPE_SECRET_KEY'] ?? $settings['SECRET_KEY'] ?? null;
        $settings['STRIPE_PUBLISHABLE_KEY'] = $settings['STRIPE_PUBLISHABLE_KEY'] ?? $settings['PUBLISHABLE_KEY'] ?? null;
    }

    return $settings[$name] ?? null;
}

function isSupportedPaymentMethod($paymentMethod)
{
    return in_array($paymentMethod, ['cod', 'stripe'], true);
}

function paymentMethodLabel($paymentMethod)
{
    $labels = [
        'cod' => 'Cash on Delivery',
        'stripe' => 'Stripe',
    ];

    return $labels[$paymentMethod] ?? 'Unknown';
}

function paymentStatusLabel($paymentMethod, $paymentStatus, $orderStatus = null)
{
    if ($orderStatus === 'cancelled' && $paymentStatus !== 'completed') {
        return 'No payment due';
    }
    if ($paymentMethod === 'cod' && $paymentStatus === 'pending') {
        return $orderStatus === 'delivered' ? 'Confirm cash collection' : 'Collect on delivery';
    }
    if ($paymentMethod === 'stripe' && $paymentStatus === 'pending') {
        return 'Awaiting payment';
    }

    $labels = [
        'completed' => 'Paid',
        'failed' => 'Failed',
        'pending' => 'Pending',
    ];

    return $labels[$paymentStatus] ?? 'Unknown';
}

function canSetOrderStatus($paymentMethod, $paymentStatus, $orderStatus, $currentStatus)
{
    if (!in_array($orderStatus, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'], true)) {
        return false;
    }

    if ($orderStatus === $currentStatus) {
        return true;
    }

    $transitions = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];
    if (!in_array($orderStatus, $transitions[$currentStatus] ?? [], true)) {
        return false;
    }

    if ($orderStatus === 'cancelled' && $paymentStatus === 'completed') {
        return false;
    }

    return $paymentMethod !== 'stripe'
        || $paymentStatus === 'completed'
        || $orderStatus === 'cancelled';
}

function isStripeSandboxConfigured()
{
    return str_starts_with((string) stripeSetting('STRIPE_SECRET_KEY'), 'sk_test_');
}

function stripeReturnBaseUrl()
{
    $configuredUrl = rtrim((string) stripeSetting('APP_BASE_URL'), '/');
    if ($configuredUrl !== '') {
        $parts = parse_url($configuredUrl);
        if (!$parts || empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            throw new RuntimeException('APP_BASE_URL must be an absolute HTTP or HTTPS URL.');
        }
        return $configuredUrl;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!preg_match('/\A[a-z0-9.-]+(?::[0-9]{1,5})?\z/i', $host)) {
        throw new RuntimeException('Set APP_BASE_URL in .env to your local site URL.');
    }

    $https = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
    return ($https ? 'https://' : 'http://') . $host;
}

function stripeApiRequest($method, $path, $parameters = [], $idempotencyKey = null)
{
    $secretKey = (string) stripeSetting('STRIPE_SECRET_KEY');
    if (!str_starts_with($secretKey, 'sk_test_')) {
        throw new RuntimeException('Configure a Stripe sandbox secret key in .env.');
    }

    $curl = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/'));
    $headers = ['Accept: application/json'];
    if ($idempotencyKey !== null) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ];
    $caBundle = ini_get('curl.cainfo');
    if (!$caBundle || !is_readable($caBundle)) {
        $caBundle = dirname(__DIR__) . '/certs/cacert.pem';
    }
    if (!is_readable($caBundle)) {
        curl_close($curl);
        throw new RuntimeException('The cURL CA certificate bundle is missing or unreadable.');
    }
    $options[CURLOPT_CAINFO] = $caBundle;
    if (strtoupper($method) === 'POST') {
        $options[CURLOPT_POSTFIELDS] = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_errno($curl);
    curl_close($curl);

    if ($response === false || $curlError !== 0) {
        throw new RuntimeException('Could not connect securely to Stripe. Check cURL and the CA certificate bundle.');
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe returned an invalid response.');
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException($decoded['error']['message'] ?? 'Stripe rejected the request.');
    }

    return $decoded;
}

function completeStripeOrder(Database $db, $connection, $checkoutSession)
{
    if (($checkoutSession['payment_status'] ?? '') !== 'paid') {
        return false;
    }

    $orderNumber = (string) ($checkoutSession['metadata']['order_number'] ?? '');
    $sessionId = (string) ($checkoutSession['id'] ?? '');
    if ($orderNumber === '' || $sessionId === '') {
        return false;
    }

    mysqli_begin_transaction($connection);
    try {
        $order = $db->fetchOne(
            'SELECT id, payment_status FROM orders WHERE order_number = ? AND transaction_id = ? AND payment_method = ? FOR UPDATE',
            [$orderNumber, $sessionId, 'stripe'],
            'sss'
        );
        if (!$order) {
            mysqli_commit($connection);
            return false;
        }
        if ($order['payment_status'] === 'completed') {
            mysqli_commit($connection);
            return true;
        }
        if ($order['payment_status'] !== 'pending') {
            mysqli_commit($connection);
            return false;
        }

        $updated = $db->execute(
            'UPDATE orders SET payment_status = ?, order_status = ? WHERE id = ? AND payment_status = ?',
            ['completed', 'processing', $order['id'], 'pending'],
            'ssis'
        );
        mysqli_commit($connection);
        if ($updated === 1) {
            sendOrderEmail($db, $order['id']);
        }
        return $updated === 1;
    } catch (Throwable $exception) {
        mysqli_rollback($connection);
        throw $exception;
    }
}

function releasePendingStripeOrder(Database $db, $connection, $orderId)
{
    mysqli_begin_transaction($connection);
    try {
        $order = $db->fetchOne(
            'SELECT id FROM orders WHERE id = ? AND payment_method = ? AND payment_status = ? AND order_status = ? FOR UPDATE',
            [$orderId, 'stripe', 'pending', 'pending'],
            'isss'
        );
        if (!$order) {
            mysqli_commit($connection);
            return false;
        }

        $items = $db->fetchAll('SELECT product_id, quantity FROM order_items WHERE order_id = ?', [$orderId], 'i');
        foreach ($items as $item) {
            $db->execute(
                'UPDATE products SET stock = stock + ? WHERE id = ?',
                [(int) $item['quantity'], (int) $item['product_id']],
                'ii'
            );
        }
        $db->execute(
            'UPDATE orders SET payment_status = ?, order_status = ? WHERE id = ? AND payment_status = ?',
            ['failed', 'cancelled', $orderId, 'pending'],
            'ssis'
        );

        mysqli_commit($connection);
        return true;
    } catch (Throwable $exception) {
        mysqli_rollback($connection);
        throw $exception;
    }
}

function updateAdminOrderStatus(Database $db, $connection, $orderId, $order, $newStatus)
{
    if (!canSetOrderStatus($order['payment_method'], $order['payment_status'], $newStatus, $order['order_status'])) {
        return false;
    }

    if (
        in_array($order['payment_method'], ['cod', 'stripe'], true)
        && $order['payment_status'] === 'pending'
        && $newStatus === 'cancelled'
    ) {
        return cancelUnpaidOrder($db, $connection, $orderId);
    }

    $db->execute('UPDATE orders SET order_status = ? WHERE id = ?', [$newStatus, $orderId], 'si');
    return true;
}

function cancelUnpaidOrder(Database $db, $connection, $orderId)
{
    mysqli_begin_transaction($connection);
    try {
        $order = $db->fetchOne(
            'SELECT id, payment_method, payment_status, order_status FROM orders WHERE id = ? FOR UPDATE',
            [$orderId],
            'i'
        );
        if (
            !$order
            || !in_array($order['payment_method'], ['cod', 'stripe'], true)
            || $order['payment_status'] !== 'pending'
            || !canSetOrderStatus($order['payment_method'], $order['payment_status'], 'cancelled', $order['order_status'])
        ) {
            mysqli_commit($connection);
            return false;
        }

        $items = $db->fetchAll('SELECT product_id, quantity FROM order_items WHERE order_id = ?', [$orderId], 'i');
        foreach ($items as $item) {
            $db->execute(
                'UPDATE products SET stock = stock + ? WHERE id = ?',
                [(int) $item['quantity'], (int) $item['product_id']],
                'ii'
            );
        }

        $paymentStatus = $order['payment_method'] === 'stripe' ? 'failed' : 'pending';
        $db->execute(
            'UPDATE orders SET payment_status = ?, order_status = ? WHERE id = ? AND payment_status = ? AND order_status = ?',
            [$paymentStatus, 'cancelled', $orderId, 'pending', $order['order_status']],
            'ssiss'
        );
        mysqli_commit($connection);
        return true;
    } catch (Throwable $exception) {
        mysqli_rollback($connection);
        throw $exception;
    }
}