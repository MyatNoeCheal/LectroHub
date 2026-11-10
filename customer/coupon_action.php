<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";

header('Content-Type: application/json');

/* ══════════════════════════════════════════
   Always compute the subtotal from the real
   session cart. Never trust $_POST['subtotal'] —
   it can be forged by the client to bypass the
   coupon's min_order requirement.
══════════════════════════════════════════ */
function get_cart_subtotal(PDO $conn): float
{
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return 0.0;
    }

    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("
        SELECT id, price, stock
        FROM products
        WHERE id IN ($placeholders)
          AND status = 'active'
    ");
    $stmt->execute($ids);

    $subtotal = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $qty = min((int)$_SESSION['cart'][$row['id']], (int)$row['stock']);
        if ($qty > 0) {
            $subtotal += (float)$row['price'] * $qty;
        }
    }

    return $subtotal;
}

$action = $_POST['action'] ?? '';
$code   = strtoupper(trim($_POST['code'] ?? ''));

switch ($action) {

    /* ── APPLY ── */
    case 'apply':
        if ($code === '') {
            echo json_encode(['success' => false, 'message' => 'Please enter a coupon code.']);
            exit;
        }

        $subtotal = get_cart_subtotal($conn);

        if ($subtotal <= 0) {
            echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
            exit;
        }

        try {
            $stmt = $conn->prepare("
                SELECT * FROM coupons
                WHERE code = :code
                  AND active = 1
                  AND (expires_at IS NULL OR expires_at > NOW())
                  AND (usage_limit IS NULL OR used_count < usage_limit)
                LIMIT 1
            ");
            $stmt->execute([':code' => $code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
            exit;
        }

        if (!$coupon) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code.']);
            exit;
        }

        /* ══════════════════════════════════════════
           FIX: OWNERSHIP CHECK FOR PERSONALIZED COUPONS
           Win-back coupons (winback_coupons.php) are
           tied to a specific user via coupons.user_id.
           Without this check, anyone who saw or guessed
           another customer's code (e.g. shared on a forum,
           or leaked via a screenshot) could redeem a
           discount that was never meant for them —
           defeating the point of a targeted, personalized
           incentive. A NULL user_id means it's a normal,
           anyone-can-use coupon and skips this check.
        ══════════════════════════════════════════ */
        if ($coupon['user_id'] !== null) {
            $current_user_id = $_SESSION['user']['id'] ?? null;

            if ($current_user_id === null || (int)$coupon['user_id'] !== (int)$current_user_id) {
                echo json_encode(['success' => false, 'message' => 'This coupon isn\'t valid for your account.']);
                exit;
            }
        }

        // Check minimum order requirement
        if ($subtotal < (float)$coupon['min_order']) {
            echo json_encode([
                'success' => false,
                'message' => 'Minimum order of €' . number_format($coupon['min_order'], 2) . ' required for this coupon.',
            ]);
            exit;
        }

        // Calculate discount
        if ($coupon['type'] === 'percent') {
            $discount = round($subtotal * ($coupon['value'] / 100), 2);
            $msg      = $coupon['value'] . '% discount applied!';
        } else {
            $discount = min((float)$coupon['value'], $subtotal);
            $msg      = '€' . number_format($discount, 2) . ' discount applied!';
        }

        // Store in session
        $_SESSION['coupon_code']     = $coupon['code'];
        $_SESSION['coupon_id']       = (int)$coupon['id'];
        $_SESSION['coupon_discount'] = $discount;

        // Recompute total so the cart page can update the summary in one round trip
        $shipping = $subtotal > 0 && $subtotal < 50 ? 4.99 : 0.00;
        $total    = max(0, $subtotal + $shipping - $discount);

        echo json_encode([
            'success'  => true,
            'message'  => $msg,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'total'    => $total,
        ]);
        break;

    /* ── REMOVE ── */
    case 'remove':
        unset($_SESSION['coupon_code'], $_SESSION['coupon_id'], $_SESSION['coupon_discount']);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}