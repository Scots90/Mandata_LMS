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
    // 1. First, get the status of the enrollment
    $stmt_get = $conn->prepare("SELECT status FROM user_courses WHERE enrollment_id = ?");
    $stmt_get->bind_param("i", $enrollment_id);
    $stmt_get->execute();
    $enrollment = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if ($enrollment) {
        $status = $enrollment['status'];

        // 2. Decide which action to take based on the status
        if ($status === 'not_started' || $status === 'in_progress') {
            // If the course was never finished, DELETE the record permanently
            $stmt_action = $conn->prepare("DELETE FROM user_courses WHERE enrollment_id = ?");
        } else {
            // If the course was completed or failed, DEACTIVATE it to preserve history
            $stmt_action = $conn->prepare("UPDATE user_courses SET is_active = 0 WHERE enrollment_id = ?");
        }

        $stmt_action->bind_param("i", $enrollment_id);
        if ($stmt_action->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
        }
        $stmt_action->close();

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Enrollment not found.']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID provided.']);
}

$conn->close();
?>