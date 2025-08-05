<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

require_once '../includes/db_connect.php';

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Invalid action.'];

// --- UPDATE ACTION ---
if ($action === 'update_course') {
    $course_id = $_POST['id'] ?? 0;
    $course_name = trim($_POST['name'] ?? '');
    $course_description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] ?? 0;

    if ($course_id > 0 && !empty($course_name) && $category_id > 0) {
        $stmt = $conn->prepare("UPDATE courses SET course_name = ?, course_description = ?, category_id = ? WHERE course_id = ?");
        $stmt->bind_param("ssii", $course_name, $course_description, $category_id, $course_id);
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Course updated successfully.'];
        } else {
            $response['message'] = 'Error updating course.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid data provided. Course name and category are required.';
    }
}

// --- DELETE ACTION ---
if ($action === 'delete_course') {
    $course_id = $_POST['id'] ?? 0;
    if ($course_id > 0) {
        $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->bind_param("i", $course_id);
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Course deleted successfully.'];
        } else {
            $response['message'] = 'Error deleting course.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid ID provided.';
    }
}

$conn->close();
echo json_encode($response);