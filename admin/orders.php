<?php

require_once "auth_check.php";

$page_title = "Orders — LectroHub Admin";

require_once "../lectrohub_db/connection.php";
require_once "../lectrohub_db/mailer.php";  

$errors  = [];
$success = '';

/* ══════════════════════════════════════════
   HANDLE POST ACTIONS
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action   = $_POST['_action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    /* ── UPDATE STATUS ── */
    if ($action === 'update_status' && $order_id > 0) {
    $new_status = $_POST['status'] ?? '';
    $allowed    = ['Processing', 'Shipped', 'Delivered', 'Cancelled'];

    if (!in_array($new_status, $allowed)) {
        $errors[] = 'Invalid status selected.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);

            // Delivered gets a full itemized receipt email;
            // Processing / Shipped / Cancelled get a plain status-update email.
            if ($new_status === 'Delivered') {
                send_delivery_confirmation_email($conn, $order_id);
            } else {
                send_order_status_email($conn, $order_id, $new_status);
            }

            $success = 'Order #' . str_pad($order_id, 4, '0', STR_PAD_LEFT) . ' status updated to ' . $new_status . '.';
        } catch (PDOException $e) {
            $errors[] = 'Failed to update order: ' . $e->getMessage();
        }
    }
}
 
    /* ── DELETE ORDER ── */
    if ($action === 'delete' && $order_id > 0) {
        try {
            $conn->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
            $conn->prepare("DELETE FROM orders WHERE id = ?")->execute([$order_id]);
            $success = 'Order #' . str_pad($order_id, 4, '0', STR_PAD_LEFT) . ' has been deleted.';
        } catch (PDOException $e) {
            $errors[] = 'Failed to delete order: ' . $e->getMessage();
        }
    }
}

/* ══════════════════════════════════════════
   LOAD ORDERS
══════════════════════════════════════════ */
$orders = [];

try {
    $orders = $conn->query("
        SELECT
            o.id,
            o.status,
            o.payment_status,
            o.total,
            o.created_at,
            u.full_name   AS customer,
            u.email       AS customer_email,
            COUNT(oi.id)  AS item_count
        FROM orders o
        LEFT JOIN users       u  ON u.id = o.user_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        GROUP BY o.id, o.status, o.payment_status, o.total, o.created_at, u.full_name, u.email
        ORDER BY o.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

/* ══════════════════════════════════════════
   STATISTICS
══════════════════════════════════════════ */
$total_orders     = count($orders);
$total_revenue    = array_sum(array_column(
    array_filter($orders, fn($o) => $o['payment_status'] === 'paid'),
    'total'
));
$processing_count = count(array_filter($orders, fn($o) => $o['status'] === 'Processing'));
$shipped_count    = count(array_filter($orders, fn($o) => $o['status'] === 'Shipped'));
$delivered_count  = count(array_filter($orders, fn($o) => $o['status'] === 'Delivered'));
$cancelled_count  = count(array_filter($orders, fn($o) => $o['status'] === 'Cancelled'));

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
                <div class="ph-eyebrow">Commerce</div>
                <h1 class="ph-title">Orders</h1>
                <p class="ph-sub"><?= $total_orders ?> orders &nbsp;·&nbsp; $<?= number_format($total_revenue, 0) ?> total revenue</p>
            </div>
            <div class="ph-actions">
                <button class="btn btn-secondary btn-sm" onclick="showToast('Export started','📤')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Export
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
                ['label' => 'Total Orders', 'value' => $total_orders,     'color' => 'var(--ac)',     'icon' => '📦'],
                ['label' => 'Processing',   'value' => $processing_count, 'color' => 'var(--amber)',  'icon' => '⏳'],
                ['label' => 'Shipped',      'value' => $shipped_count,    'color' => 'var(--purple)', 'icon' => '🚚'],
                ['label' => 'Delivered',    'value' => $delivered_count,  'color' => 'var(--green)',  'icon' => '✅'],
                ['label' => 'Cancelled',    'value' => $cancelled_count,  'color' => 'var(--red)',    'icon' => '🚫'],
            ];
            foreach ($strips as $s):
            ?>
                <div style="display:flex;align-items:center;gap:10px;padding:12px 18px;background:var(--card);border:1px solid var(--bd);border-radius:var(--r);flex:1;min-width:130px">
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
            <input type="text" id="searchInput" placeholder="Search by order ID, customer, email…" oninput="filterOrders()">
            <select id="statusFilter" onchange="filterOrders()">
                <option value="all">All Status</option>
                <option value="Processing">Processing</option>
                <option value="Shipped">Shipped</option>
                <option value="Delivered">Delivered</option>
                <option value="Cancelled">Cancelled</option>
            </select>
            <select id="paymentFilter" onchange="filterOrders()">
                <option value="all">All Payments</option>
                <option value="paid">Paid</option>
                <option value="pending">Pending</option>
                <option value="failed">Failed</option>
            </select>
            <div class="filter-spacer"></div>
            <span style="font-size:11px;color:var(--dim)" id="resultCount"><?= $total_orders ?> orders</span>
        </div>

        <!-- Orders Table -->
        <div class="adm-card au">
            <div class="card-body p0">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="orderTableBody">
                        <?php foreach ($orders as $o):

                            $order_ref = '#ORD-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);

                            $status_cls = match ($o['status']) {
                                'Delivered'  => 'b-green',
                                'Shipped'    => 'b-cyan',
                                'Processing' => 'b-amber',
                                'Cancelled'  => 'b-red',
                                default      => 'b-grey'
                            };

                            $pay_cls = match ($o['payment_status']) {
                                'paid'    => 'b-green',
                                'pending' => 'b-amber',
                                'failed'  => 'b-red',
                                default   => 'b-grey'
                            };

                            $customer_initial = strtoupper(substr($o['customer'] ?? 'G', 0, 1));

                        ?>
                            <tr data-order="<?= strtolower($order_ref) ?>"
                                data-customer="<?= strtolower(htmlspecialchars($o['customer'] ?? 'guest')) ?>"
                                data-email="<?= strtolower(htmlspecialchars($o['customer_email'] ?? '')) ?>"
                                data-status="<?= htmlspecialchars($o['status']) ?>"
                                data-payment="<?= htmlspecialchars($o['payment_status']) ?>">

                                <td>
                                    <span class="td-mono" style="color:var(--ac);font-weight:600"><?= $order_ref ?></span>
                                </td>

                                <td>
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <div style="width:32px;height:32px;border-radius:50%;background:rgba(0,229,255,.1);display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:13px;font-weight:700;color:var(--ac);flex-shrink:0">
                                            <?= htmlspecialchars($customer_initial) ?>
                                        </div>
                                        <div>
                                            <div style="font-size:13px;font-weight:500;color:var(--txt)"><?= htmlspecialchars($o['customer'] ?? 'Guest') ?></div>
                                            <div style="font-size:10px;color:var(--muted);margin-top:1px"><?= htmlspecialchars($o['customer_email'] ?? '—') ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="td-mono" style="font-size:12px;color:var(--dim)"><?= (int)$o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></span>
                                </td>

                                <td>
                                    <span class="td-mono" style="font-weight:700;color:var(--txt)">$<?= number_format($o['total'], 2) ?></span>
                                </td>

                                <td>
                                    <span class="badge <?= $pay_cls ?>"><?= ucfirst($o['payment_status']) ?></span>
                                </td>

                                <td>
                                    <span class="badge <?= $status_cls ?>"><?= htmlspecialchars($o['status']) ?></span>
                                </td>

                                <td>
                                    <span style="font-size:12px;color:var(--muted)"><?= date('M j, Y', strtotime($o['created_at'])) ?></span>
                                    <div style="font-size:10px;color:var(--dim);margin-top:1px"><?= date('H:i', strtotime($o['created_at'])) ?></div>
                                </td>

                                <td>
                                    <div style="display:flex;gap:5px;align-items:center">

                                        <!-- View -->
                                        <button class="btn btn-icon btn-sm" title="View order"
                                            onclick="openViewModal(
                                                <?= $o['id'] ?>,
                                                '<?= addslashes($order_ref) ?>',
                                                '<?= addslashes(htmlspecialchars($o['customer'] ?? 'Guest', ENT_QUOTES)) ?>',
                                                '<?= addslashes(htmlspecialchars($o['customer_email'] ?? '—', ENT_QUOTES)) ?>',
                                                '<?= addslashes($o['status']) ?>',
                                                '<?= addslashes($o['payment_status']) ?>',
                                                <?= (int)$o['item_count'] ?>,
                                                '$<?= number_format($o['total'], 2) ?>',
                                                '<?= date('M j, Y · H:i', strtotime($o['created_at'])) ?>'
                                            )">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </button>

                                        <!-- Edit status -->
                                        <button class="btn btn-icon btn-sm" title="Update status"
                                            onclick="openStatusModal(<?= $o['id'] ?>, '<?= addslashes($order_ref) ?>', '<?= addslashes($o['status']) ?>')">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>

                                        <!-- Delete -->
                                        <button class="btn btn-icon btn-sm" title="Delete order"
                                            style="color:rgba(239,68,68,.7)"
                                            onclick="openDeleteModal(<?= $o['id'] ?>, '<?= addslashes($order_ref) ?>')">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                                <path d="M10 11v6M14 11v6"/>
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
                    <div style="font-size:40px;margin-bottom:12px">📦</div>
                    <div style="font-family:var(--fd);font-size:14px;font-weight:700;color:var(--txt)">No orders found</div>
                    <div style="font-size:12px;color:var(--muted);margin-top:4px">Try adjusting your search or filters.</div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination au">
            <span class="pag-info" id="pagInfo">Showing 1–<?= $total_orders ?> of <?= $total_orders ?> orders</span>
            <div class="pag-btns">
                <button class="pag-btn" disabled>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="pag-btn active">1</button>
                <button class="pag-btn" onclick="showToast('Pagination coming soon','📄')">2</button>
                <button class="pag-btn" onclick="showToast('Pagination coming soon','📄')">3</button>
                <button class="pag-btn" onclick="showToast('Pagination coming soon','📄')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>

    </main>
</div>

<?php require_once "footer.php"; ?>

<div class="toast" id="toast"><span id="ti" style="font-size:17px">✓</span><span id="tm"></span></div>

<!-- ── View Order Modal ── -->
<div id="viewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:28px;max-width:440px;width:90%;box-shadow:var(--sh2)">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <div>
                <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt)" id="vm-ref"></div>
                <div style="font-size:11px;color:var(--dim);margin-top:2px" id="vm-date"></div>
            </div>
            <button onclick="closeViewModal()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;line-height:1;padding:4px">✕</button>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px">

            <div style="padding:14px;background:var(--card3);border:1px solid var(--bd);border-radius:calc(var(--r) - 4px)">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Customer</div>
                <div style="font-size:13px;font-weight:600;color:var(--txt)" id="vm-name"></div>
                <div style="font-size:11px;color:var(--muted);margin-top:2px" id="vm-email"></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                <div style="padding:12px;background:var(--card3);border:1px solid var(--bd);border-radius:calc(var(--r) - 4px);text-align:center">
                    <div style="font-family:var(--fm);font-size:18px;font-weight:700;color:var(--ac)" id="vm-total"></div>
                    <div style="font-size:10px;color:var(--muted);margin-top:3px">Total</div>
                </div>
                <div style="padding:12px;background:var(--card3);border:1px solid var(--bd);border-radius:calc(var(--r) - 4px);text-align:center">
                    <div style="font-family:var(--fm);font-size:18px;font-weight:700;color:var(--txt)" id="vm-items"></div>
                    <div style="font-size:10px;color:var(--muted);margin-top:3px">Items</div>
                </div>
                <div style="padding:12px;background:var(--card3);border:1px solid var(--bd);border-radius:calc(var(--r) - 4px);text-align:center">
                    <div style="font-size:11px;font-weight:600;margin-top:2px" id="vm-payment"></div>
                    <div style="font-size:10px;color:var(--muted);margin-top:3px">Payment</div>
                </div>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--card3);border:1px solid var(--bd);border-radius:calc(var(--r) - 4px)">
                <span style="font-size:12px;color:var(--muted)">Order Status</span>
                <span class="badge" id="vm-status-badge"></span>
            </div>

        </div>

        <div style="display:flex;gap:10px;margin-top:20px">
            <button class="btn btn-secondary" style="flex:1;justify-content:center" onclick="closeViewModal()">Close</button>
            <button class="btn btn-primary" style="flex:1;justify-content:center" id="vm-edit-btn">Update Status</button>
        </div>
    </div>
</div>

<!-- ── Update Status Modal ── -->
<div id="statusModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:28px;max-width:400px;width:90%;box-shadow:var(--sh2)">
        <div style="font-size:28px;margin-bottom:12px;text-align:center">📋</div>
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:4px">Update Order Status</div>
        <div style="font-size:12px;color:var(--muted);text-align:center;margin-bottom:22px" id="statusModalRef"></div>
        <form method="POST" action="orders.php">
            <input type="hidden" name="_action" value="update_status">
            <input type="hidden" name="order_id" id="statusOrderId">
            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:22px">
                <?php
                $statuses = [
                    ['val' => 'Processing', 'icon' => '⏳', 'desc' => 'Order is being prepared'],
                    ['val' => 'Shipped',    'icon' => '🚚', 'desc' => 'Order is on its way'],
                    ['val' => 'Delivered',  'icon' => '✅', 'desc' => 'Customer received the order'],
                    ['val' => 'Cancelled',  'icon' => '🚫', 'desc' => 'Order has been cancelled'],
                ];
                foreach ($statuses as $st):
                ?>
                <label class="status-opt" data-val="<?= $st['val'] ?>" style="display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--bd);border-radius:calc(var(--r) - 4px);cursor:pointer;transition:border-color .15s,background .15s">
                    <input type="radio" name="status" value="<?= $st['val'] ?>" onchange="highlightStatus(this)">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--txt)"><?= $st['icon'] ?> <?= $st['val'] ?></div>
                        <div style="font-size:11px;color:var(--muted);margin-top:1px"><?= $st['desc'] ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:10px">
                <button type="button" class="btn btn-secondary" style="flex:1" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center">Save Status</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirm Modal ── -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid rgba(239,68,68,.25);border-radius:var(--r);padding:28px;max-width:380px;width:90%;box-shadow:var(--sh2)">
        <div style="font-size:32px;margin-bottom:12px;text-align:center">🗑️</div>
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:6px">Delete Order?</div>
        <div style="font-size:13px;font-weight:600;color:var(--txt);text-align:center;margin-bottom:4px" id="deleteModalRef"></div>
        <div style="font-size:12px;color:var(--dim);text-align:center;margin-bottom:24px">This will permanently remove the order and all its items.</div>
        <div style="display:flex;gap:10px">
            <button class="btn btn-secondary" style="flex:1" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" action="orders.php" style="flex:1">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="order_id" id="deleteOrderId">
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
        debounceTimer = setTimeout(filterOrders, 200);
    });

    function filterOrders() {
        const q       = document.getElementById('searchInput').value.toLowerCase().trim();
        const status  = document.getElementById('statusFilter').value;
        const payment = document.getElementById('paymentFilter').value;
        const rows    = document.querySelectorAll('#orderTableBody tr[data-order]');
        let visible   = 0;

        rows.forEach(row => {
            const matchQ = !q || row.dataset.order.includes(q) ||
                           row.dataset.customer.includes(q) ||
                           row.dataset.email.includes(q);
            const matchS = status  === 'all' || row.dataset.status  === status;
            const matchP = payment === 'all' || row.dataset.payment === payment;
            const show   = matchQ && matchS && matchP;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('resultCount').textContent = visible + ' order' + (visible !== 1 ? 's' : '');
        document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
    }

    /* ── View Modal ── */
    const statusBadgeMap = {
        'Delivered':  'b-green',
        'Shipped':    'b-cyan',
        'Processing': 'b-amber',
        'Cancelled':  'b-red'
    };
    const payColorMap = {
        'paid':    'var(--green)',
        'pending': 'var(--amber)',
        'failed':  'var(--red)'
    };

    function openViewModal(id, ref, name, email, status, payment, items, total, date) {
        document.getElementById('vm-ref').textContent   = ref;
        document.getElementById('vm-date').textContent  = date;
        document.getElementById('vm-name').textContent  = name;
        document.getElementById('vm-email').textContent = email;
        document.getElementById('vm-total').textContent = total;
        document.getElementById('vm-items').textContent = items;
        document.getElementById('vm-payment').textContent = payment.charAt(0).toUpperCase() + payment.slice(1);
        document.getElementById('vm-payment').style.color = payColorMap[payment] || 'var(--muted)';

        const badge = document.getElementById('vm-status-badge');
        badge.textContent = status;
        badge.className   = 'badge ' + (statusBadgeMap[status] || 'b-grey');

        document.getElementById('vm-edit-btn').onclick = () => {
            closeViewModal();
            openStatusModal(id, ref, status);
        };

        document.getElementById('viewModal').style.display = 'flex';
    }
    function closeViewModal() { document.getElementById('viewModal').style.display = 'none'; }
    document.getElementById('viewModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeViewModal(); });

    /* ── Status Modal ── */
    function openStatusModal(id, ref, currentStatus) {
        document.getElementById('statusOrderId').value        = id;
        document.getElementById('statusModalRef').textContent = ref;

        document.querySelectorAll('.status-opt input[type=radio]').forEach(r => {
            r.checked = r.value === currentStatus;
            highlightStatus(r);
        });

        document.getElementById('statusModal').style.display = 'flex';
    }
    function closeStatusModal() { document.getElementById('statusModal').style.display = 'none'; }
    document.getElementById('statusModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeStatusModal(); });

    function highlightStatus(radio) {
        document.querySelectorAll('.status-opt').forEach(lbl => {
            const r      = lbl.querySelector('input[type=radio]');
            const active = r && r.checked;
            lbl.style.borderColor = active ? 'var(--ac)' : 'var(--bd)';
            lbl.style.background  = active ? 'rgba(0,229,255,.04)' : '';
        });
    }

    /* ── Delete Modal ── */
    function openDeleteModal(id, ref) {
        document.getElementById('deleteOrderId').value       = id;
        document.getElementById('deleteModalRef').textContent = ref;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
    document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteModal(); });

    /* ── ESC ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeSb();
            closeViewModal();
            closeStatusModal();
            closeDeleteModal();
        }
    });
</script>

</body>
</html>