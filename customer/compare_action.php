<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once "../lectrohub_db/connection.php";
header('Content-Type: application/json');

/* ══════════════════════════════════════════
   COMPARE ACTION — session-based, works for
   guests and logged-in users alike (compare
   lists are inherently short-lived / browsing
   session state, so no DB table needed).

   $_SESSION['compare'] = [product_id, product_id, ...]

   Actions: toggle | remove | clear
══════════════════════════════════════════ */

const COMPARE_MAX = 4;

$action     = $_POST['action'] ?? 'toggle';
$product_id = (int)($_POST['product_id'] ?? 0);

if (!isset($_SESSION['compare']) || !is_array($_SESSION['compare'])) {
    $_SESSION['compare'] = [];
}

/* ── CLEAR ── */
if ($action === 'clear') {
    $_SESSION['compare'] = [];
    echo json_encode(['success' => true, 'compare_count' => 0, 'compare_ids' => []]);
    exit;
}

if ($product_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

/* ── REMOVE ── */
if ($action === 'remove') {
    $_SESSION['compare'] = array_values(
        array_filter($_SESSION['compare'], fn($id) => $id !== $product_id)
    );
    echo json_encode([
        'success'        => true,
        'action'         => 'removed',
        'in_compare'     => false,
        'compare_count'  => count($_SESSION['compare']),
        'compare_ids'    => $_SESSION['compare'],
    ]);
    exit;
}

/* ── TOGGLE ── */
$in_compare = in_array($product_id, $_SESSION['compare']);

if ($in_compare) {
    $_SESSION['compare'] = array_values(
        array_filter($_SESSION['compare'], fn($id) => $id !== $product_id)
    );
    echo json_encode([
        'success'        => true,
        'action'         => 'removed',
        'in_compare'     => false,
        'compare_count'  => count($_SESSION['compare']),
        'compare_ids'    => $_SESSION['compare'],
    ]);
} else {
    if (count($_SESSION['compare']) >= COMPARE_MAX) {
        echo json_encode([
            'success'       => false,
            'message'       => 'You can compare up to ' . COMPARE_MAX . ' products at a time. Remove one first.',
            'compare_count' => count($_SESSION['compare']),
            'compare_ids'   => $_SESSION['compare'],
        ]);
        exit;
    }

    $_SESSION['compare'][] = $product_id;
    echo json_encode([
        'success'        => true,
        'action'         => 'added',
        'in_compare'     => true,
        'compare_count'  => count($_SESSION['compare']),
        'compare_ids'    => $_SESSION['compare'],
    ]);
}