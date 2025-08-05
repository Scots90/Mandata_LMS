<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

$feedback = '';

// Handle 'Add User' POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (!empty($username) && !empty($email) && !empty($password) && !empty($role)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
        $feedback = $stmt->execute() ? 'User added successfully!' : 'Error: Username or email may already exist.';
        $stmt->close();
    } else {
        $feedback = 'All fields are required to add a user.';
    }
}

// Fetch all users
$users = $conn->query("SELECT user_id, username, email, role, created_at FROM users ORDER BY username");

include '../includes/header.php';
?>

<h2>Manage Users</h2>
<p>Add new users or edit existing ones directly from the table.</p>

<div class="dashboard-grid">
    <div class="card" style="grid-column: 1 / -1;">
        <h3>Existing Users</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users->num_rows > 0): ?>
                    <?php while($row = $users->fetch_assoc()): ?>
                        <tr id="row-user-<?php echo $row['user_id']; ?>">
                            <td>
                                <span class="view-mode"><?php echo htmlspecialchars($row['username']); ?></span>
                                <input type="text" class="edit-mode edit-username" value="<?php echo htmlspecialchars($row['username']); ?>" style="display:none;">
                            </td>
                            <td>
                                <span class="view-mode"><?php echo htmlspecialchars($row['email']); ?></span>
                                <input type="email" class="edit-mode edit-email" value="<?php echo htmlspecialchars($row['email']); ?>" style="display:none;">
                            </td>
                             <td>
                                <span class="view-mode"><?php echo ucfirst($row['role']); ?></span>
                                <select class="edit-mode edit-role" style="display:none;">
                                    <option value="student" <?php echo ($row['role'] == 'student') ? 'selected' : ''; ?>>Student</option>
                                    <option value="admin" <?php echo ($row['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </td>
                            <td class="actions-cell">
                                <button class="button edit-user-btn" data-id="<?php echo $row['user_id']; ?>">Edit</button>
                                <button class="button save-user-btn" data-id="<?php echo $row['user_id']; ?>" style="display:none;">Save</button>
                                <button class="button delete-user-btn" data-id="<?php echo $row['user_id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Add New User</h3>
        <?php if ($feedback) echo "<p><strong>$feedback</strong></p>"; ?>
        <form action="users.php" method="post" class="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" required>
            </div>
             <div class="form-group">
                <label for="role">Role</label>
                <select name="role" style="width:100%; padding: 8px;">
                    <option value="student">Student</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" name="add_user" class="button">Add User</button>
        </form>
    </div>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>