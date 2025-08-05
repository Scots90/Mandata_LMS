<?php
session_start();
header('Content-Type: application/json');

// Security checks
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

require_once '../includes/db_connect.php';

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Invalid action.'];

// --- UPDATE ACTION ---
if ($action === 'update_user') {
    $user_id = $_POST['id'] ?? 0;
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'student';

    if ($user_id > 0 && !empty($username) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Prevent admin from editing their own role to student
        if ($user_id == $_SESSION['user_id'] && $role === 'student') {
             $response['message'] = 'Error: You cannot change your own role.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $username, $email, $role, $user_id);
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'User updated successfully.'];
            } else {
                $response['message'] = 'Error: Username or email may already be in use.';
            }
            $stmt->close();
        }
    } else {
        $response['message'] = 'Invalid data provided.';
    }
}

// --- DELETE ACTION ---
if ($action === 'delete_user') {
    $user_id = $_POST['id'] ?? 0;

    // Critical security check: prevent an admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        $response['message'] = 'Error: You cannot delete your own account.';
    } elseif ($user_id > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'User deleted successfully.'];
        } else {
            $response['message'] = 'Error deleting user.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Invalid ID provided.';
    }
}

$conn->close();
echo json_encode($response);