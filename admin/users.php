<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Security check using the new role functions
if (!isLoggedIn() || (!isAdmin() && !isManager())) {
    header("Location: ../login.php");
    exit();
}

$feedback = '';

// Handle 'Add User' POST request
if ((isAdmin() || isManager()) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $roles_to_assign = $_POST['roles'] ?? [];
    
    // Admins can assign a manager; managers auto-assign themselves
    $manager_id = isAdmin() ? (!empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null) : $_SESSION['user_id'];

    if (!empty($username) && !empty($email) && !empty($password) && !empty($roles_to_assign)) {
        // For managers, ensure they are only creating students
        if(isManager() && !isAdmin()){
            $student_role_id = 3; // Assuming 3 is the student role ID
            $is_student_only = true;
            foreach($roles_to_assign as $role_id){
                if($role_id != $student_role_id){
                    $is_student_only = false;
                    break;
                }
            }
            if(!$is_student_only){
                $feedback = "Error: Managers can only create users with the 'Student' role.";
                $roles_to_assign = []; // Prevent execution
            }
        }
    }


    if (!empty($username) && !empty($email) && !empty($password) && !empty($roles_to_assign)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $conn->begin_transaction();
        try {
            $stmt_user = $conn->prepare("INSERT INTO users (username, email, password, manager_id) VALUES (?, ?, ?, ?)");
            $stmt_user->bind_param("sssi", $username, $email, $hashed_password, $manager_id);
            $stmt_user->execute();
            $new_user_id = $conn->insert_id;
            $stmt_user->close();

            $stmt_role = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            foreach ($roles_to_assign as $role_id) {
                $stmt_role->bind_param("ii", $new_user_id, $role_id);
                $stmt_role->execute();
            }
            $stmt_role->close();

            $conn->commit();
            $feedback = 'User added successfully!';
        } catch (Exception $e) {
            $conn->rollback();
            $feedback = 'Error: ' . $e->getMessage();
        }
    } else if (empty($feedback)) {
        $feedback = 'All fields and at least one role are required.';
    }
}

// Fetch all available roles for the forms
$roles_result = $conn->query("SELECT * FROM roles ORDER BY role_name");
$all_roles = $roles_result->fetch_all(MYSQLI_ASSOC);

// Fetch all managers for the dropdown
$managers = [];
$manager_result = $conn->query("SELECT u.user_id, u.username FROM users u JOIN user_roles ur ON u.user_id = ur.user_id WHERE ur.role_id = 2 ORDER BY u.username");
if($manager_result) { while($row = $manager_result->fetch_assoc()) { $managers[] = $row; } }

// Fetch users
$users_sql = "
    SELECT u.user_id, u.username, u.email, u.manager_id, m.username as manager_name, GROUP_CONCAT(r.role_name ORDER BY r.role_id) as roles
    FROM users u
    LEFT JOIN user_roles ur ON u.user_id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.role_id
    LEFT JOIN users m ON u.manager_id = m.user_id";

// If the user is a manager but not an admin, only show their managed users
if (isManager() && !isAdmin()) {
    $users_sql .= " WHERE u.manager_id = " . (int)$_SESSION['user_id'];
}

$users_sql .= " GROUP BY u.user_id ORDER BY u.username";
$users_result = $conn->query($users_sql);

include '../includes/header.php';
?>

<h2>Manage Users</h2>
<p>
    <?php if (isAdmin()): ?>
        Add new users or edit their roles and managers.
    <?php else: // Manager view ?>
        Add new students to your team.
    <?php endif; ?>
</p>

<div class="dashboard-grid">
    <div class="card responsive-table" id="manage-users-table" style="grid-column: 1 / -1;">
        <h3>Existing Users</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th>Manager</th>
                    <?php if (isAdmin()): ?><th style="width: 150px;">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($users_result && $users_result->num_rows > 0): ?>
                    <?php while($row = $users_result->fetch_assoc()): ?>
                        <tr id="row-user-<?php echo $row['user_id']; ?>">
                            <td data-label="Username"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($row['email']); ?></td>
                            <td data-label="Roles"><?php echo htmlspecialchars(str_replace(',', ', ', $row['roles'])); ?></td>
                            <td data-label="Manager"><?php echo htmlspecialchars($row['manager_name'] ?? 'N/A'); ?></td>
                            <?php if (isAdmin()): ?>
                            <td data-label="Actions" class="actions-cell">
                                <button class="button edit-user-btn" data-id="<?php echo $row['user_id']; ?>">Edit</button>
                                <button class="button delete-user-btn" data-id="<?php echo $row['user_id']; ?>">Delete</button>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (isAdmin() || isManager()): ?>
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
                <label for="roles">Roles (Hold Ctrl/Cmd to select multiple)</label>
                <select name="roles[]" multiple required style="width:100%; padding: 8px; height: 100px;">
                    <?php foreach($all_roles as $role): ?>
                        <?php if(isAdmin() || $role['role_name'] == 'student'): ?>
                            <option value="<?php echo $role['role_id']; ?>"><?php echo ucfirst($role['role_name']); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (isAdmin()): ?>
                <div class="form-group">
                    <label for="manager_id">Assign Manager (Optional)</label>
                    <select name="manager_id" style="width:100%; padding: 8px;">
                        <option value="">-- None --</option>
                        <?php foreach($managers as $manager): ?>
                            <option value="<?php echo $manager['user_id']; ?>"><?php echo htmlspecialchars($manager['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <button type="submit" name="add_user" class="button">Add User</button>
        </form>
    </div>
    <?php endif; ?>
</div>


<div id="edit-user-modal" style="display:none;" title="Edit User">
    <form id="edit-user-form">
        <input type="hidden" id="edit-user-id" name="user_id">
        <p>Editing user: <strong id="edit-username-display"></strong></p>
        <div class="form-group">
            <label>Roles</label>
            <div id="edit-roles-container">
                <?php foreach($all_roles as $role): ?>
                    <label style="display: block;">
                        <input type="checkbox" name="roles[]" value="<?php echo $role['role_id']; ?>">
                        <?php echo ucfirst($role['role_name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="edit-manager-id">Manager</label>
            <select id="edit-manager-id" name="manager_id" style="width:100%; padding: 8px;">
                <option value="">-- None --</option>
                <?php foreach($managers as $manager): ?>
                    <option value="<?php echo $manager['user_id']; ?>"><?php echo htmlspecialchars($manager['username']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>