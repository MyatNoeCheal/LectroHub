<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "auth_check.php";
require_once "../lectrohub_db/connection.php";
require_once __DIR__ . "/lang/translate.php";

$user_id = (int)$_SESSION['user']['id'];

$errors     = [];
$success    = '';
$active_tab = $_GET['tab'] ?? 'profile';
if (!in_array($active_tab, ['profile', 'addresses', 'security'], true)) {
    $active_tab = 'profile';
}

/* ══════════════════════════════════════════
   LOAD USER
══════════════════════════════════════════ */
$stmt = $conn->prepare("SELECT id, full_name, email, phone, password, google_id, role, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: /LectroHub/auth/signout.php");
    exit;
}

$has_password = !empty($user['password']);
$is_google     = !empty($user['google_id']);

/* ══════════════════════════════════════════
   HANDLE POST ACTIONS
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['_action'] ?? '';

    /* ── UPDATE PROFILE ── */
    if ($action === 'update_profile') {
        $active_tab = 'profile';

        $full_name = trim($_POST['full_name'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');

        if ($full_name === '') {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($full_name) > 150) {
            $errors[] = 'Full name is too long.';
        }

        if (empty($errors)) {
            try {
                $upd = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
                $upd->execute([$full_name, $phone !== '' ? $phone : null, $user_id]);

                $_SESSION['user']['full_name'] = $full_name;
                $user['full_name'] = $full_name;
                $user['phone']     = $phone;

                $success = 'Profile updated successfully.';
            } catch (PDOException $e) {
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
    }

    /* ── CHANGE / SET PASSWORD ── */
    if ($action === 'change_password') {
        $active_tab = 'security';

        $was_set  = $has_password; // remember state before we touch it
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($was_set && !password_verify($current, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($new_pass) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($new_pass !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            try {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $conn->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user_id]);
                $has_password = true;
                $success = $was_set ? 'Password updated successfully.' : 'Password set successfully — you can now sign in with your email too.';
            } catch (PDOException $e) {
                $errors[] = 'Failed to update password. Please try again.';
            }
        }
    }

    /* ── ADD / EDIT ADDRESS ── */
    if ($action === 'save_address') {
        $active_tab = 'addresses';

        $address_id   = (int)($_POST['address_id'] ?? 0);
        $full_name_a  = trim($_POST['full_name'] ?? '');
        $phone_a      = trim($_POST['phone'] ?? '');
        $address_line = trim($_POST['address_line'] ?? '');
        $city         = trim($_POST['city'] ?? '');
        $state        = trim($_POST['state'] ?? '');
        $is_default   = isset($_POST['is_default']) ? 1 : 0;

        if ($full_name_a === '' || $address_line === '' || $city === '') {
            $errors[] = 'Please fill in all required address fields.';
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                if ($is_default) {
                    $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
                }

                if ($address_id > 0) {
                    // Ownership check — never trust the posted id blindly
                    $chk = $conn->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
                    $chk->execute([$address_id, $user_id]);
                    if (!$chk->fetch()) {
                        throw new Exception('not_found');
                    }
                    $upd = $conn->prepare("
                        UPDATE addresses SET full_name=?, phone=?, address_line=?, city=?, state=?, is_default=?
                        WHERE id = ? AND user_id = ?
                    ");
                    $upd->execute([$full_name_a, $phone_a, $address_line, $city, $state, $is_default, $address_id, $user_id]);
                    $success = 'Address updated successfully.';
                } else {
                    $ins = $conn->prepare("
                        INSERT INTO addresses (user_id, full_name, phone, address_line, city, state, is_default)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$user_id, $full_name_a, $phone_a, $address_line, $city, $state, $is_default]);
                    $success = 'Address added successfully.';
                }

                // Guarantee exactly one default when the user has addresses
                $has_default_chk = $conn->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = ? AND is_default = 1");
                $has_default_chk->execute([$user_id]);
                if ((int)$has_default_chk->fetchColumn() === 0) {
                    $conn->prepare("UPDATE addresses SET is_default = 1 WHERE user_id = ? ORDER BY id ASC LIMIT 1")->execute([$user_id]);
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
                $errors[] = 'Failed to save address. Please try again.';
            }
        }
    }

    /* ── DELETE ADDRESS ── */
    if ($action === 'delete_address') {
        $active_tab = 'addresses';
        $address_id = (int)($_POST['address_id'] ?? 0);

        try {
            $del = $conn->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
            $del->execute([$address_id, $user_id]);

            // Promote another address to default if none remain
            $has_default_chk = $conn->prepare("SELECT COUNT(*) FROM addresses WHERE user_id = ? AND is_default = 1");
            $has_default_chk->execute([$user_id]);
            if ((int)$has_default_chk->fetchColumn() === 0) {
                $conn->prepare("UPDATE addresses SET is_default = 1 WHERE user_id = ? ORDER BY id ASC LIMIT 1")->execute([$user_id]);
            }

            $success = 'Address removed.';
        } catch (PDOException $e) {
            $errors[] = 'Failed to delete address. Please try again.';
        }
    }

    /* ── SET DEFAULT ADDRESS ── */
    if ($action === 'set_default_address') {
        $active_tab = 'addresses';
        $address_id = (int)($_POST['address_id'] ?? 0);

        try {
            $conn->beginTransaction();
            $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
            $conn->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$address_id, $user_id]);
            $conn->commit();
            $success = 'Default address updated.';
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = 'Failed to update default address.';
        }
    }
}

/* ══════════════════════════════════════════
   LOAD ADDRESSES
══════════════════════════════════════════ */
$addr_stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addr_stmt->execute([$user_id]);
$addresses = $addr_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ══════════════════════════════════════════
   ACCOUNT STATS
══════════════════════════════════════════ */
$order_stats_stmt = $conn->prepare("
    SELECT COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS total_spent
    FROM orders WHERE user_id = ?
");
$order_stats_stmt->execute([$user_id]);
$order_stats = $order_stats_stmt->fetch(PDO::FETCH_ASSOC);

$wishlist_count_stmt = $conn->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
$wishlist_count_stmt->execute([$user_id]);
$wishlist_count = (int)$wishlist_count_stmt->fetchColumn();

$parts = explode(' ', trim($user['full_name']));
$user_initials = strtoupper(substr($parts[0], 0, 1));
if (isset($parts[1])) $user_initials .= strtoupper(substr($parts[1], 0, 1));

$member_since = date('F Y', strtotime($user['created_at']));

$mm_cities = [
    'Yangon', 'Mandalay', 'Naypyidaw', 'Bago', 'Mawlamyine', 'Taunggyi',
    'Myitkyina', 'Sittwe', 'Pathein', 'Magway', 'Monywa', 'Meiktila',
    'Pyay', 'Hpa-An', 'Lashio', 'Dawei', 'Loikaw', 'Hakha', 'Kengtung', 'Myeik',
];
?>
<?php require_once "head.php" ?>
<?php require_once "navbar.php" ?>

<body>
<main class="account-page" style="max-width:1000px;margin:0 auto;padding:32px 24px 80px">

    <!-- ── BREADCRUMB ── -->
    <div class="cart-breadcrumb">
        <a href="index.php"><?= htmlspecialchars(t('home')) ?></a>
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
        <span>My Account</span>
    </div>

    <!-- ── PAGE HEADER ── -->
    <div class="orders-hd" style="margin-top:14px">
        <div style="display:flex;align-items:center;gap:16px">
            <div style="width:56px;height:56px;border-radius:50%;background:var(--accent,#00e5ff);color:#04070c;display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:20px;font-weight:800;flex-shrink:0">
                <?= htmlspecialchars($user_initials) ?>
            </div>
            <div>
                <h1 class="orders-title" style="margin-bottom:2px"><?= htmlspecialchars($user['full_name']) ?></h1>
                <p class="orders-sub">Member since <?= htmlspecialchars($member_since) ?></p>
            </div>
        </div>
        <a href="/LectroHub/auth/signout.php" class="btn-outline-sm">Sign Out</a>
    </div>

    <!-- ── STAT STRIP ── -->
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin:24px 0">
        <div style="flex:1;min-width:150px;padding:16px 18px;border:1px solid var(--border,#242c3d);border-radius:10px">
            <div style="font-size:22px;font-weight:800;color:var(--accent,#00e5ff)"><?= number_format((int)$order_stats['order_count']) ?></div>
            <div style="font-size:12px;color:var(--muted)">Orders placed</div>
        </div>
        <div style="flex:1;min-width:150px;padding:16px 18px;border:1px solid var(--border,#242c3d);border-radius:10px">
            <div style="font-size:22px;font-weight:800;color:var(--accent,#00e5ff)">$<?= number_format((float)$order_stats['total_spent'], 2) ?></div>
            <div style="font-size:12px;color:var(--muted)">Total spent</div>
        </div>
        <div style="flex:1;min-width:150px;padding:16px 18px;border:1px solid var(--border,#242c3d);border-radius:10px">
            <div style="font-size:22px;font-weight:800;color:var(--accent,#00e5ff)"><?= $wishlist_count ?></div>
            <div style="font-size:12px;color:var(--muted)">Wishlist items</div>
        </div>
        <div style="flex:1;min-width:150px;padding:16px 18px;border:1px solid var(--border,#242c3d);border-radius:10px">
            <div style="font-size:22px;font-weight:800;color:var(--accent,#00e5ff)"><?= count($addresses) ?></div>
            <div style="font-size:12px;color:var(--muted)">Saved addresses</div>
        </div>
    </div>

    <!-- ── BANNERS ── -->
    <?php if ($success): ?>
        <div style="background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.3);border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:600;color:#4ade80">
            ✅ <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:8px;padding:12px 16px;margin-bottom:20px">
            <?php foreach ($errors as $err): ?>
                <div style="font-size:13px;font-weight:600;color:#ef4444">⚠️ <?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── TABS ── -->
    <div style="display:flex;gap:4px;border-bottom:1px solid var(--border,#242c3d);margin-bottom:24px">
        <a href="account.php?tab=profile"   class="account-tab <?= $active_tab === 'profile'   ? 'active' : '' ?>">Profile</a>
        <a href="account.php?tab=addresses" class="account-tab <?= $active_tab === 'addresses' ? 'active' : '' ?>">Addresses</a>
        <a href="account.php?tab=security"  class="account-tab <?= $active_tab === 'security'  ? 'active' : '' ?>">Security</a>
    </div>

    <?php if ($active_tab === 'profile'): ?>

        <!-- ══════════════════════════════════════════
             PROFILE TAB
        ══════════════════════════════════════════ -->
        <div class="co-card">
            <div class="co-card-hd"><span class="co-step">1</span> Personal Information</div>
            <form method="POST" action="account.php?tab=profile">
                <input type="hidden" name="_action" value="update_profile">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-input" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+49 151 0000 0000">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity:.6;cursor:not-allowed">
                    <div class="form-hint">Contact support to change your email address.</div>
                </div>
                <button type="submit" class="btn-checkout" style="max-width:220px;margin-top:8px">Save Changes</button>
            </form>
        </div>

    <?php elseif ($active_tab === 'addresses'): ?>

        <!-- ══════════════════════════════════════════
             ADDRESSES TAB
        ══════════════════════════════════════════ -->
        <div class="co-card">
            <div class="co-card-hd"><span class="co-step">2</span> Saved Addresses</div>

            <?php if (empty($addresses)): ?>
                <p style="font-size:13px;color:var(--muted)">You don't have any saved addresses yet.</p>
            <?php else: ?>
                <div class="saved-addresses" style="margin-bottom:20px">
                    <?php foreach ($addresses as $addr): ?>
                        <div class="addr-option <?= $addr['is_default'] ? 'selected' : '' ?>" style="cursor:default">
                            <div class="addr-body" style="width:100%">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
                                    <div>
                                        <div class="addr-name"><?= htmlspecialchars($addr['full_name']) ?></div>
                                        <div class="addr-line"><?= htmlspecialchars($addr['address_line']) ?></div>
                                        <div class="addr-line"><?= htmlspecialchars($addr['city']) ?><?= $addr['state'] ? ', ' . htmlspecialchars($addr['state']) : '' ?></div>
                                        <?php if ($addr['phone']): ?><div class="addr-phone"><?= htmlspecialchars($addr['phone']) ?></div><?php endif; ?>
                                        <?php if ($addr['is_default']): ?><span class="addr-default-tag">Default</span><?php endif; ?>
                                    </div>
                                    <div style="display:flex;gap:6px;flex-shrink:0">
                                        <?php if (!$addr['is_default']): ?>
                                            <form method="POST" action="account.php?tab=addresses">
                                                <input type="hidden" name="_action" value="set_default_address">
                                                <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                                <button type="submit" class="btn-outline-sm" style="font-size:11px;padding:5px 10px">Set Default</button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" class="btn-outline-sm" style="font-size:11px;padding:5px 10px"
                                            onclick='fillAddressForm(<?= json_encode($addr, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                        <form method="POST" action="account.php?tab=addresses" onsubmit="return confirm('Remove this address?')">
                                            <input type="hidden" name="_action" value="delete_address">
                                            <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                            <button type="submit" class="btn-danger-sm" style="font-size:11px;padding:5px 10px">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr style="border:none;border-top:1px solid var(--border,#242c3d);margin:20px 0">

            <div id="addressFormTitle" style="font-size:14px;font-weight:700;margin-bottom:12px">Add a New Address</div>
            <form method="POST" action="account.php?tab=addresses" id="addressForm">
                <input type="hidden" name="_action" value="save_address">
                <input type="hidden" name="address_id" id="af-id" value="">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" id="af-full-name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="af-phone" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address *</label>
                    <input type="text" name="address_line" id="af-address" class="form-input" required>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">City *</label>
                        <select name="city" id="af-city" class="form-input" required>
                            <option value="" disabled selected>Select city…</option>
                            <?php foreach ($mm_cities as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">State / Region</label>
                        <input type="text" name="state" id="af-state" class="form-input">
                    </div>
                </div>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);margin-bottom:14px">
                    <input type="checkbox" name="is_default" id="af-default">
                    Set as default address
                </label>

                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn-checkout" style="max-width:200px">Save Address</button>
                    <button type="button" class="btn-outline-sm" onclick="resetAddressForm()">Cancel</button>
                </div>
            </form>
        </div>

    <?php else: /* security */ ?>

        <!-- ══════════════════════════════════════════
             SECURITY TAB
        ══════════════════════════════════════════ -->
        <div class="co-card">
            <div class="co-card-hd"><span class="co-step">3</span> <?= $has_password ? 'Change Password' : 'Set a Password' ?></div>

            <?php if ($is_google && !$has_password): ?>
                <p style="font-size:13px;color:var(--muted);margin-bottom:14px">
                    Your account currently signs in via Google only. Set a password below to also enable email/password sign-in.
                </p>
            <?php endif; ?>

            <form method="POST" action="account.php?tab=security">
                <input type="hidden" name="_action" value="change_password">

                <?php if ($has_password): ?>
                <div class="form-group">
                    <label class="form-label">Current Password *</label>
                    <input type="password" name="current_password" class="form-input" required>
                </div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" class="form-input" minlength="8" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-input" minlength="8" required>
                    </div>
                </div>
                <div class="form-hint" style="margin-bottom:14px">Must be at least 8 characters.</div>

                <button type="submit" class="btn-checkout" style="max-width:220px"><?= $has_password ? 'Update Password' : 'Set Password' ?></button>
            </form>
        </div>

    <?php endif; ?>

</main>

<?php require_once "footer.php" ?>

<div class="toast" id="toast">
    <span class="toast-icon" id="toastIcon">✓</span>
    <span id="toastMsg">Done</span>
</div>

<style>
.account-tab {
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
}
.account-tab.active {
    color: var(--accent, #00e5ff);
    border-bottom-color: var(--accent, #00e5ff);
}
</style>

<script>
function fillAddressForm(addr) {
    document.getElementById('af-id').value = addr.id;
    document.getElementById('af-full-name').value = addr.full_name || '';
    document.getElementById('af-phone').value = addr.phone || '';
    document.getElementById('af-address').value = addr.address_line || '';
    document.getElementById('af-city').value = addr.city || '';
    document.getElementById('af-state').value = addr.state || '';
    document.getElementById('af-default').checked = !!parseInt(addr.is_default, 10);
    document.getElementById('addressFormTitle').textContent = 'Edit Address';
    document.getElementById('addressForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function resetAddressForm() {
    document.getElementById('addressForm').reset();
    document.getElementById('af-id').value = '';
    document.getElementById('addressFormTitle').textContent = 'Add a New Address';
}
</script>

</body>