<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Security: Only admins can get user details for editing.
if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'No user ID provided.']);
    exit();
}

$response = ['status' => 'error', 'message' => 'User not found.'];

// Fetch user's main details
$stmt_user = $conn->prepare("SELECT user_id, username, manager_id FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if ($user_data) {
    // Fetch user's assigned roles
    $stmt_roles = $conn->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
    $stmt_roles->bind_param("i", $user_id);
    $stmt_roles->execute();
    $roles_result = $stmt_roles->get_result();
    $assigned_roles = [];
    while ($row = $roles_result->fetch_assoc()) {
        $assigned_roles[] = $row['role_id'];
    }
    $stmt_roles->close();
    
    $user_data['roles'] = $assigned_roles;
    $response = ['status' => 'success', 'data' => $user_data];
}

$conn->close();
echo json_encode($response);
?>