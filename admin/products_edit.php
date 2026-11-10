<?php

require_once "auth_check.php";


require_once "../lectrohub_db/connection.php";

/* ══════════════════════════════════════════
   GUARD — require a valid ?id=
══════════════════════════════════════════ */
$product_id = (int)($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header('Location: products.php');
    exit;
}

/* ══════════════════════════════════════════
   LOAD PRODUCT FROM DB
══════════════════════════════════════════ */
$product = null;
$errors  = [];
$success = false;

try {
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.product_name,
            p.brand_id,
            p.sku,
            p.description,
            p.category_id,
            p.price,
            p.stock,
            p.sold,
            p.image,
            p.featured,
            p.status
        FROM products p
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

// Product not found → redirect
if (!$product && empty($errors)) {
    header('Location: products.php');
    exit;
}

$page_title = 'Edit Product — ' . htmlspecialchars($product['product_name'] ?? '') . ' — LectroHub Admin';

/* ══════════════════════════════════════════
   LOAD CATEGORIES & BRANDS
══════════════════════════════════════════ */
$categories = [];
$brands      = [];

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
    $errors[] = 'Could not load categories/brands: ' . $e->getMessage();
}

/* ══════════════════════════════════════════
   HANDLE DELETE
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'delete') {
    try {
        $del = $conn->prepare("DELETE FROM products WHERE id = ?");
        $del->execute([$product_id]);
        header('Location: products.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $errors[] = 'Could not delete product: ' . $e->getMessage();
    }
}

/* ══════════════════════════════════════════
   HANDLE UPDATE
══════════════════════════════════════════ */
$old = []; // holds POST values on validation failure

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['_action'])) {

    $old = $_POST;

    // ── Sanitise & validate ──
    $product_name = trim($_POST['product_name'] ?? '');
    $brand_id     = (int)($_POST['brand_id']    ?? 0);
    $sku          = trim($_POST['sku']          ?? '');
    $description  = trim($_POST['description']  ?? '');
    $category_id  = (int)($_POST['category_id'] ?? 0);
    $price        = $_POST['price']  ?? '';
    $stock        = $_POST['stock']  ?? '';
    $status       = $_POST['status'] ?? 'draft';
    $featured     = isset($_POST['featured']) ? 1 : 0;


    if ($product_name === '')                   $errors[] = 'Product name is required.';
    if ($brand_id <= 0)                         $errors[] = 'Please select a brand.';
    if ($sku === '')                            $errors[] = 'SKU is required.';
    if ($category_id <= 0)                     $errors[] = 'Please select a category.';
    if (!is_numeric($price) || $price < 0)     $errors[] = 'Enter a valid price.';
    if (!is_numeric($stock) || $stock < 0)     $errors[] = 'Enter a valid stock quantity.';
    if (!in_array($status, ['active', 'draft', 'inactive'])) $status = 'draft';

    // Look up brand name for the legacy text column (still NOT NULL in the DB)
    $brand_name = '';
    if ($brand_id > 0) {
        $brand_lookup = $conn->prepare("SELECT brand_name FROM brands WHERE id = ?");
        $brand_lookup->execute([$brand_id]);
        $brand_name = (string)$brand_lookup->fetchColumn();
        if ($brand_name === '') $errors[] = 'Selected brand no longer exists.';
    }

    // SKU uniqueness — exclude the current product
    if (empty($errors)) {
        $sku_check = $conn->prepare("SELECT COUNT(*) FROM products WHERE sku = ? AND id != ?");
        $sku_check->execute([$sku, $product_id]);
        if ($sku_check->fetchColumn() > 0) {
            $errors[] = 'SKU "' . htmlspecialchars($sku) . '" is already used by another product.';
        }
    }

    $image_name = $product['image'];

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {

            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            $extension = strtolower(
                pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)
            );

            if (!in_array($extension, $allowed)) {

                $errors[] = 'Only JPG, JPEG, PNG and WEBP files are allowed.';
            } else {

                $upload_dir = "img/";

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $image_name = uniqid('product_', true) . '.' . $extension;

                move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    $upload_dir . $image_name
                );

                // Delete old image
                if (
                    !empty($product['image']) &&
                    file_exists($upload_dir . $product['image'])
                ) {
                    unlink($upload_dir . $product['image']);
                }
            }
        }
    }

    // ── Update ──
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE products SET
                    category_id  = :category_id,
                    product_name = :product_name,
                    brand_legacy = :brand_legacy,
                    brand_id     = :brand_id,
                    sku          = :sku,
                    description  = :description,
                    price        = :price,
                    stock        = :stock,
                    image        = :image,
                    featured     = :featured,
                    status       = :status
                WHERE id = :id
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
                ':status'       => $status,
                ':id'           => $product_id,
            ]);
            // Refresh product data from DB so the form shows saved values
            $stmt2 = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt2->execute([$product_id]);
            $product = $stmt2->fetch(PDO::FETCH_ASSOC);

            $success = true;
            $old     = [];
        } catch (PDOException $e) {
            $errors[] = 'Failed to update product: ' . $e->getMessage();
        }
    }
}

/* ══════════════════════════════════════════
   HELPER — field value resolution
   Priority: POST (on error) → DB row → default
══════════════════════════════════════════ */
function val(string $key, string $default = ''): string
{
    global $old, $product;
    if (!empty($old))  return htmlspecialchars($old[$key]     ?? $default);
    return htmlspecialchars($product[$key] ?? $default);
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
                <h1 class="ph-title">Edit Product</h1>
                <p class="ph-sub">
                    Editing <strong style="color:var(--txt)"><?= htmlspecialchars($product['product_name']) ?></strong>
                    &nbsp;·&nbsp;
                    <span class="td-mono" style="font-size:12px;color:var(--dim)"><?= htmlspecialchars($product['sku']) ?></span>
                </p>
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
                        <div style="font-family:var(--fd);font-size:14px;font-weight:700;color:var(--green)">Product updated successfully!</div>
                        <div style="font-size:12px;color:var(--muted);margin-top:2px">
                            <a href="products.php" style="color:var(--ac);text-decoration:none">View all products</a>
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
        <form method="POST" action="products_edit.php?id=<?= $product_id ?>" id="editForm" autocomplete="off" enctype="multipart/form-data">

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
                                    value="<?= val('product_name') ?>"
                                    required>
                            </div>

                            <!-- Brand + SKU -->
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

                                <div class="form-group">
                                    <label class="form-label">Brand <span class="req">*</span></label>
                                    <select name="brand_id" class="form-input" required>
                                        <option value="" disabled>Select brand…</option>
                                        <?php
                                        $current_brand_id = !empty($old) ? (int)($old['brand_id'] ?? 0) : (int)($product['brand_id'] ?? 0);
                                        foreach ($brands as $b):
                                        ?>
                                            <option value="<?= (int)$b['id'] ?>" <?= $current_brand_id === (int)$b['id'] ? 'selected' : '' ?>>
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
                                        value="<?= val('sku') ?>"
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
                                    placeholder="Short product description…"><?= val('description') ?></textarea>
                            </div>

                        </div>
                    </div>

                    <!-- Pricing & Inventory -->
                    <div class="adm-card au">
                        <div class="card-hd">
                            <div>
                                <div class="card-title">Pricing & Inventory</div>
                                <div class="card-sub">Retail price and current stock level</div>
                            </div>
                            <!-- Read-only sold count -->
                            <div style="text-align:right">
                                <div style="font-family:var(--fm);font-size:20px;font-weight:700;color:var(--ac);line-height:1"><?= number_format((int)$product['sold']) ?></div>
                                <div style="font-size:10px;color:var(--muted);margin-top:2px">units sold</div>
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
                                        value="<?= val('price') ?>"
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
                                    value="<?= val('stock') ?>"
                                    required>
                                <div class="form-hint">Units currently available for sale.</div>
                            </div>

                        </div>
                    </div>

                    <!-- Product Image -->
                    <div class="adm-card au">
                        <div class="card-hd">
                            <div>
                                <div class="card-title">Product Image</div>
                                <div class="card-sub">Upload a new image to replace the current one</div>
                            </div>
                        </div>

                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">

                            <!-- Current image pill (shown only when one exists) -->
                            <?php if (!empty($product['image'])): ?>
                                <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--card3);border:1px solid var(--bd);border-radius:calc(var(--r) - 4px)">
                                    <img
                                        src="img/<?= rawurlencode($product['image']) ?>"
                                        alt="Current image"
                                        style="width:44px;height:44px;object-fit:cover;border-radius:6px;flex-shrink:0">
                                    <div style="min-width:0">
                                        <div style="font-size:11px;font-weight:600;color:var(--txt)">Current image</div>
                                        <div style="font-size:10px;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($product['image']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Drop zone -->
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
                                    <div class="dz-hint"><?= !empty($product['image']) ? 'Replaces the current image · ' : '' ?>JPG, PNG or WEBP · max 5 MB</div>
                                </div>
                                <!-- preview state -->
                                <div id="dropPreview" style="display:none;flex-direction:column;align-items:center;gap:10px">
                                    <img id="imgPreview" src="" alt="Preview" style="max-height:120px;max-width:100%;border-radius:6px;object-fit:contain">
                                    <div id="dropFilename" style="font-size:11px;color:var(--muted);max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
                                    <button type="button" id="dropRemove" onclick="event.stopPropagation();removeImage()" style="font-size:11px;color:var(--red);background:none;border:none;cursor:pointer;padding:0">✕ Remove</button>
                                </div>
                            </div>

                            <div class="form-hint" style="margin-top:-8px">Leave empty to keep the current image.</div>

                        </div>
                    </div>

                </div><!-- /left column -->

                <!-- RIGHT COLUMN -->
                <div style="display:flex;flex-direction:column;gap:20px">

                    <!-- Publish -->
                    <div class="adm-card au">
                        <div class="card-hd">
                            <div class="card-title">Publish</div>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:16px">

                            <?php
                            $current_status = !empty($old) ? ($old['status'] ?? 'draft') : ($product['status'] ?? 'draft');
                            ?>
                            <div class="form-group" style="margin:0">
                                <label class="form-label">Status <span class="req">*</span></label>
                                <select name="status" class="form-input">
                                    <option value="active" <?= $current_status === 'active'   ? 'selected' : '' ?>>Active — visible in store</option>
                                    <option value="draft" <?= $current_status === 'draft'    ? 'selected' : '' ?>>Draft — hidden from store</option>
                                    <option value="inactive" <?= $current_status === 'inactive' ? 'selected' : '' ?>>Inactive — unlisted</option>
                                </select>
                            </div>

                            <?php
                            $is_featured = !empty($old)
                                ? isset($old['featured'])
                                : (int)($product['featured'] ?? 0) === 1;
                            ?>
                            <label class="toggle-row">
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:var(--txt)">Featured Product</div>
                                    <div style="font-size:11px;color:var(--muted);margin-top:2px">Highlighted on homepage &amp; category pages</div>
                                </div>
                                <div class="toggle-wrap">
                                    <input type="checkbox" name="featured" id="featuredToggle" class="toggle-input" <?= $is_featured ? 'checked' : '' ?>>
                                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                </div>
                            </label>

                            <hr style="border:none;border-top:1px solid var(--bd);margin:0">

                            <!-- Product meta (read-only) -->
                            <div style="display:flex;flex-direction:column;gap:8px">
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <span style="font-size:11px;color:var(--muted)">Product ID</span>
                                    <span class="td-mono" style="font-size:11px;color:var(--dim)">#<?= $product['id'] ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <span style="font-size:11px;color:var(--muted)">Units Sold</span>
                                    <span class="td-mono" style="font-size:11px;color:var(--ac);font-weight:600"><?= number_format((int)$product['sold']) ?></span>
                                </div>
                            </div>

                            <hr style="border:none;border-top:1px solid var(--bd);margin:0">

                            <div style="display:flex;flex-direction:column;gap:10px">
                                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                        <polyline points="17 21 17 13 7 13 7 21" />
                                        <polyline points="7 3 7 8 15 8" />
                                    </svg>
                                    Save Changes
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
                                    <option value="" disabled>Select category…</option>
                                    <?php
                                    $current_cat = !empty($old)
                                        ? (int)($old['category_id'] ?? 0)
                                        : (int)($product['category_id'] ?? 0);
                                    foreach ($categories as $cat):
                                    ?>
                                        <option value="<?= $cat['id'] ?>" <?= $current_cat === (int)$cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="adm-card au" style="border-color:rgba(239,68,68,.25)">
                        <div class="card-hd">
                            <div class="card-title" style="color:var(--red)">Danger Zone</div>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
                            <p style="font-size:12px;color:var(--muted);margin:0;line-height:1.6">
                                Permanently deletes this product and all associated order item records. This action <strong style="color:var(--txt)">cannot be undone</strong>.
                            </p>
                            <button type="button" class="btn btn-secondary" style="color:var(--red);border-color:rgba(239,68,68,.3);width:100%;justify-content:center" onclick="openDeleteModal()">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                    <path d="M10 11v6M14 11v6" />
                                </svg>
                                Delete This Product
                            </button>
                        </div>
                    </div>

                </div><!-- /right column -->

            </div><!-- /grid -->

        </form>

        <!-- ── Delete Confirm Modal ── -->
        <div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
            <div style="background:var(--card);border:1px solid rgba(239,68,68,.3);border-radius:var(--r);padding:28px;max-width:380px;width:90%;box-shadow:var(--sh2)">
                <div style="font-size:32px;margin-bottom:12px;text-align:center">🗑️</div>
                <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:8px">Delete Product?</div>
                <div style="font-size:13px;color:var(--muted);text-align:center;margin-bottom:4px">
                    <strong style="color:var(--txt)"><?= htmlspecialchars($product['product_name']) ?></strong>
                </div>
                <div style="font-size:12px;color:var(--dim);text-align:center;margin-bottom:24px">This action cannot be undone.</div>
                <div style="display:flex;gap:10px">
                    <button class="btn btn-secondary" style="flex:1" onclick="closeDeleteModal()">Cancel</button>
                    <form method="POST" action="products_edit.php?id=<?= $product_id ?>" style="flex:1">
                        <input type="hidden" name="_action" value="delete">
                        <button type="submit" class="btn" style="width:100%;background:var(--red);color:#fff;justify-content:center">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>

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
            const dt = new DataTransfer();
            dt.items.add(file);
            imgFileInput.files = dt.files;
            applyFile(file);
        }
    });

    /* ── Delete modal ── */
    function openDeleteModal() {
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
    document.getElementById('deleteModal').addEventListener('click', e => {
        if (e.target === e.currentTarget) closeDeleteModal();
    });

    /* ── Form submit feedback ── */
    document.getElementById('editForm').addEventListener('submit', () => {
        showToast('Saving changes…', '💾');
    });

    /* ── ESC ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeSb();
            closeDeleteModal();
        }
    });
</script>

</body>

</html>