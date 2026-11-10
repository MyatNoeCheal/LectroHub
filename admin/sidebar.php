<?php

require_once "auth_check.php";


$username = $_SESSION['user']['full_name'] ?? 'Admin';
$email    = $email    ?? ($_SESSION['user']['email']     ?? '');
$initials = $initials ?? strtoupper(substr($username, 0, 1));

require_once "../lectrohub_db/connection.php";

$order_count = 0;

try {
    $stmt = $conn->query("SELECT COUNT(*) FROM orders");
    $order_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $order_count = 0;
}

?>

<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sb-brand">
        <a href="index.php" class="sb-logo">Lectro<span>Hub</span></a>
        
    </div>

    <!-- Logged-in user -->
    <div class="sb-user">
        <div class="sb-avatar"><?= htmlspecialchars($initials) ?></div>
        <div>
            <div class="sb-uname"><?= htmlspecialchars($username) ?></div>
            <div class="sb-urole">Administrator</div>
        </div>
    </div>

    <!-- Nav -->
    <div class="sb-section">
        <span class="sb-section-lbl">Main</span>
        <ul class="sb-nav">
            <li>
                <a href="index.php" class="sb-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                    <svg class="si" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" />
                        <rect x="14" y="3" width="7" height="7" />
                        <rect x="3" y="14" width="7" height="7" />
                        <rect x="14" y="14" width="7" height="7" />
                    </svg>
                    <span class="sl">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="orders.php" class="sb-link <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>">
                    <svg class="si" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                        <line x1="3" y1="6" x2="21" y2="6" />
                        <path d="M16 10a4 4 0 0 1-8 0" />
                    </svg>
                    <span class="sl">Orders</span>
                    <span class="sb-badge2"><?= $order_count ?></span>
                </a>
            </li>
            <li>
                <a href="products.php" class="sb-link <?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">
                    <svg class="si" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                    </svg>
                    <span class="sl">Products</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="sb-link <?= basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : '' ?>">
                    <svg class="si" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <span class="sl">Users</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sb-section">
        <span class="sb-section-lbl">Catalogue</span>
        <ul class="sb-nav">
            <li>
                <a href="categories.php" class="sb-link <?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : '' ?>">
                    <svg class="si" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
                    </svg>
                    <span class="sl">Categories</span>
                </a>
            </li>
            <li>
                <a href="coupons.php" class="sb-link <?= basename($_SERVER['PHP_SELF']) === 'coupons.php' ? 'active' : '' ?>">
                    <svg class="si" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                        <line x1="7" y1="7" x2="7.01" y2="7" />
                    </svg>
                    <span class="sl">Coupons</span>
                </a>
            </li>
            <li>
                <a href="shipping_zones.php" class="sb-link <?= basename($_SERVER['PHP_SELF']) === 'shipping_zones.php' ? 'active' : '' ?>">
                    <svg class="si" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg>
                    <span class="sl">Shipping Zones</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sb-section">
        <span class="sb-section-lbl">Reports</span>
        <ul class="sb-nav">
            <li>
                <a href="reviews.php" class="sb-link <?= basename($_SERVER['PHP_SELF']) === 'reviews.php' ? 'active' : '' ?>">
                    <svg class="si" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                    </svg>
                    <span class="sl">Reviews</span>
                    <span class="sb-badge2 g">12</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sb-section">
        <span class="sb-section-lbl">System</span>
        <ul class="sb-nav">
            <li>
                <a href="../admin/index.php" class="sb-link" target="_blank">
                    <svg class="si" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                        <polyline points="15 3 21 3 21 9" />
                        <line x1="10" y1="14" x2="21" y2="3" />
                    </svg>
                    <span class="sl">View Store</span>
                </a>
            </li>
        </ul>
    </div>


</aside>