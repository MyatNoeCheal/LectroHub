<?php

require_once "auth_check.php";

$page_title = "Products — LectroHub Admin";

require_once "../lectrohub_db/connection.php";

$products = [];
$categories = [];
$brands = [];

try {

    /* Products */
    $sql = "
SELECT
    p.id,
    p.product_name AS name,
    b.brand_name AS brand,
    p.sku,
    p.description,
    c.category_name AS category,
    p.price,
    p.stock,
    p.sold,
    p.image AS img,
    p.featured,

    CASE
        WHEN p.status = 'active' THEN 'Active'
        WHEN p.status = 'draft' THEN 'Draft'
        WHEN p.status = 'inactive' THEN 'Inactive'
        ELSE 'Draft'
    END AS status

FROM products p
INNER JOIN categories c ON p.category_id = c.id
INNER JOIN brands b ON p.brand_id = b.id
ORDER BY p.id DESC
";

    $stmt = $conn->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Categories */
    $sql = "
        SELECT category_name
        FROM categories
        ORDER BY category_name
    ";

    $stmt = $conn->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    /* Brands */
    $sql = "
        SELECT brand_name
        FROM brands
        ORDER BY brand_name
    ";

    $stmt = $conn->query($sql);
    $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}

/* =========================
   Dashboard Statistics
========================= */

$total = count($products);

$active = count(array_filter(
    $products,
    fn($p) => (($p['stock'] ?? 0) == 0 ? 'Out of Stock' : $p['status']) === 'Active'
));

$oos = count(array_filter(
    $products,
    fn($p) => ($p['stock'] ?? 0) == 0
));

$low = count(array_filter(
    $products,
    fn($p) => ($p['stock'] ?? 0) > 0 && $p['stock'] < 20
));

$featured = count(array_filter(
    $products,
    fn($p) => (int)$p['featured'] === 1
));

/* Optional: reusable display status helper (recommended for UI later) */
$getDispStatus = function ($p) {
    return (($p['stock'] ?? 0) == 0)
        ? 'Out of Stock'
        : $p['status'];
};

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
                    <div class="ph-eyebrow">Catalogue</div>
                    <h1 class="ph-title">Products</h1>
                    <p class="ph-sub"><?= $total ?> products · <?= $active ?> active · <?= $oos ?> out of stock</p>
                </div>
                <div class="ph-actions">
                    <button class="btn btn-secondary btn-sm" onclick="showToast('Importing products…','📥')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="17 8 12 3 7 8" />
                            <line x1="12" y1="3" x2="12" y2="15" />
                        </svg>
                        Import
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="showToast('Export started','📤')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        Export
                    </button>
                    <a href="products_create.php" class="btn btn-primary btn-sm">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Add Product
                    </a>
                </div>
            </div>

            <!-- Summary Stat Strip -->
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px">
                <?php
                $strips = [
                    ['label' => 'Total Products', 'value' => $total,    'color' => 'var(--ac)',     'icon' => '📦'],
                    ['label' => 'Active',         'value' => $active,   'color' => 'var(--green)',  'icon' => '✅'],
                    ['label' => 'Low Stock',      'value' => $low,      'color' => 'var(--amber)',  'icon' => '⚠️'],
                    ['label' => 'Out of Stock',   'value' => $oos,      'color' => 'var(--red)',    'icon' => '🚫'],
                    ['label' => 'Featured',       'value' => $featured, 'color' => 'var(--purple)', 'icon' => '⭐'],
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

            <!-- Bulk Action Bar -->
            <div class="bulk-bar" id="bulkBar">
                <span class="bulk-count" id="bulkCount">0 selected</span>
                <button class="btn btn-secondary btn-sm" onclick="showToast('Status updated','✅')">Set Active</button>
                <button class="btn btn-secondary btn-sm" onclick="showToast('Marked as draft','📝')">Set Draft</button>
                <button class="btn btn-secondary btn-sm" onclick="showToast('Feature toggled','⭐')">Toggle Featured</button>
                <button class="btn btn-secondary btn-sm" style="color:var(--red);border-color:rgba(239,68,68,.3)" onclick="showToast('Products deleted','🗑️')">Delete</button>
                <button class="btn btn-secondary btn-sm" style="margin-left:auto" onclick="clearSelection()">Cancel</button>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar au">
                <input type="text" id="searchInput" placeholder="Search products, SKUs, brands…">
                <select id="catFilter" onchange="filterProducts()">
                    <option value="All">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="brandFilter" onchange="filterProducts()">
                    <option value="All">All Brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option><?= htmlspecialchars($b) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="statusFilter" onchange="filterProducts()">
                    <option>All Status</option>
                    <option>Active</option>
                    <option>Draft</option>
                    <option>Out of Stock</option>
                </select>
                <div class="filter-spacer"></div>
                <span style="font-size:11px;color:var(--dim)" id="resultCount"><?= $total ?> products</span>
                <div class="view-toggle">
                    <button class="vt-btn active" id="gridBtn" onclick="setView('grid')" title="Grid view">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7" />
                            <rect x="14" y="3" width="7" height="7" />
                            <rect x="3" y="14" width="7" height="7" />
                            <rect x="14" y="14" width="7" height="7" />
                        </svg>
                    </button>
                    <button class="vt-btn" id="listBtn" onclick="setView('list')" title="List view">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6" />
                            <line x1="8" y1="12" x2="21" y2="12" />
                            <line x1="8" y1="18" x2="21" y2="18" />
                            <line x1="3" y1="6" x2="3.01" y2="6" />
                            <line x1="3" y1="12" x2="3.01" y2="12" />
                            <line x1="3" y1="18" x2="3.01" y2="18" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ── GRID VIEW ── -->
<div class="product-grid au" id="gridView">

    <?php foreach ($products as $p):

        $status_cls = match ($p['status']) {
            'Active'       => 'b-green',
            'Draft'        => 'b-grey',
            'Out of Stock' => 'b-red',
            default        => 'b-grey'
        };

        $stock_color = $p['stock'] == 0
            ? 'var(--red)'
            : ($p['stock'] < 20 ? 'var(--amber)' : 'var(--green)');

        // ✅ IMAGE SAFE FALLBACK
        $imgFile = !empty($p['img']) ? $p['img'] : 'default-product.webp';
    ?>

        <div class="prod-card"
             data-name="<?= strtolower($p['name']) ?>"
             data-brand="<?= htmlspecialchars($p['brand']) ?>"
             data-category="<?= htmlspecialchars($p['category']) ?>"
             data-status="<?= $p['status'] == 'Active' && $p['stock'] == 0 ? 'Out of Stock' : $p['status'] ?>">

            <div class="prod-card-img">

                <!-- ✅ FIXED IMAGE PATH -->
                <img src="img/<?= $imgFile ?>"
                     alt="<?= htmlspecialchars($p['name']) ?>">

                <div class="prod-card-badges">
                    <span class="badge <?= $status_cls ?>">
                        <?= $p['status'] === 'Active' && $p['stock'] == 0 ? 'Out of Stock' : $p['status'] ?>
                    </span>

                    <?php if ($p['featured']): ?>
                        <span class="badge b-purple">⭐ Featured</span>
                    <?php endif; ?>
                </div>

                <div class="prod-card-actions">

                    <a href="products_edit.php?id=<?= $p['id'] ?>"
                       class="pca-btn"
                       title="Edit">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                        </svg>
                    </a>

                    <a href="product_view.php?id=<?= $p['id'] ?>"
                       class="pca-btn"
                       title="View detail">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </a>

                    <button class="pca-btn danger pca-delete"
                            title="Delete"
                            data-id="<?= $p['id'] ?>"
                            data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                            <path d="M10 11v6M14 11v6" />
                        </svg>
                    </button>

                </div>
            </div>

            <div class="prod-card-body">
                <div class="pcb-category"><?= htmlspecialchars($p['category']) ?></div>
                <div class="pcb-name"><?= htmlspecialchars($p['name']) ?></div>

                <div class="pcb-desc">
                    <?= htmlspecialchars(substr($p['description'], 0, 80)) ?>...
                </div>

                <div class="pcb-sku"><?= htmlspecialchars($p['sku']) ?></div>

                <div class="pcb-row">
                    <span class="pcb-price">$<?= number_format($p['price'], 2) ?></span>
                    <span class="pcb-stock" style="color:<?= $stock_color ?>">
                        <?= $p['stock'] == 0 ? 'No stock' : $p['stock'] . ' units' ?>
                    </span>
                </div>
            </div>

        </div>

    <?php endforeach; ?>

</div>
          <!-- ── TABLE / LIST VIEW ── -->
<div class="products-table-wrap au" id="listView" style="display:none">

    <table>
        <thead>
            <tr>
                <th style="width:36px">
                    <input type="checkbox" class="cb" id="selectAll" onchange="toggleSelectAll(this)">
                </th>
                <th class="sorted" onclick="sortTable('name')">
                    Product <span class="sort-arrow">↕</span>
                </th>
                <th onclick="sortTable('sku')">
                    SKU <span class="sort-arrow">↕</span>
                </th>
                <th onclick="sortTable('category')">
                    Category <span class="sort-arrow">↕</span>
                </th>
                <th onclick="sortTable('price')">
                    Price <span class="sort-arrow">↕</span>
                </th>
                <th onclick="sortTable('stock')">
                    Stock <span class="sort-arrow">↕</span>
                </th>
                <th onclick="sortTable('sold')">
                    Sold <span class="sort-arrow">↕</span>
                </th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody id="productTableBody">

            <?php foreach ($products as $p):

                $status_cls = match ($p['status']) {
                    'Active'       => 'b-green',
                    'Draft'        => 'b-grey',
                    'Out of Stock' => 'b-red',
                    default        => 'b-grey'
                };

                $disp_status = ($p['status'] === 'Active' && $p['stock'] == 0)
                    ? 'Out of Stock'
                    : $p['status'];

                $disp_cls = ($p['status'] === 'Active' && $p['stock'] == 0)
                    ? 'b-red'
                    : $status_cls;

                $stock_color = $p['stock'] == 0
                    ? 'var(--red)'
                    : ($p['stock'] < 20 ? 'var(--amber)' : 'var(--green)');

                $bar_pct = min(100, round($p['stock'] / 100 * 100));

                // ✅ IMAGE SAFE FALLBACK
                $imgFile = !empty($p['img']) ? $p['img'] : 'default-product.webp';
            ?>

                <tr data-name="<?= strtolower($p['name']) ?>"
                    data-brand="<?= htmlspecialchars($p['brand']) ?>"
                    data-category="<?= htmlspecialchars($p['category']) ?>"
                    data-status="<?= $disp_status ?>">

                    <td>
                        <input type="checkbox" class="cb row-cb" onchange="updateBulk()">
                    </td>

                    <td>
                        <div class="td-prod">

                            
                            <img class="td-thumb"
                                 src="img/<?= $imgFile ?>"
                                 alt="<?= htmlspecialchars($p['name']) ?>">

                            <div>
                                <div class="td-pname">
                                    <?= htmlspecialchars($p['name']) ?>

                                    <?php if ($p['featured']): ?>
                                        <span class="featured-star">⭐</span>
                                    <?php endif; ?>
                                </div>

                                <div class="td-psku">
                                    <?= htmlspecialchars($p['brand']) ?>
                                </div>
                            </div>

                        </div>
                    </td>

                    <td>
                        <span class="td-mono" style="font-size:11px;color:var(--dim)">
                            <?= htmlspecialchars($p['sku']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="badge b-grey">
                            <?= htmlspecialchars($p['category']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="td-mono" style="font-weight:700;color:var(--txt)">
                            $<?= number_format($p['price'], 2) ?>
                        </span>
                    </td>

                    <td>
                        <div class="stock-bar-wrap">
                            <div class="stock-mini-bar">
                                <div class="smb-fill"
                                     style="width:<?= $bar_pct ?>%;background:<?= $stock_color ?>"></div>
                            </div>

                            <span class="stock-num" style="color:<?= $stock_color ?>">
                                <?= (int)$p['stock'] ?>
                            </span>
                        </div>
                    </td>

                    <td>
                        <span class="td-mono" style="color:var(--ac);font-weight:600">
                            <?= number_format($p['sold']) ?>
                        </span>
                    </td>

                    <td>
                        <span class="badge <?= $disp_cls ?>">
                            <?= $disp_status ?>
                        </span>
                    </td>

                    <td>
                        <div style="display:flex;gap:5px">

                            <a href="products_edit.php?id=<?= $p['id'] ?>"
                               class="btn btn-icon btn-sm"
                               title="Edit">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                </svg>
                            </a>

                            <a href="product_view.php?id=<?= $p['id'] ?>"
                               class="btn btn-icon btn-sm"
                               title="View detail">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </a>

                            <button class="btn btn-icon btn-sm"
                                    title="Duplicate"
                                    onclick="showToast('Product duplicated','📋')">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" />
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                </svg>
                            </button>

                            <button class="btn btn-icon btn-sm pca-delete"
                                    title="Delete"
                                    style="color:rgba(239,68,68,.7)"
                                    data-id="<?= $p['id'] ?>"
                                    data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                </svg>
                            </button>

                        </div>
                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>
    </table>

</div>
                <!-- Empty state (shown by JS when no results) -->
                <div class="empty-state" id="emptyState" style="display:none">
                    <div class="empty-icon">📦</div>
                    <div class="empty-title">No products found</div>
                    <div class="empty-desc">Try adjusting your search or filters.</div>
                </div>
            </div>

        </main>
    </div>

    <?php require_once "footer.php"; ?>

    <style>
        .prod-card-badges { pointer-events: none; }
    </style>

    <!-- Toast -->
    <div class="toast" id="toast"><span id="ti" style="font-size:17px">✓</span><span id="tm"></span></div>

    <!-- Delete Confirm Modal -->
    <div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
        <div style="background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:28px;max-width:380px;width:90%;box-shadow:var(--sh2)">
            <div style="font-size:28px;margin-bottom:12px;text-align:center">🗑️</div>
            <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:8px">Delete Product?</div>
            <div style="font-size:13px;color:var(--muted);text-align:center;margin-bottom:24px" id="deleteModalDesc">This action cannot be undone.</div>
            <div style="display:flex;gap:10px">
                <button class="btn btn-secondary" style="flex:1" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn" style="flex:1;background:var(--red);color:#fff" onclick="doDelete()">Delete</button>
            </div>
        </div>
    </div>

    <script>
        /* ── SIDEBAR / DROPDOWNS ── */
        function toggleSb() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sbOvl').classList.toggle('show');
        }

        function closeSb() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sbOvl').classList.remove('show');
        }

        function toggleDrop(id) {
            document.querySelectorAll('.nav-dropdown.open').forEach(d => {
                if (d.id !== id) d.classList.remove('open');
            });
            document.getElementById(id).classList.toggle('open');
        }
        document.addEventListener('click', e => {
            if (!e.target.closest('.nav-dropdown'))
                document.querySelectorAll('.nav-dropdown.open').forEach(d => d.classList.remove('open'));
        });

        /* ── TOAST ── */
        function showToast(msg, icon = '✓') {
            const t = document.getElementById('toast');
            document.getElementById('tm').textContent = msg;
            document.getElementById('ti').textContent = icon;
            t.classList.add('show');
            clearTimeout(t._t);
            t._t = setTimeout(() => t.classList.remove('show'), 3200);
        }

        /* ── VIEW TOGGLE ── */
        let currentView = localStorage.getItem('voltedge_prod_view') || 'grid';

        function setView(v) {
            currentView = v;
            localStorage.setItem('voltedge_prod_view', v);
            document.getElementById('gridView').style.display = v === 'grid' ? 'grid' : 'none';
            document.getElementById('listView').style.display = v === 'list' ? 'block' : 'none';
            document.getElementById('gridBtn').classList.toggle('active', v === 'grid');
            document.getElementById('listBtn').classList.toggle('active', v === 'list');
        }
        setView(currentView);

        /* ── FILTER / SEARCH ── */
        let debounceTimer;
        document.getElementById('searchInput').addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(filterProducts, 200);
        });

        function filterProducts() {
            const q = document.getElementById('searchInput').value.toLowerCase().trim();
            const cat = document.getElementById('catFilter').value;
            const brand = document.getElementById('brandFilter').value;
            const status = document.getElementById('statusFilter').value;

            const gridCards = document.querySelectorAll('#gridView .prod-card');
            const tableRows = document.querySelectorAll('#productTableBody tr[data-name]');
            let visible = 0;

            function matches(el) {
                const name = el.dataset.name || '';
                const elBrand = el.dataset.brand || '';
                const elCat = el.dataset.category || '';
                const elStatus = el.dataset.status || '';
                if (q && !name.includes(q) && !elBrand.toLowerCase().includes(q)) return false;
                if (cat !== 'All' && elCat !== cat) return false;
                if (brand !== 'All' && elBrand !== brand) return false;
                if (status !== 'All Status' && elStatus !== status) return false;
                return true;
            }

            gridCards.forEach(el => {
                const show = matches(el);
                el.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            tableRows.forEach(el => {
                el.style.display = matches(el) ? '' : 'none';
            });

            document.getElementById('resultCount').textContent = visible + ' product' + (visible !== 1 ? 's' : '');
            const empty = document.getElementById('emptyState');
            if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
        }

        /* ── BULK SELECTION ── */
        function toggleSelectAll(cb) {
            document.querySelectorAll('.row-cb').forEach(c => c.checked = cb.checked);
            updateBulk();
        }

        function updateBulk() {
            const checked = document.querySelectorAll('.row-cb:checked').length;
            const bar = document.getElementById('bulkBar');
            bar.classList.toggle('show', checked > 0);
            document.getElementById('bulkCount').textContent = checked + ' selected';
            const sa = document.getElementById('selectAll');
            const all = document.querySelectorAll('.row-cb').length;
            sa.indeterminate = checked > 0 && checked < all;
            sa.checked = checked === all;
        }

        function clearSelection() {
            document.querySelectorAll('.row-cb, #selectAll').forEach(c => c.checked = false);
            document.getElementById('bulkBar').classList.remove('show');
        }

        /* ── SORT (client-side demo) ── */
        function sortTable(col) {
            showToast('Sorting by ' + col + '…', '↕');
        }

        /* ── DELETE MODAL ── */
        let pendingDeleteId = null;

        function confirmDelete(id, name) {
            pendingDeleteId = id;
            document.getElementById('deleteModalDesc').textContent = `"${name}" will be permanently removed.`;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            pendingDeleteId = null;
        }

        // Use mousedown + event delegation so hover-hide doesn't swallow the click
        document.addEventListener('mousedown', function(e) {
            const btn = e.target.closest('.pca-delete');
            if (!btn) return;
            e.preventDefault();
            const id   = btn.dataset.id;
            const name = btn.dataset.name;
            confirmDelete(id, name);
        });

        function doDelete() {
            if (!pendingDeleteId) return;
            closeDeleteModal();

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'products_action.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = pendingDeleteId;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
        document.getElementById('deleteModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeDeleteModal();
        });

        /* ── ESC ── */
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeSb();
                closeDeleteModal();
            }
        });

        /* ── SEARCH BAR ── */
        document.getElementById('navSearchInput')?.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.target.value.trim())
                showToast(`Searching for "${e.target.value}"…`, '🔍');
        });
    </script>
</body>
</html>