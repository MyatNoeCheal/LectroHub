<?php
/* ══════════════════════════════════════════
   REVIEW HELPER FUNCTIONS
   Save as: C:\xampp\htdocs\LectroHub\lectrohub_db\review_helper.php
   (same folder as shipping_helper.php / mailer.php)

   Matches your existing `reviews` table:
     id, product_id, user_id, order_id (new), rating, title, body,
     status ('pending'|'approved'|'rejected'), created_at
   New reviews are always inserted with status = 'approved' (per your
   "public immediately, admin can delete" policy) — the pending/rejected
   states stay available for a future moderation flow if you want one.
══════════════════════════════════════════ */

/**
 * Returns the eligible order_id the given user can leave a NEW review for,
 * on the given product — i.e. a Delivered order containing that product
 * which does not already have a review from this user for that order.
 * Returns null if the user hasn't purchased/received it, or already
 * reviewed every delivered order containing it.
 */
function get_reviewable_order_id(PDO $conn, int $user_id, int $product_id): ?int
{
    $stmt = $conn->prepare("
        SELECT o.id
        FROM orders o
        INNER JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN reviews r ON r.order_id = o.id AND r.product_id = oi.product_id AND r.user_id = o.user_id
        WHERE o.user_id = ?
          AND oi.product_id = ?
          AND o.status = 'Delivered'
          AND r.id IS NULL
        ORDER BY o.id DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $product_id]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/**
 * All approved reviews for a product, newest first, with reviewer name.
 */
function get_product_reviews(PDO $conn, int $product_id): array
{
    $stmt = $conn->prepare("
        SELECT r.id, r.rating, r.title, r.body, r.created_at, r.user_id,
               u.full_name
        FROM reviews r
        INNER JOIN users u ON u.id = r.user_id
        WHERE r.product_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$product_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Average rating + count (approved only) for a product.
 * Use on product cards / product.php header.
 */
function get_product_rating_summary(PDO $conn, int $product_id): array
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS avg_rating
        FROM reviews WHERE product_id = ? AND status = 'approved'
    ");
    $stmt->execute([$product_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'count' => (int)$row['review_count'],
        'avg'   => round((float)$row['avg_rating'], 1),
    ];
}

/**
 * Every product in a given order that the user is still eligible to review
 * (Delivered, not yet reviewed for that order). Used on orders.php to show
 * "Write a Review" buttons per line item.
 * Returns rows: product_id, product_name, img
 */
function get_reviewable_products_for_order(PDO $conn, int $order_id, int $user_id): array
{
    $stmt = $conn->prepare("
        SELECT oi.product_id, oi.product_name, p.image AS img
        FROM order_items oi
        INNER JOIN orders o   ON o.id = oi.order_id
        INNER JOIN products p ON p.id = oi.product_id
        LEFT JOIN reviews r   ON r.order_id = o.id AND r.product_id = oi.product_id AND r.user_id = o.user_id
        WHERE oi.order_id = ?
          AND o.user_id = ?
          AND o.status = 'Delivered'
          AND r.id IS NULL
    ");
    $stmt->execute([$order_id, $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}