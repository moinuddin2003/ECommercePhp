<?php
/**
 * core/Mailer.php
 * ------------------------------------------------
 * Sends email through an SMTP server (Gmail by default) using
 * PHPMailer. PHPMailer is just 3 plain files in /phpmailer —
 * no Composer needed.
 *
 * Settings come from .env (same file the Stripe keys use):
 *   SMTP_HOST=smtp.gmail.com
 *   SMTP_PORT=587
 *   SMTP_USER=youraddress@gmail.com
 *   SMTP_PASS=your-16-character-app-password
 *   SMTP_FROM_NAME="Your Shop Name"
 *
 * Note: stripeSetting() (in config/payments.php) is the function
 * that reads .env. It works for any key, not only Stripe ones.
 */

require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends one HTML email. Returns true on success, false on failure.
 * It never throws, so a mail problem can never break checkout.
 * Failures are written to the PHP error log.
 */
function sendMail($toEmail, $toName, $subject, $htmlBody)
{
    $host = stripeSetting('SMTP_HOST');
    $port = (int) stripeSetting('SMTP_PORT');
    $user = stripeSetting('SMTP_USER');
    $pass = stripeSetting('SMTP_PASS');
    $fromName = stripeSetting('SMTP_FROM_NAME') ?: 'Shop';

    if (!$host || !$port || !$user || !$pass) {
        error_log('Mailer: SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASS missing in .env');
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        // 465 = SSL from the start, 587 = STARTTLS upgrade
        $mail->SMTPSecure = $port === 465
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        // Give up after 10 seconds instead of making the customer wait minutes
        // (Timeout = opening the connection, Timelimit = waiting for replies)
        $mail->Timeout = 10;
        $mail->getSMTPInstance()->Timelimit = 10;
        $mail->CharSet = 'UTF-8';

        // Reuse the CA bundle already used for Stripe (helps on XAMPP/Windows)
        $caBundle = dirname(__DIR__) . '/certs/cacert.pem';
        if (is_readable($caBundle)) {
            $mail->SMTPOptions = ['ssl' => ['cafile' => $caBundle]];
        }

        $mail->setFrom($user, $fromName);
        $mail->addAddress($toEmail, (string) $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = trim(strip_tags($htmlBody));

        $mail->send();
        return true;
    } catch (Throwable $exception) {
        error_log('Mailer: could not send to ' . $toEmail . ' - ' . $exception->getMessage());
        return false;
    }
}

function orderEmailEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderOrderEmail($order, $items, $event)
{
    $events = [
        'placed' => [
            'subject' => 'We received your order',
            'title' => 'Thank you for your order',
            'message' => 'We have received your order and our team will begin preparing it shortly.',
            'accent' => '#287a63',
        ],
        'shipped' => [
            'subject' => 'Your order is on its way',
            'title' => 'Your order has shipped',
            'message' => 'Your order has left us and is on its way to the delivery address below.',
            'accent' => '#356b9a',
        ],
        'delivered' => [
            'subject' => 'Your order was delivered',
            'title' => 'Your order has been delivered',
            'message' => 'Your order is marked as delivered. We hope you enjoy your purchase.',
            'accent' => '#287a63',
        ],
        'cancelled' => [
            'subject' => 'Your order was cancelled',
            'title' => 'Order cancelled',
            'message' => 'Your order has been cancelled. No further fulfillment will take place.',
            'accent' => '#a34545',
        ],
    ];
    if (!isset($events[$event])) {
        return null;
    }

    $details = $events[$event];
    $shopName = trim((string) stripeSetting('SMTP_FROM_NAME')) ?: 'NexMart';
    $itemRows = '';
    foreach ($items as $item) {
        $itemRows .= '<tr>'
            . '<td style="padding:12px 8px;border-bottom:1px solid #e8edf0;color:#303a40;font-size:14px;">'
            . orderEmailEscape($item['name']) . ' <span style="color:#77838b;">&times; ' . (int) $item['quantity'] . '</span></td>'
            . '<td align="right" style="padding:12px 8px;border-bottom:1px solid #e8edf0;color:#303a40;font-size:14px;white-space:nowrap;">$'
            . number_format((float) $item['subtotal'], 2) . '</td></tr>';
    }

    $paymentLabel = paymentMethodLabel($order['payment_method']);
    $paymentState = paymentStatusLabel($order['payment_method'], $order['payment_status'], $order['order_status']);
    $orderNumber = orderEmailEscape($order['order_number']);
    $total = number_format((float) $order['total_amount'], 2);
    $name = orderEmailEscape($order['name']);
    $address = nl2br(orderEmailEscape($order['shipping_address']));
    $preheader = orderEmailEscape($details['message']);

    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . orderEmailEscape($details['subject']) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f2f5f4;font-family:Arial,Helvetica,sans-serif;color:#253139;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $preheader . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f5f4;padding:32px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e3e9e7;">'
        . '<tr><td style="padding:22px 32px;background:#1f302d;color:#ffffff;font-size:16px;font-weight:bold;">' . orderEmailEscape($shopName) . '</td></tr>'
        . '<tr><td style="padding:32px 32px 20px;"><div style="margin-bottom:12px;color:' . $details['accent'] . ';font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">Order update</div>'
        . '<h1 style="margin:0 0 12px;font-size:26px;line-height:1.25;color:#202c31;">' . orderEmailEscape($details['title']) . '</h1>'
        . '<p style="margin:0;color:#647179;font-size:15px;line-height:1.7;">Hi ' . $name . ', ' . orderEmailEscape($details['message']) . '</p></td></tr>'
        . '<tr><td style="padding:0 32px 24px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8f7;border:1px solid #e8edeb;">'
        . '<tr><td style="padding:12px 14px;color:#647179;font-size:12px;text-transform:uppercase;">Order number</td><td align="right" style="padding:12px 14px;color:#253139;font-size:14px;font-weight:bold;">' . $orderNumber . '</td></tr>'
        . '<tr><td style="padding:0 14px 12px;color:#647179;font-size:12px;text-transform:uppercase;">Payment</td><td align="right" style="padding:0 14px 12px;color:#253139;font-size:14px;">' . orderEmailEscape($paymentLabel) . ' &middot; ' . orderEmailEscape($paymentState) . '</td></tr>'
        . '</table></td></tr>'
        . '<tr><td style="padding:0 32px 8px;"><h2 style="margin:0;color:#253139;font-size:16px;">Order summary</h2></td></tr>'
        . '<tr><td style="padding:0 32px 24px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $itemRows
        . '<tr><td style="padding:14px 8px;color:#253139;font-size:14px;font-weight:bold;">Total</td><td align="right" style="padding:14px 8px;color:#253139;font-size:16px;font-weight:bold;">$' . $total . '</td></tr>'
        . '</table></td></tr>'
        . '<tr><td style="padding:0 32px 28px;"><h2 style="margin:0 0 8px;color:#253139;font-size:16px;">Delivery address</h2><p style="margin:0;color:#647179;font-size:14px;line-height:1.7;">' . $address . '</p></td></tr>'
        . '<tr><td style="padding:18px 32px;background:#f6f8f7;color:#77838b;font-size:12px;line-height:1.6;">This is an automated order update from ' . orderEmailEscape($shopName) . '. Please keep it for your records.</td></tr>'
        . '</table></td></tr></table></body></html>';

    return ['subject' => $details['subject'] . ' - ' . $order['order_number'], 'html' => $html];
}

function sendOrderEmail(Database $db, $orderId)
{
    $order = $db->fetchOne(
        'SELECT o.order_number, o.total_amount, o.payment_method, o.payment_status, o.order_status, o.shipping_address, u.name, u.email
         FROM orders o
         JOIN users u ON u.id = o.user_id
         WHERE o.id = ?',
        [$orderId],
        'i'
    );
    if (!$order) {
        return false;
    }

    $items = $db->fetchAll(
        'SELECT p.name, oi.quantity, oi.subtotal
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?',
        [$orderId],
        'i'
    );

    $email = renderOrderEmail($order, $items, 'placed');
    return $email ? sendMail($order['email'], $order['name'], $email['subject'], $email['html']) : false;
}

function sendOrderStatusEmail(Database $db, $orderId, $status)
{
    if (!in_array($status, ['shipped', 'delivered', 'cancelled'], true)) {
        return false;
    }

    $order = $db->fetchOne(
        'SELECT o.order_number, o.total_amount, o.payment_method, o.payment_status, o.order_status, o.shipping_address, u.name, u.email
         FROM orders o
         JOIN users u ON u.id = o.user_id
         WHERE o.id = ?',
        [$orderId],
        'i'
    );
    if (!$order || $order['order_status'] !== $status) {
        return false;
    }

    $items = $db->fetchAll(
        'SELECT p.name, oi.quantity, oi.subtotal
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?',
        [$orderId],
        'i'
    );
    $email = renderOrderEmail($order, $items, $status);

    return $email ? sendMail($order['email'], $order['name'], $email['subject'], $email['html']) : false;
}
