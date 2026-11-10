<?php
/* ══════════════════════════════════════════
   REVIEW ACTION — session-based, logged-in users only
   Save as: C:\xampp\htdocs\LectroHub\customer\review_action.php

   Actions: submit | delete
══════════════════════════════════════════ */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";
require_once "../lectrohub_db/review_helper.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Please sign in to leave a review.']);
    exit;
}

$user_id = (int)$_SESSION['user']['id'];
$action  = $_POST['action'] ?? '';

switch ($action) {

    /* ── SUBMIT ── */
    case 'submit':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $rating     = (int)($_POST['rating'] ?? 0);
        $comment    = trim($_POST['comment'] ?? '');

        if ($product_id < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid product.']);
            exit;
        }
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Please choose a rating from 1 to 5 stars.']);
            exit;
        }
        if (mb_strlen($comment) > 2000) {
            echo json_encode(['success' => false, 'message' => 'Review is too long.']);
            exit;
        }

        // Verified-purchase check happens server-side — never trust the client.
        // An optional order_id may be posted (e.g. from orders.php, which already
        // knows which order) but it's re-verified here regardless.
        $posted_order_id   = (int)($_POST['order_id'] ?? 0);
        $eligible_order_id = get_reviewable_order_id($conn, $user_id, $product_id);

        if (!$eligible_order_id) {
            echo json_encode(['success' => false, 'message' => 'You can only review products from a delivered order, and only once per order.']);
            exit;
        }

        // If the caller specified an order_id, make sure it's actually the
        // eligible one (defends against reusing an old delivered order id
        // for a different purchase of the same product).
        if ($posted_order_id > 0 && $posted_order_id !== $eligible_order_id) {
            $check = $conn->prepare("
                SELECT o.id FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.id
                LEFT JOIN reviews r ON r.order_id = o.id AND r.product_id = oi.product_id AND r.user_id = o.user_id
                WHERE o.id = ? AND o.user_id = ? AND oi.product_id = ? AND o.status = 'Delivered' AND r.id IS NULL
                LIMIT 1
            ");
            $check->execute([$posted_order_id, $user_id, $product_id]);
            $confirmed = $check->fetchColumn();
            if ($confirmed) $eligible_order_id = (int)$confirmed;
        }

        try {
            // title stays NULL — the UI only collects a star rating + free-text
            // body. status is 'approved' immediately (public right away, per
            // policy); admins can still delete a review afterward.
            $ins = $conn->prepare("
                INSERT INTO reviews (product_id, user_id, order_id, rating, title, body, status)
                VALUES (?, ?, ?, ?, NULL, ?, 'approved')
            ");
            $ins->execute([$product_id, $user_id, $eligible_order_id, $rating, $comment !== '' ? $comment : null]);

            $summary = get_product_rating_summary($conn, $product_id);

            echo json_encode([
                'success' => true,
                'message' => 'Thanks for your review!',
                'review'  => [
                    'id'      => (int)$conn->lastInsertId(),
                    'rating'  => $rating,
                    'comment' => $comment,
                    'name'    => $_SESSION['user']['full_name'] ?? 'You',
                ],
                'summary' => $summary,
            ]);
        } catch (PDOException $e) {
            // Most likely the unique-key guard (already reviewed this order)
            echo json_encode(['success' => false, 'message' => 'You\'ve already reviewed this order.']);
        }
        break;

    /* ── DELETE ── */
    case 'delete':
        $review_id = (int)($_POST['review_id'] ?? 0);

        $stmt = $conn->prepare("SELECT product_id FROM reviews WHERE id = ? AND user_id = ?");
        $stmt->execute([$review_id, $user_id]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$review) {
            echo json_encode(['success' => false, 'message' => 'Review not found.']);
            exit;
        }

        $conn->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?")->execute([$review_id, $user_id]);
        $summary = get_product_rating_summary($conn, (int)$review['product_id']);

        echo json_encode(['success' => true, 'message' => 'Review removed.', 'summary' => $summary]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}