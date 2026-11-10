<?php
require_once "auth_check.php";
require_once "../lectrohub_db/connection.php";
require_once "../lectrohub_db/mailer.php";

$user_id = $_SESSION['user']['id'] ?? null;
if (!$user_id) {
    header('Location: /LectroHub/auth/signin.php');
    exit;
}

$sent = send_order_history_email($conn, $user_id);

$_SESSION['flash'] = $sent
    ? ['type' => 'success', 'text' => 'Your order history has been emailed to you.']
    : ['type' => 'error',   'text' => 'We could not send that email. Please try again.'];

/* Go back to whatever page the link was clicked from; fall back to home */
$back = $_SERVER['HTTP_REFERER'] ?? '/LectroHub/customer/index.php';
header('Location: ' . $back);
exit;