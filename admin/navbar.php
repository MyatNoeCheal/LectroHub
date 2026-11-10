<?php

require_once "auth_check.php";

/* NOTE: session/auth logic lives in auth_check.php (it must run
   before any HTML output — sidebar.php already prints markup
   before this file loads, which caused a "headers already sent"
   warning). */

$username  = $username  ?? ($_SESSION['user']['full_name'] ?? 'Admin');
$initials  = $initials  ?? strtoupper(substr($username, 0, 1));
$nav_title = $nav_title ?? 'Dashboard';
$nav_sub   = $nav_sub   ?? 'Overview';

$notifs = [
    ['icon' => '📦', 'title' => 'New Order #ORD-8821', 'desc' => 'Alex Johnson placed an order for iPhone 16 Pro Max', 'time' => '2 min ago', 'color' => 'rgba(0,229,255,.1)'],
    ['icon' => '⚠️', 'title' => 'Low Stock Alert',    'desc' => 'MacBook Pro M3 has only 12 units left',             'time' => '18 min ago','color' => 'rgba(245,158,11,.1)'],
    ['icon' => '⭐', 'title' => 'New Review Posted',  'desc' => 'Samsung Galaxy S25 Ultra received a 5-star review', 'time' => '1 hr ago',  'color' => 'rgba(167,139,250,.1)'],
    ['icon' => '💳', 'title' => 'Payment Received',   'desc' => '$4,280 payment cleared for order #ORD-8819',        'time' => '3 hr ago',  'color' => 'rgba(34,197,94,.1)'],
];
?>

<nav class="navbar">
    <button class="nav-toggle" id="navToggle" onclick="toggleSb()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
    </button>

    <div class="nav-title"><?= htmlspecialchars($nav_title) ?> <span>/ <?= htmlspecialchars($nav_sub) ?></span></div>
    <div class="nav-spacer"></div>

    <!-- Search -->
    <div class="nav-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
        </svg>
        <input type="text" placeholder="Search…" id="navSearchInput">
    </div>

    <div class="nav-actions">

        <button type="button" class="nav-icon-btn theme-toggle-btn" onclick="lhToggleTheme()"
                title="Toggle light / dark theme" aria-label="Toggle light / dark theme" aria-pressed="false">
            <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="5"/>
                <line x1="12" y1="1" x2="12" y2="3"/>
                <line x1="12" y1="21" x2="12" y2="23"/>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                <line x1="1" y1="12" x2="3" y2="12"/>
                <line x1="21" y1="12" x2="23" y2="12"/>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
            <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
        </button>

        <!-- Notifications -->
        <div class="nav-dropdown" id="notifDrop">
            <div class="nav-icon-btn" onclick="toggleDrop('notifDrop')" title="Notifications">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                <span class="notif-dot"></span>
            </div>
            <div class="nav-dd-menu" style="min-width:300px">
                <div style="padding:13px 16px 10px;border-bottom:1px solid var(--bd);display:flex;justify-content:space-between;align-items:center">
                    <span style="font-family:var(--fd);font-size:13px;font-weight:700">Notifications</span>
                    <span style="font-size:11px;color:var(--ac);cursor:pointer">Mark all read</span>
                </div>
                <?php foreach ($notifs as $n): ?>
                    <div class="dd-item" style="gap:12px;border-bottom:1px solid var(--bd)">
                        <div style="width:34px;height:34px;border-radius:9px;background:<?= $n['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0"><?= $n['icon'] ?></div>
                        <div style="flex:1">
                            <div style="font-size:12px;font-weight:600;color:var(--txt);margin-bottom:2px"><?= htmlspecialchars($n['title']) ?></div>
                            <div style="font-size:11px;color:var(--muted);line-height:1.4"><?= htmlspecialchars($n['desc']) ?></div>
                            <div style="font-size:10px;color:var(--dim);margin-top:3px;font-family:var(--fm)"><?= $n['time'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="padding:10px 16px;text-align:center;font-size:12px;color:var(--ac);cursor:pointer">View all notifications</div>
            </div>
        </div>

        <!-- Messages -->
        <div class="nav-icon-btn" title="Messages" onclick="showToast('Messages panel coming soon','💬')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
        </div>

        <!-- User dropdown -->
        <div class="nav-dropdown" id="userDrop">
            <div class="nav-user" onclick="toggleDrop('userDrop')">
                <div class="nav-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="nav-user-info">
                    <div class="nav-uname"><?= htmlspecialchars($username) ?></div>
                    <div class="nav-urole">Administrator</div>
                </div>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--dim);margin-left:4px">
                    <path d="M6 9l6 6 6-6" />
                </svg>
            </div>
            <div class="nav-dd-menu">
                <a href="../admin/index.php" class="dd-item" target="_blank">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                        <polyline points="15 3 21 3 21 9" />
                        <line x1="10" y1="14" x2="21" y2="3" />
                    </svg>
                    View Store
                </a>
                <div class="dd-divider"></div>
                <a href="/LectroHub/auth/signout.php" class="dd-item danger">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                    Sign Out
                </a>
            </div>
        </div>

    </div>
</nav>