<?php

require_once "auth_check.php";


$page_title = "Add Product — LectroHub Admin";

require_once "../lectrohub_db/connection.php";

/* LOAD CATEGORIES & BRANDS FROM DB */
$categories = [];
$brands      = [];
$errors      = [];
$new_id = null;
$success     = false;

try {
    $categories = $conn->query("
        SELECT id, category_name
        FROM categories
        WHERE category_name != 'All'
        ORDER BY category_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $brands = $conn->query("
    SELECT id, brand_name
    FROM brands
    ORDER BY brand_name
")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

/* HANDLE FORM SUBMISSION */
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old = $_POST;

    // ── Sanitise & validate ──
    $product_name = trim($_POST['product_name'] ?? '');
    $brand_id     = (int)($_POST['brand_id'] ?? 0);
    $sku          = trim($_POST['sku'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $category_id  = (int)($_POST['category_id'] ?? 0);
    $price        = $_POST['price'] ?? '';
    $stock        = $_POST['stock'] ?? '';
    $status       = $_POST['status'] ?? 'draft';
    $featured     = isset($_POST['featured']) ? 1 : 0;

    if ($product_name === '') $errors[] = 'Product name is required.';
    if ($brand_id <= 0) $errors[] = 'Please select a brand.';
    if ($sku === '') $errors[] = 'SKU is required.';
    if ($category_id <= 0) $errors[] = 'Please select a category.';
    if (!is_numeric($price) || $price < 0) $errors[] = 'Enter a valid price.';
    if (!is_numeric($stock) || $stock < 0) $errors[] = 'Enter a valid stock quantity.';
    if (!in_array($status, ['active', 'draft', 'inactive'])) {
        $status = 'draft';
    }

    // Look up brand name for the legacy text column (still NOT NULL in the DB)
    $brand_name = '';
    if ($brand_id > 0) {
        $brand_lookup = $conn->prepare("SELECT brand_name FROM brands WHERE id = ?");
        $brand_lookup->execute([$brand_id]);
        $brand_name = (string)$brand_lookup->fetchColumn();
        if ($brand_name === '') $errors[] = 'Selected brand no longer exists.';
    }

    // ── Check SKU uniqueness ──
    if (empty($errors)) {
        $sku_check = $conn->prepare(
            "SELECT COUNT(*) FROM products WHERE sku = ?"
        );
        $sku_check->execute([$sku]);

        if ($sku_check->fetchColumn() > 0) {
            $errors[] = 'SKU already exists. Please use another SKU.';
        }
    }

    // ── Image Upload ──
   $image_name = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {

    $allowed = ['jpg','jpeg','png','webp'];

    $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

    if (in_array($extension, $allowed)) {

        $upload_dir = __DIR__ . "/img/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $image_name = uniqid('product_', true) . "." . $extension;

        $target_path = $upload_dir . $image_name;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $errors[] = "Failed to move uploaded file.";
        }

    } else {
        $errors[] = "Invalid image format.";
    }
}

    // ── Insert Product ──
    if (empty($errors)) {

        try {

            $stmt = $conn->prepare("
                INSERT INTO products
                (
                    category_id,
                    product_name,
                    brand_legacy,
                    brand_id,
                    sku,
                    description,
                    price,
                    stock,
                    sold,
                    image,
                    featured,
                    status
                )
                VALUES
                (
                    :category_id,
                    :product_name,
                    :brand_legacy,
                    :brand_id,
                    :sku,
                    :description,
                    :price,
                    :stock,
                    0,
                    :image,
                    :featured,
                    :status
                )
            ");

            $stmt->execute([
                ':category_id'  => $category_id,
                ':product_name' => $product_name,
                ':brand_legacy' => $brand_name,
                ':brand_id'     => $brand_id,
                ':sku'          => $sku,
                ':description'  => $description,
                ':price'        => number_format((float)$price, 2, '.', ''),
                ':stock'        => (int)$stock,
                ':image'        => $image_name,
                ':featured'     => $featured,
                ':status'       => $status
            ]);

            $new_id = $conn->lastInsertId();

            $success = true;
            $old = [];
        } catch (PDOException $e) {
            $errors[] = 'Failed to save product: ' . $e->getMessage();
        }
    }
}

/* ══════════════════════════════════════════
   HELPER
══════════════════════════════════════════ */
function old(string $key, string $default = ''): string
{
    global $old;
    return htmlspecialchars($old[$key] ?? $default);
}
?>

<?php require_once "head.php"; ?>

<div class="sb-mobile-ovl" id="sbOvl" onclick="closeSb()"></div>

<div class="shell">

    <?php require_once "sidebar.php"; ?>
    <?php require_once "navbar.php"; ?>

    <main class="main">

        <!-- ── Page Header ── -->
        <div class="page-hd au">
            <div>
                <div class="ph-eyebrow">Catalogue</div>
                <h1 class="ph-title">Add Product</h1>
                <p class="ph-sub">Fill in the details below to add a new product to your catalogue.</p>
            </div>
            <div class="ph-actions">
                <a href="products.php" class="btn btn-secondary btn-sm">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                    Back to Products
                </a>
            </div>
        </div>

        <!-- ── Success Banner ── -->
        <?php if ($success): ?>
            <div class="adm-card au" style="border-color:rgba(74,222,128,.3);background:rgba(74,222,128,.06);margin-bottom:20px;padding:16px 20px">
                <div style="display:flex;align-items:center;gap:12px">
                    <span style="font-size:22px">✅</span>
                    <div>
                        <div style="font-family:var(--fd);font-size:14px;font-weight:700;color:var(--green)">Product created successfully!</div>
                        <div style="font-size:12px;color:var(--muted);margin-top:2px">
                            <a href="products.php" style="color:var(--ac);text-decoration:none">View all products</a>
                            &nbsp;·&nbsp;
                            <a href="products_edit.php?id=<?= $new_id ?>" style="color:var(--ac);text-decoration:none">Edit this product</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Error Banner ── -->
        <?php if (!empty($errors)): ?>
            <div class="adm-card au" style="border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.06);margin-bottom:20px;padding:16px 20px">
                <div style="display:flex;gap:12px">
                    <span style="font-size:22px;line-height:1.2">⚠️</span>
                    <div>
                        <div style="font-family:var(--fd);font-size:14px;font-weight:700;color:var(--red);margin-bottom:6px">Please fix the following errors:</div>
                        <ul style="margin:0;padding-left:16px;display:flex;flex-direction:column;gap:3px">
                            <?php foreach ($errors as $err): ?>
                                <li style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── Form Layout ── -->
        <form method="POST" action="products_create.php" id="createForm" autocomplete="off" enctype="multipart/form-data">

            <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

                <!-- LEFT COLUMN -->
                <div style="display:flex;flex-direction:column;gap:20px">

                    <!-- Basic Info -->
                    <div class="adm-card au">
                        <div class="card-hd">
                            <div>
                                <div class="card-title">Basic Information</div>
                                <div class="card-sub">Name, brand, SKU, and description</div>
                            </div>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:18px">

                            <!-- Product Name -->
                            <div class="form-group">
                                <label class="form-label">Product Name <span class="req">*</span></label>
                                <input
                                    type="text"
                                    name="product_name"
                                    class="form-input"
                                    placeholder="e.g. iPhone 16 Pro Max"
                                    value="<?= old('product_name') ?>"
                                    required>
                            </div>

                            <!-- Brand + SKU -->
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

                                <div class="form-group">
                                    <label class="form-label">Brand <span class="req">*</span></label>
                                    <select name="brand_id" class="form-input" required>
                                        <option value="" disabled <?= empty($old['brand_id']) ? 'selected' : '' ?>>Select brand…</option>
                                        <?php foreach ($brands as $b): ?>
                                            <option value="<?= (int)$b['id'] ?>" data-name="<?= htmlspecialchars($b['brand_name']) ?>" <?= (old('brand_id') === (string)$b['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($b['brand_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">SKU <span class="req">*</span></label>
                                    <input
                                        type="text"
                                        name="sku"
                                        class="form-input"
                                        placeholder="e.g. APL-IP16PM-256"
                                        value="<?= old('sku') ?>"
                                        required>
                                    <div class="form-hint">Must be unique across all products.</div>
                                </div>

                            </div>

                            <!-- Description -->
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea
                                    name="description"
                                    class="form-input"
                                    rows="4"
                                    placeholder="Short product description shown on listing pages…"><?= old('description') ?></textarea>
                            </div>

                        </div>
                    </div>

                    <!-- Pricing & Inventory -->
                    <div class="adm-card au">
                        <div class="card-hd">
                            <div>
                                <div class="card-title">Pricing & Inventory</div>
                                <div class="card-sub">Set the retail price and initial stock level</div>
                            </div>
                        </div>
                        <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

                            <div class="form-group">
                                <label class="form-label">Price (USD) <span class="req">*</span></label>
                                <div class="input-prefix-wrap">
                                    <span class="input-prefix">$</span>
                                    <input
                                        type="number"
                                        name="price"
                                        class="form-input has-prefix"
                                        placeholder="0.00"
                                        step="0.01"
                                        min="0"
                                        value="<?= old('price') ?>"
                                        required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Stock Quantity <span class="req">*</span></label>
                                <input
                                    type="number"
                                    name="stock"
                                    class="form-input"
                                    placeholder="0"
                                    min="0"
                                    value="<?= old('stock') ?>"
                                    required>
                                <div class="form-hint">Units available for sale.</div>
                            </div>

                        </div>
                    </div>

                    <!-- Product Image -->
                    <div class="adm-card au">
                        <div class="card-hd">
                            <div>
                                <div class="card-title">Product Image</div>
                                <div class="card-sub">JPG, PNG or WEBP · recommended 800 × 800 px</div>
                            </div>
                        </div>
                        <div class="card-body">

                            <input type="file" id="imageFileInput" name="image" accept="image/jpeg,image/png,image/webp" style="display:none">

                            <div id="dropZone" onclick="document.getElementById('imageFileInput').click()">

                                <!-- idle state -->
                                <div id="dropIdle">
                                    <div class="dz-icon">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                            <rect x="3" y="3" width="18" height="18" rx="3" />
                                            <circle cx="8.5" cy="8.5" r="1.5" />
                                            <polyline points="21 15 16 10 5 21" />
                                        </svg>
                                    </div>
                                    <div class="dz-primary">Click to upload <span class="dz-or">or drag &amp; drop</span></div>
                                    <div class="dz-hint">JPG, PNG or WEBP · max 5 MB</div>
                                </div>
                                <!-- preview state (hidden until file chosen) -->
                                <div id="dropPreview" style="display:none;flex-direction:column;align-items:center;gap:10px">
                                    <img id="imgPreview" src="img" alt="Preview" style="max-height:120px;max-width:100%;border-radius:6px;object-fit:contain">
                                    <div id="dropFilename" style="font-size:11px;color:var(--muted);max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
                                    <button type="button" id="dropRemove" onclick="event.stopPropagation();removeImage()" style="font-size:11px;color:var(--red);background:none;border:none;cursor:pointer;padding:0">✕ Remove</button>
                                </div>
                            </div>

                        </div>
                    </div>

                </div><!-- /left column -->

                <!-- RIGHT COLUMN -->
                <div style="display:flex;flex-direction:column;gap:20px">

                    <!-- Publish Settings -->
                    <div class="adm-card au">
                        <div class="card-hd">
                            <div class="card-title">Publish</div>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">

                            <div class="form-group" style="margin:0">
                                <label class="form-label">Status <span class="req">*</span></label>
                                <select name="status" class="form-input">
                                    <option value="active" <?= old('status', 'draft') === 'active'   ? 'selected' : '' ?>>Active — visible in store</option>
                                    <option value="draft" <?= old('status', 'draft') === 'draft'    ? 'selected' : '' ?>>Draft — hidden from store</option>
                                    <option value="inactive" <?= old('status', 'draft') === 'inactive' ? 'selected' : '' ?>>Inactive — unlisted</option>
                                </select>
                            </div>

                            <!-- Featured toggle -->
                            <label class="toggle-row">
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:var(--txt)">Featured Product</div>
                                    <div style="font-size:11px;color:var(--muted);margin-top:2px">Highlighted on homepage &amp; category pages</div>
                                </div>
                                <div class="toggle-wrap">
                                    <input type="checkbox" name="featured" id="featuredToggle" class="toggle-input" <?= !empty($old['featured']) ? 'checked' : '' ?>>
                                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                </div>
                            </label>

                            <hr style="border:none;border-top:1px solid var(--bd);margin:0">

                            <div style="display:flex;flex-direction:column;gap:10px">
                                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19" />
                                        <line x1="5" y1="12" x2="19" y2="12" />
                                    </svg>
                                    Create Product
                                </button>
                                <a href="products.php" class="btn btn-secondary" style="width:100%;justify-content:center;text-align:center">
                                    Cancel
                                </a>
                            </div>

                        </div>
                    </div>

                    <!-- Category -->
                    <div class="adm-card au">
                        <div class="card-hd">
                            <div class="card-title">Category</div>
                        </div>
                        <div class="card-body">
                            <div class="form-group" style="margin:0">
                                <label class="form-label">Category <span class="req">*</span></label>
                                <select name="category_id" class="form-input" required>
                                    <option value="" disabled <?= empty($old['category_id']) ? 'selected' : '' ?>>Select category…</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option
                                            value="<?= $cat['id'] ?>"
                                            <?= (old('category_id') == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Reference -->
                    <div class="adm-card au">
                        <div class="card-hd">
                            <div class="card-title">SKU Format Guide</div>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                            <?php
                            $sku_examples = [
                                'Apple'     => 'APL-{MODEL}-{STORAGE}',
                                'Samsung'   => 'SAM-{MODEL}-{STORAGE}',
                                'Sony'      => 'SNY-{MODEL}',
                                'DJI'       => 'DJI-{MODEL}',
                                'Logitech'  => 'LOG-{MODEL}',
                                'Razer'     => 'RAZ-{MODEL}',
                                'Anker'     => 'ANK-{MODEL}',
                                'JBL'       => 'JBL-{MODEL}',
                                'Google'    => 'GOO-{MODEL}-{STORAGE}',
                                'Microsoft' => 'MS-{MODEL}-{STORAGE}',
                                'Nintendo'  => 'NIN-{MODEL}',
                                'Dell'      => 'DEL-{MODEL}',
                            ];
                            foreach ($sku_examples as $b => $fmt): ?>
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                                    <span style="font-size:11px;color:var(--muted)"><?= $b ?></span>
                                    <span class="td-mono" style="font-size:10px;color:var(--dim);background:var(--card3);padding:2px 7px;border-radius:4px"><?= $fmt ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div><!-- /right column -->

            </div><!-- /grid -->

        </form>

    </main>

</div><!-- /shell -->

<?php require_once "footer.php"; ?>

<!-- Toast -->
<div class="toast" id="toast"><span id="ti" style="font-size:17px">✓</span><span id="tm"></span></div>

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

    /* ── Dropdowns ── */
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

    /* ── Upload drop zone ── */
    const imgFileInput = document.getElementById('imageFileInput');
    const dropZone = document.getElementById('dropZone');
    const dropIdle = document.getElementById('dropIdle');
    const dropPreview = document.getElementById('dropPreview');
    const imgPreview = document.getElementById('imgPreview');
    const dropFilename = document.getElementById('dropFilename');

    function applyFile(file) {
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
            imgPreview.src = e.target.result;
            dropFilename.textContent = file.name + ' · ' + (file.size / 1024).toFixed(0) + ' KB';
            dropIdle.style.display = 'none';
            dropPreview.style.display = 'flex';
        };
        reader.readAsDataURL(file);
    }

    function removeImage() {
        imgFileInput.value = '';
        imgPreview.src = '';
        dropIdle.style.display = 'flex';
        dropPreview.style.display = 'none';
    }

    imgFileInput.addEventListener('change', () => applyFile(imgFileInput.files[0]));

    dropZone.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) {
            // inject into the real input so the form submits it
            const dt = new DataTransfer();
            dt.items.add(file);
            imgFileInput.files = dt.files;
            applyFile(file);
        }
    });

    /* ── SKU auto-suggest from brand ── */
    const brandSel = document.querySelector('select[name="brand"]');
    const skuInput = document.querySelector('input[name="sku"]');
    const skuPrefixes = {
        'Apple': 'APL',
        'Samsung': 'SAM',
        'Sony': 'SNY',
        'DJI': 'DJI',
        'Logitech': 'LOG',
        'Razer': 'RAZ',
        'Anker': 'ANK',
        'JBL': 'JBL',
        'Google': 'GOO',
        'Microsoft': 'MS',
        'Nintendo': 'NIN',
        'Dell': 'DEL',
        'Canon': 'CAN',
        'LG': 'LG',
        'Bose': 'BSE',
        'Garmin': 'GRM'
    };
    brandSel.addEventListener('change', () => {
        if (skuInput.value) return; // don't overwrite if user already typed
        const selectedName = brandSel.selectedOptions[0]?.dataset.name;
        const prefix = skuPrefixes[selectedName];
        if (prefix) skuInput.placeholder = prefix + '-{MODEL}-{VARIANT}';
    });

    /* ── Form submit feedback ── */
    document.getElementById('createForm').addEventListener('submit', () => {
        showToast('Saving product…', '💾');
    });

    /* ── ESC ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeSb();
    });
</script>

</body>

</html>