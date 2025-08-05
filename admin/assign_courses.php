<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

$feedback = '';
$feedback_type = 'success';

// Handle 'Assign Course' POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_course'])) {
    $course_id = $_POST['course_id_to_assign'];
    $user_ids = $_POST['user_ids'] ?? [];

    if (!empty($course_id) && !empty($user_ids)) {
        $assigned_count = 0;
        $already_active_count = 0;
        
        $check_stmt = $conn->prepare("SELECT enrollment_id FROM user_courses WHERE user_id = ? AND course_id = ? AND is_active = 1");
        $insert_stmt = $conn->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (?, ?)");

        foreach ($user_ids as $user_id) {
            $check_stmt->bind_param("ii", $user_id, $course_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows == 0) {
                $insert_stmt->bind_param("ii", $user_id, $course_id);
                if ($insert_stmt->execute()) {
                    $assigned_count++;
                }
            } else {
                $already_active_count++;
            }
        }
        
        $check_stmt->close();
        $insert_stmt->close();
        
        $feedback = "Assigned course to $assigned_count new user(s).";
        if ($already_active_count > 0) {
            $feedback .= " $already_active_count user(s) already had an active enrollment for this course.";
        }
    } else {
        $feedback = 'Please select a product, category, course, and at least one user.';
        $feedback_type = 'error';
    }
}

// Fetch data for the form
$products = $conn->query("SELECT product_id, product_name FROM products ORDER BY product_name");
$users = $conn->query("SELECT user_id, username FROM users WHERE role = 'student' ORDER BY username");
$enrollments_result = $conn->query("
    SELECT uc.enrollment_id, u.username, c.course_name
    FROM user_courses uc JOIN users u ON uc.user_id = u.user_id JOIN courses c ON uc.course_id = c.course_id
    WHERE u.role = 'student' AND uc.is_active = 1
    ORDER BY u.username, c.course_name
");

include '../includes/header.php';
?>

<h2>Assign & Manage Course Allocations</h2>
<p>Filter by product and category, then select a course to enroll users.</p>

<?php if ($feedback): ?>
    <div class="<?php echo $feedback_type === 'success' ? 'success-message' : 'error-message'; ?>" style="margin-bottom: 20px;">
        <?php echo htmlspecialchars($feedback); ?>
    </div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="card">
        <h3>Assign a Course</h3>
        <form action="assign_courses.php" method="post" class="login-form">
            <div class="form-group">
                <label>1. Select Product</label>
                <select id="product_filter" required style="width:100%; padding: 8px;">
                     <option value="">-- Choose a Product --</option>
                    <?php if ($products && $products->num_rows > 0): ?>
                        <?php while($row = $products->fetch_assoc()): ?>
                            <option value="<?php echo $row['product_id']; ?>"><?php echo htmlspecialchars($row['product_name']); ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>2. Select Category</label>
                <select id="category_filter" required disabled style="width:100%; padding: 8px;">
                     <option value="">-- Select a Product First --</option>
                </select>
            </div>
            <div class="form-group">
                <label>3. Select Course</label>
                <select id="course_select" name="course_id_to_assign" required disabled style="width:100%; padding: 8px;">
                     <option value="">-- Select a Category First --</option>
                </select>
            </div>
            <div class="form-group">
                <label>4. Select Users (Hold Ctrl/Cmd to select multiple)</label>
                <select name="user_ids[]" multiple required style="width:100%; height: 250px;">
                    <?php if($users && $users->num_rows > 0): while($row = $users->fetch_assoc()): ?>
                        <option value="<?php echo $row['user_id']; ?>"><?php echo htmlspecialchars($row['username']); ?></option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <button type="submit" name="assign_course" class="button">Assign Course</button>
        </form>
    </div>

    <div class="card">
        <h3>Current Allocations</h3>
        <div style="max-height: 520px; overflow-y: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Assigned Course</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($enrollments_result && $enrollments_result->num_rows > 0): ?>
                        <?php while($enrollment = $enrollments_result->fetch_assoc()): ?>
                            <tr id="enrollment-row-<?php echo $enrollment['enrollment_id']; ?>">
                                <td><?php echo htmlspecialchars($enrollment['username']); ?></td>
                                <td><?php echo htmlspecialchars($enrollment['course_name']); ?></td>
                                <td class="actions-cell">
                                    <button class="button delete-btn deallocate-btn" data-id="<?php echo $enrollment['enrollment_id']; ?>">Deallocate</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No courses are currently assigned to users.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>