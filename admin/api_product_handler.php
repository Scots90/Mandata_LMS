<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// --- FIX: Use the correct, safe function for the security check ---
if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Invalid action.'];

if ($action === 'update_product') {
    $product_id = $_POST['id'] ?? 0;
    $product_name = trim($_POST['name'] ?? '');
    if ($product_id > 0 && !empty($product_name)) {
        $stmt = $conn->prepare("UPDATE products SET product_name = ? WHERE product_id = ?");
        $stmt->bind_param("si", $product_name, $product_id);
        if ($stmt->execute()) {
            $response = ['status' => 'success'];
        } else {
            $response['message'] = 'Error updating product.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid data provided.';
    }
}

if ($action === 'delete_product') {
    $product_id = $_POST['id'] ?? 0;
    if ($product_id > 0) {
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            $response = ['status' => 'success'];
        } else {
            $response['message'] = 'Error deleting product.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid ID provided.';
    }
}

$conn->close();
echo json_encode($response);
?>