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

if ($action === 'update_category') {
    $category_id = $_POST['id'] ?? 0;
    $category_name = trim($_POST['name'] ?? '');
    $product_id = $_POST['product_id'] ?? 0;

    if ($category_id > 0 && !empty($category_name) && $product_id > 0) {
        $stmt = $conn->prepare("UPDATE course_categories SET category_name = ?, product_id = ? WHERE category_id = ?");
        $stmt->bind_param("sii", $category_name, $product_id, $category_id);
        if ($stmt->execute()) {
            $response = ['status' => 'success'];
        } else {
            $response['message'] = 'Error updating category.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid data provided.';
    }
}

if ($action === 'delete_category') {
    $category_id = $_POST['id'] ?? 0;
    if ($category_id > 0) {
        $stmt = $conn->prepare("DELETE FROM course_categories WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        if ($stmt->execute()) {
            $response = ['status' => 'success'];
        } else {
            $response['message'] = 'Error deleting category.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid ID provided.';
    }
}

$conn->close();
echo json_encode($response);
?>