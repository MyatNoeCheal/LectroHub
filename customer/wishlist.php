<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";
require_once __DIR__ . "/lang/translate.php";


$user_id = $_SESSION['user']['id'] ?? null;

if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

/* ── Fetch wishlist product IDs ── */
$wishlist_ids = [];

if ($user_id) {
    $stmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $wishlist_ids = $_SESSION['wishlist'];
}

/* ── Fetch product details ── */
$wishlist_items = [];

if (!empty($wishlist_ids)) {
    $placeholders = implode(',', array_fill(0, count($wishlist_ids), '?'));
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.product_name  AS name,
            p.brand_legacy  AS brand,
            p.price,
            p.stock,
            p.image         AS img,
            p.featured,
            p.sold,
            c.category_name AS category
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders)
          AND p.status = 'active'
        ORDER BY p.product_name ASC
    ");
    $stmt->execute($wishlist_ids);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ── Cart product IDs (to show "In Cart" state) ── */
$cart_ids = isset($_SESSION['cart']) ? array_keys($_SESSION['cart']) : [];

/* ── Compare product IDs (to show "Compare" checkbox state) ── */
if (!isset($_SESSION['compare']) || !is_array($_SESSION['compare'])) {
    $_SESSION['compare'] = [];
}
$compare_ids = $_SESSION['compare'];
define('COMPARE_MAX', 4);

/* ── Helper: resolve image path ── */
function wl_img(?string $img): string {
    if ($img && $img !== '') {
        return "/LectroHub/admin/img/" . htmlspecialchars($img);
    }
    return "https://placehold.co/280x280/070a10/00e5ff?text=No+Image";
}
?>

<?php require_once "head.php" ?>
<?php require_once "navbar.php" ?>

<body>
<main class="wishlist-page">

    <!-- ── BREADCRUMB ── -->
    <div class="wl-breadcrumb">
        <a href="index.php"><?= t('home') ?></a>
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
        <span><?= t('wishlist') ?></span>
    </div>

    <!-- ── PAGE HEADER ── -->
    <div class="wl-header">
        <div><br>
            <h1 class="wl-title">
                <?= t('my_wishlist_title') ?>
                <?php if (!empty($wishlist_items)): ?>
                    <span class="wl-count-badge"><?= count($wishlist_items) ?> <?= t(count($wishlist_items) === 1 ? 'item_singular' : 'item_plural') ?></span>
                <?php endif; ?>
            </h1>
            <p class="wl-sub"><?= t('wishlist_sub') ?></p>
        </div>
        <?php if (!empty($wishlist_items)): ?>
            <button class="btn-add-all" onclick="addAllToCart()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <?= t('add_all_to_cart') ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($wishlist_items)): ?>
        <!-- ── COMPARE BAR ── -->
        <div class="wl-compare-bar" id="wlCompareBar">
            <span class="wl-compare-count">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 3v18M15 3v18M4 8h5M15 8h5M4 16h5M15 16h5"/>
                </svg>
                <span id="wlCompareCountText"><?= count($compare_ids) ?></span>/<?= COMPARE_MAX ?> <?= t('selected_to_compare') ?>
            </span>
            <a href="compare.php" class="btn-outline-sm" id="wlCompareGoBtn"
               style="<?= count($compare_ids) < 2 ? 'pointer-events:none;opacity:.5' : '' ?>">
                <?= t('compare_now') ?>
            </a>
        </div>
    <?php endif; ?>

    <?php if (empty($wishlist_items)): ?>

        <!-- ── EMPTY STATE ── -->
        <div class="wl-empty">
            <div class="wl-empty-icon">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </div>
            <div class="wl-empty-title"><?= t('wishlist_empty_title') ?></div>
            <p class="wl-empty-sub"><?= t('wishlist_empty_sub') ?></p>
            <a href="products.php" class="btn-hero-primary" style="display:inline-flex;margin-top:20px;">
                <?= t('browse_products') ?>
            </a>
        </div>

    <?php else: ?>

        <!-- ── Guest notice ── -->
        <?php if (!$user_id): ?>
            <div style="background:var(--surface-1);border:1px solid var(--border);border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:13px;color:var(--muted);display:flex;align-items:center;gap:10px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= t('guest_wishlist_notice') ?> <a href="/LectroHub/auth/signin.php" style="color:var(--accent)"><?= t('guest_wishlist_signin_link') ?></a> <?= t('guest_wishlist_notice_suffix') ?>
            </div>
        <?php endif; ?>

        <!-- ── WISHLIST GRID ── -->
        <div class="wl-grid" id="wishlistGrid">
            <?php foreach ($wishlist_items as $item): ?>
                <?php $in_cart = in_array($item['id'], $cart_ids); ?>
                <div class="wl-card" id="wlCard<?= $item['id'] ?>">

                    <button class="wl-remove-btn"
                            onclick="removeFromWishlist(<?= $item['id'] ?>)"
                            title="<?= t('remove_from_wishlist_title') ?>"
                            aria-label="<?= t('remove_from_wishlist_aria', ['name' => htmlspecialchars($item['name'])]) ?>">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>

                    <?php if ($item['featured']): ?>
                        <span class="wl-featured-badge"><?= t('featured') ?></span>
                    <?php endif; ?>

                    <a href="product.php?id=<?= $item['id'] ?>" class="wl-img-wrap">
                        <img src="<?= wl_img($item['img']) ?>"
                             alt="<?= htmlspecialchars($item['name']) ?>"
                             loading="lazy">
                    </a>

                    <div class="wl-info">
                        <div class="wl-category"><?= htmlspecialchars($item['category']) ?></div>
                        <a href="product.php?id=<?= $item['id'] ?>" class="wl-name">
                            <?= htmlspecialchars($item['name']) ?>
                        </a>
                        <div class="wl-brand"><?= htmlspecialchars($item['brand']) ?></div>

                        <div class="wl-meta-row">
                            <span class="wl-price">$<?= number_format($item['price'], 2) ?></span>
                            <?php if ($item['stock'] <= 0): ?>
                                <span class="wl-stock wl-out"><?= t('out_of_stock') ?></span>
                            <?php elseif ($item['stock'] <= 5): ?>
                                <span class="wl-stock wl-low"><?= t('only_x_left', ['x' => $item['stock']]) ?></span>
                            <?php else: ?>
                                <span class="wl-stock wl-in"><?= t('in_stock') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php $is_comparing = in_array($item['id'], $compare_ids); ?>
                    <label class="wl-compare-check <?= $is_comparing ? 'is-comparing' : '' ?>"
                           id="compareLabel<?= $item['id'] ?>"
                           for="compareCheck<?= $item['id'] ?>">
                        <input type="checkbox"
                               id="compareCheck<?= $item['id'] ?>"
                               class="wl-compare-input"
                               data-product-id="<?= $item['id'] ?>"
                               autocomplete="off"
                               <?= $is_comparing ? 'checked' : '' ?>
                               <?= !$is_comparing && count($compare_ids) >= COMPARE_MAX ? 'disabled' : '' ?>>
                        <svg class="wl-compare-icon-add" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M9 3v18M15 3v18M4 8h5M15 8h5M4 16h5M15 16h5"/>
                        </svg>
                        <svg class="wl-compare-icon-check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                        <span class="wl-compare-label-text">
                            <?= $is_comparing ? t('comparing') : t('add_to_compare') ?>
                        </span>
                    </label>

                    <div class="wl-actions">
                        <?php if ($item['stock'] > 0): ?>
                            <button class="btn-wl-cart <?= $in_cart ? 'in-cart' : '' ?>"
                                    id="cartBtn<?= $item['id'] ?>"
                                    onclick="addToCartFromWishlist(<?= $item['id'] ?>, this)"
                                    <?= $in_cart ? 'disabled' : '' ?>>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                    <line x1="3" y1="6" x2="21" y2="6"/>
                                    <path d="M16 10a4 4 0 0 1-8 0"/>
                                </svg>
                                <?= $in_cart ? t('js_in_cart') : t('add_to_cart') ?>
                            </button>
                        <?php else: ?>
                            <button class="btn-wl-cart" disabled><?= t('out_of_stock') ?></button>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <div class="wl-footer-row">
            <button class="btn-wl-clear" onclick="clearWishlist()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    <path d="M10 11v6M14 11v6"/>
                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                </svg>
                <?= t('clear_wishlist') ?>
            </button>
        </div>

    <?php endif; ?>

</main>

<?php require_once "footer.php" ?>

<div class="toast" id="toast">
    <span class="toast-icon" id="toastIcon">✓</span>
    <span id="toastMsg"><?= t('toast_done') ?></span>
</div>

</body>