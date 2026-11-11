<?php
/*
 * google_callback.php — Google OAuth callback
 * Finds or creates the user, logs them in, merges any guest
 * cart/wishlist, then redirects by role — mirrors auth.php.
 */
session_start();

require '../vendor/autoload.php';
require_once "../lectrohub_db/connection.php";
$oauth_cfg = require __DIR__ . '/google_oauth_config.php';

/* ── CSRF: state must match what google_login.php issued ── */
$state = $_GET['state'] ?? '';
if (
    empty($state) ||
    empty($_SESSION['google_oauth_state']) ||
    !hash_equals($_SESSION['google_oauth_state'], $state)
) {
    unset($_SESSION['google_oauth_state']);
    header("Location: signin.php?error=oauth_state");
    exit;
}
unset($_SESSION['google_oauth_state']);

/* ── User denied access, or Google sent no code ── */
if (!empty($_GET['error']) || empty($_GET['code'])) {
    header("Location: signin.php?error=oauth_cancelled");
    exit;
}

$client = new Google\Client();
$client->setClientId($oauth_cfg['client_id']);
$client->setClientSecret($oauth_cfg['client_secret']);
$client->setRedirectUri($oauth_cfg['redirect_uri']);

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        throw new Exception($token['error_description'] ?? $token['error']);
    }

    $client->setAccessToken($token);

    $oauth       = new Google\Service\Oauth2($client);
    $google_user = $oauth->userinfo->get();

    $email     = $google_user->email;
    $name      = $google_user->name
        ?: trim(($google_user->givenName ?? '') . ' ' . ($google_user->familyName ?? ''));
    $google_id = $google_user->id;

    if (empty($email)) {
        throw new Exception('Google did not return an email address.');
    }
} catch (Exception $e) {
    error_log('google_callback.php OAuth error: ' . $e->getMessage());
    header("Location: signin.php?error=oauth_failed");
    exit;
}

try {
    /* ── Find by google_id first (returning Google user) ── */
    $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? LIMIT 1");
    $stmt->execute([$google_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        /* ── Fall back to email — links Google to an existing password account ── */
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?")
                 ->execute([$google_id, $user['id']]);
            $user['google_id'] = $google_id;
        } else {
            /* ── Brand new account — no password, Google is the only login method ── */
            $stmt = $conn->prepare("
                INSERT INTO users (full_name, email, password, google_id, role)
                VALUES (?, ?, NULL, ?, 'user')
            ");
            $stmt->execute([$name ?: $email, $email, $google_id]);
            $new_id = (int)$conn->lastInsertId();

            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$new_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    /* ── Save guest cart/wishlist BEFORE regenerating (same pattern as auth.php) ── */
    $guest_cart     = $_SESSION['cart']     ?? [];
    $guest_wishlist = $_SESSION['wishlist'] ?? [];
    $post_redirect  = $_SESSION['google_oauth_redirect'] ?? '';
    unset($_SESSION['google_oauth_redirect']);

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'        => $user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ];

    /* ── Merge guest cart ── */
    foreach ($guest_cart as $product_id => $qty) {
        $existing = (int)($_SESSION['cart'][$product_id] ?? 0);
        $_SESSION['cart'][$product_id] = $existing + (int)$qty;
    }

    /* ── Merge guest wishlist ── */
    $existing_wishlist    = $_SESSION['wishlist'] ?? [];
    $_SESSION['wishlist'] = array_values(array_unique(array_merge($existing_wishlist, $guest_wishlist)));

    if (!empty($guest_wishlist) && $user['role'] === 'user') {
        $wl_ins = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
        foreach ($guest_wishlist as $product_id) {
            $wl_ins->execute([$user['id'], $product_id]);
        }
    }

    /* ── Route by role ── */
    if ($user['role'] === 'admin') {
        header("Location: /LectroHub/admin/index.php");
    } else {
        header("Location: " . ($post_redirect === 'cart'
            ? "/LectroHub/customer/cart.php"
            : "/LectroHub/customer/index.php"));
    }
    exit;

} catch (PDOException $e) {
    error_log('google_callback.php DB error: ' . $e->getMessage());
    header("Location: signin.php?error=server");
    exit;
}