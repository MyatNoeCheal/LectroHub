<?php

require_once "auth_check.php";

$page_title = "Categories — LectroHub Admin";

require_once "../lectrohub_db/connection.php";

$errors  = [];
$success = '';

/* ══════════════════════════════════════════
   AVATAR COLOR PALETTE
   Purely a display helper — categories don't
   store a color/icon in the DB, so we derive a
   stable one from the row id.
══════════════════════════════════════════ */
$avatar_palette = ['#00e5ff', '#a78bfa', '#4ade80', '#f59e0b', '#ff6b35', '#f472b6', '#38bdf8', '#fb923c'];

function avatar_color(int $id, array $palette): string {
    return $palette[$id % count($palette)];
}

/* ══════════════════════════════════════════
   HANDLE POST ACTIONS
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action      = $_POST['_action'] ?? '';
    $category_id = (int)($_POST['category_id'] ?? 0);

    /* ── ADD / EDIT ── */
    if ($action === 'add' || $action === 'edit') {

        $category_name = trim($_POST['category_name'] ?? '');
        $description    = trim($_POST['description'] ?? '');

        if ($category_name === '') $errors[] = 'Category name is required.';
        if (mb_strlen($category_name) > 100) $errors[] = 'Category name must be 100 characters or fewer.';

        // "All" is a reserved system row used as the "All Categories" filter
        // placeholder across the storefront and admin filters — protect it.
        if (strcasecmp($category_name, 'All') === 0) {
            $errors[] = '"All" is a reserved system category name and can\'t be used.';
        }

        // Uniqueness check (case-insensitive)
        if (empty($errors)) {
            if ($action === 'add') {
                $chk = $conn->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(category_name) = LOWER(?)");
                $chk->execute([$category_name]);
            } else {
                $chk = $conn->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(category_name) = LOWER(?) AND id != ?");
                $chk->execute([$category_name, $category_id]);
            }
            if ($chk->fetchColumn() > 0) {
                $errors[] = 'A category named "' . htmlspecialchars($category_name) . '" already exists.';
            }
        }

        // Don't let anyone rename the system "All" row away from "All"
        if ($action === 'edit' && empty($errors)) {
            $cur = $conn->prepare("SELECT category_name FROM categories WHERE id = ?");
            $cur->execute([$category_id]);
            $cur_name = $cur->fetchColumn();
            if ($cur_name !== false && strcasecmp($cur_name, 'All') === 0) {
                $errors[] = 'The "All" category is a system placeholder and can\'t be edited.';
            }
        }

        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO categories (category_name, description)
                        VALUES (:category_name, :description)
                    ");
                    $stmt->execute([
                        ':category_name' => $category_name,
                        ':description'   => $description !== '' ? $description : null,
                    ]);
                    $success = 'Category "' . htmlspecialchars($category_name) . '" created successfully.';
                } else {
                    $stmt = $conn->prepare("
                        UPDATE categories SET
                            category_name = :category_name,
                            description   = :description
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':category_name' => $category_name,
                        ':description'   => $description !== '' ? $description : null,
                        ':id'            => $category_id,
                    ]);
                    $success = 'Category "' . htmlspecialchars($category_name) . '" updated successfully.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Failed to save category: ' . $e->getMessage();
            }
        }
    }

    /* ── DELETE ── */
    if ($action === 'delete' && $category_id > 0) {
        $cat_chk = $conn->prepare("SELECT category_name FROM categories WHERE id = ?");
        $cat_chk->execute([$category_id]);
        $cat_name = $cat_chk->fetchColumn();

        if ($cat_name === false) {
            $errors[] = 'Category not found.';
        } elseif (strcasecmp($cat_name, 'All') === 0) {
            $errors[] = 'The "All" category is a system placeholder and can\'t be deleted.';
        } else {
            $prod_chk = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $prod_chk->execute([$category_id]);
            $prod_count = (int)$prod_chk->fetchColumn();

            if ($prod_count > 0) {
                $errors[] = 'Can\'t delete "' . htmlspecialchars($cat_name) . '" — ' . $prod_count . ' product' . ($prod_count !== 1 ? 's' : '') . ' still use it. Reassign or remove those products first.';
            } else {
                try {
                    $conn->prepare("DELETE FROM categories WHERE id = ?")->execute([$category_id]);
                    $success = 'Category "' . htmlspecialchars($cat_name) . '" has been deleted.';
                } catch (PDOException $e) {
                    $errors[] = 'Failed to delete category: ' . $e->getMessage();
                }
            }
        }
    }
}

/* ══════════════════════════════════════════
   LOAD CATEGORIES + PRODUCT COUNTS
══════════════════════════════════════════ */
$categories = [];

try {
    $categories = $conn->query("
        SELECT
            c.id,
            c.category_name,
            c.description,
            c.created_at,
            COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id, c.category_name, c.description, c.created_at
        ORDER BY (c.category_name = 'All') DESC, c.category_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

/* ══════════════════════════════════════════
   STATISTICS
══════════════════════════════════════════ */
$real_categories  = array_filter($categories, fn($c) => strcasecmp($c['category_name'], 'All') !== 0);
$total_categories = count($real_categories);
$total_products   = array_sum(array_column($real_categories, 'product_count'));
$empty_count      = count(array_filter($real_categories, fn($c) => (int)$c['product_count'] === 0));
$avg_per_cat      = $total_categories ? round($total_products / $total_categories, 1) : 0;

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
                <h1 class="ph-title">Categories</h1>
                <p class="ph-sub"><?= $total_categories ?> categories &nbsp;·&nbsp; <?= number_format($total_products) ?> products categorized</p>
            </div>
            <div class="ph-actions">
                <button class="btn btn-primary btn-sm" onclick="openCategoryModal()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Add Category
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
                ['label' => 'Total Categories',  'value' => $total_categories,        'color' => 'var(--ac)',     'icon' => '🗂️'],
                ['label' => 'Products Tagged',   'value' => number_format($total_products), 'color' => 'var(--green)',  'icon' => '📦'],
                ['label' => 'Avg. per Category', 'value' => $avg_per_cat,              'color' => 'var(--purple)', 'icon' => '📊'],
                ['label' => 'Empty Categories',  'value' => $empty_count,              'color' => 'var(--amber)',  'icon' => '⚠️'],
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
            <input type="text" id="searchInput" placeholder="Search categories…" oninput="filterCategories()">
            <div class="filter-spacer"></div>
            <span style="font-size:11px;color:var(--dim)" id="resultCount"><?= count($categories) ?> categories</span>
        </div>

        <!-- Categories Grid -->
        <div class="au" id="categoryGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">
            <?php foreach ($categories as $c):
                $is_system = strcasecmp($c['category_name'], 'All') === 0;
                $color     = avatar_color((int)$c['id'], $avatar_palette);
                $initial   = mb_strtoupper(mb_substr($c['category_name'], 0, 1));
            ?>
                <div class="adm-card" data-name="<?= strtolower($c['category_name']) ?>" style="padding:18px;display:flex;flex-direction:column;gap:12px">

                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <div style="width:44px;height:44px;border-radius:12px;background:<?= $color ?>1a;border:1px solid <?= $color ?>40;display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:17px;font-weight:800;color:<?= $color ?>"><?= htmlspecialchars($initial) ?></div>
                        <?php if ($is_system): ?>
                            <span class="badge b-grey" title="Used system-wide as the &quot;All Categories&quot; filter placeholder">System</span>
                        <?php else: ?>
                            <span class="badge <?= $c['product_count'] > 0 ? 'b-cyan' : 'b-grey' ?>"><?= (int)$c['product_count'] ?> product<?= $c['product_count'] != 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div style="font-family:var(--fd);font-size:15px;font-weight:700;color:var(--txt)"><?= htmlspecialchars($c['category_name']) ?></div>
                        <?php if (!empty($c['description'])): ?>
                            <div style="font-size:11px;color:var(--muted);margin-top:4px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= htmlspecialchars($c['description']) ?></div>
                        <?php else: ?>
                            <div style="font-size:11px;color:var(--dim);margin-top:4px">No description</div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$is_system): ?>
                        <div style="display:flex;gap:6px;margin-top:auto">
                            <button class="btn btn-secondary btn-sm" style="flex:1;justify-content:center"
                                onclick="openCategoryModal(
                                    <?= (int)$c['id'] ?>,
                                    '<?= addslashes(htmlspecialchars($c['category_name'], ENT_QUOTES)) ?>',
                                    '<?= addslashes(htmlspecialchars($c['description'] ?? '', ENT_QUOTES)) ?>'
                                )">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                </svg>
                                Edit
                            </button>
                            <button class="btn btn-icon btn-sm" title="Delete category" style="color:rgba(239,68,68,.7)"
                                onclick="openDeleteModal(<?= (int)$c['id'] ?>, '<?= addslashes(htmlspecialchars($c['category_name'], ENT_QUOTES)) ?>', <?= (int)$c['product_count'] ?>)">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                    <path d="M10 11v6M14 11v6" />
                                </svg>
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="font-size:11px;color:var(--dim);margin-top:auto">Used as the "All Categories" filter — can't be edited or deleted.</div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- Empty state -->
        <div id="emptyState" style="display:none;padding:56px 24px;text-align:center">
            <div style="font-size:40px;margin-bottom:12px">🗂️</div>
            <div style="font-family:var(--fd);font-size:14px;font-weight:700;color:var(--txt)">No categories found</div>
            <div style="font-size:12px;color:var(--muted);margin-top:4px">Try a different search.</div>
        </div>

    </main>
</div>

<?php require_once "footer.php"; ?>

<div class="toast" id="toast"><span id="ti" style="font-size:17px">✓</span><span id="tm"></span></div>

<!-- ── Add / Edit Category Modal ── -->
<div id="categoryModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:28px;max-width:420px;width:90%;box-shadow:var(--sh2);max-height:90vh;overflow-y:auto">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt)" id="cm-title">Add Category</div>
            <button type="button" onclick="closeCategoryModal()" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;line-height:1;padding:4px">✕</button>
        </div>

        <form method="POST" action="categories.php" id="categoryForm">
            <input type="hidden" name="_action" id="cm-action" value="add">
            <input type="hidden" name="category_id" id="cm-id" value="">

            <div style="display:flex;flex-direction:column;gap:16px">

                <div class="form-group" style="margin:0">
                    <label class="form-label">Category Name <span class="req">*</span></label>
                    <input type="text" name="category_name" id="cm-name" class="form-input" placeholder="e.g. Smartphones" maxlength="100" required>
                </div>

                <div class="form-group" style="margin:0">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="cm-description" class="form-input" rows="3" placeholder="Optional — shown on category listing pages…"></textarea>
                </div>

            </div>

            <div style="display:flex;gap:10px;margin-top:24px">
                <button type="button" class="btn btn-secondary" style="flex:1;justify-content:center" onclick="closeCategoryModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center" id="cm-submit">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirm Modal ── -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid rgba(239,68,68,.25);border-radius:var(--r);padding:28px;max-width:380px;width:90%;box-shadow:var(--sh2)">
        <div style="font-size:32px;margin-bottom:12px;text-align:center">🗑️</div>
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:6px">Delete Category?</div>
        <div style="font-size:13px;font-weight:600;color:var(--txt);text-align:center;margin-bottom:4px" id="deleteModalName"></div>
        <div style="font-size:12px;color:var(--dim);text-align:center;margin-bottom:24px" id="deleteModalNote">This action cannot be undone.</div>
        <div style="display:flex;gap:10px">
            <button class="btn btn-secondary" style="flex:1" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" action="categories.php" style="flex:1" id="deleteForm">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="category_id" id="deleteCategoryId">
                <button type="submit" class="btn" style="width:100%;background:var(--red);color:#fff;justify-content:center" id="deleteConfirmBtn">Delete</button>
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
        debounceTimer = setTimeout(filterCategories, 200);
    });

    function filterCategories() {
        const q      = document.getElementById('searchInput').value.toLowerCase().trim();
        const cards  = document.querySelectorAll('#categoryGrid .adm-card');
        let visible  = 0;

        cards.forEach(card => {
            const show = !q || card.dataset.name.includes(q);
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('resultCount').textContent = visible + ' categor' + (visible !== 1 ? 'ies' : 'y');
        document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
    }

    /* ── Add / Edit Modal ── */
    function openCategoryModal(id, name, description) {
        const isEdit = typeof id !== 'undefined';

        document.getElementById('cm-title').textContent = isEdit ? 'Edit Category' : 'Add Category';
        document.getElementById('cm-action').value        = isEdit ? 'edit' : 'add';
        document.getElementById('cm-id').value             = isEdit ? id : '';
        document.getElementById('cm-submit').textContent   = isEdit ? 'Save Changes' : 'Create Category';

        document.getElementById('cm-name').value        = isEdit ? name : '';
        document.getElementById('cm-description').value = isEdit ? description : '';

        document.getElementById('categoryModal').style.display = 'flex';
    }
    function closeCategoryModal() { document.getElementById('categoryModal').style.display = 'none'; }
    document.getElementById('categoryModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeCategoryModal(); });

    document.getElementById('categoryForm').addEventListener('submit', () => {
        showToast('Saving category…', '💾');
    });

    /* ── Delete Modal ── */
    function openDeleteModal(id, name, productCount) {
        document.getElementById('deleteCategoryId').value       = id;
        document.getElementById('deleteModalName').textContent  = name;

        const note = document.getElementById('deleteModalNote');
        const btn  = document.getElementById('deleteConfirmBtn');

        if (productCount > 0) {
            note.textContent = `${productCount} product${productCount !== 1 ? 's' : ''} still use this category — reassign or delete them first.`;
            note.style.color = 'var(--amber)';
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
        } else {
            note.textContent = 'This action cannot be undone.';
            note.style.color = '';
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.cursor = '';
        }

        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
    document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteModal(); });

    /* ── ESC ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeSb();
            closeCategoryModal();
            closeDeleteModal();
        }
    });
</script>

</body>
</html>