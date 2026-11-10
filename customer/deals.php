<?php


if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";
require_once __DIR__ . "/lang/translate.php";

/* ══════════════════════════════════════════
   HELPERS
══════════════════════════════════════════ */

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

function cat_icon(string $name): string {
    global $cat_icons;
    return $cat_icons[strtolower($name)] ?? '📦';
}

function product_img(?string $img): string {
    if (!empty($img)) {
        return "/LectroHub/admin/img/" . htmlspecialchars($img);
    }
    return "https://placehold.co/400x400/070a10/00e5ff?text=No+Image";
}

/* ══════════════════════════════════════════
   DEAL TYPE FILTER
══════════════════════════════════════════ */
$deal_type = trim($_GET['type'] ?? 'all');
$valid_types = ['all', 'flash', 'clearance', 'bundle'];
if (!in_array($deal_type, $valid_types)) $deal_type = 'all';

/* ══════════════════════════════════════════
   FETCH DEALS FROM DB
   "Deals" = active products with a sale_price set (sale_price < price)
   OR featured products if no sale_price column exists yet.
   Adjust the query to match your actual schema.
══════════════════════════════════════════ */

$flash_products      = [];
$clearance_products  = [];
$bundle_products     = [];
$all_deals           = [];

try {
    /* ── Flash deals: featured products, highest price first (premium) ── */
    $stmt = $conn->query("
        SELECT
            p.id,
            p.product_name  AS name,
            p.brand_legacy  AS brand,
            p.price,
            p.stock,
            p.sold,
            p.image         AS img,
            p.featured,
            c.category_name AS category,
            ROUND(p.price * 0.75, 2) AS sale_price,
            25 AS discount_pct
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
          AND p.featured = 1
          AND p.stock > 0
        ORDER BY p.price DESC
        LIMIT 6
    ");
    $flash_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ── Clearance: low stock, sorted by stock ASC ── */
    $stmt = $conn->query("
        SELECT
            p.id,
            p.product_name  AS name,
            p.brand_legacy  AS brand,
            p.price,
            p.stock,
            p.sold,
            p.image         AS img,
            p.featured,
            c.category_name AS category,
            ROUND(p.price * 0.85, 2) AS sale_price,
            15 AS discount_pct
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
          AND p.stock > 0
          AND p.stock < 15
        ORDER BY p.stock ASC
        LIMIT 8
    ");
    $clearance_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ── Bundle deals: bestsellers ── */
    $stmt = $conn->query("
        SELECT
            p.id,
            p.product_name  AS name,
            p.brand_legacy  AS brand,
            p.price,
            p.stock,
            p.sold,
            p.image         AS img,
            p.featured,
            c.category_name AS category,
            ROUND(p.price * 0.80, 2) AS sale_price,
            20 AS discount_pct
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
          AND p.sold > 0
          AND p.stock > 0
        ORDER BY p.sold DESC
        LIMIT 6
    ");
    $bundle_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Merge for "all" view */
    $seen = [];
    foreach (array_merge($flash_products, $clearance_products, $bundle_products) as $p) {
        if (!isset($seen[$p['id']])) {
            $seen[$p['id']] = true;
            $all_deals[] = $p;
        }
    }

} catch (PDOException $e) {
    error_log("LectroHub deals DB error: " . $e->getMessage());
}

/* Flash deal countdown end time — 24hrs from midnight today */
$countdown_end = mktime(0, 0, 0, date('n'), date('j') + 1);

$cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$user_name  = $_SESSION['user_name'] ?? null;
?>
<?php require_once "head.php" ?>

<body>

    <!-- ── NAVBAR ── -->
    <?php require_once "navbar.php" ?>

    <!-- mobile hamburger is appended by navbar.php, close the nav -->
    <button class="nav-menu-btn" onclick="toggleDrawer()" aria-label="Menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
    </button>
    </nav>

    <!-- Mobile drawer -->
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
         HERO
    ══════════════════════════════════════════ -->
    <div class="deals-hero">
        <div class="deals-hero-tag"><?= htmlspecialchars(t('limited_time_deals')) ?></div>

        <h1><?= htmlspecialchars(t('save_big_1')) ?><br><em><?= htmlspecialchars(t('save_big_2')) ?></em></h1>
        <p><?= htmlspecialchars(t('deals_hero_sub')) ?></p>

        <!-- Countdown clock -->
        <div class="countdown-wrap">
            <span class="cd-label"><?= htmlspecialchars(t('flash_sale_ends_in')) ?></span>
            <div class="cd-unit">
                <span class="cd-num" id="cdHours">00</span>
                <span class="cd-sub"><?= htmlspecialchars(t('hrs')) ?></span>
            </div>
            <span class="cd-sep">:</span>
            <div class="cd-unit">
                <span class="cd-num" id="cdMins">00</span>
                <span class="cd-sub"><?= htmlspecialchars(t('min')) ?></span>
            </div>
            <span class="cd-sep">:</span>
            <div class="cd-unit">
                <span class="cd-num" id="cdSecs">00</span>
                <span class="cd-sub"><?= htmlspecialchars(t('sec')) ?></span>
            </div>
        </div>

        <!-- Deal type tabs -->
        <div class="deal-tabs">
            <a href="deals.php"
                class="deal-tab <?= $deal_type === 'all'       ? 'active' : '' ?>"><?= htmlspecialchars(t('tab_all_deals')) ?></a>
            <a href="deals.php?type=flash"
                class="deal-tab deal-tab-flash <?= $deal_type === 'flash'     ? 'active' : '' ?>"><?= htmlspecialchars(t('tab_flash_sale')) ?></a>
            <a href="deals.php?type=clearance"
                class="deal-tab deal-tab-clear <?= $deal_type === 'clearance' ? 'active' : '' ?>"><?= htmlspecialchars(t('tab_clearance')) ?></a>
            <a href="deals.php?type=bundle"
                class="deal-tab deal-tab-bundle <?= $deal_type === 'bundle'   ? 'active' : '' ?>"><?= htmlspecialchars(t('tab_bestsellers')) ?></a>
        </div>
    </div>


    <?php
    /* Which sections to show */
    $show_flash     = in_array($deal_type, ['all', 'flash']);
    $show_clearance = in_array($deal_type, ['all', 'clearance']);
    $show_bundle    = in_array($deal_type, ['all', 'bundle']);
    ?>


    <!-- ══════════════════════════════════════════
         FLASH SALE BANNER (only on flash / all)
    ══════════════════════════════════════════ -->
    <?php if ($show_flash): ?>
    <div class="flash-banner">
        <div class="flash-banner-text">
            <h2><?= htmlspecialchars(t('flash_sale_banner_title')) ?></h2>
            <p><?= htmlspecialchars(t('flash_sale_banner_sub')) ?></p>
        </div>
        <div class="flash-banner-actions">
            <a href="deals.php?type=flash" class="btn btn-primary" style="background:#e0185a;border-color:#e0185a">
                <?= htmlspecialchars(t('shop_flash_deals')) ?>
            </a>
        </div>
    </div>
    <?php endif; ?>


    <!-- ══════════════════════════════════════════
         FLASH SALE PRODUCTS
    ══════════════════════════════════════════ -->
    <?php if ($show_flash): ?>
    <section class="deals-section">
        <div class="deals-section-header">
            <div class="dsh-icon dsh-icon-flash">⚡</div>
            <div class="dsh-text">
                <h2><?= htmlspecialchars(t('flash_sale_section_title')) ?></h2>
                <p><?= htmlspecialchars(t('flash_sale_section_sub')) ?></p>
            </div>
            <a href="products.php?featured=1" class="dsh-link">
                <?= htmlspecialchars(t('view_all_featured')) ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </a>
        </div>

        <div class="deals-grid">
            <?php if (empty($flash_products)): ?>
            <div class="deals-empty">
                <div class="de-icon">⚡</div>
                <h3><?= htmlspecialchars(t('no_flash_deals')) ?></h3>
                <p><?= htmlspecialchars(t('check_back_soon')) ?></p>
            </div>
            <?php else: ?>
            <?php foreach ($flash_products as $p):
                    $stock_pct = min(100, (int)$p['stock']);
                    $savings   = number_format($p['price'] - $p['sale_price'], 2);
                ?>
            <div class="deal-card" onclick="window.location='product.php?id=<?= $p['id'] ?>'">
                <div class="deal-img-wrap">
                    <img src="<?= product_img($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                    <span class="deal-badge badge-flash"><?= htmlspecialchars(t('badge_flash_label')) ?></span>
                    <span class="deal-discount">−<?= $p['discount_pct'] ?>%</span>
                    <div class="deal-overlay">
                        <button class="btn-deal-add"
                            onclick="event.stopPropagation(); addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <path d="M16 10a4 4 0 0 1-8 0" />
                            </svg>
                            <?= htmlspecialchars(t('add_to_cart')) ?>
                        </button>
                    </div>
                </div>
                <div class="deal-info">
                    <div class="deal-category"><?= htmlspecialchars($p['category']) ?></div>
                    <div class="deal-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="deal-brand"><?= htmlspecialchars($p['brand']) ?></div>
                    <div class="deal-price-row">
                        <span class="deal-price-now">$<?= number_format($p['sale_price'], 2) ?></span>
                        <span class="deal-price-was">$<?= number_format($p['price'], 2) ?></span>
                        <span class="deal-savings"><?= htmlspecialchars(t('save_x', ['x' => $savings])) ?></span>
                    </div>
                    <div class="deal-stock-row">
                        <div class="deal-stock-bar">
                            <div class="deal-stock-fill" style="width:<?= $stock_pct ?>%"></div>
                        </div>
                        <span
                            class="deal-stock-label"><?= htmlspecialchars(t('x_left_label', ['x' => $p['stock']])) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>


    <!-- ══════════════════════════════════════════
         CLEARANCE
    ══════════════════════════════════════════ -->
    <?php if ($show_clearance): ?>
    <section class="deals-section">
        <div class="deals-section-header">
            <div class="dsh-icon dsh-icon-clear">🏷️</div>
            <div class="dsh-text">
                <h2><?= htmlspecialchars(t('clearance_title')) ?></h2>
                <p><?= htmlspecialchars(t('clearance_sub')) ?></p>
            </div>
            <a href="products.php?instock=1&sort=price_asc" class="dsh-link">
                <?= htmlspecialchars(t('browse_all')) ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </a>
        </div>

        <div class="deals-grid wide">
            <?php if (empty($clearance_products)): ?>
            <div class="deals-empty">
                <div class="de-icon">🏷️</div>
                <h3><?= htmlspecialchars(t('clearance_empty_title')) ?></h3>
                <p><?= htmlspecialchars(t('clearance_empty_sub')) ?></p>
            </div>
            <?php else: ?>
            <?php foreach ($clearance_products as $p):
                    $stock_pct = min(100, (int)$p['stock'] * 7); /* exaggerate low stock visually */
                    $stock_pct = min($stock_pct, 35); /* cap at 35% to emphasise scarcity */
                    $savings   = number_format($p['price'] - $p['sale_price'], 2);
                ?>
            <div class="deal-card" onclick="window.location='product.php?id=<?= $p['id'] ?>'">
                <div class="deal-img-wrap">
                    <img src="<?= product_img($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                    <span class="deal-badge badge-clear"><?= htmlspecialchars(t('badge_clearance_label')) ?></span>
                    <span class="deal-discount">−<?= $p['discount_pct'] ?>%</span>
                    <div class="deal-overlay">
                        <button class="btn-deal-add" style="background:#f59e0b;color:#000"
                            onclick="event.stopPropagation(); addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <path d="M16 10a4 4 0 0 1-8 0" />
                            </svg>
                            <?= htmlspecialchars(t('grab_it')) ?>
                        </button>
                    </div>
                </div>
                <div class="deal-info">
                    <div class="deal-category"><?= htmlspecialchars($p['category']) ?></div>
                    <div class="deal-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="deal-brand"><?= htmlspecialchars($p['brand']) ?></div>
                    <div class="deal-price-row">
                        <span class="deal-price-now">$<?= number_format($p['sale_price'], 2) ?></span>
                        <span class="deal-price-was">$<?= number_format($p['price'], 2) ?></span>
                        <span class="deal-savings"><?= htmlspecialchars(t('save_x', ['x' => $savings])) ?></span>
                    </div>
                    <div class="deal-stock-row">
                        <div class="deal-stock-bar">
                            <div class="deal-stock-fill" style="width:<?= $stock_pct ?>%"></div>
                        </div>
                        <span class="deal-stock-label"
                            style="color:#f59e0b"><?= htmlspecialchars(t('only_x_left_bang', ['x' => $p['stock']])) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>


    <!-- ══════════════════════════════════════════
         BEST SELLERS / BUNDLES
    ══════════════════════════════════════════ -->
    <?php if ($show_bundle): ?>
    <section class="deals-section">
        <div class="deals-section-header">
            <div class="dsh-icon dsh-icon-bundle">📦</div>
            <div class="dsh-text">
                <h2><?= htmlspecialchars(t('bestsellers_title')) ?></h2>
                <p><?= htmlspecialchars(t('bestsellers_sub')) ?></p>
            </div>
            <a href="products.php?sort=bestseller" class="dsh-link">
                <?= htmlspecialchars(t('view_bestsellers')) ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12h14M12 5l7 7-7 7" />
                </svg>
            </a>
        </div>

        <div class="deals-grid">
            <?php if (empty($bundle_products)): ?>
            <div class="deals-empty">
                <div class="de-icon">📦</div>
                <h3><?= htmlspecialchars(t('no_bestsellers')) ?></h3>
                <p><?= htmlspecialchars(t('no_bestsellers_sub')) ?></p>
            </div>
            <?php else: ?>
            <?php foreach ($bundle_products as $p):
                    $stock_pct = min(100, (int)$p['stock']);
                    $savings   = number_format($p['price'] - $p['sale_price'], 2);
                ?>
            <div class="deal-card" onclick="window.location='product.php?id=<?= $p['id'] ?>'">
                <div class="deal-img-wrap">
                    <img src="<?= product_img($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                    <span class="deal-badge badge-bundle"><?= htmlspecialchars(t('top_seller')) ?></span>
                    <span class="deal-discount">−<?= $p['discount_pct'] ?>%</span>
                    <div class="deal-overlay">
                        <button class="btn-deal-add" style="background:#7c3aed"
                            onclick="event.stopPropagation(); addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <path d="M16 10a4 4 0 0 1-8 0" />
                            </svg>
                            <?= htmlspecialchars(t('add_to_cart')) ?>
                        </button>
                    </div>
                </div>
                <div class="deal-info">
                    <div class="deal-category"><?= htmlspecialchars($p['category']) ?></div>
                    <div class="deal-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="deal-brand"><?= htmlspecialchars($p['brand']) ?></div>
                    <div class="deal-price-row">
                        <span class="deal-price-now">$<?= number_format($p['sale_price'], 2) ?></span>
                        <span class="deal-price-was">$<?= number_format($p['price'], 2) ?></span>
                        <span class="deal-savings"><?= htmlspecialchars(t('save_x', ['x' => $savings])) ?></span>
                    </div>
                    <div class="deal-stock-row">
                        <div class="deal-stock-bar">
                            <div class="deal-stock-fill" style="width:<?= $stock_pct ?>%"></div>
                        </div>
                        <span
                            class="deal-stock-label"><?= htmlspecialchars(t('x_sold_label', ['x' => number_format($p['sold'])])) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>


    <!-- ══════════════════════════════════════════
         DEALS NEWSLETTER
    ══════════════════════════════════════════ -->
    <div class="deals-newsletter">
        <div class="deals-newsletter-text">
            <h3><?= htmlspecialchars(t('deal_alerts_title')) ?></h3>
            <p><?= htmlspecialchars(t('deal_alerts_sub')) ?></p>
        </div>
        <form class="deals-newsletter-form" onsubmit="subscribeDeals(event)">
            <input type="email" placeholder="<?= htmlspecialchars(t('email_placeholder')) ?>" id="dealsEmail" required>
            <button type="submit"><?= htmlspecialchars(t('notify_me')) ?></button>
        </form>
    </div>


    <!-- ── FOOTER ── -->
    <?php require_once "footer.php" ?>

    <!-- ── TOAST ── -->
    <div class="toast" id="toast">
        <span class="toast-icon" id="toastIcon">✓</span>
        <span id="toastMsg"><?= htmlspecialchars(t('toast_done')) ?></span>
    </div>

    <script>
    // Countdown end time from PHP (Unix timestamp -> milliseconds)
    const countdownEnd = <?= $countdown_end ?> * 1000;

    function updateCountdown() {

        // Current time
        const now = Date.now();

        // Time remaining
        const distance = countdownEnd - now;

        // If countdown is finished
        if (distance <= 0) {
            document.getElementById("cdHours").textContent = "00";
            document.getElementById("cdMins").textContent = "00";
            document.getElementById("cdSecs").textContent = "00";
            clearInterval(timer);
            return;
        }

        // Calculate hours, minutes and seconds
        const hours = Math.floor(distance / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        // Display with leading zero (01 instead of 1)
        document.getElementById("cdHours").textContent = String(hours).padStart(2, "0");
        document.getElementById("cdMins").textContent = String(minutes).padStart(2, "0");
        document.getElementById("cdSecs").textContent = String(seconds).padStart(2, "0");
    }

    // Run immediately when page loads
    updateCountdown();

    // Update every second
    const timer = setInterval(updateCountdown, 1000);
    </script>

</body>

</html>