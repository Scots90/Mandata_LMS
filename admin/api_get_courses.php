<?php
session_start();
header('Content-Type: application/json');

// Security check to ensure an admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
require_once '../includes/db_connect.php';

// Get the category ID from the GET request, default to 0 if not set
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Start building the base SQL query
$sql = "SELECT course_id, course_name FROM courses";

// If a valid category ID is provided, add a WHERE clause to filter
if ($category_id > 0) {
    $sql .= " WHERE category_id = ?";
}

// Always order the results by course name
$sql .= " ORDER BY course_name";

$stmt = $conn->prepare($sql);

// If we have a category ID, bind it to the prepared statement to prevent SQL injection
if ($category_id > 0) {
    $stmt->bind_param("i", $category_id);
}

$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Output the array of courses as a JSON object
echo json_encode($courses);