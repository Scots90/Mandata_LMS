<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// --- FIX: Use the correct, safe functions for the security check ---
if (!isLoggedIn() || (!isAdmin() && !isManager())) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$enrollment_id = $_POST['id'] ?? 0;

if ($enrollment_id > 0) {
    
    // Security check for Managers
    if (isManager() && !isAdmin()) {
        $manager_id = $_SESSION['user_id'];
        $stmt_check = $conn->prepare("SELECT uc.enrollment_id FROM user_courses uc JOIN users u ON uc.user_id = u.user_id WHERE uc.enrollment_id = ? AND u.manager_id = ?");
        $stmt_check->bind_param("ii", $enrollment_id, $manager_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
            $stmt_check->close();
            $conn->close();
            exit();
        }
        $stmt_check->close();
    }
    
    // Deactivate the enrollment
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