<?php


// Arrays from DB (fallback safe empty arrays)
$categories   = $categories   ?? [];
$brands_list  = $brands_list  ?? [];

// Filters (safe defaults)
$active_cat   = isset($active_cat)   ? (string)$active_cat   : '';
$active_brand = isset($active_brand) ? (string)$active_brand : '';
$search_q     = isset($search_q)     ? (string)$search_q     : '';
$sort         = isset($sort)         ? (string)$sort         : 'newest';

// Pagination / totals
$total_products = isset($total_products) ? (int)$total_products : 0;

// Price boundaries (IMPORTANT: must exist before filter values)
$price_min = isset($price_min) ? (float)$price_min : 0;
$price_max = isset($price_max) ? (float)$price_max : 0;

// User-selected price filters (fallback to full range)
$filter_min = isset($filter_min) ? (float)$filter_min : $price_min;
$filter_max = isset($filter_max) ? (float)$filter_max : $price_max;

// Safety correction (prevents broken slider states)
if ($filter_min > $filter_max) {
    $temp = $filter_min;
    $filter_min = $filter_max;
    $filter_max = $temp;
}
?>

<?php
if (!isset($categories)) {
    exit('Direct access not allowed.');
}

/* Is any filter currently active? (used for "Clear all" visibility) */
$filters_active = $active_cat
    || $active_brand
    || $search_q
    || $filter_min > $price_min
    || $filter_max < $price_max
    || isset($_GET['instock']);
?>

<aside class="shop-sidebar" id="shopSidebar">

    <!-- ── Header ── -->
    <div class="sidebar-header">
        <div class="sidebar-title-row">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" style="opacity:.6">
                <line x1="4"  y1="6"  x2="20" y2="6"/>
                <line x1="8"  y1="12" x2="20" y2="12"/>
                <line x1="12" y1="18" x2="20" y2="18"/>
            </svg>
            <span class="sidebar-title"><?= htmlspecialchars(t('filters')) ?></span>
            <?php if ($filters_active): ?>
                <span class="filter-active-dot"></span>
            <?php endif; ?>
        </div>

        <div class="sidebar-header-right">
            <?php if ($filters_active): ?>
                <a href="products.php" class="sidebar-clear"><?= htmlspecialchars(t('clear_all')) ?></a>
            <?php endif; ?>
            <!-- Close button — mobile only -->
            <button class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Close filters">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- ── Filter Form ── -->
    <form method="GET" action="products.php" id="filterForm">

        <!-- Preserve non-filter params across submissions -->
        <?php if ($search_q): ?>
            <input type="hidden" name="q"    value="<?= htmlspecialchars($search_q) ?>">
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <input type="hidden" name="page" value="1">

        <!-- ════════════════════════════
             CATEGORIES
        ════════════════════════════ -->
        <div class="sf-group">
            <div class="sf-label">
                <span><?= htmlspecialchars(t('category')) ?></span>
                <?php if ($active_cat): ?>
                    <a href="<?= htmlspecialchars(build_url(['cat' => '', 'page' => 1])) ?>"
                       class="sf-reset-link"><?= htmlspecialchars(t('reset')) ?></a>
                <?php endif; ?>
            </div>

            <div class="sf-options">

                <!-- All -->
                <label class="sf-opt <?= $active_cat === '' ? 'checked' : '' ?>">
                    <input type="radio" name="cat" value=""
                           <?= $active_cat === '' ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                    <span class="sf-icon">🔥</span>
                    <span class="sf-name"><?= htmlspecialchars(t('all_categories')) ?></span>
                    <span class="sf-num"><?= number_format($total_products) ?></span>
                </label>

                <?php foreach ($categories as $cat):
                    $is_active = $active_cat === $cat['name'];
                ?>
                    <label class="sf-opt <?= $is_active ? 'checked' : '' ?>">
                        <input type="radio" name="cat"
                               value="<?= htmlspecialchars($cat['name']) ?>"
                               <?= $is_active ? 'checked' : '' ?>
                               onchange="this.form.submit()">
                        <span class="sf-icon"><?= cat_icon($cat['name']) ?></span>
                        <span class="sf-name"><?= htmlspecialchars($cat['name']) ?></span>
                        <span class="sf-num"><?= number_format((int)$cat['count']) ?></span>
                    </label>
                <?php endforeach; ?>

            </div>
        </div>

        <!-- ════════════════════════════
             BRAND — now a dropdown select
             (backend only ever supports a
             single active brand at a time,
             so a <select> matches reality
             instead of looking like a
             multi-select checkbox list)
        ════════════════════════════ -->
        <div class="sf-group">
            <div class="sf-label">
                <span><?= htmlspecialchars(t('brand')) ?></span>
                <?php if ($active_brand): ?>
                    <a href="<?= htmlspecialchars(build_url(['brand' => '', 'page' => 1])) ?>"
                       class="sf-reset-link"><?= htmlspecialchars(t('reset')) ?></a>
                <?php endif; ?>
            </div>

            <?php
            $brands_with_products = array_filter($brands_list, fn($b) => $b['count'] > 0);
            ?>

            <?php if (empty($brands_with_products)): ?>
                <p class="sf-empty"><?= htmlspecialchars(t('no_brands')) ?></p>
            <?php else: ?>
                <select name="brand" class="sf-brand-select" onchange="this.form.submit()">
                    <option value="">All Brands</option>
                    <?php foreach ($brands_with_products as $b): ?>
                        <option value="<?= htmlspecialchars($b['name']) ?>"
                                <?= $active_brand === $b['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?> (<?= (int)$b['count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <!-- ════════════════════════════
             PRICE RANGE

             FIX: number inputs need step="0.01" —
             without it, the browser defaults to
             step="1". Since `min` is a fractional
             DB-derived price (e.g. 1769.99), HTML5
             number validation only accepted values
             landing on min + 1*n (e.g. 1770.99,
             1771.99…), which is why typing a clean
             number like 1770 threw "please enter a
             valid value". step="0.01" lets any cent
             value be typed. The range sliders got
             the same treatment for consistency, plus
             step is small enough (1) that dragging
             still feels smooth against a fractional
             min/max.
        ════════════════════════════ -->
        <div class="sf-group">
            <div class="sf-label">
                <span><?= htmlspecialchars(t('price_range')) ?></span>
                <span class="sf-price-display" id="priceDisplay">
                    $<?= number_format($filter_min, 0) ?>
                    –
                    $<?= number_format($filter_max, 0) ?>
                </span>
            </div>

            <!-- Dual range slider track visual -->
            <div class="price-track-wrap">
                <div class="price-track-bg"></div>
                <div class="price-track-fill" id="priceTrackFill"></div>
                <input type="range" class="price-slider" id="sliderMin"
                       min="<?= $price_min ?>" max="<?= $price_max ?>"
                       value="<?= $filter_min ?>" step="1"
                       oninput="updatePriceDisplay()">
                <input type="range" class="price-slider" id="sliderMax"
                       min="<?= $price_min ?>" max="<?= $price_max ?>"
                       value="<?= $filter_max ?>" step="1"
                       oninput="updatePriceDisplay()">
            </div>

            <div class="price-inputs">
                <div class="price-input-wrap">
                    <span class="price-sym">$</span>
                    <input type="number" class="price-input" id="inputMin"
                           min="<?= $price_min ?>" max="<?= $price_max ?>"
                           step="0.01"
                           value="<?= $filter_min ?>" placeholder="Min"
                           oninput="syncSliders()">
                </div>
                <span class="price-dash">–</span>
                <div class="price-input-wrap">
                    <span class="price-sym">$</span>
                    <input type="number" class="price-input" id="inputMax"
                           min="<?= $price_min ?>" max="<?= $price_max ?>"
                           step="0.01"
                           value="<?= $filter_max ?>" placeholder="Max"
                           oninput="syncSliders()">
                </div>
            </div>

            <!-- Hidden fields sent on form submit — these are the sole source of pmin/pmax -->
            <input type="hidden" name="pmin" id="hiddenPmin" value="<?= $filter_min ?>">
            <input type="hidden" name="pmax" id="hiddenPmax" value="<?= $filter_max ?>">

            <button type="submit" class="price-apply-btn" onclick="preparePriceSubmit()">
                <?= htmlspecialchars(t('apply_price_filter')) ?>
            </button>
        </div>

        <!-- ════════════════════════════
             IN STOCK ONLY
        ════════════════════════════ -->
        <div class="sf-group sf-group-last">
            <label class="sf-toggle-wrap">
                <input type="checkbox" name="instock" value="1"
                       <?= isset($_GET['instock']) ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                <div class="sf-toggle">
                    <div class="sf-toggle-knob"></div>
                </div>
                <div class="sf-toggle-text">
                    <span class="sf-toggle-label"><?= htmlspecialchars(t('in_stock_only')) ?></span>
                    <span class="sf-toggle-sub"><?= htmlspecialchars(t('hide_sold_out')) ?></span>
                </div>
            </label>
        </div>

    </form>
</aside>