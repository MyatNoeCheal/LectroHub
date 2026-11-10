<?php

/* FIX: this page never started the session, so $_SESSION['user'],
   $_SESSION['cart'] and $_SESSION['wishlist'] were always empty here —
   even for logged-in users — which made the navbar show "Guest" and
   made add-to-cart / wishlist state look broken on this page. */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";
require_once "../lectrohub_db/review_helper.php";

/* ── Helpers ── */
function product_img(?string $img): string {
    if (!empty($img)) return "/LectroHub/admin/img/" . htmlspecialchars($img);
    return "https://placehold.co/600x600/070a10/00e5ff?text=No+Image";
}

/* ── Fetch product ── */
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: products.php");
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            p.*,
            p.brand_legacy AS brand,
            c.category_name AS category
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.id = :id AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("product.php DB error: " . $e->getMessage());
    $p = null;
}

if (!$p) {
    header("Location: products.php");
    exit;
}

/* ── Review rating summary (used in the header stars + reviews_section.php) ── */
$rating_summary = get_product_rating_summary($conn, $id);

/* ══════════════════════════════════════════
   FIX: FREQUENTLY BOUGHT TOGETHER
   Amazon Prime case-study lesson — purchase
   history is already sitting in order_items,
   so surface it instead of only ever showing
   same-category filler. Products that were
   actually co-purchased with this one, ranked
   by how often that pairing occurs.
══════════════════════════════════════════ */
try {
    $fbt_stmt = $conn->prepare("
        SELECT
            p2.id,
            p2.product_name  AS name,
            p2.brand_legacy  AS brand,
            p2.price,
            p2.stock,
            p2.sold,
            p2.image         AS img,
            c2.category_name AS category,
            COUNT(*)         AS freq
        FROM order_items oi1
        JOIN order_items oi2 ON oi2.order_id = oi1.order_id
                             AND oi2.product_id != oi1.product_id
        JOIN products p2 ON p2.id = oi2.product_id
        LEFT JOIN categories c2 ON c2.id = p2.category_id
        WHERE oi1.product_id = :id
          AND p2.status = 'active'
          AND p2.stock  > 0
        GROUP BY p2.id, p2.product_name, p2.brand_legacy, p2.price, p2.stock, p2.sold, p2.image, c2.category_name
        ORDER BY freq DESC, p2.sold DESC
        LIMIT 4
    ");
    $fbt_stmt->execute([':id' => $id]);
    $frequently_bought = $fbt_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("product.php FBT query error: " . $e->getMessage());
    $frequently_bought = [];
}

/* ── Related products (same category, exclude current) ──
   FIX: kept as a fallback for products with no purchase
   history yet (e.g. brand-new listings) so the page never
   shows an empty recommendations area. */
try {
    $rel = $conn->prepare("
        SELECT p.id, p.product_name AS name, p.brand_legacy AS brand, p.price, p.stock, p.sold, p.image AS img,
               c.category_name AS category
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.category_id = :cat_id
          AND p.id       != :id
          AND p.status    = 'active'
          AND p.stock     > 0
        ORDER BY p.sold DESC
        LIMIT 4
    ");
    $rel->execute([':cat_id' => $p['category_id'], ':id' => $id]);
    $related = $rel->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $related = [];
}

$is_out       = (int)$p['stock'] <= 0;
$is_low_stock = !$is_out && (int)$p['stock'] < 20;

/* ══════════════════════════════════════════
   FIX: WISHLIST STATE
   This previously only ever checked
   $_SESSION['wishlist'], which is exclusively
   the GUEST storage mechanism. Logged-in users'
   wishlist state lives in the `wishlist` DB
   table (see wishlist.php / wishlist_action.php),
   so the heart on this page always rendered
   empty (♡) for logged-in users even when the
   product was already wishlisted. Now mirrors
   the same user_id-aware logic products.php
   already uses.
══════════════════════════════════════════ */
$user_id_pdp = $_SESSION['user']['id'] ?? null;

if ($user_id_pdp) {
    $wl_chk = $conn->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1");
    $wl_chk->execute([$user_id_pdp, $id]);
    $in_wishlist = (bool)$wl_chk->fetchColumn();
} else {
    $in_wishlist = isset($_SESSION['wishlist']) && is_array($_SESSION['wishlist']) && in_array($id, $_SESSION['wishlist']);
}
?>
<?php require_once "head.php" ?>

<body>
<?php require_once "navbar.php" ?>

<div class="product-page">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <a href="products.php">Shop</a>
        <a href="products.php?cat=<?= urlencode($p['category']) ?>"><?= htmlspecialchars($p['category']) ?></a>
        <span><?= htmlspecialchars($p['product_name']) ?></span>
    </nav>

    <!-- Main Grid -->
    <div class="product-main">

        <!-- ── GALLERY ── -->
        <div class="gallery">
            <div class="gallery-main">
                <img src="<?= product_img($p['image']) ?>"
                     alt="<?= htmlspecialchars($p['product_name']) ?>"
                     id="mainImg">

                <div class="gallery-badge">
                    <?php if ($p['featured']): ?>
                        <span class="gb gb-featured">Featured</span>
                    <?php endif; ?>
                    <?php if ($is_out): ?>
                        <span class="gb gb-out">Out of Stock</span>
                    <?php elseif ($is_low_stock): ?>
                        <span class="gb gb-low">Only <?= (int)$p['stock'] ?> left</span>
                    <?php endif; ?>
                </div>

                <div class="gallery-wish <?= $in_wishlist ? 'active' : '' ?>"
                     id="wishBtn"
                     onclick="handleWishlist(<?= $p['id'] ?>)"
                     title="<?= $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                    <?= $in_wishlist ? '♥' : '♡' ?>
                </div>
            </div>
        </div>

        <!-- ── PRODUCT DETAIL ── -->
        <div class="product-detail">

            <div class="pd-category"><?= htmlspecialchars($p['category']) ?></div>

            <h1 class="pd-name"><?= htmlspecialchars($p['product_name']) ?></h1>

            <div class="pd-brand">
                Brand: <strong><?= htmlspecialchars($p['brand']) ?></strong>
            </div>

            <div class="pd-rating">
                <span class="pd-stars"><?= str_repeat('★', (int)round($rating_summary['avg'])) . str_repeat('☆', 5 - (int)round($rating_summary['avg'])) ?></span>
                <?php if ($rating_summary['count'] > 0): ?>
                    <a href="#reviews" style="color:var(--muted);text-decoration:underline;font-size:12px">
                        <?= number_format($rating_summary['avg'], 1) ?> (<?= $rating_summary['count'] ?> <?= $rating_summary['count'] === 1 ? 'review' : 'reviews' ?>)
                    </a>
                <?php else: ?>
                    <a href="#reviews" style="color:var(--muted);text-decoration:underline;font-size:12px">No reviews yet</a>
                <?php endif; ?>
                <span class="pd-sold"><?= number_format((int)$p['sold']) ?> sold</span>
            </div>

            <hr class="pd-divider">

            <!-- Price -->
            <div class="pd-price-row">
                <div class="pd-price">$<?= number_format((float)$p['price'], 2) ?></div>
                <?php if ($is_out): ?>
                    <span class="pd-price-out">Out of Stock</span>
                <?php endif; ?>
            </div>

            <!-- Stock status -->
            <?php if ($is_out): ?>
                <div class="pd-stock pd-stock-out">
                    <span class="stock-dot"></span> Out of Stock
                </div>
            <?php elseif ($is_low_stock): ?>
                <div class="pd-stock pd-stock-low">
                    <span class="stock-dot"></span> Only <?= (int)$p['stock'] ?> left in stock
                </div>
            <?php else: ?>
                <div class="pd-stock pd-stock-ok">
                    <span class="stock-dot"></span> In Stock (<?= (int)$p['stock'] ?> available)
                </div>
            <?php endif; ?>

            <hr class="pd-divider">

            <!-- Quantity -->
            <?php if (!$is_out): ?>
            <div class="pd-qty-row" data-max-qty="<?= (int)$p['stock'] ?>">
                <span class="pd-qty-label">Quantity</span>
                <div class="qty-ctrl">
                    <button class="qty-btn" id="qtyMinus" onclick="changeQty(-1)" <?= (int)$p['stock'] < 1 ? 'disabled' : '' ?>>−</button>
                    <span class="qty-val" id="qtyVal">1</span>
                    <button class="qty-btn" id="qtyPlus"  onclick="changeQty(1)"  <?= (int)$p['stock'] < 2 ? 'disabled' : '' ?>>+</button>
                </div>
                <span style="font-size:12px;color:var(--muted)">Max: <?= (int)$p['stock'] ?></span>
            </div>
            <?php endif; ?>

            <!-- CTAs -->
            <div class="pd-ctas">
                <button class="btn-add-cart"
                        id="addCartBtn"
                        onclick="addToCartQty(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['product_name'])) ?>')"
                        <?= $is_out ? 'disabled' : '' ?>>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <?= $is_out ? 'Out of Stock' : 'Add to Cart' ?>
                </button>

                <button class="btn-wishlist-main <?= $in_wishlist ? 'active' : '' ?>"
                        id="wishBtnMain"
                        onclick="handleWishlist(<?= $p['id'] ?>)"
                        title="Wishlist">
                    <?= $in_wishlist ? '♥' : '♡' ?>
                </button>
            </div>

            <!-- Meta / specs -->
            <div class="pd-meta">
                <div class="pm-row">
                    <span class="pm-label">Brand</span>
                    <span class="pm-value"><?= htmlspecialchars($p['brand']) ?></span>
                </div>
                <div class="pm-row">
                    <span class="pm-label">Category</span>
                    <span class="pm-value"><?= htmlspecialchars($p['category']) ?></span>
                </div>
                <?php if (!empty($p['sku'])): ?>
                <div class="pm-row">
                    <span class="pm-label">SKU</span>
                    <span class="pm-value" style="font-family:'JetBrains Mono',monospace;font-size:12px"><?= htmlspecialchars($p['sku']) ?></span>
                </div>
                <?php endif; ?>
                <div class="pm-row">
                    <span class="pm-label">Availability</span>
                    <span class="pm-value" style="color:<?= $is_out ? '#ef4444' : '#10b981' ?>">
                        <?= $is_out ? 'Out of Stock' : 'In Stock' ?>
                    </span>
                </div>
            </div>

            <!-- Description -->
            <?php if (!empty($p['description'])): ?>
            <div class="pd-desc-box">
                <div class="pd-desc-title">About this product</div>
                <div class="pd-desc-text"><?= htmlspecialchars($p['description']) ?></div>
            </div>
            <?php endif; ?>

            <!-- Trust badges -->
            <div class="trust-row">
                <div class="trust-badge"><span>🚀</span> Fast Dispatch</div>
                <div class="trust-badge"><span>🔄</span> 30-Day Returns</div>
                <div class="trust-badge"><span>🛡️</span> 2-Year Warranty</div>
                <div class="trust-badge"><span>🔒</span> Secure Checkout</div>
            </div>

        </div><!-- /product-detail -->
    </div><!-- /product-main -->

    <?php require_once "reviews_section.php"; ?>

    <!-- ══════════════════════════════════════════
         FIX: FREQUENTLY BOUGHT TOGETHER
         Real co-purchase data instead of static
         same-category filler. Only renders when
         there's at least one actual pairing in
         order history.
    ══════════════════════════════════════════ -->
    <?php if (!empty($frequently_bought)): ?>
    <div class="related-section">
        <div class="related-header">
            <div class="related-title">🛒 Frequently Bought Together</div>
            <span style="font-size:12px;color:var(--muted)">Based on real customer orders</span>
        </div>

        <div class="related-grid products-grid">
            <?php foreach ($frequently_bought as $r): ?>
                <div class="product-card" onclick="window.location='product.php?id=<?= $r['id'] ?>'">
                    <div class="product-img-wrap">
                        <img src="<?= product_img($r['img']) ?>"
                             alt="<?= htmlspecialchars($r['name']) ?>" loading="lazy">
                        <span class="product-badge badge-new">Often bought together</span>
                        <div class="product-wishlist"
                             id="wl<?= $r['id'] ?>"
                             onclick="event.stopPropagation(); toggleWishlist(<?= $r['id'] ?>)">♡</div>
                        <div class="product-overlay">
                            <button class="btn-quick-add" 
                                    onclick="event.stopPropagation(); addToCart(<?= $r['id'] ?>, '<?= addslashes(htmlspecialchars($r['name'])) ?>')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2.5">
                                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                    <line x1="3" y1="6" x2="21" y2="6"/>
                                    <path d="M16 10a4 4 0 0 1-8 0"/>
                                </svg>
                                Add to Cart
                            </button>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?= htmlspecialchars($r['category'] ?? '') ?></div>
                        <div class="product-name"><?= htmlspecialchars($r['name']) ?></div>
                        <div class="product-brand"><?= htmlspecialchars($r['brand']) ?></div>
                        <div class="product-rating">
                            <span class="stars">★★★★★</span>
                            <span class="rating-count">(<?= number_format($r['sold']) ?> sold)</span>
                        </div>
                        <div class="product-price">
                            <span class="price-current">$<?= number_format($r['price'], 2) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>


    <!-- ── RELATED PRODUCTS (fallback: same category) ── -->
    <?php if (!empty($related)): ?>
    <div class="related-section">
        <div class="related-header">
            <div class="related-title">You Might Also Like</div>
            <a href="products.php?cat=<?= urlencode($p['category']) ?>" class="related-link">
                View all in <?= htmlspecialchars($p['category']) ?> →
            </a>
        </div>

        <div class="related-grid products-grid">
            <?php foreach ($related as $r): ?>
                <div class="product-card" onclick="window.location='product.php?id=<?= $r['id'] ?>'">
                    <div class="product-img-wrap">
                        <img src="<?= product_img($r['img']) ?>"
                             alt="<?= htmlspecialchars($r['name']) ?>" loading="lazy">
                        <div class="product-wishlist"
                             id="wl<?= $r['id'] ?>"
                             onclick="event.stopPropagation(); toggleWishlist(<?= $r['id'] ?>)">♡</div>
                        <div class="product-overlay">
                            <button class="btn-quick-add"
                                    onclick="event.stopPropagation(); addToCart(<?= $r['id'] ?>, '<?= addslashes(htmlspecialchars($r['name'])) ?>')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2.5">
                                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                    <line x1="3" y1="6" x2="21" y2="6"/>
                                    <path d="M16 10a4 4 0 0 1-8 0"/>
                                </svg>
                                Add to Cart
                            </button>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?= htmlspecialchars($r['category'] ?? '') ?></div>
                        <div class="product-name"><?= htmlspecialchars($r['name']) ?></div>
                        <div class="product-brand"><?= htmlspecialchars($r['brand']) ?></div>
                        <div class="product-rating">
                            <span class="stars">★★★★★</span>
                            <span class="rating-count">(<?= number_format($r['sold']) ?> sold)</span>
                        </div>
                        <div class="product-price">
                            <span class="price-current">$<?= number_format($r['price'], 2) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /product-page -->

<?php require_once "footer.php" ?>

<!-- Toast -->
<div class="toast" id="toast">
    <span class="toast-icon" id="toastIcon">✓</span>
    <span id="toastMsg">Item added to cart</span>
</div>

</body>