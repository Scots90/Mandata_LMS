<?php
session_start();
header('Content-Type: application/json');

// Security check: Must be a logged-in student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Base query to find courses the user is NOT enrolled in
$sql = "
    SELECT c.course_id, c.course_name, c.course_description
    FROM courses c
    LEFT JOIN user_courses uc ON c.course_id = uc.course_id AND uc.user_id = ?
    WHERE uc.enrollment_id IS NULL
";

$params = [$user_id];
$types = 'i';

// Add the category filter if a category is selected
if ($category_id > 0) {
    $sql .= " AND c.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

$sql .= " ORDER BY c.course_name ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database query failed to prepare.']);
    exit();
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$available_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

echo json_encode($available_courses);