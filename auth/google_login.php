<?php
/*
 * google_login.php — kicks off the Google OAuth flow
 * Linked from both signin.php and signup.php (same flow either way —
 * Google login always finds-or-creates the account in google_callback.php)
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require '../vendor/autoload.php';
$oauth_cfg = require __DIR__ . '/google_oauth_config.php';

$client = new Google\Client();
$client->setClientId($oauth_cfg['client_id']);
$client->setClientSecret($oauth_cfg['client_secret']);
$client->setRedirectUri($oauth_cfg['redirect_uri']);
$client->addScope('email');
$client->addScope('profile');

/* ── CSRF protection: verified against session in google_callback.php ── */
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$client->setState($state);

/* ── Carry the post-login redirect target through the OAuth round trip ── */
$redirect = $_GET['redirect'] ?? '';
if ($redirect === 'cart') {
    $_SESSION['google_oauth_redirect'] = $redirect;
} else {
    unset($_SESSION['google_oauth_redirect']);
}

header('Location: ' . $client->createAuthUrl());
exit;