<?php
/**
 * promo_scroller.php
 * ─────────────────────────────────────────────────────────
 * Drop-in horizontally auto-scrolling promo product strip.
 *
 * USAGE — include it wherever you want the strip to appear
 * (e.g. right after the hero in index.php, or above the
 * flash-sale grid in deals.php). Requires $conn (PDO) to
 * already be available, which it is on every page that has
 * already required lectrohub_db/connection.php.
 *
 *   <?php require_once "promo_scroller.php" ?>
 *
 * It pulls the SAME "flash deal" logic already used in
 * deals.php (featured, in-stock products, 25% off) so the
 * discounts shown here always match what deals.php shows —
 * no separate source of truth to keep in sync.
 *
 * ── FIXES ──
 * 1. Theme mismatch: the inline <style> block below used to
 *    reference CSS variables that don't exist anywhere else
 *    in the site (--surface-1, --text, --font-display,
 *    --font-mono, --accent... some of those ARE real, but
 *    --surface-1 and --text are not). Because of the
 *    var(x, fallback) syntax, a missing variable silently
 *    resolves to its hardcoded fallback — so this section
 *    stayed dark/white no matter what data-theme was set to.
 *    Swapped in the actual variables from light-mode.css /
 *    dark-mode.css (--card2, --txt, --border, --muted, --fd,
 *    --fm, --accent) so it now follows the theme toggle.
 * 2. Scroll speed: animation duration raised 38s -> 65s so
 *    the strip drifts instead of racing past.
 * 3. FROZEN MARQUEE (root cause): the individual cards used
 *    class "promo-card", which collides with TWO other things
 *    sitewide: (a) an unrelated .promo-card rule in
 *    light-mode.css for the homepage promo-banner section, and
 *    (b) animations.js's initScrollReveal(), which explicitly
 *    watches ".promo-card" and hides every match at opacity:0
 *    until it individually scrolls into the browser viewport
 *    via IntersectionObserver. Most of this strip's cards sit
 *    far outside the viewport and only enter view via the
 *    marquee's own translateX animation, not real page scroll —
 *    so they never fired the observer and stayed invisible,
 *    making the whole strip look frozen. Renamed every class
 *    here to a "ps-card" namespace so nothing sitewide touches
 *    it anymore.
 * ─────────────────────────────────────────────────────────
 */

if (!isset($conn)) {
    require_once __DIR__ . "/../lectrohub_db/connection.php";
}

function promo_scroller_img(?string $img): string {
    if (!empty($img)) {
        return "/LectroHub/admin/img/" . htmlspecialchars($img);
    }
    return "https://placehold.co/200x200/070a10/00e5ff?text=No+Image";
}

$promo_items    = [];
$promo_is_flash = true; // true = real discount badge, false = fallback "Popular" badge

try {
    $stmt = $conn->query("
        SELECT
            p.id,
            p.product_name  AS name,
            p.brand_legacy  AS brand,
            p.price,
            p.image         AS img,
            c.category_name AS category,
            ROUND(p.price * 0.75, 2) AS sale_price,
            25 AS discount_pct
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active'
          AND p.featured = 1
          AND p.stock > 0
        ORDER BY p.id DESC
        LIMIT 12
    ");
    $promo_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ── FALLBACK ──
       No featured + in-stock products right now (e.g. nothing
       flagged "featured" yet, or featured items are sold out).
       Rather than the whole strip silently vanishing, fall back
       to top-selling active/in-stock products so the section
       always has something to show admins can act on this by
       marking products as "Featured" in products_edit.php. */
    if (empty($promo_items)) {
        $promo_is_flash = false;
        $stmt = $conn->query("
            SELECT
                p.id,
                p.product_name  AS name,
                p.brand_legacy  AS brand,
                p.price,
                p.image         AS img,
                c.category_name AS category,
                p.price AS sale_price,
                0 AS discount_pct
            FROM products p
            INNER JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
              AND p.stock > 0
            ORDER BY p.sold DESC, p.id DESC
            LIMIT 12
        ");
        $promo_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("promo_scroller.php DB error: " . $e->getMessage());
    $promo_items = [];
}
?>

<?php if (!empty($promo_items)): ?>

<style>
/* ── Promo Scroller ── */
.promo-scroller-wrap {
    position: relative;
    overflow: hidden;
    padding: 28px 0;
    background: var(--card2);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.promo-scroller-hd {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 24px 16px;
    max-width: 1400px;
    margin: 0 auto;
}

.promo-scroller-hd .ps-badge {
    background: linear-gradient(90deg, #e0185a, #ff6b35);
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 20px;
}

.promo-scroller-hd .ps-title {
    font-family: var(--fd);
    font-size: 16px;
    font-weight: 700;
    color: var(--txt);
}

.promo-scroller-hd .ps-sub {
    font-size: 12px;
    color: var(--muted);
    margin-left: auto;
}

.promo-scroller-track-outer {
    overflow: hidden;
    width: 100%;
    mask-image: linear-gradient(90deg, transparent 0, #000 40px, #000 calc(100% - 40px), transparent 100%);
    -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 40px, #000 calc(100% - 40px), transparent 100%);
}

.promo-scroller-track {
    display: flex;
    gap: 16px;
    animation: promoScrollX 20s linear infinite;
    white-space: nowrap;
}

.promo-scroller-track-outer:hover .promo-scroller-track {
    animation-play-state: paused;
}

@media (prefers-reduced-motion: reduce) {
    .promo-scroller-track {
        animation: none;
    }
}

@keyframes promoScrollX {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); } /* content is tripled below, same pattern as .ticker-inner */
}

.ps-card {
    flex: 0 0 auto;
    width: 190px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px;
    cursor: pointer;
    transition: transform .18s ease, border-color .18s ease;
    text-decoration: none;
    display: block;
}

.ps-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
}

.ps-card-imgwrap {
    position: relative;
    width: 100%;
    aspect-ratio: 1/1;
    border-radius: 8px;
    overflow: hidden;
    background: var(--card2);
    margin-bottom: 10px;
}

.ps-card-imgwrap img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 8px;
    box-sizing: border-box;
}

.ps-card-disc {
    position: absolute;
    top: 6px;
    right: 6px;
    background: #e0185a;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 7px;
    border-radius: 6px;
}

.ps-card-cat {
    font-size: 10px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 2px;
}

.ps-card-name {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--txt);
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 8px;
    min-height: 34px;
}

.ps-card-prices {
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.ps-card-now {
    font-family: var(--fm);
    font-size: 14px;
    font-weight: 800;
    color: var(--accent);
}

.ps-card-was {
    font-size: 11px;
    color: var(--muted);
    text-decoration: line-through;
}

@media (max-width: 640px) {
    .ps-card { width: 150px; }
    .promo-scroller-hd { padding: 0 16px 12px; flex-wrap: wrap; }
}
</style>

<div class="promo-scroller-wrap">
    <div class="promo-scroller-hd">
        <?php if ($promo_is_flash): ?>
            <span class="ps-badge">⚡ Flash Deals</span>
            <span class="ps-title">Hot Picks Moving Fast</span>
            <a href="deals.php?type=flash" class="ps-sub" style="text-decoration:none">View all deals →</a>
        <?php else: ?>
            <span class="ps-badge" style="background:linear-gradient(90deg,#7c3aed,#00e5ff)">🔥 Popular</span>
            <span class="ps-title">Customers Are Buying These</span>
            <a href="products.php?sort=bestseller" class="ps-sub" style="text-decoration:none">View all →</a>
        <?php endif; ?>
    </div>

    <div class="promo-scroller-track-outer">
        <div class="promo-scroller-track">
            <?php
            // Render the row THREE times back-to-back, same pattern as the
            // working ticker-inner marquee — relying on flex overflow
            // rather than width:max-content, so the -50% translateX loop
            // reads as seamless even though it isn't an exact boundary.
            for ($rep = 0; $rep < 3; $rep++):
                foreach ($promo_items as $p):
            ?>
                <a class="ps-card" href="product.php?id=<?= $p['id'] ?>" aria-hidden="<?= $rep === 0 ? 'false' : 'true' ?>" tabindex="<?= $rep === 0 ? '0' : '-1' ?>">
                    <div class="ps-card-imgwrap">
                        <img src="<?= promo_scroller_img($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                        <?php if ($promo_is_flash): ?>
                            <span class="ps-card-disc">−<?= $p['discount_pct'] ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="ps-card-cat"><?= htmlspecialchars($p['category']) ?></div>
                    <div class="ps-card-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="ps-card-prices">
                        <span class="ps-card-now">$<?= number_format($p['sale_price'], 2) ?></span>
                        <?php if ($promo_is_flash): ?>
                            <span class="ps-card-was">$<?= number_format($p['price'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php
                endforeach;
            endfor;
            ?>
        </div>
    </div>
</div>

<?php endif; ?>