<?php

/* ── LANGUAGE SWITCHER + TRANSLATIONS ── */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/lang/translate.php";

$nav_langs = [
    'en' => ['label' => 'English', 'flag' => '🇬🇧'],
    'mm' => ['label' => 'မြန်မာ',   'flag' => '🇲🇲'],
];

if (isset($_GET['lang']) && isset($nav_langs[$_GET['lang']])) {
    $_SESSION['lang'] = $_GET['lang'];
    $qs = $_GET;
    unset($qs['lang']);
    $back = strtok($_SERVER['REQUEST_URI'], '?') . (!empty($qs) ? '?' . http_build_query($qs) : '');
    header("Location: $back");
    exit;
}

$current_lang = $_SESSION['lang'] ?? 'en';

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$user_name  = $_SESSION['user']['full_name'] ?? null;
$user_email = $_SESSION['user']['email']     ?? null;
$user_id    = $_SESSION['user']['id']        ?? null;

$wishlist_count = 0;
if ($user_id) {
    try {
        $wl_count_stmt = $conn->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $wl_count_stmt->execute([$user_id]);
        $wishlist_count = (int)$wl_count_stmt->fetchColumn();
    } catch (PDOException $e) {
        $wishlist_count = 0;
    }
} elseif (isset($_SESSION['wishlist']) && is_array($_SESSION['wishlist'])) {
    $wishlist_count = count($_SESSION['wishlist']);
}

$user_initials = '';
if ($user_name) {
    $parts = explode(' ', trim($user_name));
    $user_initials = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $user_initials .= strtoupper(substr($parts[1], 0, 1));
}

require_once "head.php"
?>

<?php if (!empty($_SESSION['flash'])): ?>
<div style="display:flex;align-items:center;justify-content:center;gap:8px;padding:10px 16px;font-size:13px;font-weight:600;
    <?= $_SESSION['flash']['type'] === 'success'
        ? 'background:#0f3d2e;color:#5eead4;'
        : 'background:#3d0f0f;color:#ff9a9a;' ?>">
    <?= htmlspecialchars($_SESSION['flash']['text']) ?>
</div>
<?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<nav>
    <a href="index.php" class="nav-logo">Lectro<span>Hub</span></a>

    <div class="nav-search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
        </svg>
        <input type="text" placeholder="<?= htmlspecialchars(t('search_placeholder')) ?>" id="navSearch">
    </div>

    <div class="nav-actions">
        <a href="products.php" class="nav-btn nav-btn-ghost"><?= htmlspecialchars(t('shop')) ?></a>
        <a href="deals.php" class="nav-btn nav-btn-ghost"><?= htmlspecialchars(t('deals')) ?></a>

        <!-- ── FIX: THEME TOGGLE (light / dark) — affects every page site-wide ── -->
        <button type="button" class="nav-icon-btn theme-toggle-btn" onclick="lhToggleTheme()"
            title="Toggle light / dark theme" aria-label="Toggle light / dark theme" aria-pressed="false">
            <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="5" />
                <line x1="12" y1="1" x2="12" y2="3" />
                <line x1="12" y1="21" x2="12" y2="23" />
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                <line x1="1" y1="12" x2="3" y2="12" />
                <line x1="21" y1="12" x2="23" y2="12" />
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
            </svg>
            <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
        </button>

        <!-- ── LANGUAGE SWITCHER ── -->
        <div class="nav-user-dropdown" id="langDropdown">
            <button class="nav-user-btn" onclick="toggleLangDrop()"
                aria-label="Change language" aria-expanded="false" aria-haspopup="true"
                style="padding:6px 10px">
                <span style="font-size:15px"><?= $nav_langs[$current_lang]['flag'] ?></span>
                <span style="font-size:12px;font-weight:700"><?= strtoupper($current_lang) ?></span>
                <svg class="nav-chevron" width="12" height="12" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M6 9l6 6 6-6" />
                </svg>
            </button>

            <div class="nav-drop-menu" id="langDropMenu" style="display:none;min-width:170px">
                <?php foreach ($nav_langs as $code => $lang):
                    $lq = $_GET;
                    $lq['lang'] = $code;
                ?>
                    <a href="?<?= htmlspecialchars(http_build_query($lq)) ?>" class="nav-drop-item">
                        <span style="font-size:15px"><?= $lang['flag'] ?></span>
                        <?= htmlspecialchars($lang['label']) ?>
                        <?php if ($current_lang === $code): ?>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-left:auto">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── WISHLIST ── -->
        <a href="wishlist.php" class="nav-icon-btn nav-wishlist" title="<?= htmlspecialchars(t('wishlist')) ?>" aria-label="<?= htmlspecialchars(t('wishlist')) ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
            </svg>
            <?php if ($wishlist_count > 0): ?>
                <span class="wishlist-badge"><?= $wishlist_count ?></span>
            <?php endif; ?>
        </a>

        <!-- ── CART ── -->
        <a href="cart.php" class="nav-btn nav-btn-cart">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <path d="M16 10a4 4 0 0 1-8 0" />
            </svg>
            <?= htmlspecialchars(t('cart')) ?>
            <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?= $cart_count ?></span>
            <?php endif; ?>
        </a>

        <!-- ── USER: LOGGED IN ── -->
        <?php if ($user_name): ?>
            <div class="nav-user-dropdown" id="userDropdown">
                <button class="nav-user-btn" onclick="toggleUserDrop()"
                    aria-label="Account menu" aria-expanded="false" aria-haspopup="true">
                    <div class="nav-user-avatar"><?= htmlspecialchars($user_initials) ?></div>
                    <span class="nav-user-name"><?= htmlspecialchars(explode(' ', $user_name)[0]) ?></span>
                    <svg class="nav-chevron" width="12" height="12" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M6 9l6 6 6-6" />
                    </svg>
                </button>

                <div class="nav-drop-menu" id="userDropMenu" style="display:none">
                    <div class="nav-drop-header">
                        <div class="ndh-avatar"><?= htmlspecialchars($user_initials) ?></div>
                        <div class="ndh-info">
                            <div class="ndh-name"><?= htmlspecialchars($user_name) ?></div>
                            <div class="ndh-email"><?= htmlspecialchars($user_email ?? '') ?></div>
                        </div>
                    </div>

                    <div class="nav-drop-divider"></div>

                    <a href="account.php" class="nav-drop-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                        <?= htmlspecialchars(t('my_account')) ?>
                    </a>
                    <a href="orders.php" class="nav-drop-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <path d="M16 10a4 4 0 0 1-8 0" />
                        </svg>
                        <?= htmlspecialchars(t('my_orders')) ?>
                    </a>
                  
                    <a href="wishlist.php" class="nav-drop-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        </svg>
                        <?= htmlspecialchars(t('wishlist')) ?>
                        <?php if ($wishlist_count > 0): ?>
                            <span class="nav-drop-badge"><?= $wishlist_count ?></span>
                        <?php endif; ?>
                    </a>

                    <div class="nav-drop-divider"></div>

                    <!-- Points to the SHARED signout handler -->
                    <a href="/LectroHub/auth/signout.php" class="nav-drop-item nav-drop-danger">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        <?= htmlspecialchars(t('sign_out')) ?>
                    </a>
                </div>
            </div>

            <!-- ── USER: GUEST ── -->
        <?php else: ?>
            <div class="nav-user-dropdown" id="userDropdown">
                <button class="nav-user-btn nav-guest-btn" onclick="toggleUserDrop()"
                    aria-label="Account" aria-expanded="false" aria-haspopup="true">
                    <div class="nav-guest-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </div>
                    <svg class="nav-chevron" width="12" height="12" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M6 9l6 6 6-6" />
                    </svg>
                </button>

                <div class="nav-drop-menu" id="userDropMenu" style="display:none">
                    <div class="nav-drop-header nav-drop-guest-header">
                        <div class="ndh-guest-icon">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.5">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                        </div>
                        <div class="ndh-info">
                            <div class="ndh-name"><?= htmlspecialchars(t('hello_guest')) ?></div>
                            <div class="ndh-email"><?= htmlspecialchars(t('guest_sub')) ?></div>
                        </div>
                    </div>

                    <div class="nav-drop-ctas">

                        <a href="/LectroHub/auth/signin.php" class="nav-drop-cta-primary"><?= htmlspecialchars(t('sign_in')) ?></a>
                        <a href="/LectroHub/auth/signup.php" class="nav-drop-cta-secondary"><?= htmlspecialchars(t('create_account')) ?></a>
                    </div>

                    <div class="nav-drop-divider"></div>

                    <a href="wishlist.php" class="nav-drop-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        </svg>
                        <?= htmlspecialchars(t('wishlist')) ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── HAMBURGER (mobile) ── -->
        <button class="nav-menu-btn" onclick="toggleDrawer()" aria-label="Menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6" />
                <line x1="3" y1="12" x2="21" y2="12" />
                <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
        </button>
    </div>
</nav>

<script>
    function toggleLangDrop() {
        const menu = document.getElementById('langDropMenu');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('langDropdown');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('langDropMenu').style.display = 'none';
        }
    });
</script>