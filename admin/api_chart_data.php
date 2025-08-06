<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// --- FIX: Use the safe, role-aware functions for security check ---
if (!isLoggedIn() || (!isAdmin() && !isManager())) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$product_id = isset($_GET['product_id']) && !empty($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$category_id = isset($_GET['category_id']) && !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;

// Base conditions for manager filtering
$manager_condition_sql = '';
if (isManager() && !isAdmin()) {
    $manager_id = (int)$_SESSION['user_id'];
    $manager_condition_sql = " AND u.manager_id = $manager_id";
}

// Build dynamic filter conditions for products and categories
$filter_condition_sql = '';
$params = [];
$types = '';
if ($product_id) {
    $filter_condition_sql .= ' AND cc.product_id = ?';
    $params[] = $product_id;
    $types .= 'i';
}
if ($category_id) {
    $filter_condition_sql .= ' AND c.category_id = ?';
    $params[] = $category_id;
    $types .= 'i';
}

$ref_params = [];
if (!empty($params)) {
    $ref_params[] = &$types;
    foreach($params as &$param) {
        $ref_params[] = &$param;
    }
    unset($param);
}

$charts_data = [];

// --- Query 1: Course Performance (Pass/Fail) ---
$sql1 = "SELECT c.course_name, 
            SUM(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as pass_count, 
            SUM(CASE WHEN qa.passed = 0 THEN 1 ELSE 0 END) as fail_count 
         FROM quiz_attempts qa 
         JOIN user_courses uc ON qa.enrollment_id = uc.enrollment_id 
         JOIN users u ON uc.user_id = u.user_id 
         JOIN courses c ON uc.course_id = c.course_id 
         LEFT JOIN course_categories cc ON c.category_id = cc.category_id
         WHERE 1=1 $manager_condition_sql $filter_condition_sql
         GROUP BY c.course_id ORDER BY c.course_name";
$stmt1 = $conn->prepare($sql1);
if (!empty($ref_params)) { call_user_func_array([$stmt1, 'bind_param'], $ref_params); }
$stmt1->execute();
$charts_data['course_performance'] = $stmt1->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt1->close();

// --- Query 2: User Breakdown (Status) ---
$sql2 = "
    SELECT u.username,
        (SELECT COUNT(*) FROM quiz_attempts qa JOIN user_courses uc_inner ON qa.enrollment_id = uc_inner.enrollment_id WHERE uc_inner.user_id = u.user_id AND qa.passed = 1) as completed_count,
        (SELECT COUNT(*) FROM quiz_attempts qa JOIN user_courses uc_inner ON qa.enrollment_id = uc_inner.enrollment_id WHERE uc_inner.user_id = u.user_id AND qa.passed = 0) as failed_count,
        (SELECT COUNT(*) FROM user_courses uc_inner WHERE uc_inner.user_id = u.user_id AND uc_inner.status = 'in_progress' AND uc_inner.is_active = 1) as in_progress_count,
        (SELECT COUNT(*) FROM user_courses uc_inner WHERE uc_inner.user_id = u.user_id AND uc_inner.status = 'not_started' AND uc_inner.is_active = 1) as not_started_count
    FROM users u
    JOIN user_roles ur ON u.user_id = ur.user_id
    WHERE ur.role_id = 3 $manager_condition_sql
    GROUP BY u.user_id
    ORDER BY u.username";
$stmt2 = $conn->prepare($sql2);
$stmt2->execute();
$charts_data['user_breakdown'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// --- Query 3: Average Score ---
$sql3 = "SELECT c.course_name, AVG(qa.score) as average_score 
         FROM quiz_attempts qa 
         JOIN user_courses uc ON qa.enrollment_id = uc.enrollment_id
         JOIN users u ON uc.user_id = u.user_id
         JOIN courses c ON uc.course_id = c.course_id 
         LEFT JOIN course_categories cc ON c.category_id = cc.category_id
         WHERE 1=1 $manager_condition_sql $filter_condition_sql
         GROUP BY c.course_id ORDER BY c.course_name";
$stmt3 = $conn->prepare($sql3);
if (!empty($ref_params)) { call_user_func_array([$stmt3, 'bind_param'], $ref_params); }
$stmt3->execute();
$charts_data['average_score'] = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt3->close();

// --- Query 4: Question Breakdown ---
$sql4 = "SELECT co.title as question_title, c.course_name, 
        SUM(CASE WHEN uqa.submitted_answer = JSON_UNQUOTE(JSON_EXTRACT(co.content_data, '$.correct_answer')) THEN 1 ELSE 0 END) as correct_count, 
        SUM(CASE WHEN uqa.submitted_answer != JSON_UNQUOTE(JSON_EXTRACT(co.content_data, '$.correct_answer')) THEN 1 ELSE 0 END) as incorrect_count 
        FROM user_quiz_answers uqa 
        JOIN quiz_attempts qa ON uqa.attempt_id = qa.attempt_id
        JOIN user_courses uc ON qa.enrollment_id = uc.enrollment_id
        JOIN users u ON uc.user_id = u.user_id
        JOIN course_content co ON uqa.question_id = co.content_id 
        JOIN courses c ON co.course_id = c.course_id
        LEFT JOIN course_categories cc ON c.category_id = cc.category_id
        WHERE co.content_type IN ('quiz_inline', 'quiz_final') $manager_condition_sql $filter_condition_sql
        GROUP BY co.content_id ORDER BY c.course_name, co.content_order";
$stmt4 = $conn->prepare($sql4);
if (!empty($ref_params)) { call_user_func_array([$stmt4, 'bind_param'], $ref_params); }
$stmt4->execute();
$charts_data['question_breakdown'] = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt4->close();

// --- Query 5 & 6 (On-Time vs Late) ---
$on_time_base_sql = "FROM user_courses uc JOIN users u ON uc.user_id = u.user_id JOIN courses c ON uc.course_id = c.course_id LEFT JOIN course_categories cc ON c.category_id = cc.category_id WHERE uc.status = 'completed' AND uc.deadline IS NOT NULL $manager_condition_sql $filter_condition_sql";

$sql5 = "SELECT c.course_name, SUM(CASE WHEN DATE(uc.completion_date) <= uc.deadline THEN 1 ELSE 0 END) as on_time_count, SUM(CASE WHEN DATE(uc.completion_date) > uc.deadline THEN 1 ELSE 0 END) as late_count $on_time_base_sql GROUP BY c.course_id ORDER BY c.course_name";
$stmt5 = $conn->prepare($sql5);
if (!empty($ref_params)) { call_user_func_array([$stmt5, 'bind_param'], $ref_params); }
$stmt5->execute();
$charts_data['on_time_course'] = $stmt5->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt5->close();

$sql6 = "SELECT u.username, SUM(CASE WHEN DATE(uc.completion_date) <= uc.deadline THEN 1 ELSE 0 END) as on_time_count, SUM(CASE WHEN DATE(uc.completion_date) > uc.deadline THEN 1 ELSE 0 END) as late_count $on_time_base_sql GROUP BY u.user_id ORDER BY u.username";
$stmt6 = $conn->prepare($sql6);
if (!empty($ref_params)) { call_user_func_array([$stmt6, 'bind_param'], $ref_params); }
$stmt6->execute();
$charts_data['on_time_user'] = $stmt6->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt6->close();

echo json_encode($charts_data);
$conn->close();
?>