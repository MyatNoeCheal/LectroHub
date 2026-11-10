<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";
require_once "../lectrohub_db/review_helper.php";
require_once __DIR__ . "/lang/translate.php";

if (!isset($_SESSION['compare']) || !is_array($_SESSION['compare'])) {
    $_SESSION['compare'] = [];
}

$compare_ids = $_SESSION['compare'];

/* ── Fetch products, preserving the order they were added ── */
$products = [];
if (!empty($compare_ids)) {
    $placeholders = implode(',', array_fill(0, count($compare_ids), '?'));
    $stmt = $conn->prepare("
        SELECT p.*, p.brand_legacy AS brand, c.category_name AS category
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders) AND p.status = 'active'
    ");
    $stmt->execute($compare_ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $by_id = [];
    foreach ($rows as $row) $by_id[$row['id']] = $row;
    foreach ($compare_ids as $cid) {
        if (isset($by_id[$cid])) $products[] = $by_id[$cid];
    }
}

/* Drop any ids that no longer resolve to a live product (deleted/deactivated) */
$_SESSION['compare'] = array_column($products, 'id');

foreach ($products as &$p) {
    $p['rating'] = get_product_rating_summary($conn, (int)$p['id']);
}
unset($p);

$cart_ids = isset($_SESSION['cart']) ? array_keys($_SESSION['cart']) : [];

function cmp_img(?string $img): string {
    if ($img && $img !== '') return "/LectroHub/admin/img/" . htmlspecialchars($img);
    return "https://placehold.co/220x220/070a10/00e5ff?text=No+Image";
}

/* Spec rows rendered underneath the product headers */
$spec_rows = [
    'Brand'       => fn($p) => htmlspecialchars($p['brand']),
    'Category'    => fn($p) => htmlspecialchars($p['category']),
    'SKU'         => fn($p) => htmlspecialchars($p['sku'] ?? '—'),
    'Rating'      => fn($p) => $p['rating']['count'] > 0
        ? str_repeat('★', (int)round($p['rating']['avg'])) . str_repeat('☆', 5 - (int)round($p['rating']['avg']))
          . ' (' . $p['rating']['count'] . ')'
        : 'No reviews yet',
    'Stock'       => fn($p) => (int)$p['stock'] > 0 ? (int)$p['stock'] . ' in stock' : 'Out of stock',
    'Sold'        => fn($p) => number_format((int)$p['sold']) . ' sold',
    'Description' => fn($p) => $p['description'] ? htmlspecialchars($p['description']) : '—',
];
?>
<?php require_once "head.php" ?>
<?php require_once "navbar.php" ?>

<body>
<main class="compare-page" style="max-width:1200px;margin:0 auto;padding:32px 24px 80px">

    <!-- ── BREADCRUMB ── -->
    <div class="cart-breadcrumb">
        <a href="index.php"><?= htmlspecialchars(t('home')) ?></a>
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
        <a href="wishlist.php"><?= htmlspecialchars(t('wishlist')) ?></a>
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
        <span>Compare</span>
    </div>

    <!-- ── PAGE HEADER ── -->
    <div class="orders-hd" style="margin-top:14px">
        <div>
            <h1 class="orders-title">Compare Products</h1>
            <p class="orders-sub"><?= count($products) ?> of 4 selected</p>
        </div>
        <?php if (!empty($products)): ?>
            <button class="btn-outline-sm" onclick="clearCompare()">Clear All</button>
        <?php endif; ?>
    </div>

    <?php if (empty($products)): ?>

        <!-- ── EMPTY STATE ── -->
        <div class="cart-empty">
            <div class="cart-empty-icon">⚖️</div>
            <div class="cart-empty-title">Nothing to compare yet</div>
            <p class="cart-empty-sub">Add products from your wishlist to compare them side by side.</p>
            <a href="wishlist.php" class="btn-hero-primary" style="display:inline-flex;margin-top:20px">
                Go to Wishlist
            </a>
        </div>

    <?php else: ?>

        <div style="overflow-x:auto;-webkit-overflow-scrolling:touch">
        <table class="compare-table" style="width:100%;border-collapse:collapse;min-width:<?= 160 + count($products) * 220 ?>px">
            <thead>
                <tr>
                    <th style="width:160px"></th>
                    <?php foreach ($products as $p): ?>
                        <th style="padding:12px;text-align:center;position:relative;border-bottom:1px solid var(--border,#242c3d);vertical-align:top">
                            <button onclick="removeFromCompare(<?= $p['id'] ?>)"
                                    title="Remove from comparison"
                                    style="position:absolute;top:6px;right:6px;background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;line-height:1">×</button>

                            <a href="product.php?id=<?= $p['id'] ?>" style="display:block;text-decoration:none;color:inherit">
                                <img src="<?= cmp_img($p['image']) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>"
                                     style="width:110px;height:110px;object-fit:contain;margin:10px auto 8px;display:block">
                                <div style="font-size:13px;font-weight:700;color:var(--txt,inherit);line-height:1.35">
                                    <?= htmlspecialchars($p['product_name']) ?>
                                </div>
                            </a>
                            <div style="font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:800;color:var(--accent,#00e5ff);margin-top:6px">
                                $<?= number_format((float)$p['price'], 2) ?>
                            </div>

                            <div style="margin-top:10px">
                                <?php $in_cart = in_array($p['id'], $cart_ids); ?>
                                <button class="btn-checkout" style="padding:6px 10px;font-size:11px;max-width:none;width:100%"
                                        onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['product_name'])) ?>')"
                                        <?= (int)$p['stock'] <= 0 || $in_cart ? 'disabled' : '' ?>>
                                    <?= $in_cart ? 'In Cart' : ((int)$p['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart') ?>
                                </button>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($spec_rows as $label => $fn): ?>
                    <tr>
                        <td style="padding:12px;font-size:12px;font-weight:700;color:var(--muted);border-bottom:1px solid var(--border,#242c3d);white-space:nowrap">
                            <?= htmlspecialchars($label) ?>
                        </td>
                        <?php foreach ($products as $p): ?>
                            <td style="padding:12px;font-size:13px;text-align:center;border-bottom:1px solid var(--border,#242c3d);max-width:220px">
                                <?= $fn($p) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php endif; ?>

</main>

<?php require_once "footer.php" ?>

<div class="toast" id="toast">
    <span class="toast-icon" id="toastIcon">✓</span>
    <span id="toastMsg">Done</span>
</div>

<script>
function removeFromCompare(id) {
    fetch('compare_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove&product_id=${id}`
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); });
}

function clearCompare() {
    if (!confirm('Clear the entire comparison?')) return;
    fetch('compare_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=clear'
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); });
}
</script>

</body>