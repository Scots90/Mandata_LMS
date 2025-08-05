<?php
session_start();
header('Content-Type: application/json'); // Set header for JSON response

// Basic security checks
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

require_once '../includes/db_connect.php';

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Invalid action.'];

// --- UPDATE ACTION ---
if ($action === 'update_category') {
    $id = $_POST['id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $product_id = $_POST['product_id'] ?? 0;

    if ($id > 0 && !empty($name) && $product_id > 0) {
        $stmt = $conn->prepare("UPDATE course_categories SET category_name = ?, product_id = ? WHERE category_id = ?");
        $stmt->bind_param("sii", $name, $product_id, $id);
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Category updated successfully.'];
        } else {
            $response['message'] = 'Error updating category.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid data provided.';
    }
}

// --- DELETE ACTION ---
if ($action === 'delete_category') {
    $id = $_POST['id'] ?? 0;

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM course_categories WHERE category_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Category deleted successfully.'];
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