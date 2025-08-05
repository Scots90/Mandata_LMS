<?php
session_start();

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // If not an admin, redirect to login.
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db_connect.php';

// Check if the enrollment_id is provided in the URL
if (isset($_GET['enrollment_id']) && is_numeric($_GET['enrollment_id'])) {
    
    $enrollment_id = (int)$_GET['enrollment_id'];
    
    // Prepare and execute the update statement
    $stmt = $conn->prepare("UPDATE user_courses SET signed_off = 1 WHERE enrollment_id = ?");
    $stmt->bind_param("i", $enrollment_id);
    
    // Execute the query
    $stmt->execute();
    
    $stmt->close();
    $conn->close();
}

// Redirect the admin back to the dashboard to see the change
header("Location: index.php");
exit();