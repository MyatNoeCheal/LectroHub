<?php

require_once "auth_check.php";


$page_title = "Users — LectroHub Admin";

require_once "../lectrohub_db/connection.php";

$errors  = [];
$success = '';

/* ══════════════════════════════════════════
   HANDLE POST ACTIONS
══════════════════════════════════════════ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action  = $_POST['_action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    /* ── EDIT ROLE ── */
    if ($action === 'edit_role' && $user_id > 0) {

        $new_role = $_POST['role'] ?? '';

        if (!in_array($new_role, ['user', 'admin'])) {
            $errors[] = 'Invalid role selected.';
        } else {

            // If demoting admin → user, ensure at least one other admin remains
            if ($new_role === 'user') {
                $target_stmt = $conn->prepare("SELECT role, full_name FROM users WHERE id = ?");
                $target_stmt->execute([$user_id]);
                $target = $target_stmt->fetch(PDO::FETCH_ASSOC);

                if ($target && $target['role'] === 'admin') {
                    $admin_count = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                    if ($admin_count <= 1) {
                        $errors[] = 'Cannot demote the last remaining admin account.';
                    }
                }
            }

            if (empty($errors)) {
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                $success = 'User role updated successfully.';
            }
        }
    }

    /* ── DELETE USER ── */
    if ($action === 'delete' && $user_id > 0) {

        $target_stmt = $conn->prepare("SELECT role, full_name FROM users WHERE id = ?");
        $target_stmt->execute([$user_id]);
        $target_user = $target_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target_user) {
            $errors[] = 'User not found.';
        } elseif ($target_user['role'] === 'admin') {
            $admin_count = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($admin_count <= 1) {
                $errors[] = 'Cannot delete the last remaining admin account.';
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $success = 'User "' . htmlspecialchars($target_user['full_name']) . '" has been deleted.';
        }
    }
}

/* ══════════════════════════════════════════
   LOAD USERS
══════════════════════════════════════════ */

$users = [];

try {
    $users = $conn->query("
        SELECT id, full_name, email, role, created_at
        FROM users
        ORDER BY role DESC, created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

$total_users  = count($users);
$total_admins = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$total_regs   = count(array_filter($users, fn($u) => $u['role'] === 'user'));

$new_this_month = (int)$conn->query("
    SELECT COUNT(*) FROM users
    WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetchColumn();

$username  = $_SESSION['user']['full_name'] ?? 'Admin';
$initials  = strtoupper(substr($username, 0, 1));
$nav_title = 'Users';
$nav_sub   = 'Manage Accounts';

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
                <div class="ph-eyebrow">Management</div>
                <h1 class="ph-title">Users</h1>
                <p class="ph-sub"><?= $total_users ?> accounts &nbsp;·&nbsp; <?= $total_admins ?> admin<?= $total_admins !== 1 ? 's' : '' ?> &nbsp;·&nbsp; <?= $total_regs ?> customers</p>
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
                ['label' => 'Total Users',    'value' => $total_users,    'color' => 'var(--ac)',     'icon' => '👥'],
                ['label' => 'Admins',         'value' => $total_admins,   'color' => 'var(--purple)', 'icon' => '🛡️'],
                ['label' => 'Customers',      'value' => $total_regs,     'color' => 'var(--green)',  'icon' => '🛒'],
                ['label' => 'New This Month', 'value' => $new_this_month, 'color' => 'var(--amber)',  'icon' => '🆕'],
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
            <input type="text" id="searchInput" placeholder="Search by name or email…" oninput="filterUsers()">
            <select id="roleFilter" onchange="filterUsers()">
                <option value="all">All Roles</option>
                <option value="admin">Admin</option>
                <option value="user">Customer</option>
            </select>
            <div class="filter-spacer"></div>
            <span style="font-size:11px;color:var(--dim)" id="resultCount"><?= $total_users ?> users</span>
        </div>

        <!-- Users Table -->
        <div class="adm-card au">
            <div class="card-body p0">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <?php foreach ($users as $u):
                            $is_admin   = $u['role'] === 'admin';
                            $role_cls   = $is_admin ? 'b-purple' : 'b-grey';
                            $role_label = $is_admin ? '🛡️ Admin' : '🛒 Customer';
                            $u_initial  = strtoupper(substr($u['full_name'], 0, 1));
                        ?>
                            <tr data-name="<?= strtolower(htmlspecialchars($u['full_name'])) ?>"
                                data-email="<?= strtolower(htmlspecialchars($u['email'])) ?>"
                                data-role="<?= $u['role'] ?>">

                                <td>
                                    <div style="display:flex;align-items:center;gap:11px">
                                        <div style="width:36px;height:36px;border-radius:50%;background:<?= $is_admin ? 'rgba(167,139,250,.18)' : 'rgba(0,229,255,.1)' ?>;display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:14px;font-weight:700;color:<?= $is_admin ? 'var(--purple)' : 'var(--ac)' ?>;flex-shrink:0">
                                            <?= htmlspecialchars($u_initial) ?>
                                        </div>
                                        <div>
                                            <div style="font-size:13px;font-weight:600;color:var(--txt)"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <div style="font-size:10px;color:var(--dim);margin-top:1px">#<?= str_pad($u['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td><span style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></span></td>

                                <td><span class="badge <?= $role_cls ?>"><?= $role_label ?></span></td>

                                <td><span style="font-size:12px;color:var(--muted)"><?= date('M j, Y', strtotime($u['created_at'])) ?></span></td>

                                <td>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <button class="btn btn-icon btn-sm" title="Edit Role"
                                            onclick="openRoleModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>', '<?= $u['role'] ?>')">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>
                                        <button class="btn btn-icon btn-sm" title="Delete User"
                                            style="color:rgba(239,68,68,.7)"
                                            onclick="openDeleteModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name'], ENT_QUOTES) ?>', '<?= $u['role'] ?>', <?= $total_admins ?>)">
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

                <div id="emptyState" style="display:none;padding:48px 24px;text-align:center">
                    <div style="font-size:36px;margin-bottom:10px">👥</div>
                    <div style="font-family:var(--fd);font-size:14px;font-weight:700;color:var(--txt)">No users found</div>
                    <div style="font-size:12px;color:var(--muted);margin-top:4px">Try adjusting your search or filters.</div>
                </div>
            </div>
        </div>

    </main>
</div>

<?php require_once "footer.php"; ?>

<div class="toast" id="toast"><span id="ti" style="font-size:17px">✓</span><span id="tm"></span></div>

<!-- Edit Role Modal -->
<div id="roleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:28px;max-width:400px;width:90%;box-shadow:var(--sh2)">
        <div style="font-size:28px;margin-bottom:12px;text-align:center">🛡️</div>
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:4px">Edit User Role</div>
        <div style="font-size:12px;color:var(--muted);text-align:center;margin-bottom:22px" id="roleModalName"></div>
        <form method="POST" action="users.php">
            <input type="hidden" name="_action" value="edit_role">
            <input type="hidden" name="user_id" id="roleUserId">
            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:22px">
                <label id="roleOptAdmin" style="display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--bd);border-radius:calc(var(--r) - 4px);cursor:pointer;transition:border-color .15s,background .15s">
                    <input type="radio" name="role" value="admin" id="roleAdmin" style="accent-color:var(--purple)" onchange="highlightRole()">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--txt)">🛡️ Admin</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:1px">Full access to admin panel</div>
                    </div>
                </label>
                <label id="roleOptUser" style="display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--bd);border-radius:calc(var(--r) - 4px);cursor:pointer;transition:border-color .15s,background .15s">
                    <input type="radio" name="role" value="user" id="roleUser" style="accent-color:var(--ac)" onchange="highlightRole()">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--txt)">🛒 Customer</div>
                        <div style="font-size:11px;color:var(--muted);margin-top:1px">Storefront access only</div>
                    </div>
                </label>
            </div>
            <div style="display:flex;gap:10px">
                <button type="button" class="btn btn-secondary" style="flex:1" onclick="closeRoleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center">Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:8000;align-items:center;justify-content:center">
    <div style="background:var(--card);border:1px solid rgba(239,68,68,.25);border-radius:var(--r);padding:28px;max-width:380px;width:90%;box-shadow:var(--sh2)">
        <div style="font-size:32px;margin-bottom:12px;text-align:center">🗑️</div>
        <div style="font-family:var(--fd);font-size:16px;font-weight:700;color:var(--txt);text-align:center;margin-bottom:6px">Delete User?</div>
        <div style="font-size:13px;font-weight:600;color:var(--txt);text-align:center;margin-bottom:4px" id="deleteModalName"></div>
        <div style="font-size:12px;color:var(--dim);text-align:center;margin-bottom:20px">This action cannot be undone.</div>
        <div id="lastAdminWarn" style="display:none;padding:10px 14px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:calc(var(--r) - 4px);margin-bottom:18px;font-size:12px;color:var(--red);text-align:center;line-height:1.5">
            ⚠️ This is the <strong>last admin account</strong> and cannot be deleted.
        </div>
        <div style="display:flex;gap:10px">
            <button class="btn btn-secondary" style="flex:1" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" action="users.php" style="flex:1">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <button type="submit" id="deleteConfirmBtn" class="btn"
                    style="width:100%;background:var(--red);color:#fff;justify-content:center">
                    Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
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

    let toastTimer;
    function showToast(msg, icon = '✓') {
        const t = document.getElementById('toast');
        document.getElementById('ti').textContent = icon;
        document.getElementById('tm').textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
    }

    function filterUsers() {
        const q    = document.getElementById('searchInput').value.toLowerCase().trim();
        const role = document.getElementById('roleFilter').value;
        const rows = document.querySelectorAll('#userTableBody tr[data-name]');
        let visible = 0;
        rows.forEach(row => {
            const show = (!q || row.dataset.name.includes(q) || row.dataset.email.includes(q)) &&
                         (role === 'all' || row.dataset.role === role);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('resultCount').textContent = visible + ' user' + (visible !== 1 ? 's' : '');
        document.getElementById('emptyState').style.display = visible === 0 ? 'block' : 'none';
    }

    function openRoleModal(id, name, currentRole) {
        document.getElementById('roleUserId').value      = id;
        document.getElementById('roleModalName').textContent = name;
        document.getElementById('roleAdmin').checked     = currentRole === 'admin';
        document.getElementById('roleUser').checked      = currentRole === 'user';
        highlightRole();
        document.getElementById('roleModal').style.display = 'flex';
    }
    function closeRoleModal() { document.getElementById('roleModal').style.display = 'none'; }
    function highlightRole() {
        const a = document.getElementById('roleAdmin').checked;
        document.getElementById('roleOptAdmin').style.cssText += ';border-color:' + (a ? 'var(--purple)' : 'var(--bd)') + ';background:' + (a ? 'rgba(167,139,250,.06)' : '');
        document.getElementById('roleOptUser').style.cssText  += ';border-color:' + (!a ? 'var(--ac)' : 'var(--bd)')    + ';background:' + (!a ? 'rgba(0,229,255,.04)' : '');
    }
    document.getElementById('roleModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeRoleModal(); });

    function openDeleteModal(id, name, role, adminCount) {
        document.getElementById('deleteUserId').value        = id;
        document.getElementById('deleteModalName').textContent = name;
        const isLastAdmin = role === 'admin' && adminCount <= 1;
        document.getElementById('lastAdminWarn').style.display   = isLastAdmin ? 'block' : 'none';
        document.getElementById('deleteConfirmBtn').disabled      = isLastAdmin;
        document.getElementById('deleteConfirmBtn').style.opacity = isLastAdmin ? '0.4' : '1';
        document.getElementById('deleteConfirmBtn').style.cursor  = isLastAdmin ? 'not-allowed' : '';
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
    document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteModal(); });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSb(); closeRoleModal(); closeDeleteModal(); }
    });
</script>

</body>
</html>