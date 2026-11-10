<?php
/*
 * mailer.php — Gmail SMTP mailer for LectroHub
 * Place in:  /LectroHub/lectrohub_db/mailer.php
 * Called by:
 *   checkout.php  -> send_order_confirmation_email($conn, $order_id)
 *   orders.php    -> send_delivery_confirmation_email($conn, $order_id)
 *                    send_order_status_email($conn, $order_id, $new_status)
 * ─────────────────────────────────────────────────────────
 * Sends order emails to the CUSTOMER who placed the order
 * (users.email — populated by either the password signup flow
 * or google_callback.php), not to the site's own SMTP sender
 * account.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Build and send the order confirmation email.
 *
 * @param  PDO  $conn      Active DB connection
 * @param  int  $order_id  orders.id of the order just placed
 * @return bool            true on success, false on failure (never throws)
 */
function send_order_confirmation_email(PDO $conn, int $order_id): bool
{
    $cfg = require __DIR__ . '/mailer_config.php';

    /* ── Pull order + customer email + shipping/pickup info ── */
    $stmt = $conn->prepare("
        SELECT
            o.id, o.status, o.subtotal, o.shipping_fee, o.discount, o.total,
            o.payment_method, o.payment_status, o.fulfillment_type, o.created_at,
            u.full_name, u.email,
            a.full_name      AS ship_name,
            a.address_line   AS ship_address,
            a.city           AS ship_city,
            sl.name          AS pickup_name,
            sl.address_line  AS pickup_address,
            sl.city          AS pickup_city
        FROM orders o
        INNER JOIN users u          ON u.id = o.user_id
        LEFT JOIN  addresses a      ON a.id = o.address_id
        LEFT JOIN  store_locations sl ON sl.id = o.pickup_location_id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || empty($order['email'])) {
        error_log("mailer.php: no order or no customer email found for order_id={$order_id}");
        return false;
    }

    $items_stmt = $conn->prepare("
        SELECT product_name, product_sku, unit_price, quantity, line_total
        FROM order_items
        WHERE order_id = ?
    ");
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ── Build item rows ── */
    $rows_html = '';
    foreach ($items as $it) {
        $rows_html .=
            '<tr>'
            . '<td style="padding:8px;border-bottom:1px solid #eee">' . htmlspecialchars($it['product_name']) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center">' . (int)$it['quantity'] . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right">$' . number_format((float)$it['unit_price'], 2) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right">$' . number_format((float)$it['line_total'], 2) . '</td>'
            . '</tr>';
    }

    /* ── Fulfilment block ── */
    $is_pickup = ($order['fulfillment_type'] === 'pickup');
    if ($is_pickup) {
        $fulfil_block =
            '<strong>Pickup Location</strong><br>'
            . htmlspecialchars($order['pickup_name'] ?? '—') . '<br>'
            . htmlspecialchars($order['pickup_address'] ?? '') . ', ' . htmlspecialchars($order['pickup_city'] ?? '');
    } else {
        $fulfil_block =
            '<strong>Delivery Address</strong><br>'
            . htmlspecialchars($order['ship_name'] ?? $order['full_name']) . '<br>'
            . htmlspecialchars($order['ship_address'] ?? '') . ', ' . htmlspecialchars($order['ship_city'] ?? '');
    }

    $order_number = str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT);
    $order_date   = date('d M Y, H:i', strtotime($order['created_at']));

    $subtotal = number_format((float)$order['subtotal'], 2);
    $shipping = number_format((float)$order['shipping_fee'], 2);
    $discount = number_format((float)$order['discount'], 2);
    $total    = number_format((float)$order['total'], 2);

    $html = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1a1a1a">
    <div style="background:#0b0f17;color:#00e5ff;padding:20px;text-align:center;font-size:20px;font-weight:bold">
        LectroHub
    </div>
    <div style="padding:24px">
        <h2 style="margin-top:0">Thanks for your order, {$order['full_name']}!</h2>
        <p>Your order <strong>#{$order_number}</strong> placed on {$order_date} has been received and is being processed.</p>

        <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:14px">
            <thead>
                <tr style="background:#f5f5f5">
                    <th style="padding:8px;text-align:left">Product</th>
                    <th style="padding:8px;text-align:center">Qty</th>
                    <th style="padding:8px;text-align:right">Unit</th>
                    <th style="padding:8px;text-align:right">Total</th>
                </tr>
            </thead>
            <tbody>
                {$rows_html}
            </tbody>
        </table>

        <table style="width:100%;font-size:14px;margin-bottom:20px">
            <tr><td>Subtotal</td><td style="text-align:right">\${$subtotal}</td></tr>
            <tr><td>Shipping</td><td style="text-align:right">\${$shipping}</td></tr>
            <tr><td>Discount</td><td style="text-align:right">-\${$discount}</td></tr>
            <tr style="font-weight:bold;font-size:16px"><td style="padding-top:8px">Total</td><td style="text-align:right;padding-top:8px">\${$total}</td></tr>
        </table>

        <div style="background:#f9f9f9;padding:14px;border-radius:8px;margin-bottom:20px;font-size:14px">
            {$fulfil_block}
        </div>

        <p style="font-size:13px;color:#666">
            Payment method: {$order['payment_method']} &middot; Status: {$order['payment_status']}
        </p>

        <p style="margin-top:30px;font-size:13px;color:#999">
            Questions about your order? Reply to this email or visit your Orders page on LectroHub.
        </p>
    </div>
</div>
HTML;

    $alt_body = "Thanks for your order #{$order_number}. Total: \${$total}. "
        . "View full details in your LectroHub account under My Orders.";

    /* ── Send via Gmail SMTP ── */
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_username'];
        $mail->Password   = $cfg['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['smtp_port'];

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($order['email'], $order['full_name']);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        $mail->isHTML(true);
        $mail->Subject = "Order Confirmation — #{$order_number} — LectroHub";
        $mail->Body    = $html;
        $mail->AltBody = $alt_body;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("mailer.php: order confirmation email failed for order_id={$order_id}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Sends a plain status-update email for non-Delivered status
 * changes (Processing, Shipped, Cancelled). Called by
 * admin/orders.php whenever an admin changes an order's status
 * to anything other than "Delivered" (which gets the fuller
 * itemized receipt via send_delivery_confirmation_email instead).
 *
 * @param  PDO    $conn        Active DB connection
 * @param  int    $order_id    orders.id being updated
 * @param  string $new_status  The new status value (Processing / Shipped / Cancelled)
 * @return bool                true on success, false on failure (never throws)
 */
function send_order_status_email(PDO $conn, int $order_id, string $new_status): bool
{
    $cfg = require __DIR__ . '/mailer_config.php';

    $stmt = $conn->prepare("
        SELECT o.id, o.total, u.full_name, u.email
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || empty($order['email'])) {
        error_log("mailer.php: no order or no customer email found for order_id={$order_id}");
        return false;
    }

    $order_number = str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT);
    $total        = number_format((float)$order['total'], 2);

    $status_messages = [
        'Processing' => 'Your order is being prepared and will ship soon.',
        'Shipped'    => 'Great news — your order is on its way!',
        'Cancelled'  => 'Your order has been cancelled. If this was unexpected, please contact support.',
    ];
    $message = $status_messages[$new_status] ?? "Your order status has been updated to \"{$new_status}\".";

    $html = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1a1a1a">
    <div style="background:#0b0f17;color:#00e5ff;padding:20px;text-align:center;font-size:20px;font-weight:bold">
        LectroHub
    </div>
    <div style="padding:24px">
        <h2 style="margin-top:0">Hi {$order['full_name']},</h2>
        <p>{$message}</p>

        <div style="background:#f9f9f9;padding:14px;border-radius:8px;margin:20px 0;font-size:14px">
            <strong>Order:</strong> #{$order_number}<br>
            <strong>Status:</strong> {$new_status}<br>
            <strong>Total:</strong> \${$total}
        </div>

        <p style="margin-top:30px;font-size:13px;color:#999">
            Questions about your order? Reply to this email or visit your Orders page on LectroHub.
        </p>
    </div>
</div>
HTML;

    $alt_body = "Order #{$order_number} status update: {$new_status}. {$message}";

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_username'];
        $mail->Password   = $cfg['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['smtp_port'];

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($order['email'], $order['full_name']);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        $mail->isHTML(true);
        $mail->Subject = "Order #{$order_number} — {$new_status} — LectroHub";
        $mail->Body    = $html;
        $mail->AltBody = $alt_body;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("mailer.php: status update email failed for order_id={$order_id}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Sends the full itemized receipt email when an order is marked
 * "Delivered". Called by admin/orders.php.
 *
 * @param  PDO  $conn      Active DB connection
 * @param  int  $order_id  orders.id being marked delivered
 * @return bool            true on success, false on failure (never throws)
 */
function send_delivery_confirmation_email(PDO $conn, int $order_id): bool
{
    $cfg = require __DIR__ . '/mailer_config.php';

    $stmt = $conn->prepare("
        SELECT
            o.id, o.subtotal, o.shipping_fee, o.discount, o.total,
            o.payment_method, o.payment_status, o.fulfillment_type, o.created_at,
            u.full_name, u.email,
            a.full_name      AS ship_name,
            a.address_line   AS ship_address,
            a.city           AS ship_city,
            sl.name          AS pickup_name,
            sl.address_line  AS pickup_address,
            sl.city          AS pickup_city
        FROM orders o
        INNER JOIN users u          ON u.id = o.user_id
        LEFT JOIN  addresses a      ON a.id = o.address_id
        LEFT JOIN  store_locations sl ON sl.id = o.pickup_location_id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order || empty($order['email'])) {
        error_log("mailer.php: no order or no customer email found for order_id={$order_id}");
        return false;
    }

    $items_stmt = $conn->prepare("
        SELECT product_name, product_sku, unit_price, quantity, line_total
        FROM order_items
        WHERE order_id = ?
    ");
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows_html = '';
    foreach ($items as $it) {
        $rows_html .=
            '<tr>'
            . '<td style="padding:8px;border-bottom:1px solid #eee">' . htmlspecialchars($it['product_name']) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center">' . (int)$it['quantity'] . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right">$' . number_format((float)$it['unit_price'], 2) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right">$' . number_format((float)$it['line_total'], 2) . '</td>'
            . '</tr>';
    }

    $is_pickup = ($order['fulfillment_type'] === 'pickup');
    if ($is_pickup) {
        $fulfil_block =
            '<strong>Picked Up From</strong><br>'
            . htmlspecialchars($order['pickup_name'] ?? '—') . '<br>'
            . htmlspecialchars($order['pickup_address'] ?? '') . ', ' . htmlspecialchars($order['pickup_city'] ?? '');
    } else {
        $fulfil_block =
            '<strong>Delivered To</strong><br>'
            . htmlspecialchars($order['ship_name'] ?? $order['full_name']) . '<br>'
            . htmlspecialchars($order['ship_address'] ?? '') . ', ' . htmlspecialchars($order['ship_city'] ?? '');
    }

    $order_number = str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT);
    $order_date   = date('d M Y, H:i', strtotime($order['created_at']));

    $subtotal = number_format((float)$order['subtotal'], 2);
    $shipping = number_format((float)$order['shipping_fee'], 2);
    $discount = number_format((float)$order['discount'], 2);
    $total    = number_format((float)$order['total'], 2);

    $html = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1a1a1a">
    <div style="background:#0b0f17;color:#00e5ff;padding:20px;text-align:center;font-size:20px;font-weight:bold">
        LectroHub
    </div>
    <div style="padding:24px">
        <h2 style="margin-top:0">✅ Delivered! Enjoy, {$order['full_name']}</h2>
        <p>Your order <strong>#{$order_number}</strong> placed on {$order_date} has been delivered. Here's your final receipt:</p>

        <table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:14px">
            <thead>
                <tr style="background:#f5f5f5">
                    <th style="padding:8px;text-align:left">Product</th>
                    <th style="padding:8px;text-align:center">Qty</th>
                    <th style="padding:8px;text-align:right">Unit</th>
                    <th style="padding:8px;text-align:right">Total</th>
                </tr>
            </thead>
            <tbody>
                {$rows_html}
            </tbody>
        </table>

        <table style="width:100%;font-size:14px;margin-bottom:20px">
            <tr><td>Subtotal</td><td style="text-align:right">\${$subtotal}</td></tr>
            <tr><td>Shipping</td><td style="text-align:right">\${$shipping}</td></tr>
            <tr><td>Discount</td><td style="text-align:right">-\${$discount}</td></tr>
            <tr style="font-weight:bold;font-size:16px"><td style="padding-top:8px">Total</td><td style="text-align:right;padding-top:8px">\${$total}</td></tr>
        </table>

        <div style="background:#f9f9f9;padding:14px;border-radius:8px;margin-bottom:20px;font-size:14px">
            {$fulfil_block}
        </div>

        <p style="font-size:13px;color:#666">
            Payment method: {$order['payment_method']} &middot; Status: {$order['payment_status']}
        </p>

        <p style="margin-top:30px;font-size:13px;color:#999">
            Love your purchase? Consider leaving a review. Need help? Reply to this email or visit your Orders page on LectroHub.
        </p>
    </div>
</div>
HTML;

    $alt_body = "Your order #{$order_number} has been delivered. Total: \${$total}. "
        . "View full details in your LectroHub account under My Orders.";

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_username'];
        $mail->Password   = $cfg['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['smtp_port'];

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($order['email'], $order['full_name']);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        $mail->isHTML(true);
        $mail->Subject = "Delivered — Order #{$order_number} — LectroHub";
        $mail->Body    = $html;
        $mail->AltBody = $alt_body;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("mailer.php: delivery confirmation email failed for order_id={$order_id}: " . $mail->ErrorInfo);
        return false;
    }
}