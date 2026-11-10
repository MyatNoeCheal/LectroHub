<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";

header('Content-Type: application/json');

/* ══════════════════════════════════════════
   WISHLIST ACTION — JSON endpoint (AJAX only)
   Mirrors cart_action.php / compare_action.php.

   - Logged-in users: wishlist stored in DB (`wishlist` table)
   - Guests: wishlist stored in $_SESSION['wishlist']

   Actions: toggle | remove | clear

   NOTE: This file previously contained a full copy of the
   wishlist.php PAGE markup instead of this JSON handler —
   that's why toggling/clearing from the client always failed
   ("Something went wrong"): main.js expects JSON back
   (success / action / wishlist_count), but was getting an
   entire HTML page instead, so response.json() always threw.
══════════════════════════════════════════ */

$user_id    = $_SESSION['user']['id'] ?? null;
$action     = $_POST['action']     ?? '';
$product_id = (int)($_POST['product_id'] ?? 0);

if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

/* ── Helper: current wishlist item count ── */
function wishlist_count(PDO $conn, ?int $user_id): int {
    if ($user_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    }
    return count($_SESSION['wishlist'] ?? []);
}

switch ($action) {

    /* ── TOGGLE ── */
    case 'toggle':
        if ($product_id < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit;
        }

        // Make sure the product actually exists/active
        $chk = $conn->prepare("SELECT id FROM products WHERE id = ? AND status = 'active' LIMIT 1");
        $chk->execute([$product_id]);
        if (!$chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }

        if ($user_id) {
            $exists_stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $exists_stmt->execute([$user_id, $product_id]);
            $exists = $exists_stmt->fetch();

            if ($exists) {
                $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")
                     ->execute([$user_id, $product_id]);
                $added = false;
            } else {
                $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)")
                     ->execute([$user_id, $product_id]);
                $added = true;
            }
        } else {
            $in_list = in_array($product_id, $_SESSION['wishlist'], true);
            if ($in_list) {
                $_SESSION['wishlist'] = array_values(array_filter(
                    $_SESSION['wishlist'],
                    fn($id) => $id !== $product_id
                ));
                $added = false;
            } else {
                $_SESSION['wishlist'][] = $product_id;
                $added = true;
            }
        }

        echo json_encode([
            'success'        => true,
            'action'         => $added ? 'added' : 'removed',
            'in_wishlist'    => $added,
            'wishlist_count' => wishlist_count($conn, $user_id),
        ]);
        break;

    /* ── REMOVE ── */
    case 'remove':
        if ($product_id < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit;
        }

        if ($user_id) {
            $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")
                 ->execute([$user_id, $product_id]);
        } else {
            $_SESSION['wishlist'] = array_values(array_filter(
                $_SESSION['wishlist'],
                fn($id) => $id !== $product_id
            ));
        }

        echo json_encode([
            'success'        => true,
            'wishlist_count' => wishlist_count($conn, $user_id),
        ]);
        break;

    /* ── CLEAR ── */
    case 'clear':
        if ($user_id) {
            $conn->prepare("DELETE FROM wishlist WHERE user_id = ?")->execute([$user_id]);
        } else {
            $_SESSION['wishlist'] = [];
        }

        echo json_encode([
            'success'        => true,
            'wishlist_count' => 0,
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}