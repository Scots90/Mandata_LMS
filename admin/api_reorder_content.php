<?php
// Add these lines to the very top for better error reporting during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}
require_once '../includes/db_connect.php';

$ordered_ids = $_POST['content'] ?? []; // The 'serialize' function uses the element's name attribute

if (empty($ordered_ids) || !is_array($ordered_ids)) {
    echo json_encode(['status' => 'error', 'message' => 'No order data received.']);
    exit();
}

// Loop through the received IDs and update their order in the database
$stmt = $conn->prepare("UPDATE course_content SET content_order = ? WHERE content_id = ?");

foreach ($ordered_ids as $index => $content_id) {
    $order = $index + 1; // Order is 1-based

    if ($content_id) {
        $stmt->bind_param("ii", $order, $content_id);
        $stmt->execute();
    }
}

$stmt->close();
$conn->close();

echo json_encode(['status' => 'success', 'message' => 'Order updated successfully.']);