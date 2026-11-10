<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";
require_once "../lectrohub_db/shipping_helper.php";

header('Content-Type: application/json');

/* ══════════════════════════════════════════
   Free Shipping Rule
                                
| ---------------------- | -------------------------------------------------------------------------------------------------------- |
| **Subtotal ≥ $50.00**  | Yangon, Mandalay, Naypyidaw, Bago, Mawlamyine, Taunggyi, Pathein, Magway, Monywa, Meiktila, Pyay, Hpa-An |
| **Subtotal ≥ $100.00** | Frankfurt, Berlin, Munich, Hamburg, Cologne, Düsseldorf                                                  |
| **No free shipping**   | Myitkyina, Sittwe, Lashio, Dawei, Loikaw, Hakha, Kengtung, Myeik                                         |

══════════════════════════════════════════ */

$city = trim($_POST['city'] ?? '');

if ($city === '') {
    echo json_encode(['success' => false, 'message' => 'City is required.']);
    exit;
}

$subtotal = 0.00;

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("
        SELECT id, price, stock
        FROM products
        WHERE id IN ($placeholders) AND status = 'active'
    ");
    $stmt->execute($ids);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $qty = min((int)$_SESSION['cart'][$row['id']], (int)$row['stock']);
        if ($qty > 0) {
            $subtotal += (float)$row['price'] * $qty;
        }
    }
}

$quote    = get_shipping_quote($conn, $city, $subtotal);
$discount = (float)($_SESSION['coupon_discount'] ?? 0);
$total    = max(0, $subtotal + $quote['fee'] - $discount);

echo json_encode([
    'success'   => true,
    'city'      => $city,
    'fee'       => $quote['fee'],
    'free'      => $quote['fee'] == 0.00,
    'threshold' => $quote['threshold'],
    'subtotal'  => round($subtotal, 2),
    'discount'  => round($discount, 2),
    'total'     => round($total, 2),
]);