<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";
require_once "../lectrohub_db/shipping_helper.php";
require_once "../lectrohub_db/coupon_helper.php";
require_once __DIR__ . "/lang/translate.php";

/* ══════════════════════════════════════════
   CART PAGE — reads $_SESSION['cart']
   Works for both guests and logged-in users.
   Login is only required at checkout.
══════════════════════════════════════════ */

$cart_items = [];
$subtotal   = 0.00;
$cart_count = 0;

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {

    $ids          = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.product_name  AS name,
            p.brand_legacy  AS brand,
            p.price,
            p.stock,
            p.image         AS img,
            c.category_name AS category
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders)
          AND p.status = 'active'
    ");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $qty = (int)$_SESSION['cart'][$row['id']];
        $qty = min($qty, (int)$row['stock']);
        if ($qty < 1) continue;

        $line_total   = $row['price'] * $qty;
        $subtotal    += $line_total;
        $cart_count  += $qty;
        $cart_items[] = array_merge($row, [
            'qty'        => $qty,
            'line_total' => $line_total,
        ]);
    }
}

/* ══════════════════════════════════════════
   SHIPPING ESTIMATE
   The cart doesn't collect a delivery city
   (that happens at checkout), so this is quoted
   against the shopper's default saved address if
   they're logged in, otherwise the zone flagged
   as default in shipping_zones. checkout.php
   re-quotes precisely once a city is chosen.
══════════════════════════════════════════ */
$estimate_city = null;
if (!empty($_SESSION['user']['id'])) {
    $addr_stmt = $conn->prepare(
        "SELECT city FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1"
    );
    $addr_stmt->execute([$_SESSION['user']['id']]);
    $estimate_city = $addr_stmt->fetchColumn() ?: null;
}

$shipping_quote = $subtotal > 0
    ? get_shipping_quote($conn, $estimate_city, $subtotal)
    : ['fee' => 0.00, 'threshold' => 50.00];

$shipping = $shipping_quote['fee'];
$total    = $subtotal + $shipping;

/* ══════════════════════════════════════════
   AVAILABLE COUPONS
   Public coupons (user_id IS NULL) for everyone,
   plus any coupon personally targeted at this
   user_id if they're logged in. Shown so shoppers
   can actually discover a code exists instead of
   needing to already know it.
══════════════════════════════════════════ */
$available_coupons = get_available_coupons($conn, $_SESSION['user']['id'] ?? null);

function product_img(?string $img): string {
    if ($img && $img !== '') {
        return "/LectroHub/admin/img/" . htmlspecialchars($img);
    }
    return "https://placehold.co/120x120/070a10/00e5ff?text=No+Image";
}
?>

<?php require_once "head.php" ?>
<?php require_once "navbar.php" ?>


<body>
<main class="cart-page">

    <!-- ── BREADCRUMB ── -->
    <div class="cart-breadcrumb">
        <a href="index.php"><?= htmlspecialchars(t('home')) ?></a>
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
        <a href="products.php"><?= htmlspecialchars(t('shop')) ?></a>
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
        <span><?= htmlspecialchars(t('cart')) ?></span>
    </div>

    <div class="cart-heading">
        <br>
        <h1><?= htmlspecialchars(t('your_cart')) ?>
            <?php if ($cart_count > 0): ?>
                <span class="cart-heading-badge"><?= $cart_count ?> <?= htmlspecialchars($cart_count !== 1 ? t('item_plural') : t('item_singular')) ?></span>
            <?php endif; ?>
        </h1>
    </div>

    <?php if (empty($cart_items)): ?>

        <!-- ── EMPTY STATE ── -->
        <div class="cart-empty">
            <div class="cart-empty-icon">🛒</div>
            <div class="cart-empty-title"><?= htmlspecialchars(t('cart_empty_title')) ?></div>
            <p class="cart-empty-sub"><?= htmlspecialchars(t('cart_empty_sub')) ?></p>
            <a href="products.php" class="btn-hero-primary" style="display:inline-flex;margin-top:20px">
                <?= htmlspecialchars(t('start_shopping')) ?>
            </a>
        </div>

    <?php else: ?>

        <!-- ── CART LAYOUT ── -->
        <div class="cart-layout">

            <!-- ── ITEMS COLUMN ── -->
            <div class="cart-items-col">

                <div class="cart-items-header">
                    <span><?= htmlspecialchars(t('th_product')) ?></span>
                    <span><?= htmlspecialchars(t('th_qty')) ?></span>
                    <span><?= htmlspecialchars(t('th_price')) ?></span>
                </div>

                <div class="cart-items-list" id="cartItemsList">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item" id="cartItem<?= $item['id'] ?>">

                            <div class="ci-img-wrap">
                                <img src="<?= product_img($item['img']) ?>"
                                     alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
                            </div>

                            <div class="ci-info">
                                <div class="ci-category"><?= htmlspecialchars($item['category']) ?></div>
                                <div class="ci-name"><?= htmlspecialchars($item['name']) ?></div>
                                <div class="ci-brand"><?= htmlspecialchars($item['brand']) ?></div>
                                <div class="ci-unit-price">$<?= number_format($item['price'], 2) ?> <?= htmlspecialchars(t('each_suffix')) ?></div>
                                <?php if ($item['stock'] <= 5): ?>
                                    <div class="ci-low-stock"><?= htmlspecialchars(t('only_x_left_bang', ['x' => $item['stock']])) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="ci-qty-wrap">
                                <button class="qty-btn" onclick="updateQty(<?= $item['id'] ?>, -1)"
                                        aria-label="<?= htmlspecialchars(t('decrease_qty')) ?>">−</button>
                                <span class="qty-val" id="qtyVal<?= $item['id'] ?>"><?= $item['qty'] ?></span>
                                <button class="qty-btn" onclick="updateQty(<?= $item['id'] ?>, 1)"
                                        aria-label="<?= htmlspecialchars(t('increase_qty')) ?>"
                                        <?= $item['qty'] >= $item['stock'] ? 'disabled' : '' ?>>+</button>
                            </div>

                            <div class="ci-line-total" id="lineTotal<?= $item['id'] ?>">
                                $<?= number_format($item['line_total'], 2) ?>
                            </div>

                            <button class="ci-remove" onclick="removeItem(<?= $item['id'] ?>)"
                                    title="<?= htmlspecialchars(t('remove_item_title')) ?>"
                                    aria-label="<?= htmlspecialchars(t('remove_item_aria', ['name' => $item['name']])) ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2.5">
                                    <path d="M18 6 6 18M6 6l12 12"/>
                                </svg>
                            </button>

                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Clear cart -->
                <div class="cart-actions-row">
                    <a href="products.php" class="btn-outline-sm"><?= htmlspecialchars(t('continue_shopping')) ?></a>
                    <button class="btn-danger-sm" onclick="clearCart()"><?= htmlspecialchars(t('clear_cart')) ?></button>
                </div>

            </div><!-- /cart-items-col -->

            <!-- ── SUMMARY COLUMN ── -->
            <div class="cart-summary-col">
                <div class="cart-summary-card">
                    <h2 class="cs-title"><?= htmlspecialchars(t('order_summary')) ?></h2>

                    <div class="cs-rows">
                        <div class="cs-row">
                            <span><?= htmlspecialchars(t('subtotal')) ?></span>
                            <span id="summarySubtotal">$<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="cs-row">
                            <span><?= htmlspecialchars(t('shipping')) ?> <span style="font-size:11px;color:var(--muted)"><?= htmlspecialchars(t('estimate')) ?></span></span>
                            <span id="summaryShipping"><?= $shipping > 0 ? '$' . number_format($shipping, 2) : '<span class="free-tag">' . htmlspecialchars(t('free')) . '</span>' ?></span>
                        </div>
                        <?php if ($shipping > 0 && $shipping_quote['threshold'] !== null): ?>
                            <div class="cs-free-notice">
                                <?= htmlspecialchars(t('add_x_more_free_shipping', ['x' => number_format($shipping_quote['threshold'] - $subtotal, 2)])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="cs-divider"></div>

                    <!-- Coupon -->
                    <div class="cs-coupon">
                        <input type="text" id="couponInput" class="coupon-input"
                               placeholder="<?= htmlspecialchars(t('coupon_placeholder')) ?>" autocomplete="off">
                        <button class="btn-coupon" onclick="applyCoupon()"><?= htmlspecialchars(t('apply')) ?></button>
                    </div>
                    <div class="coupon-msg" id="couponMsg"></div>

                    <!-- ── AVAILABLE COUPONS ── -->
                    <?php if (!empty($available_coupons)): ?>
                        <div class="available-coupons">
                            <?php foreach ($available_coupons as $c):
                                $label    = $c['type'] === 'percent'
                                    ? $c['value'] . '% OFF'
                                    : '$' . number_format($c['value'], 2) . ' OFF';
                                $eligible = $subtotal >= (float)$c['min_order'];
                            ?>
                                <button type="button"
                                        class="coupon-chip <?= $eligible ? '' : 'coupon-chip-locked' ?>"
                                        onclick="useCoupon('<?= htmlspecialchars($c['code'], ENT_QUOTES) ?>')"
                                        title="<?= $c['min_order'] > 0
                                            ? 'Minimum order $' . number_format($c['min_order'], 2)
                                            : 'No minimum order' ?>">
                                    🏷️ <?= htmlspecialchars($c['code']) ?> — <?= htmlspecialchars($label) ?>
                                    <?php if (!$eligible): ?>
                                        <span style="font-size:10px">(min $<?= number_format($c['min_order'], 2) ?>)</span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="cs-divider"></div>

                    <div class="cs-total-row">
                        <span><?= htmlspecialchars(t('total')) ?></span>
                        <span id="summaryTotal">$<?= number_format($total, 2) ?></span>
                    </div>

                    <?php if (isset($_SESSION['user'])): ?>
                        <a href="checkout.php" class="btn-checkout">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            <?= htmlspecialchars(t('proceed_to_checkout')) ?>
                        </a>
                    <?php else: ?>
                        <!-- Guest: prompt login before checkout -->
                        <a href="/LectroHub/auth/signin.php" class="btn-checkout">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                <polyline points="10 17 15 12 10 7"/>
                                <line x1="15" y1="12" x2="3" y2="12"/>
                            </svg>
                            <?= htmlspecialchars(t('sign_in_to_checkout')) ?>
                        </a>
                        <p style="text-align:center;font-size:12px;color:var(--muted);margin-top:10px">
                            <?= htmlspecialchars(t('cart_saved_notice')) ?>
                        </p>
                    <?php endif; ?>

                    <div class="cs-trust">
                        <span><?= htmlspecialchars(t('trust_secure_checkout')) ?></span>
                        <span><?= htmlspecialchars(t('trust_fast_delivery')) ?></span>
                        <span><?= htmlspecialchars(t('trust_easy_returns')) ?></span>
                    </div>
                </div>
            </div><!-- /cart-summary-col -->

        </div><!-- /cart-layout -->

    <?php endif; ?>

</main>

<?php require_once "footer.php" ?>

<div class="toast" id="toast">
    <span class="toast-icon" id="toastIcon">✓</span>
    <span id="toastMsg"><?= htmlspecialchars(t('toast_done')) ?></span>
</div>

</body>