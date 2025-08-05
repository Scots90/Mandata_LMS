<?php
session_start();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}
require_once '../includes/db_connect.php';

$enrollment_id = $_POST['id'] ?? 0;

if ($enrollment_id > 0) {
    // We always deactivate the enrollment, never delete it.
    // This preserves the record for historical reports.
    $stmt = $conn->prepare("UPDATE user_courses SET is_active = 0 WHERE enrollment_id = ?");
    $stmt->bind_param("i", $enrollment_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
    }
    $stmt->close();

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID provided.']);
}

$conn->close();
?>