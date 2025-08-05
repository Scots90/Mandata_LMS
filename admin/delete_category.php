<?php
session_start();
// Admin security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db_connect.php';

if (isset($_GET['id'])) {
    $category_id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM course_categories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: categories.php");
exit();
?>