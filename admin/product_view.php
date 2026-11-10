<?php

require_once "auth_check.php";

require_once "../lectrohub_db/connection.php";

/* ══════════════════════════════════════════
   GUARD — require a valid ?id=
══════════════════════════════════════════ */
$product_id = (int)($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header('Location: products.php');
    exit;
}

/* ══════════════════════════════════════════
   LOAD PRODUCT + CATEGORY NAME
══════════════════════════════════════════ */
$product = null;
$errors  = [];

try {
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.product_name,
            b.brand_name AS brand,
            p.sku,
            p.description,
            p.price,
            p.stock,
            p.sold,
            p.image,
            p.featured,
            p.status,
            c.category_name AS category
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

if (!$product && empty($errors)) {
    header('Location: products.php');
    exit;
}

$page_title = 'View Product — ' . htmlspecialchars($product['product_name'] ?? '') . ' — LectroHub Admin';

/* ══════════════════════════════════════════
   REVENUE & ORDER STATS FOR THIS PRODUCT
══════════════════════════════════════════ */
$product_revenue = round($product['sold'] * $product['price'], 2);

// Orders that contain this product
try {
    $order_stats = $conn->prepare("
        SELECT
            COUNT(DISTINCT o.id)                          AS order_count,
            COALESCE(SUM(oi.quantity), 0)                 AS units_in_orders,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS order_revenue,
            MAX(o.created_at)                             AS last_order_at
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE oi.product_name = ?
    ");
    $order_stats->execute([$product['product_name']]);
    $ostats = $order_stats->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ostats = ['order_count' => 0, 'units_in_orders' => 0, 'order_revenue' => 0, 'last_order_at' => null];
}

// Recent orders containing this product
try {
    $recent_orders_stmt = $conn->prepare("
        SELECT
            o.id,
            o.status,
            o.payment_status,
            o.created_at,
            u.full_name  AS customer,
            oi.quantity,
            oi.unit_price
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN users u ON u.id = o.user_id
        WHERE oi.product_name = ?
        ORDER BY o.created_at DESC
        LIMIT 8
    ");
    $recent_orders_stmt->execute([$product['product_name']]);
    $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_orders = [];
}

/* ══════════════════════════════════════════
   DERIVED DISPLAY VALUES
══════════════════════════════════════════ */
$imgFile     = !empty($product['image']) ? $product['image'] : 'default-product.webp';
$is_featured = (int)$product['featured'] === 1;

$disp_status = ($product['status'] === 'active' && $product['stock'] == 0)
    ? 'Out of Stock'
    : ucfirst($product['status']);

$status_cls = match ($disp_status) {
    'Active', 'active'   => 'b-green',
    'Out of Stock'       => 'b-red',
    'Draft', 'draft'     => 'b-grey',
    'Inactive','inactive'=> 'b-grey',
    default              => 'b-grey'
};

// Normalise to display-friendly
$disp_status_label = match ($product['status']) {
    'active'   => 'Active',
    'draft'    => 'Draft',
    'inactive' => 'Inactive',
    default    => ucfirst($product['status'])
};
if ($product['stock'] == 0 && $product['status'] === 'active') {
    $disp_status_label = 'Out of Stock';
    $status_cls        = 'b-red';
} elseif ($product['status'] === 'active') {
    $status_cls = 'b-green';
}

$stock_color = $product['stock'] == 0
    ? 'var(--red)'
    : ($product['stock'] < 20 ? 'var(--amber)' : 'var(--green)');

// Stock bar — cap at 100 for display
$stock_bar_pct = min(100, $product['stock'] > 0 ? round(($product['stock'] / max($product['stock'] + $product['sold'], 1)) * 100) : 0);

?>

<?php require_once "head.php"; ?>

<div class="sb-mobile-ovl" id="sbOvl" onclick="closeSb()"></div>

<div class="shell">

    <?php require_once "sidebar.php"; ?>
    <?php require_once "navbar.php"; ?>

    <main class="main">

        <!-- ── Page Header ── -->
        <div class="page-hd au">
            <div>
                <div class="ph-eyebrow">Catalogue</div>
                <h1 class="ph-title">Product Detail</h1>
                <p class="ph-sub">
                    Viewing <strong style="color:var(--txt)"><?= htmlspecialchars($product['product_name']) ?></strong>
                    &nbsp;·&nbsp;
                    <span class="td-mono" style="font-size:12px;color:var(--dim)"><?= htmlspecialchars($product['sku']) ?></span>
                </p>
            </div>
            <div class="ph-actions">
                <a href="products.php" class="btn btn-secondary btn-sm">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Back
                </a>
                <a href="products_edit.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit Product
                </a>
            </div>
        </div>

        <!-- ── TOP LAYOUT: image + info + sidebar ── -->
        <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;margin-bottom:20px">

            <!-- LEFT: image + details -->
            <div style="display:flex;flex-direction:column;gap:20px">

                <!-- Product Hero Card -->
                <div class="adm-card au">
                    <div class="card-body" style="display:grid;grid-template-columns:260px 1fr;gap:28px;align-items:start">

                        <!-- Image -->
                        <div style="position:relative">
                            <div style="aspect-ratio:1/1;border-radius:calc(var(--r) - 2px);overflow:hidden;background:var(--card3);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center">
                                <img
                                    src="img/<?= htmlspecialchars($imgFile) ?>"
                                    alt="<?= htmlspecialchars($product['product_name']) ?>"
                                    style="width:100%;height:100%;object-fit:contain;padding:16px;box-sizing:border-box"
                                    onerror="this.src='img/default-product.webp'">
                            </div>
                            <?php if ($is_featured): ?>
                                <div style="position:absolute;top:10px;left:10px">
                                    <span class="badge b-purple">⭐ Featured</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Core Info -->
                        <div style="display:flex;flex-direction:column;gap:16px;padding-top:4px">

                            <div>
                                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:4px"><?= htmlspecialchars($product['category'] ?? '—') ?></div>
                                <h2 style="font-family:var(--fd);font-size:20px;font-weight:800;color:var(--txt);margin:0 0 6px"><?= htmlspecialchars($product['product_name']) ?></h2>
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                    <span class="badge b-grey"><?= htmlspecialchars($product['brand']) ?></span>
                                    <span class="badge <?= $status_cls ?>"><?= $disp_status_label ?></span>
                                </div>
                            </div>

                            <!-- Price -->
                            <div>
                                <div style="font-size:11px;color:var(--muted);margin-bottom:2px">Retail Price</div>
                                <div style="font-family:var(--fm);font-size:28px;font-weight:800;color:var(--ac);line-height:1">$<?= number_format($product['price'], 2) ?></div>
                            </div>

                            <!-- SKU -->
                            <div style="display:flex;flex-direction:column;gap:6px">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="font-size:11px;color:var(--muted);min-width:70px">SKU</span>
                                    <span class="td-mono" style="font-size:12px;color:var(--dim);background:var(--card3);padding:3px 9px;border-radius:5px;border:1px solid var(--bd)"><?= htmlspecialchars($product['sku']) ?></span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="font-size:11px;color:var(--muted);min-width:70px">Product ID</span>
                                    <span class="td-mono" style="font-size:12px;color:var(--dim)">#<?= $product['id'] ?></span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="font-size:11px;color:var(--muted);min-width:70px">Featured</span>
                                    <span style="font-size:12px;color:<?= $is_featured ? 'var(--purple)' : 'var(--dim)' ?>"><?= $is_featured ? '⭐ Yes' : '— No' ?></span>
                                </div>
                            </div>

                            <!-- Description -->
                            <?php if (!empty($product['description'])): ?>
                                <div>
                                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px">Description</div>
                                    <p style="font-size:13px;color:var(--muted);line-height:1.65;margin:0"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <!-- Stat Strip -->
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
                    <?php
                    $pv_stats = [
                        ['icon' => '💰', 'label' => 'Est. Revenue',  'value' => '$' . number_format($product_revenue, 0),        'color' => 'var(--green)'],
                        ['icon' => '📦', 'label' => 'Units Sold',    'value' => number_format($product['sold']),                  'color' => 'var(--ac)'],
                        ['icon' => '🏪', 'label' => 'In Stock',      'value' => number_format($product['stock']),                 'color' => $stock_color],
                        ['icon' => '🛒', 'label' => 'In Orders',     'value' => number_format($ostats['order_count']),            'color' => 'var(--purple)'],
                    ];
                    foreach ($pv_stats as $ps):
                    ?>
                        <div class="adm-card au" style="padding:16px 18px">
                            <div style="font-size:20px;margin-bottom:8px"><?= $ps['icon'] ?></div>
                            <div style="font-family:var(--fd);font-size:22px;font-weight:800;color:<?= $ps['color'] ?>;line-height:1"><?= $ps['value'] ?></div>
                            <div style="font-size:10px;color:var(--muted);margin-top:4px"><?= $ps['label'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Stock & Inventory Card -->
                <div class="adm-card au">
                    <div class="card-hd">
                        <div>
                            <div class="card-title">Inventory</div>
                            <div class="card-sub">Current stock levels and availability</div>
                        </div>
                        <a href="products_edit.php?id=<?= $product['id'] ?>" class="btn btn-secondary btn-sm">Adjust Stock</a>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:16px">

                        <!-- Stock bar -->
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                                <span style="font-size:12px;color:var(--muted)">Stock Level</span>
                                <span style="font-family:var(--fm);font-size:13px;font-weight:700;color:<?= $stock_color ?>"><?= number_format($product['stock']) ?> units remaining</span>
                            </div>
                            <div style="height:8px;background:var(--card3);border-radius:4px;overflow:hidden;border:1px solid var(--bd)">
                                <div style="height:100%;width:<?= $stock_bar_pct ?>%;background:linear-gradient(90deg,<?= $stock_color ?>,<?= $stock_color ?>88);border-radius:4px;transition:width .6s ease"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-top:5px">
                                <span style="font-size:10px;color:var(--dim)">0</span>
                                <span style="font-size:10px;color:var(--dim)"><?= number_format($product['stock'] + $product['sold']) ?> total ever stocked</span>
                            </div>
                        </div>

                        <!-- Inventory meta row -->
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
                            <?php
                            $inv_meta = [
                                ['label' => 'Current Stock', 'value' => number_format($product['stock']),  'color' => $stock_color],
                                ['label' => 'Units Sold',    'value' => number_format($product['sold']),   'color' => 'var(--ac)'],
                                ['label' => 'Status',        'value' => $disp_status_label,                'color' => $product['stock'] == 0 ? 'var(--red)' : 'var(--green)'],
                            ];
                            foreach ($inv_meta as $m):
                            ?>
                                <div style="padding:12px 14px;background:var(--card3);border:1px solid var(--bd);border-radius:calc(var(--r) - 4px)">
                                    <div style="font-size:11px;color:var(--muted);margin-bottom:4px"><?= $m['label'] ?></div>
                                    <div style="font-family:var(--fm);font-size:16px;font-weight:700;color:<?= $m['color'] ?>"><?= $m['value'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($product['stock'] < 20 && $product['stock'] > 0): ?>
                            <div style="display:flex;align-items:center;gap:10px;padding:11px 14px;background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.25);border-radius:calc(var(--r) - 4px)">
                                <span style="font-size:16px">⚠️</span>
                                <span style="font-size:12px;color:var(--amber);font-weight:600">Low stock — consider restocking soon (threshold: 20 units)</span>
                            </div>
                        <?php elseif ($product['stock'] == 0): ?>
                            <div style="display:flex;align-items:center;gap:10px;padding:11px 14px;background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.25);border-radius:calc(var(--r) - 4px)">
                                <span style="font-size:16px">🚫</span>
                                <span style="font-size:12px;color:var(--red);font-weight:600">Out of stock — this product is currently unavailable in the store</span>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- Recent Orders Table -->
                <?php if (!empty($recent_orders)): ?>
                <div class="adm-card au">
                    <div class="card-hd">
                        <div>
                            <div class="card-title">Recent Orders</div>
                            <div class="card-sub">Last <?= count($recent_orders) ?> orders containing this product</div>
                        </div>
                        <a href="orders.php" class="btn btn-secondary btn-sm">All Orders</a>
                    </div>
                    <div class="card-body p0">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $ro):
                                    $ro_ref     = '#ORD-' . str_pad($ro['id'], 4, '0', STR_PAD_LEFT);
                                    $ro_scls    = match ($ro['status']) {
                                        'Delivered'  => 'b-green',
                                        'Shipped'    => 'b-cyan',
                                        'Processing' => 'b-amber',
                                        'Cancelled'  => 'b-red',
                                        default      => 'b-grey'
                                    };
                                    $ro_pcls = match ($ro['payment_status']) {
                                        'paid'    => 'b-green',
                                        'pending' => 'b-amber',
                                        'failed'  => 'b-red',
                                        default   => 'b-grey'
                                    };
                                ?>
                                    <tr>
                                        <td><span class="td-mono" style="color:var(--ac);font-weight:600"><?= $ro_ref ?></span></td>
                                        <td><span style="font-size:13px;font-weight:500;color:var(--txt)"><?= htmlspecialchars($ro['customer'] ?? 'Guest') ?></span></td>
                                        <td><span class="td-mono" style="color:var(--txt);font-weight:600">×<?= (int)$ro['quantity'] ?></span></td>
                                        <td><span class="td-mono" style="color:var(--muted)">$<?= number_format($ro['unit_price'], 2) ?></span></td>
                                        <td><span class="badge <?= $ro_pcls ?>"><?= ucfirst($ro['payment_status']) ?></span></td>
                                        <td><span class="badge <?= $ro_scls ?>"><?= htmlspecialchars($ro['status']) ?></span></td>
                                        <td><span style="font-size:12px;color:var(--muted)"><?= date('M j, Y', strtotime($ro['created_at'])) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="adm-card au">
                    <div class="card-hd">
                        <div class="card-title">Recent Orders</div>
                    </div>
                    <div style="padding:40px 24px;text-align:center">
                        <div style="font-size:32px;margin-bottom:10px">🛒</div>
                        <div style="font-family:var(--fd);font-size:13px;font-weight:700;color:var(--txt)">No orders yet</div>
                        <div style="font-size:12px;color:var(--muted);margin-top:4px">This product hasn't been ordered yet.</div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /left -->

            <!-- RIGHT SIDEBAR -->
            <div style="display:flex;flex-direction:column;gap:20px">

                <!-- Quick Actions -->
                <div class="adm-card au">
                    <div class="card-hd">
                        <div class="card-title">Quick Actions</div>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
                        <a href="products_edit.php?id=<?= $product['id'] ?>" class="btn btn-primary" style="width:100%;justify-content:center">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Edit Product
                        </a>
                        <button class="btn btn-secondary" style="width:100%;justify-content:center" onclick="showToast('Exported','📤')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Export Data
                        </button>
                        <hr style="border:none;border-top:1px solid var(--bd);margin:0">
                        <button class="btn btn-secondary" style="width:100%;justify-content:center;color:var(--red);border-color:rgba(239,68,68,.3)" onclick="openDeleteModal()">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                <path d="M10 11v6M14 11v6"/>
                            </svg>
                            Delete Product
                        </button>
                    </div>
                </div>

                <!-- Product Details -->
                <div class="adm-card au">
                    <div class="card-hd">
                        <div class="card-title">Details</div>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:0">
                        <?php
                        $details = [
                            ['label' => 'Category',  'value' => $product['category'] ?? '—'],
                            ['label' => 'Brand',     'value' => $product['brand']],
                            ['label' => 'SKU',       'value' => $product['sku'],       'mono' => true],
                            ['label' => 'Price',     'value' => '$' . number_format($product['price'], 2), 'mono' => true],
                            ['label' => 'Status',    'value' => $disp_status_label],
                            ['label' => 'Featured',  'value' => $is_featured ? '⭐ Yes' : 'No'],
                        ];
                        foreach ($details as $i => $d):
                        ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;<?= $i < count($details)-1 ? 'border-bottom:1px solid var(--bd)' : '' ?>">
                                <span style="font-size:11px;color:var(--muted)"><?= $d['label'] ?></span>
                                <?php if (!empty($d['mono'])): ?>
                                    <span class="td-mono" style="font-size:12px;color:var(--dim)"><?= htmlspecialchars($d['value']) ?></span>
                                <?php else: ?>
                                    <span style="font-size:12px;font-weight:600;color:var(--txt)"><?= htmlspecialchars($d['value']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sales Performance -->
                <div class="adm-card au">
                    <div class="card-hd">
                        <div class="card-title">Sales Performance</div>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:0">
                        <?php
                        $perf = [
                            ['label' => 'Units Sold',       'value' => number_format($product['sold']),                          'color' => 'var(--ac)'],
                            ['label' => 'Est. Revenue',     'value' => '$' . number_format($product_revenue, 0),                 'color' => 'var(--green)'],
                            ['label' => 'Order Count',      'value' => number_format($ostats['order_count']),                    'color' => 'var(--purple)'],
                            ['label' => 'Revenue (Orders)', 'value' => '$' . number_format($ostats['order_revenue'], 0),         'color' => 'var(--green)'],
                            ['label' => 'Last Ordered',     'value' => $ostats['last_order_at']
                                ? date('M j, Y', strtotime($ostats['last_order_at']))
                                : 'Never',                                                                                       'color' => 'var(--muted)'],
                        ];
                        foreach ($perf as $i => $pf):
                        ?>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;<?= $i < count($perf)-1 ? 'border-bottom:1px solid var(--bd)' : '' ?>">
                                <span style="font-size:11px;color:var(--muted)"><?= $pf['label'] ?></span>
                                <span class="td-mono" style="font-size:13px;font-weight:700;color:<?= $pf['color'] ?>"><?= $pf['value'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sell-through rate -->
                <?php
                $total_ever = $product['stock'] + $product['sold'];
                $sellthrough = $total_ever > 0 ? round(($product['sold'] / $total_ever) * 100) : 0;
                $st_color = $sellthrough >= 70 ? 'var(--green)' : ($sellthrough >= 40 ? 'var(--amber)' : 'var(--red)');
                ?>
                <div class="adm-card au">
                    <div class="card-hd">
                        <div class="card-title">Sell-through Rate</div>
                    </div>
                    <div class="card-body">
                        <div style="text-align:center;margin-bottom:14px">
                            <div style="font-family:var(--fd);font-size:40px;font-weight:800;color:<?= $st_color ?>;line-height:1"><?= $sellthrough ?>%</div>
                            <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= number_format($product['sold']) ?> sold of <?= number_format($total_ever) ?> total stocked</div>
                        </div>
                        <div style="height:8px;background:var(--card3);border-radius:4px;overflow:hidden;border:1px solid var(--bd)">
                            <div style="height:100%;width:<?= $sellthrough ?>%;background:linear-gradient(90deg,<?= $st_color ?>,<?= $st_color ?>88);border-radius:4px"></div>
                        </div>
                        <div style="font-size:11px;color:var(--dim);margin-top:8px;text-align:center">
                            <?php if ($sellthrough >= 70): ?>
                                🔥 Strong performance
                            <?php elseif ($sellthrough >= 40): ?>
                                📊 Moderate performance
                            <?php else: ?>
                                📉 Below average — consider a promotion
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div><!-- /right sidebar -->

        </div><!-- /top grid -->

        <!-- ── Delete Confirm Modal ── -->
        <div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
            <div style="background:var(--card);border:1px solid rgba(239,68,68,.3);border-radius:var(--r);padding:28px;max-width:380px;width:90%;box-shadow:var(--sh2)">
                <div style="font-size:32px;margin-bottom:12px;text-align:center">🗑️</div>
                <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:8px">Delete Product?</div>
                <div style="font-size:13px;color:var(--muted);text-align:center;margin-bottom:4px">
                    <strong style="color:var(--txt)"><?= htmlspecialchars($product['product_name']) ?></strong>
                </div>
                <div style="font-size:12px;color:var(--dim);text-align:center;margin-bottom:24px">This action cannot be undone.</div>
                <div style="display:flex;gap:10px">
                    <button class="btn btn-secondary" style="flex:1" onclick="closeDeleteModal()">Cancel</button>
                    <form method="POST" action="products_action.php" style="flex:1">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                        <button type="submit" class="btn" style="width:100%;background:var(--red);color:#fff;justify-content:center">Delete</button>
                    </form>
                </div>
            </div>
        </div>

    </main>

</div><!-- /shell -->

<?php require_once "footer.php"; ?>

<!-- Toast -->
<div class="toast" id="toast"><span id="ti" style="font-size:17px">✓</span><span id="tm"></span></div>

<script>
    /* ── Sidebar ── */
    function toggleSb() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sbOvl').classList.toggle('show');
    }
    function closeSb() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sbOvl').classList.remove('show');
    }
    function toggleDrop(id) {
        document.querySelectorAll('.nav-dropdown.open').forEach(d => { if (d.id !== id) d.classList.remove('open'); });
        document.getElementById(id).classList.toggle('open');
    }
    document.addEventListener('click', e => {
        if (!e.target.closest('.nav-dropdown'))
            document.querySelectorAll('.nav-dropdown.open').forEach(d => d.classList.remove('open'));
    });

    /* ── Toast ── */
    let toastTimer;
    function showToast(msg, icon = '✓') {
        const t = document.getElementById('toast');
        document.getElementById('ti').textContent = icon;
        document.getElementById('tm').textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
    }

    /* ── Delete Modal ── */
    function openDeleteModal() {
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
    document.getElementById('deleteModal').addEventListener('click', e => {
        if (e.target === e.currentTarget) closeDeleteModal();
    });

    /* ── ESC ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSb(); closeDeleteModal(); }
    });
</script>

</body>
</html>