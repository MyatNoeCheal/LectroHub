<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "../lectrohub_db/connection.php";
require_once __DIR__ . "/lang/translate.php";

/* ══════════════════════════════════════════
   HELPERS  (shared with shop_sidebar.php
            and shop_navbar.php via global scope)
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
    if ($img && $img !== '') {
        return "/LectroHub/admin/img/" . htmlspecialchars($img);
    }
    return "https://placehold.co/400x400/070a10/00e5ff?text=No+Image";
}

function short_desc(?string $text, int $limit = 90): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (mb_strlen($text) <= $limit) return $text;
    return rtrim(mb_substr($text, 0, $limit)) . '…';
}

/* ══════════════════════════════════════════
   URL BUILDER — keep all current params,
   override specific ones
══════════════════════════════════════════ */
function build_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    // Always reset to page 1 when changing a filter or sort
    if (isset($overrides['cat']) || isset($overrides['brand'])
        || isset($overrides['sort']) || isset($overrides['q'])) {
        $params['page'] = 1;
    }
    return '?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
}

/* ══════════════════════════════════════════
   FILTERS FROM URL
══════════════════════════════════════════ */

$active_cat   = trim($_GET['cat']   ?? '');
$active_brand = trim($_GET['brand'] ?? '');
$search_q     = trim($_GET['q']     ?? '');
$sort         = $_GET['sort'] ?? 'newest';
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 12;

/* ══════════════════════════════════════════
   DATA FOR SIDEBAR & NAVBAR INCLUDES
══════════════════════════════════════════ */

// Categories with live product counts
$categories = $conn->query("
    SELECT c.id, c.category_name AS name, COUNT(p.id) AS count
    FROM categories c
    LEFT JOIN products p
        ON p.category_id = c.id AND p.status = 'active'
    WHERE c.category_name != 'All'
    GROUP BY c.id, c.category_name
    ORDER BY c.category_name
")->fetchAll(PDO::FETCH_ASSOC);

// Brands with product counts
$brands_list = $conn->query("
    SELECT b.brand_name AS name, COUNT(p.id) AS count
    FROM brands b
    LEFT JOIN products p
        ON p.brand_legacy = b.brand_name AND p.status = 'active'
    GROUP BY b.brand_name
    ORDER BY b.brand_name
")->fetchAll(PDO::FETCH_ASSOC);

// Price boundaries
$price_range = $conn->query("
    SELECT MIN(price) AS min_price, MAX(price) AS max_price
    FROM products WHERE status = 'active'
")->fetch(PDO::FETCH_ASSOC);

// FIX: round DB min/max to whole dollars. A fractional boundary (e.g.
// 1769.99) combined with the price sliders/number inputs caused
// "please enter a valid value" errors in the browser's own HTML5
// validation. Whole-dollar bounds keep the range clean and predictable.
$price_min  = floor((float)($price_range['min_price'] ?? 0));
$price_max  = ceil((float)($price_range['max_price'] ?? 9999));
$filter_min = isset($_GET['pmin']) ? (float)$_GET['pmin'] : $price_min;
$filter_max = isset($_GET['pmax']) ? (float)$_GET['pmax'] : $price_max;

/* ══════════════════════════════════════════
   PRODUCTS QUERY
══════════════════════════════════════════ */

$where  = ["p.status = 'active'"];
$params = [];

if ($active_cat !== '') {
    $where[]          = "c.category_name = :cat";
    $params[':cat']   = $active_cat;
}

if ($active_brand !== '') {
    $where[]           = "p.brand_legacy = :brand";
    $params[':brand']  = $active_brand;
}

if ($search_q !== '') {
    $where[]       = "(p.product_name LIKE :q OR p.brand_legacy LIKE :q2 OR c.category_name LIKE :q3)";
    $params[':q']  = "%$search_q%";
    $params[':q2'] = "%$search_q%";
    $params[':q3'] = "%$search_q%";
}

if (isset($_GET['instock'])) {
    $where[] = "p.stock > 0";
}

$where[]          = "p.price BETWEEN :pmin AND :pmax";
$params[':pmin']  = $filter_min;
$params[':pmax']  = $filter_max;

$where_sql = implode(' AND ', $where);

$order_sql = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'bestseller' => 'p.sold DESC',
    'name_asc'   => 'p.product_name ASC',
    default      => 'p.id DESC',
};

// Total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) FROM products p
    INNER JOIN categories c ON p.category_id = c.id
    WHERE $where_sql
");
$count_stmt->execute($params);
$total_products = (int)$count_stmt->fetchColumn();
$total_pages    = max(1, (int)ceil($total_products / $per_page));
$page           = min($page, $total_pages);
$offset         = ($page - 1) * $per_page;

// Products for current page
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.product_name  AS name,
        p.brand_legacy  AS brand,
        p.description,
        p.price,
        p.stock,
        p.sold,
        p.image         AS img,
        p.featured,
        c.category_name AS category
    FROM products p
    INNER JOIN categories c ON p.category_id = c.id
    WHERE $where_sql
    ORDER BY $order_sql
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Wishlist IDs for heart state ── */
$wishlist_ids = [];
$user_id_shop = $_SESSION['user']['id'] ?? null;
if ($user_id_shop) {
    $wl = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wl->execute([$user_id_shop]);
    $wishlist_ids = $wl->fetchAll(PDO::FETCH_COLUMN);
} elseif (!empty($_SESSION['wishlist']) && is_array($_SESSION['wishlist'])) {
    $wishlist_ids = $_SESSION['wishlist'];
}

/* ══════════════════════════════════════════
   PERSONALIZED RECOMMENDATIONS
   Same logic as index.php — based on THIS
   customer's own order history (category +
   brand affinity), not global co-purchase data.
   Only shown on page 1 with no active filters,
   so it doesn't clutter a filtered/paginated view.
══════════════════════════════════════════ */
function get_recommended_for_user(PDO $conn, int $user_id, int $limit = 6): array
{
    $hist_stmt = $conn->prepare("
        SELECT DISTINCT p.category_id, p.brand_legacy AS brand
        FROM order_items oi
        INNER JOIN orders   o ON o.id = oi.order_id
        INNER JOIN products p ON p.id = oi.product_id
        WHERE o.user_id = ?
    ");
    $hist_stmt->execute([$user_id]);
    $history = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($history)) return [];

    $cat_ids = array_values(array_unique(array_column($history, 'category_id')));
    $brands  = array_values(array_unique(array_column($history, 'brand')));

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
$show_recommendations = $page === 1 && !$active_cat && !$active_brand && !$search_q;

if ($show_recommendations && !empty($_SESSION['user']['id'])) {
    $recommended_products = get_recommended_for_user($conn, (int)$_SESSION['user']['id']);
}

?>

<?php require_once "head.php" ?>
<?php require_once "navbar.php" ?>

<body>

    <!-- ── SHOP HERO: breadcrumb + title bar ── -->
    <div class="shop-hero">
        <div class="shop-hero-inner">
            <nav class="shop-breadcrumb" aria-label="Breadcrumb">
                <a href="index.php"><?= htmlspecialchars(t('home')) ?></a>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m9 18 6-6-6-6" />
                </svg>
                <?php if ($active_cat): ?>
                <a href="products.php"><?= htmlspecialchars(t('shop')) ?></a>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m9 18 6-6-6-6" />
                </svg>
                <span class="bc-current"><?= htmlspecialchars($active_cat) ?></span>
                <?php elseif ($active_brand): ?>
                <a href="products.php"><?= htmlspecialchars(t('shop')) ?></a>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m9 18 6-6-6-6" />
                </svg>
                <span class="bc-current"><?= htmlspecialchars($active_brand) ?></span>
                <?php elseif ($search_q): ?>
                <a href="products.php"><?= htmlspecialchars(t('shop')) ?></a>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="m9 18 6-6-6-6" />
                </svg>
                <span class="bc-current"><?= htmlspecialchars(t('search_results_for')) ?>:
                    &quot;<?= htmlspecialchars($search_q) ?>&quot;</span>
                <?php else: ?>
                <span class="bc-current"><?= htmlspecialchars(t('all_products')) ?></span>
                <?php endif; ?>
            </nav>

            <div class="shop-hero-body">
                <div class="shop-hero-text">
                    <h1 class="shop-title">
                        <span class="shop-title-icon"><?= $active_cat ? cat_icon($active_cat) : '🛍️' ?></span>
                        <?= $active_cat ? htmlspecialchars($active_cat) : ($active_brand ? htmlspecialchars($active_brand) : htmlspecialchars(t('all_products'))) ?>
                    </h1>
                    <div class="shop-hero-meta">
                        <span class="shop-hero-count">
                            <strong><?= number_format($total_products) ?></strong>
                            <em>
                                <?= $total_products !== 1 ? htmlspecialchars(t('products_count_plural')) : htmlspecialchars(t('product_count')) ?></em>
                        </span>
                    </div>
                </div>

                <!-- Search -->
                <form class="shop-search-form" method="GET" action="products.php">
                    <?php if ($active_cat): ?><input type="hidden" name="cat"
                        value="<?= htmlspecialchars($active_cat) ?>"><?php endif; ?>
                    <?php if ($active_brand): ?><input type="hidden" name="brand"
                        value="<?= htmlspecialchars($active_brand) ?>"><?php endif; ?>
                    <div class="shop-search-wrap">
                        <svg class="shop-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.35-4.35" />
                        </svg>
                        <input type="text" name="q" class="shop-search-input"
                            placeholder="<?= htmlspecialchars(t('search_placeholder')) ?>"
                            value="<?= htmlspecialchars($search_q) ?>">
                        <?php if ($search_q): ?>
                        <a href="products.php<?= $active_cat ? '?cat='.urlencode($active_cat) : '' ?>"
                            class="shop-search-clear">×</a>
                        <?php endif; ?>
                        <button type="submit" class="shop-search-btn"><?= htmlspecialchars(t('search')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── MAIN LAYOUT ── -->
    <div class="shop-layout">

        <!-- ── SHOP_SIDEBAR ── -->
        <?php require_once "shop_sidebar.php" ?>

        <!-- Mobile sidebar overlay -->
        <div class="filter-overlay" id="filterOverlay" onclick="closeSidebar()"></div>

        <!-- ── PRODUCTS AREA ── -->
        <div class="shop-main">

            <!-- Active filter chips -->
            <?php if ($active_cat || $active_brand || $search_q): ?>
            <div class="active-filters">
                <?php if ($search_q): ?>
                <div class="af-chip">
                    🔍 <?= htmlspecialchars($search_q) ?>
                    <a href="<?= htmlspecialchars(build_url(['q' => ''])) ?>" class="af-remove">×</a>
                </div>
                <?php endif; ?>
                <?php if ($active_cat): ?>
                <div class="af-chip">
                    <?= cat_icon($active_cat) ?> <?= htmlspecialchars($active_cat) ?>
                    <a href="<?= htmlspecialchars(build_url(['cat' => ''])) ?>" class="af-remove">×</a>
                </div>
                <?php endif; ?>
                <?php if ($active_brand): ?>
                <div class="af-chip">
                    <?= htmlspecialchars($active_brand) ?>
                    <a href="<?= htmlspecialchars(build_url(['brand' => ''])) ?>" class="af-remove">×</a>
                </div>
                <?php endif; ?>
                <a href="products.php" class="af-clear"><?= htmlspecialchars(t('clear_all')) ?></a>
            </div>
            <?php endif; ?>

            <!-- ── RECOMMENDED FOR YOU (personalized) ── -->
            <?php if (!empty($recommended_products)): ?>
            <div class="recommended-strip" style="margin-bottom:28px">
                <div class="section-header" style="margin-bottom:14px">
                    <div>
                        <div class="section-tag">Just For You</div>
                        <div class="section-title" style="font-size:20px">Recommended For You</div>
                    </div>
                </div>

                <div class="products-grid">
                    <?php foreach ($recommended_products as $p):
                        /* FIX: this card never checked $wishlist_ids, so the
                           heart always rendered as an empty outline on page
                           load even for products already wishlisted — unlike
                           the main product grid below, which does this
                           correctly. Now consistent with that. */
                        $in_wl_rec = in_array($p['id'], $wishlist_ids);
                    ?>
                    <div class="product-card" onclick="window.location='product.php?id=<?= $p['id'] ?>'">
                        <div class="product-img-wrap">
                            <img src="<?= product_img($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                            <span class="product-badge badge-new">For You</span>
                            <div class="product-wishlist <?= $in_wl_rec ? 'active' : '' ?>" id="wl<?= $p['id'] ?>"
                                onclick="event.stopPropagation(); toggleWishlist(<?= $p['id'] ?>)"><?= $in_wl_rec ? '♥' : '♡' ?></div>
                            <div class="product-overlay">
                                <button class="btn-quick-add"
                                    onclick="event.stopPropagation(); addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
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
                                <span class="rating-count">(<?= number_format($p['sold']) ?> <?= htmlspecialchars(t('sold_suffix')) ?>)</span>
                            </div>
                            <div class="product-price">
                                <span class="price-current">$<?= number_format($p['price'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Results count (desktop toolbar) -->
            <div class="results-bar">
                <span class="results-count">
                    <?= htmlspecialchars(t('showing_x_of_y', ['x' => number_format(count($products)), 'y' => number_format($total_products)])) ?>
                </span>
                <!-- Full sort on desktop (strip sort is for mobile) -->
                <?php
                $sort_opts = [
                    'newest'     => t('sort_newest'),
                    'bestseller' => t('sort_bestseller'),
                    'price_asc'  => t('sort_price_asc'),
                    'price_desc' => t('sort_price_desc'),
                    'name_asc'   => t('sort_name_asc'),
                ];
                $sort_current_label = $sort_opts[$sort] ?? $sort_opts['newest'];
                ?>
                <div class="sort-wrap desktop-only">
                    <label class="sort-label"><?= htmlspecialchars(t('sort')) ?>:</label>

                    <!-- Custom themed dropdown (replaces native <select> —
                         browsers render a native select's OPEN option list
                         using OS chrome that CSS can't restyle, which is why
                         it was showing as a plain blue Windows/Chrome list
                         instead of matching the site) -->
                    <div class="lh-sort-dd" id="sortDropdown">
                        <button type="button" class="lh-sort-dd-btn" id="sortDropBtn"
                                onclick="toggleSortDrop()"
                                aria-haspopup="listbox" aria-expanded="false">
                            <span id="sortDropLabel"><?= htmlspecialchars($sort_current_label) ?></span>
                            <svg class="lh-sort-dd-chevron" width="13" height="13" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </button>

                        <div class="lh-sort-dd-menu" id="sortDropMenu" role="listbox" aria-label="Sort products">
                            <?php foreach ($sort_opts as $val => $label): ?>
                                <a href="<?= htmlspecialchars(build_url(['sort' => $val, 'page' => 1])) ?>"
                                   class="lh-sort-dd-item <?= $sort === $val ? 'active' : '' ?>"
                                   role="option" aria-selected="<?= $sort === $val ? 'true' : 'false' ?>">
                                    <span><?= htmlspecialchars($label) ?></span>
                                    <?php if ($sort === $val): ?>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="3">
                                            <path d="M20 6 9 17l-5-5"/>
                                        </svg>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Grid / Empty State -->
            <?php if (empty($products)): ?>
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <div class="no-results-title"><?= htmlspecialchars(t('no_products_found')) ?></div>
                <p class="no-results-sub"><?= htmlspecialchars(t('try_adjust_filters')) ?></p>
                <a href="products.php" class="btn-hero-primary" style="display:inline-flex;margin-top:16px">
                    <?= htmlspecialchars(t('clear_filters')) ?>
                </a>
            </div>
            <?php else: ?>
            <div class="products-grid" id="productsGrid">
                <?php foreach ($products as $p):
                        $is_low_stock = $p['stock'] > 0 && $p['stock'] < 20;
                        $is_out       = $p['stock'] <= 0;
                        $stock_pct    = $p['stock'] > 0 ? min(100, $p['stock']) : 0;
                    ?>
                <div class="product-card <?= $is_out ? 'out-of-stock' : '' ?>"
                    onclick="<?= $is_out ? 'void(0)' : "window.location='product.php?id={$p['id']}'" ?>">

                    <div class="product-img-wrap">
                        <img src="<?= product_img($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>"
                            loading="lazy">

                        <?php if ($p['featured']): ?>
                        <span class="product-badge badge-new"><?= htmlspecialchars(t('featured')) ?></span>
                        <?php endif; ?>

                        <?php if ($is_out): ?>
                        <span class="product-badge badge-out"><?= htmlspecialchars(t('out_of_stock')) ?></span>
                        <?php elseif ($is_low_stock): ?>
                        <span
                            class="product-badge badge-low"><?= htmlspecialchars(t('only_x_left', ['x' => $p['stock']])) ?></span>
                        <?php endif; ?>

                        <?php if (!$is_out):
                                    $in_wl = in_array($p['id'], $wishlist_ids);
                                ?>
                        <div class="product-wishlist <?= $in_wl ? 'active' : '' ?>" id="wl<?= $p['id'] ?>"
                            onclick="event.stopPropagation(); toggleWishlist(<?= $p['id'] ?>)"
                            title="<?= $in_wl ? htmlspecialchars(t('wl_remove_tooltip')) : htmlspecialchars(t('wl_add_tooltip')) ?>">
                            <?= $in_wl ? '♥' : '♡' ?></div>
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
                        <?php endif; ?>
                    </div>

                    <div class="product-info">
                        <div class="product-category"><?= htmlspecialchars($p['category']) ?></div>
                        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="product-brand"><?= htmlspecialchars($p['brand']) ?></div>
                        <?php $desc = short_desc($p['description'] ?? ''); ?>
                        <?php if ($desc !== ''): ?>
                        <div class="product-desc"
                            style="font-size:12px;color:var(--muted);line-height:1.5;margin:4px 0 6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-clamp:2;overflow:hidden">
                            <?= htmlspecialchars($desc) ?>
                        </div>
                        <?php endif; ?>
                        <div class="product-rating">
                            <span class="stars">★★★★★</span>
                            <span class="rating-count">(<?= number_format($p['sold']) ?>
                                <?= htmlspecialchars(t('sold_suffix')) ?>)</span>
                        </div>
                        <div class="product-price">
                            <span class="price-current">$<?= number_format($p['price'], 2) ?></span>
                            <?php if ($is_out): ?>
                            <span class="price-tag-out"><?= htmlspecialchars(t('out_of_stock')) ?></span>
                            <?php endif; ?>
                        </div>
                        <!-- List-view only: stock bar -->
                        <div class="stock-bar-wrap">
                            <div class="stock-bar">
                                <div class="stock-fill <?= $is_low_stock ? 'low' : '' ?>"
                                    style="width:<?= $stock_pct ?>%"></div>
                            </div>
                            <span class="stock-label"><?= $p['stock'] ?>
                                <?= htmlspecialchars(t('in_stock_label')) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars(build_url(['page' => $page - 1])) ?>"
                    class="pg-btn pg-prev"><?= htmlspecialchars(t('pg_prev')) ?></a>
                <?php endif; ?>

                <?php
                        $pg_start = max(1, $page - 2);
                        $pg_end   = min($total_pages, $page + 2);
                        if ($pg_start > 1):
                        ?>
                <a href="<?= htmlspecialchars(build_url(['page' => 1])) ?>" class="pg-btn">1</a>
                <?php if ($pg_start > 2): ?><span class="pg-ellipsis">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $pg_start; $i <= $pg_end; $i++): ?>
                <a href="<?= htmlspecialchars(build_url(['page' => $i])) ?>"
                    class="pg-btn <?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if ($pg_end < $total_pages): ?>
                <?php if ($pg_end < $total_pages - 1): ?>
                <span class="pg-ellipsis">…</span>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(build_url(['page' => $total_pages])) ?>"
                    class="pg-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                <a href="<?= htmlspecialchars(build_url(['page' => $page + 1])) ?>"
                    class="pg-btn pg-next"><?= htmlspecialchars(t('pg_next')) ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        </div><!-- /shop-main -->
    </div><!-- /shop-layout -->


    <!-- FOOTER -->
    <?php require_once "footer.php" ?>

    <!-- TOAST -->
    <div class="toast" id="toast">
        <span class="toast-icon" id="toastIcon">✓</span>
        <span id="toastMsg"><?= htmlspecialchars(t('item_added_cart')) ?></span>
    </div>

    <script>
    /* Set sidebar top = navbar height + shop-hero height.
   Re-measures on load (fonts/images can shift height after
   first paint) and watches both elements for size changes. */
    function setSidebarTop() {
        var nav = document.querySelector('nav');
        var shopHero = document.querySelector('.shop-hero');
        if (nav && shopHero) {
            var total = nav.getBoundingClientRect().height + shopHero.getBoundingClientRect().height;
            document.documentElement.style.setProperty('--sidebar-top', total + 'px');
        }
    }
    setSidebarTop();
    window.addEventListener('load', setSidebarTop);
    window.addEventListener('resize', setSidebarTop);

    if (window.ResizeObserver) {
        var navEl = document.querySelector('nav');
        var heroEl = document.querySelector('.shop-hero');
        var ro = new ResizeObserver(setSidebarTop);
        if (navEl) ro.observe(navEl);
        if (heroEl) ro.observe(heroEl);
    }

    /* ── Custom sort dropdown (replaces native <select>) ── */
    function toggleSortDrop() {
        const dd  = document.getElementById('sortDropdown');
        const btn = document.getElementById('sortDropBtn');
        if (!dd) return;
        const willOpen = !dd.classList.contains('open');
        dd.classList.toggle('open', willOpen);
        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    }
    document.addEventListener('click', function (e) {
        const dd = document.getElementById('sortDropdown');
        if (dd && !dd.contains(e.target)) {
            dd.classList.remove('open');
            document.getElementById('sortDropBtn')?.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const dd = document.getElementById('sortDropdown');
            dd?.classList.remove('open');
            document.getElementById('sortDropBtn')?.setAttribute('aria-expanded', 'false');
        }
    });
    </script>

</body>