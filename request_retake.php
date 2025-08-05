<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Security check
if (!isLoggedIn()) { redirect('login.php'); }

$user_id = $_SESSION['user_id'];
$enrollment_id = $_GET['id'] ?? 0;

if ($enrollment_id > 0) {
    // Security check: Make sure the logged-in user owns this enrollment
    $stmt_check = $conn->prepare("SELECT user_id FROM user_courses WHERE enrollment_id = ?");
    $stmt_check->bind_param("i", $enrollment_id);
    $stmt_check->execute();
    $owner = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($owner && $owner['user_id'] == $user_id) {
        // User is authorized, so set the retake_request flag
        $stmt_request = $conn->prepare("UPDATE user_courses SET retake_request = 1 WHERE enrollment_id = ?");
        $stmt_request->bind_param("i", $enrollment_id);
        $stmt_request->execute();
        $stmt_request->close();
    }
}

redirect('dashboard.php');