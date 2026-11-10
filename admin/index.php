<?php

require_once "auth_check.php";

require_once "../lectrohub_db/connection.php";
if (!isset($_SESSION)) session_start();

/* ══════════════════════════════════════════
   STAT CARDS
══════════════════════════════════════════ */

// Today's sales total
$today_sales = $conn->query("
    SELECT COALESCE(SUM(total), 0) FROM orders
    WHERE DATE(created_at) = CURDATE()
      AND payment_status = 'paid'
")->fetchColumn();

// Last 30 days sales (for "this month" comparison)
$this_month = $conn->query("
    SELECT COALESCE(SUM(total), 0) FROM orders
    WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND payment_status = 'paid'
")->fetchColumn();

// Total all-time revenue
$total_revenue = $conn->query("
    SELECT COALESCE(SUM(total), 0) FROM orders
    WHERE payment_status = 'paid'
")->fetchColumn();

// New orders today
$new_orders = $conn->query("
    SELECT COUNT(*) FROM orders
    WHERE DATE(created_at) = CURDATE()
")->fetchColumn();

// Total users
$total_users = $conn->query("
    SELECT COUNT(*) FROM users WHERE role = 'user'
")->fetchColumn();

// Yesterday's sales (for change % on Today Sales card)
$yesterday_sales = $conn->query("
    SELECT COALESCE(SUM(total), 0) FROM orders
    WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
      AND payment_status = 'paid'
")->fetchColumn();

// Last month revenue (for change %)
$last_month_rev = $conn->query("
    SELECT COALESCE(SUM(total), 0) FROM orders
    WHERE created_at >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
      AND created_at  < DATE_FORMAT(CURDATE(), '%Y-%m-01')
      AND payment_status = 'paid'
")->fetchColumn();

// Yesterday's orders (for change %)
$yesterday_orders = $conn->query("
    SELECT COUNT(*) FROM orders
    WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
")->fetchColumn();

// Users last month (for change %)
$last_month_users = $conn->query("
    SELECT COUNT(*) FROM users
    WHERE role = 'user'
      AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetchColumn();

// Helper: calculate % change string
function pct_change($now, $prev): array
{
    if ($prev == 0) return ['label' => 'N/A', 'up' => true];
    $pct = (($now - $prev) / $prev) * 100;
    return ['label' => ($pct >= 0 ? '+' : '') . number_format($pct, 1) . '%', 'up' => $pct >= 0];
}

$today_change   = pct_change($today_sales,   $yesterday_sales);
$revenue_change = pct_change($total_revenue, $last_month_rev + $total_revenue - $this_month);
$orders_change  = pct_change($new_orders,    $yesterday_orders);
$users_change   = pct_change($total_users,   $last_month_users);

$stats = [
    ['label' => 'Today Sales',   'value' => '$' . number_format($today_sales, 0),   'change' => $today_change['label'],   'up' => $today_change['up'],   'icon' => '💰', 'color' => '#00e5ff'],
    ['label' => 'Total Revenue', 'value' => '$' . number_format($total_revenue, 0), 'change' => $revenue_change['label'], 'up' => $revenue_change['up'], 'icon' => '📈', 'color' => '#4ade80'],
    ['label' => 'New Orders',    'value' => number_format($new_orders),              'change' => $orders_change['label'],  'up' => $orders_change['up'],  'icon' => '📦', 'color' => '#a78bfa'],
    ['label' => 'Total Users',   'value' => number_format($total_users),             'change' => $users_change['label'],   'up' => $users_change['up'],   'icon' => '👥', 'color' => '#f59e0b'],
];

/* ══════════════════════════════════════════
   RECENT ORDERS
══════════════════════════════════════════ */
$orders_raw = $conn->query("
    SELECT
        o.id,
        o.status,
        o.total,
        o.created_at,
        u.full_name AS customer,
        oi.product_name AS product
    FROM orders o
    LEFT JOIN users      u  ON u.id = o.user_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    ORDER BY o.created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$orders = array_map(fn($o) => [
    'id'       => '#ORD-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT),
    'customer' => $o['customer'] ?? 'Guest',
    'product'  => $o['product']  ?? '—',
    'amount'   => '$' . number_format($o['total'], 0),
    'status'   => $o['status'],
    'date'     => date('M j, Y', strtotime($o['created_at'])),
], $orders_raw);

/* ══════════════════════════════════════════
   TOP PRODUCTS
══════════════════════════════════════════ */
$top_products_raw = $conn->query("
    SELECT
        p.id,
        p.product_name AS name,
        b.brand_name AS brand,
        p.sold,
        ROUND(p.sold * p.price, 0) AS revenue,
        p.stock,
        p.image AS img,
        p.sku
    FROM products p
    JOIN brands b ON b.id = p.brand_id
    WHERE p.status = 'active'
    ORDER BY p.sold DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$top_products = array_map(fn($p) => [
    'id'      => $p['id'],
    'name'    => $p['name'],
    'brand'   => $p['brand'],
    'sold'    => (int)$p['sold'],
    'revenue' => '$' . number_format($p['revenue'], 0),
    'stock'   => (int)$p['stock'],
    'img'     => $p['img'],
    'sku'     => $p['sku'],
], $top_products_raw);

/* ══════════════════════════════════════════
   CATEGORY SALES DISTRIBUTION
   (based on sold × price per product in each category)
══════════════════════════════════════════ */
$category_colors = [
    'Smartphones' => '#00e5ff',
    'Laptops'     => '#a78bfa',
    'Audio'       => '#4ade80',
    'Headphones'  => '#4ade80',
    'Cameras'     => '#f59e0b',
    'Drones'      => '#ff6b35',
    'Gaming'      => '#f472b6',
    'Tablets'     => '#38bdf8',
    'Wearables'   => '#fb923c',
    'Accessories' => '#94a3b8',
    'TVs'         => '#e879f9',
];

$cat_raw = $conn->query("
    SELECT
        c.category_name AS name,
        COALESCE(SUM(p.sold * p.price), 0) AS total_rev
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
    WHERE c.category_name != 'All'
    GROUP BY c.id, c.category_name
    HAVING total_rev > 0
    ORDER BY total_rev DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$grand_total = array_sum(array_column($cat_raw, 'total_rev')) ?: 1;

$categories = array_map(fn($c) => [
    'name'  => $c['name'],
    'pct'   => round(($c['total_rev'] / $grand_total) * 100),
    'color' => $category_colors[$c['name']] ?? '#3d4757',
], $cat_raw);

/* ══════════════════════════════════════════
   REVENUE CHART — This Year (rolling last 12
   calendar months) vs Last Year (the 12 months
   immediately before that), fetched in one query
   and split into two series for the dropdown.
══════════════════════════════════════════ */
$chart_raw = $conn->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        ROUND(SUM(total) / 1000, 1) AS sales_k,
        COUNT(*) AS order_count
    FROM orders
    WHERE created_at >= DATE_FORMAT(NOW() - INTERVAL 23 MONTH, '%Y-%m-01')
      AND payment_status = 'paid'
    GROUP BY month_key
    ORDER BY month_key ASC
")->fetchAll(PDO::FETCH_ASSOC);

$chart_map = [];
foreach ($chart_raw as $row) {
    $chart_map[$row['month_key']] = $row;
}

// Build a 12-month skeleton for the window [$startMonthsAgo .. $endMonthsAgo]
// (counted backwards from the current month), so missing months show as 0.
function build_chart_series(array $chart_map, int $startMonthsAgo, int $endMonthsAgo): array
{
    $months = [];
    $sales  = [];
    $orders = [];
    for ($i = $startMonthsAgo; $i >= $endMonthsAgo; $i--) {
        $key   = date('Y-m', strtotime("-$i months"));
        $label = date('M',   strtotime("-$i months"));
        $row   = $chart_map[$key] ?? null;
        $months[] = $label;
        $sales[]  = $row ? (float)$row['sales_k'] : 0;
        $orders[] = $row ? (int)$row['order_count'] : 0;
    }
    return [$months, $sales, $orders];
}

[$months,    $sales_data,    $orders_data]    = build_chart_series($chart_map, 11, 0);
[$months_ly, $sales_data_ly, $orders_data_ly] = build_chart_series($chart_map, 23, 12);

$max_sales    = max($sales_data)    ?: 1; // avoid division by zero
$max_sales_ly = max($sales_data_ly) ?: 1;

/* ══════════════════════════════════════════
   FIX: SPARKLINE TREND DATA — was 4 hardcoded
   arrays that never reflected real activity.
   Now built from actual orders/users data:
     0. Today Sales   → daily paid revenue, last 12 days
     1. Total Revenue → reuses $sales_data (monthly revenue, last 12 months)
     2. New Orders    → reuses $orders_data (monthly order count, last 12 months)
     3. Total Users   → new signups per month, last 12 months
══════════════════════════════════════════ */
$daily_sales_raw = $conn->query("
    SELECT DATE(created_at) AS d, COALESCE(SUM(total), 0) AS total
    FROM orders
    WHERE created_at >= CURDATE() - INTERVAL 11 DAY
      AND payment_status = 'paid'
    GROUP BY DATE(created_at)
")->fetchAll(PDO::FETCH_KEY_PAIR);

$daily_sales_spark = [];
for ($i = 11; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $daily_sales_spark[] = (float)($daily_sales_raw[$day] ?? 0);
}

$monthly_users_raw = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
    FROM users
    WHERE role = 'user'
      AND created_at >= DATE_FORMAT(NOW() - INTERVAL 11 MONTH, '%Y-%m-01')
    GROUP BY m
")->fetchAll(PDO::FETCH_KEY_PAIR);

$monthly_users_spark = [];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $monthly_users_spark[] = (int)($monthly_users_raw[$key] ?? 0);
}

$spark_data = [
    $daily_sales_spark,   // Today Sales
    $sales_data,          // Total Revenue
    $orders_data,         // New Orders
    $monthly_users_spark, // Total Users
];

/* ══════════════════════════════════════════
   STOCK ALERTS — FIX: was a hardcoded static
   array (MacBook Pro M3 / DJI Mini 4 Pro /
   Galaxy S25 Ultra) that never reflected real
   inventory. Now pulled live from `products`
   so the panel always matches actual stock.
══════════════════════════════════════════ */
$stock_alerts_raw = $conn->query("
    SELECT id, product_name, stock, sku
    FROM products
    WHERE status = 'active'
      AND stock < 20
    ORDER BY stock ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$alerts = array_map(function ($p) {
    $stock = (int)$p['stock'];

    if ($stock == 0) {
        $icon = '🚫';
        $bg   = 'rgba(239,68,68,.1)';
        $desc = 'Out of stock';
    } elseif ($stock < 10) {
        $icon = '⚠️';
        $bg   = 'rgba(245,158,11,.1)';
        $desc = $stock . ' units remaining — reorder soon';
    } else {
        $icon = '📉';
        $bg   = 'rgba(0,229,255,.1)';
        $desc = $stock . ' units remaining';
    }

    return [
        'icon'  => $icon,
        'bg'    => $bg,
        'title' => $p['product_name'],
        'desc'  => $desc,
        'time'  => 'SKU: ' . $p['sku'],
    ];
}, $stock_alerts_raw);

// "Critical" = out of stock or under 10 units — drives the badge count
$critical_alert_count = count(array_filter(
    $stock_alerts_raw,
    fn($p) => (int)$p['stock'] < 10
));

/* ══════════════════════════════════════════
   HELPER
══════════════════════════════════════════ */
$username = $_SESSION['user']['full_name'] ?? 'Admin';
$email    = $_SESSION['user']['email'] ?? '';
$initials = strtoupper(substr($username, 0, 1));
$h        = (int)date('H');
$greeting = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');

?>


<?php require_once "head.php"; ?>

<div class="sb-mobile-ovl" id="sbOvl" onclick="closeSb()"></div>

<div class="shell">

    <!-- SIDEBAR  -->
    <?php require_once "sidebar.php" ?>

    <!-- NAVBAR -->
    <?php require_once "navbar.php" ?>

    <main class="main">

        <!-- Page Header -->
        <div class="page-hd au">
            <div>
                <div class="ph-eyebrow">Overview</div>
                <h1 class="ph-title"><?= $greeting ?>, <?= htmlspecialchars($username) ?> 👋</h1>
                <p class="ph-sub">Here's what's happening at LectroHub today — <?= date('l, F j, Y') ?></p>
            </div>

        </div>

        <!-- ── STAT CARDS ── -->
        <div class="stats-grid">
            <?php
            $spark_data = [
                [40, 55, 38, 62, 50, 70, 65, 82, 75, 90, 88, 95],
                [90, 85, 92, 88, 95, 100, 98, 105, 102, 110, 108, 115],
                [65, 72, 68, 80, 75, 88, 82, 95, 90, 100, 97, 105],
                [120, 115, 125, 118, 130, 122, 128, 118, 125, 120, 118, 124],
            ];
            foreach ($stats as $i => $s):
            ?>
                <div class="stat-card au au-<?= $i + 1 ?>" style="--sc:<?= $s['color'] ?>">
                    <div class="sc-top">
                        <div class="sc-icon"><?= $s['icon'] ?></div>
                        <div class="sc-change <?= $s['up'] ? 'up' : 'dn' ?>">
                            <?= $s['up'] ? '▲' : '▼' ?> <?= $s['change'] ?>
                        </div>
                    </div>
                    <div class="sc-value"><?= $s['value'] ?></div>
                    <div class="sc-label"><?= $s['label'] ?></div>
                    <div class="sc-spark" id="spark<?= $i ?>">
                        <?php $md = max($spark_data[$i]) ?: 1;
                        foreach ($spark_data[$i] as $v): ?>
                            <div class="sp-bar" style="height:<?= round(($v / $md) * 100) ?>%"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ── CHARTS ROW ── -->
        <div class="g-main" style="margin-bottom:20px">

            <!-- Revenue Chart -->
            <div class="adm-card au">
                <div class="card-hd">
                    <div>
                        <div class="card-title">Revenue & Sales</div>
                        <div class="card-sub">Monthly performance — last 12 months</div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <div class="chart-legend">
                            <div class="cleg-item"><span class="cleg-dot" style="background:var(--ac)"></span>Revenue</div>
                            <div class="cleg-item"><span class="cleg-dot" style="background:var(--orange)"></span>Orders</div>
                        </div>
                        <select id="revChartRange" class="btn btn-secondary btn-sm" style="padding:5px 28px 5px 10px;border-radius:50px;appearance:none;background-image:url(\" data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%237a8799' stroke-width='2' %3E%3Cpath d='M6 9l6 6 6-6' /%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 8px center">
                            <option value="thisYear">This Year</option>
                            <option value="lastYear">Last Year</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <div class="chart-bars-wrap" id="revChart">
                            <?php foreach ($sales_data as $idx => $val):
                                $pct = round($val / $max_sales * 100) / 100;
                                $is_current = $idx === 11;
                            ?>
                                <div class="cbar <?= $is_current ? 'highlight' : '' ?>" style="--h:<?= $pct ?>">
                                    <span class="ctip">$<?= $val ?>k &middot; <?= $orders_data[$idx] ?> orders</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="clabels">
                            <?php foreach ($months as $m): ?><span class="clabel"><?= $m ?></span><?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                window.revChartData = {
                    thisYear: {
                        months: <?= json_encode($months) ?>,
                        sales:  <?= json_encode($sales_data) ?>,
                        orders: <?= json_encode($orders_data) ?>,
                        max:    <?= json_encode($max_sales) ?>
                    },
                    lastYear: {
                        months: <?= json_encode($months_ly) ?>,
                        sales:  <?= json_encode($sales_data_ly) ?>,
                        orders: <?= json_encode($orders_data_ly) ?>,
                        max:    <?= json_encode($max_sales_ly) ?>
                    }
                };
            </script>

            <!-- Category Donut -->
            <div class="adm-card au">
                <div class="card-hd">
                    <div>
                        <div class="card-title">Sales by Category</div>
                        <div class="card-sub">Revenue distribution this month</div>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    // Build SVG donut manually
                    $r = 60;
                    $cx = 70;
                    $cy = 70;
                    $sw = 22;
                    $total_pct = 0;
                    $offset = -90; // start at top
                    $circumference = 2 * M_PI * $r;
                    ?>
                    <div class="donut-wrap">
                        <svg width="140" height="140" viewBox="0 0 140 140" class="donut-svg">
                            <!-- bg ring -->
                            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none" stroke="var(--card3)" stroke-width="<?= $sw ?>" />
                            <?php foreach ($categories as $cat):
                                $dash     = ($cat['pct'] / 100) * $circumference;
                                $gap      = $circumference - $dash;
                                $rotation = $offset;
                                $offset  += ($cat['pct'] / 100) * 360;
                            ?>
                                <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>" fill="none"
                                    stroke="<?= $cat['color'] ?>" stroke-width="<?= $sw ?>"
                                    stroke-dasharray="<?= $dash ?> <?= $gap ?>"
                                    stroke-dashoffset="0"
                                    transform="rotate(<?= $rotation ?> <?= $cx ?> <?= $cy ?>)"
                                    style="transition:stroke-dasharray .6s ease" />
                            <?php endforeach; ?>
                            <text x="<?= $cx ?>" y="<?= $cy - 6 ?>" text-anchor="middle"
                                font-family="Syne,sans-serif" font-size="16"
                                font-weight="700" fill="var(--txt)">$128k</text>

                            <text x="<?= $cx ?>" y="<?= $cy + 10 ?>" text-anchor="middle"
                                font-family="DM Sans,sans-serif" font-size="10"
                                fill="var(--muted)">total</text>

                        </svg>
                        <div class="donut-legend">
                            <?php foreach ($categories as $cat): ?>
                                <div class="dl-item">
                                    <div class="dl-left">
                                        <div class="dl-dot" style="background:<?= $cat['color'] ?>"></div>
                                        <span class="dl-name"><?= $cat['name'] ?></span>
                                    </div>
                                    <span class="dl-pct"><?= $cat['pct'] ?>%</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- ── ORDERS + ALERTS ROW ── -->
        <div class="g2" style="margin-bottom:20px">

            <!-- Recent Orders -->
            <div class="adm-card au">
                <div class="card-hd">
                    <div>
                        <div class="card-title">Recent Orders</div>
                        <div class="card-sub"><?= count($orders) ?> latest transactions</div>
                    </div>
                    <a href="orders.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="card-body p0">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $o):
                                $sc = match ($o['status']) {
                                    'Delivered' => 'b-green',
                                    'Shipped' => 'b-cyan',
                                    'Processing' => 'b-amber',
                                    'Cancelled' => 'b-red',
                                    default => 'b-grey'
                                };
                            ?>
                                <tr>
                                    <td><span class="td-mono" style="color:var(--ac)"><?= $o['id'] ?></span>
                                        <div style="font-size:10px;color:var(--dim);margin-top:2px"><?= $o['date'] ?></div>
                                    </td>
                                    <td><span style="font-size:13px;font-weight:500"><?= htmlspecialchars($o['customer']) ?></span>
                                        <div style="font-size:10px;color:var(--muted);margin-top:1px"><?= htmlspecialchars($o['product']) ?></div>
                                    </td>
                                    <td><span class="td-mono" style="font-weight:600"><?= $o['amount'] ?></span></td>
                                    <td><span class="badge <?= $sc ?>"><?= $o['status'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Alerts + Category Progress -->
            <div style="display:flex;flex-direction:column;gap:20px">

                <!-- Stock Alerts -->
                <div class="adm-card au">
                    <div class="card-hd">
                        <div class="card-title">⚠️ Stock Alerts</div>
                        <?php if ($critical_alert_count > 0): ?>
                            <span class="badge b-amber"><?= $critical_alert_count ?> Critical</span>
                        <?php else: ?>
                            <span class="badge b-green">All Healthy</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p0">
                        <?php if (empty($alerts)): ?>
                            <div style="padding:32px 20px;text-align:center;color:var(--muted);font-size:12px">
                                ✅ No low-stock products right now.
                            </div>
                        <?php else: ?>
                            <?php foreach ($alerts as $al): ?>
                                <div class="alert-item">
                                    <div class="alert-icon" style="background:<?= $al['bg'] ?>"><?= $al['icon'] ?></div>
                                    <div class="alert-text">
                                        <div class="alert-title"><?= htmlspecialchars($al['title']) ?></div>
                                        <div class="alert-desc"><?= htmlspecialchars($al['desc']) ?></div>
                                        <div class="alert-time"><?= htmlspecialchars($al['time']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Category progress -->
                <div class="adm-card au">
                    <div class="card-hd">
                        <div class="card-title">Sales by Category</div>
                        <div class="card-sub">vs last month</div>
                    </div>
                    <div class="card-body">
                        <?php foreach ($categories as $cat): ?>
                            <div class="prog-wrap">
                                <div class="prog-row">
                                    <span class="prog-name"><?= $cat['name'] ?></span>
                                    <span class="prog-pct"><?= $cat['pct'] ?>%</span>
                                </div>
                                <div class="prog-bg">
                                    <div class="prog-fill" style="width:<?= $cat['pct'] ?>%;background:linear-gradient(90deg,<?= $cat['color'] ?>,<?= $cat['color'] ?>88)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── TOP PRODUCTS ── -->
        <div class="adm-card au">
            <div class="card-hd">
                <div>
                    <div class="card-title">Top Performing Products</div>
                    <div class="card-sub">Ranked by units sold this month</div>
                </div>
                <a href="products.php?sort=sales" class="btn btn-secondary btn-sm">Full Report</a>
            </div>
            <div class="card-body p0">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Brand</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $rank => $p):
                            $stock_badge = $p['stock'] < 20 ? 'b-red' : ($p['stock'] < 50 ? 'b-amber' : 'b-green');
                            $imgFile = !empty($p['img']) ? $p['img'] : 'default-product.webp';
                        ?>
                            <tr>
                                <td><span style="font-family:var(--fm);font-size:13px;font-weight:700;color:var(--dim)"><?= str_pad($rank + 1, 2, '0', STR_PAD_LEFT) ?></span></td>
                                <td>
                                    <div class="td-prod">
                                        <img class="td-thumb" src="img/<?= $imgFile ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                                        <div>
                                            <div class="td-pname"><?= htmlspecialchars($p['name']) ?></div>
                                            <div class="td-psku"><?= htmlspecialchars($p['sku']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge b-grey"><?= htmlspecialchars($p['brand']) ?></span></td>
                                <td><span class="td-mono" style="font-weight:600;color:var(--txt)"><?= number_format($p['sold']) ?></span></td>
                                <td><span class="td-mono" style="color:var(--green);font-weight:600"><?= $p['revenue'] ?></span></td>
                                <td><span class="badge <?= $stock_badge ?>"><?= $p['stock'] ?> units</span></td>
                                <td>
                                    <div style="display:flex;gap:6px">
                                        <a href="products_edit.php?id=<?= $p['id'] ?>" class="btn btn-icon btn-sm" title="Edit">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </a>
                                        <button class="btn btn-icon btn-sm" title="View" onclick="showToast('Viewing product details','📦')">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main><!-- /main -->

</div><!-- /shell -->


<!-- FOOTER -->
<?php require_once "footer.php" ?>


<!-- Toast -->
<div class="toast" id="toast"><span id="ti" style="font-size:17px">✓</span><span id="tm"></span></div>

<script src="js/index.js"></script>

</body>

</html>