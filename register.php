<?php
// register.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php'; // We can create this file for common functions

$errors = [];
$success_message = '';

// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize and retrieve form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // --- Validation ---
    
    // Check for empty fields
    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $errors[] = "All fields are required.";
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check if passwords match
    if ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    }
    
    // Check password strength (optional but recommended)
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    // If there are no validation errors so far, check the database
    if (empty($errors)) {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "Username or email already taken.";
        }
        $stmt->close();
    }

    // --- Process Registration ---
    
    // If there are still no errors, create the user
    if (empty($errors)) {
        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user into the database
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
        $stmt->bind_param("sss", $username, $email, $hashed_password);
        
        if ($stmt->execute()) {
            $success_message = "Registration successful! You can now <a href='login.php'>log in</a>.";
        } else {
            $errors[] = "Registration failed. Please try again later.";
        }
        $stmt->close();
    }
    $conn->close();
}

// Include the header
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
        <div class="success-message" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724; padding: 10px; margin-bottom: 15px; border: 1px solid transparent; border-radius: .25rem;">
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
// Include the footer
include 'includes/footer.php';
?>