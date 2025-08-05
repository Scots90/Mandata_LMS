<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) { redirect('login.php'); }

$user_id = $_SESSION['user_id'];
$enrollment_id = $_GET['id'] ?? 0;

if ($enrollment_id > 0) {
    // 1. Get course_id and verify the user owns this enrollment
    $stmt_get = $conn->prepare("SELECT user_id, course_id FROM user_courses WHERE enrollment_id = ?");
    $stmt_get->bind_param("i", $enrollment_id);
    $stmt_get->execute();
    $enrollment_data = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if ($enrollment_data && $enrollment_data['user_id'] == $user_id) {
        // 2. Deactivate the old enrollment record
        $stmt_deactivate = $conn->prepare("UPDATE user_courses SET is_active = 0, retake_request = 0 WHERE enrollment_id = ?");
        $stmt_deactivate->bind_param("i", $enrollment_id);
        $stmt_deactivate->execute();
        $stmt_deactivate->close();

        // 3. Create a new, active enrollment record
        $stmt_insert = $conn->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (?, ?)");
        $stmt_insert->bind_param("ii", $user_id, $enrollment_data['course_id']);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
}

redirect('dashboard.php');
?>