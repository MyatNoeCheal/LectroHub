<?php
/* ══════════════════════════════════════════
   ADMIN REVIEW ACTION
   Save as: C:\xampp\htdocs\LectroHub\admin\review_action.php

   Admin-only. Deliberately supports ONLY 'delete' — there is no
   'edit' action. Admins should not be able to alter what a
   customer actually wrote; the only moderation lever here is
   removal. (Mirrors the customer-side review_action.php, which
   likewise only ever supports 'submit' and 'delete'.)
══════════════════════════════════════════ */

require_once "auth_check.php";
require_once "../lectrohub_db/connection.php";
if (!isset($_SESSION)) session_start();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action !== 'delete') {
    echo json_encode(['success' => false, 'message' => 'Unsupported action.']);
    exit;
}

$review_id = (int)($_POST['review_id'] ?? 0);

if ($review_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid review.']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM reviews WHERE id = ?");
$stmt->execute([$review_id]);

if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode(['success' => false, 'message' => 'Review not found.']);
    exit;
}

$conn->prepare("DELETE FROM reviews WHERE id = ?")->execute([$review_id]);

echo json_encode(['success' => true, 'message' => 'Review deleted.']);