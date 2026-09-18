<?php
/**
 * Local payment sandbox configuration.
 *
 * This is intentionally a simulator. It does not contact JazzCash or
 * Easypaisa and must not be used to accept real payments.
 */

define('PAYMENT_MODE', 'sandbox');
define('PAYMENT_SANDBOX', PAYMENT_MODE === 'sandbox');

function isSupportedPaymentMethod($paymentMethod)
{
    return in_array($paymentMethod, ['cod', 'jazzcash', 'easypaisa'], true);
}

function createSandboxTransactionId($paymentMethod)
{
    return 'SBX-' . strtoupper($paymentMethod) . '-' . strtoupper(bin2hex(random_bytes(5)));
}