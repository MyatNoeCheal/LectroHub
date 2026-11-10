<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";
require_once __DIR__ . "/lang/translate.php";

/* FETCH LIVE DATA FROM DB */

$featured_products = [];
$categories        = [];
$brands            = [];
$ticker_products   = [];

try {

    /* Featured products — active, featured=1, in stock */
    $stmt = $conn->query("
        SELECT
            p.id,
            p.product_name  AS name,
            p.brand_legacy  AS brand,
            p.price,
            p.stock,
            p.sold,
            p.image         AS img,
            c.category_name AS category
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.status   = 'active'
          AND p.featured  = 1
          AND p.stock     > 0
        ORDER BY p.id DESC
        LIMIT 6
    ");
    $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Categories with live product counts (active products only) */
    $stmt = $conn->query("
        SELECT
            c.id,
            c.category_name AS name,
            COUNT(p.id)     AS count
        FROM categories c
        LEFT JOIN products p
            ON p.category_id = c.id
           AND p.status = 'active'
        WHERE c.category_name != 'All'
        GROUP BY c.id, c.category_name
        ORDER BY c.category_name
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Brands from the brands table (same source as admin) */
    $brands = $conn->query("
        SELECT brand_name FROM brands ORDER BY brand_name
    ")->fetchAll(PDO::FETCH_COLUMN);

    /* Ticker — latest 10 active products */
    $stmt = $conn->query("
        SELECT p.product_name AS name, p.price, c.category_name AS category
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
        ORDER BY p.id DESC
        LIMIT 10
    ");
    $ticker_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Live hero stats */
    $total_products  = $conn->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $total_customers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
} catch (PDOException $e) {
    error_log("LectroHub index DB error: " . $e->getMessage());
    $total_products  = 0;
    $total_customers = 0;
}

/* ── Category icon map ── */
$cat_icons = [
    'smartphones'  => '📱',
    'laptops'      => '💻',
    'audio'        => '🎧',
    'headphones'   => '🎧',
    'cameras'      => '📷',
    'gaming'       => '🎮',
    'wearables'    => '⌚',
    'smart home'   => '🏠',
    'tablets'      => '📲',
    'drones'       => '🚁',
    'televisions'  => '🖥️',
    'tvs'          => '🖥️',
    'accessories'  => '🔌',
    'networking'   => '📡',
    'storage'      => '💾',
    'monitors'     => '🖥️',
];

function cat_icon(string $name): string
{
    global $cat_icons;
    return $cat_icons[strtolower($name)] ?? '📦';
}

/* ── Product image path helper ── */
function product_img(?string $img): string
{
    if (!empty($img)) {
        return "/LectroHub/admin/img/" . $img;
    }
    return "https://placehold.co/400x400/070a10/00e5ff?text=No+Image";
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$user_name  = $_SESSION['user']['full_name'] ?? null;


/* ══════════════════════════════════════════
   PERSONALIZED RECOMMENDATIONS
   Amazon-style "based on your purchases" —
   uses THIS customer's own order history,
   unlike product.php's FBT which is global
   co-purchase data. Returns [] for guests or
   customers with no order history yet, so the
   section can cleanly hide itself.
══════════════════════════════════════════ */
function get_recommended_for_user(PDO $conn, int $user_id, int $limit = 6): array
{
    // Categories & brands this customer has actually bought from
    $hist_stmt = $conn->prepare("
        SELECT DISTINCT p.category_id, p.brand_legacy AS brand
        FROM order_items oi
        INNER JOIN orders   o ON o.id = oi.order_id
        INNER JOIN products p ON p.id = oi.product_id
        WHERE o.user_id = ?
    ");
    $hist_stmt->execute([$user_id]);
    $history = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($history)) return []; // never ordered — nothing to personalize on yet

    $cat_ids = array_values(array_unique(array_column($history, 'category_id')));
    $brands  = array_values(array_unique(array_column($history, 'brand')));

    // Never recommend something they've already bought
    $bought_stmt = $conn->prepare("
        SELECT DISTINCT oi.product_id
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE o.user_id = ?
    ");
    $bought_stmt->execute([$user_id]);
    $bought_ids = $bought_stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($bought_ids)) $bought_ids = [0];

    $cat_ph    = implode(',', array_fill(0, count($cat_ids), '?'));
    $brand_ph  = implode(',', array_fill(0, count($brands), '?'));
    $bought_ph = implode(',', array_fill(0, count($bought_ids), '?'));

    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.product_name  AS name,
            p.brand_legacy  AS brand,
            p.price,
            p.stock,
            p.sold,
            p.image         AS img,
            c.category_name AS category,
            (CASE WHEN p.brand_legacy IN ($brand_ph) THEN 2 ELSE 1 END) AS relevance
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE p.status     = 'active'
          AND p.stock      > 0
          AND p.category_id IN ($cat_ph)
          AND p.id NOT IN ($bought_ph)
        ORDER BY relevance DESC, p.sold DESC
        LIMIT $limit
    ");
    $stmt->execute(array_merge($brands, $cat_ids, $bought_ids));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$recommended_products = [];
if (!empty($_SESSION['user']['id'])) {
    $recommended_products = get_recommended_for_user($conn, (int)$_SESSION['user']['id']);
}

/* ══════════════════════════════════════════
   FIX: WISHLIST HEART STATE
   The product cards below always rendered the
   heart as an empty outline (♡) regardless of
   whether the product was already wishlisted —
   they never checked any wishlist state at all.
   products.php already does this correctly, so
   this mirrors that same logic: DB lookup for
   logged-in users, $_SESSION['wishlist'] for
   guests.
══════════════════════════════════════════ */
$wishlist_ids = [];
$user_id_home = $_SESSION['user']['id'] ?? null;
if ($user_id_home) {
    $wl = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wl->execute([$user_id_home]);
    $wishlist_ids = $wl->fetchAll(PDO::FETCH_COLUMN);
} elseif (!empty($_SESSION['wishlist']) && is_array($_SESSION['wishlist'])) {
    $wishlist_ids = $_SESSION['wishlist'];
}

?>


<?php require_once "head.php" ?>

<body>

    <!-- ── NAVBAR ── -->
    <?php require_once "navbar.php"  ?>


    <div class="nav-drawer" id="navDrawer">
        <div class="nav-search" style="max-width:100%;margin:0 0 4px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
            </svg>
            <input type="text" placeholder="<?= htmlspecialchars(t('search_placeholder')) ?>" id="navSearchMobile">
        </div>
        <a href="products.php" class="nav-btn nav-btn-ghost"><?= htmlspecialchars(t('shop')) ?></a>
        <a href="deals.php" class="nav-btn nav-btn-ghost"><?= htmlspecialchars(t('deals')) ?></a>
        <a href="cart.php" class="nav-btn nav-btn-cart"><?= htmlspecialchars(t('cart')) ?><?php if ($cart_count > 0): ?>
            (<?= $cart_count ?>)<?php endif; ?></a>
        <?php if ($user_name): ?>
        <a href="account.php" class="nav-btn nav-btn-primary"><?= htmlspecialchars($user_name) ?></a>
        <?php else: ?>
        <a href="../admin/signup.php" class="nav-btn nav-btn-primary"><?= htmlspecialchars(t('get_started')) ?></a>
        <?php endif; ?>
    </div>


    <!-- ══════════════════════════════════════════
         HERO — now the "lh-hero-dark" cinematic
         variant: dark backdrop, optional looping
         video, and a real 3D circuit-constellation
         scene rendered with Three.js. Everything
         below the hero stays on the site's normal
         light theme — the classic Apple pattern of
         a dark, dramatic hero flowing into clean
         content sections.
    ══════════════════════════════════════════ -->
    <section class="hero lh-hero-dark">

        <!-- Optional cinematic loop. Drop an .mp4 at
             customer/assets/video/hero-loop.mp4 to enable it.
             If the file is missing, the browser fires the error
             events below and this block hides itself — the 3D
             scene + gradient carry the hero on their own. -->
        <div class="lh-hero-video" id="lhHeroVideoWrap">
            <video autoplay muted loop playsinline
                onerror="document.getElementById('lhHeroVideoWrap').style.display='none'">
                <source src="assets/video/white.mp4" type="video/mp4"
                    onerror="document.getElementById('lhHeroVideoWrap').style.display='none'">
            </video>
        </div>

        <div class="hero-bg"></div>
        <div class="hero-grid-lines"></div>

        <div class="hero-content">
            <div class="hero-eyebrow"><?= htmlspecialchars(t('hero_eyebrow')) ?></div>

            <h1 class="hero-title">
                <?= htmlspecialchars(t('hero_title_1')) ?><br>
                <span class="line2"><?= htmlspecialchars(t('hero_title_2')) ?></span><br>
                <span class="line3"><?= htmlspecialchars(t('hero_title_3')) ?></span>
            </h1>

            <p class="hero-sub">
                <?= htmlspecialchars(t('hero_sub')) ?>
            </p>

            <div class="hero-ctas">
                <a href="products.php" class="btn-hero-primary">
                    <?= htmlspecialchars(t('explore_products')) ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </a>
                <a href="deals.php" class="btn-hero-secondary">
                    <?= htmlspecialchars(t('view_deals')) ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                    </svg>
                </a>
            </div>

            <div class="hero-stats">
                <div>
                    <div class="stat-num"><?= number_format((int)$total_products) ?><span>+</span></div>
                    <div class="stat-label"><?= htmlspecialchars(t('stat_products')) ?></div>
                </div>
                <div>
                    <div class="stat-num">4.9<span>★</span></div>
                    <div class="stat-label"><?= htmlspecialchars(t('stat_rating')) ?></div>
                </div>
                <div>
                    <div class="stat-num">
                        <?php
                        $c = (int)$total_customers;
                        echo $c >= 1000 ? round($c / 1000) . 'K' : $c;
                        ?>
                        <span>+</span>
                    </div>
                    <div class="stat-label"><?= htmlspecialchars(t('stat_customers')) ?></div>
                </div>
            </div>
        </div>

        <!-- Hero visual: first 3 featured products -->
        <div class="hero-visual">
            <div class="hero-device-grid">
                <?php
                $hero_items = array_slice($featured_products, 0, 3);
                foreach ($hero_items as $p):
                ?>
                <div class="hero-device-card">
                    <img src="<?= product_img($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    <div class="device-card-info">
                        <div class="device-card-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="device-card-price">$<?= number_format($p['price'], 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($hero_items)): ?>
                <div class="hero-device-card"
                    style="grid-column:span 2;display:flex;align-items:center;justify-content:center;min-height:160px;color:var(--muted);font-size:13px">
                    <?= htmlspecialchars(t('no_featured')) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>


    <!-- ── TICKER ── -->
    <?php if (!empty($ticker_products)): ?>
    <div class="ticker-bar">
        <div class="ticker-inner" id="ticker">
            <?php
                /* Triple the array for seamless infinite scroll */
                $all_ticks = array_merge($ticker_products, $ticker_products, $ticker_products);
                foreach ($all_ticks as $t):
                ?>
            <span class="ticker-item">
                <?= cat_icon($t['category']) ?>
                <strong><?= htmlspecialchars($t['name']) ?></strong>
                <span style="color:var(--accent)">$<?= number_format($t['price'], 2) ?></span>
                <span class="ticker-dot">·</span>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>


    <!-- ── CATEGORIES ── -->
    <section class="categories-section">
        <div class="section-header">
            <div>
                <div class="section-tag"><?= htmlspecialchars(t('browse')) ?></div>
                <div class="section-title"><?= htmlspecialchars(t('shop_by_category')) ?></div>
            </div>
        </div>

        <div class="categories-grid">
            <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $cat): ?>
            <a href="products.php?cat=<?= urlencode($cat['name']) ?>" class="category-card">
                <div class="cat-icon"><?= cat_icon($cat['name']) ?></div>
                <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
                <div class="cat-count"><?= (int)$cat['count'] ?> <?= htmlspecialchars(t('items_suffix')) ?></div>
            </a>
            <?php endforeach; ?>
            <?php else: ?>
            <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted);font-size:13px">
                <?= htmlspecialchars(t('no_categories')) ?>
            </div>
            <?php endif; ?>
        </div>
    </section>


    <!-- ── RECOMMENDED FOR YOU (personalized) ── -->
    <?php if (!empty($recommended_products)): ?>
    <section>
        <div class="section-header">
            <div>
                <div class="section-tag">Just For You</div>
                <div class="section-title">Recommended For You</div>
            </div>
        </div>

        <div class="products-grid">
            <?php foreach ($recommended_products as $p):
                $in_wl_home = in_array($p['id'], $wishlist_ids);
            ?>
            <div class="product-card" onclick="window.location='product.php?id=<?= $p['id'] ?>'">
                <div class="product-img-wrap">
                    <img src="<?= product_img($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                    <span class="product-badge badge-new">For You</span>
                    <div class="product-wishlist <?= $in_wl_home ? 'active' : '' ?>" id="wl<?= $p['id'] ?>"
                        onclick="event.stopPropagation(); toggleWishlist(<?= $p['id'] ?>)"><?= $in_wl_home ? '♥' : '♡' ?>
                    </div>
                    <div class="product-overlay">
                        <button class="btn-quick-add"
                            onclick="event.stopPropagation(); addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <path d="M16 10a4 4 0 0 1-8 0" />
                            </svg>
                            Add to Cart
                        </button>
                    </div>
                </div>
                <div class="product-info">
                    <div class="product-category"><?= htmlspecialchars($p['category']) ?></div>
                    <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="product-rating">
                        <span class="stars">★★★★★</span>
                        <span class="rating-count">(<?= number_format($p['sold']) ?> sold)</span>
                    </div>
                    <div class="product-price">
                        <span class="price-current">$<?= number_format($p['price'], 2) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>


    <!-- ── FEATURED PRODUCTS ── -->
    <section>
        <div class="section-header">
            <div>
                <div class="section-tag"><?= htmlspecialchars(t('trending')) ?></div>
                <div class="section-title"><?= htmlspecialchars(t('featured_products')) ?></div>
            </div>
            <a href="products.php" class="section-link">
                <?= htmlspecialchars(t('view_all')) ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </a>
        </div>

        <div class="products-grid">
            <?php if (!empty($featured_products)): ?>
            <?php foreach ($featured_products as $p): ?>
            <div class="product-card" onclick="window.location='product.php?id=<?= $p['id'] ?>'">
                <div class="product-img-wrap">
                    <img src="<?= product_img($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">

                    <span class="product-badge badge-new"><?= htmlspecialchars(t('featured')) ?></span>

                    <div class="product-wishlist" onclick="event.stopPropagation(); toggleWishlist(<?= $p['id'] ?>)">♡
                    </div>

                    <div class="product-overlay">
                        <button class="btn-quick-add"
                            onclick="event.stopPropagation(); addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <path d="M16 10a4 4 0 0 1-8 0" />
                            </svg>
                            <?= htmlspecialchars(t('add_to_cart')) ?>
                        </button>
                    </div>
                </div>

                <div class="product-info">
                    <div class="product-category"><?= htmlspecialchars($p['category']) ?></div>
                    <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="product-rating">
                        <span class="stars">★★★★★</span>
                        <span class="rating-count">(<?= number_format($p['sold']) ?>
                            <?= htmlspecialchars(t('sold_suffix')) ?>)</span>
                    </div>
                    <div class="product-price">
                        <span class="price-current">$<?= number_format($p['price'], 2) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--muted);font-size:13px">
                <?= htmlspecialchars(t('no_featured')) ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ── PROMOTION ── -->
    <?php require_once "promo_scroller.php" ?>

    <!-- ── BRANDS ── -->
    <?php if (!empty($brands)): ?>
    <div class="brands-section">
        <div class="brands-label"><?= htmlspecialchars(t('trusted_brands')) ?></div>
        <div class="brands-row">
            <?php foreach ($brands as $b): ?>
            <span class="brand-item"><?= htmlspecialchars($b) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>


    <!-- ── FEATURES ── -->
    <section>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🚀</div>
                <div class="feature-title"><?= htmlspecialchars(t('feature_delivery_title')) ?></div>
                <div class="feature-desc"><?= htmlspecialchars(t('feature_delivery_desc')) ?></div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <div class="feature-title"><?= htmlspecialchars(t('feature_payments_title')) ?></div>
                <div class="feature-desc"><?= htmlspecialchars(t('feature_payments_desc')) ?></div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔄</div>
                <div class="feature-title"><?= htmlspecialchars(t('feature_returns_title')) ?></div>
                <div class="feature-desc"><?= htmlspecialchars(t('feature_returns_desc')) ?></div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <div class="feature-title"><?= htmlspecialchars(t('feature_warranty_title')) ?></div>
                <div class="feature-desc"><?= htmlspecialchars(t('feature_warranty_desc')) ?></div>
            </div>
        </div>
    </section>


    <!-- ── NEWSLETTER ── -->
    <div class="newsletter-section">
        <div class="newsletter-card">
            <div class="newsletter-text">
                <h2><?= htmlspecialchars(t('newsletter_title')) ?></h2>
                <p><?= htmlspecialchars(t('newsletter_sub')) ?></p>
            </div>
            <form class="newsletter-form" onsubmit="subscribeNewsletter(event)">
                <input type="email" placeholder="<?= htmlspecialchars(t('email_placeholder')) ?>" id="newsletterEmail"
                    required>
                <button type="submit"><?= htmlspecialchars(t('subscribe')) ?></button>
            </form>
        </div>
    </div>


    <!-- ── FOOTER ── -->
    <?php require_once "footer.php"  ?>

    <!-- ── TOAST ── -->
    <div class="toast" id="toast">
        <span class="toast-icon" id="toastIcon">✓</span>
        <span id="toastMsg"><?= htmlspecialchars(t('item_added_cart')) ?></span>
    </div>



</body>