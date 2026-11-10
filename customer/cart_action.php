<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";

header('Content-Type: application/json');

/* ══════════════════════════════════════════
   CART ACTION — session-based cart
   Works for both guests and logged-in users.
   $_SESSION['cart'] = [ product_id => quantity, ... ]

   Actions: add | update | remove | clear
══════════════════════════════════════════ */

$action     = $_POST['action']     ?? '';
$product_id = (int)($_POST['product_id'] ?? 0);
$qty        = (int)($_POST['qty']        ?? 1);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

switch ($action) {

    /* ── ADD ── */
    case 'add':
        if ($product_id < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit;
        }

        /* Ensure qty is at least 1 */
        if ($qty < 1) $qty = 1;

        try {
            $stmt = $conn->prepare(
                "SELECT id, stock FROM products WHERE id = :id AND status = 'active' LIMIT 1"
            );
            $stmt->execute([':id' => $product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $product = null;
        }

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }

        $current_qty = (int)($_SESSION['cart'][$product_id] ?? 0);

        /* FIX: Use $qty (from POST) instead of hardcoded +1,
           so the quantity selector on the product page is respected. */
        $new_qty = $current_qty + $qty;

        if ($new_qty > (int)$product['stock']) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock.']);
            exit;
        }

        $_SESSION['cart'][$product_id] = $new_qty;

        echo json_encode([
            'success'    => true,
            'message'    => 'Added to cart.',
            'cart_count' => array_sum($_SESSION['cart']),
        ]);
        break;

    /* ── UPDATE ── */
    case 'update':
        if ($product_id < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit;
        }

        if ($qty < 1) {
            unset($_SESSION['cart'][$product_id]);
        } else {
            try {
                $stmt = $conn->prepare("SELECT stock FROM products WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $product = null;
            }

            $max = $product ? (int)$product['stock'] : $qty;
            $_SESSION['cart'][$product_id] = min($qty, $max);
        }

        echo json_encode([
            'success'    => true,
            'cart_count' => array_sum($_SESSION['cart']),
        ]);
        break;

    /* ── REMOVE ── */
    case 'remove':
        unset($_SESSION['cart'][$product_id]);

        echo json_encode([
            'success'    => true,
            'cart_count' => array_sum($_SESSION['cart']),
        ]);
        break;

    /* ── CLEAR ── */
    case 'clear':
        $_SESSION['cart'] = [];

        echo json_encode([
            'success'    => true,
            'cart_count' => 0,
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}