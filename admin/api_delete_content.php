<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}
require_once '../includes/db_connect.php';

$content_id = $_POST['id'] ?? 0;
if (!$content_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
    exit();
}

// First, get the content type and data to check for associated files
$stmt_get = $conn->prepare("SELECT content_type, content_data FROM course_content WHERE content_id = ?");
$stmt_get->bind_param("i", $content_id);
$stmt_get->execute();
$content = $stmt_get->get_result()->fetch_assoc();
$stmt_get->close();

if ($content) {
    // Delete associated files from the server
    if ($content['content_type'] === 'video') {
        $file_path = '../uploads/videos/' . $content['content_data'];
        if (file_exists($file_path)) { unlink($file_path); }
    } elseif ($content['content_type'] === 'image') {
        $file_path = '../uploads/images/' . $content['content_data'];
        if (file_exists($file_path)) { unlink($file_path); }
    } elseif ($content['content_type'] === 'quiz_inline' || $content['content_type'] === 'quiz_final') {
        $quiz_data = json_decode($content['content_data'], true);
        if (!empty($quiz_data['image_path'])) {
            $file_path = '../uploads/quiz_images/' . $quiz_data['image_path'];
            if (file_exists($file_path)) { unlink($file_path); }
        }
    }

    // Now, delete the record from the database
    $stmt_delete = $conn->prepare("DELETE FROM course_content WHERE content_id = ?");
    $stmt_delete->bind_param("i", $content_id);
    if ($stmt_delete->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Content deleted.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    $stmt_delete->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Content not found.']);
}

$conn->close();