<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header("Location: ../login.php");
    exit();
}

$enrollment_id = $_GET['enrollment_id'] ?? 0;

if ($enrollment_id > 0) {
    if (isManager()) {
        $manager_id = $_SESSION['user_id'];
        $stmt_check = $conn->prepare("SELECT uc.enrollment_id FROM user_courses uc JOIN users u ON uc.user_id = u.user_id WHERE uc.enrollment_id = ? AND u.manager_id = ?");
        $stmt_check->bind_param("ii", $enrollment_id, $manager_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        if ($result->num_rows === 0) {
            header("Location: index.php");
            exit();
        }
        $stmt_check->close();
    }
    
    $stmt = $conn->prepare("UPDATE user_courses SET signed_off = 1, is_active = 0 WHERE enrollment_id = ?");
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header("Location: index.php");
exit();
?>