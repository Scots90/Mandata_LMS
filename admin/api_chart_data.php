<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
require_once '../includes/db_connect.php';

$product_id = isset($_GET['product_id']) && !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$category_id = isset($_GET['category_id']) && !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;

// This array will hold our dynamic WHERE conditions for different tables
$filter_conditions = [];
$params = [];
$types = '';

if ($product_id) {
    $filter_conditions[] = 'cc.product_id = ?';
    $params[] = $product_id;
    $types .= 'i';
}
if ($category_id) {
    $filter_conditions[] = 'c.category_id = ?';
    $params[] = $category_id;
    $types .= 'i';
}

// Function to safely add WHERE or AND to a query
function add_where_clause($sql, $conditions) {
    if (!empty($conditions)) {
        if (strpos(strtoupper($sql), 'WHERE') === false) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        } else {
            $sql .= ' AND ' . implode(' AND ', $conditions);
        }
    }
    return $sql;
}

$charts_data = [];

// --- Query 1: Course Performance (Pass/Fail) ---
$sql1 = "SELECT c.course_name, SUM(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as pass_count, SUM(CASE WHEN qa.passed = 0 THEN 1 ELSE 0 END) as fail_count FROM quiz_attempts qa JOIN user_courses uc ON qa.enrollment_id = uc.enrollment_id JOIN courses c ON uc.course_id = c.course_id LEFT JOIN course_categories cc ON c.category_id = cc.category_id";
$sql1 = add_where_clause($sql1, $filter_conditions);
$sql1 .= " GROUP BY c.course_id ORDER BY c.course_name";
$stmt1 = $conn->prepare($sql1);
if (!empty($params)) { $stmt1->bind_param($types, ...$params); }
$stmt1->execute();
$charts_data['course_performance'] = $stmt1->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt1->close();

// --- Query 2: User Breakdown (Status) ---
$sql2 = "SELECT u.username, SUM(CASE WHEN uc.status = 'completed' THEN 1 ELSE 0 END) as completed_count, SUM(CASE WHEN uc.status = 'failed' THEN 1 ELSE 0 END) as failed_count, SUM(CASE WHEN uc.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count, SUM(CASE WHEN uc.status = 'not_started' THEN 1 ELSE 0 END) as not_started_count FROM users u LEFT JOIN user_courses uc ON u.user_id = uc.user_id LEFT JOIN courses c ON uc.course_id = c.course_id LEFT JOIN course_categories cc ON c.category_id = cc.category_id WHERE u.role = 'student'";
$sql2 = add_where_clause($sql2, $filter_conditions);
$sql2 .= " GROUP BY u.user_id HAVING SUM(CASE WHEN uc.status IS NOT NULL THEN 1 ELSE 0 END) > 0 ORDER BY u.username";
$stmt2 = $conn->prepare($sql2);
if (!empty($params)) { $stmt2->bind_param($types, ...$params); }
$stmt2->execute();
$charts_data['user_breakdown'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// --- Query 3: Average Score ---
$sql3 = "SELECT c.course_name, AVG(qa.score) as average_score FROM quiz_attempts qa JOIN user_courses uc ON qa.enrollment_id = uc.enrollment_id JOIN courses c ON uc.course_id = c.course_id LEFT JOIN course_categories cc ON c.category_id = cc.category_id";
$sql3 = add_where_clause($sql3, $filter_conditions);
$sql3 .= " GROUP BY c.course_id ORDER BY c.course_name";
$stmt3 = $conn->prepare($sql3);
if (!empty($params)) { $stmt3->bind_param($types, ...$params); }
$stmt3->execute();
$charts_data['average_score'] = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt3->close();

// --- Query 4: Question Breakdown ---
$sql4 = "SELECT cc.title as question_title, c.course_name, SUM(CASE WHEN uqa.submitted_answer = JSON_UNQUOTE(JSON_EXTRACT(cc.content_data, '$.correct_answer')) THEN 1 ELSE 0 END) as correct_count, SUM(CASE WHEN uqa.submitted_answer != JSON_UNQUOTE(JSON_EXTRACT(cc.content_data, '$.correct_answer')) THEN 1 ELSE 0 END) as incorrect_count FROM user_quiz_answers uqa JOIN course_content cc ON uqa.question_id = cc.content_id JOIN courses c ON cc.course_id = c.course_id";
$sql4 = add_where_clause($sql4, $filter_conditions);
$sql4 .= " WHERE cc.content_type IN ('quiz_inline', 'quiz_final') GROUP BY cc.content_id ORDER BY c.course_name, cc.content_order";
$stmt4 = $conn->prepare($sql4);
if (!empty($params)) { $stmt4->bind_param($types, ...$params); }
$stmt4->execute();
$charts_data['question_breakdown'] = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt4->close();

// --- Query 5: On-Time vs Late by Course ---
$sql5 = "SELECT c.course_name, SUM(CASE WHEN uc.completion_date <= uc.deadline THEN 1 ELSE 0 END) as on_time_count, SUM(CASE WHEN uc.completion_date > uc.deadline THEN 1 ELSE 0 END) as late_count FROM user_courses uc JOIN courses c ON uc.course_id = c.course_id LEFT JOIN course_categories cc ON c.category_id = cc.category_id WHERE uc.status = 'completed' AND uc.deadline IS NOT NULL";
$sql5 = add_where_clause($sql5, $filter_conditions);
$sql5 .= " GROUP BY c.course_id ORDER BY c.course_name";
$stmt5 = $conn->prepare($sql5);
if (!empty($params)) { $stmt5->bind_param($types, ...$params); }
$stmt5->execute();
$charts_data['on_time_course'] = $stmt5->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt5->close();

// --- Query 6: On-Time vs Late by User ---
$sql6 = "SELECT u.username, SUM(CASE WHEN uc.completion_date <= uc.deadline THEN 1 ELSE 0 END) as on_time_count, SUM(CASE WHEN uc.completion_date > uc.deadline THEN 1 ELSE 0 END) as late_count FROM user_courses uc JOIN users u ON uc.user_id = u.user_id JOIN courses c ON uc.course_id = c.course_id LEFT JOIN course_categories cc ON c.category_id = cc.category_id WHERE uc.status = 'completed' AND uc.deadline IS NOT NULL";
$sql6 = add_where_clause($sql6, $filter_conditions);
$sql6 .= " GROUP BY u.user_id ORDER BY u.username";
$stmt6 = $conn->prepare($sql6);
if (!empty($params)) { $stmt6->bind_param($types, ...$params); }
$stmt6->execute();
$charts_data['on_time_user'] = $stmt6->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt6->close();

echo json_encode($charts_data);
$conn->close();
?>