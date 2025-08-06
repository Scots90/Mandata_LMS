<?php
session_start();
header('Content-Type: application/json');
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$sql = "
    SELECT c.course_id, c.course_name, c.course_description
    FROM courses c
    LEFT JOIN course_categories cc ON c.category_id = cc.category_id
    WHERE NOT EXISTS (
        SELECT 1 
        FROM user_courses uc 
        WHERE uc.course_id = c.course_id AND uc.user_id = ? AND uc.is_active = 1
    )
";

$params = [$user_id];
$types = 'i';

if ($product_id > 0) {
    $sql .= " AND cc.product_id = ?";
    $params[] = $product_id;
    $types .= 'i';
}
if ($category_id > 0) {
    $sql .= " AND c.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

$sql .= " ORDER BY c.course_name ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed to prepare.']);
    exit();
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$available_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

echo json_encode($available_courses);