<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$action = $_POST['action'] ?? '';
$response = ['status' => 'error', 'message' => 'Invalid action.'];

// --- UPDATE USER ACTION (REWRITTEN) ---
if ($action === 'update_user') {
    $user_id = $_POST['user_id'] ?? 0;
    $roles = $_POST['roles'] ?? [];
    $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;

    if ($user_id > 0 && !empty($roles)) {
        $conn->begin_transaction();
        try {
            // Update the manager_id in the users table
            $stmt_manager = $conn->prepare("UPDATE users SET manager_id = ? WHERE user_id = ?");
            $stmt_manager->bind_param("ii", $manager_id, $user_id);
            $stmt_manager->execute();
            $stmt_manager->close();

            // Delete existing roles for the user
            $stmt_delete = $conn->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $stmt_delete->bind_param("i", $user_id);
            $stmt_delete->execute();
            $stmt_delete->close();
            
            // Insert the new roles
            $stmt_insert = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            foreach ($roles as $role_id) {
                $stmt_insert->bind_param("ii", $user_id, $role_id);
                $stmt_insert->execute();
            }
            $stmt_insert->close();
            
            $conn->commit();
            $response = ['status' => 'success', 'message' => 'User updated successfully.'];

        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = 'Database error: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Invalid data provided. User ID and at least one role are required.';
    }
}


// --- DELETE USER ACTION ---
if ($action === 'delete_user') {
    $user_id_to_delete = $_POST['id'] ?? 0;

    if ($user_id_to_delete == $_SESSION['user_id']) {
        $response['message'] = 'Error: You cannot delete your own account.';
    } elseif ($user_id_to_delete > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_to_delete);
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
?>