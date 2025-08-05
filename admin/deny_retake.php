<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

$enrollment_id = $_GET['id'] ?? 0;

if ($enrollment_id > 0) {
    // This simply sets the request flag back to 0, canceling the request.
    $stmt = $conn->prepare("UPDATE user_courses SET retake_request = 0 WHERE enrollment_id = ?");
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: index.php");
exit();
?>