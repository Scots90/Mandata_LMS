<?php
// login.php
session_start();
require_once 'includes/functions.php';

// If the user is already logged in, redirect them away to the dashboard.
if (isLoggedIn()) {
    redirect('dashboard.php');
}

require_once 'includes/db_connect.php';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                // After a successful login, redirect directly to the dashboard.
                redirect('dashboard.php');
            }
        }
        $error_message = "Invalid username or password.";
    }
}

include 'includes/header.php';
?>
<div class="login-container">
    <h2>Login</h2>
    <?php if ($error_message): ?><div class="error-message"><?php echo $error_message; ?></div><?php endif; ?>
    <form action="login.php" method="post" class="login-form">
        <div class="form-group"><label for="username">Username</label><input type="text" name="username" id="username" required></div>
        <div class="form-group"><label for="password">Password</label><input type="password" name="password" id="password" required></div>
        <button type="submit" class="button">Login</button>
    </form>
</div>
<?php include 'includes/footer.php'; ?>