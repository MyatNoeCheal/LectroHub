<?php
/**
 * winback_coupons.php
 * ─────────────────────────────────────────────────────────
 * Run via cron (e.g. once a day):
 *   php /path/to/LectroHub/cron/winback_coupons.php
 *
 * Amazon Prime case-study lesson — predictable purchase data
 * lets you spot lapsed customers and win them back. Finds
 * customers who have ordered before but placed no order in
 * the last 60 days, and issues each one a single-use,
 * time-limited, personalized coupon (if they don't already
 * have one outstanding).
 *
 * Requires migration_personalization.sql to have been run
 * first (adds coupons.user_id and coupons.reason).
 * ─────────────────────────────────────────────────────────
 */

require_once __DIR__ . "/../lectrohub_db/connection.php";

const WINBACK_LOOKBACK_DAYS = 60;
const WINBACK_DISCOUNT_PCT  = 15.00;
const WINBACK_EXPIRY_DAYS   = 14;
const WINBACK_REASON        = 'winback';

/* ══════════════════════════════════════════
   Generate a unique coupon code like BACK-4F9A2C
══════════════════════════════════════════ */
function generate_unique_code(PDO $conn): string
{
    do {
        $code = 'BACK-' . strtoupper(bin2hex(random_bytes(3)));
        $chk = $conn->prepare("SELECT COUNT(*) FROM coupons WHERE code = ?");
        $chk->execute([$code]);
    } while ($chk->fetchColumn() > 0);

    return $code;
}

function log_line(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    echo $line;
    file_put_contents(__DIR__ . '/winback_coupons.log', $line, FILE_APPEND);
}

log_line("Starting win-back coupon run...");

try {
    /* ══════════════════════════════════════════
       Lapsed customers:
       - have at least one past order (not a
         never-purchased browser/guest)
       - no order in the last 60 days
       - don't already have an active, unused
         win-back coupon outstanding
    ══════════════════════════════════════════ */
    $lapsed_stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.email
        FROM users u
        WHERE u.role = 'user'
          AND EXISTS (
              SELECT 1 FROM orders o WHERE o.user_id = u.id
          )
          AND NOT EXISTS (
              SELECT 1 FROM orders o2
              WHERE o2.user_id = u.id
                AND o2.created_at >= DATE_SUB(NOW(), INTERVAL :lookback DAY)
          )
          AND NOT EXISTS (
              SELECT 1 FROM coupons c
              WHERE c.user_id = u.id
                AND c.reason  = :reason
                AND c.active  = 1
                AND c.used_count < c.usage_limit
                AND (c.expires_at IS NULL OR c.expires_at > NOW())
          )
    ");
    $lapsed_stmt->execute([
        ':lookback' => WINBACK_LOOKBACK_DAYS,
        ':reason'   => WINBACK_REASON,
    ]);
    $lapsed_users = $lapsed_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($lapsed_users)) {
        log_line("No lapsed customers need a win-back coupon today.");
        exit(0);
    }

    $ins = $conn->prepare("
        INSERT INTO coupons
            (code, user_id, reason, type, value, min_order, usage_limit, used_count, expires_at, active)
        VALUES
            (:code, :user_id, :reason, 'percent', :value, 0, 1, 0, :expires_at, 1)
    ");

    $issued = 0;

    foreach ($lapsed_users as $user) {
        $code       = generate_unique_code($conn);
        $expires_at = date('Y-m-d 23:59:59', strtotime('+' . WINBACK_EXPIRY_DAYS . ' days'));

        $ins->execute([
            ':code'       => $code,
            ':user_id'    => $user['id'],
            ':reason'     => WINBACK_REASON,
            ':value'      => WINBACK_DISCOUNT_PCT,
            ':expires_at' => $expires_at,
        ]);

        $issued++;
        log_line(sprintf(
            "Issued %s (%.0f%% off, expires %s) to %s <%s>",
            $code,
            WINBACK_DISCOUNT_PCT,
            $expires_at,
            $user['full_name'],
            $user['email']
        ));

        // TODO: email the customer their code — e.g.
        // send_winback_email($user['email'], $user['full_name'], $code, $expires_at);
        // See reorder_alerts.php's send_alert_email() for the mail() pattern to reuse.
    }

    log_line("Done. Issued {$issued} win-back coupon(s).");

} catch (PDOException $e) {
    error_log('winback_coupons.php error: ' . $e->getMessage());
    log_line("ERROR: " . $e->getMessage());
    exit(1);
}