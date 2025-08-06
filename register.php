<?php
// register.php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $errors[] = "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Username or email already taken.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $conn->begin_transaction();
        try {
            $stmt_user = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt_user->bind_param("sss", $username, $email, $hashed_password);
            $stmt_user->execute();
            $new_user_id = $conn->insert_id;
            $stmt_user->close();

            // Assign the 'student' role (role_id = 3)
            $stmt_role = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, 3)");
            $stmt_role->bind_param("i", $new_user_id);
            $stmt_role->execute();
            $stmt_role->close();

            $conn->commit();
            $success_message = "Registration successful! You can now <a href='login.php'>log in</a>.";

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Registration failed. Please try again later.";
        }
    }
    $conn->close();
}

include 'includes/header.php';
?>

<div class="login-container"> <h2>Register</h2>
    <p>Create an account to access the LMS.</p>

    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <?php foreach ($errors as $error): ?>
                <p><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="success-message">
            <p><?php echo $success_message; ?></p>
        </div>
    <?php else: ?>
        <form action="register.php" method="post" class="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" name="password_confirm" id="password_confirm" required>
            </div>
            <button type="submit" class="button">Register</button>
        </form>
        <p style="margin-top: 1rem;">Already have an account? <a href="login.php">Login here</a>.</p>
    <?php endif; ?>
</div>

<?php
include 'includes/footer.php';
?>