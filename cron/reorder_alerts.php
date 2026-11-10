<?php
/**
 * reorder_alerts.php
 * ─────────────────────────────────────────────────────────
 * Run via cron (e.g. once a day):
 *   0 8 * * * php /path/to/LectroHub/cron/reorder_alerts.php
 *
 * Turns the passive "low stock" badge into an active operational
 * trigger: emails admins whenever a FEATURED product's stock drops
 * below the reorder threshold, so restocking doesn't rely on someone
 * noticing the badge in the dashboard.
 *
 * Suppresses duplicate alerts for the same product within 24h using
 * reorder_alert_log (see migration_reorder_alerts.sql).
 * ─────────────────────────────────────────────────────────
 */

require_once __DIR__ . "/../lectrohub_db/connection.php";

const REORDER_STOCK_THRESHOLD = 10;
const ALERT_SUPPRESS_HOURS    = 24;
const FROM_EMAIL              = 'alerts@lectrohub.local';
const FROM_NAME               = 'LectroHub Inventory Bot';

/* ══════════════════════════════════════════
   Send a single email. Uses PHP's built-in
   mail() — on most XAMPP/local setups this
   needs an SMTP relay configured in php.ini
   (sendmail_path) to actually deliver. If you
   don't have that set up yet, every alert is
   still written to reorder_alerts.log below,
   so you can verify the logic works even
   before email delivery is wired up.
══════════════════════════════════════════ */
function send_alert_email(string $to, string $subject, string $body): bool
{
    $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($to, $subject, $body, $headers);
}

function log_line(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    echo $line;
    file_put_contents(__DIR__ . '/reorder_alerts.log', $line, FILE_APPEND);
}

log_line("Starting reorder alert run...");

try {
    /* ── Featured products under the reorder threshold ──
       FIX: products has no `brand` column — brand lives in
       brands.brand_name, joined via products.brand_id. ── */
    $stmt = $conn->prepare("
        SELECT p.id, p.product_name, p.sku, p.stock, b.brand_name AS brand
        FROM products p
        JOIN brands b ON b.id = p.brand_id
        WHERE p.status = 'active'
          AND p.featured = 1
          AND p.stock < :threshold
        ORDER BY p.stock ASC
    ");
    $stmt->execute([':threshold' => REORDER_STOCK_THRESHOLD]);
    $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($low_stock_products)) {
        log_line("No featured products below the reorder threshold. Nothing to do.");
        exit(0);
    }

    /* ── Filter out products already alerted in the last 24h ── */
    $suppress_check = $conn->prepare("
        SELECT COUNT(*) FROM reorder_alert_log
        WHERE product_id = ?
          AND sent_at >= DATE_SUB(NOW(), INTERVAL " . ALERT_SUPPRESS_HOURS . " HOUR)
    ");

    $to_alert = [];
    foreach ($low_stock_products as $p) {
        $suppress_check->execute([$p['id']]);
        if ((int)$suppress_check->fetchColumn() === 0) {
            $to_alert[] = $p;
        }
    }

    if (empty($to_alert)) {
        log_line("All low-stock featured products were already alerted within the last "
            . ALERT_SUPPRESS_HOURS . "h. Skipping.");
        exit(0);
    }

    /* ── Get admin recipients ── */
    $admins = $conn->query("
        SELECT full_name, email FROM users WHERE role = 'admin'
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($admins)) {
        log_line("No admin users found — nowhere to send alerts. Aborting.");
        exit(1);
    }

    /* ── Build the email body ── */
    $subject = '⚠️ LectroHub: ' . count($to_alert) . ' featured product(s) need restocking';

    $body  = "The following featured products have fallen below the reorder threshold of "
           . REORDER_STOCK_THRESHOLD . " units:\n\n";

    foreach ($to_alert as $p) {
        $body .= sprintf(
            "- %s (%s) — %s — %d units left\n",
            $p['product_name'],
            $p['brand'],
            $p['sku'],
            (int)$p['stock']
        );
    }

    $body .= "\nRestock these soon to avoid stockouts on high-visibility products.\n";
    $body .= "— LectroHub Inventory Bot\n";

    /* ── Send + log ── */
    $log_ins = $conn->prepare("INSERT INTO reorder_alert_log (product_id) VALUES (?)");
    $sent_count = 0;

    foreach ($admins as $admin) {
        if (send_alert_email($admin['email'], $subject, $body)) {
            $sent_count++;
            log_line("Emailed {$admin['full_name']} <{$admin['email']}>");
        } else {
            log_line("FAILED to email {$admin['full_name']} <{$admin['email']}> — check mail server config.");
        }
    }

    // Log each alerted product once, regardless of how many admins received it,
    // so the 24h suppression window applies per-product, not per-email.
    foreach ($to_alert as $p) {
        $log_ins->execute([$p['id']]);
    }

    log_line("Done. " . count($to_alert) . " product(s) alerted, "
        . $sent_count . "/" . count($admins) . " admin email(s) sent successfully.");

} catch (PDOException $e) {
    error_log('reorder_alerts.php error: ' . $e->getMessage());
    log_line("ERROR: " . $e->getMessage());
    exit(1);
}