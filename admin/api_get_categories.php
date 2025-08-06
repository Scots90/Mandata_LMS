<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/functions.php';

// --- FIX: Use the safe, role-aware functions for security check ---
if (!isLoggedIn() || (!isAdmin() && !isManager())) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
require_once '../includes/db_connect.php';

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

$sql = "SELECT category_id, category_name FROM course_categories";
if ($product_id > 0) {
    $sql .= " WHERE product_id = ?";
}
$sql .= " ORDER BY category_name";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed to prepare.']);
    exit();
}

if ($product_id > 0) {
    $stmt->bind_param('i', $product_id);
}

$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

echo json_encode($categories);
?>