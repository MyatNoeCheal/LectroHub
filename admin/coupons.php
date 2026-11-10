<?php

require_once "auth_check.php";

$page_title = "Coupons — LectroHub Admin";

require_once "../lectrohub_db/connection.php";

$errors  = [];
$success = '';

/* ══════════════════════════════════════════
   HANDLE POST ACTIONS
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action     = $_POST['_action'] ?? '';
    $coupon_id  = (int)($_POST['coupon_id'] ?? 0);

    /* ── ADD / EDIT ── */
    if ($action === 'add' || $action === 'edit') {

        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $type        = $_POST['type'] ?? 'percent';
        $value       = $_POST['value'] ?? '';
        $min_order   = $_POST['min_order'] ?? '0';
        $usage_limit = trim($_POST['usage_limit'] ?? '');
        $expires_at  = trim($_POST['expires_at'] ?? '');
        $active      = isset($_POST['active']) ? 1 : 0;

        if ($code === '') $errors[] = 'Coupon code is required.';
        if (!in_array($type, ['percent', 'fixed'])) $errors[] = 'Invalid discount type.';
        if (!is_numeric($value) || $value <= 0) $errors[] = 'Enter a valid discount value.';
        if ($type === 'percent' && is_numeric($value) && $value > 100) $errors[] = 'Percentage value cannot exceed 100.';
        if (!is_numeric($min_order) || $min_order < 0) $errors[] = 'Enter a valid minimum order amount.';
        if ($usage_limit !== '' && (!ctype_digit($usage_limit) || (int)$usage_limit <= 0)) $errors[] = 'Usage limit must be a positive whole number.';

        $expires_sql = null;
        if ($expires_at !== '') {
            $ts = strtotime($expires_at);
            if ($ts === false) {
                $errors[] = 'Invalid expiry date.';
            } else {
                $expires_sql = date('Y-m-d 23:59:59', $ts);
            }
        }

        // Uniqueness check
        if (empty($errors)) {
            if ($action === 'add') {
                $chk = $conn->prepare("SELECT COUNT(*) FROM coupons WHERE code = ?");
                $chk->execute([$code]);
            } else {
                $chk = $conn->prepare("SELECT COUNT(*) FROM coupons WHERE code = ? AND id != ?");
                $chk->execute([$code, $coupon_id]);
            }
            if ($chk->fetchColumn() > 0) {
                $errors[] = 'Coupon code "' . htmlspecialchars($code) . '" already exists.';
            }
        }

        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO coupons (code, type, value, min_order, usage_limit, used_count, expires_at, active)
                        VALUES (:code, :type, :value, :min_order, :usage_limit, 0, :expires_at, :active)
                    ");
                    $stmt->execute([
                        ':code'        => $code,
                        ':type'        => $type,
                        ':value'       => number_format((float)$value, 2, '.', ''),
                        ':min_order'   => number_format((float)$min_order, 2, '.', ''),
                        ':usage_limit' => $usage_limit !== '' ? (int)$usage_limit : null,
                        ':expires_at'  => $expires_sql,
                        ':active'      => $active,
                    ]);
                    $success = 'Coupon "' . htmlspecialchars($code) . '" created successfully.';
                } else {
                    $stmt = $conn->prepare("
                        UPDATE coupons SET
                            code        = :code,
                            type        = :type,
                            value       = :value,
                            min_order   = :min_order,
                            usage_limit = :usage_limit,
                            expires_at  = :expires_at,
                            active      = :active
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':code'        => $code,
                        ':type'        => $type,
                        ':value'       => number_format((float)$value, 2, '.', ''),
                        ':min_order'   => number_format((float)$min_order, 2, '.', ''),
                        ':usage_limit' => $usage_limit !== '' ? (int)$usage_limit : null,
                        ':expires_at'  => $expires_sql,
                        ':active'      => $active,
                        ':id'          => $coupon_id,
                    ]);
                    $success = 'Coupon "' . htmlspecialchars($code) . '" updated successfully.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Failed to save coupon: ' . $e->getMessage();
            }
        }
    }

    /* ── TOGGLE ACTIVE ── */
    if ($action === 'toggle' && $coupon_id > 0) {
        try {
            $stmt = $conn->prepare("UPDATE coupons SET active = NOT active WHERE id = ?");
            $stmt->execute([$coupon_id]);
            $success = 'Coupon status updated.';
        } catch (PDOException $e) {
            $errors[] = 'Failed to update status: ' . $e->getMessage();
        }
    }

    /* ── DELETE ── */
    if ($action === 'delete' && $coupon_id > 0) {
        try {
            $conn->prepare("DELETE FROM coupons WHERE id = ?")->execute([$coupon_id]);
            $success = 'Coupon has been deleted.';
        } catch (PDOException $e) {
            $errors[] = 'Failed to delete coupon: ' . $e->getMessage();
        }
    }
}

/* ══════════════════════════════════════════
   LOAD COUPONS
══════════════════════════════════════════ */
$coupons = [];

try {
    $coupons = $conn->query("
        SELECT id, code, type, value, min_order, usage_limit, used_count, expires_at, active, created_at
        FROM coupons
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

/* ══════════════════════════════════════════
   DERIVED STATUS + STATISTICS
══════════════════════════════════════════ */
$now = time();

foreach ($coupons as &$c) {
    $is_expired = !empty($c['expires_at']) && strtotime($c['expires_at']) < $now;
    $is_maxed   = !empty($c['usage_limit']) && (int)$c['used_count'] >= (int)$c['usage_limit'];

    if ($is_expired) {
        $c['disp_status'] = 'Expired';
        $c['status_cls']  = 'b-red';
    } elseif ($is_maxed) {
        $c['disp_status'] = 'Fully Used';
        $c['status_cls']  = 'b-amber';
    } elseif ((int)$c['active'] === 1) {
        $c['disp_status'] = 'Active';
        $c['status_cls']  = 'b-green';
    } else {
        $c['disp_status'] = 'Inactive';
        $c['status_cls']  = 'b-grey';
    }
}
unset($c);

$total_coupons   = count($coupons);
$active_count    = count(array_filter($coupons, fn($c) => $c['disp_status'] === 'Active'));
$expired_count   = count(array_filter($coupons, fn($c) => $c['disp_status'] === 'Expired'));
$total_redeemed  = array_sum(array_column($coupons, 'used_count'));

/* ══════════════════════════════════════════
   FIX: SELL-THROUGH-DRIVEN DISCOUNT SUGGESTIONS
   Amazon case-study lesson — automate the link
   between slow-moving stock and promotional
   action instead of relying on an admin to
   notice it manually. Same sell-through formula
   already used on product_view.php
   (sold / (sold + stock)), surfaced here as
   one-click "Create Coupon" suggestions.
══════════════════════════════════════════ */
$discount_suggestions = [];
try {
    $discount_suggestions = $conn->query("
        SELECT
            id,
            product_name,
            sku,
            stock,
            sold,
            price,
            ROUND(sold / NULLIF(sold + stock, 0) * 100, 1) AS sell_through
        FROM products
        WHERE status = 'active'
          AND stock > 0
          AND (sold + stock) > 0
        HAVING sell_through < 40
        ORDER BY sell_through ASC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Non-critical — just don't show the panel if this fails.
    $discount_suggestions = [];
}

?>

<?php require_once "head.php"; ?>

<div class="sb-mobile-ovl" id="sbOvl" onclick="closeSb()"></div>

<div class="shell">

    <?php require_once "sidebar.php"; ?>
    <?php require_once "navbar.php"; ?>

    <main class="main">

        <!-- Page Header -->
        <div class="page-hd au">
            <div>
                <div class="ph-eyebrow">Marketing</div>
                <h1 class="ph-title">Coupons</h1>
                <p class="ph-sub"><?= $total_coupons ?> coupons &nbsp;·&nbsp; <?= $active_count ?> active &nbsp;·&nbsp; <?= number_format($total_redeemed) ?> total redemptions</p>
            </div>
            <div class="ph-actions">
                <button class="btn btn-primary btn-sm" onclick="openCouponModal()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Add Coupon
                </button>
            </div>
        </div>

        <!-- Success Banner -->
        <?php if ($success): ?>
            <div class="adm-card au" style="border-color:rgba(74,222,128,.3);background:rgba(74,222,128,.06);margin-bottom:20px;padding:14px 20px">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:20px">✅</span>
                    <span style="font-size:13px;font-weight:600;color:var(--green)"><?= htmlspecialchars($success) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Banner -->
        <?php if (!empty($errors)): ?>
            <div class="adm-card au" style="border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.06);margin-bottom:20px;padding:14px 20px">
                <div style="display:flex;gap:10px;align-items:flex-start">
                    <span style="font-size:20px">⚠️</span>
                    <div>
                        <?php foreach ($errors as $err): ?>
                            <div style="font-size:13px;font-weight:600;color:var(--red)"><?= htmlspecialchars($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stat Strip -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">
            <?php
            $strips = [
                ['label' => 'Total Coupons',      'value' => $total_coupons,             'color' => 'var(--ac)',     'icon' => '🏷️'],
                ['label' => 'Active',             'value' => $active_count,              'color' => 'var(--green)',  'icon' => '✅'],
                ['label' => 'Expired',            'value' => $expired_count,             'color' => 'var(--red)',    'icon' => '⏰'],
                ['label' => 'Total Redemptions',  'value' => number_format($total_redeemed), 'color' => 'var(--purple)', 'icon' => '🎟️'],
            ];
            foreach ($strips as $s):
            ?>
                <div style="display:flex;align-items:center;gap:10px;padding:12px 18px;background:var(--card);border:1px solid var(--bd);border-radius:var(--r);flex:1;min-width:150px">
                    <span style="font-size:18px"><?= $s['icon'] ?></span>
                    <div>
                        <div style="font-family:var(--fd);font-size:20px;font-weight:800;color:<?= $s['color'] ?>;line-height:1"><?= $s['value'] ?></div>
                        <div style="font-size:10px;color:var(--muted);margin-top:2px"><?= $s['label'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ══════════════════════════════════════════
             FIX: SUGGESTED FOR DISCOUNT PANEL
             Products under 40% sell-through, ranked
             worst-first, each with a one-click
             "Create Coupon" button that pre-fills the
             existing Add Coupon modal.
        ══════════════════════════════════════════ -->
        <?php if (!empty($discount_suggestions)): ?>
        <div class="adm-card au" style="margin-bottom:20px;border-color:rgba(245,158,11,.3)">
            <div class="card-hd">
                <div>
                    <div class="card-title">💡 Suggested for Discount</div>
                    <div class="card-sub">Products under 40% sell-through — slow movers that could use a promotional push</div>
                </div>
                <span class="badge b-amber"><?= count($discount_suggestions) ?> flagged</span>
            </div>
            <div class="card-body p0">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Sell-through</th>
                            <th>Stock</th>
                            <th>Sold</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($discount_suggestions as $ds):
                            $st = (float)$ds['sell_through'];
                            $st_cls = $st < 15 ? 'b-red' : 'b-amber';
                            // Steeper suggested discount the worse the sell-through is
                            $suggested_pct = $st < 15 ? 20 : ($st < 25 ? 15 : 10);
                        ?>
                            <tr>
                                <td><span style="font-size:13px;font-weight:600;color:var(--txt)"><?= htmlspecialchars($ds['product_name']) ?></span></td>
                                <td><span class="td-mono" style="font-size:11px;color:var(--dim)"><?= htmlspecialchars($ds['sku']) ?></span></td>
                                <td><span class="badge <?= $st_cls ?>"><?= $st ?>%</span></td>
                                <td><span class="td-mono" style="color:var(--muted)"><?= (int)$ds['stock'] ?></span></td>
                                <td><span class="td-mono" style="color:var(--muted)"><?= (int)$ds['sold'] ?></span></td>
                                <td><span class="td-mono" style="font-weight:600">$<?= number_format($ds['price'], 2) ?></span></td>
                                <td>
                                    <button class="btn btn-secondary btn-sm"
                                        onclick="suggestCoupon('<?= addslashes($ds['sku']) ?>', '<?= addslashes(htmlspecialchars($ds['product_name'], ENT_QUOTES)) ?>', <?= $suggested_pct ?>)">
                                        Create <?= $suggested_pct ?>% Coupon
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar au" style="margin-bottom:16px">
            <input type="text" id="searchInput" placeholder="Search by coupon code…" oninput="filterCoupons()">
            <select id="typeFilter" onchange="filterCoupons()">
                <option value="all">All Types</option>
                <option value="percent">Percentage</option>
                <option value="fixed">Fixed Amount</option>
            </select>
            <select id="statusFilter" onchange="filterCoupons()">
                <option value="all">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Expired">Expired</option>
                <option value="Fully Used">Fully Used</option>
            </select>
            <div class="filter-spacer"></div>
            <span style="font-size:11px;color:var(--dim)" id="resultCount"><?= $total_coupons ?> coupons</span>
        </div>

        <!-- Coupons Table -->
        <div class="adm-card au">
            <div class="card-body p0">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Min. Order</th>
                            <th>Usage</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="couponTableBody">
                        <?php foreach ($coupons as $c):

                            $usage_label = $c['usage_limit']
                                ? number_format($c['used_count']) . ' / ' . number_format($c['usage_limit'])
                                : number_format($c['used_count']) . ' / ∞';

                            $usage_pct = $c['usage_limit']
                                ? min(100, round(($c['used_count'] / $c['usage_limit']) * 100))
                                : 0;

                            $value_label = $c['type'] === 'percent'
                                ? number_format($c['value'], 0) . '%'
                                : '$' . number_format($c['value'], 2);

                            $expires_label = $c['expires_at']
                                ? date('M j, Y', strtotime($c['expires_at']))
                                : 'No expiry';
                        ?>
                            <tr data-code="<?= strtolower($c['code']) ?>"
                                data-type="<?= $c['type'] ?>"
                                data-status="<?= $c['disp_status'] ?>">

                                <td>
                                    <span class="td-mono" style="color:var(--ac);font-weight:700;font-size:13px"><?= htmlspecialchars($c['code']) ?></span>
                                </td>

                                <td>
                                    <span class="badge b-grey"><?= $c['type'] === 'percent' ? 'Percentage' : 'Fixed Amount' ?></span>
                                </td>

                                <td>
                                    <span class="td-mono" style="font-weight:700;color:var(--txt)"><?= $value_label ?></span>
                                </td>

                                <td>
                                    <span class="td-mono" style="color:var(--muted)">$<?= number_format($c['min_order'], 2) ?></span>
                                </td>

                                <td>
                                    <div style="display:flex;flex-direction:column;gap:4px;min-width:90px">
                                        <span class="td-mono" style="font-size:11px;color:var(--muted)"><?= $usage_label ?></span>
                                        <?php if ($c['usage_limit']): ?>
                                            <div style="height:5px;background:var(--card3);border-radius:3px;overflow:hidden;border:1px solid var(--bd)">
                                                <div style="height:100%;width:<?= $usage_pct ?>%;background:<?= $usage_pct >= 100 ? 'var(--red)' : 'var(--ac)' ?>"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <span style="font-size:12px;color:var(--muted)"><?= $expires_label ?></span>
                                </td>

                                <td>
                                    <span class="badge <?= $c['status_cls'] ?>"><?= $c['disp_status'] ?></span>
                                </td>

                                <td>
                                    <div style="display:flex;gap:5px;align-items:center">

                                        <!-- Edit -->
                                        <button class="btn btn-icon btn-sm" title="Edit coupon"
                                            onclick="openCouponModal(
                                                <?= (int)$c['id'] ?>,
                                                '<?= addslashes($c['code']) ?>',
                                                '<?= $c['type'] ?>',
                                                <?= (float)$c['value'] ?>,
                                                <?= (float)$c['min_order'] ?>,
                                                <?= $c['usage_limit'] !== null ? (int)$c['usage_limit'] : 'null' ?>,
                                                '<?= $c['expires_at'] ? date('Y-m-d', strtotime($c['expires_at'])) : '' ?>',
                                                <?= (int)$c['active'] ?>
                                            )">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </button>

                                        <!-- Toggle Active -->
                                        <form method="POST" action="coupons.php" style="display:inline">
                                            <input type="hidden" name="_action" value="toggle">
                                            <input type="hidden" name="coupon_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-icon btn-sm" title="<?= $c['active'] ? 'Deactivate' : 'Activate' ?>">
                                                <?php if ($c['active']): ?>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="12" r="10" />
                                                        <path d="M9 12l2 2 4-4" />
                                                    </svg>
                                                <?php else: ?>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="12" r="10" />
                                                        <line x1="8" y1="12" x2="16" y2="12" />
                                                    </svg>
                                                <?php endif; ?>
                                            </button>
                                        </form>

                                        <!-- Delete -->
                                        <button class="btn btn-icon btn-sm" title="Delete coupon"
                                            style="color:rgba(239,68,68,.7)"
                                            onclick="openDeleteModal(<?= (int)$c['id'] ?>, '<?= addslashes($c['code']) ?>')">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6" />
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                <path d="M10 11v6M14 11v6" />
                                            </svg>
                                        </button>

                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Empty state -->
                <div id="emptyState" style="display:none;padding:56px 24px;text-align:center">
                    <div style="font-size:40px;margin-bottom:12px">🏷️</div>
                    <div style="font-family:var(--fd);font-size:14px;font-weight:700;color:var(--txt)">No coupons found</div>
                    <div style="font-size:12px;color:var(--muted);margin-top:4px">Try adjusting your search or filters.</div>
                </div>
            </div>
        </div>

    </main>
</div>

<?php require_once "footer.php"; ?>

<div class="toast" id="toast"><span id="ti" style="font-size:17px">✓</span><span id="tm"></span></div>

<!-- ── Add / Edit Coupon Modal ── -->
<div id="couponModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:28px;max-width:440px;width:90%;box-shadow:var(--sh2);max-height:90vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt)" id="cm-title">Add Coupon</div>
            <button type="button" onclick="closeCouponModal()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;line-height:1;padding:4px">✕</button>
        </div>

        <form method="POST" action="coupons.php" id="couponForm">
            <input type="hidden" name="_action" id="cm-action" value="add">
            <input type="hidden" name="coupon_id" id="cm-id" value="">

            <div style="display:flex;flex-direction:column;gap:16px">

                <div class="form-group" style="margin:0">
                    <label class="form-label">Coupon Code <span class="req">*</span></label>
                    <input type="text" name="code" id="cm-code" class="form-input" placeholder="e.g. SUMMER15" style="text-transform:uppercase" required>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Discount Type <span class="req">*</span></label>
                        <select name="type" id="cm-type" class="form-input">
                            <option value="percent">Percentage (%)</option>
                            <option value="fixed">Fixed Amount ($)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Value <span class="req">*</span></label>
                        <input type="number" name="value" id="cm-value" class="form-input" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Minimum Order</label>
                        <input type="number" name="min_order" id="cm-min-order" class="form-input" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Usage Limit</label>
                        <input type="number" name="usage_limit" id="cm-usage-limit" class="form-input" min="1" placeholder="Unlimited">
                    </div>
                </div>

                <div class="form-group" style="margin:0">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expires_at" id="cm-expires" class="form-input">
                    <div class="form-hint">Leave empty for no expiry.</div>
                </div>

                <label class="toggle-row">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--txt)">Active</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px">Coupon can be redeemed by customers</div>
                    </div>
                    <div class="toggle-wrap">
                        <input type="checkbox" name="active" id="cm-active" class="toggle-input" checked>
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    </div>
                </label>

            </div>

            <div style="display:flex;gap:10px;margin-top:24px">
                <button type="button" class="btn btn-secondary" style="flex:1;justify-content:center" onclick="closeCouponModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center" id="cm-submit">Create Coupon</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirm Modal ── -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid rgba(239,68,68,.25);border-radius:var(--r);padding:28px;max-width:380px;width:90%;box-shadow:var(--sh2)">
        <div style="font-size:32px;margin-bottom:12px;text-align:center">🗑️</div>
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:6px">Delete Coupon?</div>
        <div style="font-size:13px;font-weight:600;color:var(--txt);text-align:center;margin-bottom:4px" id="deleteModalCode"></div>
        <div style="font-size:12px;color:var(--dim);text-align:center;margin-bottom:24px">This will permanently remove the coupon. This action cannot be undone.</div>
        <div style="display:flex;gap:10px">
            <button class="btn btn-secondary" style="flex:1" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" action="coupons.php" style="flex:1">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="coupon_id" id="deleteCouponId">
                <button type="submit" class="btn" style="width:100%;background:var(--red);color:#fff;justify-content:center">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
    /* ── Sidebar ── */
    function toggleSb() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sbOvl').classList.toggle('show');
    }
    function closeSb() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sbOvl').classList.remove('show');
    }
    function toggleDrop(id) {
        document.querySelectorAll('.nav-dropdown.open').forEach(d => { if (d.id !== id) d.classList.remove('open'); });
        document.getElementById(id).classList.toggle('open');
    }
    document.addEventListener('click', e => {
        if (!e.target.closest('.nav-dropdown'))
            document.querySelectorAll('.nav-dropdown.open').forEach(d => d.classList.remove('open'));
    });

    /* ── Toast ── */
    let toastTimer;
    function showToast(msg, icon = '✓') {
        const t = document.getElementById('toast');
        document.getElementById('ti').textContent = icon;
        document.getElementById('tm').textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
    }

    /* ── Filter / Search ── */
    let debounceTimer;
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(filterCoupons, 200);
    });

    function filterCoupons() {
        const q      = document.getElementById('searchInput').value.toLowerCase().trim();
        const type   = document.getElementById('typeFilter').value;
        const status = document.getElementById('statusFilter').value;
        const rows   = document.querySelectorAll('#couponTableBody tr[data-code]');
        let visible  = 0;

        rows.forEach(row => {
            const matchQ = !q || row.dataset.code.includes(q);
            const matchT = type   === 'all' || row.dataset.type   === type;
            const matchS = status === 'all' || row.dataset.status === status;
            const show   = matchQ && matchT && matchS;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('resultCount').textContent = visible + ' coupon' + (visible !== 1 ? 's' : '');
        document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
    }

    /* ── Add / Edit Modal ── */
    function openCouponModal(id, code, type, value, minOrder, usageLimit, expiresAt, active) {
        const isEdit = typeof id !== 'undefined';

        document.getElementById('cm-title').textContent  = isEdit ? 'Edit Coupon' : 'Add Coupon';
        document.getElementById('cm-action').value        = isEdit ? 'edit' : 'add';
        document.getElementById('cm-id').value             = isEdit ? id : '';
        document.getElementById('cm-submit').textContent   = isEdit ? 'Save Changes' : 'Create Coupon';

        document.getElementById('cm-code').value        = isEdit ? code : '';
        document.getElementById('cm-type').value         = isEdit ? type : 'percent';
        document.getElementById('cm-value').value        = isEdit ? value : '';
        document.getElementById('cm-min-order').value    = isEdit ? minOrder : '';
        document.getElementById('cm-usage-limit').value  = isEdit && usageLimit !== null ? usageLimit : '';
        document.getElementById('cm-expires').value      = isEdit ? expiresAt : '';
        document.getElementById('cm-active').checked     = isEdit ? !!active : true;

        document.getElementById('couponModal').style.display = 'flex';
    }
    function closeCouponModal() { document.getElementById('couponModal').style.display = 'none'; }
    document.getElementById('couponModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeCouponModal(); });

    document.getElementById('couponForm').addEventListener('submit', () => {
        showToast('Saving coupon…', '💾');
    });

    /* ── Delete Modal ── */
    function openDeleteModal(id, code) {
        document.getElementById('deleteCouponId').value       = id;
        document.getElementById('deleteModalCode').textContent = code;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
    document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteModal(); });

    /* ══════════════════════════════════════════
       FIX: SUGGESTED DISCOUNT → COUPON PREFILL
       Opens the existing Add Coupon modal in
       "add" mode and prefills a sensible code +
       percentage based on the flagged product,
       so admins go from "spotted a slow mover"
       to "coupon ready to save" in one click.
    ══════════════════════════════════════════ */
    function suggestCoupon(sku, name, suggestedPct) {
        openCouponModal(); // opens in add mode with defaults

        const cleanSku = sku.replace(/[^A-Z0-9]/gi, '').toUpperCase();
        document.getElementById('cm-code').value  = 'SAVE' + suggestedPct + '-' + cleanSku;
        document.getElementById('cm-type').value  = 'percent';
        document.getElementById('cm-value').value = suggestedPct;

        showToast('Prefilled a ' + suggestedPct + '% coupon for ' + name, '💡');
    }

    /* ── ESC ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeSb();
            closeCouponModal();
            closeDeleteModal();
        }
    });
</script>

</body>
</html>