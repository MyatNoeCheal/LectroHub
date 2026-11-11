<?php
/*
 * auth.php — Unified login handler (admin & customer)
 * Place in:  /LectroHub/auth/auth.php
 * Called by: signin.php (form action="auth.php")
 */
session_start();
require_once "../lectrohub_db/connection.php";

$email    = trim($_POST['email']    ?? '');
$password =      $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: signin.php?error=missing");
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    /* User registered with Google (no password set) */
if ($user && $user['password'] === null) {
    header("Location: signin.php?error=use_google");
    exit;
}

    if ($user && password_verify($password, $user['password'])) {

        /* Save everything BEFORE regenerating — some configs wipe $_SESSION */
        $guest_cart     = $_SESSION['cart']     ?? [];
        $guest_wishlist = $_SESSION['wishlist'] ?? [];

        /* Regenerate session ID (old one is destroyed; session data is kept) */
        session_regenerate_id(true);

        /* Store core user data */
        $_SESSION['user'] = [
            'id'        => $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ];

        /* ── Merge guest cart into session ── */
        foreach ($guest_cart as $product_id => $qty) {
            $existing = (int)($_SESSION['cart'][$product_id] ?? 0);
            $_SESSION['cart'][$product_id] = $existing + (int)$qty;
        }

        /* ── Merge guest wishlist into session ── */
        $existing_wishlist = $_SESSION['wishlist'] ?? [];
        $_SESSION['wishlist'] = array_values(
            array_unique(array_merge($existing_wishlist, $guest_wishlist))
        );

        /* ── Sync guest wishlist into DB for logged-in user ── */
        if (!empty($guest_wishlist) && $user['role'] === 'user') {
            $wl_ins = $conn->prepare(
                "INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)"
            );
            foreach ($guest_wishlist as $product_id) {
                $wl_ins->execute([$user['id'], $product_id]);
            }
        }

        /* ── Route by role ── */
        if ($user['role'] === 'admin') {
            header("Location: /LectroHub/admin/index.php");
        } else {
            /* $_GET holds query-string params even on POST requests */
            $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
            if ($redirect === 'cart') {
                header("Location: /LectroHub/customer/cart.php");
            } else {
                header("Location: /LectroHub/customer/index.php");
            }
        }
        exit;

    } else {
        header("Location: signin.php?error=invalid");
        exit;
    }

} catch (PDOException $e) {
    error_log("auth.php PDO error: " . $e->getMessage());
    header("Location: signin.php?error=server");
    exit;
}