<?php

if (session_status() === PHP_SESSION_NONE) session_start();

$role = $_SESSION['user']['role'] ?? 'user';

session_unset();
session_destroy();

if (isset($_COOKIE['lh_email'])) {
    setcookie('lh_email', '', time() - 3600, '/', '', true, true);
}

header("Location: /LectroHub/auth/signin.php");
exit;