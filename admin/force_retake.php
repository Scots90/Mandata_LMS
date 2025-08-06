<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Security check for Admin or Manager roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header("Location: ../login.php");
    exit();
}

$enrollment_id = $_GET['id'] ?? 0;

if ($enrollment_id > 0) {
    // 1. Get the user_id from the enrollment for the manager security check
    $stmt_get = $conn->prepare("SELECT user_id FROM user_courses WHERE enrollment_id = ?");
    $stmt_get->bind_param("i", $enrollment_id);
    $stmt_get->execute();
    $enrollment_data = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if ($enrollment_data) {
        // 2. Security check for Managers to ensure they own the user
        if (isManager()) {
            $user_to_modify = $enrollment_data['user_id'];
            $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND manager_id = ?");
            $stmt_check->bind_param("ii", $user_to_modify, $_SESSION['user_id']);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows === 0) {
                header("Location: index.php"); // Not their user, redirect
                exit();
            }
            $stmt_check->close();
        }

        // --- FIX: This is the new logic ---
        // 3. Reset the existing enrollment record for a retake instead of creating a new one.
        // This preserves the history of attempts under the same enrollment_id.
        $stmt_reset = $conn->prepare("
            UPDATE user_courses 
            SET 
                status = 'not_started', 
                score = NULL, 
                completion_date = NULL, 
                signed_off = 0, 
                retake_request = 0,
                last_viewed_page = 1
            WHERE enrollment_id = ?
        ");
        $stmt_reset->bind_param("i", $enrollment_id);
        $stmt_reset->execute();
        $stmt_reset->close();
    }
}

header("Location: index.php");
exit();
?>