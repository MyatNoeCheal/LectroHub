<?php
require_once "auth_check.php";

if (!isset($_SESSION)) {
    session_start();
}


require_once "../lectrohub_db/connection.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: products.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: products.php?error=delete_failed');
        exit;
    }
}

if ($action === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];

    // Keep only positive integers
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

    if (empty($ids)) {
        header('Location: products.php');
        exit;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        header('Location: products.php?deleted=' . $stmt->rowCount());
        exit;
    } catch (PDOException $e) {
        header('Location: products.php?error=delete_failed');
        exit;
    }
}

header('Location: products.php');
exit;