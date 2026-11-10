<?php
require_once "auth_check.php";
require_once "../lectrohub_db/connection.php";
require_once "../lectrohub_db/review_helper.php";

$user_id   = (int)$_SESSION['user']['id'];
$user_name = $_SESSION['user']['full_name'] ?? '';

/* ══════════════════════════════════════════
   FILTERS
══════════════════════════════════════════ */
$filter_status = trim($_GET['status'] ?? '');
$search_q      = trim($_GET['q']      ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 8;

$valid_statuses = ['Processing', 'Shipped', 'Delivered', 'Cancelled'];

/* ══════════════════════════════════════════
   BUILD QUERY
══════════════════════════════════════════ */
$where  = ['o.user_id = :uid'];
$params = [':uid' => $user_id];

if ($filter_status !== '' && in_array($filter_status, $valid_statuses)) {
    $where[]           = 'o.status = :status';
    $params[':status'] = $filter_status;
}

if ($search_q !== '') {
    $where[]       = '(o.id LIKE :q OR oi.product_name LIKE :q2)';
    $params[':q']  = "%$search_q%";
    $params[':q2'] = "%$search_q%";
}

$where_sql = implode(' AND ', $where);

/* ── Total count ── */
$count_sql = "
    SELECT COUNT(DISTINCT o.id)
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE $where_sql
";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_orders = (int)$count_stmt->fetchColumn();
$total_pages  = max(1, (int)ceil($total_orders / $per_page));
$page         = min($page, $total_pages);
$offset       = ($page - 1) * $per_page;

/* ── Orders for current page ── */
$orders_stmt = $conn->prepare("
    SELECT DISTINCT
        o.id,
        o.status,
        o.subtotal,
        o.shipping_fee,
        o.discount,
        o.total,
        o.payment_method,
        o.payment_status,
        o.created_at,
        a.city,
        a.state
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN addresses   a  ON a.id = o.address_id
    WHERE $where_sql
    ORDER BY o.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$orders_stmt->execute($params);
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Fetch items for each order ── */
$order_ids = array_column($orders, 'id');
$items_map = [];

if (!empty($order_ids)) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $items_stmt = $conn->prepare("
        SELECT
            oi.order_id,
            oi.product_name,
            oi.product_sku,
            oi.unit_price,
            oi.quantity,
            oi.line_total,
            p.image AS img,
            p.id    AS product_id
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id IN ($in)
        ORDER BY oi.id ASC
    ");
    $items_stmt->execute($order_ids);
    foreach ($items_stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $items_map[$item['order_id']][] = $item;
    }
}

/* ── Reviewable products per order (Delivered orders only) ──
   Used to show "Write a Review" vs "✓ Reviewed" per line item below. */
$reviewable_by_order = [];
foreach ($orders as $o) {
    if ($o['status'] === 'Delivered') {
        $reviewable = get_reviewable_products_for_order($conn, (int)$o['id'], $user_id);
        $reviewable_by_order[$o['id']] = array_column($reviewable, 'product_id', 'product_id');
    }
}

/* ── Status counts for filter tabs ── */
$status_counts = [];
$sc_stmt = $conn->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM orders
    WHERE user_id = ?
    GROUP BY status
");
$sc_stmt->execute([$user_id]);
foreach ($sc_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $status_counts[$row['status']] = (int)$row['cnt'];
}
$total_count = array_sum($status_counts);

/* ══════════════════════════════════════════
   HELPERS
══════════════════════════════════════════ */
function order_product_img(?string $img): string
{
    if ($img && $img !== '') {
        return "/LectroHub/admin/img/" . htmlspecialchars($img);
    }
    return "https://placehold.co/56x56/070a10/00e5ff?text=?";
}

function status_class(string $status): string
{
    return match ($status) {
        'Processing' => 'b-purple',
        'Shipped'    => 'b-blue',
        'Delivered'  => 'b-green',
        'Cancelled'  => 'b-red',
        default      => 'b-grey',
    };
}

function status_icon(string $status): string
{
    return match ($status) {
        'Processing' => '⏳',
        'Shipped'    => '🚚',
        'Delivered'  => '✅',
        'Cancelled'  => '❌',
        default      => '📦',
    };
}

function payment_label(string $method): string
{
    return match ($method) {
        'card'          => '💳 Card',
        'paypal'        => '🅿️ PayPal',
        'kbzpay'        => '📱 KBZPay',
        'wavepay'       => '🌊 WavePay',
        'bank_transfer' => '🏦 Bank Transfer',
        default         => ucfirst($method),
    };
}
?>
<?php require_once "head.php" ?>
<?php require_once "navbar.php" ?>

<body>
    <br>
    <main class="orders-page">

        <!-- ── BREADCRUMB ── -->
        <div class="orders-breadcrumb">
            <a href="index.php">Home</a>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="m9 18 6-6-6-6" />
            </svg>
            <span>My Orders</span>
        </div>

        <!-- ── PAGE HEADER ── -->
        <div class="orders-hd">
            <div>
                <h1 class="orders-title">My Orders</h1>
                <p class="orders-sub"><?= number_format($total_count) ?> order<?= $total_count !== 1 ? 's' : '' ?> placed</p>
            </div>
            <a href="products.php" class="btn-shop-more">Continue Shopping →</a>
        </div>

        <!-- ── FILTER TABS ── -->
        <div class="orders-tabs">
            <a href="orders.php" class="orders-tab <?= $filter_status === '' ? 'active' : '' ?>">
                All
                <span class="tab-count"><?= number_format($total_count) ?></span>
            </a>
            <?php foreach ($valid_statuses as $s): ?>
            <a href="orders.php?status=<?= urlencode($s) ?><?= $search_q ? '&q=' . urlencode($search_q) : '' ?>"
                class="orders-tab <?= $filter_status === $s ? 'active' : '' ?>">
                <?= status_icon($s) ?> <?= $s ?>
                <?php if (!empty($status_counts[$s])): ?>
                <span class="tab-count"><?= $status_counts[$s] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ── SEARCH BAR ── -->
        <div class="orders-search-wrap">
            <form method="GET" action="orders.php">
                <?php if ($filter_status): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <?php endif; ?>
                <div class="orders-search">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.35-4.35" />
                    </svg>
                    <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>"
                        placeholder="Search by order # or product name…" class="orders-search-input">
                    <?php if ($search_q): ?>
                    <a href="orders.php<?= $filter_status ? '?status=' . urlencode($filter_status) : '' ?>"
                        class="search-clear">×</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ══════════════════════════════════════════
             ORDERS LIST
        ══════════════════════════════════════════ -->
        <?php if (empty($orders)): ?>

        <div class="orders-empty">
            <div class="orders-empty-icon">📦</div>
            <div class="orders-empty-title">No orders found</div>
            <p class="orders-empty-sub">
                <?= $search_q || $filter_status
                    ? 'Try adjusting your search or filter.'
                    : "You haven't placed any orders yet." ?>
            </p>
            <?php if ($search_q || $filter_status): ?>
            <a href="orders.php" class="btn-outline-sm" style="margin-top:16px;display:inline-block">Clear filters</a>
            <?php else: ?>
            <a href="products.php" class="btn-hero-primary" style="margin-top:20px;display:inline-flex">Start Shopping</a>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <div class="orders-list">
            <?php foreach ($orders as $order):
                $items    = $items_map[$order['id']] ?? [];
                $item_cnt = array_sum(array_column($items, 'quantity'));
                $date     = date('d M Y', strtotime($order['created_at']));
                $time     = date('H:i', strtotime($order['created_at']));
            ?>
            <div class="order-card" id="order<?= $order['id'] ?>">

                <!-- ── ORDER HEADER ── -->
                <div class="oc-header">
                    <div class="oc-header-left">
                        <div class="oc-order-num">
                            <span class="oc-label">Order</span>
                            <span class="oc-id">#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <div class="oc-meta">
                            <span class="oc-date"><?= $date ?></span>
                            <span class="oc-sep">·</span>
                            <span class="oc-time"><?= $time ?></span>
                            <?php if ($order['city']): ?>
                            <span class="oc-sep">·</span>
                            <span class="oc-location">
                                📍 <?= htmlspecialchars($order['city']) ?>
                                <?= !empty($order['state']) ? ', ' . htmlspecialchars($order['state']) : '' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="oc-header-right">
                        <span class="badge <?= status_class($order['status']) ?>">
                            <?= status_icon($order['status']) ?> <?= $order['status'] ?>
                        </span>
                        <button class="oc-toggle-btn" onclick="toggleOrder(<?= $order['id'] ?>)"
                            aria-expanded="false" id="toggleBtn<?= $order['id'] ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2.5" class="oc-chevron">
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- ── ORDER PREVIEW (product thumbnails) ── -->
                <div class="oc-preview">
                    <div class="oc-thumbs">
                        <?php foreach (array_slice($items, 0, 4) as $item): ?>
                        <div class="oc-thumb-wrap">
                            <img src="<?= order_product_img($item['img']) ?>"
                                alt="<?= htmlspecialchars($item['product_name']) ?>"
                                title="<?= htmlspecialchars($item['product_name']) ?>" loading="lazy">
                            <?php if ($item['quantity'] > 1): ?>
                            <span class="oc-thumb-qty">×<?= $item['quantity'] ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($items) > 4): ?>
                        <div class="oc-thumb-more">+<?= count($items) - 4 ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="oc-summary-strip">
                        <div class="oc-strip-item">
                            <span class="oc-strip-label">Items</span>
                            <span class="oc-strip-val"><?= $item_cnt ?></span>
                        </div>
                        <div class="oc-strip-item">
                            <span class="oc-strip-label">Payment</span>
                            <span class="oc-strip-val"><?= payment_label($order['payment_method']) ?></span>
                        </div>
                        <div class="oc-strip-item">
                            <span class="oc-strip-label">Total</span>
                            <span class="oc-strip-val oc-total">$<?= number_format($order['total'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- ── ORDER DETAIL (collapsible) ── -->
                <div class="oc-detail" id="orderDetail<?= $order['id'] ?>" style="display:none;">

                    <!-- Items table -->
                    <div class="oc-items-table">
                        <div class="oc-items-hd">
                            <span>Product</span>
                            <span>SKU</span>
                            <span>Unit Price</span>
                            <span>Qty</span>
                            <span>Subtotal</span>
                        </div>

                        <?php foreach ($items as $item):
                            $is_reviewable = $item['product_id'] && isset($reviewable_by_order[$order['id']][$item['product_id']]);
                            $is_reviewed   = $item['product_id'] && $order['status'] === 'Delivered' && !$is_reviewable;
                        ?>
                        <div class="oc-item-row">
                            <div class="oc-item-product">
                                <img src="<?= order_product_img($item['img']) ?>"
                                    alt="<?= htmlspecialchars($item['product_name']) ?>">
                                <div>
                                    <div class="oc-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <?php if ($item['product_id']): ?>
                                    <a href="product.php?id=<?= $item['product_id'] ?>" class="oc-item-link">View product →</a>
                                    <?php endif; ?>
                                    <?php if ($is_reviewable): ?>
                                    <a href="product.php?id=<?= $item['product_id'] ?>#reviews" class="oc-item-link"
                                       style="color:var(--accent,#00e5ff)">⭐ Write a Review</a>
                                    <?php elseif ($is_reviewed): ?>
                                    <span style="font-size:12px;color:#10b981;display:inline-block;margin-top:4px">✓ Reviewed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="oc-item-sku"><?= htmlspecialchars($item['product_sku'] ?? '—') ?></div>
                            <div class="oc-item-price">$<?= number_format($item['unit_price'], 2) ?></div>
                            <div class="oc-item-qty"><?= $item['quantity'] ?></div>
                            <div class="oc-item-total">$<?= number_format($item['line_total'], 2) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Order totals -->
                    <div class="oc-totals">
                        <div class="oc-totals-rows">
                            <div class="oc-tot-row">
                                <span>Subtotal</span>
                                <span>$<?= number_format($order['subtotal'], 2) ?></span>
                            </div>
                            <div class="oc-tot-row">
                                <span>Shipping</span>
                                <span>
                                    <?php if ((float)$order['shipping_fee'] > 0): ?>
                                    $<?= number_format($order['shipping_fee'], 2) ?>
                                    <?php else: ?>
                                    <span class="free-tag">Free</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ((float)$order['discount'] > 0): ?>
                            <div class="oc-tot-row oc-discount">
                                <span>Discount</span>
                                <span>−$<?= number_format($order['discount'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="oc-tot-divider"></div>
                            <div class="oc-tot-row oc-tot-grand">
                                <span>Total</span>
                                <span>$<?= number_format($order['total'], 2) ?></span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="oc-actions">
                            <?php if ($order['status'] === 'Delivered'): ?>
                            <button class="btn-reorder" onclick="reorderItems(<?= $order['id'] ?>)">
                                🔄 Reorder
                            </button>
                            <?php endif; ?>
                      
                        </div>
                    </div>

                </div><!-- /oc-detail -->

            </div><!-- /order-card -->
            <?php endforeach; ?>
        </div>

        <!-- ── PAGINATION ── -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $base = 'orders.php?' .
                ($filter_status ? 'status=' . urlencode($filter_status) . '&' : '') .
                ($search_q      ? 'q=' . urlencode($search_q) . '&' : '');
            ?>

            <?php if ($page > 1): ?>
            <a href="<?= $base ?>page=<?= $page - 1 ?>" class="pg-btn pg-prev">← Prev</a>
            <?php endif; ?>

            <?php
            $pg_start = max(1, $page - 2);
            $pg_end   = min($total_pages, $page + 2);
            if ($pg_start > 1):
            ?>
            <a href="<?= $base ?>page=1" class="pg-btn">1</a>
            <?php if ($pg_start > 2): ?><span class="pg-ellipsis">…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $pg_start; $i <= $pg_end; $i++): ?>
            <a href="<?= $base ?>page=<?= $i ?>" class="pg-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($pg_end < $total_pages): ?>
            <?php if ($pg_end < $total_pages - 1): ?>
            <span class="pg-ellipsis">…</span>
            <?php endif; ?>
            <a href="<?= $base ?>page=<?= $total_pages ?>" class="pg-btn"><?= $total_pages ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
            <a href="<?= $base ?>page=<?= $page + 1 ?>" class="pg-btn pg-next">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>

    </main>

    <?php require_once "footer.php" ?>

    <!-- TOAST -->
    <div class="toast" id="toast">
        <span class="toast-icon" id="toastIcon">✓</span>
        <span id="toastMsg">Done</span>
    </div>

</body>