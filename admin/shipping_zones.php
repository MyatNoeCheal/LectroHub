<?php

require_once "auth_check.php";

$page_title = "Shipping Zones — LectroHub Admin";

require_once "../lectrohub_db/connection.php";

$errors  = [];
$success = '';

/* ══════════════════════════════════════════
   CITY LIST — kept in sync with the delivery
   city <select> in customer/checkout.php.
══════════════════════════════════════════ */
$mm_cities = [
    'Yangon', 'Mandalay', 'Naypyidaw', 'Bago', 'Mawlamyine', 'Taunggyi',
    'Myitkyina', 'Sittwe', 'Pathein', 'Magway', 'Monywa', 'Meiktila',
    'Pyay', 'Hpa-An', 'Lashio', 'Dawei', 'Loikaw', 'Hakha', 'Kengtung', 'Myeik',
];

/* ══════════════════════════════════════════
   HANDLE POST ACTIONS
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action  = $_POST['_action'] ?? '';
    $zone_id = (int)($_POST['zone_id'] ?? 0);

    /* ── ADD / EDIT ── */
    if ($action === 'add' || $action === 'edit') {

        $city       = trim($_POST['city'] ?? '');
        $fee        = $_POST['fee'] ?? '';
        $threshold  = trim($_POST['free_shipping_threshold'] ?? '');
        $active     = isset($_POST['active']) ? 1 : 0;
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if ($city === '') $errors[] = 'City is required.';
        if (!is_numeric($fee) || $fee < 0) $errors[] = 'Enter a valid shipping fee.';

        $threshold_sql = null;
        if ($threshold !== '') {
            if (!is_numeric($threshold) || $threshold < 0) {
                $errors[] = 'Enter a valid free-shipping threshold, or leave it blank.';
            } else {
                $threshold_sql = number_format((float)$threshold, 2, '.', '');
            }
        }

        // Uniqueness check
        if (empty($errors)) {
            if ($action === 'add') {
                $chk = $conn->prepare("SELECT COUNT(*) FROM shipping_zones WHERE city = ?");
                $chk->execute([$city]);
            } else {
                $chk = $conn->prepare("SELECT COUNT(*) FROM shipping_zones WHERE city = ? AND id != ?");
                $chk->execute([$city, $zone_id]);
            }
            if ($chk->fetchColumn() > 0) {
                $errors[] = 'A shipping zone for "' . htmlspecialchars($city) . '" already exists.';
            }
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                // Only one zone can be the default fallback at a time.
                if ($is_default) {
                    $conn->exec("UPDATE shipping_zones SET is_default = 0");
                }

                if ($action === 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO shipping_zones (city, fee, free_shipping_threshold, is_default, active)
                        VALUES (:city, :fee, :threshold, :is_default, :active)
                    ");
                    $stmt->execute([
                        ':city'       => $city,
                        ':fee'        => number_format((float)$fee, 2, '.', ''),
                        ':threshold'  => $threshold_sql,
                        ':is_default' => $is_default,
                        ':active'     => $active,
                    ]);
                    $success = 'Shipping zone for "' . htmlspecialchars($city) . '" created successfully.';
                } else {
                    $stmt = $conn->prepare("
                        UPDATE shipping_zones SET
                            city = :city,
                            fee  = :fee,
                            free_shipping_threshold = :threshold,
                            is_default = :is_default,
                            active = :active
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':city'       => $city,
                        ':fee'        => number_format((float)$fee, 2, '.', ''),
                        ':threshold'  => $threshold_sql,
                        ':is_default' => $is_default,
                        ':active'     => $active,
                        ':id'         => $zone_id,
                    ]);
                    $success = 'Shipping zone for "' . htmlspecialchars($city) . '" updated successfully.';
                }

                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = 'Failed to save shipping zone: ' . $e->getMessage();
            }
        }
    }

    /* ── TOGGLE ACTIVE ── */
    if ($action === 'toggle' && $zone_id > 0) {
        try {
            $conn->prepare("UPDATE shipping_zones SET active = NOT active WHERE id = ?")->execute([$zone_id]);
            $success = 'Zone status updated.';
        } catch (PDOException $e) {
            $errors[] = 'Failed to update status: ' . $e->getMessage();
        }
    }

    /* ── DELETE ── */
    if ($action === 'delete' && $zone_id > 0) {
        try {
            $conn->prepare("DELETE FROM shipping_zones WHERE id = ?")->execute([$zone_id]);
            $success = 'Shipping zone has been deleted.';
        } catch (PDOException $e) {
            $errors[] = 'Failed to delete zone: ' . $e->getMessage();
        }
    }
}

/* ══════════════════════════════════════════
   LOAD ZONES
══════════════════════════════════════════ */
$zones = [];

try {
    $zones = $conn->query("
        SELECT id, city, fee, free_shipping_threshold, is_default, active, created_at
        FROM shipping_zones
        ORDER BY is_default DESC, city ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

/* ══════════════════════════════════════════
   STATISTICS
══════════════════════════════════════════ */
$total_zones  = count($zones);
$active_count = count(array_filter($zones, fn($z) => (int)$z['active'] === 1));
$default_zone = null;
foreach ($zones as $z) {
    if ((int)$z['is_default'] === 1) { $default_zone = $z; break; }
}
$avg_fee = $total_zones ? array_sum(array_column($zones, 'fee')) / $total_zones : 0;

// Which of the fixed city list still has no zone configured
$configured_cities = array_column($zones, 'city');
$unconfigured_count = count(array_diff($mm_cities, $configured_cities));

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
                <div class="ph-eyebrow">Fulfillment</div>
                <h1 class="ph-title">Shipping Zones</h1>
                <p class="ph-sub"><?= $total_zones ?> zones &nbsp;·&nbsp; <?= $active_count ?> active &nbsp;·&nbsp; <?= $default_zone ? 'Default: ' . htmlspecialchars($default_zone['city']) : 'No default zone set' ?></p>
            </div>
            <div class="ph-actions">
                <button class="btn btn-primary btn-sm" onclick="openZoneModal()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Add Zone
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

        <!-- No-default warning -->
        <?php if (!$default_zone && $total_zones > 0): ?>
            <div class="adm-card au" style="border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.06);margin-bottom:20px;padding:14px 20px">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:20px">⚠️</span>
                    <span style="font-size:13px;font-weight:600;color:var(--amber)">No default zone is set — cities without a configured zone will fall back to a hardcoded €4.99 rate. Edit a zone and check "Set as Default" to fix this.</span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stat Strip -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">
            <?php
            $strips = [
                ['label' => 'Total Zones',        'value' => $total_zones,                          'color' => 'var(--ac)',     'icon' => '📍'],
                ['label' => 'Active',              'value' => $active_count,                         'color' => 'var(--green)',  'icon' => '✅'],
                ['label' => 'Avg. Fee',            'value' => '$' . number_format($avg_fee, 2),       'color' => 'var(--purple)', 'icon' => '💰'],
                ['label' => 'Unconfigured Cities', 'value' => $unconfigured_count,                   'color' => 'var(--amber)',  'icon' => '🗺️'],
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

        <!-- Filter Bar -->
        <div class="filter-bar au" style="margin-bottom:16px">
            <input type="text" id="searchInput" placeholder="Search by city…" oninput="filterZones()">
            <select id="statusFilter" onchange="filterZones()">
                <option value="all">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
            <div class="filter-spacer"></div>
            <span style="font-size:11px;color:var(--dim)" id="resultCount"><?= $total_zones ?> zones</span>
        </div>

        <!-- Zones Table -->
        <div class="adm-card au">
            <div class="card-body p0">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>City</th>
                            <th>Fee</th>
                            <th>Free Shipping At</th>
                            <th>Default</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="zoneTableBody">
                        <?php foreach ($zones as $z):

                            $status_label = (int)$z['active'] === 1 ? 'Active' : 'Inactive';
                            $status_cls   = (int)$z['active'] === 1 ? 'b-green' : 'b-grey';

                            $threshold_label = $z['free_shipping_threshold'] !== null
                                ? '$' . number_format($z['free_shipping_threshold'], 2)
                                : 'Never free';
                        ?>
                            <tr data-city="<?= strtolower($z['city']) ?>"
                                data-status="<?= $status_label ?>">

                                <td>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <span style="font-family:var(--fd);font-weight:700;color:var(--txt);font-size:13px"><?= htmlspecialchars($z['city']) ?></span>
                                        <?php if ($z['is_default']): ?>
                                            <span class="badge b-purple" title="Fallback zone for unconfigured cities">★ Default</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="td-mono" style="font-weight:700;color:var(--ac)">$<?= number_format($z['fee'], 2) ?></span>
                                </td>

                                <td>
                                    <span style="font-size:12px;color:var(--muted)"><?= $threshold_label ?></span>
                                </td>

                                <td>
                                    <?= $z['is_default'] ? '<span class="badge b-purple">Yes</span>' : '<span style="font-size:12px;color:var(--dim)">—</span>' ?>
                                </td>

                                <td>
                                    <span class="badge <?= $status_cls ?>"><?= $status_label ?></span>
                                </td>

                                <td>
                                    <div style="display:flex;gap:5px;align-items:center">

                                        <!-- Edit -->
                                        <button class="btn btn-icon btn-sm" title="Edit zone"
                                            onclick="openZoneModal(
                                                <?= (int)$z['id'] ?>,
                                                '<?= addslashes($z['city']) ?>',
                                                <?= (float)$z['fee'] ?>,
                                                <?= $z['free_shipping_threshold'] !== null ? (float)$z['free_shipping_threshold'] : 'null' ?>,
                                                <?= (int)$z['active'] ?>,
                                                <?= (int)$z['is_default'] ?>
                                            )">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </button>

                                        <!-- Toggle Active -->
                                        <form method="POST" action="shipping_zones.php" style="display:inline">
                                            <input type="hidden" name="_action" value="toggle">
                                            <input type="hidden" name="zone_id" value="<?= (int)$z['id'] ?>">
                                            <button type="submit" class="btn btn-icon btn-sm" title="<?= $z['active'] ? 'Deactivate' : 'Activate' ?>">
                                                <?php if ($z['active']): ?>
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
                                        <button class="btn btn-icon btn-sm" title="Delete zone"
                                            style="color:rgba(239,68,68,.7)"
                                            onclick="openDeleteModal(<?= (int)$z['id'] ?>, '<?= addslashes($z['city']) ?>')">
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
                    <div style="font-size:40px;margin-bottom:12px">📍</div>
                    <div style="font-family:var(--fd);font-size:14px;font-weight:700;color:var(--txt)">No shipping zones found</div>
                    <div style="font-size:12px;color:var(--muted);margin-top:4px">Try adjusting your search or filters.</div>
                </div>
            </div>
        </div>

    </main>
</div>

<?php require_once "footer.php"; ?>

<div class="toast" id="toast"><span id="ti" style="font-size:17px">✓</span><span id="tm"></span></div>

<!-- ── Add / Edit Zone Modal ── -->
<div id="zoneModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:28px;max-width:440px;width:90%;box-shadow:var(--sh2);max-height:90vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt)" id="zm-title">Add Shipping Zone</div>
            <button type="button" onclick="closeZoneModal()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;line-height:1;padding:4px">✕</button>
        </div>

        <form method="POST" action="shipping_zones.php" id="zoneForm">
            <input type="hidden" name="_action" id="zm-action" value="add">
            <input type="hidden" name="zone_id" id="zm-id" value="">

            <div style="display:flex;flex-direction:column;gap:16px">

                <div class="form-group" style="margin:0">
                    <label class="form-label">City <span class="req">*</span></label>
                    <select name="city" id="zm-city" class="form-input" required>
                        <option value="" disabled selected>Select city…</option>
                        <?php foreach ($mm_cities as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Must match a city option in checkout.php exactly.</div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Shipping Fee <span class="req">*</span></label>
                        <div class="input-prefix-wrap">
                            <span class="input-prefix">$</span>
                            <input type="number" name="fee" id="zm-fee" class="form-input has-prefix" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Free Shipping At</label>
                        <div class="input-prefix-wrap">
                            <span class="input-prefix">$</span>
                            <input type="number" name="free_shipping_threshold" id="zm-threshold" class="form-input has-prefix" step="0.01" min="0" placeholder="Never free">
                        </div>
                    </div>
                </div>
                <div class="form-hint" style="margin-top:-8px">Leave "Free Shipping At" blank if this city should never get free shipping.</div>

                <label class="toggle-row">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--txt)">Set as Default Zone</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px">Used as the fallback fee for any city without its own zone</div>
                    </div>
                    <div class="toggle-wrap">
                        <input type="checkbox" name="is_default" id="zm-default" class="toggle-input">
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    </div>
                </label>

                <label class="toggle-row">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--txt)">Active</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:2px">Inactive zones fall through to the default zone</div>
                    </div>
                    <div class="toggle-wrap">
                        <input type="checkbox" name="active" id="zm-active" class="toggle-input" checked>
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    </div>
                </label>

            </div>

            <div style="display:flex;gap:10px;margin-top:24px">
                <button type="button" class="btn btn-secondary" style="flex:1;justify-content:center" onclick="closeZoneModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center" id="zm-submit">Create Zone</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirm Modal ── -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid rgba(239,68,68,.25);border-radius:var(--r);padding:28px;max-width:380px;width:90%;box-shadow:var(--sh2)">
        <div style="font-size:32px;margin-bottom:12px;text-align:center">🗑️</div>
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:6px">Delete Shipping Zone?</div>
        <div style="font-size:13px;font-weight:600;color:var(--txt);text-align:center;margin-bottom:4px" id="deleteModalCity"></div>
        <div style="font-size:12px;color:var(--dim);text-align:center;margin-bottom:24px">Orders to this city will fall back to the default zone rate. This action cannot be undone.</div>
        <div style="display:flex;gap:10px">
            <button class="btn btn-secondary" style="flex:1" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" action="shipping_zones.php" style="flex:1">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="zone_id" id="deleteZoneId">
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
        debounceTimer = setTimeout(filterZones, 200);
    });

    function filterZones() {
        const q      = document.getElementById('searchInput').value.toLowerCase().trim();
        const status = document.getElementById('statusFilter').value;
        const rows   = document.querySelectorAll('#zoneTableBody tr[data-city]');
        let visible  = 0;

        rows.forEach(row => {
            const matchQ = !q || row.dataset.city.includes(q);
            const matchS = status === 'all' || row.dataset.status === status;
            const show   = matchQ && matchS;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('resultCount').textContent = visible + ' zone' + (visible !== 1 ? 's' : '');
        document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
    }

    /* ── Add / Edit Modal ── */
    function openZoneModal(id, city, fee, threshold, active, isDefault) {
        const isEdit = typeof id !== 'undefined';

        document.getElementById('zm-title').textContent = isEdit ? 'Edit Shipping Zone' : 'Add Shipping Zone';
        document.getElementById('zm-action').value       = isEdit ? 'edit' : 'add';
        document.getElementById('zm-id').value            = isEdit ? id : '';
        document.getElementById('zm-submit').textContent  = isEdit ? 'Save Changes' : 'Create Zone';

        document.getElementById('zm-city').value      = isEdit ? city : '';
        document.getElementById('zm-fee').value        = isEdit ? fee : '';
        document.getElementById('zm-threshold').value  = isEdit && threshold !== null ? threshold : '';
        document.getElementById('zm-active').checked   = isEdit ? !!active : true;
        document.getElementById('zm-default').checked  = isEdit ? !!isDefault : false;

        document.getElementById('zoneModal').style.display = 'flex';
    }
    function closeZoneModal() { document.getElementById('zoneModal').style.display = 'none'; }
    document.getElementById('zoneModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeZoneModal(); });

    document.getElementById('zoneForm').addEventListener('submit', () => {
        showToast('Saving zone…', '💾');
    });

    /* ── Delete Modal ── */
    function openDeleteModal(id, city) {
        document.getElementById('deleteZoneId').value        = id;
        document.getElementById('deleteModalCity').textContent = city;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
    document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteModal(); });

    /* ── ESC ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeSb();
            closeZoneModal();
            closeDeleteModal();
        }
    });
</script>

</body>
</html>