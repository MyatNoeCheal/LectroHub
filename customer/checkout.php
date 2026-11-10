<?php
require_once "auth_check.php";
require_once "../lectrohub_db/connection.php";
require_once "../lectrohub_db/shipping_helper.php";
require_once "../lectrohub_db/mailer.php";
require_once __DIR__ . "/lang/translate.php";
require_once __DIR__ . "/../siem_event_sender.php";

/* ══════════════════════════════════════════
   REDIRECT IF CART IS EMPTY
══════════════════════════════════════════ */
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

/* ══════════════════════════════════════════
   FETCH CART ITEMS FROM DB
══════════════════════════════════════════ */
$cart_items = [];
$subtotal   = 0.00;

$ids          = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = $conn->prepare("
    SELECT p.id, p.product_name AS name, p.brand_legacy AS brand, p.price, p.stock,
           p.image AS img, p.sku, c.category_name AS category
    FROM products p
    INNER JOIN categories c ON p.category_id = c.id
    WHERE p.id IN ($placeholders) AND p.status = 'active'
");
$stmt->execute($ids);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $qty = min((int)$_SESSION['cart'][$row['id']], (int)$row['stock']);
    if ($qty < 1) continue;
    $line         = $row['price'] * $qty;
    $subtotal    += $line;
    $cart_items[] = array_merge($row, ['qty' => $qty, 'line_total' => $line]);
}

if (empty($cart_items)) {
    header('Location: cart.php');
    exit;
}

$discount = (float)($_SESSION['coupon_discount'] ?? 0);

/* ══════════════════════════════════════════
   FETCH SAVED ADDRESSES (logged-in users)
══════════════════════════════════════════ */
$addresses  = [];
$user_id    = $_SESSION['user']['id'] ?? null;
$user_name  = $_SESSION['user']['full_name'] ?? ($_SESSION['user_name'] ?? '');
$user_email = $_SESSION['user']['email'] ?? '';

if ($user_id) {
    $addr_stmt = $conn->prepare(
        "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC"
    );
    $addr_stmt->execute([$user_id]);
    $addresses = $addr_stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ══════════════════════════════════════════
   FIX: FETCH PICKUP LOCATIONS
   Click & Collect — lets the customer skip
   delivery entirely and collect from a store.
   See migration_click_and_collect.sql for the
   `store_locations` table + `orders.fulfillment_type`
   / `orders.pickup_location_id` columns this needs.
══════════════════════════════════════════ */
$store_locations = [];
try {
    $store_locations = $conn->query("
        SELECT id, name, address_line, city
        FROM store_locations
        ORDER BY city, name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet if the migration hasn't been run —
    // fail soft so delivery-only checkout still works.
    $store_locations = [];
}

/* ══════════════════════════════════════════
   SHIPPING — quoted by city, looked up from
   shipping_zones. Initial page load quotes
   against the default saved address's city (if
   any); the city <select> / saved-address radios
   re-quote this live via shipping_fee_action.php
   as the shopper picks a different address.
   FIX: this quote assumes delivery — pickup
   orders always show $0 shipping (handled by JS
   on the front end and re-validated server-side
   on submit).
══════════════════════════════════════════ */
$default_ship_city = null;
foreach ($addresses as $a) {
    if ($a['is_default']) {
        $default_ship_city = $a['city'];
        break;
    }
}
if (!$default_ship_city && !empty($addresses)) {
    $default_ship_city = $addresses[0]['city'];
}

$shipping_quote = get_shipping_quote($conn, $default_ship_city, $subtotal);
$shipping       = $shipping_quote['fee'];
$total          = max(0, $subtotal + $shipping - $discount);

/* ══════════════════════════════════════════
   PAYMENT METHODS
   card     = Credit / Debit Card
   paypal   = PayPal
   kbzpay   = KBZPay (mobile wallet)
   wavepay  = WavePay (mobile wallet)
   DB enum: card | paypal | bank_transfer | kbzpay | wavepay
   (bank_transfer kept in the enum for historical orders only —
   it is intentionally no longer offered as a checkout option)
══════════════════════════════════════════ */
$valid_payment_methods = ['card', 'paypal', 'kbzpay', 'wavepay'];
$wallet_methods         = ['kbzpay', 'wavepay'];

/* ══════════════════════════════════════════
   HANDLE ORDER SUBMISSION
══════════════════════════════════════════ */
$order_error          = '';
$order_success        = false;
$order_id             = null;
$order_summary        = null;
$order_items_summary  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $payment = $_POST['payment_method'] ?? 'card';
    if (!in_array($payment, $valid_payment_methods, true)) {
        $payment = 'card';
    }
    $payment_db = $payment; // stored as-is; enum now includes kbzpay/wavepay
    $notes      = trim($_POST['notes'] ?? '');

    // Mobile wallet phone number — required only for kbzpay / wavepay.
    // FIX: read from a method-specific field (kbzpay_phone / wavepay_phone)
    // instead of a shared "wallet_phone" name. Two inputs sharing one name
    // meant the browser posted BOTH values, and PHP kept only the last one
    // in DOM order — silently discarding whichever field the person had
    // actually filled in. Unique names remove that risk entirely, no
    // matter what JS does or doesn't run on the client.
    $wallet_phone = trim(($payment === 'kbzpay'
        ? ($_POST['kbzpay_phone']  ?? '')
        : ($_POST['wavepay_phone'] ?? '')));

    // Card fields — collected for UX/receipt purposes only. This demo
    // checkout does not process real card payments; never store full
    // card numbers or CVVs in a production system.
    $card_name = trim($_POST['card_name'] ?? '');

    /* FIX: fulfilment type — 'delivery' (default) or 'pickup' */
    $fulfillment_type = $_POST['fulfillment_type'] ?? 'delivery';
    if (!in_array($fulfillment_type, ['delivery', 'pickup'], true)) {
        $fulfillment_type = 'delivery';
    }

    // Address — either saved or new fields (delivery only)
    $addr_id   = null;
    $full_name = trim($_POST['full_name']    ?? '');
    $phone     = trim($_POST['phone']        ?? '');
    $address   = trim($_POST['address_line'] ?? '');
    $city      = trim($_POST['city']         ?? '');
    $state     = trim($_POST['state']        ?? '');

    // Pickup location (pickup only)
    $pickup_location_id = null;
    $pickup_location     = null;

    if ($fulfillment_type === 'pickup') {
        $pickup_location_id = (int)($_POST['pickup_location_id'] ?? 0);

        $loc_check = $conn->prepare("SELECT id, name, address_line, city FROM store_locations WHERE id = ? LIMIT 1");
        $loc_check->execute([$pickup_location_id]);
        $pickup_location = $loc_check->fetch(PDO::FETCH_ASSOC);

        if (!$pickup_location) {
            $order_error = t('please_select_pickup');
        }
    }

    // Determine if a valid saved address was chosen (delivery only)
    $using_saved = false;
    if ($fulfillment_type === 'delivery' && $user_id && !empty($_POST['saved_address_id']) && (int)$_POST['saved_address_id'] > 0) {
        // Security: verify the address belongs to this user
        $verify = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ? LIMIT 1");
        $verify->execute([(int)$_POST['saved_address_id'], $user_id]);
        if ($verify->fetch()) {
            $addr_id     = (int)$_POST['saved_address_id'];
            $using_saved = true;
        }
    }

    // Validate payment method
    if ($order_error) {
        // pickup location error already set above — fall through to render it

    } elseif (!in_array($payment, $valid_payment_methods, true)) {
        $order_error = t('invalid_payment_method');

    } elseif (in_array($payment, $wallet_methods, true) && $wallet_phone === '') {
        $order_error = ($payment === 'kbzpay' ? 'KBZPay' : 'WavePay') . ' phone number is required.';

    } elseif ($payment === 'card' && $card_name === '') {
        $order_error = 'Please enter the name on the card.';

        // Delivery orders need address fields when NOT using a saved address
    } elseif ($fulfillment_type === 'delivery' && !$using_saved && (!$full_name || !$address || !$city)) {
        $order_error = t('fill_required_address');
    } else {

        try {
            $conn->beginTransaction();

            // Save new address for logged-in users if checkbox ticked and no saved address chosen
            if ($fulfillment_type === 'delivery' && $user_id && !$addr_id && !empty($_POST['save_address'])) {
                $ins = $conn->prepare("
                    INSERT INTO addresses (user_id, full_name, phone, address_line, city, state)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$user_id, $full_name, $phone, $address, $city, $state]);
                $addr_id = (int)$conn->lastInsertId();
            }

            // Re-calculate totals fresh from DB (never trust stale session values)
            $fresh_subtotal = 0.00;
            foreach ($cart_items as $ci) {
                $fresh_subtotal += $ci['line_total'];
            }

            if ($fulfillment_type === 'pickup') {
                $fresh_shipping = 0.00;
            } else {
                // Resolve the actual delivery city for this order — a saved
                // address's city (verified against the DB, not the client)
                // takes precedence over the submitted new-address city field.
                $ship_city = $city ?: null;
                if ($using_saved) {
                    $saved_city_stmt = $conn->prepare("SELECT city FROM addresses WHERE id = ? LIMIT 1");
                    $saved_city_stmt->execute([$addr_id]);
                    $ship_city = $saved_city_stmt->fetchColumn() ?: $ship_city;
                }

                $fresh_shipping_quote = get_shipping_quote($conn, $ship_city, $fresh_subtotal);
                $fresh_shipping       = $fresh_shipping_quote['fee'];
            }

            $fresh_discount = 0.00;
            $coupon_id_to_increment = null;

            if (!empty($_SESSION['coupon_id'])) {
                $coupon_check = $conn->prepare("
                    SELECT id, type, value, min_order
                    FROM coupons
                    WHERE id = ?
                      AND active = 1
                      AND (expires_at IS NULL OR expires_at > NOW())
                      AND (usage_limit IS NULL OR used_count < usage_limit)
                    LIMIT 1
                ");
                $coupon_check->execute([$_SESSION['coupon_id']]);
                $valid_coupon = $coupon_check->fetch(PDO::FETCH_ASSOC);

                if ($valid_coupon && $fresh_subtotal >= (float)$valid_coupon['min_order']) {
                    if ($valid_coupon['type'] === 'percent') {
                        $fresh_discount = round($fresh_subtotal * ($valid_coupon['value'] / 100), 2);
                    } else {
                        $fresh_discount = min((float)$valid_coupon['value'], $fresh_subtotal);
                    }
                    $coupon_id_to_increment = (int)$valid_coupon['id'];
                } else {
                    unset($_SESSION['coupon_code'], $_SESSION['coupon_id'], $_SESSION['coupon_discount']);
                }
            }

            $fresh_total = max(0, $fresh_subtotal + $fresh_shipping - $fresh_discount);


            if ($fulfillment_type === 'delivery' && !$addr_id && $user_id && $full_name && $address && $city) {
                $ins2 = $conn->prepare("
                    INSERT INTO addresses (user_id, full_name, phone, address_line, city, state)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins2->execute([$user_id, $full_name, $phone, $address, $city, $state]);
                $addr_id = (int)$conn->lastInsertId();
            }

            $payment_notes = '';
            if (in_array($payment, $wallet_methods, true) && $wallet_phone !== '') {
                $payment_notes = ($payment === 'kbzpay' ? 'KBZPay' : 'WavePay') . ' number: ' . $wallet_phone;
            } elseif ($payment === 'card' && $card_name !== '') {
                $payment_notes = 'Card holder: ' . $card_name;
            }
            $final_notes = trim($notes . ($payment_notes ? ("\n" . $payment_notes) : ''));

            // Create order
            $ord = $conn->prepare("
                INSERT INTO orders
                    (user_id, address_id, fulfillment_type, pickup_location_id, status,
                     subtotal, shipping_fee, discount, total,
                     payment_method, payment_status, notes)
                VALUES (?, ?, ?, ?, 'Processing', ?, ?, ?, ?, ?, 'paid', ?)
            ");
            $ord->execute([
                $user_id,
                $addr_id,              
                $fulfillment_type,
                $pickup_location_id,  
                $fresh_subtotal,
                $fresh_shipping,
                $fresh_discount,
                $fresh_total,
                $payment_db,
                $final_notes !== '' ? $final_notes : null,
            ]);
            $order_id = (int)$conn->lastInsertId();

            // Insert order items & decrement stock
            $item_ins = $conn->prepare("
                INSERT INTO order_items
                    (order_id, product_id, product_name, product_sku, unit_price, quantity, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $upd_stock = $conn->prepare(
                "UPDATE products SET stock = stock - ?, sold = sold + ? WHERE id = ?"
            );

            foreach ($cart_items as $ci) {
                $item_ins->execute([
                    $order_id,
                    $ci['id'],
                    $ci['name'],
                    $ci['sku'],
                    $ci['price'],
                    $ci['qty'],
                    $ci['line_total'],
                ]);
                $upd_stock->execute([$ci['qty'], $ci['qty'], $ci['id']]);
            }

            // Increment coupon used_count only if coupon was still valid at order time
            if ($coupon_id_to_increment) {
                $upd_coupon = $conn->prepare(
                    "UPDATE coupons SET used_count = used_count + 1 WHERE id = ?"
                );
                $upd_coupon->execute([$coupon_id_to_increment]);
            }

            $conn->commit();

            // ── SIEM: log this completed payment so the detection engine
            // can score it (rule-based high-value check + Fraud Autoencoder
            // offline scoring, see fraud_inference.py). Fires after commit
            // so this never blocks or risks the actual order if the SIEM
            // is slow/unreachable -- siem_send_event() is fire-and-forget
            // with a short timeout (see siem_event_sender.php).
            siem_payment((string)$user_id, $fresh_total);


             // FIX: notify the customer their order was placed
            send_order_confirmation_email($conn, $order_id);

            // Clear cart & coupon from session
            $_SESSION['cart'] = [];
            unset($_SESSION['coupon_discount'], $_SESSION['coupon_code'], $_SESSION['coupon_id']);
            $order_success = true;

            $order_summary_stmt = $conn->prepare("
                SELECT
                    o.*,
                    a.full_name, a.phone, a.address_line, a.city, a.state,
                    sl.name         AS pickup_name,
                    sl.address_line AS pickup_address_line,
                    sl.city         AS pickup_city
                FROM orders o
                LEFT JOIN addresses       a  ON a.id = o.address_id
                LEFT JOIN store_locations sl ON sl.id = o.pickup_location_id
                WHERE o.id = ?
            ");
            $order_summary_stmt->execute([$order_id]);
            $order_summary = $order_summary_stmt->fetch(PDO::FETCH_ASSOC);

            $order_items_stmt = $conn->prepare("
                SELECT product_name, product_sku, unit_price, quantity, line_total
                FROM order_items WHERE order_id = ?
            ");
            $order_items_stmt->execute([$order_id]);
            $order_items_summary = $order_items_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log('Checkout PDOException: ' . $e->getMessage());
            $order_error = t('checkout_generic_error');
        }
    }
}

/* ── helper ── */
function product_img(?string $img): string
{
    if ($img && $img !== '') return "/LectroHub/admin/img/" . htmlspecialchars($img);
    return "https://placehold.co/72x72/070a10/00e5ff?text=?";
}

/* ── payment method display helper (used on the success/tracking view) ── */
function payment_method_label(string $method): string
{
    return match ($method) {
        'card'          => 'Credit / Debit Card',
        'paypal'        => 'PayPal',
        'kbzpay'        => 'KBZPay',
        'wavepay'       => 'WavePay',
        'bank_transfer' => 'Bank Transfer',
        default         => ucfirst($method),
    };
}


$posted_payment      = $_POST['payment_method']  ?? 'card';
if (!in_array($posted_payment, ['card', 'paypal', 'kbzpay', 'wavepay'], true)) {
    $posted_payment = 'card';
}
$posted_kbzpay_phone  = htmlspecialchars($_POST['kbzpay_phone']  ?? '');
$posted_wavepay_phone = htmlspecialchars($_POST['wavepay_phone'] ?? '');
$posted_card_name     = htmlspecialchars($_POST['card_name']     ?? '');
?>
<?php require_once "head.php" ?>
<?php require_once "navbar.php" ?>

<body> <br><br>
    
    <main class="checkout-page">

        <!-- BREADCRUMB -->
        <div class="co-breadcrumb">
            <a href="index.php"><?= htmlspecialchars(t('home')) ?></a>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="m9 18 6-6-6-6" />
            </svg>
            <a href="cart.php"><?= htmlspecialchars(t('cart')) ?></a>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="m9 18 6-6-6-6" />
            </svg>
            <span><?= htmlspecialchars(t('checkout')) ?></span>
        </div>

        <?php if ($order_success): ?>
            <!-- ══════════════════════════════════════════
         SUCCESS STATE + ORDER TRACKING
    ══════════════════════════════════════════ -->
            <?php
            // Map order status to a step index for the tracker
            $track_steps  = [
                'Processing' => t('track_processing'),
                'Confirmed'  => t('track_confirmed'),
                'Shipped'    => t('track_shipped'),
                'Delivered'  => t('track_delivered'),
            ];
            $track_keys      = array_keys($track_steps);
            $current_status  = $order_summary['status'] ?? 'Processing';
            $current_step    = array_search($current_status, $track_keys);
            if ($current_step === false) $current_step = 0; // unknown/cancelled falls back to step 1
            $is_cancelled = ($current_status === 'Cancelled');
            $is_pickup    = ($order_summary['fulfillment_type'] ?? 'delivery') === 'pickup';
            ?>
            <div class="order-success">
                <div class="os-icon">✅</div>
                <h1 class="os-title"><?= htmlspecialchars(t('order_placed_title')) ?></h1>
                <p class="os-sub">
                    <?= htmlspecialchars(t('order_placed_sub', ['id' => '#' . $order_id])) ?>
                    <?= $is_pickup ? htmlspecialchars(t('order_placed_pickup_suffix')) : '.' ?>
                </p>
                <div class="os-actions">
                    <a href="index.php" class="btn-co-primary"><?= htmlspecialchars(t('back_to_home')) ?></a>
                    <a href="products.php" class="btn-co-ghost"><?= htmlspecialchars(t('continue_shopping_plain')) ?></a>
                </div>
            </div>

            <div class="order-tracking-card">
                <div class="otc-head">
                    <h2><?= htmlspecialchars(t('track_your_order')) ?></h2>
                    <span class="otc-order-id"><?= htmlspecialchars(t('order_number_hash', ['id' => $order_id])) ?></span>
                </div>

                <?php if ($is_cancelled): ?>
                    <div class="otc-cancelled"><?= htmlspecialchars(t('order_cancelled_notice')) ?></div>
                <?php else: ?>
                    <div class="otc-progress">
                        <?php $i = 0;
                        foreach ($track_steps as $label): ?>
                            <div
                                class="otc-step <?= $i <= $current_step ? 'done' : '' ?> <?= $i === $current_step ? 'current' : '' ?>">
                                <div class="otc-dot"><?= $i < $current_step ? '✓' : ($i + 1) ?></div>
                                <div class="otc-label"><?= htmlspecialchars($label) ?></div>
                            </div>
                            <?php if ($i < count($track_steps) - 1): ?>
                                <div class="otc-line <?= $i < $current_step ? 'done' : '' ?>"></div>
                            <?php endif;
                            $i++; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="otc-divider"></div>

                <div class="otc-grid">
                    <!-- Items -->
                    <div class="otc-block">
                        <h3><?= htmlspecialchars(t('items')) ?></h3>
                        <?php foreach ($order_items_summary as $oi): ?>
                            <div class="otc-item-row">
                                <span class="otc-item-name"><?= htmlspecialchars($oi['product_name']) ?> ×
                                    <?= (int)$oi['quantity'] ?></span>
                                <span class="otc-item-price">$<?= number_format($oi['line_total'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="otc-item-row otc-total-row">
                            <span><?= htmlspecialchars(t('total')) ?></span>
                            <span>$<?= number_format($order_summary['total'] ?? 0, 2) ?></span>
                        </div>
                    </div>

                    <!-- Delivery / Pickup + Payment -->
                    <div class="otc-block">
                        <?php if ($is_pickup): ?>
                            <span class="otc-pickup-tag"><?= htmlspecialchars(t('store_pickup_tag')) ?></span>
                            <h3><?= htmlspecialchars(t('pickup_location')) ?></h3>
                            <p class="otc-addr"><?= htmlspecialchars($order_summary['pickup_name'] ?? '—') ?></p>
                            <p class="otc-addr">
                                <?= htmlspecialchars($order_summary['pickup_address_line'] ?? '') ?>,
                                <?= htmlspecialchars($order_summary['pickup_city'] ?? '') ?>
                            </p>
                        <?php else: ?>
                            <h3><?= htmlspecialchars(t('delivery_address')) ?></h3>
                            <?php if (!empty($order_summary['full_name'])): ?>
                                <p class="otc-addr"><?= htmlspecialchars($order_summary['full_name']) ?></p>
                                <p class="otc-addr"><?= htmlspecialchars($order_summary['address_line']) ?>,
                                    <?= htmlspecialchars($order_summary['city']) ?></p>
                                <?php if (!empty($order_summary['phone'])): ?>
                                    <p class="otc-addr"><?= htmlspecialchars($order_summary['phone']) ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="otc-addr">—</p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <h3 class="otc-pay-hd"><?= htmlspecialchars(t('payment')) ?></h3>
                        <p class="otc-addr">
                            <?= htmlspecialchars(payment_method_label($order_summary['payment_method'] ?? '')) ?>
                            · <span
                                class="otc-pay-status"><?= htmlspecialchars(ucfirst($order_summary['payment_status'] ?? '')) ?></span>
                        </p>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- ══════════════════════════════════════════
         CHECKOUT FORM
    ══════════════════════════════════════════ -->

            <h1 class="co-heading"><?= htmlspecialchars(t('checkout')) ?></h1>

            <?php if ($order_error): ?>
                <div class="co-error-banner">⚠️ <?= htmlspecialchars($order_error) ?></div>
            <?php endif; ?>

            <form method="POST" id="checkoutForm">
                <input type="hidden" name="place_order" value="1">

                <div class="co-layout">

                    <!-- ── LEFT: Fulfilment + Address + Payment ── -->
                    <div class="co-left">

                        <!-- ── STEP 1: FULFILMENT METHOD ── -->
                        <!-- FIX: Click & Collect — lets the customer choose store
                     pickup instead of delivery, mirroring Zara/Walmart's
                     pickup services (free, faster, drives store traffic). -->
                        <div class="co-card">
                            <div class="co-card-hd">
                                <span class="co-step">1</span>
                                <?= htmlspecialchars(t('step_fulfilment')) ?>
                            </div>

                            <div class="payment-options">

                                <label class="pay-option selected" id="fulfillDelivery">
                                    <input type="radio" name="fulfillment_type" value="delivery" checked
                                        onchange="selectFulfilment('delivery')">
                                    <div class="pay-icon">🚚</div>
                                    <div class="pay-body">
                                        <div class="pay-name"><?= htmlspecialchars(t('home_delivery')) ?></div>
                                        <div class="pay-sub"><?= htmlspecialchars(t('home_delivery_sub')) ?></div>
                                    </div>
                                </label>

                                <label class="pay-option <?= empty($store_locations) ? 'disabled' : '' ?>"
                                    id="fulfillPickup">
                                    <input type="radio" name="fulfillment_type" value="pickup"
                                        onchange="selectFulfilment('pickup')"
                                        <?= empty($store_locations) ? 'disabled' : '' ?>>
                                    <div class="pay-icon">🏬</div>
                                    <div class="pay-body">
                                        <div class="pay-name"><?= htmlspecialchars(t('store_pickup')) ?></div>
                                        <div class="pay-sub">
                                            <?= empty($store_locations) ? htmlspecialchars(t('store_pickup_sub_none')) : htmlspecialchars(t('store_pickup_sub_ok')) ?>
                                        </div>
                                    </div>
                                </label>

                            </div>
                        </div>

                        <!-- ── STEP 2a: DELIVERY ADDRESS (shown when Delivery selected) ── -->
                        <div class="co-card" id="addressCard">
                            <div class="co-card-hd">
                                <span class="co-step">2</span>
                                <?= htmlspecialchars(t('step_delivery_address')) ?>
                            </div>

                            <?php if (!empty($addresses)): ?>
                                <!-- Saved addresses -->
                                <div class="saved-addresses">
                                    <?php foreach ($addresses as $addr): ?>
                                        <label class="addr-option <?= $addr['is_default'] ? 'selected' : '' ?>"
                                            id="addrLabel<?= $addr['id'] ?>">
                                            <input type="radio" name="saved_address_id" value="<?= $addr['id'] ?>"
                                                data-city="<?= htmlspecialchars($addr['city']) ?>"
                                                <?= $addr['is_default'] ? 'checked' : '' ?>
                                                onchange="selectSavedAddr(<?= $addr['id'] ?>)">
                                            <div class="addr-body">
                                                <div class="addr-name"><?= htmlspecialchars($addr['full_name']) ?></div>
                                                <div class="addr-line"><?= htmlspecialchars($addr['address_line']) ?></div>
                                                <div class="addr-line"><?= htmlspecialchars($addr['city']) ?></div>
                                                <?php if ($addr['phone']): ?>
                                                    <div class="addr-phone"><?= htmlspecialchars($addr['phone']) ?></div>
                                                <?php endif; ?>
                                                <?php if ($addr['is_default']): ?>
                                                    <span class="addr-default-tag"><?= htmlspecialchars(t('default_tag')) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>

                                    <!-- Option to use a new address -->
                                    <label class="addr-option addr-new-option" id="addrLabelNew">
                                        <input type="radio" name="saved_address_id" value="" onchange="selectSavedAddr('new')">
                                        <div class="addr-body">
                                            <div class="addr-name"><?= htmlspecialchars(t('use_new_address')) ?></div>
                                        </div>
                                    </label>
                                </div>

                                <!-- New address fields — hidden until "new" is selected.
                         JS adds/removes the required attribute dynamically. -->
                                <div id="newAddrFields" style="display:none; margin-top:16px;">
                                <?php endif; ?>

                                <!-- Address fields always shown for guests (no saved addresses) -->
                                <?php if (empty($addresses)): ?>
                                    <div id="newAddrFields">
                                    <?php endif; ?>

                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label"><?= htmlspecialchars(t('full_name_label')) ?>
                                                *</label>
                                            <input type="text" name="full_name" class="form-input addr-required"
                                                value="<?= htmlspecialchars($user_name) ?>" placeholder="John Doe"
                                                <?= empty($addresses) ? 'required' : '' ?>>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label"><?= htmlspecialchars(t('phone')) ?></label>
                                            <input type="text" name="phone" class="form-input"
                                                placeholder="+49 151 0000 0000">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><?= htmlspecialchars(t('address_label')) ?> *</label>
                                        <input type="text" name="address_line" class="form-input addr-required"
                                            placeholder="<?= htmlspecialchars(t('street_and_number')) ?>"
                                            <?= empty($addresses) ? 'required' : '' ?>>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><?= htmlspecialchars(t('city_label')) ?> *</label>
                                        <select name="city" id="citySelect" class="form-input addr-required"
                                            <?= empty($addresses) ? 'required' : '' ?>>
                                            <option value="" disabled selected><?= htmlspecialchars(t('select_city')) ?>
                                            </option>
                                            <option value="Yangon">Yangon</option>
                                            <option value="Mandalay">Mandalay</option>
                                            <option value="Naypyidaw">Naypyidaw</option>
                                            <option value="Bago">Bago</option>
                                            <option value="Mawlamyine">Mawlamyine</option>
                                            <option value="Taunggyi">Taunggyi</option>
                                            <option value="Myitkyina">Myitkyina</option>
                                            <option value="Sittwe">Sittwe</option>
                                            <option value="Pathein">Pathein</option>
                                            <option value="Magway">Magway</option>
                                            <option value="Monywa">Monywa</option>
                                            <option value="Meiktila">Meiktila</option>
                                            <option value="Pyay">Pyay</option>
                                            <option value="Hpa-An">Hpa-An</option>
                                            <option value="Lashio">Lashio</option>
                                            <option value="Dawei">Dawei</option>
                                            <option value="Loikaw">Loikaw</option>
                                            <option value="Hakha">Hakha</option>
                                            <option value="Kengtung">Kengtung</option>
                                            <option value="Myeik">Myeik</option>
                                        </select>
                                    </div>
                                    <input type="hidden" name="state" value="">
                                    <input type="hidden" name="country" value="MM">

                                    <?php if ($user_id): ?>
                                        <div class="form-group save-addr-wrap">
                                            <label class="save-addr-label">
                                                <input type="checkbox" name="save_address" value="1" id="saveAddrCheck">
                                                <span><?= htmlspecialchars(t('save_address_future')) ?></span>
                                            </label>
                                        </div>
                                    <?php endif; ?>

                                    </div><!-- /newAddrFields -->
                                </div><!-- /addressCard -->

                                <!-- ── STEP 2b: PICKUP LOCATION (shown when Pickup selected) ── -->
                                <div class="co-card" id="pickupCard" style="display:none">
                                    <div class="co-card-hd">
                                        <span class="co-step">2</span>
                                        <?= htmlspecialchars(t('step_pickup_location')) ?>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label"><?= htmlspecialchars(t('choose_a_store')) ?> *</label>
                                        <select name="pickup_location_id" id="pickupLocationSelect" class="form-input">
                                            <option value="" disabled selected><?= htmlspecialchars(t('select_a_store')) ?>
                                            </option>
                                            <?php foreach ($store_locations as $loc): ?>
                                                <option value="<?= $loc['id'] ?>">
                                                    <?= htmlspecialchars($loc['name']) ?> — <?= htmlspecialchars($loc['city']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-hint"><?= htmlspecialchars(t('pickup_ready_hint')) ?></div>
                                    </div>
                                </div><!-- /pickupCard -->

                                <!-- ── STEP 3: PAYMENT METHOD ── -->
                                <!-- FIX: replaced KPay-only flow with 4 real options —
                                     Credit/Debit Card, PayPal, KBZPay, WavePay. Each
                                     option reveals its own fields via selectPay() below;
                                     hidden fields are never `required` so switching
                                     methods never blocks submission of the wrong panel. -->
                                <div class="co-card">
                                    <div class="co-card-hd">
                                        <span class="co-step">3</span>
                                        <?= htmlspecialchars(t('step_payment')) ?>
                                    </div>

                                    <div class="payment-options">

                                        <label class="pay-option <?= $posted_payment === 'card' ? 'selected' : '' ?>" id="payCard">
                                            <input type="radio" name="payment_method" value="card"
                                                <?= $posted_payment === 'card' ? 'checked' : '' ?>
                                                onchange="selectPay('payCard')">
                                            <div class="pay-icon">💳</div>
                                            <div class="pay-body">
                                                <div class="pay-name">Credit / Debit Card</div>
                                                <div class="pay-sub">Visa, Mastercard, and more</div>
                                            </div>
                                        </label>

                                        <label class="pay-option <?= $posted_payment === 'paypal' ? 'selected' : '' ?>" id="payPaypal">
                                            <input type="radio" name="payment_method" value="paypal"
                                                <?= $posted_payment === 'paypal' ? 'checked' : '' ?>
                                                onchange="selectPay('payPaypal')">
                                            <div class="pay-icon">🅿️</div>
                                            <div class="pay-body">
                                                <div class="pay-name">PayPal</div>
                                                <div class="pay-sub">Pay with your PayPal account</div>
                                            </div>
                                        </label>

                                        <label class="pay-option <?= $posted_payment === 'kbzpay' ? 'selected' : '' ?>" id="payKbz">
                                            <input type="radio" name="payment_method" value="kbzpay"
                                                <?= $posted_payment === 'kbzpay' ? 'checked' : '' ?>
                                                onchange="selectPay('payKbz')">
                                            <div class="pay-icon">📱</div>
                                            <div class="pay-body">
                                                <div class="pay-name">KBZPay</div>
                                                <div class="pay-sub">Pay with KBZ Pay wallet</div>
                                            </div>
                                        </label>

                                        <label class="pay-option <?= $posted_payment === 'wavepay' ? 'selected' : '' ?>" id="payWave">
                                            <input type="radio" name="payment_method" value="wavepay"
                                                <?= $posted_payment === 'wavepay' ? 'checked' : '' ?>
                                                onchange="selectPay('payWave')">
                                            <div class="pay-icon">🌊</div>
                                            <div class="pay-body">
                                                <div class="pay-name">WavePay</div>
                                                <div class="pay-sub">Pay with Wave Money wallet</div>
                                            </div>
                                        </label>

                                    </div>

                                    <!-- Credit / Debit Card fields -->
                                    <div id="cardFields" class="card-fields" style="<?= $posted_payment === 'card' ? '' : 'display:none' ?>">
                                        <div class="form-grid">
                                            <div class="form-group" style="grid-column:1/-1">
                                                <label class="form-label">Card Number *</label>
                                                <input type="text" class="form-input" name="card_number"
                                                    placeholder="1234 5678 9012 3456" maxlength="19" inputmode="numeric"
                                                    autocomplete="cc-number">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Expiry Date *</label>
                                                <input type="text" class="form-input" name="card_expiry"
                                                    placeholder="MM / YY" maxlength="7" autocomplete="cc-exp">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">CVV *</label>
                                                <input type="text" class="form-input" name="card_cvv"
                                                    placeholder="123" maxlength="4" inputmode="numeric"
                                                    autocomplete="cc-csc">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Name on Card *</label>
                                            <input type="text" class="form-input" name="card_name"
                                                value="<?= $posted_card_name ?>"
                                                placeholder="John Doe" autocomplete="cc-name">
                                        </div>
                                    </div>

                                    <!-- PayPal -->
                                    <div id="paypalFields" class="card-fields" style="<?= $posted_payment === 'paypal' ? '' : 'display:none' ?>">
                                        <div class="kpay-info">
                                            <div class="kpay-logo">🅿️ PayPal</div>
                                            <p class="kpay-note">You'll be redirected to PayPal to complete your payment securely after placing the order.</p>
                                        </div>
                                    </div>

                                    <!-- KBZPay -->
                                    <!-- FIX: unique field name (kbzpay_phone) instead of the
                                         shared "wallet_phone" both this and the WavePay input
                                         used to have — see the PHP-side note above for why
                                         that silently dropped whichever value you typed. -->
                                    <div id="kbzFields" class="card-fields" style="<?= $posted_payment === 'kbzpay' ? '' : 'display:none' ?>">
                                        <div class="kpay-info">
                                            <div class="kpay-logo">📱 KBZPay</div>
                                            <p class="kpay-note">Enter your registered KBZPay phone number to complete payment.</p>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">KBZPay Phone Number *</label>
                                            <input type="text" class="form-input wallet-phone-input" name="kbzpay_phone"
                                                value="<?= $posted_kbzpay_phone ?>"
                                                placeholder="09xxxxxxxxx" maxlength="12">
                                        </div>
                                    </div>

                                    <!-- WavePay -->
                                    <div id="waveFields" class="card-fields" style="<?= $posted_payment === 'wavepay' ? '' : 'display:none' ?>">
                                        <div class="kpay-info">
                                            <div class="kpay-logo">🌊 WavePay</div>
                                            <p class="kpay-note">Enter your registered WavePay phone number to complete payment.</p>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">WavePay Phone Number *</label>
                                            <input type="text" class="form-input wallet-phone-input" name="wavepay_phone"
                                                value="<?= $posted_wavepay_phone ?>"
                                                placeholder="09xxxxxxxxx" maxlength="12">
                                        </div>
                                    </div>

                                </div><!-- /co-card -->

                                <!-- ── STEP 4: NOTES ── -->
                                <div class="co-card">
                                    <div class="co-card-hd">
                                        <span class="co-step">4</span>
                                        <?= htmlspecialchars(t('step_order_notes')) ?> <span
                                            class="co-optional"><?= htmlspecialchars(t('optional')) ?></span>
                                    </div>
                                    <textarea name="notes" class="form-input form-textarea"
                                        placeholder="<?= htmlspecialchars(t('order_notes_placeholder')) ?>"></textarea>
                                </div>

                        </div><!-- /co-left -->

                        <!-- ── RIGHT: Order Summary ── -->
                        <div class="co-right">
                            <div class="co-summary-card">
                                <h2 class="cos-title"><?= htmlspecialchars(t('order_summary')) ?></h2>

                                <!-- Items -->
                                <div class="cos-items">
                                    <?php foreach ($cart_items as $ci): ?>
                                        <div class="cos-item">
                                            <div class="cos-img">
                                                <img src="<?= product_img($ci['img']) ?>"
                                                    alt="<?= htmlspecialchars($ci['name']) ?>">
                                                <span class="cos-qty-badge"><?= $ci['qty'] ?></span>
                                            </div>
                                            <div class="cos-info">
                                                <div class="cos-name"><?= htmlspecialchars($ci['name']) ?></div>
                                                <div class="cos-brand"><?= htmlspecialchars($ci['brand']) ?></div>
                                            </div>
                                            <div class="cos-price">$<?= number_format($ci['line_total'], 2) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="cos-divider"></div>

                                <!-- Coupon -->
                                <div class="cos-coupon">
                                    <input type="text" id="couponInput" class="coupon-input"
                                        placeholder="<?= htmlspecialchars(t('coupon_placeholder')) ?>"
                                        value="<?= htmlspecialchars($_SESSION['coupon_code'] ?? '') ?>">
                                    <button type="button" class="btn-coupon"
                                        onclick="applyCoupon()"><?= htmlspecialchars(t('apply')) ?></button>
                                </div>
                                <div class="coupon-msg" id="couponMsg">
                                    <?php if ($discount > 0): ?>
                                        <span class="success">✓ <?= htmlspecialchars(t('coupon_applied_prefix')) ?> —
                                            −$<?= number_format($discount, 2) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="cos-divider"></div>

                                <!-- Totals -->
                                <div class="cos-rows">
                                    <div class="cos-row">
                                        <span><?= htmlspecialchars(t('subtotal')) ?></span>
                                        <span>$<?= number_format($subtotal, 2) ?></span>
                                    </div>
                                    <div class="cos-row">
                                        <span
                                            id="cosShippingLabel"><?= htmlspecialchars(t('shipping')) ?><?= $default_ship_city ? ' <span class="cos-ship-city" id="cosShipCity">(' . htmlspecialchars($default_ship_city) . ')</span>' : '' ?></span>
                                        <span
                                            id="cosShippingValue"><?= $shipping > 0 ? '$' . number_format($shipping, 2) : '<span class="free-tag">' . htmlspecialchars(t('free')) . '</span>' ?></span>
                                    </div>
                                    <?php if ($discount > 0): ?>
                                        <div class="cos-row discount-row">
                                            <span><?= htmlspecialchars(t('discount')) ?></span>
                                            <span>−$<?= number_format($discount, 2) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="cos-divider"></div>

                                <div class="cos-total-row">
                                    <span><?= htmlspecialchars(t('total')) ?></span>
                                    <span id="cosTotalValue">$<?= number_format($total, 2) ?></span>
                                </div>

                                <!-- Submit -->
                                <button type="submit" class="btn-place-order" id="placeOrderBtn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                    </svg>
                                    <?= htmlspecialchars(t('place_order')) ?> · <span
                                        id="placeOrderTotal">$<?= number_format($total, 2) ?></span>
                                </button>

                                <div class="cos-trust">
                                    <span><?= htmlspecialchars(t('ssl_secured')) ?></span>
                                    <span><?= htmlspecialchars(t('returns_30day')) ?></span>
                                </div>

                                <a href="cart.php" class="cos-back"><?= htmlspecialchars(t('back_to_cart')) ?></a>
                            </div>
                        </div><!-- /co-right -->

                    </div><!-- /co-layout -->
            </form>

        <?php endif; ?>

    </main>

    <?php require_once "footer.php" ?>

    <!-- TOAST -->
    <div class="toast" id="toast">
        <span class="toast-icon" id="toastIcon">✓</span>
        <span id="toastMsg"><?= htmlspecialchars(t('toast_done')) ?></span>
    </div>

    <script>
        /* ══════════════════════════════════════════
   FIX: FULFILMENT TOGGLE (Click & Collect)
   Switches between the Delivery Address card
   and the Pickup Location card, and adjusts
   which fields are `required` so hidden fields
   never block form submission.
══════════════════════════════════════════ */
        function selectFulfilment(type) {
            document.getElementById('fulfillDelivery').classList.toggle('selected', type === 'delivery');
            document.getElementById('fulfillPickup').classList.toggle('selected', type === 'pickup');

            const addressCard = document.getElementById('addressCard');
            const pickupCard = document.getElementById('pickupCard');
            const pickupSelect = document.getElementById('pickupLocationSelect');

            if (type === 'pickup') {
                addressCard.style.display = 'none';
                pickupCard.style.display = '';

                // Hidden fields must never be `required`, or the browser blocks submit
                document.querySelectorAll('.addr-required').forEach(inp => inp.required = false);
                pickupSelect?.setAttribute('required', 'required');
            } else {
                addressCard.style.display = '';
                pickupCard.style.display = 'none';

                pickupSelect?.removeAttribute('required');

                // Restore address requirement based on whichever address mode is active
                const checkedSaved = document.querySelector('input[name="saved_address_id"]:checked');
                const usingNew = !checkedSaved || checkedSaved.value === '';
                document.querySelectorAll('.addr-required').forEach(inp => inp.required = usingNew);
            }

            refreshShipping();
        }

        /* ══════════════════════════════════════════
           FIX: PAYMENT METHOD TOGGLE
           Shows only the fields relevant to the chosen
           payment method (Card / PayPal / KBZPay /
           WavePay) and keeps `required` attributes in
           sync so a hidden panel's inputs never block
           submission of the one actually in use.
══════════════════════════════════════════ */
        function selectPay(id) {
            document.querySelectorAll('.pay-option').forEach(el => el.classList.remove('selected'));
            document.getElementById(id)?.classList.add('selected');

            const panels = {
                payCard:   'cardFields',
                payPaypal: 'paypalFields',
                payKbz:    'kbzFields',
                payWave:   'waveFields',
            };

            Object.values(panels).forEach(panelId => {
                const el = document.getElementById(panelId);
                if (el) el.style.display = 'none';
            });

            const activePanelId = panels[id];
            if (activePanelId) {
                const el = document.getElementById(activePanelId);
                if (el) el.style.display = 'block';
            }

            // Only the wallet phone input inside the currently visible
            // panel should be required. KBZPay and WavePay now each post
            // under their own field name (kbzpay_phone / wavepay_phone),
            // so there's no more risk of one clobbering the other server-
            // side. Disabling the inactive one too is just defense in
            // depth — it also keeps an unused, invisible field out of the
            // request entirely.
            document.querySelectorAll('.wallet-phone-input').forEach(inp => {
                const visible = inp.closest('.card-fields')?.style.display !== 'none';
                inp.required = visible;
                inp.disabled = !visible;
            });
        }

        /* ══════════════════════════════════════════
           FIX: SYNC UI WITH ACTUAL CHECKED RADIO ON LOAD
           Browsers (Chrome/Firefox) can restore a radio
           button's checked state on a form re-render —
           e.g. after this page reloads with a validation
           error — WITHOUT firing a `change` event. Since
           selectPay()/selectFulfilment() only ever run in
           response to onchange, this caused a visible
           mismatch: e.g. the KBZPay radio would show as
           selected (browser-restored) while its phone
           number field stayed hidden (still display:none,
           because selectPay('payKbz') never actually ran).
           Explicitly re-sync every panel to whatever is
           really checked as soon as the DOM is ready, so
           the visible UI always matches the real form state
           regardless of how that state got there.
        ══════════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', function () {
            const payMap = { card: 'payCard', paypal: 'payPaypal', kbzpay: 'payKbz', wavepay: 'payWave' };
            const checkedPay = document.querySelector('input[name="payment_method"]:checked');
            if (checkedPay && payMap[checkedPay.value]) {
                selectPay(payMap[checkedPay.value]);
            }

            const checkedFulfil = document.querySelector('input[name="fulfillment_type"]:checked');
            if (checkedFulfil) {
                selectFulfilment(checkedFulfil.value);
            }

            const checkedAddr = document.querySelector('input[name="saved_address_id"]:checked');
            if (checkedAddr && typeof selectSavedAddr === 'function') {
                selectSavedAddr(checkedAddr.value === '' ? 'new' : checkedAddr.value);
            }
        });

        /* ══════════════════════════════════════════
           LIVE SHIPPING RE-QUOTE
           Re-fetches the shipping fee from
           shipping_fee_action.php whenever the
           delivery city changes — either via the new
           -address <select id="citySelect"> or by
           picking a different saved address (each
           radio carries data-city).
           FIX: if Store Pickup is selected, shipping
           is always $0 and no fetch is made.
        ══════════════════════════════════════════ */
        (function() {
            const citySelect = document.getElementById('citySelect');
            const shippingEl = document.getElementById('cosShippingValue');
            const shipCityEl = document.getElementById('cosShipCity');
            const shipLabelEl = document.getElementById('cosShippingLabel');
            const totalEl = document.getElementById('cosTotalValue');
            const placeTotal = document.getElementById('placeOrderTotal');
            const savedRadios = document.querySelectorAll('input[name="saved_address_id"]');

            if (!shippingEl || !totalEl) return; // summary markup not present, nothing to wire

            function currentFulfilment() {
                return document.querySelector('input[name="fulfillment_type"]:checked')?.value || 'delivery';
            }

            function currentCity() {
                const checkedSaved = document.querySelector('input[name="saved_address_id"]:checked');
                if (checkedSaved && checkedSaved.value !== '') {
                    return checkedSaved.dataset.city || '';
                }
                return citySelect ? citySelect.value : '';
            }

            // Re-derive the subtotal/discount already rendered server-side so pickup
            // totals can be recalculated purely client-side without another request.
            function currentSubtotalMinusDiscount() {
                const totalNow = parseFloat((totalEl.textContent || '0').replace(/[^0-9.]/g, '')) || 0;
                const shippingNow = shippingEl.textContent.includes('Free') ?
                    0 :
                    parseFloat(shippingEl.textContent.replace(/[^0-9.]/g, '')) || 0;
                return totalNow - shippingNow; // subtotal - discount, whatever it currently is
            }

            window.refreshShipping = function refreshShipping() {
                if (currentFulfilment() === 'pickup') {
                    shippingEl.innerHTML = '<span class="free-tag">Free</span>';
                    if (shipLabelEl) shipLabelEl.childNodes[0].textContent = 'Shipping ';
                    if (shipCityEl) shipCityEl.textContent = '(Pickup)';

                    const base = currentSubtotalMinusDiscount();
                    const totalStr = '$' + base.toFixed(2);
                    totalEl.textContent = totalStr;
                    if (placeTotal) placeTotal.textContent = totalStr;
                    return;
                }

                const city = currentCity();
                if (!city) return;

                const fd = new FormData();
                fd.append('city', city);

                fetch('shipping_fee_action.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) return;

                        shippingEl.innerHTML = data.fee > 0 ?
                            '$' + data.fee.toFixed(2) :
                            '<span class="free-tag">Free</span>';

                        if (shipCityEl) shipCityEl.textContent = '(' + data.city + ')';

                        const totalStr = '$' + data.total.toFixed(2);
                        totalEl.textContent = totalStr;
                        if (placeTotal) placeTotal.textContent = totalStr;
                    })
                    .catch(() => {
                        /* leave last known values on the screen */
                    });
            };

            citySelect && citySelect.addEventListener('change', refreshShipping);
            savedRadios.forEach(r => r.addEventListener('change', refreshShipping));
        })();

    
        <?php if ($order_error): ?>
        document.addEventListener('DOMContentLoaded', function () {
            var target =
                document.querySelector('.wallet-phone-input') ||
                document.getElementById('addressCard') ||
                document.querySelector('.co-error-banner');
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (target.focus) target.focus({ preventScroll: true });
            }
        });
        <?php endif; ?>
    </script>

</body>
</html>