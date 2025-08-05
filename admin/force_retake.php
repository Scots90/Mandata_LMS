<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

$enrollment_id = $_GET['id'] ?? 0;

if ($enrollment_id > 0) {
    // 1. Get the user_id and course_id from the old enrollment
    $stmt_get = $conn->prepare("SELECT user_id, course_id FROM user_courses WHERE enrollment_id = ?");
    $stmt_get->bind_param("i", $enrollment_id);
    $stmt_get->execute();
    $enrollment_data = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if ($enrollment_data) {
        // 2. Deactivate the old enrollment record by setting is_active to 0
        $stmt_deactivate = $conn->prepare("UPDATE user_courses SET is_active = 0, retake_request = 0 WHERE enrollment_id = ?");
        $stmt_deactivate->bind_param("i", $enrollment_id);
        $stmt_deactivate->execute();
        $stmt_deactivate->close();

        // 3. Create a new, active enrollment record for the same user and course
        // The database defaults will set its status to 'not_started' and 'is_active' to 1
        $stmt_insert = $conn->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (?, ?)");
        $stmt_insert->bind_param("ii", $enrollment_data['user_id'], $enrollment_data['course_id']);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
}

header("Location: index.php");
exit();
?>